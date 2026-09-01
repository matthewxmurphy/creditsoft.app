<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisputePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'playbook_key', 'playbook_version', 'display_name', 'status',
        'execution_mode', 'mailing_method', 'letter_review', 'budget_cap_cents',
        'spent_cents', 'current_round', 'consent_name', 'consented_at',
        'consent_payload', 'started_at', 'paused_at', 'last_report_imported_at',
        'next_report_due_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'letter_review' => 'boolean',
            'consented_at' => 'datetime',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'last_report_imported_at' => 'datetime',
            'next_report_due_at' => 'datetime',
            'consent_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DisputePlanStep::class)->orderBy('sequence');
    }

    public function clocks(): HasMany
    {
        return $this->hasMany(DisputeBureauClock::class);
    }
}
