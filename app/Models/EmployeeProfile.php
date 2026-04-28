<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'legal_name',
        'preferred_name',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'timezone',
        'employment_type',
        'department',
        'title',
        'onboarding_status',
        'onboarding_started_at',
        'onboarding_completed_at',
        'pay_method',
        'pay_destination',
        'pay_currency',
        'payroll_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'onboarding_started_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
