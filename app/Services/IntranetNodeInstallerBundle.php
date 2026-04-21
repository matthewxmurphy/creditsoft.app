<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class IntranetNodeInstallerBundle
{
    public function __construct(
        protected CreditsoftApiAccess $apiAccess,
        protected CreditsoftUpdateFeed $updateFeed,
    ) {}

    public function downloadName(): string
    {
        $version = $this->version();

        return sprintf('creditsoft-intranet-node-installer-v%s.zip', $version);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function summary(array $state): array
    {
        $package = $this->packageState(refresh: true);

        return [
            'name' => 'CreditSoft Intranet Node Installer',
            'version' => $this->version(),
            'download_name' => $this->downloadName(),
            'download_url' => route('install.intranet-node.download'),
            'description' => 'Cross-platform installer for a Dockerized CreditSoft office node with queue, scheduler, PostgreSQL-ready storage, local router, and optional white-label CRM sidecar.',
            'platforms' => ['Windows PowerShell', 'Linux bash', 'macOS bash'],
            'includes_package' => (bool) ($package['bundled'] ?? false),
            'package_name' => $package['name'] ?? null,
            'package_source' => $package['source'] ?? 'update-feed',
            'latest_version' => $package['version'] ?? $this->version(),
            'feed_url' => $package['feed_url'] ?? config('creditsoft.updates.feed_url'),
            'api_token_included' => false,
            'masked_api_token' => null,
            'office_name' => data_get($state, 'company_name') ?: config('app.name', 'CreditSoft'),
            'sensitive' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function build(array $state): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build the intranet node installer.');
        }

        $directory = storage_path('app/private/intranet-node-installer');
        $archivePath = $directory.DIRECTORY_SEPARATOR.$this->downloadName();
        $package = $this->packageState(refresh: true);
        $manifest = $this->manifest($state, $package);

        File::ensureDirectoryExists($directory);

        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('CreditSoft could not create the intranet node installer archive.');
        }

        $zip->addFromString('README.md', $this->readme($manifest));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('creditsoft-node.env', $this->environmentFile($state));
        $zip->addFromString('install.sh', $this->shellInstaller());
        $zip->addFromString('install.ps1', $this->powershellInstaller());

        $brandingSource = $this->brandingLogoPath($state);
        $brandingArchivePath = (string) data_get($manifest, 'branding.logo_file', '');

        if ($brandingSource !== '' && $brandingArchivePath !== '' && is_file($brandingSource)) {
            $zip->addFile($brandingSource, $brandingArchivePath);
        }

        if (($package['bundled'] ?? false) && is_string($package['path'] ?? null) && is_file($package['path'])) {
            $zip->addFile($package['path'], 'packages/'.basename($package['path']));
        }

        $zip->close();

        return $archivePath;
    }

    protected function version(): string
    {
        $feedPath = base_path('update.creditsoft.app/data/update-feed.json');

        if (File::exists($feedPath)) {
            $decoded = json_decode((string) File::get($feedPath), true);
            $latestVersion = is_array($decoded) ? trim((string) ($decoded['latest_version'] ?? '')) : '';

            if ($latestVersion !== '') {
                return $latestVersion;
            }
        }

        return trim((string) config('creditsoft.updates.current_version', '0.5.0')) ?: '0.5.0';
    }

    /**
     * @return array<string, mixed>
     */
    protected function packageState(bool $refresh = false): array
    {
        $feed = $this->localPackageFeed();

        if ($feed === null) {
            $feed = $refresh ? $this->updateFeed->refresh() : $this->updateFeed->current();
        }

        $latestVersion = trim((string) ($feed['latest_version'] ?? $this->version()));
        $packagePath = trim((string) ($feed['package_path'] ?? ''));
        $downloadUrl = trim((string) ($feed['download_url'] ?? ''));

        if ($latestVersion !== '' && ($downloadUrl === '' || str_starts_with($downloadUrl, '/'))) {
            $downloadUrl = sprintf('https://updates.creditsoft.app/downloads/creditsoft-office-v%s.zip', $latestVersion);
        }

        $bundled = $packagePath !== '' && is_file($packagePath);
        $feedUrl = trim((string) ($feed['feed_url'] ?? config('creditsoft.updates.feed_url')));

        if ($feedUrl === '' || str_starts_with($feedUrl, 'file://')) {
            $feedUrl = (string) config('creditsoft.updates.feed_url', 'https://updates.creditsoft.app/api/update-feed');
        }

        return [
            'name' => $bundled ? basename($packagePath) : sprintf('creditsoft-office-v%s.zip', $latestVersion ?: $this->version()),
            'path' => $bundled ? $packagePath : null,
            'bundled' => $bundled,
            'source' => $bundled ? 'bundled-office-package' : 'remote-update-feed',
            'version' => $latestVersion ?: $this->version(),
            'build' => $feed['latest_build'] ?? null,
            'download_url' => $downloadUrl,
            'feed_url' => $feedUrl,
        ];
    }

    /**
     * Prefer a freshly built local update package when the installer is generated from source.
     *
     * @return array<string, mixed>|null
     */
    protected function localPackageFeed(): ?array
    {
        $feedPath = base_path('update.creditsoft.app/data/update-feed.json');

        if (! File::exists($feedPath)) {
            return null;
        }

        $decoded = json_decode((string) File::get($feedPath), true);

        if (! is_array($decoded)) {
            return null;
        }

        $latestVersion = trim((string) ($decoded['latest_version'] ?? ''));

        if ($latestVersion === '') {
            return null;
        }

        $packagePath = base_path(sprintf('update.creditsoft.app/downloads/creditsoft-office-v%s.zip', $latestVersion));

        if (! is_file($packagePath)) {
            return null;
        }

        return [
            ...$decoded,
            'source' => 'local',
            'feed_url' => 'file://'.$feedPath,
            'package_path' => $packagePath,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    protected function manifest(array $state, array $package): array
    {
        $clusterSshEnabled = filter_var(config('creditsoft.cluster_ssh.enabled', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        $clusterSshIdentityFile = trim((string) config('creditsoft.cluster_ssh.identity_file', 'creditsoft_cluster_ed25519')) ?: 'creditsoft_cluster_ed25519';
        $clusterSshCommentPrefix = trim((string) config('creditsoft.cluster_ssh.comment_prefix', 'creditsoft@cluster')) ?: 'creditsoft@cluster';
        $clusterSshSourceCidrs = trim((string) config('creditsoft.cluster_ssh.source_cidrs', '100.64.0.0/10,fd7a:115c:a1e0::/48'));
        $clusterSshOptions = $clusterSshSourceCidrs !== ''
            ? sprintf('from="%s",no-agent-forwarding,no-X11-forwarding,no-port-forwarding', str_replace('"', '', $clusterSshSourceCidrs))
            : 'no-agent-forwarding,no-X11-forwarding,no-port-forwarding';

        return [
            'product' => 'CreditSoft Intranet Node Installer',
            'installer_version' => $this->version(),
            'generated_at' => now()->toIso8601String(),
            'office' => [
                'name' => data_get($state, 'company_name') ?: config('app.name', 'CreditSoft'),
                'admin_email' => data_get($state, 'admin_email'),
                'tailscale_hostname' => data_get($state, 'tailscale_hostname') ?: config('creditsoft.tailscale_hostname'),
            ],
            'branding' => $this->brandingManifest($state),
            'runtime' => [
                'target' => 'docker-compose',
                'services' => ['intranet', 'queue', 'scheduler'],
                'profiles' => ['office', 'postgres', 'router', 'crm'],
                'default_url' => 'http://127.0.0.1:8001',
                'router_url' => 'http://127.0.0.1:8877/dashboard?source=intranet-client',
                'preferred_app_ports' => [80, 8001, 8002, 8003, 8004, 8005, 8080, 8081, 8082],
                'preferred_router_ports' => [8877, 8878, 8879, 8880, 8881, 8882, 8883, 8884, 8885, 8886, 8887, 8888, 8889, 8890],
            ],
            'package' => [
                'name' => $package['name'] ?? null,
                'bundled' => (bool) ($package['bundled'] ?? false),
                'version' => $package['version'] ?? $this->version(),
                'build' => $package['build'] ?? null,
                'download_url' => $package['download_url'] ?? null,
                'feed_url' => $package['feed_url'] ?? config('creditsoft.updates.feed_url'),
            ],
            'crm_sidecar' => [
                'image' => (string) config('creditsoft.integrations.crm.image', 'creditsoft/crm-sidecar:local'),
                'base_image' => (string) config('creditsoft.integrations.crm.base_image', 'update.creditsoft.app/creditsoft/crm-sidecar:latest'),
                'profile' => 'crm',
                'update_strategy' => 'local_build_from_creditsoft_base',
                'public_label' => 'CreditSoft CRM Sidecar',
            ],
            'security' => [
                'contains_office_configuration' => true,
                'contains_api_token' => false,
                'contains_tunnel_tokens' => false,
                'contains_ai_keys' => false,
                'contains_cluster_ssh_public_key' => false,
                'contains_cluster_ssh_private_key' => false,
                'note' => 'This zip intentionally ships without owner API, AI, tunnel, backup, CRM, or reusable SSH keys. The first-run installer generates local office credentials or asks the operator to paste provider tokens.',
            ],
            'cluster_ssh' => [
                'enabled' => $clusterSshEnabled,
                'strategy' => 'generate_on_install',
                'identity_file' => basename(str_replace('\\', '/', $clusterSshIdentityFile)),
                'comment_prefix' => $clusterSshCommentPrefix,
                'source_cidrs' => $clusterSshSourceCidrs,
                'authorized_keys_options' => $clusterSshOptions,
                'note' => 'No cluster SSH key material is shipped. The installer generates a unique Ed25519 identity on each node and authorizes that node public key with Tailscale-only restrictions.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function brandingManifest(array $state): array
    {
        $logoPath = $this->brandingLogoPath($state);
        $logoName = trim((string) data_get($state, 'branding.logo_name', ''));
        $uploadedAt = trim((string) data_get($state, 'branding.uploaded_at', ''));

        if ($logoPath === null) {
            return [
                'logo_name' => null,
                'logo_file' => null,
                'logo_url' => null,
                'uploaded_at' => null,
            ];
        }

        $filename = basename($logoPath);

        return [
            'logo_name' => $logoName !== '' ? $logoName : $filename,
            'logo_file' => 'branding/'.$filename,
            'logo_url' => rtrim((string) config('creditsoft.installer.logo_url_prefix', '/installer/branding'), '/').'/'.$filename,
            'uploaded_at' => $uploadedAt !== '' ? $uploadedAt : now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function brandingLogoPath(array $state): ?string
    {
        $logoUrl = trim((string) data_get($state, 'branding.logo_url', ''));

        if ($logoUrl === '') {
            return null;
        }

        $path = (string) parse_url($logoUrl, PHP_URL_PATH);
        $filename = basename($path);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $logoPath = rtrim((string) config('creditsoft.installer.logo_path', public_path('installer/branding')), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$filename;

        return is_file($logoPath) ? $logoPath : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function environmentFile(array $state): string
    {
        $package = $this->packageState();
        $officePgSuperpassword = Str::password(32, symbols: false);
        $creditsoftPgPassword = Str::password(32, symbols: false);

        $values = [
            'APP_NAME' => 'CreditSoft',
            'APP_ENV' => 'production',
            'APP_KEY' => '',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost:8001',
            'APP_LOCALE' => (string) config('app.locale', 'en'),
            'APP_FALLBACK_LOCALE' => (string) config('app.fallback_locale', 'en'),
            'APP_FAKER_LOCALE' => (string) config('app.faker_locale', 'en_US'),
            'APP_MAINTENANCE_DRIVER' => (string) data_get(config('app.maintenance', []), 'driver', 'file'),
            'BCRYPT_ROUNDS' => (string) env('BCRYPT_ROUNDS', '12'),
            'CREDITSOFT_APP_VERSION' => (string) ($package['version'] ?? $this->version()),
            'CREDITSOFT_APP_BUILD' => (string) ($package['build'] ?? now()->format('Y.m.d.His')),
            'CREDITSOFT_DOCKER_BIND' => '0.0.0.0',
            'CREDITSOFT_DOCKER_PORT' => '8001',
            'CREDITSOFT_ROUTER_BIND' => '127.0.0.1',
            'CREDITSOFT_ROUTER_PORT' => '8877',
            'CREDITSOFT_ROUTER_SELECTION_STRATEGY' => 'resource-aware',
            'CREDITSOFT_ROUTER_PREFERRED_LABEL' => '',
            'CREDITSOFT_ROUTER_PREFERRED_BASE_URL' => '',
            'CREDITSOFT_BROWSER_COMPANION_TRIAL_DAYS' => '7',
            'CREDITSOFT_BROWSER_COMPANION_DOWNLOAD_URL' => (string) config('creditsoft.updates.browser_companion_download_url', 'https://updates.creditsoft.app/downloads/creditsoft-browser-companion-v0.5.10.zip'),
            'LOG_CHANNEL' => 'stack',
            'LOG_STACK' => 'single',
            'LOG_DEPRECATIONS_CHANNEL' => 'null',
            'LOG_LEVEL' => 'info',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => 'office-db',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'creditsoft',
            'DB_USERNAME' => 'creditsoft',
            'DB_PASSWORD' => $creditsoftPgPassword,
            'DB_SSLMODE' => 'prefer',
            'OFFICE_PG_SUPERUSER' => 'creditsoft_root',
            'OFFICE_PG_SUPERPASSWORD' => $officePgSuperpassword,
            'OFFICE_PG_SHARED_BUFFERS' => '256MB',
            'OFFICE_PG_EFFECTIVE_CACHE_SIZE' => '1GB',
            'OFFICE_PG_WORK_MEM' => '4MB',
            'OFFICE_PG_MAINTENANCE_WORK_MEM' => '64MB',
            'PHP_OPCACHE_MEMORY_CONSUMPTION' => '192',
            'PHP_OPCACHE_MAX_ACCELERATED_FILES' => '20000',
            'PHP_OPCACHE_VALIDATE_TIMESTAMPS' => '0',
            'CREDITSOFT_PG_DATABASE' => 'creditsoft',
            'CREDITSOFT_PG_USER' => 'creditsoft',
            'CREDITSOFT_PG_PASSWORD' => $creditsoftPgPassword,
            'SESSION_DRIVER' => 'database',
            'SESSION_LIFETIME' => '43200',
            'SESSION_ENCRYPT' => 'false',
            'SESSION_PATH' => '/',
            'SESSION_DOMAIN' => 'null',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'BROADCAST_CONNECTION' => 'log',
            'FILESYSTEM_DISK' => 'local',
            'CREDITSOFT_MAIL_ENABLED' => 'false',
            'CREDITSOFT_MAIL_PROVIDER' => 'custom_smtp',
            'MAIL_MAILER' => 'log',
            'MAIL_SCHEME' => 'null',
            'MAIL_HOST' => '127.0.0.1',
            'MAIL_PORT' => '2525',
            'MAIL_USERNAME' => 'null',
            'MAIL_PASSWORD' => 'null',
            'MAIL_FROM_ADDRESS' => 'hello@example.com',
            'MAIL_FROM_NAME' => '${APP_NAME}',
            'MAIL_REPLY_TO_ADDRESS' => '',
            'MAIL_REPLY_TO_NAME' => '${APP_NAME}',
            'MAIL_EHLO_DOMAIN' => '',
            'SENDGRID_API_KEY' => '',
            'MEMCACHED_HOST' => '127.0.0.1',
            'REDIS_CLIENT' => 'phpredis',
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PASSWORD' => 'null',
            'REDIS_PORT' => '6379',
            'AWS_ACCESS_KEY_ID' => '',
            'AWS_SECRET_ACCESS_KEY' => '',
            'AWS_DEFAULT_REGION' => 'us-east-1',
            'AWS_BUCKET' => '',
            'AWS_USE_PATH_STYLE_ENDPOINT' => 'false',
            'BACKUP_DESTINATION_DISKS' => (string) data_get($state, 'backup_destination', 'local'),
            'BACKUP_ARCHIVE_PASSWORD' => '',
            'BACKUP_NOTIFY_EMAIL' => (string) data_get($state, 'admin_email', 'ops@creditsoft.local'),
            'CREDITSOFT_PORTAL_URL' => (string) config('creditsoft.portal_url', 'https://www.creditsoft.app'),
            'CREDITSOFT_TAILSCALE_REQUIRED' => $this->boolEnv(config('creditsoft.tunnels.tailscale.required', true)),
            'CREDITSOFT_TAILSCALE_HOSTNAME' => (string) (data_get($state, 'tailscale_hostname') ?: config('creditsoft.tailscale_hostname', 'creditsoft-intranet')),
            'CREDITSOFT_TAILSCALE_TAILNET' => '',
            'CREDITSOFT_TAILSCALE_API_KEY' => '',
            'CREDITSOFT_TAILSCALE_API_AUTHTOKEN' => '',
            'CREDITSOFT_TAILSCALE_API_KEY_EXPIRES_AT' => '',
            'CREDITSOFT_TAILSCALE_API_KEY_WARNING_DAYS' => (string) config('creditsoft.tunnels.tailscale.api_key_warning_days', 3),
            'CREDITSOFT_NGROK_ENABLED' => $this->boolEnv(config('creditsoft.tunnels.ngrok.enabled', false)),
            'CREDITSOFT_NGROK_API_ONLY' => $this->boolEnv(config('creditsoft.tunnels.ngrok.api_only', true)),
            'CREDITSOFT_NGROK_AUTHTOKEN' => '',
            'CREDITSOFT_NGROK_API_KEY' => '',
            'CREDITSOFT_NGROK_DOMAIN' => '',
            'CREDITSOFT_API_ENABLED' => $this->boolEnv(config('creditsoft.api.enabled', true)),
            'CREDITSOFT_API_TOKEN' => '',
            'CREDITSOFT_API_PUBLIC_BASE_URL' => (string) config('creditsoft.api.public_base_url', ''),
            'CREDITSOFT_INSTALLER_ENABLED' => 'true',
            'CREDITSOFT_INSTALLER_LOGO_URL_PREFIX' => (string) config('creditsoft.installer.logo_url_prefix', '/installer/branding'),
            'CREDITSOFT_LICENSE_MODE' => (string) config('creditsoft.installer.license_mode', 'auto'),
            'CREDITSOFT_LICENSE_API_URL' => (string) config('creditsoft.installer.license_check_api_url', 'https://api.creditsoft.app/license/validate'),
            'CREDITSOFT_LICENSE_PORTAL_URL' => (string) config('creditsoft.installer.license_check_portal_url', 'https://www.creditsoft.app/license/validate.json'),
            'CREDITSOFT_LICENSE_GRACE_DAYS' => (string) config('creditsoft.installer.license_grace_days', 7),
            'CREDITSOFT_UPDATE_FEED_URL' => (string) config('creditsoft.updates.feed_url', 'https://updates.creditsoft.app/api/update-feed'),
            'CREDITSOFT_AI_DEFAULT_PROVIDER' => (string) config('ai.default', 'openrouter_creditsoft'),
            'CREDITSOFT_CRM_ENABLED' => $this->boolEnv(config('creditsoft.integrations.crm.enabled', false)),
            'CREDITSOFT_CRM_BASE_URL' => (string) config('creditsoft.integrations.crm.base_url', ''),
            'CREDITSOFT_CRM_API_KEY' => '',
            'CRM_TAG' => 'latest',
            'CRM_IMAGE' => (string) config('creditsoft.integrations.crm.image', 'creditsoft/crm-sidecar:local'),
            'CRM_BASE_IMAGE' => (string) config('creditsoft.integrations.crm.base_image', 'update.creditsoft.app/creditsoft/crm-sidecar:latest'),
            'CRM_PORT' => '3000',
            'CRM_SERVER_URL' => 'http://localhost:3000',
            'CRM_APP_SECRET' => '',
            'CRM_PG_HOST' => 'office-db',
            'CRM_PG_PORT' => '5432',
            'CRM_PG_DATABASE' => 'crm',
            'CRM_PG_USER' => 'crm',
            'CRM_PG_PASSWORD' => Str::password(32, symbols: false),
            'CRM_STORAGE_TYPE' => 'local',
            'OPENCODE_API_KEY' => '',
            'OPENCODE_BASE_URL' => (string) config('creditsoft.ai.providers.opencode_zen.url', 'https://opencode.ai/zen/v1/chat/completions'),
            'OPENROUTER_API_KEY' => '',
            'OLLAMA_CLOUD_API_KEY' => '',
            'OLLAMA_CLOUD_BASE_URL' => (string) config('ai.providers.ollama_cloud.url', 'https://ollama.com'),
            'OPENROUTER_TEXT_MODEL' => (string) config('ai.providers.openrouter_creditsoft.text_model', 'arcee-ai/trinity-large-thinking'),
            'VITE_APP_NAME' => '${APP_NAME}',
        ];

        return collect($values)
            ->map(fn (string $value, string $key): string => "{$key}=".$this->escapeEnv($value))
            ->implode(PHP_EOL).PHP_EOL;
    }

    protected function readme(array $manifest): string
    {
        return implode(PHP_EOL, [
            '# CreditSoft Intranet Node Installer',
            '',
            'This package is generated by the CreditSoft intranet installer. It is not a hand-tuned Mac setup.',
            'It can install the same office node on Windows, Linux, or macOS as long as Docker with Compose is available.',
            '',
            '## Run',
            '',
            'Linux/macOS:',
            '',
            '```bash',
            'bash install.sh',
            'bash install.sh --office',
            'bash install.sh --postgres',
            'bash install.sh --with-router',
            'bash install.sh --office --app-port 80',
            'bash install.sh --postgres --with-router --with-crm',
            '```',
            '',
            'Windows PowerShell:',
            '',
            '```powershell',
            'Set-ExecutionPolicy -Scope Process Bypass',
            '.\\install.ps1',
            '.\\install.ps1 -Office',
            '.\\install.ps1 -Postgres',
            '.\\install.ps1 -WithRouter',
            '.\\install.ps1 -Office -AppPort 80',
            '.\\install.ps1 -Postgres -WithRouter -WithCrm',
            '```',
            '',
            '## What it does',
            '',
            '- Creates a CreditSoft intranet install directory.',
            '- Uses the bundled office package when present, otherwise downloads the package from the update feed.',
            '- Writes the generated `creditsoft-node.env` into `.env.docker`.',
            '- Generates a stable Laravel `APP_KEY` when the env file does not already have one.',
            '- Generates a node-unique `creditsoft_cluster_ed25519` SSH identity for the private cluster lane.',
            '- Probes host ports and prefers publishing the office app on port 80 when the server is free to use it.',
            '- Installs the uploaded office logo into the login screen when the web installer has branding attached.',
            '- Can run the full office stack with `--office` / `-Office`, which enables PostgreSQL, router, and CRM together.',
            '- Can switch the intranet database from SQLite to PostgreSQL with `--postgres` / `-Postgres`.',
            '- Starts `intranet`, `queue`, and `scheduler` through Docker Compose.',
            '- Optionally starts the local router and white-label CRM sidecar.',
            '',
            '## Sensitive',
            '',
            (string) data_get($manifest, 'security.note'),
            '',
            'Default app URL: '.data_get($manifest, 'runtime.default_url'),
            'Router URL: '.data_get($manifest, 'runtime.router_url'),
            '',
            'If an office needs a reserved LAN address, create the DHCP/static reservation on the router or operating system first, then set CREDITSOFT_DOCKER_BIND to that IP before starting the stack.',
        ]).PHP_EOL;
    }

    protected function shellInstaller(): string
    {
        return <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_DIR="${CREDITSOFT_INSTALL_DIR:-$HOME/CreditSoft-Intranet}"
PACKAGE_DIR="$SCRIPT_DIR/packages"
PACKAGE_PATH=""
WITH_ROUTER="false"
WITH_CRM="false"
WITH_POSTGRES="false"
WITH_OFFICE="false"
REQUESTED_APP_PORT="${CREDITSOFT_DOCKER_PORT:-}"
REQUESTED_ROUTER_PORT="${CREDITSOFT_ROUTER_PORT:-}"
REQUESTED_DOCKER_BIND="${CREDITSOFT_DOCKER_BIND:-}"
REQUESTED_ROUTER_BIND="${CREDITSOFT_ROUTER_BIND:-}"

while [ "$#" -gt 0 ]; do
  case "$1" in
    --office|--full)
      WITH_OFFICE="true"
      shift
      ;;
    --postgres)
      WITH_POSTGRES="true"
      shift
      ;;
    --with-router)
      WITH_ROUTER="true"
      shift
      ;;
    --with-crm)
      WITH_CRM="true"
      shift
      ;;
    --port|--app-port)
      REQUESTED_APP_PORT="${2:-}"
      shift 2
      ;;
    --router-port)
      REQUESTED_ROUTER_PORT="${2:-}"
      shift 2
      ;;
    --bind)
      REQUESTED_DOCKER_BIND="${2:-}"
      shift 2
      ;;
    --router-bind)
      REQUESTED_ROUTER_BIND="${2:-}"
      shift 2
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
done

if [ "$WITH_OFFICE" = "true" ]; then
  WITH_POSTGRES="true"
  WITH_ROUTER="true"
  WITH_CRM="true"
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required before CreditSoft can install this node."
  exit 1
fi

if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE=(docker-compose)
else
  echo "Docker Compose is required before CreditSoft can install this node."
  exit 1
fi

if ! command -v unzip >/dev/null 2>&1; then
  echo "unzip is required before CreditSoft can unpack the office package."
  exit 1
fi

host_port_is_busy() {
  local port="$1"

  if command -v lsof >/dev/null 2>&1; then
    lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
    return $?
  fi

  if command -v ss >/dev/null 2>&1; then
    ss -ltn "sport = :$port" 2>/dev/null | grep -q ":$port"
    return $?
  fi

  if command -v netstat >/dev/null 2>&1; then
    netstat -an 2>/dev/null | grep -E "[.:]${port}[[:space:]].*LISTEN" >/dev/null 2>&1
    return $?
  fi

  return 1
}

choose_host_port() {
  local label="$1"
  local requested="$2"
  shift 2

  if [ -n "$requested" ]; then
    if ! [[ "$requested" =~ ^[0-9]+$ ]] || [ "$requested" -lt 1 ] || [ "$requested" -gt 65535 ]; then
      echo "Requested $label port is invalid: $requested"
      exit 1
    fi

    if host_port_is_busy "$requested"; then
      echo "Requested $label port $requested is already in use."
      exit 1
    fi

    printf '%s\n' "$requested"
    return 0
  fi

  for port in "$@"; do
    if ! host_port_is_busy "$port"; then
      printf '%s\n' "$port"
      return 0
    fi
  done

  echo "No available $label port was found."
  exit 1
}

local_url_for() {
  local port="$1"
  if [ "$port" = "80" ]; then
    printf 'http://127.0.0.1\n'
  else
    printf 'http://127.0.0.1:%s\n' "$port"
  fi
}

mkdir -p "$INSTALL_DIR"
TMP_DIR="$(mktemp -d)"
cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

if compgen -G "$PACKAGE_DIR/creditsoft-office-v*.zip" >/dev/null 2>&1; then
  PACKAGE_PATH="$(ls "$PACKAGE_DIR"/creditsoft-office-v*.zip | sort | tail -n 1)"
else
  DOWNLOAD_URL="$(python3 - <<'PY' "$SCRIPT_DIR/manifest.json"
import json, sys
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    manifest = json.load(handle)
print(manifest.get("package", {}).get("download_url") or "")
PY
)"

  if [ -z "$DOWNLOAD_URL" ]; then
    echo "No bundled package or download URL was found in manifest.json."
    exit 1
  fi

  PACKAGE_PATH="$TMP_DIR/creditsoft-office.zip"
  if command -v curl >/dev/null 2>&1; then
    curl -fL "$DOWNLOAD_URL" -o "$PACKAGE_PATH"
  elif command -v wget >/dev/null 2>&1; then
    wget -O "$PACKAGE_PATH" "$DOWNLOAD_URL"
  else
    echo "curl or wget is required when no package is bundled."
    exit 1
  fi
fi

unzip -q "$PACKAGE_PATH" -d "$TMP_DIR/package"
PACKAGE_ROOT="$(find "$TMP_DIR/package" -maxdepth 1 -type d -name 'creditsoft-office-v*' | sort | tail -n 1)"
if [ -z "$PACKAGE_ROOT" ]; then
  PACKAGE_ROOT="$TMP_DIR/package"
fi

cp -R "$PACKAGE_ROOT"/. "$INSTALL_DIR"/
cp "$SCRIPT_DIR/creditsoft-node.env" "$INSTALL_DIR/.env.docker"

BRANDING_FILE="$(python3 - <<'PY' "$SCRIPT_DIR/manifest.json"
import json, sys
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    manifest = json.load(handle)
print(manifest.get("branding", {}).get("logo_file") or "")
PY
)"

if [ -n "$BRANDING_FILE" ] && [ -f "$SCRIPT_DIR/$BRANDING_FILE" ]; then
  BRANDING_NAME="$(basename "$BRANDING_FILE")"
  mkdir -p "$INSTALL_DIR/public/installer/branding" "$INSTALL_DIR/storage/app/private/install"
  cp "$SCRIPT_DIR/$BRANDING_FILE" "$INSTALL_DIR/public/installer/branding/$BRANDING_NAME"
  python3 - <<'PY' "$SCRIPT_DIR/manifest.json" "$INSTALL_DIR/storage/app/private/install/state.json"
import json, os, sys

manifest_path, state_path = sys.argv[1], sys.argv[2]
with open(manifest_path, "r", encoding="utf-8") as handle:
    manifest = json.load(handle)

state = {}
if os.path.exists(state_path):
    try:
        with open(state_path, "r", encoding="utf-8") as handle:
            existing = json.load(handle)
        if isinstance(existing, dict):
            state = existing
    except Exception:
        state = {}

branding = manifest.get("branding") or {}
office = manifest.get("office") or {}
state["company_name"] = office.get("name") or state.get("company_name")
state["admin_email"] = office.get("admin_email") or state.get("admin_email")
state["tailscale_hostname"] = office.get("tailscale_hostname") or state.get("tailscale_hostname")
state["branding"] = {
    "logo_name": branding.get("logo_name"),
    "logo_url": branding.get("logo_url"),
    "uploaded_at": branding.get("uploaded_at"),
}

os.makedirs(os.path.dirname(state_path), exist_ok=True)
with open(state_path, "w", encoding="utf-8") as handle:
    json.dump(state, handle, indent=2)
    handle.write("\n")
PY
fi

CLUSTER_SSH_SETTINGS="$(python3 - <<'PY' "$SCRIPT_DIR/manifest.json"
import json, sys
with open(sys.argv[1], "r", encoding="utf-8") as handle:
    manifest = json.load(handle)
cluster_ssh = manifest.get("cluster_ssh") or {}
enabled = bool(cluster_ssh.get("enabled"))
identity_file = str(cluster_ssh.get("identity_file") or "creditsoft_cluster_ed25519").strip().replace("\\", "/").split("/")[-1] or "creditsoft_cluster_ed25519"
comment_prefix = str(cluster_ssh.get("comment_prefix") or "creditsoft@cluster").strip() or "creditsoft@cluster"
options = str(cluster_ssh.get("authorized_keys_options") or "").strip()
print("\n".join([
    "1" if enabled else "0",
    identity_file,
    comment_prefix,
    options,
]))
PY
)"

CLUSTER_SSH_ENABLED="$(printf '%s\n' "$CLUSTER_SSH_SETTINGS" | sed -n '1p')"
CLUSTER_SSH_IDENTITY_FILE="$(printf '%s\n' "$CLUSTER_SSH_SETTINGS" | sed -n '2p')"
CLUSTER_SSH_COMMENT_PREFIX="$(printf '%s\n' "$CLUSTER_SSH_SETTINGS" | sed -n '3p')"
CLUSTER_SSH_OPTIONS="$(printf '%s\n' "$CLUSTER_SSH_SETTINGS" | sed -n '4p')"

if [ "$CLUSTER_SSH_ENABLED" = "1" ]; then
  if command -v ssh-keygen >/dev/null 2>&1; then
    SSH_DIR="$HOME/.ssh"
    CLUSTER_SSH_KEY_PATH="$SSH_DIR/$CLUSTER_SSH_IDENTITY_FILE"
    CLUSTER_SSH_PUBLIC_KEY_PATH="$CLUSTER_SSH_KEY_PATH.pub"
    CLUSTER_SSH_AUTHORIZED_KEYS="$SSH_DIR/authorized_keys"

    mkdir -p "$SSH_DIR"
    chmod 700 "$SSH_DIR"

    if [ ! -f "$CLUSTER_SSH_KEY_PATH" ]; then
      NODE_NAME="$(hostname 2>/dev/null || uname -n 2>/dev/null || printf 'node')"
      SAFE_NODE_NAME="$(printf '%s' "$NODE_NAME" | tr -cs '[:alnum:]._-' '-')"
      KEY_STAMP="$(date -u +%Y%m%d%H%M%SZ)"
      ssh-keygen -t ed25519 -a 64 -N "" -C "$CLUSTER_SSH_COMMENT_PREFIX $SAFE_NODE_NAME $KEY_STAMP" -f "$CLUSTER_SSH_KEY_PATH" >/dev/null
    fi

    chmod 600 "$CLUSTER_SSH_KEY_PATH"
    [ -f "$CLUSTER_SSH_PUBLIC_KEY_PATH" ] && chmod 644 "$CLUSTER_SSH_PUBLIC_KEY_PATH"

    if [ -f "$CLUSTER_SSH_PUBLIC_KEY_PATH" ]; then
      touch "$CLUSTER_SSH_AUTHORIZED_KEYS"
      chmod 600 "$CLUSTER_SSH_AUTHORIZED_KEYS"

      CLUSTER_SSH_PUBLIC_KEY="$(cat "$CLUSTER_SSH_PUBLIC_KEY_PATH")"
      CLUSTER_SSH_KEY_BODY="$(awk '{print $1 " " $2}' "$CLUSTER_SSH_PUBLIC_KEY_PATH")"
      CLUSTER_SSH_AUTHORIZED_KEY="${CLUSTER_SSH_OPTIONS:+$CLUSTER_SSH_OPTIONS }$CLUSTER_SSH_PUBLIC_KEY"

      if ! grep -qF "$CLUSTER_SSH_KEY_BODY" "$CLUSTER_SSH_AUTHORIZED_KEYS" 2>/dev/null; then
        printf '%s\n' "$CLUSTER_SSH_AUTHORIZED_KEY" >> "$CLUSTER_SSH_AUTHORIZED_KEYS"
      fi

      echo "CreditSoft cluster SSH identity ready: $CLUSTER_SSH_KEY_PATH"
    fi
  else
    echo "ssh-keygen was not found, so CreditSoft skipped cluster SSH identity generation."
  fi
fi

generate_key() {
  if command -v openssl >/dev/null 2>&1; then
    printf 'base64:%s' "$(openssl rand -base64 32)"
  else
    python3 - <<'PY'
import base64, os
print("base64:" + base64.b64encode(os.urandom(32)).decode("ascii"))
PY
  fi
}

if grep -q '^APP_KEY=$' "$INSTALL_DIR/.env.docker"; then
  APP_KEY_VALUE="$(generate_key)"
  TMP_ENV="$TMP_DIR/env"
  awk -v key="$APP_KEY_VALUE" '/^APP_KEY=/{print "APP_KEY=" key; next} {print}' "$INSTALL_DIR/.env.docker" > "$TMP_ENV"
  mv "$TMP_ENV" "$INSTALL_DIR/.env.docker"
fi

set_env() {
  local key="$1"
  local value="$2"
  local env_file="$INSTALL_DIR/.env.docker"
  local tmp_file="$TMP_DIR/env-${key}"

  if grep -q "^${key}=" "$env_file"; then
    awk -v key="$key" -v value="$value" 'BEGIN { line=key "=" value } $0 ~ "^" key "=" { print line; next } { print }' "$env_file" > "$tmp_file"
    mv "$tmp_file" "$env_file"
  else
    printf '%s=%s\n' "$key" "$value" >> "$env_file"
  fi
}

APP_PORT="$(choose_host_port "office app" "$REQUESTED_APP_PORT" 80 8001 8002 8003 8004 8005 8080 8081 8082)"
ROUTER_PORT="$(choose_host_port "local router" "$REQUESTED_ROUTER_PORT" 8877 8878 8879 8880 8881 8882 8883 8884 8885 8886 8887 8888 8889 8890)"
DOCKER_BIND="${REQUESTED_DOCKER_BIND:-0.0.0.0}"
ROUTER_BIND="${REQUESTED_ROUTER_BIND:-127.0.0.1}"
APP_URL_VALUE="$(local_url_for "$APP_PORT")"

set_env "CREDITSOFT_DOCKER_BIND" "$DOCKER_BIND"
set_env "CREDITSOFT_DOCKER_PORT" "$APP_PORT"
set_env "CREDITSOFT_ROUTER_BIND" "$ROUTER_BIND"
set_env "CREDITSOFT_ROUTER_PORT" "$ROUTER_PORT"
set_env "APP_URL" "$APP_URL_VALUE"

echo "CreditSoft office app host port selected: $APP_PORT"
echo "CreditSoft local router host port selected: $ROUTER_PORT"

if [ "$WITH_POSTGRES" = "true" ]; then
  CREDITSOFT_PG_DATABASE="$(grep '^CREDITSOFT_PG_DATABASE=' "$INSTALL_DIR/.env.docker" | tail -n 1 | cut -d= -f2-)"
  CREDITSOFT_PG_USER="$(grep '^CREDITSOFT_PG_USER=' "$INSTALL_DIR/.env.docker" | tail -n 1 | cut -d= -f2-)"
  CREDITSOFT_PG_PASSWORD="$(grep '^CREDITSOFT_PG_PASSWORD=' "$INSTALL_DIR/.env.docker" | tail -n 1 | cut -d= -f2-)"

  set_env "DB_CONNECTION" "pgsql"
  set_env "DB_HOST" "office-db"
  set_env "DB_PORT" "5432"
  set_env "DB_DATABASE" "${CREDITSOFT_PG_DATABASE:-creditsoft}"
  set_env "DB_USERNAME" "${CREDITSOFT_PG_USER:-creditsoft}"
  set_env "DB_PASSWORD" "${CREDITSOFT_PG_PASSWORD}"
  set_env "DB_SSLMODE" "prefer"
fi

cd "$INSTALL_DIR"
"${COMPOSE[@]}" --env-file .env.docker build intranet

if [ "$WITH_CRM" = "true" ]; then
  "${COMPOSE[@]}" --env-file .env.docker --profile crm build crm
fi

if [ "$WITH_OFFICE" = "true" ]; then
  "${COMPOSE[@]}" --env-file .env.docker --profile office up -d
else
  if [ "$WITH_POSTGRES" = "true" ]; then
    "${COMPOSE[@]}" --env-file .env.docker --profile postgres up -d office-db
  fi

  "${COMPOSE[@]}" --env-file .env.docker up -d intranet queue scheduler

  if [ "$WITH_ROUTER" = "true" ]; then
    "${COMPOSE[@]}" --env-file .env.docker --profile router up -d local-router
  fi

  if [ "$WITH_CRM" = "true" ]; then
    "${COMPOSE[@]}" --env-file .env.docker --profile crm up -d crm crm-worker
  fi
fi

echo "CreditSoft intranet node is installed at $INSTALL_DIR"
echo "Open $APP_URL_VALUE"
BASH;
    }

    protected function powershellInstaller(): string
    {
        return <<<'POWERSHELL'
param(
    [string]$InstallDir = "$env:ProgramData\CreditSoft\Intranet",
    [string]$Bind = "",
    [int]$AppPort = 0,
    [string]$RouterBind = "",
    [int]$RouterPort = 0,
    [switch]$Office,
    [switch]$Postgres,
    [switch]$WithRouter,
    [switch]$WithCrm
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$PackageDir = Join-Path $ScriptDir "packages"
$ManifestPath = Join-Path $ScriptDir "manifest.json"
$Manifest = Get-Content $ManifestPath -Raw | ConvertFrom-Json

if ($Office) {
    $Postgres = $true
    $WithRouter = $true
    $WithCrm = $true
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker is required before CreditSoft can install this node."
}

docker compose version *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Docker Compose is required before CreditSoft can install this node."
}

function Test-CreditSoftHostPortBusy {
    param([int]$Port)

    if (Get-Command Get-NetTCPConnection -ErrorAction SilentlyContinue) {
        $Listeners = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue

        if ($Listeners) {
            return $true
        }
    }

    $Listener = $null
    try {
        $Listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Any, $Port)
        $Listener.Start()
        return $false
    } catch {
        return $true
    } finally {
        if ($Listener) {
            $Listener.Stop()
        }
    }
}

function Select-CreditSoftHostPort {
    param(
        [string]$Label,
        [int]$Requested,
        [int[]]$Candidates
    )

    if ($Requested -gt 0) {
        if (Test-CreditSoftHostPortBusy -Port $Requested) {
            throw "Requested $Label port $Requested is already in use."
        }

        return $Requested
    }

    foreach ($Candidate in $Candidates) {
        if (-not (Test-CreditSoftHostPortBusy -Port $Candidate)) {
            return $Candidate
        }
    }

    throw "No available $Label port was found."
}

function Get-CreditSoftLocalUrl {
    param([int]$Port)

    if ($Port -eq 80) {
        return "http://127.0.0.1"
    }

    return "http://127.0.0.1`:$Port"
}

New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null
$TempDir = Join-Path ([System.IO.Path]::GetTempPath()) ("creditsoft-" + [System.Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Force -Path $TempDir | Out-Null

try {
    $PackagePath = Get-ChildItem -Path $PackageDir -Filter "creditsoft-office-v*.zip" -ErrorAction SilentlyContinue |
        Sort-Object Name |
        Select-Object -Last 1 |
        ForEach-Object { $_.FullName }

    if (-not $PackagePath) {
        $DownloadUrl = $Manifest.package.download_url

        if (-not $DownloadUrl) {
            throw "No bundled package or download URL was found in manifest.json."
        }

        $PackagePath = Join-Path $TempDir "creditsoft-office.zip"
        Invoke-WebRequest -Uri $DownloadUrl -OutFile $PackagePath
    }

    $ExtractDir = Join-Path $TempDir "package"
    Expand-Archive -Path $PackagePath -DestinationPath $ExtractDir -Force

    $PackageRoot = Get-ChildItem -Path $ExtractDir -Directory -Filter "creditsoft-office-v*" |
        Sort-Object Name |
        Select-Object -Last 1

    if (-not $PackageRoot) {
        $PackageRoot = Get-Item $ExtractDir
    }

    Copy-Item -Path (Join-Path $PackageRoot.FullName "*") -Destination $InstallDir -Recurse -Force
    Copy-Item -Path (Join-Path $ScriptDir "creditsoft-node.env") -Destination (Join-Path $InstallDir ".env.docker") -Force

    if ($Manifest.branding.logo_file) {
        $BrandingSource = Join-Path $ScriptDir $Manifest.branding.logo_file

        if (Test-Path $BrandingSource) {
            $BrandingTargetDir = Join-Path $InstallDir "public\installer\branding"
            New-Item -ItemType Directory -Force -Path $BrandingTargetDir | Out-Null
            Copy-Item -Path $BrandingSource -Destination (Join-Path $BrandingTargetDir (Split-Path -Leaf $BrandingSource)) -Force

            $StateDir = Join-Path $InstallDir "storage\app\private\install"
            $StatePath = Join-Path $StateDir "state.json"
            New-Item -ItemType Directory -Force -Path $StateDir | Out-Null

            $State = [ordered]@{
                company_name = $Manifest.office.name
                admin_email = $Manifest.office.admin_email
                tailscale_hostname = $Manifest.office.tailscale_hostname
                branding = [ordered]@{
                    logo_name = $Manifest.branding.logo_name
                    logo_url = $Manifest.branding.logo_url
                    uploaded_at = $Manifest.branding.uploaded_at
                }
            }

            $State | ConvertTo-Json -Depth 8 | Set-Content -Path $StatePath
        }
    }

    if ($Manifest.cluster_ssh -and $Manifest.cluster_ssh.enabled) {
        if (-not (Get-Command ssh-keygen -ErrorAction SilentlyContinue)) {
            Write-Warning "ssh-keygen was not found, so CreditSoft skipped cluster SSH identity generation."
        } else {
            $IdentityFile = [string]$Manifest.cluster_ssh.identity_file
            if (-not $IdentityFile) {
                $IdentityFile = "creditsoft_cluster_ed25519"
            }

            $IdentityFile = Split-Path -Leaf $IdentityFile
            $CommentPrefix = [string]$Manifest.cluster_ssh.comment_prefix
            if (-not $CommentPrefix) {
                $CommentPrefix = "creditsoft@cluster"
            }

            $Options = [string]$Manifest.cluster_ssh.authorized_keys_options
            $SshDir = Join-Path $HOME ".ssh"
            $KeyPath = Join-Path $SshDir $IdentityFile
            $PublicKeyPath = "$KeyPath.pub"
            $AuthorizedKeysPath = Join-Path $SshDir "authorized_keys"

            New-Item -ItemType Directory -Force -Path $SshDir | Out-Null

            if (-not (Test-Path $KeyPath)) {
                $NodeName = [Environment]::MachineName
                $SafeNodeName = $NodeName -replace "[^A-Za-z0-9._-]+", "-"
                $KeyStamp = (Get-Date).ToUniversalTime().ToString("yyyyMMddHHmmssZ")
                & ssh-keygen -t ed25519 -a 64 -N "" -C "$CommentPrefix $SafeNodeName $KeyStamp" -f $KeyPath *> $null

                if ($LASTEXITCODE -ne 0) {
                    throw "CreditSoft could not generate the cluster SSH identity."
                }
            }

            if (-not (Test-Path $AuthorizedKeysPath)) {
                New-Item -ItemType File -Force -Path $AuthorizedKeysPath | Out-Null
            }

            if (Test-Path $PublicKeyPath) {
                $PublicKey = (Get-Content -Path $PublicKeyPath -Raw).Trim()
                $PublicKeyParts = $PublicKey -split "\s+"
                $KeyBody = if ($PublicKeyParts.Length -ge 2) { "$($PublicKeyParts[0]) $($PublicKeyParts[1])" } else { $PublicKey }
                $AuthorizedKey = if ($Options) { "$Options $PublicKey" } else { $PublicKey }
                $ExistingAuthorizedKeys = [string] (Get-Content -Path $AuthorizedKeysPath -Raw -ErrorAction SilentlyContinue)

                if (-not $ExistingAuthorizedKeys.Contains($KeyBody)) {
                    Add-Content -Path $AuthorizedKeysPath -Value $AuthorizedKey
                }

                Write-Host "CreditSoft cluster SSH identity ready: $KeyPath"
            }
        }
    }

    $EnvPath = Join-Path $InstallDir ".env.docker"
    $EnvText = Get-Content $EnvPath -Raw

    if ($EnvText -match "(?m)^APP_KEY=$") {
        $Bytes = New-Object byte[] 32
        [System.Security.Cryptography.RandomNumberGenerator]::Fill($Bytes)
        $AppKey = "base64:" + [Convert]::ToBase64String($Bytes)
        $EnvText = $EnvText -replace "(?m)^APP_KEY=$", "APP_KEY=$AppKey"
        Set-Content -Path $EnvPath -Value $EnvText -NoNewline
    }

    function Set-CreditSoftEnvValue {
        param(
            [string]$Path,
            [string]$Key,
            [string]$Value
        )

        $Text = Get-Content $Path -Raw
        $Line = "$Key=$Value"

        if ($Text -match "(?m)^$([regex]::Escape($Key))=") {
            $Text = [regex]::Replace($Text, "(?m)^$([regex]::Escape($Key))=.*$", $Line)
        } else {
            $Text = $Text.TrimEnd() + [Environment]::NewLine + $Line + [Environment]::NewLine
        }

        Set-Content -Path $Path -Value $Text -NoNewline
    }

    $SelectedAppPort = Select-CreditSoftHostPort -Label "office app" -Requested $AppPort -Candidates @(80,8001,8002,8003,8004,8005,8080,8081,8082)
    $SelectedRouterPort = Select-CreditSoftHostPort -Label "local router" -Requested $RouterPort -Candidates @(8877,8878,8879,8880,8881,8882,8883,8884,8885,8886,8887,8888,8889,8890)
    $DockerBind = if ($Bind) { $Bind } else { "0.0.0.0" }
    $SelectedRouterBind = if ($RouterBind) { $RouterBind } else { "127.0.0.1" }
    $LocalAppUrl = Get-CreditSoftLocalUrl -Port $SelectedAppPort

    Set-CreditSoftEnvValue -Path $EnvPath -Key "CREDITSOFT_DOCKER_BIND" -Value $DockerBind
    Set-CreditSoftEnvValue -Path $EnvPath -Key "CREDITSOFT_DOCKER_PORT" -Value ([string]$SelectedAppPort)
    Set-CreditSoftEnvValue -Path $EnvPath -Key "CREDITSOFT_ROUTER_BIND" -Value $SelectedRouterBind
    Set-CreditSoftEnvValue -Path $EnvPath -Key "CREDITSOFT_ROUTER_PORT" -Value ([string]$SelectedRouterPort)
    Set-CreditSoftEnvValue -Path $EnvPath -Key "APP_URL" -Value $LocalAppUrl

    Write-Host "CreditSoft office app host port selected: $SelectedAppPort"
    Write-Host "CreditSoft local router host port selected: $SelectedRouterPort"

    if ($Postgres) {
        $EnvValues = @{}
        Get-Content $EnvPath | ForEach-Object {
            if ($_ -match '^([^=]+)=(.*)$') {
                $EnvValues[$Matches[1]] = $Matches[2]
            }
        }

        $CreditSoftPgDatabase = "creditsoft"
        $CreditSoftPgUser = "creditsoft"
        $CreditSoftPgPassword = ""

        if ($EnvValues.ContainsKey("CREDITSOFT_PG_DATABASE") -and $EnvValues["CREDITSOFT_PG_DATABASE"]) {
            $CreditSoftPgDatabase = $EnvValues["CREDITSOFT_PG_DATABASE"]
        }

        if ($EnvValues.ContainsKey("CREDITSOFT_PG_USER") -and $EnvValues["CREDITSOFT_PG_USER"]) {
            $CreditSoftPgUser = $EnvValues["CREDITSOFT_PG_USER"]
        }

        if ($EnvValues.ContainsKey("CREDITSOFT_PG_PASSWORD")) {
            $CreditSoftPgPassword = $EnvValues["CREDITSOFT_PG_PASSWORD"]
        }

        Set-CreditSoftEnvValue -Path $EnvPath -Key "DB_CONNECTION" -Value "pgsql"
        Set-CreditSoftEnvValue -Path $EnvPath -Key "DB_HOST" -Value "office-db"
        Set-CreditSoftEnvValue -Path $EnvPath -Key "DB_PORT" -Value "5432"
        Set-CreditSoftEnvValue -Path $EnvPath -Key "DB_DATABASE" -Value $CreditSoftPgDatabase
        Set-CreditSoftEnvValue -Path $EnvPath -Key "DB_USERNAME" -Value $CreditSoftPgUser
        Set-CreditSoftEnvValue -Path $EnvPath -Key "DB_PASSWORD" -Value $CreditSoftPgPassword
        Set-CreditSoftEnvValue -Path $EnvPath -Key "DB_SSLMODE" -Value "prefer"
    }

    Push-Location $InstallDir
    docker compose --env-file .env.docker build intranet

    if ($WithCrm) {
        docker compose --env-file .env.docker --profile crm build crm
    }

    if ($Office) {
        docker compose --env-file .env.docker --profile office up -d
    } else {
        if ($Postgres) {
            docker compose --env-file .env.docker --profile postgres up -d office-db
        }

        docker compose --env-file .env.docker up -d intranet queue scheduler

        if ($WithRouter) {
            docker compose --env-file .env.docker --profile router up -d local-router
        }

        if ($WithCrm) {
            docker compose --env-file .env.docker --profile crm up -d crm crm-worker
        }
    }

    Pop-Location

    Write-Host "CreditSoft intranet node is installed at $InstallDir"
    Write-Host "Open $LocalAppUrl"
}
finally {
    if (Test-Path $TempDir) {
        Remove-Item -Recurse -Force $TempDir
    }
}
POWERSHELL;
    }

    protected function boolEnv(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false ? 'false' : 'true';
    }

    protected function escapeEnv(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|#|"/', $value) === 1) {
            return '"'.addcslashes($value, '"\\').'"';
        }

        return $value;
    }
}
