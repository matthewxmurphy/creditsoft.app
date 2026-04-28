#requires -Version 5.1

[CmdletBinding()]
param(
    [string]$OfficeName = "CreditSoft Office",
    [string[]]$ApiBase = @(),
    [string]$ApiToken = "",
    [string]$ApiTokenFile = "",
    [string]$TailscaleAuthKey = "",
    [string]$TailscaleHostname = "",
    [ValidateSet("fastest", "ordered")]
    [string]$Strategy = "fastest",
    [int]$RouterPort = 8877,
    [string]$DashboardPath = "/dashboard?source=intranet-client",
    [string]$InstallRoot = "",
    [switch]$InstallTailscale,
    [switch]$SkipTailscaleUp,
    [switch]$NoStartAtLogin,
    [switch]$StartNow,
    [switch]$Force
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)

    Write-Host ""
    Write-Host "== $Message =="
}

function Test-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)

    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Get-CommandPath {
    param([string]$Name)

    $command = Get-Command $Name -ErrorAction SilentlyContinue

    if ($command) {
        return $command.Source
    }

    return $null
}

function Refresh-ProcessPath {
    $machinePath = [Environment]::GetEnvironmentVariable("Path", "Machine")
    $userPath = [Environment]::GetEnvironmentVariable("Path", "User")
    $env:Path = @($machinePath, $userPath) -join ";"
}

function Invoke-WingetPackage {
    param(
        [string]$Mode,
        [string]$Id,
        [string]$Name
    )

    $winget = Get-CommandPath "winget"

    if (-not $winget) {
        throw "winget is not available. Install App Installer from Microsoft Store, then rerun this setup."
    }

    $arguments = @(
        $Mode,
        "--id", $Id,
        "--exact",
        "--source", "winget",
        "--accept-package-agreements",
        "--accept-source-agreements"
    )

    if ($Mode -in @("install", "upgrade")) {
        $arguments += "--silent"
    }

    Write-Host "$Name via winget: $Mode $Id"
    & $winget @arguments

    return $LASTEXITCODE
}

function Ensure-PowerShell {
    Write-Step "Checking PowerShell"

    $pwsh = Get-CommandPath "pwsh"

    if ($pwsh) {
        $versionText = & $pwsh -NoLogo -NoProfile -Command '$PSVersionTable.PSVersion.ToString()'
        Write-Host "PowerShell 7 is installed: $versionText"
    } else {
        Write-Host "PowerShell 7 was not found. Installing latest Microsoft.PowerShell."
        $exitCode = Invoke-WingetPackage -Mode "install" -Id "Microsoft.PowerShell" -Name "PowerShell"

        if ($exitCode -ne 0) {
            throw "PowerShell install failed with exit code $exitCode."
        }

        Refresh-ProcessPath
    }

    $upgradeExitCode = Invoke-WingetPackage -Mode "upgrade" -Id "Microsoft.PowerShell" -Name "PowerShell"

    if ($upgradeExitCode -eq 0) {
        Write-Host "PowerShell is current according to winget."
    } else {
        Write-Host "winget did not apply a PowerShell upgrade. This is normal when no newer package is available."
    }
}

function Ensure-Node {
    Write-Step "Checking Node.js"

    $node = Get-CommandPath "node"
    $needsInstall = $true

    if ($node) {
        $versionText = (& $node --version).Trim()
        $major = [int]($versionText.TrimStart("v").Split(".")[0])
        $needsInstall = $major -lt 20
        Write-Host "Node.js is installed: $versionText"
    }

    if ($needsInstall) {
        Write-Host "Installing or upgrading Node.js LTS."
        $installExitCode = Invoke-WingetPackage -Mode "install" -Id "OpenJS.NodeJS.LTS" -Name "Node.js LTS"

        if ($installExitCode -ne 0) {
            $upgradeExitCode = Invoke-WingetPackage -Mode "upgrade" -Id "OpenJS.NodeJS.LTS" -Name "Node.js LTS"

            if ($upgradeExitCode -ne 0) {
                throw "Node.js LTS install/upgrade failed."
            }
        }

        Refresh-ProcessPath
    }
}

function Resolve-TailscalePath {
    $tailscale = Get-CommandPath "tailscale"

    if ($tailscale) {
        return $tailscale
    }

    $programFiles = @(
        ${env:ProgramFiles},
        ${env:ProgramFiles(x86)}
    ) | Where-Object { $_ }

    foreach ($root in $programFiles) {
        $candidate = Join-Path $root "Tailscale\tailscale.exe"

        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

function Ensure-Tailscale {
    Write-Step "Checking Tailscale"

    $tailscale = Resolve-TailscalePath

    if (-not $tailscale -and $InstallTailscale) {
        Write-Host "Tailscale was not found. Installing latest Tailscale."
        $installExitCode = Invoke-WingetPackage -Mode "install" -Id "Tailscale.Tailscale" -Name "Tailscale"

        if ($installExitCode -ne 0) {
            throw "Tailscale install failed with exit code $installExitCode."
        }

        Refresh-ProcessPath
        $tailscale = Resolve-TailscalePath
    }

    if (-not $tailscale) {
        Write-Host "Tailscale is not installed. Rerun with -InstallTailscale or install it manually."
        return
    }

    Write-Host "Tailscale CLI: $tailscale"

    if ($SkipTailscaleUp) {
        return
    }

    if ($TailscaleAuthKey -eq "") {
        Write-Host "No Tailscale auth key was supplied. Sign in manually or rerun with -TailscaleAuthKey."
        return
    }

    if (-not (Test-Admin)) {
        Write-Warning "Tailscale enrollment usually needs an elevated PowerShell session. If enrollment fails, rerun this script as Administrator."
    }

    $hostname = if ($TailscaleHostname -ne "") { $TailscaleHostname } else { "creditsoft-$env:COMPUTERNAME".ToLowerInvariant() }
    $tailscaleArgs = @(
        "up",
        "--auth-key=$TailscaleAuthKey",
        "--hostname=$hostname",
        "--accept-routes"
    )

    Write-Host "Enrolling this Windows device in Tailscale as $hostname."
    & $tailscale @tailscaleArgs

    if ($LASTEXITCODE -ne 0) {
        throw "Tailscale enrollment failed with exit code $LASTEXITCODE."
    }
}

function Protect-UserFile {
    param([string]$Path)

    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $acl = Get-Acl $Path
    $acl.SetAccessRuleProtection($true, $false)
    $rule = [Security.AccessControl.FileSystemAccessRule]::new(
        $identity,
        "FullControl",
        "Allow"
    )
    $acl.SetAccessRule($rule)
    Set-Acl -Path $Path -AclObject $acl
}

function Write-JsonFile {
    param(
        [string]$Path,
        [object]$Payload
    )

    $Payload | ConvertTo-Json -Depth 8 | Set-Content -Path $Path -Encoding UTF8
}

function Install-CreditSoftClient {
    Write-Step "Installing CreditSoft intranet client"

    $scriptRoot = Split-Path -Parent $PSCommandPath

    if ($InstallRoot -eq "") {
        $InstallRoot = Join-Path $env:LOCALAPPDATA "CreditSoft\IntranetClient"
    }

    New-Item -ItemType Directory -Force -Path $InstallRoot | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $InstallRoot "bin") | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $InstallRoot "secrets") | Out-Null

    Copy-Item -Path (Join-Path $scriptRoot "package.json") -Destination (Join-Path $InstallRoot "package.json") -Force
    Copy-Item -Path (Join-Path $scriptRoot "README.md") -Destination (Join-Path $InstallRoot "README.md") -Force
    Copy-Item -Path (Join-Path $scriptRoot "bin\*.mjs") -Destination (Join-Path $InstallRoot "bin") -Force

    $candidateBases = @($ApiBase | Where-Object { $_ -and $_.Trim() -ne "" })

    if ($candidateBases.Count -eq 0) {
        Write-Warning "No API base URLs were supplied. The client will try localhost first, which is usually wrong for a remote Windows workstation."
    }

    $configDir = Join-Path $env:USERPROFILE ".creditsoft"
    New-Item -ItemType Directory -Force -Path $configDir | Out-Null

    $configPath = Join-Path $configDir "intranet-client.json"
    $configPayload = [ordered]@{
        officeName = $OfficeName
        candidateBaseUrls = $candidateBases
        dashboardPath = $DashboardPath
        selectionStrategy = $Strategy
        installedBy = [Security.Principal.WindowsIdentity]::GetCurrent().Name
        installedAt = (Get-Date).ToUniversalTime().ToString("o")
    }

    Write-JsonFile -Path $configPath -Payload $configPayload

    $tokenPath = Join-Path $InstallRoot "secrets\creditsoft-api-token.txt"

    if ($ApiToken -ne "") {
        Set-Content -Path $tokenPath -Value $ApiToken -NoNewline -Encoding UTF8
        Protect-UserFile -Path $tokenPath
    } elseif ($ApiTokenFile -ne "") {
        Copy-Item -Path $ApiTokenFile -Destination $tokenPath -Force
        Protect-UserFile -Path $tokenPath
    }

    $clientScript = Join-Path $InstallRoot "bin\creditsoft-intranet-client.mjs"
    $runnerPath = Join-Path $InstallRoot "Start-CreditSoftIntranetClient.ps1"
    $baseArgs = ($candidateBases | ForEach-Object { "--base `"$($_)`"" }) -join " "
    $tokenLine = if (Test-Path $tokenPath) { "`$env:CREDITSOFT_API_TOKEN_FILE = `"$tokenPath`"" } else { "Remove-Item Env:CREDITSOFT_API_TOKEN_FILE -ErrorAction SilentlyContinue" }

    $runner = @"
`$ErrorActionPreference = "Stop"
$tokenLine
`$node = Get-Command node -ErrorAction Stop
& `$node.Source "$clientScript" --serve --save --strategy "$Strategy" --listen 127.0.0.1 --listen-port "$RouterPort" $baseArgs --open
"@

    Set-Content -Path $runnerPath -Value $runner -Encoding UTF8

    return [ordered]@{
        installRoot = $InstallRoot
        configPath = $configPath
        tokenPath = if (Test-Path $tokenPath) { $tokenPath } else { $null }
        runnerPath = $runnerPath
        routerUrl = "http://127.0.0.1:$RouterPort$DashboardPath"
    }
}

function Register-ClientTask {
    param([string]$RunnerPath)

    if ($NoStartAtLogin) {
        Write-Host "Start-at-login registration skipped."
        return
    }

    Write-Step "Registering start-at-login task"

    $pwsh = Get-CommandPath "pwsh"

    if (-not $pwsh) {
        $pwsh = Join-Path $env:SystemRoot "System32\WindowsPowerShell\v1.0\powershell.exe"
    }

    $taskName = "CreditSoft Intranet Client Router"
    $argument = "-NoLogo -NoProfile -ExecutionPolicy Bypass -File `"$RunnerPath`""
    $action = New-ScheduledTaskAction -Execute $pwsh -Argument $argument
    $trigger = New-ScheduledTaskTrigger -AtLogOn
    $principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel LeastPrivilege
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)

    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
    Write-Host "Registered scheduled task: $taskName"
}

Ensure-PowerShell
Ensure-Node
Ensure-Tailscale

$install = Install-CreditSoftClient
Register-ClientTask -RunnerPath $install.runnerPath

if ($StartNow) {
    Write-Step "Starting CreditSoft intranet client"
    $powerShellHost = Get-CommandPath "pwsh"

    if (-not $powerShellHost) {
        $powerShellHost = Join-Path $env:SystemRoot "System32\WindowsPowerShell\v1.0\powershell.exe"
    }

    Start-Process -FilePath $powerShellHost -ArgumentList @(
        "-NoLogo",
        "-NoProfile",
        "-ExecutionPolicy",
        "Bypass",
        "-File",
        $install.runnerPath
    ) | Out-Null
}

Write-Step "Done"
Write-Host "Installed to: $($install.installRoot)"
Write-Host "Client config: $($install.configPath)"
Write-Host "Router URL: $($install.routerUrl)"

if ($install.tokenPath) {
    Write-Host "API token file: $($install.tokenPath)"
}

Write-Host ""
Write-Host "Open the router URL in Edge or Chrome, then use the browser menu to install it as an app/PWA."
