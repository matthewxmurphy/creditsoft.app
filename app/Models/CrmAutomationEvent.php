<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmAutomationEvent extends Model
{
    protected $fillable = [
        'provider',
        'external_event_id',
        'idempotency_key',
        'event_type',
        'object_type',
        'object_id',
        'client_id',
        'status',
        'priority',
        'payload',
        'signals',
        'decision',
        'processed_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signals' => 'array',
            'decision' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
