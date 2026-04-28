<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationOperatorCapture extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_system',
        'capture_type',
        'page_title',
        'page_url',
        'operator_note',
        'content_html',
        'extracted_text',
        'status',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
