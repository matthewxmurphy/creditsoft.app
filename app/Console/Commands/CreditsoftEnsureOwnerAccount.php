<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreditsoftEnsureOwnerAccount extends Command
{
    protected $signature = 'creditsoft:owner:ensure
        {--email= : Override the configured owner email for this run}
        {--name= : Override the configured owner name for this run}
        {--password= : Override the configured owner password for this run}';

    protected $description = 'Ensure the installed office owner account exists without using a developer hard-coded email.';

    public function handle(): int
    {
        $email = $this->ownerEmail();
        $name = $this->ownerName($email);
        $password = $this->ownerPassword();

        if ($email === '') {
            $this->warn('No owner email is configured; set CREDITSOFT_OWNER_EMAIL or complete the installer profile.');

            return self::SUCCESS;
        }

        Role::findOrCreate('owner_admin');

        $user = User::query()
            ->where('email', $email)
            ->orWhere(function ($query): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'owner_admin'));
            })
            ->first();

        $created = false;

        if (! $user) {
            $user = new User();
            $created = true;
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ];

        if ($password !== '') {
            $attributes['password'] = $password;
        } elseif (! $user->exists) {
            $attributes['password'] = $this->writeGeneratedPassword();
        }

        $user->forceFill($attributes)->save();
        $user->syncRoles(['owner_admin']);

        $this->info(sprintf(
            '%s owner account %s.',
            $created ? 'Created' : 'Verified',
            $email,
        ));

        return self::SUCCESS;
    }

    protected function ownerEmail(): string
    {
        return Str::of((string) ($this->option('email') ?: config('creditsoft.access.owner.email', '')))
            ->lower()
            ->trim()
            ->value();
    }

    protected function ownerName(string $email): string
    {
        $name = trim((string) ($this->option('name') ?: config('creditsoft.access.owner.name', '')));

        if ($name !== '') {
            return $name;
        }

        $prefix = Str::before($email, '@');

        return Str::of($prefix !== '' ? $prefix : 'office owner')
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->value();
    }

    protected function ownerPassword(): string
    {
        return trim((string) ($this->option('password') ?: config('creditsoft.access.owner.password', '')));
    }

    protected function writeGeneratedPassword(): string
    {
        $path = storage_path('app/private/install/owner-password.txt');

        if (File::exists($path) && trim((string) File::get($path)) !== '') {
            return trim((string) File::get($path));
        }

        $password = Str::password(24);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $password.PHP_EOL);
        @chmod($path, 0600);

        $this->warn("Generated a first-run owner password at {$path}.");

        return $password;
    }
}
