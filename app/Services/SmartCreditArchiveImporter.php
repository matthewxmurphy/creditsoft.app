<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\ClientProviderAccount;
use App\Models\ReportingCycle;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SmartCreditArchiveImporter
{
    public function __construct(
        protected BrowserCaptureIntake $browserCaptureIntake,
        protected SmartCreditCaptureParser $parser,
        protected AuditTrail $auditTrail,
    ) {}

    /**
     * @return Collection<int, Client>
     */
    public function importDirectory(string $directory, ?User $actor = null): Collection
    {
        $files = collect(File::files($directory))
            ->filter(fn ($file) => Str::lower($file->getExtension()) === 'html')
            ->groupBy(fn ($file) => $this->subjectFromFilename($file->getFilename()))
            ->filter(fn (Collection $group, string $subject) => $subject !== '');

        return $files->map(function (Collection $group, string $subject) use ($actor): Client {
            return $this->importSubject($subject, $group, $actor);
        })->values();
    }

    /**
     * @param  Collection<int, \SplFileInfo>  $files
     */
    protected function importSubject(string $subject, Collection $files, ?User $actor = null): Client
    {
        $client = Client::query()->firstOrCreate(
            ['cuid' => 'c_'.Str::slug("smartcredit-{$subject}-archive", '_')],
            [
                'first_name' => Str::title($subject),
                'last_name' => 'Archive',
                'status' => 'active_review',
                'assigned_to' => $actor?->getKey(),
                'goals' => 'Imported from SmartCredit archive files for comparison and follow-up.',
                'metadata' => [
                    'identity_status' => 'filename_only',
                    'archive_subject_name' => Str::title($subject),
                ],
            ],
        );

        $scoreTrackerFile = $files->first(fn ($file) => str_contains(Str::lower($file->getFilename()), 'scoretracker'));
        $cycleLabel = $this->cycleLabelForFiles($files, $scoreTrackerFile);

        $cycle = ReportingCycle::query()->firstOrCreate(
            [
                'client_id' => $client->getKey(),
                'cycle_label' => $cycleLabel,
            ],
            [
                'source' => 'browser_archive',
                'started_at' => now()->toDateString(),
                'public_summary' => 'SmartCredit archive imported into the local dossier.',
            ],
        );

        $captures = $files->map(function ($file) use ($client, $cycle, $actor) {
            $html = File::get($file->getPathname());
            $pageTitle = $this->extractTitle($html) ?: pathinfo($file->getFilename(), PATHINFO_FILENAME);

            $existing = BrowserCapture::query()
                ->where('client_id', $client->getKey())
                ->where('reporting_cycle_id', $cycle->getKey())
                ->where('page_title', $pageTitle)
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->browserCaptureIntake->ingest(
                client: $client,
                cycle: $cycle,
                payload: [
                    'source_type' => 'browser_capture',
                    'browser_name' => 'Chrome',
                    'page_title' => $pageTitle,
                    'html' => $html,
                ],
                user: $actor,
            );
        });

        $reportedName = $captures
            ->map(fn (BrowserCapture $capture) => data_get($capture->metadata, 'smartcredit.summary.name.value'))
            ->first(fn ($name) => is_string($name) && trim($name) !== '');

        if (is_string($reportedName) && trim($reportedName) !== '') {
            $normalizedName = Str::of($reportedName)
                ->replaceMatches('/\s+/', ' ')
                ->trim()
                ->title()
                ->value();

            [$firstName, $lastName] = $this->splitName($normalizedName);

            if ($firstName !== '' && $lastName !== '') {
                $metadata = array_merge($client->metadata ?? [], [
                    'identity_status' => 'report_summary_name',
                    'archive_subject_name' => Str::title($subject),
                    'imported_subject_name' => $normalizedName,
                ]);

                $client->forceFill([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'metadata' => $metadata,
                ])->saveQuietly();
            }
        }

        $officeContext = $captures
            ->map(fn (BrowserCapture $capture) => data_get($capture->metadata, 'smartcredit.office_context'))
            ->first(fn ($context) => is_array($context) && $context !== []);

        $provider = $client->providerAccounts()->firstOrNew([
            'provider_key' => 'smartcredit',
        ]);

        $provider->fill([
            'provider_label' => 'SmartCredit',
            'status' => 'import_only',
            'last_imported_at' => now(),
            'notes' => 'Imported from SmartCredit archive files. Save the customer SmartCredit login here to automate future pulls.',
            'metadata' => array_filter([
                'archive_subject_name' => Str::title($subject),
                'source' => 'smartcredit_archive',
                'imported_subject_name' => is_string($reportedName) ? Str::of($reportedName)->replaceMatches('/\s+/', ' ')->trim()->title()->value() : null,
                'import_profiles' => $captures
                    ->map(fn (BrowserCapture $capture) => data_get($capture->metadata, 'import_profile'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'office_context' => $officeContext,
            ]),
        ]);
        $provider->save();

        if ($client->wasRecentlyCreated) {
            $this->auditTrail->record(
                $actor,
                'client.imported.smartcredit',
                "Imported SmartCredit archive dossier for {$client->display_name}.",
                $client,
                [
                    'source' => 'smartcredit_archive',
                    'capture_count' => $captures->count(),
                ],
            );
        }

        return $client;
    }

    /**
     * @param  Collection<int, \SplFileInfo>  $files
     */
    protected function cycleLabelForFiles(Collection $files, ?\SplFileInfo $scoreTrackerFile): string
    {
        if ($scoreTrackerFile) {
            $html = File::get($scoreTrackerFile->getPathname());
            $parsed = $this->parser->parse($html, $this->extractTitle($html));
            $asOfDate = data_get($parsed, 'as_of_date');

            if (is_string($asOfDate) && $asOfDate !== '') {
                try {
                    return Carbon::parse($asOfDate)->format('F Y').' SmartCredit import';
                } catch (\Throwable) {
                    // fall through
                }
            }
        }

        $latestFile = $files->sortByDesc(fn ($file) => $file->getMTime())->first();
        $date = $latestFile ? Carbon::createFromTimestamp($latestFile->getMTime()) : now();

        return $date->format('F Y').' SmartCredit import';
    }

    protected function subjectFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/\s*\|\s*SmartCredit$/i', '', $base) ?? $base;
        $base = preg_replace('/\s*-\s*3-Bureau.*$/i', '', $base) ?? $base;
        $base = preg_replace('/-ScoreTracker$/i', '', $base) ?? $base;

        return trim((string) $base);
    }

    protected function extractTitle(string $html): ?string
    {
        if (! preg_match('/<title>(.*?)<\/title>/is', $html, $match)) {
            return null;
        }

        $title = trim(strip_tags(html_entity_decode((string) ($match[1] ?? ''), ENT_QUOTES | ENT_HTML5)));

        return $title !== '' ? $title : null;
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = array_shift($parts) ?: '';
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }
}
