<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZellePaymentMessage extends Model
{
    protected $fillable = [
        'office_zelle_setting_id',
        'client_id',
        'client_payment_id',
        'mailbox',
        'message_uid',
        'message_id',
        'received_at',
        'sent_on',
        'from_name',
        'from_email',
        'subject',
        'body_excerpt',
        'amount',
        'currency',
        'sender_name',
        'memo_email',
        'memo_text',
        'transaction_id',
        'status',
        'match_type',
        'header_status',
        'processed_at',
        'deleted_from_mailbox_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'sent_on' => 'date',
            'processed_at' => 'datetime',
            'deleted_from_mailbox_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(OfficeZelleSetting::class, 'office_zelle_setting_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ClientPayment::class, 'client_payment_id');
    }
}
