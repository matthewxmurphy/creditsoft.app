<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClusterDatabaseTombstone extends Model
{
    protected $fillable = [
        'event_uuid',
        'source_node',
        'model_type',
        'table_name',
        'record_key',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
