<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeActivitySample extends Model
{
    protected $fillable = [
        'user_id',
        'sampled_at',
        'route_path',
        'page_title',
        'session_uuid',
        'active_ms',
        'keypress_count',
        'click_count',
        'mouse_move_count',
        'scroll_count',
        'focus_count',
        'form_submit_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'active_ms' => 'integer',
            'keypress_count' => 'integer',
            'click_count' => 'integer',
            'mouse_move_count' => 'integer',
            'scroll_count' => 'integer',
            'focus_count' => 'integer',
            'form_submit_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
