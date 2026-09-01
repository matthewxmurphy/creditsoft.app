<?php

namespace App\Models;

use App\Support\PhoneNumber;
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

    public function getPhoneAttribute(mixed $value): ?string
    {
        return PhoneNumber::normalize($value);
    }

    public function setPhoneAttribute(mixed $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function getEmergencyContactPhoneAttribute(mixed $value): ?string
    {
        return PhoneNumber::normalize($value);
    }

    public function setEmergencyContactPhoneAttribute(mixed $value): void
    {
        $this->attributes['emergency_contact_phone'] = PhoneNumber::normalize($value);
    }

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
