<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseBrief extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'reporting_cycle_id',
        'user_id',
        'period',
        'title',
        'content',
        'generated_by_ai',
        'ai_metadata',
        'sync_eligible',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'generated_by_ai' => 'boolean',
            'ai_metadata' => 'array',
            'sync_eligible' => 'boolean',
            'approved_at' => 'datetime',
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
}
