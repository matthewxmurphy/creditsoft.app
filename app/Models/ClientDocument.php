<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'reporting_cycle_id',
        'user_id',
        'title',
        'category',
        'notes',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'portal_visible',
        'metadata',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'portal_visible' => 'boolean',
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
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
