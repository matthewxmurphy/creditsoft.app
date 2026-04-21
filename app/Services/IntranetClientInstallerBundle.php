<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class IntranetClientInstallerBundle
{
    public function sourcePath(): string
    {
        return base_path('intranet-client');
    }

    public function version(): string
    {
        $packagePath = $this->sourcePath().DIRECTORY_SEPARATOR.'package.json';

        if (is_file($packagePath)) {
            $decoded = json_decode((string) file_get_contents($packagePath), true);
            $version = trim((string) data_get(is_array($decoded) ? $decoded : [], 'version'));

            if ($version !== '') {
                return $version;
            }
        }

        return '0.1.0';
    }

    public function downloadName(): string
    {
        return sprintf('creditsoft-intranet-client-installer-v%s.zip', $this->version());
    }

    public function publicDownloadUrl(): string
    {
        $configuredUrl = trim((string) config('creditsoft.updates.intranet_client_download_url', ''));

        return $configuredUrl !== ''
            ? $configuredUrl
            : sprintf('https://updates.creditsoft.app/downloads/%s', $this->downloadName());
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function summary(array $state): array
    {
        return [
            'name' => 'CreditSoft Employee Intranet Client',
            'version' => $this->version(),
            'download_name' => $this->downloadName(),
            'download_url' => $this->publicDownloadUrl(),
            'local_route_url' => route('install.intranet-client.download'),
            'description' => 'Employee workstation installer for the local 127.0.0.1 router and PWA. It does not install Docker, a database, or cluster SSH.',
            'platforms' => ['Windows PowerShell', 'macOS bash', 'Linux bash'],
            'router_url' => 'http://127.0.0.1/dashboard?source=intranet-client',
            'office_name' => 'CreditSoft Office',
            'candidate_api_bases' => $this->candidateApiBases(),
            'api_token_included' => false,
            'contains_cluster_ssh' => false,
            'sensitive' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function build(array $state): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build the intranet client installer.');
        }

        if (! is_dir($this->sourcePath())) {
            throw new RuntimeException('CreditSoft intranet client source folder was not found.');
        }

        $directory = storage_path('app/private/intranet-client-installer');
        $archivePath = $directory.DIRECTORY_SEPARATOR.$this->downloadName();
        $manifest = $this->manifest();

        File::ensureDirectoryExists($directory);

        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('CreditSoft could not create the intranet client installer archive.');
        }

        $zip->addFromString('README.md', $this->readme($manifest));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $zip->addFromString('pairing-config.json', json_encode($this->pairingConfig($manifest), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $zip->addFromString('install.sh', $this->shellInstaller());
        $zip->addFromString('install.ps1', $this->powershellInstaller());

        $this->addRequiredClientFile($zip, 'README.md', 'client/README.md');
        $this->addRequiredClientFile($zip, 'package.json', 'client/package.json');
        $this->addRequiredClientFile($zip, 'bin/creditsoft-intranet-client.mjs', 'client/bin/creditsoft-intranet-client.mjs');
        $this->addRequiredClientFile($zip, 'bin/creditsoft-loopback-router.mjs', 'client/bin/creditsoft-loopback-router.mjs');
        $this->addRequiredClientFile($zip, 'examples/pairing-config.example.json', 'client/examples/pairing-config.example.json');

        $zip->close();

        $this->publishToUpdateLane($archivePath);

        return $archivePath;
    }

    /**
     * @return array<string, mixed>
     */
    protected function manifest(): array
    {
        return [
            'product' => 'CreditSoft Employee Intranet Client Installer',
            'installer_version' => $this->version(),
            'generated_at' => now()->toIso8601String(),
            'office' => [
                'name' => 'CreditSoft Office',
                'admin_email' => null,
                'tailscale_hostname' => null,
            ],
            'runtime' => [
                'role' => 'employee-client',
                'listen_host' => '127.0.0.1',
                'listen_port' => 'auto',
                'preferred_listen_ports' => [80, 8877, 8878, 8879, 8880, 8881, 8882, 8883, 8884, 8885, 8886, 8887, 8888, 8889, 8890, 8891, 8892, 8893, 8894, 8895, 8896, 8897, 8898, 8899],
                'router_url' => 'http://127.0.0.1/dashboard?source=intranet-client',
                'dashboard_path' => '/dashboard?source=intranet-client',
                'selection_strategy' => 'resource-aware',
            ],
            'api' => [
                'candidate_base_urls' => $this->candidateApiBases(),
                'token_included' => false,
                'token_file' => '~/.creditsoft/intranet-client-api-token',
                'pairing_note' => 'Use a staff API key and an office pairing URL or --api-base value. This public installer never ships office API URLs or API keys.',
            ],
            'security' => [
                'contains_api_token' => false,
                'contains_ai_keys' => false,
                'contains_tunnel_tokens' => false,
                'contains_cluster_ssh' => false,
                'contains_database' => false,
                'contains_server_runtime' => false,
                'note' => 'This zip is for employee workstations. It installs only the local loopback router and does not join server cluster SSH.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    protected function pairingConfig(array $manifest): array
    {
        return [
            'officeName' => (string) data_get($manifest, 'office.name', 'CreditSoft Office'),
            'candidateBaseUrls' => data_get($manifest, 'api.candidate_base_urls', []),
            'selectionStrategy' => data_get($manifest, 'runtime.selection_strategy', 'resource-aware'),
            'dashboardPath' => data_get($manifest, 'runtime.dashboard_path', '/dashboard?source=intranet-client'),
            'notes' => 'Do not store a personal API key in this file. Pass it through CREDITSOFT_API_TOKEN, --token, or a future OS keychain wrapper.',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function candidateApiBases(): array
    {
        return collect([
            'http://127.0.0.1:8001/api/v1',
        ])
            ->map(fn (mixed $url): ?string => $this->normalizeApiBaseUrl($url))
            ->filter()
            ->unique(fn (string $url): string => strtolower($url))
            ->values()
            ->all();
    }

    protected function normalizeApiBaseUrl(mixed $url): ?string
    {
        $candidate = trim((string) $url);

        if ($candidate === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        try {
            $parsed = parse_url($candidate);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return null;
        }

        $path = rtrim((string) ($parsed['path'] ?? ''), '/');

        if ($path === '' || $path === '/') {
            $path = '/api/v1';
        } elseif ($path === '/api') {
            $path = '/api/v1';
        } elseif (! str_ends_with($path, '/api/v1')) {
            $path .= '/api/v1';
        }

        $host = (string) $parsed['host'];
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';

        return strtolower((string) $parsed['scheme']).'://'.$host.$port.$path;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function readme(array $manifest): string
    {
        $officeName = (string) data_get($manifest, 'office.name', 'CreditSoft Office');
        $routerUrl = (string) data_get($manifest, 'runtime.router_url', 'http://127.0.0.1/dashboard?source=intranet-client');
        $apiBases = collect(data_get($manifest, 'api.candidate_base_urls', []))
            ->map(fn (string $url): string => '- '.$url)
            ->implode(PHP_EOL);

        return implode(PHP_EOL, [
            '# CreditSoft Employee Intranet Client',
            '',
            'This package is for employee workstations, not server nodes.',
            'It installs the local browser/PWA router on the first available localhost port, preferring 80 and falling back to 8877+.',
            '',
            'Office: '.$officeName,
            'Router URL: '.$routerUrl,
            '',
            '## What it does',
            '',
            '- Copies the CreditSoft intranet client runner into the user profile.',
            '- Starts the local loopback router on 127.0.0.1, preferring port 80 when the workstation can bind it.',
            '- Reads a staff API key from the local user profile when one is provided.',
            '- Probes configured and Tailscale-discovered server nodes and picks the healthiest reachable one.',
            '',
            '## What it does not do',
            '',
            '- Does not install Docker.',
            '- Does not install a database.',
            '- Does not install or generate cluster SSH keys.',
            '- Does not ship API, AI, tunnel, or backup secrets.',
            '',
            '## Run',
            '',
            'macOS/Linux:',
            '',
            '```bash',
            'bash install.sh',
            'bash install.sh --api-base http://office-server:8001/api/v1',
            'bash install.sh --listen-port 8877',
            'CREDITSOFT_API_TOKEN="paste-staff-api-key" bash install.sh',
            '```',
            '',
            'Windows PowerShell:',
            '',
            '```powershell',
            'Set-ExecutionPolicy -Scope Process Bypass',
            '.\\install.ps1',
            '.\\install.ps1 -ApiBase http://office-server:8001/api/v1',
            '.\\install.ps1 -ListenPort 8877',
            '$env:CREDITSOFT_API_TOKEN = "paste-staff-api-key"; .\\install.ps1',
            '```',
            '',
            '## Candidate API lanes',
            '',
            $apiBases !== '' ? $apiBases : '- Add a pairing URL or API base after install.',
            '',
            'Personal API keys belong to the staff member using the workstation. Keep them out of this zip.',
        ]).PHP_EOL;
    }

    protected function shellInstaller(): string
    {
        return <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_DIR="${CREDITSOFT_CLIENT_INSTALL_DIR:-$HOME/.creditsoft/intranet-client}"
CONFIG_DIR="$HOME/.creditsoft"
TOKEN_FILE="${CREDITSOFT_API_TOKEN_FILE:-$CONFIG_DIR/intranet-client-api-token}"
RUNNER="$INSTALL_DIR/run-router.sh"
MANIFEST_PATH="$SCRIPT_DIR/manifest.json"
PAIRING_PATH="$SCRIPT_DIR/pairing-config.json"
NO_SERVICE="false"
EXTRA_BASES=""
REQUESTED_LISTEN_HOST=""
REQUESTED_LISTEN_PORT=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    --api-base)
      EXTRA_BASES="${EXTRA_BASES:+$EXTRA_BASES,}${2:-}"
      shift 2
      ;;
    --token)
      CREDITSOFT_API_TOKEN="${2:-}"
      shift 2
      ;;
    --listen)
      REQUESTED_LISTEN_HOST="${2:-}"
      shift 2
      ;;
    --listen-port)
      REQUESTED_LISTEN_PORT="${2:-}"
      shift 2
      ;;
    --no-service)
      NO_SERVICE="true"
      shift
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
done

can_bind_port() {
  node -e "const net=require('net'); const port=Number(process.argv[1]); const host=process.argv[2]; if (!Number.isInteger(port) || port < 1 || port > 65535) process.exit(2); const server=net.createServer(); server.once('error', () => process.exit(1)); server.listen(port, host, () => server.close(() => process.exit(0)));" "$1" "$2" >/dev/null 2>&1
}

choose_listen_port() {
  local host="$1"
  local requested="$2"
  shift 2

  if [ -n "$requested" ]; then
    if can_bind_port "$requested" "$host"; then
      printf '%s\n' "$requested"
      return 0
    fi

    echo "Requested CreditSoft local router port $requested is not available on $host."
    exit 1
  fi

  for port in "$@"; do
    if can_bind_port "$port" "$host"; then
      printf '%s\n' "$port"
      return 0
    fi
  done

  echo "No CreditSoft local router port was available on $host."
  exit 1
}

router_url_for() {
  local host="$1"
  local port="$2"
  if [ "$port" = "80" ]; then
    printf 'http://%s/dashboard?source=intranet-client\n' "$host"
  else
    printf 'http://%s:%s/dashboard?source=intranet-client\n' "$host" "$port"
  fi
}

if ! command -v node >/dev/null 2>&1; then
  echo "Node.js 20 or newer is required for the CreditSoft employee client router."
  exit 1
fi

NODE_MAJOR="$(node -p "Number(process.versions.node.split('.')[0])")"
if [ "$NODE_MAJOR" -lt 20 ]; then
  echo "Node.js 20 or newer is required. Found: $(node --version)"
  exit 1
fi

if [ ! -f "$MANIFEST_PATH" ]; then
  echo "manifest.json was not found next to install.sh."
  exit 1
fi

mkdir -p "$INSTALL_DIR" "$INSTALL_DIR/bin" "$INSTALL_DIR/examples" "$CONFIG_DIR"
cp "$SCRIPT_DIR/client/README.md" "$INSTALL_DIR/README.md"
cp "$SCRIPT_DIR/client/package.json" "$INSTALL_DIR/package.json"
cp "$SCRIPT_DIR/client/bin/creditsoft-intranet-client.mjs" "$INSTALL_DIR/bin/creditsoft-intranet-client.mjs"
cp "$SCRIPT_DIR/client/bin/creditsoft-loopback-router.mjs" "$INSTALL_DIR/bin/creditsoft-loopback-router.mjs"
cp "$SCRIPT_DIR/client/examples/pairing-config.example.json" "$INSTALL_DIR/examples/pairing-config.example.json"
cp "$MANIFEST_PATH" "$INSTALL_DIR/manifest.json"
cp "$PAIRING_PATH" "$INSTALL_DIR/pairing-config.json"
chmod +x "$INSTALL_DIR/bin/creditsoft-intranet-client.mjs" "$INSTALL_DIR/bin/creditsoft-loopback-router.mjs"

API_BASES="$(node -e "const fs=require('fs'); const manifest=JSON.parse(fs.readFileSync(process.argv[1], 'utf8')); const extra=(process.argv[2]||'').split(',').map((v)=>v.trim()).filter(Boolean); const bases=[...(manifest.api?.candidate_base_urls||[]), ...extra]; console.log([...new Set(bases.filter(Boolean))].join(','));" "$MANIFEST_PATH" "$EXTRA_BASES")"
LISTEN_HOST="$(node -e "const fs=require('fs'); const manifest=JSON.parse(fs.readFileSync(process.argv[1], 'utf8')); console.log(manifest.runtime?.listen_host || '127.0.0.1');" "$MANIFEST_PATH")"
LISTEN_HOST="${REQUESTED_LISTEN_HOST:-$LISTEN_HOST}"
PREFERRED_LISTEN_PORTS="$(node -e "const fs=require('fs'); const manifest=JSON.parse(fs.readFileSync(process.argv[1], 'utf8')); const ports=manifest.runtime?.preferred_listen_ports || [80,8877,8878,8879,8880,8881,8882,8883,8884,8885,8886,8887,8888,8889,8890,8891,8892,8893,8894,8895,8896,8897,8898,8899]; console.log(ports.join(' '));" "$MANIFEST_PATH")"
# shellcheck disable=SC2086
LISTEN_PORT="$(choose_listen_port "$LISTEN_HOST" "$REQUESTED_LISTEN_PORT" $PREFERRED_LISTEN_PORTS)"
STRATEGY="$(node -e "const fs=require('fs'); const manifest=JSON.parse(fs.readFileSync(process.argv[1], 'utf8')); console.log(manifest.runtime?.selection_strategy || 'resource-aware');" "$MANIFEST_PATH")"
ROUTER_URL="$(router_url_for "$LISTEN_HOST" "$LISTEN_PORT")"

node -e "const fs=require('fs'); const path=process.argv[1]; const bases=(process.argv[2]||'').split(',').map((v)=>v.trim()).filter(Boolean); const config=JSON.parse(fs.readFileSync(path, 'utf8')); config.candidateBaseUrls=[...new Set(bases)]; fs.writeFileSync(path, JSON.stringify(config, null, 2) + '\n');" "$INSTALL_DIR/pairing-config.json" "$API_BASES"

if [ -n "${CREDITSOFT_API_TOKEN:-}" ]; then
  printf '%s' "$CREDITSOFT_API_TOKEN" > "$TOKEN_FILE"
  chmod 600 "$TOKEN_FILE"
elif [ ! -f "$TOKEN_FILE" ]; then
  : > "$TOKEN_FILE"
  chmod 600 "$TOKEN_FILE"
fi

cat > "$RUNNER" <<EOF
#!/usr/bin/env bash
set -euo pipefail
TOKEN_VALUE=""
if [ -f '$TOKEN_FILE' ]; then
  TOKEN_VALUE="\$(tr -d '\r\n' < '$TOKEN_FILE' || true)"
fi
if [ -n "\$TOKEN_VALUE" ]; then
  export CREDITSOFT_API_TOKEN="\$TOKEN_VALUE"
else
  unset CREDITSOFT_API_TOKEN
fi
exec node '$INSTALL_DIR/bin/creditsoft-intranet-client.mjs' --serve --pair '$INSTALL_DIR/pairing-config.json' --listen '$LISTEN_HOST' --listen-port '$LISTEN_PORT' --strategy '$STRATEGY' --no-open --no-prompt-token --save
EOF
chmod +x "$RUNNER"
echo "CreditSoft local router port selected: $LISTEN_PORT on $LISTEN_HOST"

if [ "$NO_SERVICE" = "true" ]; then
  echo "CreditSoft employee client installed without a service."
  echo "Run manually: $RUNNER"
  echo "Open: $ROUTER_URL"
  exit 0
fi

if [ "$(uname -s)" = "Darwin" ]; then
  LAUNCH_DIR="$HOME/Library/LaunchAgents"
  PLIST="$LAUNCH_DIR/app.creditsoft.intranet-client.plist"
  mkdir -p "$LAUNCH_DIR"
  cat > "$PLIST" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key>
  <string>app.creditsoft.intranet-client</string>
  <key>ProgramArguments</key>
  <array>
    <string>/bin/bash</string>
    <string>$RUNNER</string>
  </array>
  <key>RunAtLoad</key>
  <true/>
  <key>KeepAlive</key>
  <true/>
  <key>StandardOutPath</key>
  <string>$CONFIG_DIR/intranet-client.log</string>
  <key>StandardErrorPath</key>
  <string>$CONFIG_DIR/intranet-client.err.log</string>
</dict>
</plist>
EOF
  launchctl bootout "gui/$(id -u)" "$PLIST" >/dev/null 2>&1 || true
  launchctl bootstrap "gui/$(id -u)" "$PLIST"
  launchctl kickstart -k "gui/$(id -u)/app.creditsoft.intranet-client"
elif command -v systemctl >/dev/null 2>&1; then
  SERVICE_DIR="$HOME/.config/systemd/user"
  SERVICE_PATH="$SERVICE_DIR/creditsoft-intranet-client.service"
  mkdir -p "$SERVICE_DIR"
  cat > "$SERVICE_PATH" <<EOF
[Unit]
Description=CreditSoft employee intranet client router

[Service]
ExecStart=/usr/bin/env bash $RUNNER
Restart=always
RestartSec=3

[Install]
WantedBy=default.target
EOF
  systemctl --user daemon-reload
  systemctl --user enable --now creditsoft-intranet-client.service
else
  echo "No supported user service manager was found."
  echo "Run manually: $RUNNER"
fi

echo "CreditSoft employee client installed."
echo "Open: $ROUTER_URL"
BASH;
    }

    protected function powershellInstaller(): string
    {
        return <<<'POWERSHELL'
param(
    [string]$ApiBase = "",
    [string]$Token = "",
    [string]$Listen = "",
    [Alias("ListenPort")]
    [int]$RequestedListenPort = 0,
    [switch]$NoService
)

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if ($env:CREDITSOFT_CLIENT_INSTALL_DIR) {
    $InstallDir = $env:CREDITSOFT_CLIENT_INSTALL_DIR
} elseif ($env:LOCALAPPDATA) {
    $InstallDir = Join-Path $env:LOCALAPPDATA "CreditSoft\IntranetClient"
} else {
    $InstallDir = Join-Path $HOME ".creditsoft/intranet-client"
}
$UserProfilePath = if ($env:USERPROFILE) { $env:USERPROFILE } else { $HOME }
$ConfigDir = Join-Path $UserProfilePath ".creditsoft"
$TokenFile = if ($env:CREDITSOFT_API_TOKEN_FILE) { $env:CREDITSOFT_API_TOKEN_FILE } else { Join-Path $ConfigDir "intranet-client-api-token" }
$ManifestPath = Join-Path $ScriptDir "manifest.json"
$PairingPath = Join-Path $ScriptDir "pairing-config.json"
$Runner = Join-Path $InstallDir "run-router.ps1"

if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    throw "Node.js 20 or newer is required for the CreditSoft employee client router."
}

$NodeMajor = [int](& node -p "Number(process.versions.node.split('.')[0])")
if ($NodeMajor -lt 20) {
    throw "Node.js 20 or newer is required. Found: $(& node --version)"
}

if (-not (Test-Path $ManifestPath)) {
    throw "manifest.json was not found next to install.ps1."
}

function Test-CreditSoftCanBindPort {
    param(
        [string]$HostName,
        [int]$Port
    )

    try {
        $Address = [System.Net.IPAddress]::Parse($HostName)
    } catch {
        try {
            $Address = [System.Net.Dns]::GetHostAddresses($HostName) |
                Where-Object { $_.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork } |
                Select-Object -First 1
        } catch {
            $Address = [System.Net.IPAddress]::Loopback
        }
    }

    if (-not $Address) {
        $Address = [System.Net.IPAddress]::Loopback
    }

    $Listener = $null
    try {
        $Listener = [System.Net.Sockets.TcpListener]::new($Address, $Port)
        $Listener.Start()
        return $true
    } catch {
        return $false
    } finally {
        if ($Listener) {
            $Listener.Stop()
        }
    }
}

function Select-CreditSoftListenPort {
    param(
        [string]$HostName,
        [int]$Requested,
        [int[]]$Candidates
    )

    if ($Requested -gt 0) {
        if (Test-CreditSoftCanBindPort -HostName $HostName -Port $Requested) {
            return $Requested
        }

        throw "Requested CreditSoft local router port $Requested is not available on $HostName."
    }

    foreach ($Port in $Candidates) {
        if (Test-CreditSoftCanBindPort -HostName $HostName -Port $Port) {
            return $Port
        }
    }

    throw "No CreditSoft local router port was available on $HostName."
}

function Get-CreditSoftRouterUrl {
    param(
        [string]$HostName,
        [int]$Port
    )

    if ($Port -eq 80) {
        return "http://$HostName/dashboard?source=intranet-client"
    }

    return "http://$HostName`:$Port/dashboard?source=intranet-client"
}

New-Item -ItemType Directory -Force -Path $InstallDir, (Join-Path $InstallDir "bin"), (Join-Path $InstallDir "examples"), $ConfigDir | Out-Null
Copy-Item (Join-Path $ScriptDir "client\README.md") (Join-Path $InstallDir "README.md") -Force
Copy-Item (Join-Path $ScriptDir "client\package.json") (Join-Path $InstallDir "package.json") -Force
Copy-Item (Join-Path $ScriptDir "client\bin\creditsoft-intranet-client.mjs") (Join-Path $InstallDir "bin\creditsoft-intranet-client.mjs") -Force
Copy-Item (Join-Path $ScriptDir "client\bin\creditsoft-loopback-router.mjs") (Join-Path $InstallDir "bin\creditsoft-loopback-router.mjs") -Force
Copy-Item (Join-Path $ScriptDir "client\examples\pairing-config.example.json") (Join-Path $InstallDir "examples\pairing-config.example.json") -Force
Copy-Item $ManifestPath (Join-Path $InstallDir "manifest.json") -Force
Copy-Item $PairingPath (Join-Path $InstallDir "pairing-config.json") -Force

$Manifest = Get-Content $ManifestPath -Raw | ConvertFrom-Json
$BaseList = @()
if ($Manifest.api.candidate_base_urls) {
    $BaseList += @($Manifest.api.candidate_base_urls)
}
if ($ApiBase) {
    $BaseList += $ApiBase.Split(",") | ForEach-Object { $_.Trim() } | Where-Object { $_ }
}
$ApiBases = ($BaseList | Where-Object { $_ } | Select-Object -Unique) -join ","
$ListenHost = if ($Listen) { $Listen } elseif ($Manifest.runtime.listen_host) { $Manifest.runtime.listen_host } else { "127.0.0.1" }
$PreferredListenPorts = if ($Manifest.runtime.preferred_listen_ports) { @($Manifest.runtime.preferred_listen_ports | ForEach-Object { [int]$_ }) } else { @(80,8877,8878,8879,8880,8881,8882,8883,8884,8885,8886,8887,8888,8889,8890,8891,8892,8893,8894,8895,8896,8897,8898,8899) }
$SelectedListenPort = Select-CreditSoftListenPort -HostName $ListenHost -Requested $RequestedListenPort -Candidates $PreferredListenPorts
$Strategy = if ($Manifest.runtime.selection_strategy) { $Manifest.runtime.selection_strategy } else { "resource-aware" }
$RouterUrl = Get-CreditSoftRouterUrl -HostName $ListenHost -Port $SelectedListenPort
$InstalledPairingPath = Join-Path $InstallDir "pairing-config.json"
$PairingConfig = Get-Content $InstalledPairingPath -Raw | ConvertFrom-Json
$PairingConfig.candidateBaseUrls = @($BaseList | Where-Object { $_ } | Select-Object -Unique)
$PairingConfig | ConvertTo-Json -Depth 5 | Set-Content -Path $InstalledPairingPath

$ResolvedToken = if ($Token) { $Token } elseif ($env:CREDITSOFT_API_TOKEN) { $env:CREDITSOFT_API_TOKEN } else { "" }
if ($ResolvedToken) {
    Set-Content -Path $TokenFile -Value $ResolvedToken -NoNewline
} elseif (-not (Test-Path $TokenFile)) {
    New-Item -ItemType File -Path $TokenFile -Force | Out-Null
}

@"
`$TokenValue = if (Test-Path "$TokenFile") { (Get-Content "$TokenFile" -Raw).Trim() } else { "" }
if (`$TokenValue) {
    `$env:CREDITSOFT_API_TOKEN = `$TokenValue
} else {
    Remove-Item Env:CREDITSOFT_API_TOKEN -ErrorAction SilentlyContinue
}
& node "$InstallDir\bin\creditsoft-intranet-client.mjs" --serve --pair "$InstallDir\pairing-config.json" --listen "$ListenHost" --listen-port "$SelectedListenPort" --strategy "$Strategy" --no-open --no-prompt-token --save
"@ | Set-Content -Path $Runner

Write-Host "CreditSoft local router port selected: $SelectedListenPort on $ListenHost"
$PowerShellCommand = if ($IsWindows) { "powershell.exe" } else { "pwsh" }

if ($NoService) {
    Write-Host "CreditSoft employee client installed without a service."
    Write-Host "Run manually: $PowerShellCommand -NoProfile -ExecutionPolicy Bypass -File `"$Runner`""
    Write-Host "Open: $RouterUrl"
    exit 0
}

if (-not $IsWindows) {
    Write-Host "PowerShell service install is only automated on Windows."
    Write-Host "Run manually: $PowerShellCommand -NoProfile -ExecutionPolicy Bypass -File `"$Runner`""
    Write-Host "Open: $RouterUrl"
    exit 0
}

$TaskName = "CreditSoft Intranet Client"
$Action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$Runner`""
$Trigger = New-ScheduledTaskTrigger -AtLogOn
$Principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel LeastPrivilege
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Principal $Principal -Description "CreditSoft local employee router on $ListenHost`:$SelectedListenPort" -Force | Out-Null
Start-ScheduledTask -TaskName $TaskName

Write-Host "CreditSoft employee client installed."
Write-Host "Open: $RouterUrl"
POWERSHELL;
    }

    protected function addRequiredClientFile(ZipArchive $zip, string $relativePath, string $archivePath): void
    {
        $path = $this->sourcePath().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Missing intranet client file: %s', $relativePath));
        }

        $zip->addFile($path, $archivePath);
    }

    protected function publishToUpdateLane(string $archivePath): void
    {
        $downloadsDirectory = base_path('update.creditsoft.app/downloads');

        if (! is_dir(dirname($downloadsDirectory))) {
            return;
        }

        File::ensureDirectoryExists($downloadsDirectory);

        $publishedPath = $downloadsDirectory.DIRECTORY_SEPARATOR.$this->downloadName();

        if (! is_file($publishedPath) || filemtime($publishedPath) < filemtime($archivePath)) {
            File::copy($archivePath, $publishedPath);
        }
    }
}
