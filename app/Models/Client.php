<?php

namespace App\Models;

use App\Casts\SafeEncryptedString;
use App\Support\ClientName;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;

    protected $appends = [
        'display_name',
        'client_health',
    ];

    protected $fillable = [
        'cuid',
        'first_name',
        'middle_name',
        'last_name',
        'name_suffix',
        'email',
        'secondary_email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'date_of_birth',
        'ssn',
        'current_score',
        'status',
        'assigned_to',
        'goals',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'ssn' => SafeEncryptedString::class,
            'metadata' => 'array',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reportingCycles(): HasMany
    {
        return $this->hasMany(ReportingCycle::class)->orderByDesc('started_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class)->latest();
    }

    public function portalEvents(): HasMany
    {
        return $this->hasMany(PortalClientEvent::class)->latest('occurred_at')->latest();
    }

    public function briefs(): HasMany
    {
        return $this->hasMany(CaseBrief::class)->latest();
    }

    public function letters(): HasMany
    {
        return $this->hasMany(LetterDraft::class)->latest();
    }

    public function browserCaptures(): HasMany
    {
        return $this->hasMany(BrowserCapture::class)->latest('imported_at');
    }

    public function providerAccounts(): HasMany
    {
        return $this->hasMany(ClientProviderAccount::class)->orderBy('provider_label');
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(ClientBillingProfile::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class)->latest('paid_at');
    }

    public function profileSnapshots(): HasMany
    {
        return $this->hasMany(ClientProfileSnapshot::class)->latest('recorded_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class)->latest('uploaded_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->latest('due_at');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(ViolationCandidate::class)->latest();
    }

    public function sopRuns(): HasMany
    {
        return $this->hasMany(SopRun::class)->latest();
    }

    public function disputePlans(): HasMany
    {
        return $this->hasMany(DisputePlan::class)->latest('started_at');
    }

    public function getDisplayNameAttribute(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->name_suffix,
        ])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(' ');
    }

    public function getPhoneAttribute(mixed $value): ?string
    {
        return PhoneNumber::normalize($value);
    }

    public function setFirstNameAttribute(mixed $value): void
    {
        $this->attributes['first_name'] = ClientName::normalizePart($value);
    }

    public function setMiddleNameAttribute(mixed $value): void
    {
        $this->attributes['middle_name'] = ClientName::normalizePart($value);
    }

    public function setLastNameAttribute(mixed $value): void
    {
        $this->attributes['last_name'] = ClientName::normalizePart($value);
    }

    public function setNameSuffixAttribute(mixed $value): void
    {
        $this->attributes['name_suffix'] = ClientName::normalizeSuffix($value);
    }

    public function setPhoneAttribute(mixed $value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function getClientHealthAttribute(): ?array
    {
        $signal = data_get($this->metadata ?? [], 'client_health');

        return is_array($signal) ? $signal : null;
    }
}
