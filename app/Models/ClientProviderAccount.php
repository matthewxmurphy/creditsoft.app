<?php

namespace App\Models;

use App\Casts\SafeEncryptedString;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProviderAccount extends Model
{
    protected $fillable = [
        'client_id',
        'provider_key',
        'provider_label',
        'login_email',
        'login_username',
        'login_password',
        'security_answer',
        'status',
        'last_imported_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'login_password' => SafeEncryptedString::class,
            'security_answer' => SafeEncryptedString::class,
            'last_imported_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function companionMetadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata ?? [], 'companion.'.$key, $default);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function hasStoredPassword(): bool
    {
        try {
            return filled($this->login_password);
        } catch (DecryptException) {
            return false;
        }
    }

    public function hasStoredSecurityAnswer(): bool
    {
        try {
            return filled($this->security_answer);
        } catch (DecryptException) {
            return false;
        }
    }
}
