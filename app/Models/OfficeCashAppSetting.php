<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeCashAppSetting extends Model
{
    protected $fillable = [
        'enabled',
        'environment',
        'api_base_url',
        'client_id',
        'api_key_id',
        'api_secret',
        'region',
        'scope_id',
        'merchant_id',
        'redirect_url',
        'user_agent',
        'auto_capture',
        'last_checked_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'auto_capture' => 'boolean',
            'api_key_id' => 'encrypted',
            'api_secret' => 'encrypted',
            'last_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CashAppPaymentRequest::class);
    }
}
