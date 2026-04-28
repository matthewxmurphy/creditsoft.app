<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\MetricSnapshot;
use App\Models\ReportingCycle;
use App\Models\Task;
use App\Models\ViolationCandidate;
use App\Services\OfficeImpactStatsService;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CfoController extends Controller
{
    public function index(OfficeImpactStatsService $impactStats): Response
    {
        $billingProfiles = ClientBillingProfile::query()->get();
        $mrrSeries = MetricSnapshot::query()
            ->where('key', 'mrr')
            ->orderBy('bucket_date')
            ->get(['bucket_date', 'value']);
        $impact = $impactStats->summary();

        return Inertia::render('cfo/Index', [
            'headline' => [
                'mrr' => $this->monthlyRecurringRevenue($billingProfiles) ?: (float) ($mrrSeries->last()?->value ?? 0),
                'active_clients' => Client::query()->count(),
                'avg_lifespan_months' => $impact['average_client_lifespan_months'],
                'new_client_velocity' => Client::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'case_throughput' => ReportingCycle::query()->whereNotNull('reviewed_at')->where('reviewed_at', '>=', now()->subDays(30))->count(),
                'staff_throughput' => Task::query()->where('status', 'done')->where('updated_at', '>=', now()->subDays(30))->count(),
                'churn_signals' => Client::query()->where('status', 'at_risk')->count(),
                'open_violations' => ViolationCandidate::query()->whereIn('status', ['open', 'confirmed'])->count(),
            ],
            'mrrSeries' => $mrrSeries,
            'statusBreakdown' => Client::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->get(),
        ]);
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
}
