<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeBillingSetting extends Model
{
    protected $fillable = [
        'gateway_provider',
        'gateway_status',
        'gateway_account_label',
        'gateway_environment',
        'webhook_status',
        'gateway_connected_at',
        'payment_portal_url',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gateway_connected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
