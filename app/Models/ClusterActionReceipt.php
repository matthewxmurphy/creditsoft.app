<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClusterActionReceipt extends Model
{
    protected $fillable = [
        'action_uuid',
        'source_node',
        'action',
        'result',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'encrypted:array',
            'received_at' => 'datetime',
        ];
    }
}
