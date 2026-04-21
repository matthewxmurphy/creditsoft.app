<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'reporting_cycle_id',
        'user_id',
        'title',
        'letter_type',
        'template_key',
        'template_version',
        'status',
        'legal_basis',
        'content',
        'generated_by_ai',
        'ai_metadata',
        'approved_at',
        'approved_by',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'legal_basis' => 'array',
            'generated_by_ai' => 'boolean',
            'ai_metadata' => 'array',
            'approved_at' => 'datetime',
            'exported_at' => 'datetime',
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
