<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrowserCapture extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'reporting_cycle_id',
        'user_id',
        'source_type',
        'browser_name',
        'page_title',
        'page_url',
        'file_name',
        'file_path',
        'mime_type',
        'archive_format',
        'content_html',
        'extracted_text',
        'metadata',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'imported_at' => 'datetime',
            'deleted_at' => 'datetime',
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
