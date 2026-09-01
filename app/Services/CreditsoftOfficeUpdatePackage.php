<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class CreditsoftOfficeUpdatePackage
{
    /**
     * @var array<int, string>|null
     */
    protected ?array $devVendorPrefixes = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $devComposerPackageNames = null;

    public function build(?string $version = null, ?string $build = null): array
    {
        $version = trim($version ?: $this->releaseVersion());
        $build = trim($build ?: $version);

        if ($version === '') {
            throw new RuntimeException('CreditSoft could not resolve an update package version.');
        }

        $packageName = sprintf('creditsoft-office-v%s', $version);
        $archiveDirectory = $this->archiveDirectory();
        $manifestDirectory = $archiveDirectory.DIRECTORY_SEPARATOR.$packageName;
        $archivePath = $archiveDirectory.DIRECTORY_SEPARATOR.$packageName.'.zip';
        $manifest = $this->manifest($version, $build);
        $readme = $this->readme($version, $build);

        File::ensureDirectoryExists($archiveDirectory);
        File::ensureDirectoryExists($manifestDirectory);

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build the office update package.');
        }

        $this->dumpComposerAutoload(noDev: true);

        $zip = new ZipArchive;
        $zipOpen = false;

        try {
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('CreditSoft could not create the office update archive.');
            }

            $zipOpen = true;
            $zip->addFromString($packageName.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->addFromString($packageName.'/README.txt', $readme);

            $composerMetadata = $this->sanitizedComposerMetadata();

            foreach ($this->sourceFiles() as $relativePath => $absolutePath) {
                if (array_key_exists($relativePath, $composerMetadata)) {
                    continue;
                }

                $zip->addFile($absolutePath, $packageName.DIRECTORY_SEPARATOR.$relativePath);
            }

            foreach ($composerMetadata as $relativePath => $contents) {
                $zip->addFromString($packageName.DIRECTORY_SEPARATOR.$relativePath, $contents);
            }

            $zip->close();
            $zipOpen = false;
        } finally {
            if ($zipOpen) {
                $zip->close();
            }

            $this->dumpComposerAutoload(noDev: false);
        }

        File::put($manifestDirectory.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($manifestDirectory.DIRECTORY_SEPARATOR.'README.txt', $readme);

        return [
            'package_name' => $packageName,
            'archive_path' => $archivePath,
            'manifest_directory' => $manifestDirectory,
            'version' => $version,
            'build' => $build,
        ];
    }

    public function archiveDirectory(): string
    {
        return base_path('update.creditsoft.app/downloads');
    }

    protected function releaseVersion(): string
    {
        $path = base_path('update.creditsoft.app/data/update-feed.json');

        if (! File::exists($path)) {
            return '2026.4.27.1';
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) && filled((string) ($decoded['latest_version'] ?? ''))
            ? trim((string) $decoded['latest_version'])
            : '2026.4.27.1';
    }

    protected function dumpComposerAutoload(bool $noDev): void
    {
        $composerJson = base_path('composer.json');

        if (! File::exists($composerJson)) {
            return;
        }

        $this->clearBootstrapDiscoveryCache();

        $process = new Process([
            'composer',
            'dump-autoload',
            $noDev ? '--no-dev' : '--dev',
            '--optimize',
            '--no-interaction',
            '--no-scripts',
        ], base_path());
        $process->setTimeout(900);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim(
                'CreditSoft could not prepare the Composer autoloader for the office package. '.
                $process->getErrorOutput().' '.$process->getOutput()
            ));
        }

        if (! $noDev) {
            $this->discoverPackages();
        }
    }

    protected function clearBootstrapDiscoveryCache(): void
    {
        foreach (['packages*.php', 'services*.php'] as $pattern) {
            foreach (glob(base_path('bootstrap/cache/'.$pattern)) ?: [] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    protected function discoverPackages(): void
    {
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'package:discover',
            '--ansi',
        ], base_path());
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim(
                'CreditSoft could not restore Laravel package discovery after building the office package. '.
                $process->getErrorOutput().' '.$process->getOutput()
            ));
        }
    }

    /**
     * @return array<string, string>
     */
    protected function sourceFiles(): array
    {
        $files = [];

        foreach ($this->includePaths() as $relativePath) {
            $absolutePath = base_path($relativePath);

            if (! File::exists($absolutePath)) {
                continue;
            }

            if (is_file($absolutePath)) {
                if (! $this->shouldSkip($relativePath)) {
                    $files[$relativePath] = $absolutePath;
                }

                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolutePath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $fileRelativePath = ltrim(str_replace(base_path(), '', $file->getPathname()), DIRECTORY_SEPARATOR);

                if ($this->shouldSkip($fileRelativePath)) {
                    continue;
                }

                $files[$fileRelativePath] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    protected function includePaths(): array
    {
        return [
            '.dockerignore',
            '.env.docker.example',
            'app',
            'artisan',
            'bootstrap',
            'browser-extension',
            'composer.json',
            'composer.lock',
            'config',
            'creditsoft',
            'database',
            'docker',
            'docker-compose.yml',
            'Dockerfile',
            'docs',
            'intranet-client',
            'package-lock.json',
            'package.json',
            'public',
            'README.md',
            'resources',
            'routes',
            'tsconfig.json',
            'vendor',
            'vite.config.ts',
        ];
    }

    protected function shouldSkip(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));

        if ($normalized === '.env') {
            return true;
        }

        foreach ([
            'bootstrap/cache/',
            'database/database.sqlite',
            'database/testing.sqlite',
            'node_modules/',
            'public/hot',
            'storage/',
            'test-results/',
            ...$this->devVendorPrefixes(),
        ] as $prefix) {
            $normalizedPrefix = str_replace('\\', '/', $prefix);

            if ($normalized === rtrim($normalizedPrefix, '/')
                || str_starts_with($normalized, $normalizedPrefix)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function devVendorPrefixes(): array
    {
        if (is_array($this->devVendorPrefixes)) {
            return $this->devVendorPrefixes;
        }

        $path = base_path('composer.lock');

        if (! File::exists($path)) {
            return $this->devVendorPrefixes = [];
        }

        $lock = json_decode((string) File::get($path), true);

        if (! is_array($lock)) {
            return $this->devVendorPrefixes = [];
        }

        return $this->devVendorPrefixes = collect($lock['packages-dev'] ?? [])
            ->map(fn (array $package): string => 'vendor/'.trim((string) ($package['name'] ?? ''), '/').'/')
            ->filter(fn (string $prefix): bool => $prefix !== 'vendor//')
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function sanitizedComposerMetadata(): array
    {
        $metadata = [];

        foreach ([
            'vendor/composer/installed.json' => 'sanitizeInstalledJson',
            'vendor/composer/installed.php' => 'sanitizeInstalledPhp',
        ] as $relativePath => $method) {
            $path = base_path($relativePath);

            if (! File::exists($path)) {
                continue;
            }

            $metadata[$relativePath] = $this->{$method}((string) File::get($path));
        }

        return $metadata;
    }

    protected function sanitizeInstalledJson(string $contents): string
    {
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return $contents;
        }

        $devPackages = array_flip($this->devComposerPackageNames());
        $packagesKey = array_key_exists('packages', $decoded) ? 'packages' : null;
        $packages = $packagesKey !== null ? $decoded[$packagesKey] : $decoded;

        if (! is_array($packages)) {
            return $contents;
        }

        $packages = array_values(array_filter($packages, function ($package) use ($devPackages): bool {
            if (! is_array($package)) {
                return false;
            }

            $name = (string) ($package['name'] ?? '');

            return $name === '' || ! isset($devPackages[$name]);
        }));

        if ($packagesKey !== null) {
            $decoded[$packagesKey] = $packages;
            $decoded['dev'] = false;
            $decoded['dev-package-names'] = [];
        } else {
            $decoded = $packages;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    protected function sanitizeInstalledPhp(string $contents): string
    {
        $path = base_path('vendor/composer/installed.php');
        $installed = is_file($path) ? require $path : null;

        if (! is_array($installed)) {
            return $contents;
        }

        $devPackages = array_flip($this->devComposerPackageNames());

        if (isset($installed['versions']) && is_array($installed['versions'])) {
            $installed['versions'] = array_filter($installed['versions'], function ($package, string $name) use ($devPackages): bool {
                if (isset($devPackages[$name])) {
                    return false;
                }

                return ! (is_array($package) && (bool) ($package['dev_requirement'] ?? false));
            }, ARRAY_FILTER_USE_BOTH);
        }

        $installed['dev-package-names'] = [];

        if (isset($installed['root']) && is_array($installed['root'])) {
            $installed['root']['dev'] = false;
        }

        return '<?php return '.var_export($installed, true).';'.PHP_EOL;
    }

    /**
     * @return array<int, string>
     */
    protected function devComposerPackageNames(): array
    {
        if (is_array($this->devComposerPackageNames)) {
            return $this->devComposerPackageNames;
        }

        $path = base_path('composer.lock');

        if (! File::exists($path)) {
            return $this->devComposerPackageNames = [];
        }

        $lock = json_decode((string) File::get($path), true);

        if (! is_array($lock)) {
            return $this->devComposerPackageNames = [];
        }

        return $this->devComposerPackageNames = collect($lock['packages-dev'] ?? [])
            ->map(fn (array $package): string => trim((string) ($package['name'] ?? '')))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function manifest(string $version, string $build): array
    {
        return [
            'product' => 'CreditSoft Intranet',
            'version' => $version,
            'build' => $build,
            'channel' => 'stable',
            'published_at' => now()->toIso8601String(),
            'notes' => [
                'CreditSoft '.$version.' forwards imported DisputeFox admin invoices and profile Billing rows to the public CreditSoft license intelligence API under the saved office license code.',
                'CreditSoft '.$version.' keeps public billing intelligence out of the browser extension secret path: the companion posts locally, then the office server queues and retries the public share if www.creditsoft.app is unreachable.',
                'CreditSoft '.$version.' strips password, SSN, token, secret, and security-answer fields before storing billing intelligence for old-platform spend analysis.',
                'CreditSoft '.$version.' keeps the browser companion provider queue from processing profile-only leads, terminated relationships, blocked credentials, or SmartCredit reactivation records.',
                'CreditSoft '.$version.' reclassifies DisputeFox profile captures that still show Lead Status as leads, even when the profile was opened directly instead of imported from the Leads list.',
                'CreditSoft '.$version.' blocks provider accounts with invalid-credential or reactivation metadata until staff saves updated login details.',
                'CreditSoft '.$version.' mirrors client document file payloads through cluster database sync so server nodes can restore real uploaded files instead of only metadata rows.',
                'CreditSoft '.$version.' adds a Progress & Credit Reports lane on client profiles so audits, progress reports, and credit reports stay together for the next work cycle.',
                'CreditSoft '.$version.' keeps general Client Documents limited to supporting files instead of mixing them with audit, progress, and credit-report artifacts.',
                'CreditSoft '.$version.' moves report-like browser captures out of Recent import records and into Progress & Credit Reports, so report artifacts are visible in one lane only.',
                'CreditSoft '.$version.' keeps the normal companion report queue limited to active/runnable clients unless staff explicitly starts a recovery sweep.',
                'CreditSoft '.$version.' adds a companion Recovery sweep button for 90+ day terminated, canceled, graduated, or payment-reactivation candidates while keeping fired clients and invalid credentials excluded.',
                'CreditSoft '.$version.' bases recovery-sweep age on real activity such as last successful report import, imported payment history, billing last-paid date, or relationship end date instead of today\'s database import timestamp.',
                'CreditSoft '.$version.' keeps fresh SmartCredit reactivation failures out of the recovery sweep until they age past the comeback window, so a stopped run cannot restart the same failed accounts immediately.',
                'CreditSoft '.$version.' ships the browser companion plugin with the same date-format version as the office release.',
                'CreditSoft '.$version.' keeps companion processing focused by hiding extra buttons during active report pulls and DisputeFox imports.',
                'CreditSoft '.$version.' makes DisputeFox migration explicitly browser-session based: log in manually, then import the current page, lists, profile history, billing, and files.',
                'CreditSoft '.$version.' keeps Import client profiles on client/profile pages and stops that pass from opening the admin billing_report.jsp invoice report automatically.',
                'CreditSoft '.$version.' labels the invoice lane as Admin invoices so client Billing/profile history stays distinct from admin invoice reports.',
                'CreditSoft '.$version.' keeps companion profile-history preservation for Billing, Messages, Scores, Tasks, Letters, Disputes, and related migration pages.',
                'CreditSoft '.$version.' normalizes DisputeFox profile Billing rows into client payment history while also staging the raw rows for migration audit review.',
                'CreditSoft '.$version.' keeps static freeze-address lookup tables out of migrated history so real client activity is not mixed with template data.',
                'CreditSoft '.$version.' forces update-feed and package downloads over IPv4 so Docker containers with broken IPv6 paths can still reach updates.creditsoft.app.',
                'CreditSoft '.$version.' updates the CTO page from the old card-heavy diagnostics screen into a cleaner server-node operations view with tighter spacing and smaller control surfaces.',
                'CreditSoft '.$version.' teaches CTO diagnostics to read macOS and Apple Silicon memory pressure correctly so an M4 Pro with healthy pressure and zero swap is treated as a valid secondary server node instead of a failing low-memory box.',
                'CreditSoft '.$version.' sends memory pressure, available memory, swapins, and swapouts through the cluster summary, router hints, and Nemotron advisor so client routing and recommendations use the same evidence.',
                'CreditSoft '.$version.' adds CTO advisor action buttons that can stage the memory-saver profile, save a healthy-node router preference, and record a RAM follow-up without pretending software can install hardware.',
                'CreditSoft '.$version.' makes the intranet client and loopback router resource-aware so new probes can prefer the healthiest configured office node instead of only taking the first or fastest response.',
                'CreditSoft '.$version.' makes PHP OPcache and PostgreSQL Docker memory settings environment-driven so M4, Ryzen, and future office servers can tune memory pressure safely after restart.',
                'CreditSoft '.$version.' calls OpenRouter Nemotron directly for CTO JSON recommendations so the free endpoint returns real advice instead of an empty structured wrapper payload.',
                'CreditSoft '.$version.' keeps the active client rolodex visible when the open profile is terminated or payment-blocked, without adding that profile back into active tabs.',
                'CreditSoft '.$version.' keeps terminated or payment-blocked profiles out of the active client rolodex, even when an old URL still carries view=clients.',
                'CreditSoft '.$version.' restores the client profile people-tab row so every client in the current view is visible instead of filtering the row to only the selected alphabet letter.',
                'CreditSoft '.$version.' lowers the profile rolodex stack another few pixels so the alphabet, people tabs, and First/Last tabs sit on the Client Health card edge instead of floating high above it.',
                'CreditSoft '.$version.' centers the client-profile rolodex alphabet, name tabs, and previous/next controls over the Client Health card instead of stretching them across the page.',
                'CreditSoft '.$version.' folds Current score, Accounts reviewed, Priority disputes, and Utilization targets into the Client Health card as four clean metric panels.',
                'CreditSoft '.$version.' moves the client-profile previous and next controls onto the name-tab row so the arrows travel with the people tabs instead of the alphabet index.',
                'CreditSoft '.$version.' adds visible per-letter counts to the client rolodex alphabet so every letter shows how many names it represents in the current view.',
                'CreditSoft '.$version.' refreshes the built intranet assets for the client roster and profile rolodex, keeping Ryzen, M4, and future update pulls on the same UI build.',
                'CreditSoft '.$version.' fixes the Office updates apply route so browser recovery after a restart lands back on the update page instead of a 405 Method Not Allowed screen.',
                'CreditSoft '.$version.' clears persistent Docker bootstrap cache files before Laravel boots so stale dev-only providers cannot brick an office node after an update.',
                'CreditSoft '.$version.' records the applied build in installer state instead of rewriting .env during the update request, preventing self-update restarts from interrupting the browser response.',
                'Adds the CreditSoft CRM launch bridge so the left-rail CRM item uses the active intranet session, creates the CRM workspace when needed, and opens the CRM with a short-lived token instead of asking for a second password.',
                'Stores only an encrypted CRM-only hidden credential for the sidecar handoff; the intranet password remains a one-way hash and is never reused or exposed.',
                'White-labels the CRM sidecar image during Docker builds so the welcome screen points legal links to creditsoft.app and uses CreditSoft branding assets.',
                'Removes the old Docker database-directory mount from the PostgreSQL office profile so new migration files are not hidden behind a stale SQLite-era volume.',
                'Reduces the main left rail and Meta/social rail icons to 18px so the rail lands closer to the intended compact size.',
                'Installs rsync into the Docker intranet runtime so self-updates can overlay office packages from inside the container.',
                'Adds a PHP overlay fallback for self-updates so the updater can still copy a package if a stripped-down runtime is missing rsync.',
                'Adds a one-week Browser companion trial: the puzzle rail download and companion API stay available during setup, then route to the license upgrade flow after the trial ends.',
                'Moves the successful browser companion download to the canonical updates.creditsoft.app package URL while keeping the local intranet license and 7-day trial gate in front of it.',
                'Normalizes DisputeFox Leads list rows into the Leads roster instead of only staging them, keeps lead/intake customers out of the active client lane, and preserves lead source metadata when a profile sync exposes Lead Status.',
                'Changes the local router failure path so normal browser visits get a maintenance/reconnect page and Inertia visits get a proper reload signal instead of a plain JSON 502 modal.',
                'Ships the stable browser companion with SmartCredit FusionAuth invalid-login detection: the real "Invalid login credentials." page is marked needs credentials, noted internally, queued for CRM email review, and skipped so the queue keeps moving.',
                'Ships the stable browser companion with report-first wording, explicit SmartCredit and IdentityIQ report-pull lanes, and a legacy import menu for DisputeFox plus upcoming Client Dispute Manager, Cloud Credit Repair, and white-label CRO sources.',
                'Ships the stable browser companion with local-router autodetection first, so browser installs try http://127.0.0.1:8877 before any direct app port.',
                'Ships the stable browser companion with SmartCredit reactivation detection: unpaid/inactive SmartCredit accounts are marked needs client payment, noted internally, assigned a staff follow-up task, and skipped so the queue keeps moving.',
                'Ships the stable browser companion with separate DisputeFox import buttons for all lists, Clients, Leads, Affiliates, Invoices, Automation, Profile details, and Current page.',
                'Renames the visible companion migration copy from Pulse to DisputeFox / legacy CRM language so the UI reads like the source product instead of internal plumbing.',
                'Captures opened DisputeFox invoice detail modals, including invoice number, client, totals, due date, notes, and line items, so clicking an Invoice ID can be imported from Current page.',
                'Adds a Client Process Checklist to client profiles with intake, assignment, portal, onboarding, billing, report import, dispute, and letter readiness steps.',
                'Reduces the main rail icons from the oversized pass to 26px and gives HR a yellow accent without adding another icon container.',
                'Ships the stable browser companion with named Pulse profile failures so retry lists show which clients or leads need attention.',
                'Makes Pulse profile migration sweep supporting Pulse lanes after profiles, including Invoices, Affiliates, and Automation.',
                'Points the Pulse invoice lane at billing_report.jsp and classifies billing_report / billing pages as invoice imports.',
                'Expands Pulse row-count selectors before importing supporting lanes so invoices and affiliates are less likely to be cut off by page size.',
                'Groups the main left rail into Workspace and Operations sections with a clean divider between daily work and management tools.',
                'Adds an expand/collapse control so the rail can switch between icon-only mode and labeled navigation.',
                'Enlarges the main rail icons and speeds up hover feedback so the rail feels easier to hit and less sluggish.',
                'Adds Chart.js HR activity visuals: a per-employee daily activity line chart, a 24-hour work-pattern line chart, and activity-window cards showing peak hour, usual span, active days, and last event.',
                'Adds HR and Payroll as first-class intranet lanes with left-rail icons, employee files, performance mix, reviews, write-ups, onboarding notes, saved pay methods, payroll ledger records, and Sendwave referral guidance.',
                'Ships the stable browser companion with Pulse profile processing raised to 500 visible profiles per run so a 341-lead list is not stopped at the old 120-profile safety cap.',
                'Raises Pulse list intake to 2,000 rows so full Leads lists such as 341 rows are not cut off at 250.',
                'Updates staged Pulse Leads and Affiliates captures in place instead of creating duplicate staged imports every time the companion is run.',
                'Stages Pulse Leads as lead import captures instead of creating client roster rows from the leads list.',
                'Separates the Clients page into Clients, Leads, and All views so imported Pulse leads do not make the active client roster look inflated.',
                'Pins Pulse Secret Key fields to IdentityIQ only, so SmartCredit, MyScoreIQ, and other provider rows do not inherit an IdentityIQ-only security value.',
                'Fixes Pulse / DisputeFox profile reimport so Account and Monitoring fields hidden inside tabs or modals can be captured intentionally instead of being dropped by the visible-field filter.',
                'Ships the stable browser companion with hidden Pulse DOB, SSN, monitoring agency, provider username, provider password, and secret-key capture.',
                'Maps Pulse monitoring-agency IDs into provider rows for IdentityIQ, SmartCredit, and MyScoreIQ, including CreditSmart wording when Pulse uses that label.',
                'Keeps the client roster provider-login signal compact with a simple Login column instead of bulky audit cards.',
                'Removes the intranet-wide floating bug-report/page-actions gear so pages no longer render dual upper-right gears.',
                'Removes the page feedback modal, event wiring, and /internal/page-feedback route from the intranet runtime.',
                'Keeps the real workspace action menus but removes their Submit bug report action.',
                'Adds /cto as a real main workspace page and redirects the old /settings/cto path to it.',
                'Adds a CTO icon to the main rail beside CFO and removes CTO from the settings sidebar.',
                'Makes the CFO Open violations metric and the footer Violations link open the global /violations review queue.',
                'Changes License / Office included access into three stacked rows instead of a wrapped two-column line.',
                'Changes included access labels to Included or Not included instead of Enabled or Off.',
                'Changes Office profile into three rows for Company, Admin Email, and Tailscale Host instead of crowding all three values across one row.',
                'Restores violations as a first-class left-rail item with a warning icon and a real /violations review queue.',
                'Redirects the old /ops shortcut to the violations queue so existing links and the 43-count badge reach the actual review work instead of Connectivity.',
                'Groups the License / Office updates changelog by CreditSoft version, with the latest version expanded by default and older versions tucked under their own disclosures.',
                'Adds the live z@creditsoft.app Zelle QR image to the intranet assets and uses it on the License / Office renewal card.',
                'Changes renewal payment copy so it no longer calls the Zelle QR a placeholder.',
                'Updates the public update-lane checkout and renewal pages to prefer the same live Zelle QR asset before falling back to generated QR text.',
                'Makes left-rail count badges 10% larger and uses one amber count style so the Tasks badge matches the rest of the intranet rail.',
                'Hides the Ops violation count while viewing settings pages such as Connectivity so the rail no longer shows an unexplained 43.',
                'Polishes task board action and status badges with the same larger rounded action language used across the app.',
                'Reshapes the CTO diagnostics page so the current node card uses one column with three ledger rows instead of three cramped mini cards.',
                'Gives Client storage footprint four full-width rows for biggest client, smallest client, average client, and estimated remaining capacity.',
                'Adds a Staff activity hardware tile showing the most active and quietest staff member from recent audit events.',
                'Changes Cluster view to a topology summary for mode, local office, online offices, and remote peers instead of repeating memory, disk, and network totals.',
                'Uses a shorter PostgreSQL server-version lookup so the runtime line no longer reads as PostgreSQL PostgreSQL.',
                'Flattens the License / Office updates page so update stats, license details, included access, office profile, and Zelle renewal fields read as clean ledger rows instead of boxes inside boxes.',
                'Shortens the license key helper text so it does not clip on mobile while keeping plain icon actions instead of pill buttons.',
                'Keeps the Zelle QR centered and moves the live license price, 10% Zelle discount, destination, memo, and total into quieter line items beneath it.',
                'Looks up current license and plan pricing for Zelle payment lanes and shows the 10% Zelle discount beside the QR code.',
                'Updates public checkout and renewal pages to show plan price, Zelle discount, and Zelle total under the centered QR code.',
                'Labels generated Zelle QR blocks as placeholders until the live Zelle QR is connected.',
                'Replaces the remaining pill-style update and license buttons with plain icon actions.',
                'Changes feature availability into compact dot labels instead of pill badges.',
                'Fixes the internal office package download route so already-current installs can download the staged package without a 500 error.',
                'Tightens the License / Office updates page with update actions at the top, a collapsed changelog disclosure, and status dots instead of filled pills.',
                'Centers the renewal QR code and moves the payment fields underneath it for cleaner screenshots and better mobile flow.',
                'Switches the active intranet cutover path to PostgreSQL with fixed migration ordering for Postgres-strict foreign keys.',
                'The SQLite-to-PostgreSQL copier now handles tables without id columns and collapses duplicate unique-key seed rows instead of blocking the migration.',
                'The Docker PostgreSQL service now exposes a configurable host port so the existing local intranet process can use Docker Postgres without starting the full web container.',
                'Ships the stable browser companion with Pulse provider credential sync so client profiles can attach SmartCredit, IdentityIQ, MyScoreIQ, and Credit Karma provider rows when Pulse exposes the saved login fields.',
                'Pulse profile matching now stores and checks source record IDs from both list pages and profile pages so imported people do not split into duplicate client records as easily.',
                'The companion Update action can now force a provider report pass from the button, while background queue checks still respect next-due timing.',
                'Ships the stable browser companion with the correct lane split: Import is for DisputeFox/Pulse CRM data, and Update is for fresh provider report pulls.',
                'Renames the main companion surface to Update / Provider reports so SmartCredit and IdentityIQ work reads like report updates instead of imports.',
                'Renames the Pulse surface to Import / DisputeFox import, with Import profiles and Import page actions for legacy CRM data.',
                'Ships the stable browser companion with one clear client-processing lane for Pulse imports and provider report capture.',
                'Pulse / DisputeFox now uses the same primary Process action to open client and lead profiles, sync full details, and stage/download reports and documents where Pulse allows file access.',
                'The old Pulse import wording is now list-import wording, and opening the import menu no longer shows SmartCredit/IdentityIQ provider queue messages.',
                'Ships the stable browser companion with Pulse profile processing for imported Clients and Leads.',
                'Adds a Process profiles action that opens visible Pulse client/lead profile pages, syncs full profile details, and stages/uploads reports and documents from the logged-in Pulse session.',
                'DisputeFox document capture now includes credit reports instead of filtering them out before import.',
                'Pulse list capture now keeps up to 250 visible rows per list so profile processing can cover larger imported pages.',
                'Ships the stable browser companion with a compact scrollable side panel so long Pulse activity stays visible.',
                'Removes the large Office pairing card from the companion; the green status dot now carries API key state and the gear opens settings.',
                'Shortens duplicate client-ready instructions and reduces the Process button size so the activity log gets more room.',
                'Ships the stable browser companion with a clearer Pulse import completion state so old Automation opening/reading breadcrumbs do not make a finished import look stuck.',
                'Pulse list import now ends with a single summary line showing the imported lanes and returns the companion to normal CreditSoft client processing.',
                'Ships the stable browser companion with an explicit Pulse source mode separate from normal SmartCredit and IdentityIQ client processing.',
                'Adds an Import lists button that walks Pulse Clients, Leads, Invoices, Affiliates, and Automation instead of stopping after the first visible list page.',
                'Adds a Done action so the companion shuts the Pulse source off and returns to normal CreditSoft client processing.',
                'Improves Pulse list-table detection for invoice, lead, affiliate, and datatable-shaped pages that do not expose the same table id every time.',
                'Ships the stable browser companion with Pulse client document detection, document metadata staging, and authenticated file upload attempts from the logged-in browser session.',
                'Adds a browser-companion client-document API endpoint so Pulse files can be upserted against the matched CreditSoft client instead of creating duplicates.',
                'Removes the visible tiny-dot credential affordance from the companion; Pulse credentials now live behind the CreditSoft logo/import menu.',
                'Companion sync status now reports document records and file uploads instead of repeating only the profile sync message.',
                'Ships the stable browser companion with real Pulse list importing for Leads, Clients, Affiliates, and Invoices instead of stopping after one opened profile.',
                'Pulse Leads and Clients tables now create or update CreditSoft client records directly from visible list rows.',
                'Pulse Invoices and Billing rows now create or update imported client payment records tied to the matched or stubbed client.',
                'Pulse Affiliates and other business lists are now staged as imported browser-companion list captures instead of being discarded after detection.',
                'Ships the stable browser companion with hardened Pulse profile capture so visible customer selectors win over hidden modal fields.',
                'Adds the creditsoft:database:migrate-postgres command to copy the current SQLite office data into a PostgreSQL office database when the node is ready.',
                'Adds a full Docker office profile so CreditSoft, queue, scheduler, PostgreSQL, local router, and the white-label CRM sidecar can be started together.',
                'Adds --office and -Office installer flags that turn on PostgreSQL, router, and CRM sidecar support in one generated installer path.',
                'Updates CTO storage diagnostics to show the real database engine, database location, and engine-specific size instead of assuming SQLite forever.',
                'Fixes the Profile settings workspace width so the personal API key and pairing panels no longer collapse into narrow slivers.',
                'Changes the intranet information rail item to a plain centered i icon so it no longer reads like a small badge.',
                'Moves the intranet information icon into the left rail as a full-size icon above the browser companion puzzle icon.',
                'Removes the badge-style overlay from the browser companion puzzle icon.',
                'Fixes footer-attached storage and connector popovers so they use a readable light card surface instead of dark cards.',
                'Keeps the footer popover arrow, border, text color, and grey rail language visually aligned.',
                'Adds an OPS automation discovery lane for reusable workflow intelligence captured by the browser companion.',
                'Adds sanitized Automation workflow/list detection to the browser companion without storing full automation-page DOM.',
                'Adds an owner-only OPS review panel for discovered automations with seen counts, source IDs, workflow metadata, and step/action summaries.',
                'Lets the companion resume processing after Pulse multiple simultaneous login prompts are cleared.',
                'Restores the intranet footer into a compact grey one-row status rail that links to the full Office updates page.',
                'Adds real Check for updates and Apply update controls to the License / Office updates page.',
                'Adds footer-based update detection with a staged self-update lane.',
                'Includes the Docker intranet node runtime, local router package, and generated installer support.',
                'Packages the intranet runtime, browser companion sources, vendor dependencies, and built assets.',
                'Keeps local storage, .env values, and local database data outside the package.',
                'Supports a white-label CRM sidecar that can pull CreditSoft-controlled image aliases through the update lane.',
            ],
        ];
    }

    protected function readme(string $version, string $build): string
    {
        return implode(PHP_EOL, [
            'CreditSoft Office Package',
            'Version: '.$version,
            'Build: '.$build,
            '',
            'This package is meant for the local intranet self-update lane.',
            'It overlays the application runtime while keeping local storage, .env values, and local database data in place.',
            'Version '.$version.' adds the public license billing-intelligence lane for DisputeFox admin invoices and profile Billing history, then keeps update downloads over IPv4, CTO operations diagnostics, resource-aware routing, memory tuning, client rolodex fixes, CRM launch bridge, PostgreSQL-safe Docker profile, lead normalization, local-router recovery, and browser companion package line.',
        ]);
    }
}
