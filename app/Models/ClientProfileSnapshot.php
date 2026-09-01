<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProfileSnapshot extends Model
{
    protected $fillable = [
        'client_id',
        'client_cuid',
        'source',
        'source_label',
        'event',
        'is_current',
        'recorded_at',
        'effective_at',
        'first_name',
        'middle_name',
        'last_name',
        'name_suffix',
        'email',
        'secondary_email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'date_of_birth',
        'ssn_last_four',
        'current_score',
        'mailing_label',
        'mailing_barcode',
        'mailing_barcode_symbology',
        'mailing_barcode_payload',
        'address_fingerprint',
        'changed_fields',
        'payload',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'recorded_at' => 'datetime',
            'effective_at' => 'datetime',
            'date_of_birth' => 'date',
            'changed_fields' => 'array',
            'payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
