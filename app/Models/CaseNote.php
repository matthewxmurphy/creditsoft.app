<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'reporting_cycle_id',
        'user_id',
        'visibility',
        'note',
        'sync_eligible',
        'ai_summary',
    ];

    protected function casts(): array
    {
        return [
            'sync_eligible' => 'boolean',
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
