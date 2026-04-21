<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationDiscovery extends Model
{
    use HasFactory;

    protected $fillable = [
        'last_seen_by_user_id',
        'source_system',
        'source_product',
        'page_kind',
        'source_identifier',
        'source_signature',
        'name',
        'status',
        'category',
        'workflow_type',
        'start_condition',
        'condition_count',
        'action_count',
        'step_count',
        'seen_count',
        'first_seen_at',
        'last_seen_at',
        'promoted_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'condition_count' => 'integer',
            'action_count' => 'integer',
            'step_count' => 'integer',
            'seen_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'promoted_at' => 'datetime',
        ];
    }

    public function lastSeenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_seen_by_user_id');
    }
}
