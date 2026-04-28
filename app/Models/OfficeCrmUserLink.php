<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeCrmUserLink extends Model
{
    protected $fillable = [
        'user_id',
        'crm_email',
        'crm_password',
        'crm_workspace_id',
        'crm_workspace_url',
        'last_launched_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'crm_password' => 'encrypted',
            'last_launched_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
