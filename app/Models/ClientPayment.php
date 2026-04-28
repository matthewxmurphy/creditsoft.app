<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPayment extends Model
{
    protected $fillable = [
        'client_id',
        'client_billing_profile_id',
        'amount',
        'currency',
        'status',
        'paid_at',
        'gateway_name',
        'gateway_transaction_id',
        'reference',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function billingProfile(): BelongsTo
    {
        return $this->belongsTo(ClientBillingProfile::class, 'client_billing_profile_id');
    }
}
