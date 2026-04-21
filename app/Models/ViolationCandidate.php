<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'reporting_cycle_id',
        'tradeline_id',
        'rule_key',
        'title',
        'severity',
        'priority_score',
        'status',
        'bureau',
        'evidence',
        'next_action',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'priority_score' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reportingCycle(): BelongsTo
    {
        return $this->belongsTo(ReportingCycle::class);
    }

    public function tradeline(): BelongsTo
    {
        return $this->belongsTo(Tradeline::class);
    }
}
