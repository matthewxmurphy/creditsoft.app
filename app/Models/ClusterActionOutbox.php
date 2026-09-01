<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClusterActionOutbox extends Model
{
    protected $fillable = [
        'action_uuid',
        'source_node',
        'peer_label',
        'peer_base_url',
        'action',
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
