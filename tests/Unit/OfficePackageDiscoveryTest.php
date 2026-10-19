<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Console\Application as Artisan;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Support\Facades\Facade;
use Laravel\Pail\PailServiceProvider;
use NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class OfficePackageDiscoveryTest extends TestCase
{
    private string $fixture;

    private Filesystem $files;

    private array $lock;

    private Container $previousContainer;

    private $previousFacade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->files = new Filesystem;
        $this->fixture = sys_get_temp_dir().'/creditsoft-discovery-'.bin2hex(random_bytes(8));
        $this->lock = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
        $this->files->makeDirectory($this->fixture.'/shared-cache', 0755, true);
    }

    protected function tearDown(): void
    {
        Artisan::forgetBootstrappers();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        $this->files->deleteDirectory($this->fixture);

        parent::tearDown();
    }

    public function test_web_discovery_excludes_development_providers_but_keeps_runtime_providers(): void
    {
        $manifest = $this->nodeManifest('web', [
            ...$this->lock['packages'],
            ...$this->lock['packages-dev'],
        ]);
        $manifest->build();
        $providers = $manifest->providers();
        $developmentProviders = $this->developmentProviders();

        $this->assertNotEmpty($developmentProviders, 'The locked development dependencies must exercise Laravel provider discovery.');

        foreach ($developmentProviders as $provider) {
            $this->assertNotContains($provider, $providers, 'A web overlay must not publish a development-only provider into the shared office cache.');
        }

        $this->assertContains('Laravel\Fortify\FortifyServiceProvider', $providers);
        $this->assertSame($this->runtimeProviders(), $providers, 'Every legitimate production provider must remain discoverable.');
        $this->assertArrayNotHasKey('kirschbaum-development/eloquent-power-joins', $this->files->getRequire($manifest->manifestPath));
    }

    public function test_production_node_can_reuse_web_generated_shared_manifest_without_development_providers(): void
    {
        $web = $this->nodeManifest('web', [
            ...$this->lock['packages'],
            ...$this->lock['packages-dev'],
        ]);
        $production = $this->nodeManifest('production', $this->lock['packages']);
        $web->build();

        // A separate PackageManifest reads the same volume using production-only
        // installed metadata. Laravel trusts the existing cache; it does not rebuild it.
        $this->assertFileExists($web->manifestPath);
        $this->assertSame($web->manifestPath, $production->manifestPath);
        $this->assertSame($web->providers(), $production->providers());

        foreach ($this->developmentProviders() as $provider) {
            $this->assertNotContains($provider, $production->providers(), 'The queue/scheduler must not inherit a provider absent from its production vendor tree.');
        }

        // Compare with a real production rebuild: the shared provider set should
        // contain exactly the providers available to both vendor installations.
        $productionOnly = $this->nodeManifest('production-only', $this->lock['packages']);
        $productionOnly->manifestPath = $this->fixture.'/shared-cache/production-only.php';
        $productionOnly->build();
        $this->assertSame($productionOnly->providers(), $production->providers());
    }

    public function test_new_discovery_exclusions_are_locked_development_provider_packages(): void
    {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $excluded = array_diff($composer['extra']['laravel']['dont-discover'], ['kirschbaum-development/eloquent-power-joins']);
        $developmentPackages = array_column($this->lock['packages-dev'], null, 'name');
        $runtimePackages = array_column($this->lock['packages'], null, 'name');

        $this->assertNotEmpty($excluded);

        foreach ($excluded as $name) {
            $this->assertArrayHasKey($name, $developmentPackages);
            $this->assertNotEmpty($developmentPackages[$name]['extra']['laravel']['providers'] ?? []);
            $this->assertArrayNotHasKey($name, $runtimePackages);
        }
    }

    #[DataProvider('developmentEnvironments')]
    public function test_development_registration_leaves_shared_manifests_unchanged(string $environment, bool $enabled): void
    {
        $manifest = $this->nodeManifest('web', [...$this->lock['packages'], ...$this->lock['packages-dev']]);
        $manifest->build();
        $app = $this->isolatedApplication($environment);
        $servicesPath = $this->fixture.'/shared-cache/services.php';
        (new ProviderRepository($app, $this->files, $servicesPath))->load([EventServiceProvider::class]);
        $packagesBefore = $this->files->get($manifest->manifestPath);
        $servicesBefore = $this->files->get($servicesPath);
        $configuredProviders = $app['config']->get('app.providers');

        $this->assertTrue(class_exists('Laravel\\Boost\\BoostServiceProvider'), 'The production guard must run with development providers actually installed.');
        $app->register(AppServiceProvider::class);

        foreach ($this->developmentProviders() as $provider) {
            $this->assertSame($enabled, $app->getProvider($provider) !== null, $environment.' provider registration: '.$provider);
        }

        $this->assertSame($configuredProviders, $app['config']->get('app.providers'));
        $this->assertSame($packagesBefore, $this->files->get($manifest->manifestPath));
        $this->assertSame($servicesBefore, $this->files->get($servicesPath));

        foreach ($this->developmentProviders() as $provider) {
            $this->assertNotContains($provider, $this->files->getRequire($servicesPath)['providers']);
        }
    }

    public static function developmentEnvironments(): array
    {
        return [
            'local development' => ['local', true],
            'test runner' => ['testing', true],
            'production with development vendor overlay' => ['production', false],
            'other environment with development vendor overlay' => ['staging', false],
        ];
    }

    public function test_local_console_keeps_pail_and_test_entrypoints_without_booting_application_services(): void
    {
        Artisan::forgetBootstrappers();
        $app = $this->isolatedApplication('local');
        $app->register(AppServiceProvider::class);

        // Boot only the two native command providers. AppServiceProvider::boot
        // would synchronize runtime configuration and is intentionally not run.
        $app->getProvider(PailServiceProvider::class)->boot();
        $app->getProvider(CollisionServiceProvider::class)->boot();
        $console = new Artisan($app, $app['events'], Application::VERSION);
        $console->setContainerCommandLoader();

        $this->assertTrue($console->has('pail'), 'composer dev must retain its artisan pail command.');
        $this->assertTrue($console->has('test'), 'composer test must retain its artisan test command.');
        $this->assertFalse($app->isBooted());
    }

    private function isolatedApplication(string $environment): Application
    {
        Facade::clearResolvedInstances();
        $app = new Application($this->fixture);
        $app->instance('env', $environment);
        $app->instance('config', new Repository(['app' => ['debug' => false, 'providers' => [EventServiceProvider::class]]]));
        $app->singleton(ExceptionHandler::class, Handler::class);
        Facade::setFacadeApplication($app);

        return $app;
    }

    private function runtimeProviders(): array
    {
        $providers = [];

        foreach ($this->lock['packages'] as $package) {
            // Preserve the application's pre-existing, unrelated discovery opt-out.
            if ($package['name'] !== 'kirschbaum-development/eloquent-power-joins') {
                array_push($providers, ...($package['extra']['laravel']['providers'] ?? []));
            }
        }

        return $providers;
    }

    private function nodeManifest(string $node, array $packages): PackageManifest
    {
        $base = $this->fixture.'/'.$node;
        $this->files->makeDirectory($base.'/vendor/composer', 0755, true);
        $this->files->copy(dirname(__DIR__, 2).'/composer.json', $base.'/composer.json');
        $this->files->put($base.'/vendor/composer/installed.json', json_encode(['packages' => $packages], JSON_THROW_ON_ERROR));

        return new PackageManifest($this->files, $base, $this->fixture.'/shared-cache/packages.php');
    }

    private function developmentProviders(): array
    {
        $providers = [];

        foreach ($this->lock['packages-dev'] as $package) {
            array_push($providers, ...($package['extra']['laravel']['providers'] ?? []));
        }

        return $providers;
    }
}
