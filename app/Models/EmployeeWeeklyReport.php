<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWeeklyReport extends Model
{
    protected $fillable = [
        'user_id',
        'generated_by',
        'period_start',
        'period_end',
        'title',
        'summary',
        'strengths',
        'risks',
        'coaching_notes',
        'next_week_focus',
        'ai_provider',
        'ai_model',
        'status',
        'generated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'strengths' => 'array',
            'risks' => 'array',
            'next_week_focus' => 'array',
            'generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
