<?php

namespace App\Http\Controllers;

use Carbon\CarbonInterface;
use App\Models\AuditEntry;
use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\ClientPayment;
use App\Models\CashAppPaymentRequest;
use App\Models\MetricSnapshot;
use App\Models\OfficeBillingSetting;
use App\Models\Task;
use App\Models\ViolationCandidate;
use App\Services\CreditsoftUpdateFeed;
use App\Services\CreditReportComparisonService;
use App\Services\OfficeCashAppPaymentService;
use App\Services\OfficeZellePaymentService;
use App\Services\OfficeImpactStatsService;
use App\Services\OperationalReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CreditReportComparisonService $comparisonService,
        OperationalReminderService $operationalReminders,
        CreditsoftUpdateFeed $updateFeed,
        OfficeImpactStatsService $impactStats,
    ): Response
    {
        if ($request->query('panel') === 'billing') {
            return Inertia::location(route('billing.index'));
        }

        $latestCycle = Client::query()
            ->with(['reportingCycles.bureauSnapshots.tradelines'])
            ->get()
            ->flatMap(fn (Client $client) => $client->reportingCycles)
            ->sortByDesc('started_at')
            ->first();
        $openTaskQuery = Task::query()->whereIn('status', ['open', 'in_progress']);
        $billingProfiles = ClientBillingProfile::query()
            ->with('client')
            ->orderByDesc('updated_at')
            ->get();
        $payments = ClientPayment::query()
            ->with(['client', 'billingProfile'])
            ->latest('paid_at')
            ->latest()
            ->get();
        $mrr = $this->monthlyRecurringRevenue($billingProfiles);
        $revenueTrend = $this->paymentTrendLine($payments);

        return Inertia::render('Dashboard', [
            'kpis' => [
                'clients' => Client::query()->count(),
                'paying_members' => $billingProfiles->filter(fn (ClientBillingProfile $profile) => $profile->isRecurringActive())->count(),
                'open_tasks' => (clone $openTaskQuery)->count() + $operationalReminders->activeCount(),
                'open_violations' => ViolationCandidate::query()->whereIn('status', ['open', 'confirmed'])->count(),
                'mrr' => $mrr > 0 ? $mrr : (float) (MetricSnapshot::query()->where('key', 'mrr')->latest('bucket_date')->value('value') ?? 0),
            ],
            'latestCycleSummary' => $latestCycle ? $comparisonService->reviewSummary($latestCycle) : null,
            'hotQueue' => $operationalReminders->prependToTaskFeed(
                Task::query()
                ->with('client')
                ->whereIn('status', ['open', 'in_progress'])
                ->orderBy('due_at')
                ->take(6)
                ->get(),
                6,
            ),
            'recentAudit' => AuditEntry::query()->with('user')->latest()->take(6)->get(),
            'trendLine' => $revenueTrend !== []
                ? $revenueTrend
                : MetricSnapshot::query()
                    ->where('key', 'mrr')
                    ->orderBy('bucket_date')
                    ->get(['bucket_date', 'value']),
            'impact' => $impactStats->summary(),
            'billing' => $this->billingPayload($request),
            'updates' => $updateFeed->current(),
        ]);
    }

    public function billing(
        Request $request,
        OfficeZellePaymentService $zellePayments,
        OfficeCashAppPaymentService $cashAppPayments,
    ): Response
    {
        return Inertia::render('Billing', [
            'billing' => $this->billingPayload($request, $zellePayments, $cashAppPayments),
        ]);
    }

    public function updateBillingSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'gateway_provider' => ['nullable', 'string', 'max:120'],
            'gateway_status' => ['required', 'in:manual,connected,needs_attention,disconnected'],
            'gateway_account_label' => ['nullable', 'string', 'max:255'],
            'gateway_environment' => ['nullable', 'string', 'max:120'],
            'webhook_status' => ['nullable', 'string', 'max:120'],
            'gateway_connected_at' => ['nullable', 'date'],
            'payment_portal_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);

        $settings = OfficeBillingSetting::query()->firstOrNew();
        $settings->fill($validated);
        $settings->save();

        return redirect()->route('billing.index');
    }

    public function storeBillingProfile(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'status' => ['required', 'in:active,trial,paused,cancelled'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_interval' => ['required', 'in:weekly,monthly,annual,lifetime'],
            'started_at' => ['nullable', 'date'],
            'last_paid_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'gateway_name' => ['nullable', 'string', 'max:120'],
            'gateway_customer_id' => ['nullable', 'string', 'max:255'],
            'gateway_subscription_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        ClientBillingProfile::query()->updateOrCreate(
            ['client_id' => $validated['client_id']],
            [
                'status' => $validated['status'],
                'amount' => $validated['amount'],
                'currency' => strtoupper((string) ($validated['currency'] ?? 'USD')),
                'billing_interval' => $validated['billing_interval'],
                'started_at' => $validated['started_at'] ?? null,
                'last_paid_at' => $validated['last_paid_at'] ?? null,
                'next_due_at' => $validated['next_due_at'] ?? null,
                'gateway_name' => $validated['gateway_name'] ?? null,
                'gateway_customer_id' => $validated['gateway_customer_id'] ?? null,
                'gateway_subscription_id' => $validated['gateway_subscription_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return redirect()->route('billing.index');
    }

    public function storePayment(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['required', 'in:paid,pending,failed,refunded'],
            'paid_at' => ['nullable', 'date'],
            'gateway_name' => ['nullable', 'string', 'max:120'],
            'gateway_transaction_id' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $profile = ClientBillingProfile::query()->where('client_id', $validated['client_id'])->first();

        $payment = ClientPayment::query()->create([
            'client_id' => $validated['client_id'],
            'client_billing_profile_id' => $profile?->getKey(),
            'amount' => $validated['amount'],
            'currency' => strtoupper((string) ($validated['currency'] ?? 'USD')),
            'status' => $validated['status'],
            'paid_at' => $validated['paid_at'] ?? null,
            'gateway_name' => $validated['gateway_name'] ?? null,
            'gateway_transaction_id' => $validated['gateway_transaction_id'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($profile && $payment->status === 'paid' && $payment->paid_at) {
            $profile->last_paid_at = $payment->paid_at;
            $profile->next_due_at = $this->nextDueAt($profile, $payment->paid_at);
            $profile->save();
        }

        return redirect()->route('billing.index');
    }

    public function updateZelleSettings(Request $request, OfficeZellePaymentService $zellePayments): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['required', 'in:ssl,tls,none'],
            'imap_username' => ['nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:1000'],
            'imap_folder' => ['nullable', 'string', 'max:255'],
            'expected_subject' => ['nullable', 'string', 'max:500'],
            'trusted_domains' => ['nullable', 'string', 'max:2000'],
            'delete_after_import' => ['sometimes', 'boolean'],
        ]);

        $zellePayments->updateSettings($validated);

        return redirect()
            ->route('billing.index')
            ->with('toast', ['type' => 'success', 'message' => 'Zelle mailbox settings saved.']);
    }

    public function syncZellePayments(Request $request, OfficeZellePaymentService $zellePayments): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $result = $zellePayments->syncInbox(100);

        if (empty($result['success'])) {
            return redirect()
                ->route('billing.index')
                ->with('toast', ['type' => 'error', 'message' => (string) ($result['error'] ?? 'Zelle sync failed.')]);
        }

        return redirect()
            ->route('billing.index')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Zelle inbox checked. Fetched '.(int) ($result['fetched'] ?? 0).', processed '.(int) ($result['processed'] ?? 0).', needs review '.(int) ($result['needs_review'] ?? 0).', deleted '.(int) ($result['deleted'] ?? 0).'.',
            ]);
    }

    public function updateCashAppSettings(Request $request, OfficeCashAppPaymentService $cashAppPayments): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'environment' => ['required', 'in:sandbox,production'],
            'api_base_url' => ['nullable', 'url', 'max:2048'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'api_key_id' => ['nullable', 'string', 'max:1000'],
            'api_secret' => ['nullable', 'string', 'max:2000'],
            'region' => ['required', 'string', 'max:24'],
            'scope_id' => ['nullable', 'string', 'max:255'],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'redirect_url' => ['nullable', 'url', 'max:2048'],
            'user_agent' => ['nullable', 'string', 'max:255'],
            'auto_capture' => ['sometimes', 'boolean'],
        ]);

        $cashAppPayments->updateSettings($validated);

        return redirect()
            ->route('billing.index')
            ->with('toast', ['type' => 'success', 'message' => 'Cash App Pay API settings saved.']);
    }

    public function createCashAppRequest(Request $request, OfficeCashAppPaymentService $cashAppPayments): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'redirect_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $result = $cashAppPayments->createCustomerRequest($validated);

        if (empty($result['success'])) {
            return redirect()
                ->route('billing.index')
                ->with('toast', ['type' => 'error', 'message' => (string) ($result['error'] ?? 'Cash App Pay request failed.')]);
        }

        return redirect()
            ->route('billing.index')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Cash App Pay request created. Open the returned QR or desktop URL from the Cash App API request list.',
            ]);
    }

    public function syncCashAppRequest(
        Request $request,
        CashAppPaymentRequest $cashAppPaymentRequest,
        OfficeCashAppPaymentService $cashAppPayments,
    ): RedirectResponse {
        abort_unless($request->user()?->canManageUsers(), 403);

        $result = $cashAppPayments->syncCustomerRequest($cashAppPaymentRequest);

        if (empty($result['success'])) {
            return redirect()
                ->route('billing.index')
                ->with('toast', ['type' => 'error', 'message' => (string) ($result['error'] ?? 'Cash App Pay sync failed.')]);
        }

        return redirect()
            ->route('billing.index')
            ->with('toast', ['type' => 'success', 'message' => 'Cash App Pay request synced from the API.']);
    }

    protected function billingPayload(
        Request $request,
        ?OfficeZellePaymentService $zellePayments = null,
        ?OfficeCashAppPaymentService $cashAppPayments = null,
    ): array
    {
        $billingProfiles = ClientBillingProfile::query()
            ->with('client')
            ->orderByDesc('updated_at')
            ->get();
        $payments = ClientPayment::query()
            ->with(['client', 'billingProfile'])
            ->latest('paid_at')
            ->latest()
            ->get();
        $officeBilling = OfficeBillingSetting::query()->first();

        return [
            'settings' => [
                'gateway_provider' => $officeBilling?->gateway_provider,
                'gateway_status' => $officeBilling?->gateway_status ?? 'manual',
                'gateway_account_label' => $officeBilling?->gateway_account_label,
                'gateway_environment' => $officeBilling?->gateway_environment,
                'webhook_status' => $officeBilling?->webhook_status,
                'gateway_connected_at' => optional($officeBilling?->gateway_connected_at)?->toDateTimeString(),
                'payment_portal_url' => $officeBilling?->payment_portal_url,
                'notes' => $officeBilling?->notes,
            ],
            'stats' => [
                'recorded_revenue' => round((float) $payments->where('status', 'paid')->sum('amount'), 2),
                'last_paid_at' => optional($payments->firstWhere('status', 'paid')?->paid_at)?->toDateTimeString(),
            ],
            'zelle' => ($zellePayments ?? app(OfficeZellePaymentService::class))->payload(),
            'cash_app' => ($cashAppPayments ?? app(OfficeCashAppPaymentService::class))->payload(),
            'clients' => Client::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(fn (Client $client) => [
                    'id' => $client->getKey(),
                    'display_name' => $client->display_name,
                    'email' => $client->email,
                ]),
            'profiles' => $billingProfiles->map(fn (ClientBillingProfile $profile) => [
                'id' => $profile->getKey(),
                'client_id' => $profile->client_id,
                'client_name' => $profile->client?->display_name,
                'status' => $profile->status,
                'amount' => (float) $profile->amount,
                'currency' => $profile->currency,
                'billing_interval' => $profile->billing_interval,
                'monthly_amount' => $profile->monthlyRecurringAmount(),
                'started_at' => optional($profile->started_at)?->toDateString(),
                'last_paid_at' => optional($profile->last_paid_at)?->toDateTimeString(),
                'next_due_at' => optional($profile->next_due_at)?->toDateTimeString(),
                'gateway_name' => $profile->gateway_name,
                'gateway_customer_id' => $profile->gateway_customer_id,
                'gateway_subscription_id' => $profile->gateway_subscription_id,
                'notes' => $profile->notes,
            ])->values(),
            'recent_payments' => $payments->take(8)->map(fn (ClientPayment $payment) => [
                'id' => $payment->getKey(),
                'client_id' => $payment->client_id,
                'client_name' => $payment->client?->display_name,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => optional($payment->paid_at)?->toDateTimeString(),
                'gateway_name' => $payment->gateway_name,
                'gateway_transaction_id' => $payment->gateway_transaction_id,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
            ])->values(),
            'can_manage_billing' => $request->user()?->canManageUsers() ?? false,
        ];
    }

    protected function monthlyRecurringRevenue(Collection $profiles): float
    {
        return round(
            $profiles
                ->filter(fn (ClientBillingProfile $profile) => $profile->isRecurringActive())
                ->sum(fn (ClientBillingProfile $profile) => $profile->monthlyRecurringAmount()),
            2,
        );
    }

    /**
     * @return array<int, array{bucket_date:string,value:float}>
     */
    protected function paymentTrendLine(Collection $payments): array
    {
        return $payments
            ->filter(fn (ClientPayment $payment) => $payment->status === 'paid' && $payment->paid_at)
            ->groupBy(fn (ClientPayment $payment) => $payment->paid_at->copy()->startOfMonth()->toDateString())
            ->map(fn (Collection $group, string $bucketDate) => [
                'bucket_date' => $bucketDate,
                'value' => round((float) $group->sum('amount'), 2),
            ])
            ->sortBy('bucket_date')
            ->values()
            ->all();
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
