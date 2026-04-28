<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'cycle_label',
        'source',
        'started_at',
        'reviewed_at',
        'public_summary',
        'review_metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'reviewed_at' => 'datetime',
            'review_metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bureauSnapshots(): HasMany
    {
        return $this->hasMany(BureauSnapshot::class)->latest('imported_at');
    }

    public function violationCandidates(): HasMany
    {
        return $this->hasMany(ViolationCandidate::class)->latest();
    }

    public function browserCaptures(): HasMany
    {
        return $this->hasMany(BrowserCapture::class)->latest('imported_at');
    }
}
