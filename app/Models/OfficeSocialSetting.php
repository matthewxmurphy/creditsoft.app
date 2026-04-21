<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeSocialSetting extends Model
{
    protected $fillable = [
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
