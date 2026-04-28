<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeReview extends Model
{
    protected $fillable = [
        'user_id',
        'reviewer_id',
        'review_type',
        'title',
        'body',
        'rating',
        'status',
        'occurred_on',
        'due_on',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_on' => 'date',
            'due_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
