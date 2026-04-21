<?php

namespace App\Filament\Widgets;

use App\Models\AuditEntry;
use App\Models\Client;
use App\Models\MetricSnapshot;
use App\Models\OutboundSignal;
use App\Models\ViolationCandidate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Clients', number_format(Client::query()->count()))
                ->description('Active local dossiers'),
            Stat::make('Open violations', number_format(ViolationCandidate::query()->whereIn('status', ['open', 'confirmed'])->count()))
                ->description('Items needing review or action'),
            Stat::make('Pending signals', number_format(OutboundSignal::query()->where('status', 'pending')->count()))
                ->description('Sanitized outbound sync queue'),
            Stat::make('MRR', '$'.number_format((float) (MetricSnapshot::query()->where('key', 'mrr')->latest('bucket_date')->value('value') ?? 0)))
                ->description('Current revenue headline'),
            Stat::make('Audit events (24h)', number_format(AuditEntry::query()->where('created_at', '>=', now()->subDay())->count()))
                ->description('Recent oversight activity'),
        ];
    }
}
