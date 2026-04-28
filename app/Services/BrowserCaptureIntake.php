<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\OutboundSignal;
use App\Models\ReportingCycle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use SimpleXMLElement;

class BrowserCaptureIntake
{
    public function __construct(
        protected SmartCreditCaptureParser $smartCreditParser,
        protected CreditKarmaCaptureParser $creditKarmaParser,
        protected InstallationFeedbackPolicy $feedbackPolicy,
        protected ReportFeedbackSignalBuilder $reportFeedbackSignalBuilder,
        protected SignalSanitizer $signalSanitizer,
        protected OfficeGrowthRuntime $growth,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(Client $client, ReportingCycle $cycle, array $payload, ?User $user = null): BrowserCapture
    {
        $uploadedFile = $payload['capture_file'] ?? null;
        $fileInfo = $uploadedFile instanceof UploadedFile
            ? $this->storeFile($client, $uploadedFile)
            : [
                'file_name' => null,
                'file_path' => null,
                'mime_type' => null,
                'archive_format' => null,
                'raw_contents' => null,
            ];

        $binaryArchive = $fileInfo['archive_format'] === 'webarchive-binary' && is_string($fileInfo['file_path'])
            ? $this->extractBinaryWebArchive($fileInfo['file_path'])
            : null;
        $decodedJson = $this->decodeExtensionJson($uploadedFile, $fileInfo['raw_contents']);
        $sourceType = $this->resolveSourceType($payload, $uploadedFile, $decodedJson);
        $rawHtmlCandidate = $this->rawHtmlCandidate($payload, $decodedJson, $binaryArchive, $fileInfo['raw_contents'], $fileInfo['archive_format']);
        $html = $this->resolveHtml($payload, $fileInfo['raw_contents'], $fileInfo['archive_format'], $decodedJson, $binaryArchive);
        $pageTitle = $this->firstString(
            $payload['page_title'] ?? null,
            $decodedJson['title'] ?? null,
            $this->extractTitleFromHtml($html),
        );
        $pageUrl = $this->firstString(
            $payload['page_url'] ?? null,
            $decodedJson['url'] ?? null,
            $binaryArchive['url'] ?? null,
            $this->extractSavedFromUrlFromHtml($rawHtmlCandidate),
            $this->extractSavedFromUrlFromHtml($html),
            $this->extractCanonicalUrlFromHtml($html),
        );
        $browserName = $this->firstString(
            $payload['browser_name'] ?? null,
            $decodedJson['browser'] ?? null,
            $decodedJson['browser_name'] ?? null,
            $sourceType === 'safari_webarchive' ? 'Safari' : null,
        );
        $extractedText = $this->extractText($html);
        $smartCredit = $this->smartCreditParser->parse(
            $rawHtmlCandidate !== '' ? $rawHtmlCandidate : $html,
            $pageTitle,
            $pageUrl,
        );
        $creditKarma = $this->creditKarmaParser->parse(
            $rawHtmlCandidate !== '' ? $rawHtmlCandidate : $html,
            $pageTitle,
            $pageUrl,
        );
        $providerCapture = $creditKarma ?? $smartCredit;
        $metadata = array_filter([
            'snapshot_pipeline' => 'browser_evidence',
            'ingestion_mode' => $sourceType,
            'companion_capture' => $decodedJson ? [
                'package_name' => $this->firstString(
                    $payload['package_name'] ?? null,
                    $decodedJson['package_name'] ?? null,
                    $decodedJson['package'] ?? null,
                    'CreditSoft Browser Companion',
                ),
                'user_agent' => $this->firstString(
                    $payload['user_agent'] ?? null,
                    $decodedJson['user_agent'] ?? null,
                ),
                'captured_at' => $payload['captured_at'] ?? $decodedJson['captured_at'] ?? null,
                'selection' => $this->firstString(
                    $payload['selection_text'] ?? null,
                    $decodedJson['selection'] ?? null,
                    $decodedJson['selection_text'] ?? null,
                ),
                'note' => $this->firstString(
                    $payload['operator_note'] ?? null,
                    $decodedJson['operator_note'] ?? null,
                    $decodedJson['note'] ?? null,
                ),
            ] : ($sourceType === 'companion_capture' ? [
                'package_name' => $this->firstString(
                    $payload['package_name'] ?? null,
                    'CreditSoft Browser Companion',
                ),
                'user_agent' => $this->firstString($payload['user_agent'] ?? null),
                'captured_at' => $payload['captured_at'] ?? null,
                'selection' => $this->firstString($payload['selection_text'] ?? null),
                'note' => $this->firstString($payload['operator_note'] ?? null),
            ] : null),
            'provider_key' => data_get($providerCapture, 'provider'),
            'provider_capture' => $providerCapture,
            'import_profile' => data_get($providerCapture, 'profile'),
            'smartcredit' => $smartCredit,
            'credit_karma' => $creditKarma,
            'parse_status' => $html !== '' ? 'parsed' : 'stored_only',
        ]);

        $existingDuplicate = $this->findExistingDuplicate(
            client: $client,
            cycle: $cycle,
            sourceType: $sourceType,
            pageTitle: $pageTitle,
            pageUrl: $pageUrl,
            html: $html,
            extractedText: $extractedText,
            metadata: $metadata,
        );

        if ($existingDuplicate) {
            if (is_string($fileInfo['file_path']) && $fileInfo['file_path'] !== '' && File::exists($fileInfo['file_path'])) {
                File::delete($fileInfo['file_path']);
            }

            $existingDuplicate->setAttribute('ingest_reused', true);

            return $existingDuplicate;
        }

        $capture = BrowserCapture::create([
            'client_id' => $client->getKey(),
            'reporting_cycle_id' => $cycle->getKey(),
            'user_id' => $user?->getKey(),
            'source_type' => $sourceType,
            'browser_name' => $browserName,
            'page_title' => $pageTitle,
            'page_url' => $pageUrl,
            'file_name' => $fileInfo['file_name'],
            'file_path' => $fileInfo['file_path'],
            'mime_type' => $fileInfo['mime_type'],
            'archive_format' => $fileInfo['archive_format'],
            'content_html' => $html !== '' ? $html : null,
            'extracted_text' => $extractedText !== '' ? $extractedText : null,
            'metadata' => $metadata,
            'imported_at' => now(),
        ]);

        $this->syncClientScoreSnapshot($client, $providerCapture);
        $this->flagClientReportReceived($client, $cycle, $capture, $providerCapture);
        $this->queueReportFeedbackSignal($client, $cycle, $capture, $providerCapture);

        return $capture;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function findExistingDuplicate(
        Client $client,
        ReportingCycle $cycle,
        string $sourceType,
        ?string $pageTitle,
        ?string $pageUrl,
        string $html,
        string $extractedText,
        array $metadata,
    ): ?BrowserCapture {
        $providerKey = data_get($metadata, 'provider_key');
        $importProfile = data_get($metadata, 'import_profile');
        $contentFingerprint = sha1(trim($html !== '' ? $html : $extractedText));

        return $client->browserCaptures()
            ->where('reporting_cycle_id', $cycle->getKey())
            ->where('source_type', $sourceType)
            ->when(filled($pageTitle), fn ($query) => $query->where('page_title', $pageTitle))
            ->when(filled($pageUrl), fn ($query) => $query->where('page_url', $pageUrl))
            ->get()
            ->first(function (BrowserCapture $capture) use ($providerKey, $importProfile, $contentFingerprint): bool {
                return data_get($capture->metadata, 'provider_key') === $providerKey
                    && data_get($capture->metadata, 'import_profile') === $importProfile
                    && sha1(trim((string) ($capture->content_html !== null && $capture->content_html !== ''
                        ? $capture->content_html
                        : $capture->extracted_text))) === $contentFingerprint;
            });
    }

    /**
     * @param  array<string, mixed>|null  $providerCapture
     */
    protected function queueReportFeedbackSignal(
        Client $client,
        ReportingCycle $cycle,
        BrowserCapture $capture,
        ?array $providerCapture,
    ): void {
        if (! is_array($providerCapture) || ! $this->feedbackPolicy->reportFeedbackEnabled()) {
            return;
        }

        $payload = $this->reportFeedbackSignalBuilder->build($client, $cycle, $capture, $providerCapture);
        $sanitized = $this->signalSanitizer->sanitize($payload, 'creditsoft.report_feedback_allowlist');

        if ($sanitized === []) {
            return;
        }

        OutboundSignal::create([
            'client_id' => $client->getKey(),
            'event_type' => 'report_feedback.capture_imported',
            'visibility' => 'aggregate_report_feedback',
            'payload' => $payload,
            'sanitized_payload' => $sanitized,
            'status' => 'pending',
            'queued_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $providerCapture
     */
    protected function flagClientReportReceived(
        Client $client,
        ReportingCycle $cycle,
        BrowserCapture $capture,
        ?array $providerCapture,
    ): void {
        $providerKey = (string) (
            data_get($providerCapture, 'provider')
            ?? data_get($capture->metadata, 'provider_key')
            ?? ''
        );

        $metadata = is_array($client->metadata) ? $client->metadata : [];
        $office = $this->growth->officeContext();
        $message = collect([
            sprintf(
                '%s received your updated credit report and will review it soon.',
                $office['company_name'] ?? config('app.name', 'CreditSoft'),
            ),
            filled($office['support_email'] ?? null)
                ? sprintf('Questions? %s can help.', $office['support_email'])
                : null,
        ])->filter()->implode(' ');

        data_set($metadata, 'portal.report_update_pending', true);
        data_set($metadata, 'portal.report_update_message', $message);
        data_set($metadata, 'portal.latest_report_received_at', now()->toIso8601String());
        data_set($metadata, 'portal.latest_report_provider_key', $providerKey !== '' ? $providerKey : null);
        data_set($metadata, 'portal.latest_report_cycle_id', $cycle->getKey());
        data_set($metadata, 'portal.latest_report_capture_id', $capture->getKey());

        $client->forceFill([
            'metadata' => $metadata,
        ])->save();

        OutboundSignal::create([
            'client_id' => $client->getKey(),
            'event_type' => 'client_portal.report_received',
            'visibility' => 'client_portal',
            'payload' => [
                'event_type' => 'client_portal.report_received',
                'recorded_at' => now()->toIso8601String(),
                'client_cuid' => $client->cuid,
                'cycle_id' => $cycle->getKey(),
                'cycle_label' => $cycle->cycle_label,
                'capture_id' => $capture->getKey(),
                'provider_key' => $providerKey !== '' ? $providerKey : null,
                'message' => $message,
            ],
            'sanitized_payload' => [
                'event_type' => 'client_portal.report_received',
                'recorded_at' => now()->toIso8601String(),
                'client_cuid' => $client->cuid,
                'cycle_label' => $cycle->cycle_label,
                'provider_key' => $providerKey !== '' ? $providerKey : null,
            ],
            'status' => 'pending',
            'queued_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $providerCapture
     */
    protected function syncClientScoreSnapshot(Client $client, ?array $providerCapture): void
    {
        if (! is_array($providerCapture)) {
            return;
        }

        $providerKey = (string) ($providerCapture['provider'] ?? '');
        $creditScore = null;

        if ($providerKey === 'credit_karma') {
            $scoresByBureau = [];
            $captures = $client->browserCaptures()
                ->latest('imported_at')
                ->get();

            foreach ($captures as $candidate) {
                if (data_get($candidate->metadata, 'provider_key') !== 'credit_karma') {
                    continue;
                }

                $bureau = (string) data_get($candidate->metadata, 'provider_capture.bureau', '');

                if ($bureau === '' || array_key_exists($bureau, $scoresByBureau)) {
                    continue;
                }

                $score = data_get($candidate->metadata, 'provider_capture.scores.credit');

                if (is_numeric($score)) {
                    $scoresByBureau[$bureau] = (int) $score;
                }
            }

            if ($scoresByBureau !== []) {
                $creditScore = min($scoresByBureau);
            }
        } elseif (($providerCapture['profile'] ?? null) === 'score_tracker') {
            $candidateScore = data_get($providerCapture, 'scores.credit');

            if (is_numeric($candidateScore)) {
                $creditScore = (int) $candidateScore;
            }
        }

        if (! is_numeric($creditScore)) {
            return;
        }

        $creditScore = (int) $creditScore;

        if ($client->current_score === $creditScore) {
            return;
        }

        $client->forceFill([
            'current_score' => $creditScore,
        ])->save();
    }

    /**
     * @return array{file_name:?string,file_path:?string,mime_type:?string,archive_format:?string,raw_contents:?string}
     */
    protected function storeFile(Client $client, UploadedFile $file): array
    {
        $directory = rtrim((string) config('creditsoft.browser_capture_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$client->getKey();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = now()->format('YmdHis').'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'capture').'.'.$extension;

        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $rawContents = File::get($path);

        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'archive_format' => $this->archiveFormat($extension, $rawContents),
            'raw_contents' => $rawContents,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeExtensionJson(?UploadedFile $file, ?string $rawContents): ?array
    {
        if (! $file || strtolower($file->getClientOriginalExtension() ?: '') !== 'json' || ! is_string($rawContents)) {
            return null;
        }

        $decoded = json_decode($rawContents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $decodedJson
     */
    protected function resolveSourceType(array $payload, ?UploadedFile $uploadedFile, ?array $decodedJson): string
    {
        $sourceType = strtolower((string) ($payload['source_type'] ?? ''));

        if ($sourceType !== '') {
            return match ($sourceType) {
                'extension_capture' => 'companion_capture',
                default => $sourceType,
            };
        }

        if ($decodedJson !== null) {
            return 'companion_capture';
        }

        if (strtolower((string) ($uploadedFile?->getClientOriginalExtension() ?? '')) === 'webarchive') {
            return 'safari_webarchive';
        }

        return 'browser_capture';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $decodedJson
     * @param  array<string, mixed>|null  $binaryArchive
     */
    protected function resolveHtml(array $payload, ?string $rawContents, ?string $archiveFormat, ?array $decodedJson, ?array $binaryArchive): string
    {
        $html = $this->rawHtmlCandidate($payload, $decodedJson, $binaryArchive, $rawContents, $archiveFormat);

        if ($html === '' && is_string($rawContents) && str_starts_with((string) $archiveFormat, 'webarchive')) {
            $html = $this->extractHtmlFromWebArchive($rawContents) ?? '';
        }

        if ($html === '' && is_string($rawContents) && $archiveFormat === 'mhtml') {
            $html = $this->extractHtmlFromMhtml($rawContents) ?? '';
        }

        if (
            $html === ''
            && is_string($rawContents)
            && ! in_array($archiveFormat, ['json'], true)
            && ! str_starts_with((string) $archiveFormat, 'webarchive')
        ) {
            $html = $rawContents;
        }

        return $this->normalizeHtml($html);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $decodedJson
     * @param  array<string, mixed>|null  $binaryArchive
     */
    protected function rawHtmlCandidate(array $payload, ?array $decodedJson, ?array $binaryArchive, ?string $rawContents, ?string $archiveFormat): string
    {
        $html = trim((string) (
            $payload['html']
            ?? $decodedJson['dom_html']
            ?? $decodedJson['page_html']
            ?? $decodedJson['html']
            ?? $binaryArchive['html']
            ?? ''
        ));

        if (
            $html === ''
            && is_string($rawContents)
            && ! in_array($archiveFormat, ['json'], true)
            && ! str_starts_with((string) $archiveFormat, 'webarchive')
        ) {
            $html = $rawContents;
        }

        return $html;
    }

    protected function archiveFormat(string $extension, string $rawContents): string
    {
        return match ($extension) {
            'webarchive' => str_starts_with($rawContents, 'bplist00') ? 'webarchive-binary' : 'webarchive-xml',
            'json' => 'json',
            'mhtml' => 'mhtml',
            default => 'html',
        };
    }

    protected function extractHtmlFromWebArchive(string $rawContents): ?string
    {
        if (str_starts_with($rawContents, 'bplist00')) {
            return null;
        }

        try {
            $xml = new SimpleXMLElement($rawContents);
        } catch (\Throwable) {
            return null;
        }

        $dict = $xml->dict ?? null;

        if (! $dict) {
            return null;
        }

        $items = array_values(iterator_to_array($dict->children(), false));

        for ($index = 0; $index < count($items); $index++) {
            if ($items[$index]->getName() !== 'key' || (string) $items[$index] !== 'WebMainResource') {
                continue;
            }

            $resourceDict = $items[$index + 1] ?? null;

            if (! $resourceDict || $resourceDict->getName() !== 'dict') {
                continue;
            }

            $resourceItems = array_values(iterator_to_array($resourceDict->children(), false));

            for ($resourceIndex = 0; $resourceIndex < count($resourceItems); $resourceIndex++) {
                if ($resourceItems[$resourceIndex]->getName() !== 'key' || (string) $resourceItems[$resourceIndex] !== 'WebResourceData') {
                    continue;
                }

                $dataNode = $resourceItems[$resourceIndex + 1] ?? null;

                if (! $dataNode || $dataNode->getName() !== 'data') {
                    continue;
                }

                $decoded = base64_decode(preg_replace('/\s+/', '', (string) $dataNode) ?: '', true);

                if ($decoded !== false && $decoded !== '') {
                    return $decoded;
                }
            }
        }

        return null;
    }

    protected function extractHtmlFromMhtml(string $rawContents): ?string
    {
        if (! str_contains($rawContents, 'Content-Type: text/html')) {
            return null;
        }

        $parts = preg_split("/\r?\n\r?\n/", $rawContents, 2);

        if (! is_array($parts) || count($parts) < 2) {
            return null;
        }

        return $parts[1] !== '' ? $parts[1] : null;
    }

    protected function normalizeHtml(string $html): string
    {
        $normalized = trim($html);

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/<!--.*?-->/s', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/>\s+</', '><', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s{2,}/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    protected function extractTitleFromHtml(string $html): ?string
    {
        if ($html === '' || preg_match('/<title>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $title !== '' ? $title : null;
    }

    protected function extractCanonicalUrlFromHtml(string $html): ?string
    {
        if ($html === '') {
            return null;
        }

        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches) !== 1
            && preg_match('/<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches) !== 1) {
            return null;
        }

        $url = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $url !== '' ? $url : null;
    }

    protected function extractSavedFromUrlFromHtml(string $html): ?string
    {
        if ($html === '') {
            return null;
        }

        if (preg_match('/<!--\s*saved from url=\(\d+\)(https?:\/\/[^ ]+)\s*-->/i', $html, $matches) !== 1) {
            return null;
        }

        $url = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $url !== '' ? $url : null;
    }

    /**
     * @return array{html:?string,url:?string,mime_type:?string}|null
     */
    protected function extractBinaryWebArchive(string $filePath): ?array
    {
        if (! is_file($filePath) || ! Process::timeout(1)->run(['sh', '-lc', 'command -v python3 >/dev/null 2>&1'])->successful()) {
            return null;
        }

        $script = <<<'PY'
import json
import plistlib
import sys

path = sys.argv[1]

with open(path, 'rb') as handle:
    payload = plistlib.load(handle)

main = payload.get('WebMainResource', {}) or {}
body = main.get('WebResourceData', b'')
encoding = main.get('WebResourceTextEncodingName') or 'utf-8'

if isinstance(body, str):
    html = body
else:
    html = body.decode(encoding, 'ignore')

print(json.dumps({
    'html': html,
    'url': main.get('WebResourceURL'),
    'mime_type': main.get('WebResourceMIMEType'),
}))
PY;

        $result = Process::timeout(6)->run(['python3', '-c', $script, $filePath]);

        if (! $result->successful()) {
            return null;
        }

        try {
            $decoded = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function extractText(string $html): string
    {
        $spacedHtml = preg_replace('/>\s*</', '> <', $html) ?? $html;

        return Str::of(html_entity_decode(strip_tags($spacedHtml), ENT_QUOTES | ENT_HTML5))
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(5000, '')
            ->value();
    }

    protected function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
