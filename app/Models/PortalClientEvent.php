<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalClientEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'source',
        'source_event_id',
        'event_type',
        'tool_key',
        'title',
        'summary',
        'message',
        'score',
        'status',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
