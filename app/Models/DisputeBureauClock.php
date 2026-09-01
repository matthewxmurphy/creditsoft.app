<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeBureauClock extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispute_plan_id', 'dispute_plan_step_id', 'bureau', 'clock_type',
        'status', 'sent_at', 'due_at', 'responded_at', 'flagged_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
            'flagged_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DisputePlan::class, 'dispute_plan_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(DisputePlanStep::class, 'dispute_plan_step_id');
    }
}
