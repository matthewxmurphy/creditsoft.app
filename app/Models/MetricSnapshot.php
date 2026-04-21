<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'bucket_date',
        'value',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'bucket_date' => 'date',
            'value' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
