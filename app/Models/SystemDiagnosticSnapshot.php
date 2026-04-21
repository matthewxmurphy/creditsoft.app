<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemDiagnosticSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'captured_at',
        'hostname',
        'cpu_cores',
        'load_one',
        'load_five',
        'load_fifteen',
        'memory_total_bytes',
        'memory_used_bytes',
        'memory_free_bytes',
        'swap_total_bytes',
        'swap_used_bytes',
        'swap_free_bytes',
        'disk_total_bytes',
        'disk_used_bytes',
        'disk_free_bytes',
        'network_rx_bytes',
        'network_tx_bytes',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'load_one' => 'float',
            'load_five' => 'float',
            'load_fifteen' => 'float',
            'memory_total_bytes' => 'integer',
            'memory_used_bytes' => 'integer',
            'memory_free_bytes' => 'integer',
            'swap_total_bytes' => 'integer',
            'swap_used_bytes' => 'integer',
            'swap_free_bytes' => 'integer',
            'disk_total_bytes' => 'integer',
            'disk_used_bytes' => 'integer',
            'disk_free_bytes' => 'integer',
            'network_rx_bytes' => 'integer',
            'network_tx_bytes' => 'integer',
        ];
    }
}
