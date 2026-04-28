<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagedLetterTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'version',
        'label',
        'letter_type',
        'legal_basis',
        'ai_focus',
        'operator_notes',
        'content_template',
        'source_system',
        'source_page_url',
        'metadata',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'legal_basis' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
