<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\CaseBrief;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientPayment;
use App\Models\ClientProviderAccount;
use App\Models\EmployeeProfile;
use App\Models\LetterDraft;
use App\Models\MigrationOperatorCapture;
use App\Models\OutboundSignal;
use App\Models\ReportingCycle;
use App\Models\Task;
use App\Models\User;
use App\Models\ViolationCandidate;
use App\Services\AuditTrail;
use App\Services\BrowserCaptureIntake;
use App\Services\ClientAssignmentService;
use App\Services\ClientScoreTimeline;
use App\Services\CreditReportComparisonService;
use App\Services\InstallerState;
use App\Services\LeadCaptureGuard;
use App\Services\OfficeGrowthRuntime;
use App\Services\ViolationLegalReviewService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientPortalController extends Controller
{
    public function store(Request $request, AuditTrail $auditTrail, OfficeGrowthRuntime $growth, LeadCaptureGuard $leadGuard): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'current_score' => ['nullable', 'integer', 'min:300', 'max:850'],
            'status' => ['nullable', 'string', 'max:50'],
            'goals' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'affiliate_key' => ['nullable', 'string', 'max:255'],
            'crm_values' => ['nullable', 'array'],
            'turnstile_token' => ['nullable', 'string', 'max:4096'],
        ]);

        $metadata = $this->mergeMetadata([], $validated, $growth);
        $mailDomain = null;

        if (config('creditsoft.lead_capture.require_mx', true) && filled($validated['email'] ?? null)) {
            $mailCheck = $leadGuard->emailDomainAcceptsMail($validated['email']);
            $mailDomain = $mailCheck['domain'];

            if (! $mailCheck['ok']) {
                throw ValidationException::withMessages([
                    'email' => $mailCheck['error'] ?? 'That email domain cannot receive mail yet.',
                ]);
            }

            data_set($metadata, 'lead_capture.mail_domain', $mailDomain);
            data_set($metadata, 'lead_capture.mail_dns_checked_at', now()->toIso8601String());
            data_set($metadata, 'lead_capture.mail_dns_has_mx', true);
        }

        $turnstileToken = $validated['turnstile_token'] ?? $request->input('cf-turnstile-response');
        $turnstileRequired = (bool) config('creditsoft.lead_capture.require_turnstile', false)
            || (bool) data_get($metadata, 'lead_capture.turnstile_required', false)
            || filled($turnstileToken);
        $turnstile = $leadGuard->verifyTurnstile(
            $turnstileToken,
            $request->ip(),
            $turnstileRequired,
        );

        if (! $turnstile['ok']) {
            throw ValidationException::withMessages([
                'turnstile_token' => $turnstile['error'] ?? 'The browser check did not pass.',
            ]);
        }

        data_set($metadata, 'lead_capture.turnstile_required', $turnstile['required']);
        data_set($metadata, 'lead_capture.turnstile_verified_at', $turnstile['skipped'] ? null : now()->toIso8601String());
        data_set($metadata, 'lead_capture.crm_confirmed_at', now()->toIso8601String());

        $client = Client::query()->create([
            'cuid' => 'c_'.Str::lower(Str::random(10)),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'current_score' => $validated['current_score'] ?? null,
            'status' => $validated['status'] ?? 'lead',
            'goals' => $validated['goals'] ?? null,
            'metadata' => $metadata,
        ]);

        $auditTrail->record(
            null,
            'api.client.created',
            "Partner API created client dossier for {$client->display_name}.",
            $client,
            [
                'source' => 'partner_api',
                'external_reference' => $validated['external_reference'] ?? null,
                'crm_confirmed' => true,
                'mail_domain' => $mailDomain,
                'turnstile_verified' => ! $turnstile['skipped'],
            ],
        );

        return response()->json([
            'data' => $this->serializeClient($client),
            'meta' => [
                ...$this->officeResponseMeta($growth),
                'crm_confirmed' => true,
            ],
        ], 201);
    }

    public function show(string $clientCuid, OfficeGrowthRuntime $growth): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);
        $client->loadMissing('assignedUser');

        return response()->json([
            'data' => $this->serializeClient($client),
            'meta' => $this->officeResponseMeta($growth),
        ]);
    }

    public function picker(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = min(max((int) ($validated['limit'] ?? 8), 1), 20);
        $currentUserId = $request->user()?->getKey();
        $activeStatuses = ['lead', 'intake', 'active', 'active_review', 'at_risk', 'monitoring'];

        $clients = Client::query()
            ->with('assignedUser')
            ->whereIn('status', $activeStatuses)
            ->when(
                $currentUserId,
                fn ($query) => $query
                    ->orderByRaw('CASE WHEN assigned_to = ? THEN 0 ELSE 1 END', [$currentUserId])
                    ->orderByRaw('CASE WHEN updated_at >= ? THEN 0 ELSE 1 END', [now()->subDays(7)])
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id'),
                fn ($query) => $query
                    ->orderByRaw('CASE WHEN updated_at >= ? THEN 0 ELSE 1 END', [now()->subDays(7)])
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
            )
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $clients
                ->map(function (Client $client) use ($currentUserId): array {
                    $client->loadMissing('assignedUser');

                    return [
                        ...$this->serializeClientSearchResult($client),
                        'assigned_to_current_user' => (bool) ($currentUserId && $client->assigned_to === $currentUserId),
                    ];
                })
                ->values(),
            'meta' => [
                'count' => $clients->count(),
                'assigned_count' => $clients->filter(fn (Client $client) => $currentUserId && $client->assigned_to === $currentUserId)->count(),
                'limit' => $limit,
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $nameParts = $this->parseClientName($validated['client_name'] ?? null);
        $query = trim((string) ($validated['query'] ?? ''));
        $email = Str::lower(trim((string) ($validated['email'] ?? '')));
        $firstName = trim((string) ($validated['first_name'] ?? $nameParts['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? $nameParts['last_name'] ?? ''));
        $limit = min(max((int) ($validated['limit'] ?? 8), 1), 20);

        if ($query === '' && $email === '' && $firstName === '' && $lastName === '') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'count' => 0,
                ],
            ]);
        }

        $matches = $this->findClientSearchMatches(
            query: $query,
            email: $email,
            firstName: $firstName,
            lastName: $lastName,
            limit: $limit,
        );

        return response()->json([
            'data' => $matches->map(fn (Client $client) => $this->serializeClientSearchResult($client))->values(),
            'meta' => [
                'count' => $matches->count(),
                'criteria' => array_filter([
                    'query' => $query ?: null,
                    'email' => $email ?: null,
                    'first_name' => $firstName ?: null,
                    'last_name' => $lastName ?: null,
                ]),
            ],
        ]);
    }

    public function companionClients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = min(max((int) ($validated['limit'] ?? 20), 1), 50);
        $actor = $request->user();

        $clients = Client::query()
            ->with('assignedUser')
            ->whereIn('status', $this->companionRunnableClientStatuses())
            ->whereRaw('not ('.$this->companionLeadClientPredicateSql().')')
            ->whereRaw('not ('.$this->companionEndedClientPredicateSql().')')
            ->whereHas('providerAccounts', function ($query): void {
                $query
                    ->whereIn('client_provider_accounts.status', ['connected', 'import_only'])
                    ->whereNotNull('client_provider_accounts.login_password')
                    ->where(function ($query): void {
                        $query->whereNotNull('client_provider_accounts.login_email')
                            ->orWhereNotNull('client_provider_accounts.login_username');
                    })
                    ->whereRaw('not ('.$this->companionBlockedProviderCredentialPredicateSql().')');
            })
            ->when($actor, fn ($query) => $query->orderByRaw(
                'CASE WHEN assigned_to = ? THEN 0 ELSE 1 END',
                [$actor->getKey()],
            ))
            ->orderByRaw(
                "CASE WHEN status IN ('active', 'in_progress', 'review') THEN 0 ELSE 1 END"
            )
            ->orderByDesc('updated_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();

        $actorId = $actor?->getKey();
        $serialized = $clients->map(function (Client $client) use ($actorId) {
            return [
                ...$this->serializeClientSearchResult($client),
                'assigned_to_current_user' => $actorId !== null && (int) $client->assigned_to === (int) $actorId,
            ];
        })->values();

        return response()->json([
            'data' => $serialized,
            'meta' => [
                'limit' => $limit,
                'count' => $clients->count(),
                'assigned_count' => $serialized->where('assigned_to_current_user', true)->count(),
                'scope' => $actor ? 'user_priority' : 'office',
                'features' => [
                    'client_sync' => true,
                    'disputefox_credentials' => true,
                    'create_client_if_missing' => true,
                    'reactivation_sweep' => true,
                ],
            ],
        ]);
    }

    public function companionNextAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_key' => ['nullable', 'string', 'max:80'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'exclude_provider_account_id' => ['nullable', 'integer'],
            'force_update' => ['nullable', 'boolean'],
            'worker_id' => ['nullable', 'string', 'max:120'],
            'queue_scope' => ['nullable', 'string', 'in:active,reactivation'],
        ]);

        $providerKey = $this->detectCompanionProviderKey([
            'provider_key' => $validated['provider_key'] ?? null,
            'page_url' => $validated['page_url'] ?? null,
            'page_title' => $validated['page_title'] ?? null,
        ]);
        $actorId = $request->user()?->getKey();
        $includeCredentials = $request->attributes->get('creditsoft_api_token_type') === 'user';
        $workerId = $this->normalizeCompanionWorkerId($validated['worker_id'] ?? null);
        $forceUpdate = (bool) ($validated['force_update'] ?? false);
        $queueScope = $this->normalizeCompanionQueueScope($validated['queue_scope'] ?? null);
        $claim = $this->claimNextCompanionProviderAccount(
            $providerKey,
            $actorId,
            $workerId,
            isset($validated['exclude_provider_account_id']) ? (int) $validated['exclude_provider_account_id'] : null,
            $forceUpdate,
            $queueScope,
        );
        /** @var ClientProviderAccount|null $providerAccount */
        $providerAccount = $claim['provider_account'];

        return response()->json([
            'data' => $providerAccount
                ? $this->serializeCompanionProviderAccount($providerAccount, $actorId, $includeCredentials)
                : null,
            'meta' => [
                'provider_key' => $providerKey ?? data_get($providerAccount, 'provider_key'),
                'available_count' => $claim['available_count'],
                'assigned_available_count' => $claim['assigned_available_count'],
                'scope' => $queueScope === 'reactivation' ? 'reactivation_sweep' : ($actorId ? 'user_priority' : 'office'),
                'queue_scope' => $queueScope,
                'worker_id' => $workerId,
                'force_update' => $forceUpdate,
                'features' => [
                    'client_sync' => true,
                    'disputefox_credentials' => true,
                    'create_client_if_missing' => true,
                    'reactivation_sweep' => true,
                ],
            ],
        ]);
    }

    public function updateCompanionProviderStatus(Request $request, AuditTrail $auditTrail): JsonResponse
    {
        $validated = $request->validate([
            'provider_account_id' => ['required', 'integer', 'exists:client_provider_accounts,id'],
            'status' => ['required', 'string', 'in:needs_client_payment,needs_reactivation,needs_credentials,blocked,connected,import_only,paused,disconnected'],
            'reason' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'worker_id' => ['nullable', 'string', 'max:120'],
            'companion' => ['nullable', 'array'],
        ]);

        /** @var ClientProviderAccount $providerAccount */
        $providerAccount = ClientProviderAccount::query()
            ->with('client.assignedUser')
            ->findOrFail((int) $validated['provider_account_id']);

        $client = $providerAccount->client;
        $status = (string) $validated['status'];
        $reason = trim((string) ($validated['reason'] ?? 'provider_status_update'));
        $detectedAt = now();
        $workerId = $this->normalizeCompanionWorkerId($validated['worker_id'] ?? null);
        $metadata = $providerAccount->metadata ?? [];
        $event = [
            'status' => $status,
            'reason' => $reason,
            'message' => trim((string) ($validated['message'] ?? '')),
            'page_url' => $validated['page_url'] ?? null,
            'page_title' => $validated['page_title'] ?? null,
            'worker_id' => $workerId,
            'detected_at' => $detectedAt->toIso8601String(),
            'companion' => $validated['companion'] ?? null,
        ];
        $history = data_get($metadata, 'companion.status_history', []);

        if (! is_array($history)) {
            $history = [];
        }

        $history[] = $event;

        data_set($metadata, 'companion.last_status_event', $event);
        data_set($metadata, 'companion.status_history', array_slice($history, -10));
        data_set($metadata, 'companion.lease.worker_id', null);
        data_set($metadata, 'companion.lease.claimed_at', null);
        data_set($metadata, 'companion.lease.expires_at', null);

        $recordsAccessBlock = in_array($status, ['needs_client_payment', 'needs_reactivation', 'needs_credentials', 'blocked'], true)
            || in_array($reason, ['smartcredit_reactivation', 'smartcredit_invalid_credentials', 'provider_invalid_credentials'], true);
        $isInvalidCredentials = in_array($reason, ['smartcredit_invalid_credentials', 'provider_invalid_credentials'], true)
            || ($status === 'needs_credentials' && Str::contains(Str::lower($reason), 'credential'));
        $isSmartCreditReactivation = $reason === 'smartcredit_reactivation' || $status === 'needs_client_payment';
        $providerDisplay = $providerAccount->provider_label ?: Str::headline(str_replace('_', ' ', $providerAccount->provider_key));
        $detectedLoginIdentifier = trim((string) data_get($validated, 'companion.login_identifier', ''));
        $loginIdentifier = trim((string) ($providerAccount->login_email ?: $providerAccount->login_username ?: $detectedLoginIdentifier));

        if ($isSmartCreditReactivation) {
            data_set($metadata, 'smartcredit.reactivation', [
                'detected_at' => $detectedAt->toIso8601String(),
                'page_url' => $validated['page_url'] ?? null,
                'page_title' => $validated['page_title'] ?? null,
                'worker_id' => $workerId,
            ]);
        }

        if ($isInvalidCredentials) {
            data_set($metadata, 'companion.credentials.invalid', [
                'detected_at' => $detectedAt->toIso8601String(),
                'reason' => $reason,
                'page_url' => $validated['page_url'] ?? null,
                'page_title' => $validated['page_title'] ?? null,
                'worker_id' => $workerId,
                'login' => $loginIdentifier !== '' ? $loginIdentifier : null,
            ]);

            if ($providerAccount->provider_key === 'smartcredit') {
                data_set($metadata, 'smartcredit.invalid_credentials', [
                    'detected_at' => $detectedAt->toIso8601String(),
                    'page_url' => $validated['page_url'] ?? null,
                    'page_title' => $validated['page_title'] ?? null,
                    'worker_id' => $workerId,
                    'login' => $loginIdentifier !== '' ? $loginIdentifier : null,
                ]);
            }

            $metadata = $this->appendProviderCredentialHistory($metadata, 'invalid_credentials', [
                'source' => 'browser_companion',
                'reason' => $reason,
                'worker_id' => $workerId,
                'login' => $loginIdentifier !== '' ? $loginIdentifier : null,
                'page_url' => $validated['page_url'] ?? null,
            ], $detectedAt);
        }

        $existingNotes = trim((string) $providerAccount->notes);
        $providerNote = $existingNotes;

        if ($recordsAccessBlock) {
            if ($isInvalidCredentials) {
                $providerNote = sprintf(
                    '%s invalid credentials detected by the companion on %s. Saved login%s was rejected. Update the %s email/password before fresh reports can be pulled.',
                    $providerDisplay,
                    $detectedAt->format('M j, Y g:ia'),
                    $loginIdentifier !== '' ? " {$loginIdentifier}" : '',
                    $providerDisplay
                );
                $providerNoteNeedle = 'invalid credentials detected by the companion';
            } elseif ($isSmartCreditReactivation) {
                $providerNote = sprintf(
                    '%s reactivation detected by the companion on %s. The client likely needs current %s access/payment before fresh reports can be pulled.',
                    $providerDisplay,
                    $detectedAt->format('M j, Y g:ia'),
                    $providerDisplay ?: 'provider'
                );
                $providerNoteNeedle = 'reactivation detected by the companion';
            } else {
                $providerNote = sprintf(
                    '%s access block detected by the companion on %s. Review the saved provider login or account status before fresh reports can be pulled.',
                    $providerDisplay,
                    $detectedAt->format('M j, Y g:ia')
                );
                $providerNoteNeedle = 'access block detected by the companion';
            }

            if ($existingNotes !== '' && ! Str::contains(Str::lower($existingNotes), $providerNoteNeedle)) {
                $providerNote = $existingNotes."\n\n".$providerNote;
            } elseif ($existingNotes !== '') {
                $providerNote = $existingNotes;
            }
        }

        $providerAccount->forceFill([
            'status' => $status,
            'notes' => $providerNote,
            'metadata' => $metadata,
        ])->save();

        $officeNoteCreated = false;
        $taskCreated = false;
        $customerEmailSignalCreated = false;
        $customerEmailTaskCreated = false;
        $customerEmailSignalId = null;
        $staffMessage = $isInvalidCredentials
            ? sprintf(
                'CreditSoft tried to sign in to %s for this customer, but the saved login%s was rejected. Update the provider email/password, then run the provider update again.',
                $providerDisplay,
                $loginIdentifier !== '' ? " ({$loginIdentifier})" : ''
            )
            : ($isSmartCreditReactivation
                ? 'CreditSoft tried to refresh this customer profile, but SmartCredit redirected to account reactivation before showing recent credit data. Confirm the customer has current SmartCredit access/payment, then run the provider update again.'
                : sprintf('CreditSoft tried to refresh this customer profile, but %s blocked access before showing recent credit data. Review the saved provider login/account state, then run the provider update again.', $providerDisplay));
        $clientFacingMessage = $isInvalidCredentials
            ? sprintf('We tried to work on your profile, but %s did not accept the login we have on file. Please send the updated login so we can continue.', $providerDisplay)
            : ($isSmartCreditReactivation
                ? 'We tried to work on your profile, but SmartCredit needs current access before we can review recent credit data.'
                : sprintf('We tried to work on your profile, but %s needs attention before we can review recent credit data.', $providerDisplay));
        $aiSummary = $isInvalidCredentials
            ? sprintf('%s rejected the saved provider credentials before a fresh report update could run.', $providerDisplay)
            : ($isSmartCreditReactivation
                ? 'SmartCredit needs client payment/reactivation before a fresh report update can run.'
                : sprintf('%s blocked access before a fresh report update could run.', $providerDisplay));
        $taskTitle = $isInvalidCredentials
            ? sprintf('Update %s login before report pull', $providerDisplay)
            : ($isSmartCreditReactivation
                ? 'Confirm SmartCredit access before report pull'
                : sprintf('Review %s access before report pull', $providerDisplay));
        $notePrefix = $isInvalidCredentials
            ? sprintf('CreditSoft tried to sign in to %s', $providerDisplay)
            : ($isSmartCreditReactivation
                ? 'CreditSoft tried to refresh this customer profile, but SmartCredit redirected'
                : sprintf('CreditSoft tried to refresh this customer profile, but %s blocked access', $providerDisplay));

        if ($client && $recordsAccessBlock) {
            $officeNoteCreated = ! $client->notes()
                ->where('visibility', 'working_note')
                ->where('note', 'like', $notePrefix.'%')
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if ($officeNoteCreated) {
                $client->notes()->create([
                    'user_id' => $request->user()?->getKey(),
                    'visibility' => 'working_note',
                    'note' => $staffMessage."\n\nClient-facing message to use: {$clientFacingMessage}",
                    'sync_eligible' => false,
                    'ai_summary' => $aiSummary,
                ]);
            }

            $taskCreated = ! $client->tasks()
                ->where('source', 'browser_companion')
                ->where('title', $taskTitle)
                ->where('status', 'open')
                ->exists();

            if ($taskCreated) {
                $client->tasks()->create([
                    'assigned_to' => $client->assigned_to,
                    'title' => $taskTitle,
                    'details' => $staffMessage,
                    'status' => 'open',
                    'priority' => 'high',
                    'source' => 'browser_companion',
                    'due_at' => now()->addDay(),
                ]);
            }

            $customerEmail = $this->queueProviderAccessCustomerEmail(
                $client,
                $providerAccount,
                $status,
                $reason,
                $providerDisplay,
                $clientFacingMessage,
                $isInvalidCredentials,
                $isSmartCreditReactivation,
                $detectedAt,
                $workerId,
            );
            $customerEmailSignalCreated = $customerEmail['signal_created'];
            $customerEmailTaskCreated = $customerEmail['task_created'];
            $customerEmailSignalId = $customerEmail['signal_id'];
        }

        $providerAccount->refresh()->loadMissing('client.assignedUser');

        $auditTrail->record(
            $request->user(),
            'api.browser_companion.provider_status_updated',
            sprintf(
                'Browser companion marked %s as %s for %s.',
                $providerAccount->provider_label ?: $providerAccount->provider_key,
                str_replace('_', ' ', $status),
                $client?->display_name ?: 'a client'
            ),
            $providerAccount,
            [
                'provider_account_id' => $providerAccount->getKey(),
                'client_id' => $client?->getKey(),
                'status' => $status,
                'reason' => $reason,
                'page_url' => $validated['page_url'] ?? null,
                'worker_id' => $workerId,
                'office_note_created' => $officeNoteCreated,
                'task_created' => $taskCreated,
                'customer_email_signal_created' => $customerEmailSignalCreated,
                'customer_email_task_created' => $customerEmailTaskCreated,
                'customer_email_signal_id' => $customerEmailSignalId,
            ],
        );

        return response()->json([
            'data' => $this->serializeCompanionProviderAccount(
                $providerAccount,
                $request->user()?->getKey(),
                false,
            ),
            'meta' => [
                'status' => $status,
                'reason' => $reason,
                'office_note_created' => $officeNoteCreated,
                'task_created' => $taskCreated,
                'customer_email_signal_created' => $customerEmailSignalCreated,
                'customer_email_task_created' => $customerEmailTaskCreated,
                'customer_email_signal_id' => $customerEmailSignalId,
            ],
        ]);
    }

    /**
     * @return array{signal_created: bool, task_created: bool, signal_id: int|null}
     */
    protected function queueProviderAccessCustomerEmail(
        Client $client,
        ClientProviderAccount $providerAccount,
        string $status,
        string $reason,
        string $providerDisplay,
        string $clientFacingMessage,
        bool $isInvalidCredentials,
        bool $isSmartCreditReactivation,
        CarbonInterface $detectedAt,
        ?string $workerId,
    ): array {
        $emailAddress = trim((string) ($client->email ?: $client->secondary_email));

        if ($emailAddress === '') {
            return [
                'signal_created' => false,
                'task_created' => false,
                'signal_id' => null,
            ];
        }

        $templateKey = $isInvalidCredentials
            ? 'provider_login_rejected'
            : ($isSmartCreditReactivation ? 'provider_reactivation_needed' : 'provider_access_blocked');
        $subject = $isInvalidCredentials
            ? sprintf('Please update your %s login', $providerDisplay)
            : ($isSmartCreditReactivation
                ? 'Please reactivate SmartCredit so we can continue'
                : sprintf('Please review your %s access', $providerDisplay));
        $body = $clientFacingMessage."\n\nReply to this message with the updated login details or account status so the office can continue the report update.";
        $eventType = 'crm.customer_email.provider_access_needed';
        $existingSignal = OutboundSignal::query()
            ->where('client_id', $client->getKey())
            ->where('event_type', $eventType)
            ->where('status', 'pending')
            ->where('queued_at', '>=', now()->subDay())
            ->get()
            ->first(fn (OutboundSignal $signal): bool => (int) data_get($signal->payload, 'provider_account_id') === (int) $providerAccount->getKey());

        if (! $existingSignal) {
            $existingSignal = OutboundSignal::create([
                'client_id' => $client->getKey(),
                'event_type' => $eventType,
                'visibility' => 'crm_email_queue',
                'payload' => [
                    'template_key' => $templateKey,
                    'provider_account_id' => $providerAccount->getKey(),
                    'provider_key' => $providerAccount->provider_key,
                    'provider_label' => $providerDisplay,
                    'status' => $status,
                    'reason' => $reason,
                    'customer_email' => $emailAddress,
                    'subject' => $subject,
                    'body' => $body,
                    'send_policy' => 'crm_review_required',
                    'source' => 'browser_companion',
                    'worker_id' => $workerId,
                    'detected_at' => $detectedAt->toIso8601String(),
                ],
                'sanitized_payload' => [
                    'template_key' => $templateKey,
                    'provider_key' => $providerAccount->provider_key,
                    'provider_label' => $providerDisplay,
                    'status' => $status,
                    'reason' => $reason,
                    'customer_email' => $emailAddress,
                    'subject' => $subject,
                    'send_policy' => 'crm_review_required',
                    'source' => 'browser_companion',
                    'detected_at' => $detectedAt->toIso8601String(),
                ],
                'status' => 'pending',
                'queued_at' => now(),
            ]);

            $signalCreated = true;
        } else {
            $signalCreated = false;
        }

        $taskTitle = $isInvalidCredentials
            ? sprintf('Email customer for updated %s login', $providerDisplay)
            : ($isSmartCreditReactivation
                ? 'Email customer for SmartCredit reactivation'
                : sprintf('Email customer about %s access', $providerDisplay));
        $taskCreated = ! $client->tasks()
            ->where('source', 'crm_email')
            ->where('title', $taskTitle)
            ->where('status', 'open')
            ->exists();

        if ($taskCreated) {
            $client->tasks()->create([
                'assigned_to' => $client->assigned_to,
                'title' => $taskTitle,
                'details' => "CRM email queue flagged this customer for review before sending.\n\nSubject: {$subject}\n\nMessage:\n{$body}",
                'status' => 'open',
                'priority' => 'high',
                'source' => 'crm_email',
                'due_at' => now()->addHours(4),
            ]);
        }

        $metadata = $providerAccount->metadata ?? [];
        data_set($metadata, 'crm.email_queue.provider_access', [
            'status' => 'pending',
            'signal_id' => $existingSignal->getKey(),
            'template_key' => $templateKey,
            'subject' => $subject,
            'customer_email' => $emailAddress,
            'send_policy' => 'crm_review_required',
            'queued_at' => now()->toIso8601String(),
        ]);
        $providerAccount->forceFill(['metadata' => $metadata])->save();

        return [
            'signal_created' => $signalCreated,
            'task_created' => $taskCreated,
            'signal_id' => $existingSignal->getKey(),
        ];
    }

    public function update(Request $request, string $clientCuid, AuditTrail $auditTrail, OfficeGrowthRuntime $growth): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'current_score' => ['sometimes', 'nullable', 'integer', 'min:300', 'max:850'],
            'status' => ['sometimes', 'string', 'max:50'],
            'goals' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'external_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'affiliate_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'crm_values' => ['sometimes', 'nullable', 'array'],
        ]);

        $before = Arr::only($client->toArray(), [
            'first_name',
            'last_name',
            'email',
            'phone',
            'current_score',
            'status',
            'goals',
            'metadata',
        ]);

        $client->fill(Arr::except($validated, ['metadata', 'external_reference', 'affiliate_key', 'crm_values']));

        if (
            array_key_exists('metadata', $validated)
            || array_key_exists('external_reference', $validated)
            || array_key_exists('affiliate_key', $validated)
            || array_key_exists('crm_values', $validated)
        ) {
            $client->metadata = $this->mergeMetadata($client->metadata ?? [], $validated, $growth);
        }

        $client->save();
        $client->refresh()->loadMissing('assignedUser');

        $after = Arr::only($client->toArray(), [
            'first_name',
            'last_name',
            'email',
            'phone',
            'current_score',
            'status',
            'goals',
            'metadata',
        ]);

        $changes = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changes[$field] = [
                    'before' => $before[$field] ?? null,
                    'after' => $value,
                ];
            }
        }

        if ($changes !== []) {
            $auditTrail->record(
                null,
                'api.client.updated',
                "Partner API updated client dossier for {$client->display_name}.",
                $client,
                [
                    'source' => 'partner_api',
                    'changes' => $changes,
                ],
            );
        }

        return response()->json([
            'data' => $this->serializeClient($client),
            'meta' => [
                'changed_fields' => array_keys($changes),
                'audit_recorded' => $changes !== [],
                ...$this->officeResponseMeta($growth),
            ],
        ]);
    }

    public function updateStatus(Request $request, string $clientCuid, AuditTrail $auditTrail): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'current_score' => ['nullable', 'integer', 'min:300', 'max:850'],
        ]);

        $before = Arr::only($client->toArray(), [
            'status',
            'current_score',
        ]);

        $client->fill($validated);
        $client->save();
        $client->refresh()->loadMissing('assignedUser');

        $after = Arr::only($client->toArray(), [
            'status',
            'current_score',
        ]);

        $changes = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changes[$field] = [
                    'before' => $before[$field] ?? null,
                    'after' => $value,
                ];
            }
        }

        $auditTrail->record(
            $request->user(),
            'api.client.status_updated',
            "Partner API updated case status for {$client->display_name}.",
            $client,
            [
                'source' => 'partner_api',
                'changes' => $changes,
            ],
        );

        return response()->json([
            'data' => $this->serializeClient($client),
            'meta' => [
                'changed_fields' => array_keys($changes),
                'audit_recorded' => true,
            ],
        ]);
    }

    public function status(string $clientCuid, CreditReportComparisonService $comparisonService, OfficeGrowthRuntime $growth): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);
        $client->load([
            'assignedUser',
            'reportingCycles' => fn ($query) => $query->latest('started_at')->limit(2),
        ]);

        $latestCycle = $client->reportingCycles->first();

        if ($latestCycle) {
            $latestCycle->loadMissing('bureauSnapshots.tradelines', 'violationCandidates');
        }

        return response()->json([
            'data' => [
                'client' => [
                    'cuid' => $client->cuid,
                    'display_name' => $client->display_name,
                    'status' => $client->status,
                    'current_score' => $client->current_score,
                    'assigned_user' => $client->assignedUser?->name,
                    'goals' => $client->goals,
                ],
                'latest_reporting_cycle' => $latestCycle ? [
                    'cycle_label' => $latestCycle->cycle_label,
                    'started_at' => optional($latestCycle->started_at)->toDateString(),
                    'reviewed_at' => optional($latestCycle->reviewed_at)?->toDateTimeString(),
                    'public_summary' => $latestCycle->public_summary,
                    'review_summary' => $comparisonService->reviewSummary($latestCycle),
                ] : null,
                'counts' => [
                    'letters_total' => $client->letters()->count(),
                    'approved_letters' => $client->letters()->where('status', 'approved')->count(),
                    'shareable_briefs' => $client->briefs()->where('sync_eligible', true)->count(),
                    'open_violations' => $client->violations()->whereIn('status', ['open', 'confirmed'])->count(),
                ],
            ],
            'meta' => $this->officeResponseMeta($growth),
        ]);
    }

    public function letters(string $clientCuid): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->letters()
                ->with('client', 'reportingCycle')
                ->latest()
                ->get()
                ->map(fn (LetterDraft $letter) => [
                    'id' => $letter->getKey(),
                    'title' => $letter->title,
                    'letter_type' => $letter->letter_type,
                    'template_key' => $letter->template_key,
                    'template_version' => $letter->template_version,
                    'status' => $letter->status,
                    'legal_basis' => $letter->legal_basis,
                    'content' => $letter->content,
                    'generated_by_ai' => $letter->generated_by_ai,
                    'approved_at' => optional($letter->approved_at)?->toIso8601String(),
                    'exported_at' => optional($letter->exported_at)?->toIso8601String(),
                    'reporting_cycle' => $letter->reportingCycle?->cycle_label,
                ])->values(),
        ]);
    }

    public function briefs(string $clientCuid): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->briefs()
                ->where('sync_eligible', true)
                ->latest()
                ->get()
                ->map(fn (CaseBrief $brief) => [
                    'id' => $brief->getKey(),
                    'period' => $brief->period,
                    'title' => $brief->title,
                    'content' => $brief->content,
                    'approved_at' => optional($brief->approved_at)?->toIso8601String(),
                    'reporting_cycle' => $brief->reportingCycle?->cycle_label,
                ])->values(),
        ]);
    }

    public function notes(string $clientCuid): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->notes()
                ->with('reportingCycle')
                ->latest()
                ->get()
                ->map(fn ($note) => [
                    'id' => $note->getKey(),
                    'visibility' => $note->visibility,
                    'note' => $note->note,
                    'ai_summary' => $note->ai_summary,
                    'sync_eligible' => $note->sync_eligible,
                    'created_at' => optional($note->created_at)?->toIso8601String(),
                    'reporting_cycle' => $note->reportingCycle?->cycle_label,
                ])->values(),
        ]);
    }

    public function storeNote(Request $request, string $clientCuid, AuditTrail $auditTrail): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'visibility' => ['required', 'in:private_note,working_note,shareable_case_brief'],
            'note' => ['required', 'string'],
            'ai_summary' => ['nullable', 'string'],
        ]);

        $note = $client->notes()->create([
            'reporting_cycle_id' => $validated['reporting_cycle_id'] ?? null,
            'user_id' => $request->user()?->getKey(),
            'visibility' => $validated['visibility'],
            'note' => $validated['note'],
            'sync_eligible' => $validated['visibility'] === 'shareable_case_brief',
            'ai_summary' => $validated['ai_summary'] ?? null,
        ]);

        $auditTrail->record(
            $request->user(),
            'api.note.created',
            "Partner API added {$validated['visibility']} note to {$client->display_name}.",
            $note,
            [
                'source' => 'partner_api',
                'client_cuid' => $client->cuid,
                'reporting_cycle_id' => $validated['reporting_cycle_id'] ?? null,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $note->getKey(),
                'visibility' => $note->visibility,
                'note' => $note->note,
                'ai_summary' => $note->ai_summary,
                'sync_eligible' => $note->sync_eligible,
                'created_at' => optional($note->created_at)?->toIso8601String(),
            ],
        ], 201);
    }

    public function cycles(string $clientCuid): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->reportingCycles()
                ->withCount(['bureauSnapshots', 'violationCandidates', 'browserCaptures'])
                ->latest('started_at')
                ->get()
                ->map(fn ($cycle) => [
                    'id' => $cycle->getKey(),
                    'cycle_label' => $cycle->cycle_label,
                    'source' => $cycle->source,
                    'started_at' => optional($cycle->started_at)?->toDateString(),
                    'reviewed_at' => optional($cycle->reviewed_at)?->toIso8601String(),
                    'public_summary' => $cycle->public_summary,
                    'bureau_snapshot_count' => $cycle->bureau_snapshots_count,
                    'violation_count' => $cycle->violation_candidates_count,
                    'browser_capture_count' => $cycle->browser_captures_count,
                ])->values(),
        ]);
    }

    public function violations(string $clientCuid, ViolationLegalReviewService $legalReview): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->violations()
                ->with('reportingCycle', 'tradeline')
                ->orderByDesc('priority_score')
                ->latest()
                ->get()
                ->map(fn (ViolationCandidate $violation) => [
                    'id' => $violation->getKey(),
                    'rule_key' => $violation->rule_key,
                    'title' => $violation->title,
                    'severity' => $violation->severity,
                    'priority_score' => $violation->priority_score,
                    'status' => $violation->status,
                    'bureau' => $violation->bureau,
                    'evidence' => $violation->evidence ?? [],
                    'next_action' => $violation->next_action,
                    'legal_frameworks' => $legalReview->frameworksFor(
                        $violation->rule_key,
                        $violation->evidence ?? [],
                        $violation->title,
                    ),
                    'confirmed_at' => optional($violation->confirmed_at)?->toIso8601String(),
                    'reporting_cycle' => $violation->reportingCycle?->cycle_label,
                    'tradeline' => $violation->tradeline?->account_name,
                ])->values(),
        ]);
    }

    public function scoreHistory(string $clientCuid, ClientScoreTimeline $scoreTimeline): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);
        $timeline = $scoreTimeline->build($client);

        if ($timeline['points'] !== []) {
            return response()->json([
                'data' => [
                    'current_score' => $client->current_score,
                    'as_of_date' => $timeline['as_of_date'],
                    'source' => $timeline['source'],
                    'history' => $timeline['points'],
                    'series' => $timeline['series'],
                    'labels' => $timeline['labels'],
                ],
            ]);
        }

        $history = AuditEntry::query()
            ->where('auditable_type', $client->getMorphClass())
            ->where('auditable_id', $client->getKey())
            ->whereIn('event', ['api.client.created', 'api.client.updated', 'api.client.status_updated'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (AuditEntry $entry) use ($client): array {
                $score = data_get($entry->context, 'changes.current_score.after');
                $status = data_get($entry->context, 'changes.status.after');

                return [
                    'event' => $entry->event,
                    'summary' => $entry->summary,
                    'score' => is_numeric($score) ? (int) $score : $client->current_score,
                    'status' => is_string($status) ? $status : $client->status,
                    'recorded_at' => optional($entry->created_at)?->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => [
                'current_score' => $client->current_score,
                'history' => $history->values(),
            ],
        ]);
    }

    public function tasks(string $clientCuid): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->tasks()
                ->with('assignedUser', 'reportingCycle')
                ->latest('due_at')
                ->get()
                ->map(fn (Task $task) => [
                    'id' => $task->getKey(),
                    'title' => $task->title,
                    'details' => $task->details,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'source' => $task->source,
                    'due_at' => optional($task->due_at)?->toIso8601String(),
                    'assigned_user' => $task->assignedUser?->name,
                    'reporting_cycle' => $task->reportingCycle?->cycle_label,
                ])->values(),
        ]);
    }

    public function storeTask(Request $request, string $clientCuid, AuditTrail $auditTrail): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,normal,high'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = $client->tasks()->create([
            ...$validated,
            'assigned_to' => $request->user()?->getKey(),
            'status' => 'open',
            'source' => 'partner_api',
        ]);

        $auditTrail->record(
            $request->user(),
            'api.task.created',
            "Partner API created task {$task->title} for {$client->display_name}.",
            $task,
            [
                'source' => 'partner_api',
                'client_cuid' => $client->cuid,
                'reporting_cycle_id' => $validated['reporting_cycle_id'] ?? null,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $task->getKey(),
                'title' => $task->title,
                'details' => $task->details,
                'status' => $task->status,
                'priority' => $task->priority,
                'source' => $task->source,
                'due_at' => optional($task->due_at)?->toIso8601String(),
            ],
        ], 201);
    }

    public function documents(string $clientCuid): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->documents()
                ->with('reportingCycle')
                ->where('portal_visible', true)
                ->latest('uploaded_at')
                ->get()
                ->map(fn (ClientDocument $document) => [
                    'id' => $document->getKey(),
                    'title' => $document->title,
                    'category' => $document->category,
                    'notes' => $document->notes,
                    'file_name' => $document->file_name,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                    'reporting_cycle' => $document->reportingCycle?->cycle_label,
                ])->values(),
        ]);
    }

    public function storeDocument(Request $request, string $clientCuid, AuditTrail $auditTrail, OfficeGrowthRuntime $growth): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'portal_visible' => ['nullable', 'boolean'],
            'document_file' => ['required', 'file', 'max:20480'],
        ]);

        /** @var UploadedFile $documentFile */
        $documentFile = $request->file('document_file');
        $stored = $this->storeClientDocumentFile($client, $documentFile);

        $document = $client->documents()->create([
            'reporting_cycle_id' => $validated['reporting_cycle_id'] ?? null,
            'user_id' => $request->user()?->getKey(),
            'title' => $validated['title'],
            'category' => $validated['category'] ?? 'supporting_document',
            'notes' => $validated['notes'] ?? null,
            'file_name' => $stored['file_name'],
            'file_path' => $stored['file_path'],
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'],
            'portal_visible' => (bool) ($validated['portal_visible'] ?? true),
            'metadata' => [
                'source' => 'partner_api',
            ],
            'uploaded_at' => now(),
        ]);

        $auditTrail->record(
            $request->user(),
            'api.document.created',
            "Partner API uploaded document {$document->title} for {$client->display_name}.",
            $document,
            [
                'source' => 'partner_api',
                'client_cuid' => $client->cuid,
                'reporting_cycle_id' => $validated['reporting_cycle_id'] ?? null,
                'portal_visible' => $document->portal_visible,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $document->getKey(),
                'title' => $document->title,
                'category' => $document->category,
                'notes' => $document->notes,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'portal_visible' => $document->portal_visible,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
            ],
            'meta' => $this->officeResponseMeta($growth),
        ], 201);
    }

    public function storeCompanionClientDocument(Request $request, AuditTrail $auditTrail, OfficeGrowthRuntime $growth): JsonResponse
    {
        $validated = $request->validate([
            'client_cuid' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'array'],
            'document.source_document_uid' => ['nullable', 'string', 'max:255'],
            'document.source_client_uid' => ['nullable', 'string', 'max:255'],
            'document.title' => ['nullable', 'string', 'max:255'],
            'document.category' => ['nullable', 'string', 'max:255'],
            'document.notes' => ['nullable', 'string'],
            'document.file_name' => ['nullable', 'string', 'max:255'],
            'document.mime_type' => ['nullable', 'string', 'max:255'],
            'document.file_size' => ['nullable', 'integer', 'min:0'],
            'document.download_url' => ['nullable', 'string', 'max:2048'],
            'document.preview_url' => ['nullable', 'string', 'max:2048'],
            'document.source_path' => ['nullable', 'string', 'max:2048'],
            'document.uploaded_at_label' => ['nullable', 'string', 'max:255'],
            'source_system' => ['nullable', 'string', 'max:120'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'worker_id' => ['nullable', 'string', 'max:120'],
            'document_file' => ['nullable', 'file', 'max:51200'],
        ]);

        $client = $this->resolveClient((string) $validated['client_cuid']);
        $sourceSystem = Str::of((string) ($validated['source_system'] ?? 'external'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value() ?: 'external';
        $documentPayload = is_array($validated['document'] ?? null) ? $validated['document'] : [];

        /** @var UploadedFile|null $documentFile */
        $documentFile = $request->file('document_file');
        $stored = $documentFile ? $this->storeClientDocumentFile($client, $documentFile) : null;
        if ($stored && $this->companionStoredFileLooksLikeTinyPreview($stored, $documentPayload)) {
            $this->discardStoredClientDocumentFile($stored);
            $stored = null;
            $documentPayload['tiny_preview_rejected'] = true;
        }
        $result = $this->upsertCompanionClientDocument(
            client: $client,
            sourceDocument: $documentPayload,
            context: [
                'source_system' => $sourceSystem,
                'page_url' => $validated['page_url'] ?? null,
                'page_title' => $validated['page_title'] ?? null,
                'worker_id' => $this->normalizeCompanionWorkerId($validated['worker_id'] ?? null),
            ],
            actorId: $request->user()?->getKey(),
            stored: $stored,
        );

        /** @var ClientDocument $document */
        $document = $result['document'];
        $auditTrail->record(
            $request->user(),
            $result['created'] ? 'api.browser_companion.document_created' : 'api.browser_companion.document_synced',
            sprintf('Browser companion synced DisputeFox document %s for %s.', $document->title, $client->display_name),
            $document,
            [
                'source' => 'browser_companion',
                'source_system' => $sourceSystem,
                'client_cuid' => $client->cuid,
                'source_document_uid' => data_get($document->metadata, 'imports.disputefox.document.source_document_uid'),
                'file_uploaded' => $stored !== null,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $document->getKey(),
                'title' => $document->title,
                'category' => $document->category,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'portal_visible' => $document->portal_visible,
                'created' => $result['created'],
                'file_uploaded' => $stored !== null,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
            ],
            'meta' => $this->officeResponseMeta($growth),
        ], $result['created'] ? 201 : 200);
    }

    public function browserCaptures(string $clientCuid): JsonResponse
    {
        $client = $this->resolveClient($clientCuid);

        return response()->json([
            'data' => $client->browserCaptures()
                ->with('reportingCycle')
                ->latest('imported_at')
                ->get()
                ->map(fn ($capture) => [
                    'id' => $capture->getKey(),
                    'source_type' => $capture->source_type,
                    'browser_name' => $capture->browser_name,
                    'page_title' => $capture->page_title,
                    'page_url' => $capture->page_url,
                    'file_name' => $capture->file_name,
                    'mime_type' => $capture->mime_type,
                    'archive_format' => $capture->archive_format,
                    'provider_key' => data_get($capture->metadata, 'provider_key'),
                    'provider_capture' => data_get($capture->metadata, 'provider_capture'),
                    'import_profile' => data_get($capture->metadata, 'import_profile'),
                    'smartcredit' => data_get($capture->metadata, 'smartcredit'),
                    'credit_karma' => data_get($capture->metadata, 'credit_karma'),
                    'imported_at' => optional($capture->imported_at)?->toIso8601String(),
                    'reporting_cycle' => $capture->reportingCycle?->cycle_label,
                ])->values(),
        ]);
    }

    public function storeBrowserCapture(
        Request $request,
        string $clientCuid,
        BrowserCaptureIntake $intake,
        AuditTrail $auditTrail,
        OfficeGrowthRuntime $growth,
    ): JsonResponse {
        $client = $this->resolveClient($clientCuid);

        $validated = $request->validate([
            'reporting_cycle_id' => ['required', 'integer', 'exists:reporting_cycles,id'],
            'source_type' => ['nullable', 'in:dom_capture,browser_capture,companion_capture,safari_webarchive'],
            'browser_name' => ['nullable', 'string', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'html' => ['nullable', 'string'],
            'capture_file' => ['nullable', 'file', 'max:10240', 'extensions:json,html,htm,txt,mhtml,webarchive'],
        ]);

        if (blank($validated['html'] ?? null) && ! $request->hasFile('capture_file')) {
            return response()->json([
                'message' => 'Paste DOM HTML or upload a capture file.',
            ], 422);
        }

        $cycle = $client->reportingCycles()->findOrFail($validated['reporting_cycle_id']);
        $capture = $intake->ingest(
            client: $client,
            cycle: $cycle,
            payload: [
                ...$validated,
                'capture_file' => $request->file('capture_file'),
            ],
            user: $request->user(),
        );

        $auditTrail->record(
            $request->user(),
            'api.browser_capture.created',
            "Partner API imported browser capture for {$cycle->cycle_label}.",
            $capture,
            [
                'source' => 'partner_api',
                'client_cuid' => $client->cuid,
                'source_type' => $capture->source_type,
                'page_title' => $capture->page_title,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $capture->getKey(),
                'source_type' => $capture->source_type,
                'browser_name' => $capture->browser_name,
                'page_title' => $capture->page_title,
                'page_url' => $capture->page_url,
                'file_name' => $capture->file_name,
                'mime_type' => $capture->mime_type,
                'archive_format' => $capture->archive_format,
                'provider_key' => data_get($capture->metadata, 'provider_key'),
                'provider_capture' => data_get($capture->metadata, 'provider_capture'),
                'import_profile' => data_get($capture->metadata, 'import_profile'),
                'smartcredit' => data_get($capture->metadata, 'smartcredit'),
                'credit_karma' => data_get($capture->metadata, 'credit_karma'),
                'imported_at' => optional($capture->imported_at)?->toIso8601String(),
            ],
            'meta' => $this->officeResponseMeta($growth),
        ], 201);
    }

    public function storeCompanionCapture(
        Request $request,
        BrowserCaptureIntake $intake,
        AuditTrail $auditTrail,
        OfficeGrowthRuntime $growth,
    ): JsonResponse {
        $validated = $request->validate([
            'client_cuid' => ['nullable', 'string', 'max:255'],
            'client_lookup' => ['nullable', 'array'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_first_name' => ['nullable', 'string', 'max:255'],
            'client_last_name' => ['nullable', 'string', 'max:255'],
            'affiliate_key' => ['nullable', 'string', 'max:255'],
            'crm_values' => ['nullable', 'array'],
            'create_client_if_missing' => ['nullable', 'boolean'],
            'provider_key' => ['nullable', 'string', 'max:80'],
            'reporting_cycle_id' => ['nullable', 'integer'],
            'reporting_cycle_label' => ['nullable', 'string', 'max:255'],
            'cycle_label' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'in:dom_capture,browser_capture,companion_capture,safari_webarchive'],
            'browser_name' => ['nullable', 'string', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'html' => ['nullable', 'string'],
            'dom_html' => ['nullable', 'string'],
            'capture_file' => ['nullable', 'file', 'max:10240', 'extensions:json,html,htm,txt,mhtml,webarchive'],
            'operator_note' => ['nullable', 'string'],
            'selection_text' => ['nullable', 'string'],
            'package_name' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['nullable', 'string', 'max:2048'],
            'captured_at' => ['nullable', 'date'],
            'worker_id' => ['nullable', 'string', 'max:120'],
        ]);

        $html = $validated['html'] ?? $validated['dom_html'] ?? null;

        if (blank($html) && ! $request->hasFile('capture_file')) {
            return response()->json([
                'message' => 'Paste DOM HTML or upload a capture file.',
            ], 422);
        }

        $clientResolution = $this->resolveClientForCompanion(
            validated: $validated,
            auditTrail: $auditTrail,
            growth: $growth,
            actorId: $request->user()?->getKey(),
            workerId: $this->normalizeCompanionWorkerId($validated['worker_id'] ?? null),
        );

        if (array_key_exists('response', $clientResolution)) {
            return $clientResolution['response'];
        }

        /** @var Client $client */
        $client = $clientResolution['client'];
        $clientCreated = (bool) ($clientResolution['created'] ?? false);
        $matchedBy = (string) ($clientResolution['matched_by'] ?? 'unknown');

        $cycleResolution = $this->resolveCompanionCycle($client, $validated, $auditTrail);

        /** @var ReportingCycle $cycle */
        $cycle = $cycleResolution['cycle'];
        $cycleCreated = (bool) ($cycleResolution['created'] ?? false);

        $capture = $intake->ingest(
            client: $client,
            cycle: $cycle,
            payload: [
                ...$validated,
                'html' => $html,
                'source_type' => $validated['source_type'] ?? 'companion_capture',
                'capture_file' => $request->file('capture_file'),
                'package_name' => $validated['package_name'] ?? 'CreditSoft Browser Companion',
            ],
            user: null,
        );

        $auditTrail->record(
            null,
            'api.browser_capture.created',
            "Browser companion imported {$capture->page_title} for {$client->display_name}.",
            $capture,
            [
                'source' => 'browser_companion',
                'client_cuid' => $client->cuid,
                'reporting_cycle_id' => $cycle->getKey(),
                'source_type' => $capture->source_type,
                'page_title' => $capture->page_title,
                'matched_by' => $matchedBy,
                'client_created' => $clientCreated,
                'cycle_created' => $cycleCreated,
            ],
        );

        $this->touchProviderAccountImport(
            client: $client,
            capture: $capture,
            workerId: $this->normalizeCompanionWorkerId($validated['worker_id'] ?? null),
        );

        return response()->json([
            'data' => [
                'client' => $this->serializeClientSearchResult($client),
                'cycle' => [
                    'id' => $cycle->getKey(),
                    'cycle_label' => $cycle->cycle_label,
                    'source' => $cycle->source,
                    'started_at' => optional($cycle->started_at)?->toDateString(),
                ],
                'capture' => [
                    'id' => $capture->getKey(),
                    'source_type' => $capture->source_type,
                    'browser_name' => $capture->browser_name,
                    'page_title' => $capture->page_title,
                    'page_url' => $capture->page_url,
                    'file_name' => $capture->file_name,
                    'mime_type' => $capture->mime_type,
                    'archive_format' => $capture->archive_format,
                    'provider_key' => data_get($capture->metadata, 'provider_key'),
                    'provider_capture' => data_get($capture->metadata, 'provider_capture'),
                    'import_profile' => data_get($capture->metadata, 'import_profile'),
                    'smartcredit' => data_get($capture->metadata, 'smartcredit'),
                    'credit_karma' => data_get($capture->metadata, 'credit_karma'),
                    'imported_at' => optional($capture->imported_at)?->toIso8601String(),
                ],
            ],
            'meta' => [
                'matched_by' => $matchedBy,
                'client_created' => $clientCreated,
                'cycle_created' => $cycleCreated,
                ...$this->officeResponseMeta($growth),
            ],
        ], 201);
    }

    public function syncCompanionClientProfile(
        Request $request,
        AuditTrail $auditTrail,
        OfficeGrowthRuntime $growth,
        ClientAssignmentService $assignments,
    ): JsonResponse
    {
        $validated = $request->validate([
            'client_cuid' => ['nullable', 'string', 'max:255'],
            'client_profile' => ['required', 'array'],
            'client_profile.fields' => ['nullable', 'array'],
            'client_profile.raw_fields' => ['nullable', 'array'],
            'client_profile.list_records' => ['nullable', 'array'],
            'client_profile.documents' => ['nullable', 'array'],
            'client_profile.page_kind' => ['nullable', 'string', 'max:80'],
            'client_profile.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'source_system' => ['nullable', 'string', 'max:120'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'worker_id' => ['nullable', 'string', 'max:120'],
            'credentials' => ['nullable', 'array'],
            'credentials.username' => ['nullable', 'string', 'max:255'],
            'credentials.password_saved' => ['nullable', 'boolean'],
            'create_client_if_missing' => ['nullable', 'boolean'],
        ]);

        $profile = $this->normalizedCompanionClientProfile((array) $validated['client_profile']);

        if ($profile['first_name'] === '' && $profile['last_name'] === '' && $profile['email'] === '') {
            if ($profile['list_records'] !== []) {
                return $this->syncCompanionRecordList($validated, $profile, $request, $auditTrail, $growth, $assignments);
            }

            return response()->json([
                'message' => 'CreditSoft could not detect a client name or email on this page.',
            ], 422);
        }

        $name = trim($profile['full_name'] ?: "{$profile['first_name']} {$profile['last_name']}");
        $resolution = $this->resolveClientForCompanion(
            validated: [
                'client_cuid' => $validated['client_cuid'] ?? null,
                'client_email' => $profile['email'],
                'client_name' => $name,
                'client_first_name' => $profile['first_name'],
                'client_last_name' => $profile['last_name'],
                'source_record_id' => $profile['source_record_id'],
                'source_record_int_id' => $profile['source_record_int_id'],
                'create_client_if_missing' => (bool) ($validated['create_client_if_missing'] ?? true),
                'metadata' => [],
            ],
            auditTrail: $auditTrail,
            growth: $growth,
            actorId: $request->user()?->getKey(),
            workerId: $this->normalizeCompanionWorkerId($validated['worker_id'] ?? null),
        );

        if (array_key_exists('response', $resolution)) {
            return $resolution['response'];
        }

        /** @var Client $client */
        $client = $resolution['client'];
        $created = (bool) ($resolution['created'] ?? false);
        $before = Arr::only($client->toArray(), [
            'first_name',
            'last_name',
            'email',
            'secondary_email',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'date_of_birth',
            'ssn',
            'status',
            'metadata',
        ]);
        $syncedAt = now()->toIso8601String();
        $credentials = is_array($validated['credentials'] ?? null) ? $validated['credentials'] : [];
        $sourceSystem = Str::of((string) ($validated['source_system'] ?? 'external'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value() ?: 'external';
        $profileLooksLikeLead = $this->companionProfileLooksLikeLead($profile, $validated, $client);

        $assignmentCandidates = $this->companionAssignmentCandidates($profile);
        $matchedAssignedTo = $assignments->matchUserId($assignmentCandidates);
        $stagedAssignedUser = $matchedAssignedTo
            ? null
            : $this->stageSourceOwnerForHr($assignmentCandidates[0] ?? '', $sourceSystem, $validated);

        $client->fill([
            'first_name' => $profile['first_name'] !== '' ? $profile['first_name'] : $client->first_name,
            'last_name' => $profile['last_name'] !== '' ? $profile['last_name'] : $client->last_name,
            'email' => $profile['email'] !== '' ? Str::lower($profile['email']) : $client->email,
            'secondary_email' => $profile['secondary_email'] !== '' ? Str::lower($profile['secondary_email']) : $client->secondary_email,
            'phone' => $profile['phone'] !== '' ? $profile['phone'] : $client->phone,
            'address_line_1' => $profile['address_line_1'] !== '' ? $profile['address_line_1'] : $client->address_line_1,
            'address_line_2' => $profile['address_line_2'] !== '' ? $profile['address_line_2'] : $client->address_line_2,
            'city' => $profile['city'] !== '' ? $profile['city'] : $client->city,
            'state' => $profile['state'] !== '' ? $this->normalizedCompanionState($profile['state']) : $client->state,
            'postal_code' => $profile['postal_code'] !== '' ? $profile['postal_code'] : $client->postal_code,
            'date_of_birth' => $profile['date_of_birth'] ?? $client->date_of_birth,
            'ssn' => $profile['ssn'] !== '' ? $profile['ssn'] : $client->ssn,
            'status' => $profile['status'] !== ''
                ? $this->statusForCompanionProfile($profile['status'], $profileLooksLikeLead)
                : ($client->status ?: ($profileLooksLikeLead ? 'lead' : 'active_review')),
        ]);

        if ($matchedAssignedTo) {
            $client->assigned_to = $matchedAssignedTo;
        }

        $metadata = $client->metadata ?? [];
        data_set($metadata, "imports.{$sourceSystem}.source_record_id", $profile['source_record_id'] !== '' ? $profile['source_record_id'] : null);
        data_set($metadata, "imports.{$sourceSystem}.source_record_int_id", $profile['source_record_int_id'] !== '' ? $profile['source_record_int_id'] : null);
        data_set($metadata, "imports.{$sourceSystem}.regular_companion_sync", [
            'synced_at' => $syncedAt,
            'source_system' => $sourceSystem,
            'source_page_url' => $validated['page_url'] ?? null,
            'source_page_title' => $validated['page_title'] ?? null,
            'source_record_id' => $profile['source_record_id'] !== '' ? $profile['source_record_id'] : null,
            'source_record_int_id' => $profile['source_record_int_id'] !== '' ? $profile['source_record_int_id'] : null,
            'source_page_kind' => $profile['page_kind'] !== '' ? $profile['page_kind'] : null,
            'source_list_record_count' => count($profile['list_records']),
            'confidence' => $profile['confidence'],
            'field_values' => $this->safeCompanionFieldSnapshot($profile['raw_fields']),
            'credentials_username' => trim((string) ($credentials['username'] ?? '')) ?: null,
            'credentials_saved' => (bool) ($credentials['password_saved'] ?? false),
            'source_assigned_to' => $profile['assigned_to'] !== '' ? $profile['assigned_to'] : null,
            'matched_assigned_to' => $matchedAssignedTo,
            'staged_hr_user_id' => $stagedAssignedUser?->getKey(),
            'ssn_present' => $profile['ssn'] !== '',
        ]);

        if ($profileLooksLikeLead) {
            data_set($metadata, 'source_kind', 'lead');
            data_set($metadata, 'crm.source_kind', 'lead');
        }

        $client->metadata = $metadata;
        $client->save();
        $client->refresh()->loadMissing('assignedUser');
        $providerStats = $this->syncCompanionProviderAccountsFromProfile($client, $profile, $validated, $sourceSystem, $syncedAt);
        $documentStats = $this->syncPulseClientDocuments($client, $profile['documents'], $validated, $sourceSystem, $request);
        $historyStats = $this->syncCompanionProfileHistory($client, $profile, $validated, $sourceSystem, $request);

        $after = Arr::only($client->toArray(), array_keys($before));
        $changedFields = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changedFields[] = $field;
            }
        }

        $auditTrail->record(
            $request->user(),
            $created ? 'api.browser_companion.client_created' : 'api.browser_companion.client_synced',
            sprintf('Browser companion synced DisputeFox profile data for %s.', $client->display_name),
            $client,
            [
                'source' => 'browser_companion',
                'source_system' => $sourceSystem,
                'created' => $created,
                'matched_by' => $resolution['matched_by'] ?? 'unknown',
                'changed_fields' => $changedFields,
                'page_url' => $validated['page_url'] ?? null,
                'documents' => $documentStats,
                'provider_accounts' => $providerStats,
                'history' => $historyStats,
            ],
        );

        return response()->json([
            'data' => [
                'client' => $this->serializeClientSearchResult($client),
                'documents' => $documentStats,
                'provider_accounts' => $providerStats,
                'history' => $historyStats,
            ],
            'meta' => [
                'created' => $created,
                'matched_by' => $resolution['matched_by'] ?? 'unknown',
                'changed_fields' => $changedFields,
                'provider_accounts' => $providerStats,
                'history' => $historyStats,
                ...$this->officeResponseMeta($growth),
            ],
        ], $created ? 201 : 200);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $profile
     */
    protected function syncCompanionRecordList(
        array $validated,
        array $profile,
        Request $request,
        AuditTrail $auditTrail,
        OfficeGrowthRuntime $growth,
        ClientAssignmentService $assignments,
    ): JsonResponse {
        $sourceSystem = Str::of((string) ($validated['source_system'] ?? 'external'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value() ?: 'external';
        $listKind = $this->companionRecordListKind($validated, $profile);
        $records = collect($profile['list_records'] ?? [])
            ->filter(fn ($record) => is_array($record))
            ->take(2000)
            ->values();
        $stats = [
            'total_rows' => $records->count(),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'payments_created' => 0,
            'payments_updated' => 0,
            'captures_created' => 0,
            'clients' => [],
        ];

        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'CreditSoft could read this DisputeFox list page, but it did not contain importable rows.',
                'data' => [
                    'list_kind' => $listKind,
                    ...$stats,
                ],
                'meta' => $this->officeResponseMeta($growth),
            ], 422);
        }

        foreach ($records as $record) {
            $values = $this->companionRecordValues($record);

            if (in_array($listKind, ['clients', 'leads'], true)) {
                $result = $this->syncPulseClientListRecord($record, $values, $listKind, $validated, $sourceSystem, $assignments);
            } elseif ($listKind === 'invoices') {
                $result = $this->syncPulseInvoiceRecord($record, $values, $validated, $sourceSystem);
            } else {
                $result = ['created' => false, 'updated' => false, 'skipped' => true, 'client_id' => null];
            }

            if ($result['created'] ?? false) {
                $stats['created']++;
            } elseif ($result['updated'] ?? false) {
                $stats['updated']++;
            } elseif ($result['payment_created'] ?? false) {
                $stats['payments_created']++;
            } elseif ($result['payment_updated'] ?? false) {
                $stats['payments_updated']++;
            } else {
                $stats['skipped']++;
            }

            if (! empty($result['client_id'])) {
                $stats['clients'][] = (int) $result['client_id'];
            }
        }

        if (! in_array($listKind, ['clients', 'invoices'], true)) {
            $capture = $this->stagePulseRecordListCapture($validated, $profile, $records->all(), $listKind, $sourceSystem, $request);

            if ($capture) {
                $stats['captures_created']++;
                $stats['skipped'] = 0;
            }
        }

        $stats['clients'] = collect($stats['clients'])->unique()->values()->all();

        $auditTrail->record(
            $request->user(),
            'api.browser_companion.record_list_synced',
            sprintf(
                'Browser companion imported %s DisputeFox %s rows.',
                $stats['total_rows'],
                str_replace('_', ' ', $listKind),
            ),
            null,
            [
                'source' => 'browser_companion',
                'source_system' => $sourceSystem,
                'list_kind' => $listKind,
                'page_url' => $validated['page_url'] ?? null,
                'created' => $stats['created'],
                'updated' => $stats['updated'],
                'payments_created' => $stats['payments_created'],
                'payments_updated' => $stats['payments_updated'],
                'captures_created' => $stats['captures_created'],
                'skipped' => $stats['skipped'],
            ],
        );

        $imported = $stats['created'] + $stats['updated'] + $stats['payments_created'] + $stats['payments_updated'] + $stats['captures_created'];
        $noun = $stats['total_rows'] === 1 ? 'row' : 'rows';

        return response()->json([
            'message' => $imported > 0
                ? sprintf('Imported %s DisputeFox %s %s.', $stats['total_rows'], str_replace('_', ' ', $listKind), $noun)
                : sprintf('Read %s DisputeFox %s %s, but nothing new was importable.', $stats['total_rows'], str_replace('_', ' ', $listKind), $noun),
            'data' => [
                'list_kind' => $listKind,
                ...$stats,
            ],
            'meta' => $this->officeResponseMeta($growth),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  array<string, mixed>  $validated
     * @return array{total:int,created:int,updated:int,skipped:int,files_uploaded:int}
     */
    protected function syncPulseClientDocuments(Client $client, array $documents, array $validated, string $sourceSystem, Request $request): array
    {
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'files_uploaded' => 0,
        ];

        foreach (collect($documents)->filter(fn ($document) => is_array($document))->take(100) as $document) {
            $stats['total']++;
            $sourceDocument = (array) $document;

            if ($this->companionDocumentTitle($sourceDocument) === '' && $this->companionDocumentValue($sourceDocument, ['download_url', 'preview_url', 'source_document_uid']) === '') {
                $stats['skipped']++;

                continue;
            }

            $result = $this->upsertCompanionClientDocument(
                client: $client,
                sourceDocument: $sourceDocument,
                context: [
                    'source_system' => $sourceSystem,
                    'page_url' => $validated['page_url'] ?? null,
                    'page_title' => $validated['page_title'] ?? null,
                    'worker_id' => $this->normalizeCompanionWorkerId($validated['worker_id'] ?? null),
                ],
                actorId: $request->user()?->getKey(),
            );

            if ($result['created']) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $sourceDocument
     * @param  array<string, mixed>  $context
     * @param  array{file_name:string,file_path:string,mime_type:?string,file_size:int}|null  $stored
     * @return array{document:ClientDocument,created:bool}
     */
    protected function upsertCompanionClientDocument(Client $client, array $sourceDocument, array $context, ?int $actorId = null, ?array $stored = null): array
    {
        $sourceDocumentUid = $this->companionDocumentValue($sourceDocument, ['source_document_uid', 'document_uid', 'client_document_u_id']);
        $downloadUrl = $this->companionDocumentValue($sourceDocument, ['download_url', 'client_document_full_url']);
        $previewUrl = $this->companionDocumentValue($sourceDocument, ['preview_url']);
        $sourcePath = $this->companionDocumentValue($sourceDocument, ['source_path', 'client_document_url']);
        $title = $this->companionDocumentTitle($sourceDocument);
        $fileName = $stored['file_name'] ?? $this->companionDocumentValue($sourceDocument, ['file_name', 'client_document_name_text']);

        if ($fileName === '' && $sourcePath !== '') {
            $fileName = basename(parse_url($sourcePath, PHP_URL_PATH) ?: $sourcePath);
        }

        if ($title === '') {
            $title = $fileName !== '' ? pathinfo($fileName, PATHINFO_FILENAME) : 'DisputeFox document';
        }

        $document = $this->findCompanionClientDocument($client, $sourceDocumentUid, $downloadUrl, $title);
        $created = ! $document;
        $metadata = $document?->metadata ?? [];
        $sourceSystem = Str::of((string) ($context['source_system'] ?? 'disputefox'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value() ?: 'disputefox';
        $sourceKey = $sourceSystem === 'disputefox' ? 'disputefox' : $sourceSystem;
        $hasFile = $stored !== null || filled($document?->file_path);

        data_set($metadata, 'source', 'browser_companion');
        data_set($metadata, "imports.{$sourceKey}.document", [
            'synced_at' => now()->toIso8601String(),
            'source_system' => $sourceSystem,
            'source_document_uid' => $sourceDocumentUid !== '' ? $sourceDocumentUid : null,
            'source_client_uid' => $this->companionDocumentValue($sourceDocument, ['source_client_uid', 'client_uid']) ?: null,
            'download_url' => $downloadUrl !== '' ? $downloadUrl : null,
            'preview_url' => $previewUrl !== '' ? $previewUrl : null,
            'source_path' => $sourcePath !== '' ? $sourcePath : null,
            'uploaded_at_label' => $this->companionDocumentValue($sourceDocument, ['uploaded_at_label', 'client_document_date']) ?: null,
            'page_url' => $context['page_url'] ?? null,
            'page_title' => $context['page_title'] ?? null,
            'worker_id' => $context['worker_id'] ?? null,
            'has_file' => $hasFile,
            'raw' => Arr::only($sourceDocument, [
                'source_document_uid',
                'source_client_uid',
                'title',
                'file_name',
                'category',
                'uploaded_at_label',
                'download_url',
                'preview_url',
                'source_path',
                'mime_type',
                'file_size',
                'is_credit_report',
                'tiny_preview_rejected',
            ]),
        ]);

        $payload = [
            'user_id' => $actorId ?: $document?->user_id,
            'title' => Str::limit(Str::squish($title), 255, ''),
            'category' => $this->companionDocumentCategory($sourceDocument, $title, $fileName),
            'notes' => $this->companionDocumentValue($sourceDocument, ['notes']) ?: ($document?->notes ?: 'Imported from DisputeFox by the CreditSoft browser companion.'),
            'file_name' => $stored['file_name'] ?? ($fileName !== '' ? Str::limit($fileName, 255, '') : ($document?->file_name ?: 'disputefox-document')),
            'file_path' => $stored['file_path'] ?? ($document?->file_path ?: ''),
            'mime_type' => $stored['mime_type'] ?? ($this->companionDocumentValue($sourceDocument, ['mime_type']) ?: $document?->mime_type),
            'file_size' => $stored['file_size'] ?? ((int) ($sourceDocument['file_size'] ?? 0) ?: $document?->file_size),
            'portal_visible' => $hasFile ? true : (bool) ($document?->portal_visible ?? false),
            'metadata' => $metadata,
            'uploaded_at' => $this->parseCompanionDocumentDate($this->companionDocumentValue($sourceDocument, ['uploaded_at_label', 'client_document_date']))
                ?? $document?->uploaded_at
                ?? now(),
        ];

        if ($document) {
            $document->fill($payload)->save();

            return ['document' => $document->refresh(), 'created' => false];
        }

        $document = $client->documents()->create($payload);

        return ['document' => $document, 'created' => $created];
    }

    protected function companionDocumentCategory(array $sourceDocument, string $title, string $fileName): string
    {
        $rawCategory = Str::of($this->companionDocumentValue($sourceDocument, ['category']))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
        $text = Str::lower(trim($rawCategory.' '.$title.' '.$fileName.' '.$this->companionDocumentValue($sourceDocument, ['notes'])));

        if (($sourceDocument['is_credit_report'] ?? false)
            || in_array($rawCategory, ['credit_report', 'credit_reports', 'credit_report_pdf'], true)
            || Str::contains($text, ['credit report', '3_bureau', '3 bureau', 'smart credit report', 'smartcredit report'])
        ) {
            return 'credit_report';
        }

        if (in_array($rawCategory, ['progress', 'progress_report', 'client_progress'], true)
            || Str::contains($text, ['progress report', 'client progress'])
        ) {
            return 'progress_report';
        }

        if (in_array($rawCategory, ['audit', 'audit_report'], true)
            || Str::contains($text, ['audit report', 'credit audit'])
        ) {
            return 'audit_report';
        }

        if (in_array($rawCategory, ['letter', 'letters', 'letter_pdf', 'client_letters'], true)
            || Str::contains($text, ['letter', 'lexisnexis', 'lexis nexis', 'innovis', 'credco', 'creditor statement', 'investigation results'])
        ) {
            return 'letter_pdf';
        }

        return $rawCategory !== '' ? $rawCategory : 'client_documents';
    }

    protected function companionStoredFileLooksLikeTinyPreview(array $stored, array $sourceDocument): bool
    {
        $size = (int) ($stored['file_size'] ?? 0);
        $mimeType = Str::lower((string) ($stored['mime_type'] ?? ''));

        if ($size <= 0 || $size > 65536 || ! Str::startsWith($mimeType, 'image/')) {
            return false;
        }

        if ($size < 8192) {
            return true;
        }

        $sourceText = Str::lower(implode(' ', array_filter([
            $this->companionDocumentValue($sourceDocument, ['download_url', 'client_document_full_url']),
            $this->companionDocumentValue($sourceDocument, ['preview_url']),
            $this->companionDocumentValue($sourceDocument, ['source_path', 'client_document_url']),
            $stored['file_name'] ?? '',
        ])));

        return Str::contains($sourceText, [
            '/static-resources/client_documents/',
            '/document?',
            'method=clientdocument',
            'clientdocument',
            '.pdf',
        ]);
    }

    protected function discardStoredClientDocumentFile(array $stored): void
    {
        $path = (string) ($stored['file_path'] ?? '');

        if ($path !== '' && File::exists($path)) {
            File::delete($path);
        }
    }

    protected function findCompanionClientDocument(Client $client, string $sourceDocumentUid, string $downloadUrl, string $title): ?ClientDocument
    {
        return $client->documents()
            ->get()
            ->first(function (ClientDocument $document) use ($sourceDocumentUid, $downloadUrl, $title): bool {
                $metadata = $document->metadata ?? [];
                $import = data_get($metadata, 'imports.disputefox.document')
                    ?: collect((array) data_get($metadata, 'imports', []))
                        ->map(fn ($value) => is_array($value) ? data_get($value, 'document') : null)
                        ->first(fn ($value) => is_array($value));

                if ($sourceDocumentUid !== '' && (string) data_get($import, 'source_document_uid') === $sourceDocumentUid) {
                    return true;
                }

                if ($downloadUrl !== '' && (string) data_get($import, 'download_url') === $downloadUrl) {
                    return true;
                }

                return $sourceDocumentUid === ''
                    && $downloadUrl === ''
                    && Str::lower((string) $document->title) === Str::lower($title);
            });
    }

    /**
     * @param  array<string, mixed>  $sourceDocument
     * @param  list<string>  $keys
     */
    protected function companionDocumentValue(array $sourceDocument, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($sourceDocument, $key, ''));

            if ($value !== '') {
                return Str::squish($value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $sourceDocument
     */
    protected function companionDocumentTitle(array $sourceDocument): string
    {
        return $this->companionDocumentValue($sourceDocument, ['title', 'client_document_name_text', 'file_name', 'name']);
    }

    protected function parseCompanionDocumentDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveClient(string $clientCuid): Client
    {
        return Client::query()
            ->where('cuid', $clientCuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{client:Client,created:bool,matched_by:string}|array{response:JsonResponse}
     */
    protected function resolveClientForCompanion(array $validated, AuditTrail $auditTrail, OfficeGrowthRuntime $growth, ?int $actorId = null, ?string $workerId = null): array
    {
        if (filled($validated['client_cuid'] ?? null)) {
            return [
                'client' => $this->resolveClient((string) $validated['client_cuid']),
                'created' => false,
                'matched_by' => 'client_cuid',
            ];
        }

        $lookup = is_array($validated['client_lookup'] ?? null) ? $validated['client_lookup'] : [];
        $providerKey = $this->detectCompanionProviderKey($validated);
        $nameParts = $this->parseClientName($validated['client_name'] ?? data_get($lookup, 'name'));
        $email = Str::lower(trim((string) ($validated['client_email'] ?? data_get($lookup, 'email') ?? '')));
        $firstName = trim((string) ($validated['client_first_name'] ?? data_get($lookup, 'first_name') ?? $nameParts['first_name'] ?? ''));
        $lastName = trim((string) ($validated['client_last_name'] ?? data_get($lookup, 'last_name') ?? $nameParts['last_name'] ?? ''));
        $sourceRecordId = trim((string) ($validated['source_record_id'] ?? ''));
        $sourceRecordIntId = trim((string) ($validated['source_record_int_id'] ?? ''));

        if ($sourceRecordId !== '' || $sourceRecordIntId !== '') {
            $sourceClient = $this->findPulseListClient($sourceRecordId, '', $firstName, $lastName, $sourceRecordIntId);

            if ($sourceClient) {
                return [
                    'client' => $sourceClient,
                    'created' => false,
                    'matched_by' => $sourceRecordId !== '' ? 'pulse_source_record_id' : 'pulse_source_record_int_id',
                ];
            }
        }

        if ($email === '' && $firstName === '' && $lastName === '') {
            if ($providerKey !== null) {
                $providerAccount = $this->nextReadyProviderAccount($providerKey, $actorId, $workerId);

                if ($providerAccount?->client) {
                    return [
                        'client' => $providerAccount->client,
                        'created' => false,
                        'matched_by' => 'provider_queue',
                    ];
                }

                return [
                    'response' => response()->json([
                        'message' => sprintf(
                            'No %s-ready client is waiting right now. Save that provider login on a client first.',
                            Str::headline(str_replace('_', ' ', $providerKey)),
                        ),
                    ], 404),
                ];
            }

            return [
                'response' => response()->json([
                    'message' => 'Provide a client email or name before sending a browser companion capture.',
                ], 422),
            ];
        }

        $matches = $this->findClientSearchMatches(
            query: '',
            email: $email,
            firstName: $firstName,
            lastName: $lastName,
            limit: 6,
        );

        if ($matches->count() === 1) {
            return [
                'client' => $matches->first(),
                'created' => false,
                'matched_by' => $email !== '' ? 'email' : 'name',
            ];
        }

        if ($matches->count() > 1) {
            return [
                'response' => response()->json([
                    'message' => 'Multiple clients matched this information. Search first and choose one explicitly.',
                    'matches' => $matches->map(fn (Client $client) => $this->serializeClientSearchResult($client))->values(),
                ], 409),
            ];
        }

        if (! ($validated['create_client_if_missing'] ?? false)) {
            return [
                'response' => response()->json([
                    'message' => 'No client matched this information. Search first or enable client creation.',
                ], 404),
            ];
        }

        if ($firstName === '' || $lastName === '') {
            return [
                'response' => response()->json([
                    'message' => 'A first and last name are required to create a new client from the browser companion.',
                ], 422),
            ];
        }

        $client = Client::query()->create([
            'cuid' => 'c_'.Str::lower(Str::random(10)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email !== '' ? $email : null,
            'status' => 'lead',
            'metadata' => $this->mergeMetadata([
                'source' => 'browser_companion',
                'capture_origin' => 'browser_companion',
            ], [
                'affiliate_key' => data_get($validated, 'affiliate_key'),
                'crm_values' => data_get($validated, 'crm_values', []),
            ], $growth),
        ]);

        $auditTrail->record(
            null,
            'api.client.created',
            "Browser companion created client dossier for {$client->display_name}.",
            $client,
            [
                'source' => 'browser_companion',
                'created_from_capture' => true,
            ],
        );

        return [
            'client' => $client,
            'created' => true,
            'matched_by' => 'created',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{cycle:ReportingCycle,created:bool}
     */
    protected function resolveCompanionCycle(Client $client, array $validated, AuditTrail $auditTrail): array
    {
        $cycleId = $validated['reporting_cycle_id'] ?? null;

        if ($cycleId !== null) {
            return [
                'cycle' => $client->reportingCycles()->findOrFail($cycleId),
                'created' => false,
            ];
        }

        $cycleLabel = trim((string) ($validated['reporting_cycle_label'] ?? $validated['cycle_label'] ?? ''));

        if ($cycleLabel === '') {
            $cycleLabel = now()->format('F Y').' review';
        }

        $cycle = $client->reportingCycles()
            ->where('cycle_label', $cycleLabel)
            ->first();

        if ($cycle) {
            return [
                'cycle' => $cycle,
                'created' => false,
            ];
        }

        $cycle = $client->reportingCycles()->create([
            'cycle_label' => $cycleLabel,
            'source' => 'browser_companion',
            'started_at' => now()->toDateString(),
        ]);

        $auditTrail->record(
            null,
            'api.reporting_cycle.created',
            "Browser companion opened reporting cycle {$cycle->cycle_label} for {$client->display_name}.",
            $cycle,
            [
                'source' => 'browser_companion',
                'client_cuid' => $client->cuid,
            ],
        );

        return [
            'cycle' => $cycle,
            'created' => true,
        ];
    }

    protected function parseClientName(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [
                'first_name' => '',
                'last_name' => '',
            ];
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        $firstName = array_shift($parts) ?: '';
        $lastName = implode(' ', $parts);

        return [
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
        ];
    }

    /**
     * @param  array<string, mixed>  $clientProfile
     * @return array{
     *     full_name:string,first_name:string,last_name:string,email:string,secondary_email:string,phone:string,
     *     address_line_1:string,address_line_2:string,city:string,state:string,postal_code:string,
     *     date_of_birth:?Carbon,ssn:string,status:string,assigned_to:string,source_record_id:string,source_record_int_id:string,
     *     page_kind:string,list_records:list<array<string, mixed>>,documents:list<array<string, mixed>>,
     *     confidence:float,raw_fields:list<array<string, mixed>>
     * }
     */
    protected function normalizedCompanionClientProfile(array $clientProfile): array
    {
        $fields = is_array($clientProfile['fields'] ?? null) ? $clientProfile['fields'] : [];
        $fullName = trim((string) ($fields['full_name'] ?? ''));
        $nameParts = $this->parseClientName($fullName);

        return [
            'full_name' => $fullName,
            'first_name' => trim((string) ($fields['first_name'] ?? $nameParts['first_name'] ?? '')),
            'last_name' => trim((string) ($fields['last_name'] ?? $nameParts['last_name'] ?? '')),
            'email' => trim((string) ($fields['email'] ?? '')),
            'secondary_email' => trim((string) ($fields['secondary_email'] ?? '')),
            'phone' => trim((string) ($fields['phone'] ?? '')),
            'address_line_1' => trim((string) ($fields['address_line_1'] ?? '')),
            'address_line_2' => trim((string) ($fields['address_line_2'] ?? '')),
            'city' => trim((string) ($fields['city'] ?? '')),
            'state' => trim((string) ($fields['state'] ?? '')),
            'postal_code' => trim((string) ($fields['postal_code'] ?? '')),
            'date_of_birth' => $this->parseCompanionProfileDate(trim((string) ($fields['date_of_birth'] ?? ''))),
            'ssn' => preg_replace('/[^0-9]/', '', trim((string) ($fields['ssn'] ?? ''))) ?? '',
            'status' => trim((string) ($fields['status'] ?? $fields['progress'] ?? '')),
            'assigned_to' => trim((string) ($fields['assigned_to'] ?? $fields['owner'] ?? $fields['agent'] ?? '')),
            'source_record_id' => trim((string) ($fields['source_record_id'] ?? '')),
            'source_record_int_id' => trim((string) ($fields['source_record_int_id'] ?? '')),
            'page_kind' => trim((string) ($clientProfile['page_kind'] ?? '')),
            'list_records' => array_values(array_filter((array) ($clientProfile['list_records'] ?? []), 'is_array')),
            'documents' => array_values(array_filter((array) ($clientProfile['documents'] ?? []), 'is_array')),
            'confidence' => max(0.0, min(1.0, (float) ($clientProfile['confidence'] ?? 0))),
            'raw_fields' => array_values(array_filter((array) ($clientProfile['raw_fields'] ?? []), 'is_array')),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<string>
     */
    protected function companionAssignmentCandidates(array $profile): array
    {
        return collect([
            $profile['assigned_to'] ?? '',
        ])
            ->map(fn ($value) => Str::squish(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function stageSourceOwnerForHr(string $sourceOwner, string $sourceSystem, array $context): ?User
    {
        $name = Str::squish(trim($sourceOwner));

        if ($name === '' || in_array(Str::lower($name), ['unassigned', 'none', 'n/a', 'na'], true)) {
            return null;
        }

        $user = User::query()
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $this->pendingHrEmailFor($name),
                'password' => Str::random(48),
            ]);
        }

        $profile = EmployeeProfile::query()->firstOrNew(['user_id' => $user->getKey()]);
        $metadata = $profile->metadata ?? [];
        data_set($metadata, 'source_owner_intake', [
            'source_system' => $sourceSystem,
            'source_name' => $name,
            'page_url' => $context['page_url'] ?? null,
            'page_title' => $context['page_title'] ?? null,
            'last_seen_at' => now()->toIso8601String(),
            'needs_setup' => ! $user->hasWorkspaceAccess(),
        ]);

        $profile->fill([
            'legal_name' => $profile->legal_name ?: $name,
            'preferred_name' => $profile->preferred_name ?: $name,
            'department' => $profile->department ?: 'HR',
            'title' => $profile->title ?: 'Source owner from '.Str::of($sourceSystem)->replace('_', ' ')->headline()->value(),
            'employment_type' => $profile->employment_type ?: 'pending',
            'onboarding_status' => $profile->onboarding_status ?: 'invited',
            'onboarding_started_at' => $profile->onboarding_started_at ?: now(),
            'pay_currency' => $profile->pay_currency ?: 'USD',
            'payroll_notes' => $profile->payroll_notes
                ?: 'Imported from a source owner field. Finish email, role, payroll, and login setup before assigning clients.',
            'metadata' => $metadata,
        ]);
        $profile->save();

        return $user;
    }

    protected function pendingHrEmailFor(string $name): string
    {
        $slug = Str::slug($name) ?: 'source-owner';
        $email = "{$slug}@pending.creditsoft.local";
        $suffix = 2;

        while (User::query()->where('email', $email)->exists()) {
            $email = "{$slug}+{$suffix}@pending.creditsoft.local";
            $suffix++;
        }

        return $email;
    }

    protected function parseCompanionProfileDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function companionProfileLooksLikeLead(array $profile, array $validated, Client $client): bool
    {
        $metadata = $client->metadata ?? [];

        if (
            data_get($metadata, 'source_kind') === 'lead'
            || data_get($metadata, 'crm.source_kind') === 'lead'
            || data_get($metadata, 'imports.disputefox.lists.leads') !== null
        ) {
            return true;
        }

        $pageTitle = Str::lower((string) ($validated['page_title'] ?? ''));
        $pageUrl = Str::lower((string) ($validated['page_url'] ?? ''));

        if (str_contains($pageTitle, 'lead status') || str_contains($pageUrl, 'type=leads')) {
            return true;
        }

        $fieldText = collect((array) ($profile['raw_fields'] ?? []))
            ->filter(fn ($field) => is_array($field))
            ->map(function (array $field): string {
                return Str::lower(implode(' ', [
                    (string) ($field['label'] ?? ''),
                    (string) ($field['name'] ?? ''),
                    (string) ($field['id'] ?? ''),
                    (string) ($field['value'] ?? ''),
                ]));
            })
            ->implode(' ');

        return str_contains($fieldText, 'lead status');
    }

    protected function statusForCompanionProfile(string $value, bool $leadProfile = false): string
    {
        $normalized = Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        if ($leadProfile) {
            if (in_array($normalized, ['canceled', 'cancelled'], true) || str_contains($normalized, 'cancel')) {
                return 'canceled';
            }

            if (in_array($normalized, ['graduated', 'finished', 'complete', 'completed'], true)) {
                return 'graduated';
            }

            if (in_array($normalized, ['fired', 'terminated'], true)) {
                return 'terminated';
            }

            return 'lead';
        }

        if (str_contains($normalized, 'cancel')) {
            return 'canceled';
        }

        if (str_contains($normalized, 'graduat') || str_contains($normalized, 'finished') || str_contains($normalized, 'complete')) {
            return 'graduated';
        }

        if (str_contains($normalized, 'fired') || str_contains($normalized, 'terminated')) {
            return 'terminated';
        }

        if (str_contains($normalized, 'monitor')) {
            return 'monitoring';
        }

        if ($normalized !== '') {
            return 'active_review';
        }

        return 'intake';
    }

    protected function normalizedCompanionState(string $value): string
    {
        $value = trim($value);

        return strlen($value) <= 3 ? Str::upper($value) : $value;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    protected function safeCompanionFieldSnapshot(array $fields): array
    {
        return collect($fields)
            ->map(function (array $field): array {
                $label = trim((string) ($field['label'] ?? $field['name'] ?? $field['id'] ?? ''));
                $key = Str::of($label)->lower()->value();
                $sensitive = str_contains($key, 'ssn')
                    || str_contains($key, 'social security')
                    || str_contains($key, 'password')
                    || str_contains($key, 'secret')
                    || str_contains($key, 'token');

                return [
                    'label' => $label,
                    'name' => trim((string) ($field['name'] ?? '')),
                    'id' => trim((string) ($field['id'] ?? '')),
                    'mapped_to' => trim((string) ($field['mapped_to'] ?? '')),
                    'value' => $sensitive ? '[redacted]' : Str::limit(trim((string) ($field['value'] ?? '')), 500, ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $validated
     * @return array{created:int,updated:int,skipped:int,providers:list<array<string, mixed>>}
     */
    protected function syncCompanionProviderAccountsFromProfile(Client $client, array $profile, array $validated, string $sourceSystem, string $syncedAt): array
    {
        $providers = $this->companionProviderCredentialsFromFields(
            array_values(array_filter((array) ($profile['raw_fields'] ?? []), 'is_array')),
        );
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'providers' => [],
        ];

        foreach ($providers as $providerKey => $credentials) {
            $login = trim((string) ($credentials['login'] ?? ''));
            $password = trim((string) ($credentials['password'] ?? ''));
            $securityAnswer = trim((string) ($credentials['security_answer'] ?? ''));

            if (
                ! $this->companionCredentialValueIsUsable($login)
                && ! $this->companionCredentialValueIsUsable($password)
                && ! $this->companionCredentialValueIsUsable($securityAnswer)
            ) {
                $stats['skipped']++;

                continue;
            }

            /** @var ClientProviderAccount $providerAccount */
            $providerAccount = $client->providerAccounts()->firstOrNew([
                'provider_key' => $providerKey,
            ]);
            $isNew = ! $providerAccount->exists;

            if (
                ! $this->companionCredentialValueIsUsable($login)
                && blank($providerAccount->login_email)
                && blank($providerAccount->login_username)
            ) {
                $stats['skipped']++;

                continue;
            }

            $catalog = collect(config('creditsoft.client_providers.catalog', []))->firstWhere('key', $providerKey);
            $providerLabel = (string) ($catalog['label'] ?? Str::headline(str_replace('_', ' ', $providerKey)));
            $metadata = $providerAccount->metadata ?? [];
            $previousLoginIdentifier = trim((string) ($providerAccount->login_email ?: $providerAccount->login_username));
            $fieldLabels = collect((array) ($credentials['field_labels'] ?? []))
                ->map(fn ($label) => Str::limit(Str::squish((string) $label), 120, ''))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $loginWillUpdate = $this->companionCredentialValueIsUsable($login);
            $passwordWillUpdate = $this->companionCredentialValueIsUsable($password);
            $securityAnswerWillUpdate = $this->companionCredentialValueIsUsable($securityAnswer);
            $loginChanged = $loginWillUpdate && Str::lower($login) !== Str::lower($previousLoginIdentifier);
            $credentialChanged = $loginWillUpdate || $passwordWillUpdate || $securityAnswerWillUpdate;

            data_set($metadata, "imports.{$sourceSystem}.provider_credentials.{$providerKey}", [
                'synced_at' => $syncedAt,
                'source_system' => $sourceSystem,
                'source_page_url' => $validated['page_url'] ?? null,
                'source_page_title' => $validated['page_title'] ?? null,
                'field_labels' => $fieldLabels,
                'login_saved' => $this->companionCredentialValueIsUsable($login) || filled($providerAccount->login_email) || filled($providerAccount->login_username),
                'password_saved' => $this->companionCredentialValueIsUsable($password) || $providerAccount->hasStoredPassword(),
                'security_answer_saved' => $this->companionCredentialValueIsUsable($securityAnswer) || $providerAccount->hasStoredSecurityAnswer(),
            ]);

            if ($credentialChanged) {
                $metadata = $this->appendProviderCredentialHistory($metadata, 'credentials_saved', [
                    'source' => $sourceSystem,
                    'login_changed' => $loginChanged,
                    'login_saved' => $loginWillUpdate,
                    'password_saved' => $passwordWillUpdate,
                    'security_answer_saved' => $securityAnswerWillUpdate,
                    'field_labels' => $fieldLabels,
                ], Carbon::parse($syncedAt));
                data_set($metadata, 'companion.credentials.invalid', null);
                data_set($metadata, 'smartcredit.invalid_credentials', null);
            }

            $nextStatus = $providerAccount->status && $providerAccount->status !== 'needs_credentials'
                ? $providerAccount->status
                : 'import_only';

            if ($credentialChanged && in_array((string) $providerAccount->status, ['needs_credentials', 'blocked', 'disconnected'], true)) {
                $nextStatus = 'import_only';
            }

            $providerAccount->fill([
                'provider_label' => $providerAccount->provider_label ?: $providerLabel,
                'status' => $nextStatus,
                'notes' => $providerAccount->notes ?: 'Imported from DisputeFox profile by the CreditSoft browser companion.',
                'metadata' => $metadata,
            ]);

            if ($this->companionCredentialValueIsUsable($login)) {
                if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
                    $providerAccount->login_email = Str::lower($login);
                    $providerAccount->login_username = null;
                } else {
                    $providerAccount->login_username = $login;
                    $providerAccount->login_email = null;
                }
            }

            if ($this->companionCredentialValueIsUsable($password)) {
                $providerAccount->login_password = $password;
            }

            if ($this->companionCredentialValueIsUsable($securityAnswer)) {
                $providerAccount->security_answer = $securityAnswer;
            }

            $providerAccount->save();

            $readyForUpdate = filled($providerAccount->login_email) || filled($providerAccount->login_username);
            $readyForUpdate = $readyForUpdate && $providerAccount->hasStoredPassword();

            if ($providerKey === 'identityiq') {
                $readyForUpdate = $readyForUpdate && $providerAccount->hasStoredSecurityAnswer();
            }

            if ($isNew) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }

            $stats['providers'][] = [
                'provider_key' => $providerKey,
                'provider_label' => $providerAccount->provider_label,
                'created' => $isNew,
                'updated' => ! $isNew,
                'login_saved' => filled($providerAccount->login_email) || filled($providerAccount->login_username),
                'password_saved' => $providerAccount->hasStoredPassword(),
                'security_answer_saved' => $providerAccount->hasStoredSecurityAnswer(),
                'ready_for_update' => $readyForUpdate,
            ];
        }

        return $stats;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, array{login?:string,password?:string,security_answer?:string,field_labels?:list<string>}>
     */
    protected function companionProviderCredentialsFromFields(array $fields): array
    {
        $providers = [];
        $currentProvider = null;

        foreach ($fields as $field) {
            $value = trim((string) ($field['value'] ?? ''));

            if (! $this->companionCredentialValueIsUsable($value)) {
                continue;
            }

            $labelText = Str::of(implode(' ', array_filter([
                $field['label'] ?? null,
                $field['name'] ?? null,
                $field['id'] ?? null,
                $field['placeholder'] ?? null,
                $field['autocomplete'] ?? null,
                $field['aria_label'] ?? null,
                $field['mapped_to'] ?? null,
                $field['type'] ?? null,
            ], fn ($part) => filled((string) $part))))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', ' ')
                ->squish()
                ->value();
            $valueProvider = $this->companionProviderKeyFromText($value)
                ?: $this->companionProviderKeyFromPulseMonitoringAgency($value, $labelText);
            $labelProvider = $this->companionProviderKeyFromText($labelText);
            $fieldProvider = $this->companionProviderKeyFromFieldLabel($labelText);
            $fieldKind = $this->companionProviderFieldKind($labelText);

            if ($fieldKind === 'provider' && $valueProvider !== null) {
                $currentProvider = $valueProvider;

                continue;
            }

            if ($fieldKind === null) {
                if ($valueProvider !== null && $this->companionProviderFieldLooksCredentialRelated($labelText)) {
                    $currentProvider = $valueProvider;
                }

                continue;
            }

            $providerKey = $fieldProvider
                ?: $labelProvider
                ?: ($this->companionProviderFieldLooksCredentialRelated($labelText) ? $currentProvider : null);

            if ($providerKey === null) {
                continue;
            }

            $providers[$providerKey] ??= [
                'field_labels' => [],
            ];
            $providers[$providerKey][$fieldKind] = $value;
            $providers[$providerKey]['field_labels'][] = trim((string) ($field['label'] ?? $field['name'] ?? $field['id'] ?? $fieldKind));
        }

        return $providers;
    }

    protected function companionProviderKeyFromText(string $value): ?string
    {
        $normalized = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();

        return match (true) {
            str_contains($normalized, 'smartcredit'),
            str_contains($normalized, 'smart credit'),
            str_contains($normalized, 'creditsmart'),
            str_contains($normalized, 'credit smart') => 'smartcredit',
            str_contains($normalized, 'identityiq'),
            str_contains($normalized, 'identity iq'),
            str_contains($normalized, 'identiyiq'),
            preg_match('/\bidiq\b/', $normalized) === 1 => 'identityiq',
            str_contains($normalized, 'myscoreiq'),
            str_contains($normalized, 'my score iq') => 'myscoreiq',
            str_contains($normalized, 'creditkarma'),
            str_contains($normalized, 'credit karma') => 'credit_karma',
            default => null,
        };
    }

    protected function companionProviderKeyFromPulseMonitoringAgency(string $value, string $labelText): ?string
    {
        if (! str_contains($labelText, 'monitoring agency')) {
            return null;
        }

        return match (trim($value)) {
            '1' => 'identityiq',
            '6' => 'smartcredit',
            '8' => 'myscoreiq',
            default => null,
        };
    }

    protected function companionProviderKeyFromFieldLabel(string $labelText): ?string
    {
        if (str_contains($labelText, 'secret key')) {
            return 'identityiq';
        }

        return null;
    }

    protected function companionProviderFieldKind(string $labelText): ?string
    {
        return match (true) {
            str_contains($labelText, 'security answer'),
            str_contains($labelText, 'security question'),
            str_contains($labelText, 'secret answer'),
            str_contains($labelText, 'secret key'),
            str_contains($labelText, 'secret code'),
            str_contains($labelText, 'last 4 of ssn'),
            str_contains($labelText, 'last four of ssn'),
            str_contains($labelText, 'last 4 ssn'),
            str_contains($labelText, 'last four ssn'),
            str_contains($labelText, 'authentication answer'),
            str_contains($labelText, 'auth answer') => 'security_answer',
            str_contains($labelText, 'password'),
            str_contains($labelText, 'access token') => 'password',
            str_contains($labelText, 'username'),
            str_contains($labelText, 'user name'),
            str_contains($labelText, 'login email'),
            str_contains($labelText, 'login'),
            str_contains($labelText, 'email or username'),
            str_contains($labelText, 'email username') => 'login',
            str_contains($labelText, 'monitoring agency'),
            str_contains($labelText, 'monitoring provider'),
            str_contains($labelText, 'credit monitoring provider'),
            str_contains($labelText, 'credit monitoring agency'),
            str_contains($labelText, 'provider source') => 'provider',
            default => null,
        };
    }

    protected function companionProviderFieldLooksCredentialRelated(string $labelText): bool
    {
        return $this->companionProviderKeyFromText($labelText) !== null
            || str_contains($labelText, 'credit monitoring')
            || str_contains($labelText, 'monitoring agency')
            || str_contains($labelText, 'monitoring provider')
            || str_contains($labelText, 'monitoring username')
            || str_contains($labelText, 'monitoring password')
            || str_contains($labelText, 'secret key')
            || str_contains($labelText, 'security answer')
            || str_contains($labelText, 'security question')
            || str_contains($labelText, 'last 4 of ssn')
            || str_contains($labelText, 'last four of ssn')
            || str_contains($labelText, 'last 4 ssn')
            || str_contains($labelText, 'last four ssn')
            || str_contains($labelText, 'credit report import')
            || str_contains($labelText, 'import credit report')
            || str_contains($labelText, 'scorefusion');
    }

    protected function companionCredentialValueIsUsable(string $value): bool
    {
        $value = trim($value);

        return $value !== ''
            && ! in_array(Str::lower($value), ['[redacted]', 'redacted', 'n/a', 'na', 'select'], true)
            && preg_match('/^[*•xX\-\s]+$/', $value) !== 1;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $profile
     */
    protected function companionRecordListKind(array $validated, array $profile): string
    {
        $pageUrl = Str::lower(trim((string) ($validated['page_url'] ?? '')));
        $pageTitle = Str::lower(trim((string) ($validated['page_title'] ?? '')));
        $tableIds = collect($profile['list_records'] ?? [])
            ->map(fn ($record) => Str::lower(trim((string) data_get($record, 'table_id'))))
            ->filter()
            ->implode(' ');

        return match (true) {
            str_contains($pageUrl, 'type=leads') || str_contains($pageTitle, 'leads') => 'leads',
            str_contains($pageUrl, 'type=clients') || str_contains($pageTitle, 'clients') => 'clients',
            str_contains($pageUrl, 'affiliate') || str_contains($pageTitle, 'affiliate') || str_contains($tableIds, 'affiliate') => 'affiliates',
            str_contains($pageUrl, 'invoice')
                || str_contains($pageUrl, 'billing_report')
                || str_contains($pageTitle, 'invoice')
                || str_contains($pageTitle, 'billing')
                || str_contains($tableIds, 'invoice') => 'invoices',
            default => 'business_list',
        };
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string>
     */
    protected function companionRecordValues(array $record): array
    {
        return collect((array) ($record['values'] ?? []))
            ->mapWithKeys(fn ($value, $key) => [
                trim((string) $key) => Str::squish(trim((string) $value)),
            ])
            ->filter(fn (string $value, string $key) => $key !== '' && $value !== '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function syncPulseClientListRecord(
        array $record,
        array $values,
        string $listKind,
        array $validated,
        string $sourceSystem,
        ClientAssignmentService $assignments,
    ): array
    {
        $nameEmail = $this->pulseNameAndEmail($values, $listKind);
        $nameParts = $this->parseClientName($nameEmail['name']);
        $email = Str::lower($nameEmail['email']);
        $firstName = $nameParts['first_name'];
        $lastName = $nameParts['last_name'];

        if ($firstName === '' && $lastName === '' && $email === '') {
            return ['created' => false, 'updated' => false, 'skipped' => true, 'client_id' => null];
        }

        if ($firstName === '' && $email !== '') {
            $firstName = Str::headline(Str::before($email, '@'));
        }

        if ($lastName === '') {
            $lastName = 'Unknown';
        }

        $sourceRecordId = trim((string) ($record['source_record_id'] ?? ''));
        $sourceRecordIntId = trim((string) ($record['source_record_int_id'] ?? ''));
        $signature = $this->pulseRecordSignature($listKind, $record, $values);
        $client = $this->findPulseListClient($sourceRecordId, $email, $firstName, $lastName, $sourceRecordIntId);
        $created = ! $client;
        $status = $this->statusForPulseListRecord(
            $listKind,
            $this->pulseValue($values, ['Status']),
            $this->pulseValue($values, ['Current Stage', 'Stage in Processs', 'Stage in Process']),
        );
        $sourceAssignedTo = $this->pulseValue($values, ['Assigned To', 'Assigned', 'Agent', 'Sales Person', 'Owner']);
        $matchedAssignedTo = $assignments->matchUserId([$sourceAssignedTo]);
        $stagedAssignedUser = $matchedAssignedTo
            ? null
            : $this->stageSourceOwnerForHr($sourceAssignedTo, $sourceSystem, $validated);
        $metadata = $client?->metadata ?? [];
        data_set($metadata, 'imports.disputefox.source_record_id', $sourceRecordId !== '' ? $sourceRecordId : null);
        data_set($metadata, 'imports.disputefox.source_record_int_id', $sourceRecordIntId !== '' ? $sourceRecordIntId : null);
        data_set($metadata, 'source_kind', $listKind === 'leads' ? 'lead' : 'client');
        data_set($metadata, 'crm.source_kind', $listKind === 'leads' ? 'lead' : 'client');
        data_set($metadata, "imports.disputefox.lists.{$listKind}", [
            'synced_at' => now()->toIso8601String(),
            'source_system' => $sourceSystem,
            'source_signature' => $signature,
            'source_record_id' => $sourceRecordId !== '' ? $sourceRecordId : null,
            'source_record_int_id' => $sourceRecordIntId !== '' ? $sourceRecordIntId : null,
            'profile_url' => trim((string) ($record['profile_url'] ?? '')) ?: null,
            'page_url' => $validated['page_url'] ?? null,
            'page_title' => $validated['page_title'] ?? null,
            'source_assigned_to' => $sourceAssignedTo !== '' ? $sourceAssignedTo : null,
            'matched_assigned_to' => $matchedAssignedTo,
            'staged_hr_user_id' => $stagedAssignedUser?->getKey(),
            'raw_row' => $values,
        ]);

        $payload = [
            'first_name' => $firstName ?: ($client?->first_name ?: 'Unknown'),
            'last_name' => $lastName ?: ($client?->last_name ?: 'Unknown'),
            'email' => $email !== '' ? $email : ($client?->email ?: null),
            'status' => $status,
            'metadata' => $metadata,
        ];

        if ($matchedAssignedTo) {
            $payload['assigned_to'] = $matchedAssignedTo;
        }

        if ($client) {
            $client->fill($payload)->save();

            return ['created' => false, 'updated' => true, 'skipped' => false, 'client_id' => $client->getKey()];
        }

        $client = Client::query()->create([
            ...$payload,
            'cuid' => 'c_'.Str::lower(Str::random(10)),
        ]);

        return ['created' => $created, 'updated' => false, 'skipped' => false, 'client_id' => $client->getKey()];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function syncPulseInvoiceRecord(array $record, array $values, array $validated, string $sourceSystem): array
    {
        $invoiceId = $this->pulseValue($values, ['Invoice ID', 'Invoice', 'Invoice #', 'Invoice Number']);
        $clientName = $this->pulseValue($values, ['Client', 'Client Name', 'Customer', 'Customer Name']);
        $amount = $this->moneyToDecimal($this->pulseValue($values, ['Amount', 'Grand Total', 'Total']));

        if ($invoiceId === '' || $clientName === '') {
            return ['payment_created' => false, 'payment_updated' => false, 'skipped' => true, 'client_id' => null];
        }

        $client = $this->findOrCreatePulseNamedClient($clientName, $record, $validated, $sourceSystem);
        $payment = ClientPayment::query()
            ->where('reference', $invoiceId)
            ->first();
        $created = ! $payment;
        $metadata = $payment?->metadata ?? [];
        data_set($metadata, 'imports.disputefox.invoice', [
            'synced_at' => now()->toIso8601String(),
            'source_system' => $sourceSystem,
            'page_url' => $validated['page_url'] ?? null,
            'page_title' => $validated['page_title'] ?? null,
            'profile_url' => trim((string) ($record['profile_url'] ?? '')) ?: null,
            'raw_row' => $values,
            'raw_record' => Arr::except($record, ['values']),
        ]);

        $payload = [
            'client_id' => $client->getKey(),
            'amount' => $amount ?? 0,
            'currency' => 'USD',
            'status' => Str::slug($this->pulseValue($values, ['Status']) ?: 'imported', '_'),
            'gateway_name' => $this->pulseValue($values, ['Payment Type']) ?: null,
            'reference' => $invoiceId,
            'notes' => trim(sprintf(
                'DisputeFox invoice imported. Created: %s. Due: %s.',
                $this->pulseValue($values, ['Create Date']) ?: 'unknown',
                $this->pulseValue($values, ['Due Date']) ?: 'unknown',
            )),
            'metadata' => $metadata,
        ];

        if ($payment) {
            $payment->fill($payload)->save();
            $this->sharePublicBillingIntelligence($client, $payment, 'disputefox_admin_invoice', $values, $validated, $sourceSystem);

            return ['payment_created' => false, 'payment_updated' => true, 'skipped' => false, 'client_id' => $client->getKey()];
        }

        $payment = ClientPayment::query()->create($payload);
        $this->sharePublicBillingIntelligence($client, $payment, 'disputefox_admin_invoice', $values, $validated, $sourceSystem);

        return ['payment_created' => $created, 'payment_updated' => false, 'skipped' => false, 'client_id' => $client->getKey()];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $profile
     * @param  list<array<string, mixed>>  $records
     */
    protected function stagePulseRecordListCapture(array $validated, array $profile, array $records, string $listKind, string $sourceSystem, Request $request): ?MigrationOperatorCapture
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $capture = MigrationOperatorCapture::query()
            ->where('source_system', $sourceSystem)
            ->where('capture_type', 'pulse_'.$listKind)
            ->where('page_url', $validated['page_url'] ?? null)
            ->first() ?? new MigrationOperatorCapture;

        $capture->fill([
            'user_id' => $user->getKey(),
            'source_system' => $sourceSystem,
            'capture_type' => 'pulse_'.$listKind,
            'page_title' => $validated['page_title'] ?? null,
            'page_url' => $validated['page_url'] ?? null,
            'operator_note' => 'Imported by the browser companion from a DisputeFox record list.',
            'extracted_text' => Str::limit(json_encode(array_slice($records, 0, 25), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', 50000, ''),
            'status' => 'imported',
            'metadata' => [
                'captured_via' => 'browser_companion',
                'list_kind' => $listKind,
                'row_count' => count($records),
                'records' => $records,
                'confidence' => $profile['confidence'] ?? null,
            ],
            'processed_at' => now(),
        ]);
        $capture->save();

        return $capture;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $validated
     * @return array{total:int,staged:int,captures_created:int,captures_updated:int,payments_created:int,payments_updated:int,skipped:int,kinds:array<string, int>}
     */
    protected function syncCompanionProfileHistory(Client $client, array $profile, array $validated, string $sourceSystem, Request $request): array
    {
        $stats = [
            'total' => 0,
            'staged' => 0,
            'captures_created' => 0,
            'captures_updated' => 0,
            'payments_created' => 0,
            'payments_updated' => 0,
            'skipped' => 0,
            'kinds' => [],
        ];
        $recordsByKind = [];

        foreach (collect($profile['list_records'] ?? [])->filter(fn ($record) => is_array($record))->take(2000) as $record) {
            $values = $this->companionRecordValues($record);

            if ($this->companionProfileHistoryRecordIsNoise($record, $values)) {
                $stats['skipped']++;

                continue;
            }

            $kind = $this->companionProfileHistoryKind($record, $values, $validated);
            $stats['total']++;
            $stats['kinds'][$kind] = ($stats['kinds'][$kind] ?? 0) + 1;
            $recordsByKind[$kind][] = [
                ...$record,
                'values' => $values,
                'history_kind' => $kind,
            ];

            if ($kind === 'billing') {
                $paymentResult = $this->syncDisputeFoxProfileBillingRecord($client, $record, $values, $validated, $sourceSystem);

                if ($paymentResult['payment_created'] ?? false) {
                    $stats['payments_created']++;
                } elseif ($paymentResult['payment_updated'] ?? false) {
                    $stats['payments_updated']++;
                }
            }
        }

        foreach ($recordsByKind as $kind => $records) {
            $capture = $this->stageCompanionProfileHistoryCapture($client, $validated, $profile, $records, $kind, $sourceSystem, $request);

            if (! $capture) {
                continue;
            }

            $stats['staged'] += count($records);

            if ($capture->wasRecentlyCreated) {
                $stats['captures_created']++;
            } else {
                $stats['captures_updated']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $values
     */
    protected function companionProfileHistoryRecordIsNoise(array $record, array $values): bool
    {
        if ($values === []) {
            return true;
        }

        $tableId = Str::lower(trim((string) ($record['table_id'] ?? '')));

        if (in_array($tableId, [
            'bureauforfreezeletter',
        ], true)) {
            return true;
        }

        if (preg_match('/^(dfmessage|dfallmessage|dfportalmessage|dfleadchat|dfclientsdocuments)/i', $tableId) === 1) {
            return true;
        }

        $keys = collect(array_keys($values))
            ->map(fn (string $key) => Str::of($key)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value())
            ->implode('|');
        $text = Str::of(implode(' ', $values))->lower()->replaceMatches('/\s+/', ' ')->squish()->value();

        if (str_contains($text, 'no records found') || str_contains($text, 'no data available in table')) {
            return true;
        }

        return $keys === 'select bureau|address|fax number';
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $validated
     */
    protected function companionProfileHistoryKind(array $record, array $values, array $validated): string
    {
        $tableId = Str::lower(trim((string) ($record['table_id'] ?? '')));
        $pageTitle = Str::lower(trim((string) ($validated['page_title'] ?? '')));
        $pageUrl = Str::lower(trim((string) ($validated['page_url'] ?? '')));
        $keys = collect(array_keys($values))
            ->map(fn (string $key) => Str::of($key)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value())
            ->implode(' ');
        $haystack = trim("{$tableId} {$pageTitle} {$pageUrl} {$keys}");

        return match (true) {
            str_contains($haystack, 'billing')
                || str_contains($haystack, 'invoice')
                || str_contains($haystack, 'due date grand total')
                || str_contains($haystack, 'billing history') => 'billing',
            str_contains($haystack, 'credit_score_history')
                || str_contains($haystack, 'scores')
                || str_contains($haystack, 'credit score') => 'score_history',
            str_contains($haystack, 'message') => 'messages',
            str_contains($haystack, 'task') || str_contains($haystack, 'tast') => 'tasks',
            str_contains($haystack, 'dispute') => 'disputes',
            str_contains($haystack, 'letter') => 'letters',
            str_contains($haystack, 'result') => 'results',
            str_contains($haystack, 'action plan') || str_contains($haystack, 'action_plan') => 'action_plan',
            str_contains($haystack, 'autofox') || str_contains($haystack, 'automation') => 'automation',
            str_contains($haystack, 'web form') || str_contains($haystack, 'webform') => 'web_forms',
            str_contains($haystack, 'freeze') => 'freeze',
            str_contains($haystack, 'account') => 'account',
            default => 'profile_history',
        };
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $validated
     * @return array{payment_created:bool,payment_updated:bool,skipped:bool,client_id:int|null}
     */
    protected function syncDisputeFoxProfileBillingRecord(Client $client, array $record, array $values, array $validated, string $sourceSystem): array
    {
        $amount = $this->moneyToDecimal($this->pulseValue($values, ['Amount', 'Grand Total', 'Total', 'Paid Amount', 'Balance']));
        $status = $this->pulseValue($values, ['Status', 'Payment Status']) ?: 'imported';
        $reference = $this->pulseValue($values, [
            'Invoice ID',
            'Invoice',
            'Invoice #',
            'Invoice Number',
            'ID',
            'Billing History',
            'Transaction ID',
            'Reference',
        ]);

        if ($amount === null && $reference === '') {
            return ['payment_created' => false, 'payment_updated' => false, 'skipped' => true, 'client_id' => $client->getKey()];
        }

        if ($reference === '') {
            $reference = 'disputefox-profile-'.substr(sha1(json_encode([$client->getKey(), $values, $record], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''), 0, 24);
        } else {
            $reference = $this->normalizedDisputeFoxBillingReference($reference);
        }

        $payment = ClientPayment::query()
            ->where('client_id', $client->getKey())
            ->where('reference', $reference)
            ->first();
        $created = ! $payment;
        $metadata = $payment?->metadata ?? [];
        $paidAt = $this->parseCompanionProfileDate($this->pulseValue($values, ['Paid Date', 'Payment Date', 'Date Paid']));

        data_set($metadata, 'imports.disputefox.profile_billing', [
            'synced_at' => now()->toIso8601String(),
            'source_system' => $sourceSystem,
            'page_url' => $validated['page_url'] ?? null,
            'page_title' => $validated['page_title'] ?? null,
            'table_id' => trim((string) ($record['table_id'] ?? '')) ?: null,
            'profile_url' => trim((string) ($record['profile_url'] ?? '')) ?: null,
            'raw_row' => $values,
            'raw_record' => Arr::except($record, ['values']),
        ]);

        $payload = [
            'client_id' => $client->getKey(),
            'amount' => $amount ?? (float) ($payment?->amount ?? 0),
            'currency' => 'USD',
            'status' => Str::slug($status, '_') ?: 'imported',
            'paid_at' => $paidAt ?? $payment?->paid_at,
            'gateway_name' => $this->pulseValue($values, ['Billing Type', 'Payment Type', 'Gateway']) ?: $payment?->gateway_name,
            'reference' => $reference,
            'notes' => Str::limit(trim(sprintf(
                'DisputeFox billing history imported. Due: %s. Paid: %s.',
                $this->pulseValue($values, ['Due Date']) ?: 'unknown',
                $this->pulseValue($values, ['Paid Date', 'Payment Date', 'Date Paid']) ?: 'unknown',
            )), 1000, ''),
            'metadata' => $metadata,
        ];

        if ($payment) {
            $payment->fill($payload)->save();
            $this->sharePublicBillingIntelligence($client, $payment, 'disputefox_profile_billing', $values, $validated, $sourceSystem);

            return ['payment_created' => false, 'payment_updated' => true, 'skipped' => false, 'client_id' => $client->getKey()];
        }

        $payment = ClientPayment::query()->create($payload);
        $this->sharePublicBillingIntelligence($client, $payment, 'disputefox_profile_billing', $values, $validated, $sourceSystem);

        return ['payment_created' => $created, 'payment_updated' => false, 'skipped' => false, 'client_id' => $client->getKey()];
    }

    protected function normalizedDisputeFoxBillingReference(string $reference): string
    {
        $reference = Str::squish(trim($reference));

        if (preg_match('/(?:DISF|Invoice\\s*#?)\\s*([0-9]+)/i', $reference, $match) === 1) {
            return 'disputefox-invoice-'.$match[1];
        }

        if (preg_match('/#\\s*([0-9]+)/', $reference, $match) === 1) {
            return 'disputefox-invoice-'.$match[1];
        }

        return Str::limit($reference, 255, '');
    }

    /**
     * @param  array<string, string>  $rawValues
     * @param  array<string, mixed>  $validated
     */
    protected function sharePublicBillingIntelligence(
        Client $client,
        ClientPayment $payment,
        string $captureType,
        array $rawValues,
        array $validated,
        string $sourceSystem,
    ): void {
        if (! (bool) config('creditsoft.installer.billing_intelligence_enabled', true)) {
            return;
        }

        $endpoint = trim((string) config('creditsoft.installer.billing_intelligence_url', ''));
        $licenseCode = $this->currentOfficeLicenseCode();

        if ($endpoint === '' || $licenseCode === '') {
            return;
        }

        $clientName = Str::squish(trim($client->display_name ?: "{$client->first_name} {$client->last_name}"));
        $idempotencyKey = sha1(implode('|', [
            $licenseCode,
            $sourceSystem,
            $captureType,
            (string) $client->cuid,
            (string) $payment->reference,
        ]));
        $installerState = app(InstallerState::class)->read();
        $payload = [
            'license_code' => $licenseCode,
            'idempotency_key' => $idempotencyKey,
            'event_type' => 'legacy_billing_imported',
            'source' => 'creditsoft_intranet',
            'source_system' => $sourceSystem,
            'capture_type' => $captureType,
            'page_url' => $validated['page_url'] ?? null,
            'page_title' => $validated['page_title'] ?? null,
            'office' => [
                'company_name' => $installerState['company_name'] ?? null,
                'admin_email' => $installerState['admin_email'] ?? null,
                'tailscale_hostname' => $installerState['tailscale_hostname'] ?? null,
            ],
            'client' => [
                'cuid' => $client->cuid,
                'display_name' => $clientName !== '' ? $clientName : null,
                'status' => $client->status,
                'source_record_id' => data_get($client->metadata, 'imports.disputefox.source_record_id'),
                'source_record_int_id' => data_get($client->metadata, 'imports.disputefox.source_record_int_id'),
            ],
            'billing' => [
                'reference' => $payment->reference,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency ?: 'USD',
                'status' => $payment->status,
                'gateway_name' => $payment->gateway_name,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'notes' => $payment->notes,
            ],
            'legacy_row' => $this->publicBillingSafeRow($rawValues),
            'imported_at' => now()->toIso8601String(),
        ];
        $signal = OutboundSignal::query()
            ->where('client_id', $client->getKey())
            ->where('event_type', 'public.license_billing_intelligence')
            ->where('queued_at', '>=', now()->subDays(30))
            ->latest()
            ->get()
            ->first(fn (OutboundSignal $candidate): bool => (string) data_get($candidate->payload, 'idempotency_key') === $idempotencyKey);

        if (! $signal) {
            $signal = OutboundSignal::create([
                'client_id' => $client->getKey(),
                'event_type' => 'public.license_billing_intelligence',
                'visibility' => 'creditsoft_public_license_intelligence',
                'payload' => $payload,
                'sanitized_payload' => $payload,
                'status' => 'pending',
                'queued_at' => now(),
            ]);
        } else {
            $signal->fill([
                'payload' => $payload,
                'sanitized_payload' => $payload,
                'status' => 'pending',
                'error_message' => null,
            ])->save();
        }

        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-CreditSoft-License' => $licenseCode,
                    'X-CreditSoft-Idempotency-Key' => $idempotencyKey,
                ])
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $signal->fill([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ])->save();

                return;
            }

            $signal->fill([
                'status' => 'pending',
                'error_message' => Str::limit('Public billing intelligence rejected: HTTP '.$response->status().' '.$response->body(), 1000, ''),
            ])->save();
        } catch (\Throwable $exception) {
            $signal->fill([
                'status' => 'pending',
                'error_message' => Str::limit('Public billing intelligence unavailable: '.$exception->getMessage(), 1000, ''),
            ])->save();
        }
    }

    protected function currentOfficeLicenseCode(): string
    {
        $state = app(InstallerState::class)->read();

        return Str::upper(trim((string) (
            $state['license_key']
            ?? data_get($state, 'license.license_key')
            ?? data_get($state, 'license.key')
            ?? ''
        )));
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    protected function publicBillingSafeRow(array $values): array
    {
        return collect($values)
            ->reject(function (string $value, string $key): bool {
                $label = Str::of($key)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

                return str_contains($label, 'password')
                    || str_contains($label, 'passcode')
                    || str_contains($label, 'ssn')
                    || str_contains($label, 'social security')
                    || str_contains($label, 'secret')
                    || str_contains($label, 'token')
                    || str_contains($label, 'security answer')
                    || str_contains($label, 'security question');
            })
            ->map(fn (string $value): string => Str::limit(Str::squish($value), 500, ''))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $profile
     * @param  list<array<string, mixed>>  $records
     */
    protected function stageCompanionProfileHistoryCapture(Client $client, array $validated, array $profile, array $records, string $kind, string $sourceSystem, Request $request): ?MigrationOperatorCapture
    {
        $user = $request->user();

        if (! $user || $records === []) {
            return null;
        }

        $captureType = 'disputefox_profile_'.$kind;
        $pageUrl = $validated['page_url'] ?? null;
        $capture = MigrationOperatorCapture::query()
            ->where('source_system', $sourceSystem)
            ->where('capture_type', $captureType)
            ->where('metadata->client_id', $client->getKey())
            ->first() ?? new MigrationOperatorCapture;

        $clientName = trim($client->display_name ?: "{$client->first_name} {$client->last_name}");
        $existingRecords = collect((array) data_get($capture->metadata ?? [], 'records', []))
            ->filter(fn ($record) => is_array($record));
        $mergedRecords = $existingRecords
            ->merge($records)
            ->mapWithKeys(function (array $record) use ($kind): array {
                $signature = sha1(json_encode([
                    'kind' => $kind,
                    'table_id' => $record['table_id'] ?? null,
                    'values' => $record['values'] ?? [],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $kind);

                return [$signature => $record];
            })
            ->values()
            ->take(1000)
            ->all();
        $sourcePages = collect((array) data_get($capture->metadata ?? [], 'source_pages', []))
            ->push([
                'page_url' => $pageUrl,
                'page_title' => $validated['page_title'] ?? null,
                'seen_at' => now()->toIso8601String(),
            ])
            ->filter(fn ($page) => is_array($page) && filled($page['page_url'] ?? null))
            ->mapWithKeys(fn (array $page) => [(string) ($page['page_url'] ?? '') => $page])
            ->values()
            ->all();

        $capture->fill([
            'user_id' => $user->getKey(),
            'source_system' => $sourceSystem,
            'capture_type' => $captureType,
            'page_title' => $validated['page_title'] ?? null,
            'page_url' => $pageUrl,
            'operator_note' => sprintf('Imported by the browser companion from DisputeFox %s profile history.', str_replace('_', ' ', $kind)),
            'extracted_text' => Str::limit(json_encode(array_slice($mergedRecords, 0, 100), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', 50000, ''),
            'status' => 'imported',
            'metadata' => [
                'captured_via' => 'browser_companion',
                'client_id' => $client->getKey(),
                'client_cuid' => $client->cuid,
                'client_name' => $clientName,
                'source_record_id' => $profile['source_record_id'] !== '' ? $profile['source_record_id'] : null,
                'source_record_int_id' => $profile['source_record_int_id'] !== '' ? $profile['source_record_int_id'] : null,
                'history_kind' => $kind,
                'row_count' => count($mergedRecords),
                'records' => $mergedRecords,
                'source_pages' => $sourcePages,
                'confidence' => $profile['confidence'] ?? null,
            ],
            'processed_at' => now(),
        ]);
        $capture->save();

        return $capture;
    }

    /**
     * @param  array<string, string>  $values
     * @return array{name:string,email:string}
     */
    protected function pulseNameAndEmail(array $values, string $listKind): array
    {
        $nameText = $this->pulseValue($values, ['Name', 'Client', 'Client Name']);
        $email = '';

        foreach ($values as $value) {
            if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $match) === 1) {
                $email = Str::lower($match[0]);
                break;
            }
        }

        $name = trim(preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '', $nameText) ?? $nameText);
        $name = trim(preg_replace('/\bEmailSMS\b/i', '', $name) ?? $name);

        if ($name === '' && $listKind === 'affiliates') {
            $name = trim($this->pulseValue($values, ['First Name']).' '.$this->pulseValue($values, ['Last Name']));
        }

        return [
            'name' => Str::squish($name),
            'email' => $email,
        ];
    }

    /**
     * @param  array<string, string>  $values
     * @param  list<string>  $labels
     */
    protected function pulseValue(array $values, array $labels): string
    {
        $normalized = collect($values)
            ->mapWithKeys(fn (string $value, string $key) => [Str::lower($key) => $value]);

        foreach ($labels as $label) {
            $value = trim((string) $normalized->get(Str::lower($label), ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function findPulseListClient(string $sourceRecordId, string $email, string $firstName, string $lastName, string $sourceRecordIntId = ''): ?Client
    {
        if ($sourceRecordId !== '') {
            $client = Client::query()
                ->where(function ($query) use ($sourceRecordId): void {
                    $query->where('metadata->imports->disputefox->source_record_id', $sourceRecordId)
                        ->orWhere('metadata->imports->disputefox->regular_companion_sync->source_record_id', $sourceRecordId);
                })
                ->first();

            if ($client) {
                return $client;
            }
        }

        if ($sourceRecordIntId !== '') {
            $client = Client::query()
                ->where(function ($query) use ($sourceRecordIntId): void {
                    $query->where('metadata->imports->disputefox->source_record_int_id', $sourceRecordIntId)
                        ->orWhere('metadata->imports->disputefox->regular_companion_sync->source_record_int_id', $sourceRecordIntId);
                })
                ->first();

            if ($client) {
                return $client;
            }
        }

        if ($email !== '') {
            $client = Client::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($client) {
                return $client;
            }
        }

        if ($firstName !== '' && $lastName !== '') {
            return Client::query()
                ->whereRaw('LOWER(first_name) = ?', [Str::lower($firstName)])
                ->whereRaw('LOWER(last_name) = ?', [Str::lower($lastName)])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $validated
     */
    protected function findOrCreatePulseNamedClient(string $clientName, array $record, array $validated, string $sourceSystem): Client
    {
        $sourceRecordId = $this->sourceRecordIdFromPulseRecord($record);
        $nameParts = $this->parseClientName($clientName);
        $client = $this->findPulseListClient($sourceRecordId, '', $nameParts['first_name'], $nameParts['last_name']);

        if ($client) {
            return $client;
        }

        $metadata = [];
        data_set($metadata, 'imports.disputefox.source_record_id', $sourceRecordId !== '' ? $sourceRecordId : null);
        data_set($metadata, 'imports.disputefox.invoice_stub', [
            'created_at' => now()->toIso8601String(),
            'source_system' => $sourceSystem,
            'page_url' => $validated['page_url'] ?? null,
            'profile_url' => trim((string) ($record['profile_url'] ?? '')) ?: null,
        ]);

        return Client::query()->create([
            'cuid' => 'c_'.Str::lower(Str::random(10)),
            'first_name' => $nameParts['first_name'] ?: 'Unknown',
            'last_name' => $nameParts['last_name'] ?: 'Client',
            'status' => 'active_review',
            'metadata' => $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function sourceRecordIdFromPulseRecord(array $record): string
    {
        $sourceRecordId = trim((string) ($record['source_record_id'] ?? ''));

        if ($sourceRecordId !== '') {
            return $sourceRecordId;
        }

        $profileUrl = trim((string) ($record['profile_url'] ?? ''));

        if ($profileUrl !== '') {
            parse_str((string) parse_url($profileUrl, PHP_URL_QUERY), $query);

            return trim((string) ($query['id'] ?? ''));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $values
     */
    protected function pulseRecordSignature(string $listKind, array $record, array $values): string
    {
        return sha1(json_encode([
            'kind' => $listKind,
            'source_record_id' => $record['source_record_id'] ?? null,
            'source_record_int_id' => $record['source_record_int_id'] ?? null,
            'profile_url' => $record['profile_url'] ?? null,
            'values' => $values,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $listKind);
    }

    protected function statusForPulseListRecord(string $listKind, string $status, string $stage): string
    {
        $text = Str::of($status.' '.$stage)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        if ($listKind === 'leads') {
            if (str_contains($text, 'cancel')) {
                return 'canceled';
            }

            if (str_contains($text, 'graduat') || str_contains($text, 'finished') || str_contains($text, 'completed')) {
                return 'graduated';
            }

            if (str_contains($text, 'fired') || str_contains($text, 'terminated')) {
                return 'terminated';
            }

            return 'lead';
        }

        if (str_contains($text, 'cancel')) {
            return 'canceled';
        }

        if (str_contains($text, 'graduat') || str_contains($text, 'finished') || str_contains($text, 'complete')) {
            return 'graduated';
        }

        if (str_contains($text, 'fired') || str_contains($text, 'terminated')) {
            return 'terminated';
        }

        if (str_contains($text, 'monitor')) {
            return 'monitoring';
        }

        return $text !== '' ? 'active_review' : ($listKind === 'leads' ? 'lead' : 'active');
    }

    protected function moneyToDecimal(string $value): ?float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        return $normalized !== '' ? round((float) $normalized, 2) : null;
    }

    protected function findClientSearchMatches(
        string $query,
        string $email,
        string $firstName,
        string $lastName,
        int $limit,
    ): Collection {
        $builder = Client::query()
            ->with('assignedUser');

        if ($email !== '') {
            $builder->whereRaw('LOWER(email) like ?', [$email.'%'])
                ->orderByRaw('CASE WHEN LOWER(email) = ? THEN 0 ELSE 1 END', [$email]);
        }

        if ($firstName !== '' || $lastName !== '') {
            $builder->where(function ($nameQuery) use ($firstName, $lastName): void {
                if ($firstName !== '') {
                    $nameQuery->whereRaw('LOWER(first_name) like ?', [Str::lower($firstName).'%']);
                }

                if ($lastName !== '') {
                    $nameQuery->whereRaw('LOWER(last_name) like ?', [Str::lower($lastName).'%']);
                }
            });

            if ($firstName !== '' && $lastName !== '') {
                $builder->orderByRaw(
                    'CASE WHEN LOWER(first_name) = ? AND LOWER(last_name) = ? THEN 0 ELSE 1 END',
                    [Str::lower($firstName), Str::lower($lastName)],
                );
            }
        }

        if ($query !== '') {
            $queryLower = Str::lower($query);
            $queryNameParts = $this->parseClientName($query);

            $builder->where(function ($lookup) use ($queryLower, $queryNameParts): void {
                $lookup->whereRaw('LOWER(first_name) like ?', ["%{$queryLower}%"])
                    ->orWhereRaw('LOWER(last_name) like ?', ["%{$queryLower}%"])
                    ->orWhereRaw('LOWER(email) like ?', ["%{$queryLower}%"]);

                if ($queryNameParts['first_name'] !== '' && $queryNameParts['last_name'] !== '') {
                    $lookup->orWhere(function ($nameLookup) use ($queryNameParts): void {
                        $nameLookup->whereRaw('LOWER(first_name) like ?', [Str::lower($queryNameParts['first_name']).'%'])
                            ->whereRaw('LOWER(last_name) like ?', [Str::lower($queryNameParts['last_name']).'%']);
                    });
                }
            })->orderByRaw(
                'CASE WHEN LOWER(first_name) = ? AND LOWER(last_name) = ? THEN 0 ELSE 1 END',
                [Str::lower($queryNameParts['first_name']), Str::lower($queryNameParts['last_name'])],
            );
        }

        return $builder
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mergeMetadata(array $existing, array $validated, OfficeGrowthRuntime $growth): array
    {
        $metadata = $existing;

        if (array_key_exists('metadata', $validated)) {
            $metadata = is_array($validated['metadata']) ? array_replace($metadata, $validated['metadata']) : $metadata;
        }

        if (array_key_exists('external_reference', $validated)) {
            if (filled($validated['external_reference'])) {
                $metadata['external_reference'] = $validated['external_reference'];
            } else {
                unset($metadata['external_reference']);
            }
        }

        if (array_key_exists('affiliate_key', $validated)) {
            $affiliate = $growth->affiliateByKey($validated['affiliate_key']);

            if ($affiliate) {
                data_set($metadata, 'intake.affiliate', [
                    'key' => $affiliate['key'],
                    'label' => $affiliate['label'],
                    'email' => $affiliate['email'],
                    'company' => $affiliate['company'],
                    'assigned_to' => $affiliate['assigned_to'],
                ]);
            } else {
                data_forget($metadata, 'intake.affiliate');
            }
        }

        if (array_key_exists('crm_values', $validated)) {
            $crmValues = $this->sanitizeCrmValues(
                is_array($validated['crm_values'] ?? null) ? $validated['crm_values'] : [],
                collect([
                    ...$growth->crmFields('lead'),
                    ...$growth->crmFields('client'),
                ])->unique('key')->values()->all(),
            );

            if ($crmValues !== []) {
                data_set($metadata, 'intake.crm', $crmValues);
            } else {
                data_forget($metadata, 'intake.crm');
            }
        }

        return $metadata;
    }

    /**
     * @return array{file_name:string,file_path:string,mime_type:?string,file_size:int}
     */
    protected function storeClientDocumentFile(Client $client, UploadedFile $file): array
    {
        $directory = rtrim((string) config('creditsoft.document_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$client->getKey();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = now()->format('YmdHis').'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'document').'.'.$extension;

        File::ensureDirectoryExists($directory);
        $file->move($directory, $filename);

        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => (int) (File::size($path) ?: 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeClient(Client $client): array
    {
        return [
            'cuid' => $client->cuid,
            'display_name' => $client->display_name,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'status' => $client->status,
            'current_score' => $client->current_score,
            'email' => $client->email,
            'phone' => $client->phone,
            'goals' => $client->goals,
            'assigned_user' => $client->assignedUser?->name,
            'metadata' => $client->metadata ?? [],
            'created_at' => optional($client->created_at)?->toIso8601String(),
            'updated_at' => optional($client->updated_at)?->toIso8601String(),
        ];
    }

    protected function serializeClientSearchResult(Client $client): array
    {
        $client->loadMissing('assignedUser');
        $latestCycle = $client->reportingCycles()->latest('started_at')->first();

        return [
            ...$this->serializeClient($client),
            'latest_reporting_cycle' => $latestCycle ? [
                'id' => $latestCycle->getKey(),
                'cycle_label' => $latestCycle->cycle_label,
                'started_at' => optional($latestCycle->started_at)?->toDateString(),
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  list<array{label:string,key:string,type:string,target:string,required:bool}>  $fields
     * @return array<string, string|bool>
     */
    protected function sanitizeCrmValues(array $submitted, array $fields): array
    {
        $allowedFields = collect($fields)->keyBy('key');

        return collect($submitted)
            ->mapWithKeys(function ($value, $key) use ($allowedFields): array {
                $field = $allowedFields->get((string) $key);

                if (! $field) {
                    return [];
                }

                if (($field['type'] ?? 'text') === 'checkbox') {
                    return [(string) $key => (bool) $value];
                }

                $normalized = trim((string) $value);

                return $normalized !== '' ? [(string) $key => $normalized] : [];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function officeResponseMeta(OfficeGrowthRuntime $growth): array
    {
        return [
            'office' => [
                ...$growth->officeContext(),
                'signup_subject' => $growth->renderTemplateSubject(
                    'signup_process_auto_password',
                    'Login credentials for your portal access',
                ),
                'follow_up_subject' => $growth->renderTemplateSubject(
                    'signup_process_email_template',
                    'Complete your signup process',
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCompanionProviderAccount(
        ClientProviderAccount $providerAccount,
        ?int $actorId = null,
        bool $includeCredentials = false,
    ): array {
        $providerAccount->loadMissing('client.assignedUser');
        $client = $providerAccount->client;

        return [
            'client' => $client ? $this->serializeClientSearchResult($client) : null,
            'provider_account' => [
                'id' => $providerAccount->getKey(),
                'provider_key' => $providerAccount->provider_key,
                'provider_label' => $providerAccount->provider_label,
                'status' => $providerAccount->status,
                'login_email' => $providerAccount->login_email,
                'login_username' => $providerAccount->login_username,
                'login_password' => $includeCredentials ? $providerAccount->login_password : null,
                'security_answer' => $includeCredentials ? $providerAccount->security_answer : null,
                'has_stored_password' => $providerAccount->hasStoredPassword(),
                'has_stored_security_answer' => $providerAccount->hasStoredSecurityAnswer(),
                'preferred_login' => [
                    'method' => filled($providerAccount->login_username) ? 'username' : 'email',
                    'value' => filled($providerAccount->login_username) ? $providerAccount->login_username : $providerAccount->login_email,
                ],
                'last_imported_at' => optional($providerAccount->last_imported_at)?->toIso8601String(),
                'next_import_due_at' => optional($this->companionNextImportDueAt($providerAccount))?->toIso8601String(),
                'import_interval_hours' => $this->companionImportIntervalHours($providerAccount),
                'lease' => [
                    'worker_id' => $providerAccount->companionMetadata('lease.worker_id'),
                    'claimed_at' => $providerAccount->companionMetadata('lease.claimed_at'),
                    'expires_at' => $providerAccount->companionMetadata('lease.expires_at'),
                ],
                'notes' => $providerAccount->notes,
                'metadata' => $providerAccount->metadata ?? [],
                'credential_health' => $this->providerCredentialHealth($providerAccount),
                'companion' => [
                    'start_url' => $this->providerCompanionStartUrl($providerAccount->provider_key),
                    'logout_url' => $this->providerCompanionLogoutUrl($providerAccount->provider_key),
                ],
            ],
            'assigned_to_current_user' => $actorId !== null && $client && (int) $client->assigned_to === (int) $actorId,
        ];
    }

    /**
     * @return array{
     *     blocked:bool,
     *     invalidated_at:?string,
     *     invalidated_reason:?string,
     *     last_updated_at:?string,
     *     login_updated_at:?string,
     *     password_updated_at:?string,
     *     security_answer_updated_at:?string,
     *     history:list<array<string, mixed>>
     * }
     */
    protected function providerCredentialHealth(ClientProviderAccount $providerAccount): array
    {
        $metadata = $providerAccount->metadata ?? [];
        $lastEvent = data_get($metadata, 'credentials.last_event', []);
        $legacyInvalid = data_get($metadata, 'companion.credentials.invalid')
            ?: data_get($metadata, 'smartcredit.invalid_credentials')
            ?: [];
        $lastStatusEvent = data_get($metadata, 'companion.last_status_event', []);
        $history = data_get($metadata, 'credentials.history', []);

        if (! is_array($lastEvent)) {
            $lastEvent = [];
        }

        if (! is_array($legacyInvalid)) {
            $legacyInvalid = [];
        }

        if (! is_array($lastStatusEvent)) {
            $lastStatusEvent = [];
        }

        if (! is_array($history)) {
            $history = [];
        }

        $lastInvalidEvent = data_get($lastEvent, 'event') === 'invalid_credentials' ? $lastEvent : [];
        $invalidatedAt = data_get($metadata, 'credentials.invalidated_at')
            ?: data_get($lastInvalidEvent, 'occurred_at')
            ?: data_get($legacyInvalid, 'detected_at')
            ?: data_get($lastStatusEvent, 'detected_at');
        $invalidatedReason = data_get($metadata, 'credentials.invalidated_reason')
            ?: data_get($lastInvalidEvent, 'reason')
            ?: data_get($legacyInvalid, 'reason')
            ?: data_get($lastStatusEvent, 'reason');

        return [
            'blocked' => in_array((string) $providerAccount->status, ['needs_credentials', 'blocked', 'disconnected'], true),
            'invalidated_at' => $invalidatedAt ?: null,
            'invalidated_reason' => $invalidatedReason ?: null,
            'last_updated_at' => data_get($metadata, 'credentials.last_updated_at'),
            'login_updated_at' => data_get($metadata, 'credentials.login_updated_at'),
            'password_updated_at' => data_get($metadata, 'credentials.password_updated_at'),
            'security_answer_updated_at' => data_get($metadata, 'credentials.security_answer_updated_at'),
            'history' => collect($history)
                ->filter(fn ($entry): bool => is_array($entry))
                ->map(fn (array $entry): array => Arr::only($entry, [
                    'event',
                    'occurred_at',
                    'source',
                    'reason',
                    'login_changed',
                    'login_saved',
                    'password_saved',
                    'security_answer_saved',
                    'worker_id',
                ]))
                ->values()
                ->all(),
        ];
    }

    protected function providerCompanionStartUrl(?string $providerKey): ?string
    {
        $normalizedKey = Str::slug((string) $providerKey, '_');

        if ($normalizedKey === '') {
            return null;
        }

        return collect(config('creditsoft.client_providers.catalog', []))
            ->firstWhere('key', $normalizedKey)['companion_start_url']
            ?? null;
    }

    protected function providerCompanionLogoutUrl(?string $providerKey): ?string
    {
        $normalizedKey = Str::slug((string) $providerKey, '_');

        if ($normalizedKey === '') {
            return null;
        }

        return collect(config('creditsoft.client_providers.catalog', []))
            ->firstWhere('key', $normalizedKey)['companion_logout_url']
            ?? null;
    }

    protected function detectCompanionProviderKey(array $validated): ?string
    {
        $explicit = Str::slug(trim((string) ($validated['provider_key'] ?? '')), '_');

        if ($explicit !== '') {
            return $explicit;
        }

        $pageUrl = Str::lower(trim((string) ($validated['page_url'] ?? '')));
        $pageTitle = Str::lower(trim((string) ($validated['page_title'] ?? '')));

        return match (true) {
            str_contains($pageUrl, 'smartcredit.com'),
            str_contains($pageTitle, 'smartcredit') => 'smartcredit',
            str_contains($pageUrl, 'creditkarma.com'),
            str_contains($pageTitle, 'credit karma') => 'credit_karma',
            str_contains($pageUrl, 'identityiq.com'),
            str_contains($pageTitle, 'identityiq') => 'identityiq',
            str_contains($pageUrl, 'myscoreiq.com'),
            str_contains($pageTitle, 'myscoreiq') => 'myscoreiq',
            str_contains($pageUrl, 'experian.com') => 'experian',
            str_contains($pageUrl, 'equifax.com') => 'equifax',
            str_contains($pageUrl, 'transunion.com') => 'transunion',
            default => null,
        };
    }

    protected function companionProviderAccountQuery(?string $providerKey, ?int $actorId = null, ?int $excludeProviderAccountId = null, string $queueScope = 'active')
    {
        $queueScope = $this->normalizeCompanionQueueScope($queueScope);

        return ClientProviderAccount::query()
            ->select('client_provider_accounts.*')
            ->join('clients', 'clients.id', '=', 'client_provider_accounts.client_id')
            ->with(['client.assignedUser', 'client.billingProfile', 'client.payments'])
            ->when(
                filled($providerKey),
                fn ($query) => $query->where('client_provider_accounts.provider_key', $providerKey)
            )
            ->whereIn(
                'client_provider_accounts.status',
                $queueScope === 'reactivation'
                    ? ['connected', 'import_only', 'needs_client_payment', 'needs_reactivation']
                    : ['connected', 'import_only']
            )
            ->whereNotNull('client_provider_accounts.login_password')
            ->where(function ($query): void {
                $query->whereNotNull('client_provider_accounts.login_email')
                    ->orWhereNotNull('client_provider_accounts.login_username');
            })
            ->when(
                $excludeProviderAccountId,
                fn ($query) => $query->where('client_provider_accounts.id', '!=', $excludeProviderAccountId)
            )
            ->whereRaw('not ('.$this->companionLeadClientPredicateSql().')')
            ->when(
                $queueScope === 'reactivation',
                fn ($query) => $query
                    ->whereRaw('not ('.$this->companionFiredClientPredicateSql().')')
                    ->whereRaw('('.$this->companionRecoveryClientPredicateSql().')')
                    ->whereRaw('('.$this->companionRecoveryAgePredicateSql().')'),
                fn ($query) => $query
                    ->whereIn('clients.status', $this->companionRunnableClientStatuses())
                    ->whereRaw('not ('.$this->companionEndedClientPredicateSql().')')
                    ->whereRaw('not ('.$this->companionBlockedProviderCredentialPredicateSql().')')
            )
            ->when(
                $actorId,
                fn ($query) => $query->orderByRaw('CASE WHEN clients.assigned_to = ? THEN 0 ELSE 1 END', [$actorId])
            )
            ->when(
                $queueScope === 'reactivation',
                fn ($query) => $query->orderByRaw(
                    "CASE client_provider_accounts.status
                        WHEN 'needs_client_payment' THEN 0
                        WHEN 'needs_reactivation' THEN 1
                        WHEN 'connected' THEN 2
                        WHEN 'import_only' THEN 3
                        ELSE 9
                    END"
                )
            )
            ->orderByRaw(
                "CASE client_provider_accounts.provider_key
                    WHEN 'smartcredit' THEN 0
                    WHEN 'credit_karma' THEN 1
                    WHEN 'identityiq' THEN 2
                    WHEN 'myscoreiq' THEN 3
                    ELSE 9
                END"
            )
            ->orderByRaw("CASE client_provider_accounts.status WHEN 'connected' THEN 0 WHEN 'import_only' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN client_provider_accounts.last_imported_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('client_provider_accounts.last_imported_at')
            ->orderByRaw("CASE clients.status WHEN 'active_review' THEN 0 WHEN 'active' THEN 1 WHEN 'at_risk' THEN 2 WHEN 'monitoring' THEN 3 ELSE 5 END")
            ->orderByDesc('clients.updated_at')
            ->orderBy('clients.last_name')
            ->orderBy('clients.first_name');
    }

    /**
     * @return list<string>
     */
    protected function companionRunnableClientStatuses(): array
    {
        return ['active', 'active_review', 'at_risk', 'monitoring'];
    }

    protected function companionLeadClientPredicateSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(clients.status, '')) = 'lead'",
            "coalesce(clients.metadata::jsonb #>> '{crm,source_kind}', '') = 'lead'",
            "coalesce(clients.metadata::jsonb #>> '{source_kind}', '') = 'lead'",
            "(clients.metadata::jsonb #> '{imports,disputefox,lists,leads}') is not null",
            "lower(coalesce(clients.metadata::jsonb #>> '{imports,disputefox,regular_companion_sync,source_page_url}', '')) like '%type=leads%'",
            "(
                (clients.metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and (clients.metadata::jsonb #> '{imports,disputefox,lists,leads}') is null
                and clients.metadata::text ilike '%Lead Status%'
            )",
        ]);
    }

    protected function companionEndedClientPredicateSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(clients.status, '')) in ('closed', 'resolved', 'archived', 'terminated', 'fired', 'canceled', 'cancelled', 'graduated', 'finished')",
            "lower(coalesce(clients.metadata::jsonb #>> '{engagement_outcome}', '')) in ('fired', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'graduated', 'finished')",
            "lower(coalesce(clients.metadata::jsonb #>> '{ended_reason}', '')) in ('fired', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'graduated', 'finished')",
            "lower(coalesce(clients.metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Status}', '')) in ('closed', 'resolved', 'archived', 'terminated', 'fired', 'canceled', 'cancelled', 'graduated', 'finished')",
            "lower(coalesce(clients.metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Client Status}', '')) in ('closed', 'resolved', 'archived', 'terminated', 'fired', 'canceled', 'cancelled', 'graduated', 'finished')",
            "lower(coalesce(clients.metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Processs}', '')) similar to '%(closed|resolved|archived|terminated|fired|canceled|cancelled|graduated|finished)%'",
            "lower(coalesce(clients.metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Process}', '')) similar to '%(closed|resolved|archived|terminated|fired|canceled|cancelled|graduated|finished)%'",
            "(clients.metadata::jsonb #> '{fired_at}') is not null",
        ]);
    }

    protected function companionFiredClientPredicateSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(clients.status, '')) = 'fired'",
            "lower(coalesce(clients.metadata::jsonb #>> '{engagement_outcome}', '')) in ('fired', 'compliance_risk', 'abusive_behavior')",
            "lower(coalesce(clients.metadata::jsonb #>> '{ended_reason}', '')) in ('fired', 'compliance_risk', 'abusive_behavior')",
            "(clients.metadata::jsonb #> '{fired_at}') is not null",
        ]);
    }

    protected function companionRecoveryClientPredicateSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(clients.status, '')) in ('closed', 'resolved', 'archived', 'terminated', 'canceled', 'cancelled', 'graduated', 'finished')",
            "lower(coalesce(clients.metadata::jsonb #>> '{engagement_outcome}', '')) in ('terminated', 'closed', 'archived', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'graduated', 'finished')",
            "lower(coalesce(clients.metadata::jsonb #>> '{ended_reason}', '')) in ('nonpayment', 'unresponsive', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'terminated', 'closed', 'archived', 'graduated', 'finished')",
            "lower(coalesce(client_provider_accounts.status, '')) in ('needs_client_payment', 'needs_reactivation')",
            "coalesce(client_provider_accounts.metadata::jsonb #>> '{smartcredit,reactivation,detected_at}', '') <> ''",
            "lower(coalesce(client_provider_accounts.metadata::jsonb #>> '{companion,last_status_event,reason}', '')) = 'smartcredit_reactivation'",
        ]);
    }

    protected function companionRecoveryAgePredicateSql(): string
    {
        $lastStatusEventAt = "CASE
            WHEN coalesce(client_provider_accounts.metadata::jsonb #>> '{companion,last_status_event,detected_at}', '') ~ '^\\d{4}-\\d{2}-\\d{2}'
                THEN (client_provider_accounts.metadata::jsonb #>> '{companion,last_status_event,detected_at}')::timestamptz
            ELSE NULL
        END";
        $smartCreditReactivationAt = "CASE
            WHEN coalesce(client_provider_accounts.metadata::jsonb #>> '{smartcredit,reactivation,detected_at}', '') ~ '^\\d{4}-\\d{2}-\\d{2}'
                THEN (client_provider_accounts.metadata::jsonb #>> '{smartcredit,reactivation,detected_at}')::timestamptz
            ELSE NULL
        END";
        $endedAt = "CASE
            WHEN coalesce(clients.metadata::jsonb #>> '{ended_at}', '') ~ '^\\d{4}-\\d{2}-\\d{2}'
                THEN (clients.metadata::jsonb #>> '{ended_at}')::timestamptz
            ELSE NULL
        END";
        $lastRealActivityAt = "coalesce(
            client_provider_accounts.last_imported_at,
            (select max(client_payments.paid_at) from client_payments where client_payments.client_id = clients.id),
            (select max(client_billing_profiles.last_paid_at) from client_billing_profiles where client_billing_profiles.client_id = clients.id),
            {$endedAt},
            clients.created_at
        )";
        $lastProviderBlockAt = "coalesce(
            {$lastStatusEventAt},
            {$smartCreditReactivationAt},
            client_provider_accounts.updated_at
        )";
        $providerNeedsReactivation = implode(' or ', [
            "lower(coalesce(client_provider_accounts.status, '')) in ('needs_client_payment', 'needs_reactivation')",
            "coalesce(client_provider_accounts.metadata::jsonb #>> '{smartcredit,reactivation,detected_at}', '') <> ''",
            "lower(coalesce(client_provider_accounts.metadata::jsonb #>> '{companion,last_status_event,reason}', '')) = 'smartcredit_reactivation'",
        ]);

        return "({$lastRealActivityAt}) <= now() - interval '90 days'
            and not (
                ({$providerNeedsReactivation})
                and ({$lastProviderBlockAt}) > now() - interval '90 days'
            )";
    }

    protected function companionBlockedProviderCredentialPredicateSql(string $table = 'client_provider_accounts'): string
    {
        return implode(' or ', [
            "coalesce({$table}.metadata::jsonb #>> '{credentials,invalidated_at}', '') <> ''",
            "coalesce({$table}.metadata::jsonb #>> '{companion,credentials,invalid,detected_at}', '') <> ''",
            "coalesce({$table}.metadata::jsonb #>> '{smartcredit,invalid_credentials,detected_at}', '') <> ''",
            "coalesce({$table}.metadata::jsonb #>> '{smartcredit,reactivation,detected_at}', '') <> ''",
            "lower(coalesce({$table}.metadata::jsonb #>> '{credentials,last_event,event}', '')) = 'invalid_credentials'",
            "lower(coalesce({$table}.metadata::jsonb #>> '{companion,last_status_event,status}', '')) in ('needs_client_payment', 'needs_reactivation', 'needs_credentials', 'blocked')",
            "lower(coalesce({$table}.metadata::jsonb #>> '{companion,last_status_event,reason}', '')) in ('smartcredit_reactivation', 'smartcredit_invalid_credentials', 'provider_invalid_credentials')",
        ]);
    }

    protected function nextReadyProviderAccount(?string $providerKey, ?int $actorId = null, ?string $workerId = null): ?ClientProviderAccount
    {
        /** @var ClientProviderAccount|null $providerAccount */
        $providerAccount = $this->companionProviderAccountQuery($providerKey, $actorId)
            ->get()
            ->first(fn (ClientProviderAccount $candidate) => $this->companionProviderAccountIsReady($candidate, $workerId));

        return $providerAccount;
    }

    protected function touchProviderAccountImport(Client $client, mixed $capture, ?string $workerId = null): void
    {
        $providerKey = trim((string) (
            data_get($capture, 'metadata.provider_key')
            ?? data_get($capture, 'metadata.provider_capture.provider')
            ?? data_get($capture, 'metadata.smartcredit.provider')
            ?? data_get($capture, 'metadata.credit_karma.provider')
            ?? ''
        ));

        if ($providerKey === '') {
            $pageUrl = Str::lower(trim((string) data_get($capture, 'page_url')));

            if (str_contains($pageUrl, 'smartcredit')) {
                $providerKey = 'smartcredit';
            } elseif (str_contains($pageUrl, 'creditkarma')) {
                $providerKey = 'credit_karma';
            }
        }

        if ($providerKey === '') {
            return;
        }

        $normalizedKey = Str::slug($providerKey, '_');
        $label = collect(config('creditsoft.client_providers.catalog', []))
            ->firstWhere('key', $normalizedKey)['label']
            ?? Str::headline(str_replace('_', ' ', $normalizedKey));

        /** @var ClientProviderAccount $providerAccount */
        $providerAccount = $client->providerAccounts()->firstOrNew([
            'provider_key' => $normalizedKey,
        ]);

        $providerAccount->fill([
            'provider_label' => $providerAccount->provider_label ?: $label,
            'status' => $providerAccount->status ?: 'import_only',
            'metadata' => array_filter([
                ...($providerAccount->metadata ?? []),
                'archive_subject_name' => data_get($capture, 'metadata.provider_capture.subject_name'),
                'office_context' => data_get($capture, 'metadata.smartcredit.office_context', data_get($providerAccount->metadata, 'office_context')),
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
        $providerAccount->save();

        $this->markCompanionProviderAccountImported($providerAccount, now(), $workerId);
    }

    /**
     * @return array{provider_account:?ClientProviderAccount,available_count:int,assigned_available_count:int}
     */
    protected function claimNextCompanionProviderAccount(
        ?string $providerKey,
        ?int $actorId = null,
        ?string $workerId = null,
        ?int $excludeProviderAccountId = null,
        bool $forceUpdate = false,
        string $queueScope = 'active',
    ): array {
        $workerId = $this->normalizeCompanionWorkerId($workerId);
        $queueScope = $this->normalizeCompanionQueueScope($queueScope);

        return DB::transaction(function () use ($providerKey, $actorId, $workerId, $excludeProviderAccountId, $forceUpdate, $queueScope): array {
            $candidates = $this->companionProviderAccountQuery($providerKey, $actorId, $excludeProviderAccountId, $queueScope)
                ->lockForUpdate()
                ->get();

            $ready = $candidates
                ->filter(fn (ClientProviderAccount $candidate) => $this->companionProviderAccountIsReady($candidate, $workerId, $forceUpdate, $queueScope))
                ->values();

            /** @var ClientProviderAccount|null $providerAccount */
            $providerAccount = $ready->first();

            if ($providerAccount) {
                $metadata = $providerAccount->metadata ?? [];
                $expiresAt = now()->addMinutes(max(1, (int) config('creditsoft.api.browser_companion.claim_ttl_minutes', 20)));

                data_set($metadata, 'companion.lease.worker_id', $workerId);
                data_set($metadata, 'companion.lease.claimed_at', now()->toIso8601String());
                data_set($metadata, 'companion.lease.expires_at', $expiresAt->toIso8601String());

                $providerAccount->forceFill([
                    'metadata' => $metadata,
                ])->save();

                $providerAccount->refresh()->loadMissing('client.assignedUser');
            }

            return [
                'provider_account' => $providerAccount,
                'available_count' => $ready->count(),
                'assigned_available_count' => $actorId
                    ? $ready->filter(fn (ClientProviderAccount $candidate) => (int) $candidate->client?->assigned_to === (int) $actorId)->count()
                    : 0,
            ];
        }, 3);
    }

    protected function companionProviderAccountIsReady(ClientProviderAccount $providerAccount, ?string $workerId = null, bool $forceUpdate = false, string $queueScope = 'active'): bool
    {
        $queueScope = $this->normalizeCompanionQueueScope($queueScope);
        $credentialHealth = $this->providerCredentialHealth($providerAccount);

        if ($credentialHealth['blocked'] || $this->companionProviderAccountHasInvalidCredentials($providerAccount)) {
            return false;
        }

        if ($queueScope === 'active' && filled($credentialHealth['invalidated_at'])) {
            return false;
        }

        if ($queueScope === 'reactivation' && ! $this->companionProviderAccountIsRecoveryEligible($providerAccount)) {
            return false;
        }

        if ($providerAccount->provider_key === 'identityiq' && ! $providerAccount->hasStoredSecurityAnswer()) {
            return false;
        }

        $leaseWorkerId = trim((string) $providerAccount->companionMetadata('lease.worker_id', ''));
        $leaseExpiresAt = $this->parseCompanionTimestamp($providerAccount->companionMetadata('lease.expires_at'));

        if ($leaseWorkerId !== '' && $leaseExpiresAt?->isFuture() && $leaseWorkerId !== $this->normalizeCompanionWorkerId($workerId)) {
            return false;
        }

        $nextDueAt = $this->companionNextImportDueAt($providerAccount);

        return $forceUpdate || ! $nextDueAt?->isFuture();
    }

    protected function normalizeCompanionQueueScope(?string $scope): string
    {
        return trim((string) $scope) === 'reactivation' ? 'reactivation' : 'active';
    }

    protected function companionProviderAccountHasInvalidCredentials(ClientProviderAccount $providerAccount): bool
    {
        $metadata = $providerAccount->metadata ?? [];
        $status = Str::lower((string) $providerAccount->status);
        $lastStatus = Str::lower((string) data_get($metadata, 'companion.last_status_event.status', ''));
        $lastReason = Str::lower((string) data_get($metadata, 'companion.last_status_event.reason', ''));
        $lastCredentialEvent = Str::lower((string) data_get($metadata, 'credentials.last_event.event', ''));

        return in_array($status, ['needs_credentials', 'blocked', 'disconnected'], true)
            || filled(data_get($metadata, 'credentials.invalidated_at'))
            || filled(data_get($metadata, 'companion.credentials.invalid.detected_at'))
            || filled(data_get($metadata, 'smartcredit.invalid_credentials.detected_at'))
            || $lastCredentialEvent === 'invalid_credentials'
            || $lastStatus === 'needs_credentials'
            || in_array($lastReason, ['smartcredit_invalid_credentials', 'provider_invalid_credentials'], true);
    }

    protected function companionProviderAccountIsRecoveryEligible(ClientProviderAccount $providerAccount): bool
    {
        $client = $providerAccount->client;

        if (! $client) {
            return false;
        }

        $clientStatus = Str::lower((string) $client->status);
        $providerStatus = Str::lower((string) $providerAccount->status);
        $metadata = $providerAccount->metadata ?? [];
        $clientMetadata = $client->metadata ?? [];
        $reason = Str::lower((string) data_get($clientMetadata, 'ended_reason', ''));
        $outcome = Str::lower((string) data_get($clientMetadata, 'engagement_outcome', ''));
        $lastReason = Str::lower((string) data_get($metadata, 'companion.last_status_event.reason', ''));
        $isFired = $clientStatus === 'fired'
            || in_array($reason, ['fired', 'compliance_risk', 'abusive_behavior'], true)
            || in_array($outcome, ['fired', 'compliance_risk', 'abusive_behavior'], true)
            || filled(data_get($clientMetadata, 'fired_at'));
        $isRecoveryKind = in_array($providerStatus, ['needs_client_payment', 'needs_reactivation'], true)
            || $lastReason === 'smartcredit_reactivation'
            || filled(data_get($metadata, 'smartcredit.reactivation.detected_at'))
            || in_array($clientStatus, ['closed', 'resolved', 'archived', 'terminated', 'canceled', 'cancelled', 'graduated', 'finished'], true)
            || in_array($reason, ['nonpayment', 'unresponsive', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'terminated', 'closed', 'archived', 'graduated', 'finished'], true)
            || in_array($outcome, ['terminated', 'closed', 'archived', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'graduated', 'finished'], true);
        $referenceDate = $this->companionProviderRecoveryReferenceDate($providerAccount);

        return ! $isFired
            && $isRecoveryKind
            && $referenceDate instanceof CarbonInterface
            && $referenceDate->lte(now()->subDays(90))
            && ! $this->companionProviderAccountHasRecentRecoveryBlock($providerAccount);
    }

    protected function companionProviderRecoveryReferenceDate(ClientProviderAccount $providerAccount): ?CarbonInterface
    {
        $client = $providerAccount->client;

        if (! $client) {
            return $providerAccount->last_imported_at ?? $providerAccount->updated_at;
        }

        $latestPayment = $client->payments
            ->filter(fn (ClientPayment $payment): bool => $payment->paid_at instanceof CarbonInterface)
            ->sortByDesc(fn (ClientPayment $payment): int => $payment->paid_at->getTimestamp())
            ->first();

        return $providerAccount->last_imported_at
            ?? $latestPayment?->paid_at
            ?? $client->billingProfile?->last_paid_at
            ?? $this->parseCompanionTimestamp(data_get($client->metadata ?? [], 'ended_at'))
            ?? $client->created_at;
    }

    protected function companionProviderAccountHasRecentRecoveryBlock(ClientProviderAccount $providerAccount): bool
    {
        $metadata = $providerAccount->metadata ?? [];
        $providerStatus = Str::lower((string) $providerAccount->status);
        $lastReason = Str::lower((string) data_get($metadata, 'companion.last_status_event.reason', ''));
        $needsReactivation = in_array($providerStatus, ['needs_client_payment', 'needs_reactivation'], true)
            || $lastReason === 'smartcredit_reactivation'
            || filled(data_get($metadata, 'smartcredit.reactivation.detected_at'));

        if (! $needsReactivation) {
            return false;
        }

        $lastBlockAt = $this->parseCompanionTimestamp(data_get($metadata, 'companion.last_status_event.detected_at'))
            ?? $this->parseCompanionTimestamp(data_get($metadata, 'smartcredit.reactivation.detected_at'))
            ?? $providerAccount->updated_at;

        return $lastBlockAt instanceof CarbonInterface
            && $lastBlockAt->gt(now()->subDays(90));
    }

    protected function companionNextImportDueAt(ClientProviderAccount $providerAccount): ?CarbonInterface
    {
        $stored = $this->parseCompanionTimestamp($providerAccount->companionMetadata('next_import_due_at'));

        if ($stored) {
            return $stored;
        }

        if (! $providerAccount->last_imported_at) {
            return null;
        }

        return $providerAccount->last_imported_at->copy()->addHours($this->companionImportIntervalHours($providerAccount));
    }

    protected function companionImportIntervalHours(ClientProviderAccount $providerAccount): int
    {
        $override = (int) $providerAccount->companionMetadata('poll_interval_hours', 0);

        if ($override > 0) {
            return $this->normalizeCompanionIntervalHours($override);
        }

        $observed = (int) $providerAccount->companionMetadata('observed_import_interval_hours', 0);

        if ($observed > 0) {
            return $this->normalizeCompanionIntervalHours($observed);
        }

        return $this->normalizeCompanionIntervalHours(
            (int) config('creditsoft.api.browser_companion.default_import_interval_hours', 168)
        );
    }

    protected function normalizeCompanionIntervalHours(int $hours): int
    {
        $minimum = max(1, (int) config('creditsoft.api.browser_companion.minimum_import_interval_hours', 24));
        $maximum = max($minimum, (int) config('creditsoft.api.browser_companion.maximum_import_interval_hours', 720));
        $hours = max($minimum, min($maximum, $hours));

        $buckets = [24, 48, 72, 96, 168, 336, 720];

        return collect($buckets)
            ->map(fn (int $bucket) => max($minimum, min($maximum, $bucket)))
            ->sort(fn (int $left, int $right) => abs($left - $hours) <=> abs($right - $hours))
            ->first() ?? $hours;
    }

    protected function deriveCompanionIntervalHours(ClientProviderAccount $providerAccount, CarbonInterface $importedAt): int
    {
        if (! $providerAccount->last_imported_at) {
            return $this->companionImportIntervalHours($providerAccount);
        }

        $gapHours = max(1, $providerAccount->last_imported_at->diffInHours($importedAt));

        return $this->normalizeCompanionIntervalHours($gapHours);
    }

    protected function markCompanionProviderAccountImported(ClientProviderAccount $providerAccount, CarbonInterface $importedAt, ?string $workerId = null): void
    {
        $metadata = $providerAccount->metadata ?? [];
        $intervalHours = $this->deriveCompanionIntervalHours($providerAccount, $importedAt);

        data_set($metadata, 'companion.observed_import_interval_hours', $intervalHours);
        data_set($metadata, 'companion.last_worker_id', $this->normalizeCompanionWorkerId($workerId));
        data_set($metadata, 'companion.last_imported_at', $importedAt->toIso8601String());
        data_set($metadata, 'companion.next_import_due_at', $importedAt->copy()->addHours($intervalHours)->toIso8601String());
        data_set($metadata, 'companion.lease.worker_id', null);
        data_set($metadata, 'companion.lease.claimed_at', null);
        data_set($metadata, 'companion.lease.expires_at', null);

        $providerAccount->forceFill([
            'last_imported_at' => $importedAt,
            'metadata' => $metadata,
        ])->save();
    }

    protected function parseCompanionTimestamp(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    protected function appendProviderCredentialHistory(array $metadata, string $event, array $details = [], ?CarbonInterface $occurredAt = null): array
    {
        $occurredAt ??= now();
        $safeDetails = Arr::except($details, ['password', 'login_password', 'security_answer']);
        $entry = [
            'event' => $event,
            'occurred_at' => $occurredAt->toIso8601String(),
            ...array_filter($safeDetails, fn ($value) => $value !== null && $value !== ''),
        ];
        $history = data_get($metadata, 'credentials.history', []);

        if (! is_array($history)) {
            $history = [];
        }

        $history[] = $entry;

        data_set($metadata, 'credentials.last_event', $entry);
        data_set($metadata, 'credentials.history', array_slice($history, -25));

        if ($event === 'credentials_saved') {
            data_set($metadata, 'credentials.last_updated_at', $entry['occurred_at']);
            data_set($metadata, 'credentials.last_source', $entry['source'] ?? null);
            data_set($metadata, 'credentials.invalidated_at', null);
            data_set($metadata, 'credentials.invalidated_reason', null);
            data_set($metadata, 'companion.last_status_event', null);
            data_set($metadata, 'smartcredit.reactivation', null);

            if (! empty($entry['login_saved']) || ! empty($entry['login_changed'])) {
                data_set($metadata, 'credentials.login_updated_at', $entry['occurred_at']);
            }

            if (! empty($entry['password_saved'])) {
                data_set($metadata, 'credentials.password_updated_at', $entry['occurred_at']);
            }

            if (! empty($entry['security_answer_saved'])) {
                data_set($metadata, 'credentials.security_answer_updated_at', $entry['occurred_at']);
            }
        }

        if ($event === 'invalid_credentials') {
            data_set($metadata, 'credentials.invalidated_at', $entry['occurred_at']);
            data_set($metadata, 'credentials.invalidated_reason', $entry['reason'] ?? 'provider_invalid_credentials');
        }

        return $metadata;
    }

    protected function normalizeCompanionWorkerId(?string $value): string
    {
        $workerId = trim((string) $value);

        if ($workerId === '') {
            return 'office-browser-companion';
        }

        $normalized = preg_replace('/[^A-Za-z0-9._:-]+/', '-', $workerId) ?: 'office-browser-companion';

        return substr($normalized, 0, 120);
    }
}
