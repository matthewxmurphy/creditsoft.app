<?php

namespace App\Filament\Resources\OutboundSignals\Pages;

use App\Filament\Resources\OutboundSignals\OutboundSignalResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOutboundSignals extends ManageRecords
{
    protected static string $resource = OutboundSignalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
