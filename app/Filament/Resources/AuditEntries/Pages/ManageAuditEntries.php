<?php

namespace App\Filament\Resources\AuditEntries\Pages;

use App\Filament\Resources\AuditEntries\AuditEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditEntries extends ManageRecords
{
    protected static string $resource = AuditEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
