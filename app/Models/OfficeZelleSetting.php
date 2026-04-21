<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeZelleSetting extends Model
{
    protected $fillable = [
        'enabled',
        'bank_name',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_folder',
        'expected_subject',
        'trusted_domains',
        'delete_after_import',
        'last_checked_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'delete_after_import' => 'boolean',
            'imap_port' => 'integer',
            'imap_password' => 'encrypted',
            'last_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ZellePaymentMessage::class);
    }
}
