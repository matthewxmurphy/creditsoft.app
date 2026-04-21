<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAppPaymentRequest extends Model
{
    protected $fillable = [
        'office_cash_app_setting_id',
        'client_id',
        'client_payment_id',
        'idempotency_key',
        'cash_app_request_id',
        'cash_app_payment_id',
        'grant_id',
        'reference_id',
        'status',
        'amount',
        'currency',
        'action_type',
        'channel',
        'scope_id',
        'merchant_id',
        'redirect_url',
        'qr_code_image_url',
        'qr_code_svg_url',
        'mobile_url',
        'desktop_url',
        'refreshes_at',
        'expires_at',
        'approved_at',
        'paid_at',
        'raw_response',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refreshes_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(OfficeCashAppSetting::class, 'office_cash_app_setting_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientPayment(): BelongsTo
    {
        return $this->belongsTo(ClientPayment::class);
    }
}
