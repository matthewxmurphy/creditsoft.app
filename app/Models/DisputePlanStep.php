<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputePlanStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispute_plan_id', 'step_key', 'sequence', 'round', 'title', 'description',
        'action_type', 'status', 'scheduled_for', 'queued_at', 'completed_at',
        'estimated_letter_count', 'estimated_cost_cents', 'actual_cost_cents',
        'requires_review', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'queued_at' => 'datetime',
            'completed_at' => 'datetime',
            'requires_review' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DisputePlan::class, 'dispute_plan_id');
    }
}
