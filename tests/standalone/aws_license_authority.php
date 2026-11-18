<?php

// Run with: php tests/standalone/aws_license_authority.php
// The HTTP facade below is an owned fixture; no socket or real key is used.
namespace Illuminate\Support\Facades {
    final class Http
    {
        public static array $requests = [];
        public static array $responses = [];
        public static array $downloads = [];
        private array $options = [];
        public static function timeout(int $seconds): self { return new self; }
        public function acceptJson(): self { return $this; }
        public function withOptions(array $options): self { $this->options = $options; return $this; }
        public function get(string $url): object
        {
            self::$requests[] = ['method'=>'GET','url'=>$url,'options'=>$this->options];
            $response = array_shift(self::$downloads);
            if ($response instanceof \Throwable) { throw $response; }
            if (! is_array($response) || ! isset($this->options['sink'])) { throw new \RuntimeException('Invalid download fixture.'); }
            file_put_contents($this->options['sink'], $response['body']);
            return new class($response) {
                public function __construct(private array $response) {}
                public function successful(): bool { return $this->response['status'] === 200; }
            };
        }
        public function post(string $url, array $payload): object
        {
            self::$requests[] = ['method' => 'POST', 'url' => $url, 'payload' => $payload];
            $response = array_shift(self::$responses);
            if ($response instanceof \Throwable) { throw $response; }
            return new class($response) {
                public function __construct(private array $payload) {}
                public function successful(): bool { return true; }
                public function json(): array { return $this->payload; }
            };
        }
    }
    final class Cache
    {
        public static array $items = [];
        public static function remember(string $key, mixed $until, callable $callback): array
        { return self::$items[$key] ??= $callback(); }
        public static function forget(string $key): void { unset(self::$items[$key]); }
    }
    final class File
    {
        public static function ensureDirectoryExists(string $path): void { if (! is_dir($path)) { mkdir($path, 0700, true); } }
        public static function exists(string $path): bool { if (str_contains($path, 'update.creditsoft.app')) { throw new \RuntimeException('Legacy local feed reached.'); } return file_exists($path); }
        public static function get(string $path): string { return (string) file_get_contents($path); }
    }
}

namespace Illuminate\Support {
    final class Str
    {
        public static function upper(string $value): string { return strtoupper($value); }
        public static function lower(string $value): string { return strtolower($value); }
        public static function mask(string $value, string $mask, int $start): string { return substr($value, 0, $start).'****'; }
    }
}

namespace {
    use App\Services\AwsLicenseAuthority as Authority;
    use App\Services\CreditsoftUpdateFeed;
    use App\Services\LicenseCheckService;
    use App\Services\CreditsoftSelfUpdateService;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Http;

    $configuration = ['creditsoft.installer.license_mode' => 'auto',
        'creditsoft.installer.license_check_api_url' => 'https://api.creditsoft.app/license/validate',
        'creditsoft.updates.feed_url' => 'https://update.creditsoft.app/api/update-feed',
        'creditsoft.updates.current_version' => '2026.1.1.1'];
    $fixtureState = ['license_key' => 'OFFICE-TEST-KEY1'];
    $fixtureServices = [];
    function config(string $name, mixed $default = null): mixed { return $GLOBALS['configuration'][$name] ?? $default; }
    function app(string $name): object {
        if (isset($GLOBALS['fixtureServices'][$name])) { return $GLOBALS['fixtureServices'][$name]; }
        return new class { public function read(): array { return $GLOBALS['fixtureState']; } };
    }
    function base_path(string $path = ''): string { return dirname(__DIR__, 2).($path !== '' ? '/'.$path : ''); }
    function storage_path(string $path = ''): string { return $GLOBALS['fixtureStorage'].($path !== '' ? '/'.$path : ''); }
    function now(): object {
        return new class {
            public function addMinutes(int $minutes): self { return $this; }
            public function toIso8601String(): string { return '2026-11-18T00:00:00Z'; }
        };
    }
    function data_get(array $data, string $path, mixed $default = null): mixed {
        foreach (explode('.', $path) as $part) {
            if (! is_array($data) || ! array_key_exists($part, $data)) { return $default; }
            $data = $data[$part];
        }
        return $data;
    }
    $checks = 0;
    function check(bool $condition, string $message): void {
        $GLOBALS['checks']++;
        if (! $condition) { throw new RuntimeException($message); }
    }
    $root = dirname(__DIR__, 2);
    require $root.'/app/Services/AwsLicenseAuthority.php';
    require $root.'/app/Services/LicenseCheckService.php';
    require $root.'/app/Services/CreditsoftUpdateFeed.php';
    require $root.'/app/Services/CreditsoftSelfUpdateService.php';
    require $root.'/app/Services/IntranetNodeInstallerBundle.php';
    require $root.'/app/Services/BrowserCompanionBundle.php';
    class FeedProbe extends CreditsoftUpdateFeed {
        public function fetch(): array { return $this->fetchFeed(); }
    }

    check(Authority::endpoint('validate', 'https://api.creditsoft.app/license/validate') === Authority::BASE_URL.'/validate', 'Legacy validation authority survived.');
    check(Authority::endpoint('validate', 'https://other-tenant.execute-api.us-east-1.amazonaws.com/license/validate') === Authority::BASE_URL.'/validate', 'Another AWS tenant could receive a license key.');
    check(Authority::normalizeKey(str_repeat('ABCD', 8)) === implode('-', array_fill(0, 8, 'ABCD')), 'Ungrouped desktop key changed identity.');
    check(Authority::normalizeKey('ABCD:'.str_repeat('A', 59)) === 'ABCD:'.str_repeat('A', 59), 'Portal token changed identity.');
    check(! Authority::amazonHttpsUrl('https://user@s3.us-east-1.amazonaws.com/package.zip'), 'URL credentials accepted.');
    check(! Authority::amazonHttpsUrl('https://bucket.s3.amazonaws.com.evil.test/package.zip'), 'Non-Amazon suffix accepted.');
    $release = ['authorized' => true, 'plan_key' => 'creditsoft', 'product' => 'creditsoft',
        'latest_version' => '2026.11.18.1', 'latest' => [
            'version' => '2026.11.18.1', 'file' => 'creditsoft-office-v2026.11.18.1.zip',
            'package_url' => 'https://release-bucket.s3.us-east-1.amazonaws.com/creditsoft-office-v2026.11.18.1.zip?signature=fixture',
            'md5' => md5('exact archive bytes'), 'sha256' => hash('sha256', 'exact archive bytes'),
            'size' => strlen('exact archive bytes'), 'notes' => 'A release.']];
    $normalized = Authority::updateFeed($release);
    check($normalized['download_url'] === $release['latest']['package_url'], 'Signed URL mapping failed.');
    check($normalized['package_path'] === null, 'A local package bypass appeared.');
    foreach (['enterprise', 'enterprise_pro', 'office', 'creditsoft'] as $plan) {
        check(Authority::updateFeed(array_replace($release, ['plan_key' => $plan])) !== null, 'Existing office plan rejected.');
    }
    check(Authority::updateFeed(array_replace($release, ['plan_key' => 'forgedplot'])) === null, 'Other product authorized an office download.');
    check(Authority::updateFeed(array_replace($release, ['authorized' => false])) === null, 'Authority denial was ignored.');
    $wrongType = $release;
    $wrongType['latest']['file'] = 'creditsoft-browser-companion.zip';
    check(Authority::updateFeed($wrongType) === null, 'A browser ZIP became the primary Office package.');
    $missingHash = $release;
    unset($missingHash['latest']['sha256']);
    check(Authority::updateFeed($missingHash) === null, 'An unhashed primary was accepted.');
    $legacy = $release;
    $legacy['latest']['package_url'] = 'https://update.creditsoft.app/downloads/office.zip';
    check(Authority::updateFeed($legacy) === null, 'Net30 download fallback survived.');
    $path = tempnam(sys_get_temp_dir(), 'creditsoft-release-fixture-');
    try {
        file_put_contents($path, 'exact archive bytes');
        check(Authority::packageMatches($path, $normalized), 'Exact package bytes rejected.');
        file_put_contents($path, 'altered archive bytes');
        check(! Authority::packageMatches($path, $normalized), 'Stale or poisoned cached bytes accepted.');
    } finally { unlink($path); }

    Http::$responses = [['valid' => true, 'status' => 'active', 'plan_key' => 'creditsoft', 'plan' => 'enterprise']];
    $verdict = (new LicenseCheckService)->check('OFFICE-TEST-KEY1');
    check($verdict['valid'] && $verdict['can_access_workspace'], 'AWS office validation changed workspace access.');
    check($verdict['plan_key'] === 'enterprise', 'AWS product family replaced the existing office edition.');
    check(Http::$requests[0]['url'] === Authority::BASE_URL.'/validate', 'Validation reached the old authority.');
    check(! str_contains(Http::$requests[0]['url'], 'OFFICE-TEST-KEY1'), 'License key entered a URL.');
    Http::$responses = [new RuntimeException('offline')];
    $offline = (new LicenseCheckService)->check('OFFICE-TEST-KEY1');
    check($offline['remote_unreachable'] && $offline['can_access_workspace'], 'Outage disabled the installed workspace.');

    Http::$responses = [$release];
    $feed = (new FeedProbe)->fetch();
    $request = Http::$requests[array_key_last(Http::$requests)];
    check($request['url'] === Authority::BASE_URL.'/updates', 'Updates reached the old authority.');
    check($request['payload'] === ['license_key' => 'OFFICE-TEST-KEY1', 'current_version' => '2026.1.1.1'], 'Update request changed existing plan semantics.');
    check(($feed['authorized'] ?? false) === true && $feed['source'] === 'aws', 'AWS authorization did not reach client feed.');
    $fixtureState['license_key'] = 'DIFFERENT-TEST-KEY1';
    Http::$responses = [['authorized' => false, 'plan_key' => 'creditsoft']];
    $denied = (new FeedProbe)->fetch();
    check($denied['source'] === 'unavailable' && ! isset($denied['download_url']), 'Another key reused cached package rights.');
    $fixtureState['license_key'] = '';
    $count = count(Http::$requests);
    check((new FeedProbe)->fetch()['source'] === 'none' && count(Http::$requests) === $count, 'Missing license sent a request.');

    class FrozenFeed extends CreditsoftUpdateFeed {
        public int $refreshes = 0;
        public function __construct(public array $value) {}
        public function refresh(): array { $this->refreshes++; return $this->value; }
        public function current(): array { return $this->value; }
    }
    class UpdaterProbe extends CreditsoftSelfUpdateService {
        public function __construct(CreditsoftUpdateFeed $feed) { $this->updateFeed = $feed; }
    }
    class NodeProbe extends \App\Services\IntranetNodeInstallerBundle {
        public function __construct(CreditsoftUpdateFeed $feed) { $this->updateFeed = $feed; }
        public function package(bool $stage = false): array { return $this->packageState(refresh: true, stage: $stage); }
    }
    function zipFixture(string $path, array $files): void {
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { throw new \RuntimeException('Fixture archive failed.'); }
        foreach ($files as $name=>$bytes) { $zip->addFromString($name, $bytes); }
        $zip->close();
    }
    $fixtureStorage = realpath(sys_get_temp_dir()).'/creditsoft-aws-client-'.bin2hex(random_bytes(8));
    mkdir($fixtureStorage, 0700);
    try {
        $office = $fixtureStorage.'/office.zip';
        $browser = $fixtureStorage.'/browser.zip';
        zipFixture($office, ['creditsoft/artisan'=>'<?php', 'creditsoft/composer.json'=>'{}', 'creditsoft/routes/web.php'=>'<?php', 'creditsoft/app/Services/LicenseCheckService.php'=>'<?php']);
        zipFixture($browser, ['manifest.json'=>json_encode(['manifest_version'=>3,'name'=>'CreditSoft Companion Capture','version'=>'2026.5.6.6']), 'background.js'=>'// fixture', 'options.html'=>'fixture']);
        check(Authority::archiveMatchesType($office, 'office-application'), 'Complete Office structure rejected.');
        check(! Authority::archiveMatchesType($browser, 'office-application'), 'Browser source passed Office structure check.');
        check(Authority::archiveMatchesType($browser, 'browser-companion'), 'Browser companion structure rejected.');
        check(! Authority::archiveMatchesType($office, 'browser-companion'), 'Office source passed browser structure check.');
        $unsafe = $fixtureStorage.'/unsafe.zip';
        zipFixture($unsafe, ['artisan'=>'<?php','composer.json'=>'{}','routes/web.php'=>'<?php','app/Services/LicenseCheckService.php'=>'<?php','../outside'=>'bad']);
        check(! Authority::archiveMatchesType($unsafe, 'office-application'), 'Unsafe archive path passed.');
        $body = file_get_contents($office);
        $primary = array_replace($normalized, ['file'=>'creditsoft-office-v2026.11.18.1.zip','md5'=>md5($body),'sha256'=>hash('sha256',$body),'size'=>strlen($body)]);
        $feed = new FrozenFeed($primary);
        $updater = new UpdaterProbe($feed);
        Http::$downloads = [['status'=>200,'body'=>$body]];
        $staged = $updater->stageLatestPackage();
        check($feed->refreshes === 1 && Authority::packageMatches($staged['path'], $primary), 'Fresh primary staging did not verify exact bytes.');
        $fixtureServices[CreditsoftSelfUpdateService::class] = $updater;
        $node = new NodeProbe($feed);
        $package = $node->package(true);
        check($package['bundled'] === true && $package['source'] === 'amazon-authorized-office-package', 'Node builder did not use the authorized primary.');
        check($package['download_url'] === null, 'An expiring presigned grant entered the generated node installer.');
        check(! str_contains($package['feed_url'], 'update.creditsoft.app'), 'Node builder kept the old update feed.');
        $feed->value = ['authorized'=>false];
        $failed = false;try { $updater->stageLatestPackage(); } catch (\RuntimeException) { $failed = true; }
        check($failed, 'Denied AWS rights staged an Office package.');
        check($node->package()['source'] === 'amazon-release-unavailable', 'Node preview used a local/public package fallback.');

        $browserBody = file_get_contents($browser);
        $artifact = ['version'=>'2026.11.18.1','file'=>'creditsoft-browser-companion-v2026.5.6.6.zip','required_feature'=>'browser_companion',
            'package_url'=>'https://release-bucket.s3.us-east-1.amazonaws.com/browser.zip?signature=fixture','md5'=>md5($browserBody),'sha256'=>hash('sha256',$browserBody),'size'=>strlen($browserBody)];
        $withBrowser = array_replace($primary, ['artifacts'=>[$artifact]]);
        check(Authority::browserArtifact($withBrowser)['artifact_type'] === 'browser-companion', 'Typed browser grant was dropped.');
        foreach (['required_feature'=>'workspace','file'=>'creditsoft-sdk.zip','sha256'=>'wrong','version'=>'2000.1.1.1'] as $field=>$value) {
            $bad = $withBrowser;$bad['artifacts'][0][$field] = $value;
            check(Authority::browserArtifact($bad) === null, 'Wrong-feature/type/hash/version auxiliary grant accepted.');
        }
        $feed->value = $withBrowser;
        Http::$downloads = [['status'=>200,'body'=>$browserBody]];
        $path = (new \App\Services\BrowserCompanionBundle)->build();
        check(Authority::packageMatches($path, $artifact), 'Browser bundle did not use exact AWS bytes.');
        check(basename($path) === $artifact['file'], 'Browser bytes were labeled as an Office primary.');
        $feed->value = array_replace($primary, ['artifacts'=>[]]);
        $failed = false;try { (new \App\Services\BrowserCompanionBundle)->build(); } catch (\RuntimeException) { $failed = true; }
        check($failed, 'Browser builder used public/local fallback without a feature grant.');
        $feed->value = $primary;
        file_put_contents($staged['path'], 'poisoned cache');
        Http::$downloads = [new \RuntimeException('PRIVATE_SIGNATURE_MUST_NOT_ESCAPE')];
        $failed = false;try { $updater->stageLatestPackage(); } catch (\RuntimeException $error) {
            $failed = true;check(! str_contains($error->getMessage(), 'PRIVATE_SIGNATURE') && $error->getPrevious() === null, 'A signed URL could escape through an exception chain.');
        }
        check($failed, 'A poisoned cache was accepted without reauthorization and verified download.');
    } finally {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fixtureStorage, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
        rmdir($fixtureStorage);
    }
    echo "PASS {$checks} AWS authority checks; no network or real license used.\n";
}
