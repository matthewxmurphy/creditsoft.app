<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClusterApiKeySyncOutbox extends Model
{
    protected $fillable = [
        'peer_label',
        'peer_base_url',
        'key_name',
        'token_suffix',
        'payload',
        'status',
        'attempts',
        'last_error',
        'next_attempt_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
