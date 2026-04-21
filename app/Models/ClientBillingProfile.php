<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientBillingProfile extends Model
{
    protected $fillable = [
        'client_id',
        'status',
        'amount',
        'currency',
        'billing_interval',
        'started_at',
        'last_paid_at',
        'next_due_at',
        'gateway_name',
        'gateway_customer_id',
        'gateway_subscription_id',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'started_at' => 'date',
            'last_paid_at' => 'datetime',
            'next_due_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class)->latest('paid_at');
    }

    public function isRecurringActive(): bool
    {
        return in_array($this->status, ['active', 'trial'], true);
    }

    public function monthlyRecurringAmount(): float
    {
        $amount = (float) $this->amount;

        return match ($this->billing_interval) {
            'weekly' => round($amount * (52 / 12), 2),
            'annual' => round($amount / 12, 2),
            'lifetime' => 0.0,
            default => round($amount, 2),
        };
    }
}
