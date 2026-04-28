<?php

namespace App\Filament\Resources\ViolationCandidates\Pages;

use App\Filament\Resources\ViolationCandidates\ViolationCandidateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageViolationCandidates extends ManageRecords
{
    protected static string $resource = ViolationCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return ViolationCandidateResource::canCreate()
            ? [CreateAction::make()]
            : [];
    }
}
