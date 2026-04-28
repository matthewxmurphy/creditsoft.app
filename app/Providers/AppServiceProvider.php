<?php

namespace App\Providers;

use App\Creditsoft\Config\YamlConfigLoader;
use App\Models\ClientBillingProfile;
use App\Models\ClientPayment;
use App\Models\User;
use App\Observers\ClientBillingProfileObserver;
use App\Observers\ClientPaymentObserver;
use App\Services\CreditsoftClusterDatabaseSyncService;
use App\Services\EnvironmentEditor;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(YamlConfigLoader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->runningInConsole()) {
            app(EnvironmentEditor::class)->syncRuntimeFromFile();
        }
        $this->configureInstalledUpdateVersion();
        ClientBillingProfile::observe(ClientBillingProfileObserver::class);
        ClientPayment::observe(ClientPaymentObserver::class);
        $this->configureDefaults();
        $this->registerPresenceListeners();
        $this->registerClusterDatabaseSyncListeners();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureInstalledUpdateVersion(): void
    {
        $packagedVersion = $this->packagedDateReleaseVersion();
        $configuredVersion = trim((string) config('creditsoft.updates.current_version', ''));
        $configuredBuild = trim((string) config('creditsoft.updates.current_build', $configuredVersion));

        if ($packagedVersion !== '' && ($configuredVersion === '' || $this->shouldPreferPackagedRelease($configuredVersion, $packagedVersion))) {
            config([
                'creditsoft.updates.current_version' => $packagedVersion,
                'creditsoft.updates.current_build' => $packagedVersion,
            ]);
        }

        $path = (string) config('creditsoft.installer.state_path', storage_path('app/private/install/state.json'));

        if (! is_file($path)) {
            return;
        }

        $state = json_decode((string) file_get_contents($path), true);

        if (! is_array($state)) {
            return;
        }

        $updates = is_array($state['updates'] ?? null) ? $state['updates'] : [];
        $version = trim((string) ($updates['current_version'] ?? ''));
        $build = trim((string) ($updates['current_build'] ?? ''));

        if (
            $version !== ''
            && ! $this->shouldPreferPackagedRelease($version, $packagedVersion)
            && $this->shouldPreferStoredRelease($configuredVersion, $version)
        ) {
            config(['creditsoft.updates.current_version' => $version]);
        }

        if (
            $build !== ''
            && ! $this->shouldPreferPackagedRelease($build, $packagedVersion)
            && $this->shouldPreferStoredRelease($configuredBuild !== '' ? $configuredBuild : $configuredVersion, $build)
        ) {
            config(['creditsoft.updates.current_build' => $build]);
        }
    }

    protected function packagedDateReleaseVersion(): string
    {
        foreach ([
            base_path('update.creditsoft.app/data/update-feed.json') => 'latest_version',
            base_path('manifest.json') => 'version',
        ] as $path => $key) {
            if (! is_file($path)) {
                continue;
            }

            $payload = json_decode((string) file_get_contents($path), true);
            $version = is_array($payload) ? trim((string) ($payload[$key] ?? '')) : '';

            if ($this->isDateReleaseVersion($version)) {
                return $version;
            }
        }

        $marker = base_path('CREDITSOFT_RELEASE.toon');

        if (is_file($marker) && preg_match('/^canonical_version:\s*([^\s]+)$/m', (string) file_get_contents($marker), $matches) === 1) {
            $version = trim($matches[1]);

            if ($this->isDateReleaseVersion($version)) {
                return $version;
            }
        }

        return '';
    }

    protected function shouldPreferPackagedRelease(string $storedVersion, string $packagedVersion): bool
    {
        if ($packagedVersion === '') {
            return false;
        }

        if ($this->isLegacyReleaseVersion($storedVersion) || ! $this->isDateReleaseVersion($storedVersion)) {
            return true;
        }

        return $this->compareDateReleaseVersions($storedVersion, $packagedVersion) < 0;
    }

    protected function shouldPreferStoredRelease(string $configuredVersion, string $storedVersion): bool
    {
        if ($configuredVersion === '') {
            return true;
        }

        if ($this->isLegacyReleaseVersion($configuredVersion) || ! $this->isDateReleaseVersion($configuredVersion)) {
            return true;
        }

        if (! $this->isDateReleaseVersion($storedVersion)) {
            return false;
        }

        return $this->compareDateReleaseVersions($storedVersion, $configuredVersion) >= 0;
    }

    protected function compareDateReleaseVersions(string $left, string $right): int
    {
        $leftParts = array_map('intval', explode('.', $left));
        $rightParts = array_map('intval', explode('.', $right));
        $length = max(count($leftParts), count($rightParts));

        for ($index = 0; $index < $length; $index++) {
            $leftPart = $leftParts[$index] ?? 0;
            $rightPart = $rightParts[$index] ?? 0;

            if ($leftPart === $rightPart) {
                continue;
            }

            return $leftPart <=> $rightPart;
        }

        return 0;
    }

    protected function isDateReleaseVersion(string $version): bool
    {
        return preg_match('/^20\d{2}\.\d{1,2}\.\d{1,2}\.\d+$/', $version) === 1;
    }

    protected function isLegacyReleaseVersion(string $version): bool
    {
        return preg_match('/^0\.\d+(?:\.\d+)*$/', $version) === 1;
    }

    protected function registerPresenceListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill([
                'last_login_at' => now(),
                'last_seen_at' => now(),
            ])->saveQuietly();
        });
    }

    protected function registerClusterDatabaseSyncListeners(): void
    {
        foreach (['created', 'updated'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $payload): void {
                $model = $payload[0] ?? null;

                if (! $model instanceof \Illuminate\Database\Eloquent\Model) {
                    return;
                }

                app(CreditsoftClusterDatabaseSyncService::class)
                    ->queueModelMutation($model, 'upsert');
            });
        }

        Event::listen('eloquent.deleted: *', function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof \Illuminate\Database\Eloquent\Model) {
                return;
            }

            app(CreditsoftClusterDatabaseSyncService::class)
                ->queueModelMutation($model, 'delete');
        });
    }
}
