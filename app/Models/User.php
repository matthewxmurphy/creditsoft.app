<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return array<string, array{label?:string, workspace?:bool, ops?:bool, readonly?:bool}>
     */
    protected function accessRoles(): array
    {
        /** @var array<string, array{label?:string, workspace?:bool, ops?:bool, readonly?:bool}> $roles */
        $roles = config('creditsoft.access.roles', []);

        return $roles;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'manager_id',
        'last_login_at',
        'last_seen_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getGravatarUrlAttribute(): string
    {
        $hash = md5(Str::lower(trim((string) $this->email)));

        return "https://www.gravatar.com/avatar/{$hash}?s=160&d=mp";
    }

    public function primaryRole(): ?string
    {
        return $this->assignedRoleNames()[0] ?? null;
    }

    public function primaryRoleLabel(): ?string
    {
        $role = $this->primaryRole();

        if (! $role) {
            return null;
        }

        return $this->accessRoles()[$role]['label'] ?? str($role)->replace('_', ' ')->title()->value();
    }

    /**
     * @return list<string>
     */
    public function assignedRoleNames(): array
    {
        $configuredRoles = array_keys($this->accessRoles());
        $assignedRoles = $this->roles->pluck('name')->values();

        $orderedConfiguredRoles = collect($configuredRoles)
            ->filter(fn (string $role) => $assignedRoles->contains($role));

        return $orderedConfiguredRoles
            ->merge(
                $assignedRoles->filter(fn (string $role) => ! in_array($role, $configuredRoles, true)),
            )
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function assignedRoleLabels(): array
    {
        return collect($this->assignedRoleNames())
            ->map(function (string $role): string {
                return $this->accessRoles()[$role]['label'] ?? str($role)->replace('_', ' ')->title()->value();
            })
            ->values()
            ->all();
    }

    public function hasWorkspaceAccess(): bool
    {
        $workspaceRoles = collect($this->accessRoles())
            ->filter(fn (array $meta) => (bool) ($meta['workspace'] ?? false))
            ->keys()
            ->all();

        return $this->hasAnyRole($workspaceRoles);
    }

    public function canAccessOpsPanel(): bool
    {
        $opsRoles = collect($this->accessRoles())
            ->filter(fn (array $meta) => (bool) ($meta['ops'] ?? false))
            ->keys()
            ->all();

        return $this->hasAnyRole($opsRoles);
    }

    public function canManageUsers(): bool
    {
        return $this->hasAnyRole([
            'owner_admin',
            'admin',
            'demo_admin',
        ]);
    }

    public function canViewUserDirectory(): bool
    {
        return $this->hasAnyRole([
            'owner_admin',
            'admin',
            'demo_admin',
            'manager',
        ]);
    }

    public function canEditUsers(): bool
    {
        return $this->hasAnyRole([
            'owner_admin',
            'admin',
        ]);
    }

    public function isReadOnlyDemo(): bool
    {
        $readonlyRoles = collect($this->accessRoles())
            ->filter(fn (array $meta) => (bool) ($meta['readonly'] ?? false))
            ->keys()
            ->all();

        return $this->hasAnyRole($readonlyRoles);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(UserApiKey::class)->latest();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id')->orderBy('name');
    }

    public function assignedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'assigned_to');
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function employeeReviews(): HasMany
    {
        return $this->hasMany(EmployeeReview::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->hasWorkspaceAccess()) {
            return false;
        }

        return match ($panel->getId()) {
            'ops' => $this->canAccessOpsPanel(),
            default => false,
        };
    }
}
