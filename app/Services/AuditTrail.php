<?php

namespace App\Services;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(?User $user, string $event, string $summary, ?Model $auditable = null, array $context = []): AuditEntry
    {
        return AuditEntry::create([
            'user_id' => $user?->getKey(),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'event' => $event,
            'summary' => $summary,
            'context' => $context,
        ]);
    }
}
