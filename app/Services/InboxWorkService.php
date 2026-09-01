<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Task;
use App\Models\ViolationCandidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class InboxWorkService
{
    public function __construct(
        protected OperationalReminderService $operationalReminders,
    ) {}

    /**
     * @return array{leads:int,tasks:int,reviews:int,reminders:int,total:int}
     */
    public function counts(): array
    {
        $counts = [
            'leads' => $this->leadQuery()->count(),
            'tasks' => Task::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'reviews' => ViolationCandidate::query()
                ->where('status', 'open')
                ->where('severity', 'high')
                ->count(),
            'reminders' => $this->operationalReminders->activeCount(),
        ];

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @return array{
     *     items:list<array<string, mixed>>,
     *     pagination:array{current_page:int,last_page:int,per_page:int,total:int,from:?int,to:?int,has_more_pages:bool}
     * }
     */
    public function leadPage(int $page = 1, int $perPage = 25): array
    {
        $perPage = min(100, max(10, $perPage));
        $paginator = $this->leadFeedQuery()
            ->paginate($perPage, ['*'], 'lead_page', max(1, $page));

        return [
            'items' => $paginator->getCollection()
                ->map(fn (Client $client): array => $this->serializeLead($client))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leadItems(int $limit = 25): array
    {
        return $this->leadFeedQuery()
            ->take($limit)
            ->get()
            ->map(fn (Client $client): array => $this->serializeLead($client))
            ->values()
            ->all();
    }

    protected function leadFeedQuery(): Builder
    {
        return $this->leadQuery()
            ->with(['assignedUser:id,name', 'billingProfile:id,client_id,status,last_paid_at,next_due_at'])
            ->withCount([
                'providerAccounts as provider_account_count',
                'documents as document_file_count' => fn (Builder $query) => $query->where('file_size', '>', 0),
                'payments as payment_count',
            ])
            ->latest('updated_at')
            ->latest('id');
    }

    public function leadQuery(): Builder
    {
        return Client::query()
            ->whereRaw('('.$this->leadPredicateSql().')')
            ->whereRaw("(metadata::jsonb #>> '{inbox,reviewed_at}') is null")
            ->whereRaw("(metadata::jsonb #>> '{inbox,ignored_at}') is null")
            ->whereRaw('not ('.$this->endedRelationshipPredicateSql().')');
    }

    /**
     * Keep this strict enough for the inbox: lead queue items should disappear
     * once promoted, deleted, or relationship-ended.
     */
    protected function leadPredicateSql(): string
    {
        return implode(' or ', [
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and lower(coalesce(status, '')) = 'lead'
            )",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and coalesce(metadata::jsonb #>> '{crm,source_kind}', '') = 'lead'
            )",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and coalesce(metadata::jsonb #>> '{source_kind}', '') = 'lead'
            )",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and (metadata::jsonb #> '{imports,disputefox,lists,leads}') is not null
            )",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,regular_companion_sync,source_page_url}', '')) like '%type=leads%'",
        ]);
    }

    protected function endedRelationshipPredicateSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(status, '')) in ('terminated', 'fired', 'canceled', 'cancelled', 'resolved', 'graduated', 'finished')",
            "lower(coalesce(metadata::jsonb #>> '{ended_reason}', '')) in ('nonpayment', 'unresponsive', 'compliance_risk', 'abusive_behavior', 'other', 'terminated', 'closed', 'archived', 'fired', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'graduated', 'finished')",
            "lower(coalesce(metadata::jsonb #>> '{engagement_outcome}', '')) in ('terminated', 'closed', 'archived', 'fired', 'requested_cancellation', 'canceled', 'cancelled', 'goals_met', 'no_longer_needed_help', 'graduated', 'finished')",
            "(metadata::jsonb #> '{fired_at}') is not null",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeLead(Client $client): array
    {
        $metadata = $client->metadata ?? [];
        $leadList = (array) data_get($metadata, 'imports.disputefox.lists.leads', []);
        $rawRow = (array) data_get($leadList, 'raw_row', []);
        $sourceLabel = trim((string) (
            data_get($leadList, 'page_title')
            ?: data_get($leadList, 'source_name')
            ?: data_get($rawRow, 'Lead Source')
            ?: data_get($metadata, 'portal.source')
            ?: data_get($metadata, 'source_label')
            ?: 'Lead intake'
        ));
        $email = Str::lower(trim((string) $client->email));
        $contactBits = collect([
            $email,
            $client->phone,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
        $profileUrl = data_get($leadList, 'profile_url')
            ?: data_get($metadata, 'imports.disputefox.regular_companion_sync.source_page_url');
        $updatedAt = $client->updated_at ?: $client->created_at;

        return [
            'id' => $client->getKey(),
            'display_name' => $client->display_name ?: ($client->cuid ?: 'Unnamed lead'),
            'status' => $client->status,
            'source_label' => $sourceLabel,
            'assigned_user' => $client->assignedUser?->name,
            'email' => $email !== '' ? $email : null,
            'avatar_url' => $email !== ''
                ? 'https://www.gravatar.com/avatar/'.md5($email).'?s=96&d=404&r=g'
                : null,
            'contact_label' => $contactBits !== [] ? implode(' · ', $contactBits) : 'No contact saved',
            'profile_url' => filled($profileUrl) ? (string) $profileUrl : null,
            'updated_at' => $updatedAt?->toIso8601String(),
            'updated_label' => $updatedAt ? $updatedAt->diffForHumans() : null,
            'provider_account_count' => (int) ($client->provider_account_count ?? 0),
            'document_file_count' => (int) ($client->document_file_count ?? 0),
            'payment_count' => (int) ($client->payment_count ?? 0),
            'billing_status' => $client->billingProfile?->status,
            'needs_provider_credentials' => ((int) ($client->provider_account_count ?? 0)) === 0,
            'has_files' => ((int) ($client->document_file_count ?? 0)) > 0,
            'has_payment' => ((int) ($client->payment_count ?? 0)) > 0,
            'href' => "/clients/{$client->getKey()}?view=leads",
            'initials' => Str::of($client->display_name ?: 'Lead')
                ->explode(' ')
                ->map(fn ($part): string => Str::substr((string) $part, 0, 1))
                ->filter()
                ->take(2)
                ->implode(''),
        ];
    }
}
