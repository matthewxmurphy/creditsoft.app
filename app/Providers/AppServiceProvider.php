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

        if ($version !== '') {
            config(['creditsoft.updates.current_version' => $version]);
        }

        if ($build !== '') {
            config(['creditsoft.updates.current_build' => $build]);
        }
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
