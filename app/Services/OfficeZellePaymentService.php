<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\ClientPayment;
use App\Models\OfficeZelleSetting;
use App\Models\ZellePaymentMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfficeZellePaymentService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $setting = $this->setting();
        $messages = ZellePaymentMessage::query()
            ->with('client')
            ->latest('received_at')
            ->latest()
            ->limit(80)
            ->get();
        $processed = $messages->where('status', 'processed');
        $needsReview = $messages->where('status', 'needs_review');

        return [
            'settings' => [
                'enabled' => $setting->enabled,
                'bank_name' => $setting->bank_name ?: 'Chase',
                'imap_host' => $setting->imap_host,
                'imap_port' => $setting->imap_port ?: 993,
                'imap_encryption' => $setting->imap_encryption ?: 'ssl',
                'imap_username' => $setting->imap_username,
                'has_password' => filled($setting->imap_password),
                'masked_password' => filled($setting->imap_password) ? 'Saved on file' : null,
                'imap_folder' => $setting->imap_folder ?: 'INBOX',
                'expected_subject' => $setting->expected_subject ?: 'You received money with Zelle®',
                'trusted_domains' => $setting->trusted_domains ?: 'chase.com,zellepay.com,zelle.com,jpmorgan.com',
                'delete_after_import' => $setting->delete_after_import,
                'last_checked_at' => optional($setting->last_checked_at)?->toDateTimeString(),
                'last_error' => $setting->last_error,
            ],
            'stats' => [
                'total_messages' => $messages->count(),
                'processed_count' => $processed->count(),
                'needs_review_count' => $needsReview->count(),
                'deleted_count' => $messages->whereNotNull('deleted_from_mailbox_at')->count(),
                'total_amount' => round((float) $messages->whereNotNull('amount')->sum('amount'), 2),
                'processed_amount' => round((float) $processed->sum('amount'), 2),
                'needs_review_amount' => round((float) $needsReview->whereNotNull('amount')->sum('amount'), 2),
                'last_received_at' => optional($messages->first()?->received_at)?->toDateTimeString(),
            ],
            'messages' => $messages->map(fn (ZellePaymentMessage $message): array => [
                'id' => $message->getKey(),
                'client_id' => $message->client_id,
                'client_name' => $message->client?->display_name,
                'client_email' => $message->client?->email,
                'amount' => $message->amount !== null ? (float) $message->amount : null,
                'currency' => $message->currency,
                'status' => $message->status,
                'match_type' => $message->match_type,
                'header_status' => $message->header_status,
                'sender_name' => $message->sender_name,
                'memo_email' => $message->memo_email,
                'memo_text' => $message->memo_text,
                'transaction_id' => $message->transaction_id,
                'received_at' => optional($message->received_at)?->toDateTimeString(),
                'sent_on' => optional($message->sent_on)?->toDateString(),
                'from_email' => $message->from_email,
                'subject' => $message->subject,
                'deleted_from_mailbox_at' => optional($message->deleted_from_mailbox_at)?->toDateTimeString(),
            ])->values(),
            'imap_enabled' => function_exists('imap_open'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateSettings(array $input): OfficeZelleSetting
    {
        $setting = $this->setting();
        $password = trim((string) ($input['imap_password'] ?? ''));

        $setting->fill([
            'enabled' => (bool) ($input['enabled'] ?? false),
            'bank_name' => $this->clean((string) ($input['bank_name'] ?? 'Chase')),
            'imap_host' => $this->clean((string) ($input['imap_host'] ?? '')),
            'imap_port' => (int) ($input['imap_port'] ?? 993),
            'imap_encryption' => $this->clean((string) ($input['imap_encryption'] ?? 'ssl')),
            'imap_username' => Str::lower($this->clean((string) ($input['imap_username'] ?? ''))),
            'imap_folder' => $this->clean((string) ($input['imap_folder'] ?? 'INBOX')) ?: 'INBOX',
            'expected_subject' => $this->clean((string) ($input['expected_subject'] ?? 'You received money with Zelle®'), 500) ?: 'You received money with Zelle®',
            'trusted_domains' => $this->normalizeTrustedDomains((string) ($input['trusted_domains'] ?? 'chase.com,zellepay.com,zelle.com,jpmorgan.com')),
            'delete_after_import' => (bool) ($input['delete_after_import'] ?? false),
            'last_error' => null,
        ]);

        if ($password !== '') {
            $setting->imap_password = $password;
        }

        $setting->save();

        return $setting;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncInbox(int $limit = 100): array
    {
        $setting = $this->setting();

        if (! $setting->enabled) {
            return $this->finishSync($setting, [
                'success' => false,
                'error' => 'Turn on Zelle inbox syncing before running the checker.',
            ]);
        }

        if (! function_exists('imap_open')) {
            return $this->finishSync($setting, [
                'success' => false,
                'error' => 'The PHP IMAP extension is not enabled for this intranet server.',
            ]);
        }

        foreach (['imap_host', 'imap_username', 'imap_password'] as $field) {
            if (blank($setting->{$field})) {
                return $this->finishSync($setting, [
                    'success' => false,
                    'error' => 'Save the Zelle mailbox host, username, and password before syncing.',
                ]);
            }
        }

        $imap = @imap_open($this->mailboxString($setting), (string) $setting->imap_username, (string) $setting->imap_password);

        if (! $imap) {
            return $this->finishSync($setting, [
                'success' => false,
                'error' => imap_last_error() ?: 'Could not open the Zelle mailbox.',
            ]);
        }

        $uids = @imap_search($imap, 'ALL', SE_UID);
        $summary = [
            'success' => true,
            'fetched' => 0,
            'processed' => 0,
            'needs_review' => 0,
            'skipped' => 0,
            'deleted' => 0,
            'messages' => [],
        ];

        if (is_array($uids)) {
            rsort($uids);

            foreach (array_slice($uids, 0, max(500, $limit * 50)) as $uid) {
                if ($summary['fetched'] >= $limit) {
                    break;
                }

                $candidate = $this->fetchCandidate($imap, $setting, (string) $uid);

                if ($candidate === null) {
                    continue;
                }

                $summary['fetched']++;
                $result = $this->processCandidate($setting, $candidate);
                $summary['messages'][] = $result;

                match ($result['status'] ?? 'skipped') {
                    'processed' => $summary['processed']++,
                    'needs_review' => $summary['needs_review']++,
                    default => $summary['skipped']++,
                };

                if (
                    $setting->delete_after_import
                    && in_array((string) ($result['status'] ?? ''), ['processed', 'needs_review', 'skipped'], true)
                    && @imap_delete($imap, (string) $uid, FT_UID)
                ) {
                    @imap_expunge($imap);
                    $summary['deleted']++;
                    ZellePaymentMessage::query()
                        ->where('office_zelle_setting_id', $setting->getKey())
                        ->where('message_uid', (string) $uid)
                        ->whereNull('deleted_from_mailbox_at')
                        ->update(['deleted_from_mailbox_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        @imap_close($imap);

        return $this->finishSync($setting, $summary);
    }

    protected function setting(): OfficeZelleSetting
    {
        return OfficeZelleSetting::query()->firstOrCreate([], [
            'enabled' => false,
            'bank_name' => 'Chase',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'expected_subject' => 'You received money with Zelle®',
            'trusted_domains' => 'chase.com,zellepay.com,zelle.com,jpmorgan.com',
            'delete_after_import' => true,
        ]);
    }

    protected function mailboxString(OfficeZelleSetting $setting): string
    {
        $host = (string) $setting->imap_host;
        $port = (int) ($setting->imap_port ?: 993);
        $folder = (string) ($setting->imap_folder ?: 'INBOX');
        $encryption = strtolower((string) ($setting->imap_encryption ?: 'ssl'));
        $flags = '/imap/novalidate-cert';

        $flags .= match ($encryption) {
            'tls' => '/tls',
            'none', 'notls' => '/notls',
            default => '/ssl',
        };

        return sprintf('{%s:%d%s}%s', $host, $port, $flags, $folder);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchCandidate(mixed $imap, OfficeZelleSetting $setting, string $uid): ?array
    {
        $msgno = (int) @imap_msgno($imap, (int) $uid);

        if ($msgno <= 0) {
            return null;
        }

        $overview = @imap_fetch_overview($imap, $uid, FT_UID);
        $overview = is_array($overview) ? ($overview[0] ?? null) : null;

        if (! is_object($overview)) {
            return null;
        }

        $subject = $this->decodeHeader((string) ($overview->subject ?? ''));

        if (! $this->subjectIsExpected($subject, (string) $setting->expected_subject)) {
            return null;
        }

        $from = $this->parseAddress((string) ($overview->from ?? ''));
        $headers = (string) @imap_fetchheader($imap, (int) $uid, FT_UID);
        $headerTrust = $this->headerTrust($headers, $from['email'], $this->trustedDomains($setting));

        if (! ($headerTrust['trusted'] || $headerTrust['has_trusted_domain'])) {
            return null;
        }

        $body = $this->fetchBody($imap, $msgno);

        return [
            'mailbox' => (string) $setting->imap_username,
            'uid' => $uid,
            'message_id' => (string) ($overview->message_id ?? ''),
            'received_at' => ! empty($overview->date) ? Carbon::parse((string) $overview->date) : now(),
            'from_name' => $from['name'],
            'from_email' => $from['email'],
            'subject' => $subject,
            'body' => $body,
            'header_trust' => $headerTrust,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function processCandidate(OfficeZelleSetting $setting, array $message): array
    {
        $existing = ZellePaymentMessage::query()
            ->where('office_zelle_setting_id', $setting->getKey())
            ->where('mailbox', (string) ($message['mailbox'] ?? ''))
            ->where('message_uid', (string) ($message['uid'] ?? ''))
            ->first();

        if ($existing) {
            return ['status' => 'skipped', 'message_id' => $existing->getKey(), 'reason' => 'Already imported.'];
        }

        $subject = (string) ($message['subject'] ?? '');
        $body = (string) ($message['body'] ?? '');
        $headerTrust = (array) ($message['header_trust'] ?? []);
        $amount = $this->extractAmount($subject, $body);
        $transactionId = $this->extractTransactionId($subject, $body, (string) ($message['message_id'] ?? ''));
        $senderName = $this->extractSenderName($subject, $body, (string) ($message['from_name'] ?? ''));
        $memoText = $this->extractMemoText($subject, $body);
        $memoEmail = $this->extractFirstEmail($memoText);
        $sentOn = $this->extractSentOn($subject, $body);
        $client = $memoEmail !== '' ? $this->clientByEmail($memoEmail) : null;
        $headerStatus = ! empty($headerTrust['trusted']) ? 'trusted_sender' : 'trusted_domain_needs_header_review';
        $status = 'needs_review';
        $matchType = match (true) {
            $headerStatus !== 'trusted_sender' => 'trusted_domain_header_review',
            $memoEmail === '' => 'missing_memo_email',
            ! $client => 'memo_email_no_client',
            $amount === null => 'missing_amount',
            default => 'client_email_memo',
        };
        $payment = null;

        if ($matchType === 'client_email_memo' && $client) {
            $payment = $this->recordClientPayment($client, $amount, $transactionId, $memoText, $sentOn ?: $message['received_at']);
            $status = 'processed';
        }

        $record = ZellePaymentMessage::query()->create([
            'office_zelle_setting_id' => $setting->getKey(),
            'client_id' => $client?->getKey(),
            'client_payment_id' => $payment?->getKey(),
            'mailbox' => (string) ($message['mailbox'] ?? ''),
            'message_uid' => (string) ($message['uid'] ?? ''),
            'message_id' => $this->clean((string) ($message['message_id'] ?? ''), 255) ?: null,
            'received_at' => $message['received_at'] ?? now(),
            'sent_on' => $sentOn,
            'from_name' => $this->clean((string) ($message['from_name'] ?? ''), 255) ?: null,
            'from_email' => $this->normalizeEmail((string) ($message['from_email'] ?? '')) ?: null,
            'subject' => $this->clean($subject, 500) ?: null,
            'body_excerpt' => Str::limit($this->clean($body, 4000), 2000, ''),
            'amount' => $amount,
            'currency' => 'USD',
            'sender_name' => $senderName ?: null,
            'memo_email' => $memoEmail ?: null,
            'memo_text' => $memoText ?: null,
            'transaction_id' => $transactionId ?: null,
            'status' => $status,
            'match_type' => $matchType,
            'header_status' => $headerStatus,
            'processed_at' => $status === 'processed' ? now() : null,
            'metadata' => [
                'header_trust' => $headerTrust,
                'all_memo_emails' => $this->extractEmails($memoText),
            ],
        ]);

        return [
            'status' => $status,
            'message_id' => $record->getKey(),
            'client_id' => $client?->getKey(),
            'payment_id' => $payment?->getKey(),
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'match_type' => $matchType,
        ];
    }

    protected function recordClientPayment(Client $client, ?float $amount, string $transactionId, string $memoText, CarbonInterface|string|null $paidAt): ClientPayment
    {
        $paidAt = $paidAt instanceof CarbonInterface ? $paidAt : ($paidAt ? Carbon::parse($paidAt) : now());
        $profile = $this->profileForPayment($client, $amount, $paidAt);
        $payment = $transactionId !== ''
            ? ClientPayment::query()
                ->where('gateway_name', 'Zelle')
                ->where('gateway_transaction_id', $transactionId)
                ->first()
            : null;

        if (! $payment) {
            $payment = ClientPayment::query()->create([
                'client_id' => $client->getKey(),
                'client_billing_profile_id' => $profile?->getKey(),
                'amount' => $amount ?? 0,
                'currency' => 'USD',
                'status' => 'paid',
                'paid_at' => $paidAt,
                'gateway_name' => 'Zelle',
                'gateway_transaction_id' => $transactionId ?: null,
                'reference' => $memoText !== '' ? 'Zelle memo: '.$memoText : 'Zelle payment',
                'notes' => 'Imported from the office Zelle mailbox.',
                'metadata' => [
                    'source' => 'office_zelle_mailbox',
                    'memo_text' => $memoText,
                ],
            ]);
        }

        if ($profile && $payment->status === 'paid') {
            $profile->last_paid_at = $paidAt;
            $profile->next_due_at = $this->nextDueAt($profile, $paidAt);
            $profile->save();
        }

        return $payment;
    }

    protected function profileForPayment(Client $client, ?float $amount, CarbonInterface $paidAt): ClientBillingProfile
    {
        $profile = ClientBillingProfile::query()->firstOrNew([
            'client_id' => $client->getKey(),
        ]);

        if (! $profile->exists) {
            $profile->fill([
                'status' => 'active',
                'amount' => $amount ?? 0,
                'currency' => 'USD',
                'billing_interval' => 'monthly',
                'started_at' => $paidAt,
                'last_paid_at' => $paidAt,
                'next_due_at' => $paidAt->copy()->addMonth(),
                'gateway_name' => 'Zelle',
                'notes' => 'Created automatically from an imported Zelle payment.',
                'metadata' => [
                    'source' => 'office_zelle_mailbox',
                    'created_from_payment_at' => now()->toIso8601String(),
                ],
            ])->save();

            return $profile;
        }

        if ((float) $profile->amount <= 0 && $amount !== null && $amount > 0) {
            $profile->amount = $amount;
        }

        if (! $profile->gateway_name) {
            $profile->gateway_name = 'Zelle';
        }

        if (! $profile->last_paid_at || Carbon::parse($profile->last_paid_at)->lessThan($paidAt)) {
            $profile->last_paid_at = $paidAt;
            $profile->next_due_at = $this->nextDueAt($profile, $paidAt);
        }

        $metadata = $profile->metadata ?? [];
        data_set($metadata, 'payment_sources.zelle.last_seen_at', now()->toIso8601String());
        $profile->metadata = $metadata;
        $profile->save();

        return $profile;
    }

    protected function clientByEmail(string $email): ?Client
    {
        $email = $this->normalizeEmail($email);

        if ($email === '') {
            return null;
        }

        return Client::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orWhereRaw('LOWER(secondary_email) = ?', [$email])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function finishSync(OfficeZelleSetting $setting, array $result): array
    {
        $setting->last_checked_at = now();
        $setting->last_error = empty($result['success']) ? (string) ($result['error'] ?? 'Unknown Zelle sync error.') : null;
        $setting->save();

        return $result;
    }

    protected function normalizeTrustedDomains(string $domains): string
    {
        return collect(preg_split('/[,;\n\r]+/', $domains) ?: [])
            ->map(fn (string $domain): string => strtolower(trim($domain, " \t\n\r\0\x0B@.")))
            ->filter()
            ->unique()
            ->values()
            ->implode(',');
    }

    /**
     * @return array<int, string>
     */
    protected function trustedDomains(OfficeZelleSetting $setting): array
    {
        return collect(explode(',', (string) ($setting->trusted_domains ?: 'chase.com,zellepay.com,zelle.com,jpmorgan.com')))
            ->map(fn (string $domain): string => strtolower(trim($domain, " \t\n\r\0\x0B@.")))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function subjectIsExpected(string $subject, string $expected): bool
    {
        $actual = $this->normalizeSubject($subject);
        $expected = $this->normalizeSubject($expected);

        return $actual !== '' && $expected !== '' && ($actual === $expected || str_contains($actual, $expected));
    }

    protected function normalizeSubject(string $subject): string
    {
        $subject = strtolower(trim($subject));
        $subject = str_replace(['®', '(r)', '&reg;'], '', $subject);
        $subject = preg_replace('/^(?:re|fw|fwd)\s*:\s*/i', '', $subject) ?: $subject;

        return trim(preg_replace('/\s+/', ' ', $subject) ?: '');
    }

    /**
     * @return array{trusted:bool,has_trusted_domain:bool,status_label:string,from_domain:string,return_path_domain:string,dkim_domains:array<int,string>,spf_pass:bool,dkim_pass:bool,dmarc_pass:bool}
     */
    protected function headerTrust(string $headers, string $fromEmail, array $trustedDomains): array
    {
        $normalized = preg_replace("/\r?\n[ \t]+/", ' ', $headers) ?: $headers;
        $lower = strtolower($normalized);
        $fromDomain = $this->emailDomain($fromEmail);
        $returnPathDomain = '';
        $dkimDomains = [];

        if (preg_match('/^Return-Path:\s*<?([^>\s]+)>?/mi', $normalized, $match) === 1) {
            $returnPathDomain = $this->emailDomain((string) $match[1]);
        }

        if (preg_match_all('/^DKIM-Signature:\s*(.+)$/mi', $normalized, $matches) !== false) {
            foreach ($matches[1] ?? [] as $signature) {
                if (preg_match('/(?:^|;\s*)d=([^;\s]+)/i', (string) $signature, $domainMatch) === 1) {
                    $dkimDomains[] = strtolower(trim((string) $domainMatch[1]));
                }
            }
        }

        $spfPass = str_contains($lower, 'spf=pass') || str_contains($lower, 'received-spf: pass');
        $dkimPass = str_contains($lower, 'dkim=pass');
        $dmarcPass = str_contains($lower, 'dmarc=pass');
        $trustedFrom = $this->domainIsTrusted($fromDomain, $trustedDomains);
        $trustedReturnPath = $this->domainIsTrusted($returnPathDomain, $trustedDomains);
        $trustedDkim = collect($dkimDomains)->contains(fn (string $domain): bool => $this->domainIsTrusted($domain, $trustedDomains));
        $trusted = $headers !== '' && (
            ($trustedDkim && ($dkimPass || $dmarcPass))
            || ($trustedFrom && ($spfPass || $dmarcPass))
            || ($trustedReturnPath && $spfPass)
        );
        $hasTrustedDomain = $trustedFrom || $trustedReturnPath || $trustedDkim;

        return [
            'trusted' => $trusted,
            'has_trusted_domain' => $hasTrustedDomain,
            'status_label' => $trusted ? 'Trusted sender' : ($hasTrustedDomain ? 'Trusted domain, header review needed' : 'Untrusted sender'),
            'from_domain' => $fromDomain,
            'return_path_domain' => $returnPathDomain,
            'dkim_domains' => array_values(array_unique($dkimDomains)),
            'spf_pass' => $spfPass,
            'dkim_pass' => $dkimPass,
            'dmarc_pass' => $dmarcPass,
        ];
    }

    protected function domainIsTrusted(string $domain, array $trustedDomains): bool
    {
        $domain = strtolower(ltrim(trim($domain), '@.'));

        if ($domain === '') {
            return false;
        }

        foreach ($trustedDomains as $trustedDomain) {
            if ($domain === $trustedDomain || str_ends_with($domain, '.'.$trustedDomain)) {
                return true;
            }
        }

        return false;
    }

    protected function emailDomain(string $email): string
    {
        $email = $this->normalizeEmail($email);

        return $email !== '' && str_contains($email, '@') ? strtolower(substr(strrchr($email, '@') ?: '', 1)) : '';
    }

    /**
     * @return array{name:string,email:string}
     */
    protected function parseAddress(string $raw): array
    {
        $raw = $this->decodeHeader($raw);
        $email = '';
        $name = trim($raw);

        if (preg_match('/<([^>]+)>/', $raw, $match) === 1) {
            $email = $this->normalizeEmail((string) $match[1]);
            $name = trim(str_replace($match[0], '', $raw), " \t\n\r\0\x0B\"'");
        } elseif (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $match) === 1) {
            $email = $this->normalizeEmail((string) $match[0]);
            $name = trim(str_replace($match[0], '', $raw), " \t\n\r\0\x0B\"'<>");
        }

        return ['name' => $this->clean($name), 'email' => $email];
    }

    protected function decodeHeader(string $value): string
    {
        if ($value === '' || ! function_exists('imap_mime_header_decode')) {
            return $value;
        }

        $parts = @imap_mime_header_decode($value);

        if (! is_array($parts)) {
            return $value;
        }

        $decoded = '';

        foreach ($parts as $part) {
            $charset = strtoupper((string) ($part->charset ?? 'UTF-8'));
            $text = (string) ($part->text ?? '');
            $decoded .= $charset !== 'DEFAULT' && $charset !== 'UTF-8' ? mb_convert_encoding($text, 'UTF-8', $charset) : $text;
        }

        return $decoded;
    }

    protected function fetchBody(mixed $imap, int $msgno): string
    {
        foreach (['1', '1.1', '1.2', '2'] as $part) {
            $body = (string) @imap_fetchbody($imap, $msgno, $part);
            $decoded = trim(html_entity_decode(strip_tags(quoted_printable_decode($body)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($decoded !== '') {
                return $decoded;
            }
        }

        return trim(html_entity_decode(strip_tags(quoted_printable_decode((string) @imap_body($imap, $msgno))), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function extractAmount(string $subject, string $body): ?float
    {
        $text = $subject."\n".$body;

        foreach ([
            '/(?:received|sent|payment|zelle|amount)[^\$]{0,120}\$\s*([0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?)/i',
            '/\$\s*([0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?)/',
            '/USD\s*([0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?)/i',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return round((float) str_replace(',', '', (string) $match[1]), 2);
            }
        }

        return null;
    }

    protected function extractTransactionId(string $subject, string $body, string $messageId = ''): string
    {
        $text = preg_replace('/\s+/', ' ', $subject."\n".$body) ?: '';

        foreach ([
            '/(?:transaction|confirmation|reference|activity|payment)\s*(?:id|number|#|no\.?)\s*:?\s*([A-Z0-9][A-Z0-9\-_.]{5,80})/i',
            '/\btransaction\s*:?\s*([0-9]{6,30})\b/i',
            '/(?:zelle|chase)\s*(?:id|reference)\s*:?\s*([A-Z0-9][A-Z0-9\-_.]{5,80})/i',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return $this->clean((string) $match[1], 255);
            }
        }

        return $this->clean($messageId, 255);
    }

    protected function extractSenderName(string $subject, string $body, string $fallback = ''): string
    {
        $text = preg_replace('/\s+/', ' ', $subject."\n".$body) ?: '';

        foreach ([
            '/(?:from|sender|paid by|payment from)\s*:?\s*([A-Z][A-Z0-9 .,&\'-]{2,80})/i',
            '/([A-Z][A-Z0-9 .,&\'-]{2,80})\s+(?:sent|paid)\s+you/i',
            '/you received (?:a )?(?:payment|zelle)?(?: from)?\s*([A-Z][A-Z0-9 .,&\'-]{2,80})/i',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                $candidate = trim((string) $match[1]);
                $candidate = preg_replace('/\s+(?:for|via|through|using|on)\s+.*$/i', '', $candidate) ?: $candidate;

                return $this->clean($candidate, 255);
            }
        }

        return $this->clean($fallback, 255);
    }

    protected function extractMemoText(string $subject, string $body): string
    {
        $text = preg_replace('/\s+/', ' ', $subject."\n".$body) ?: '';

        foreach ([
            '/(?:memo|note|message|payment memo|zelle memo)\s*:?\s*([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|[^.;\n\r]{1,180})/i',
            '/(?:for|description)\s*:?\s*([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}|[^.;\n\r]{1,180})/i',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return $this->clean((string) $match[1], 255);
            }
        }

        return '';
    }

    protected function extractSentOn(string $subject, string $body): ?CarbonInterface
    {
        $text = preg_replace('/\s+/', ' ', $subject."\n".$body) ?: '';

        if (preg_match('/sent on\s*:?\s*([A-Z][a-z]{2,8}\s+\d{1,2},\s+\d{4})/i', $text, $match) === 1) {
            return Carbon::parse((string) $match[1])->startOfDay();
        }

        return null;
    }

    protected function extractFirstEmail(string $text): string
    {
        return $this->extractEmails($text)[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    protected function extractEmails(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email): string => $this->normalizeEmail($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    protected function clean(string $value, int $max = 255): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');

        return $value === '' ? '' : mb_substr($value, 0, $max);
    }

    protected function nextDueAt(ClientBillingProfile $profile, CarbonInterface $paidAt): ?CarbonInterface
    {
        return match ($profile->billing_interval) {
            'weekly' => $paidAt->copy()->addWeek(),
            'monthly' => $paidAt->copy()->addMonth(),
            'annual' => $paidAt->copy()->addYear(),
            default => null,
        };
    }
}
