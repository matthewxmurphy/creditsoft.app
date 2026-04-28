<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BureauSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporting_cycle_id',
        'bureau',
        'source',
        'imported_by',
        'imported_at',
        'file_name',
        'snapshot_hash',
        'raw_summary',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'raw_summary' => 'array',
        ];
    }

    public function reportingCycle(): BelongsTo
    {
        return $this->belongsTo(ReportingCycle::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function tradelines(): HasMany
    {
        return $this->hasMany(Tradeline::class);
    }
}
