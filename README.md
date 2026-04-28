# Creditsoft.app Intranet Platform

Creditsoft is a local-first Laravel 13 intranet for credit repair teams. It replaces spreadsheet casework with a private client dossier, bureau snapshot intake, side-by-side report comparison, Metro 2 style violation tracking, AI-assisted briefs and letters, SOP execution, audit trails, and CFO reporting.

## Stack

- Laravel 13
- Vue 3 + Inertia + TypeScript
- Laravel Wayfinder
- Laravel AI and Laravel Boost
- Filament v5 at `/ops`
- Chart.js
- Spatie Permission and Spatie Backup
- YAML-driven runtime configuration in `/creditsoft`

## Core product areas

- Client dossiers with notes, briefs, letters, tasks, and audit history
- Immutable reporting cycles and bureau snapshots
- Tradeline normalization and three-bureau comparison grids
- Violation candidate tracking backed by YAML rule definitions
- Shareable case briefs with privacy-safe outbound signal staging
- CFO reporting with MRR trend lines and operational metrics
- Admin operations surface in Filament for future backup, template, and audit controls

## Local setup

The canonical local workspace is `/Users/mmurphy/Code/CreditSoft`. `/Users/mmurphy/Desktop/CreditSoft` should only be a symlink to that folder. Run the workspace doctor before release/deploy work:

```bash
bash scripts/creditsoft-workspace-doctor.sh
```

```bash
composer install
npm install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Open the operator shell at `/dashboard` and the admin surface at `/ops`.

## Docker intranet node

CreditSoft can also run as a Dockerized intranet node for repeatable office installs:

```bash
cp .env.docker.example .env.docker
docker compose --env-file .env.docker build intranet
docker compose --env-file .env.docker up -d intranet queue scheduler
```

Set a stable `APP_KEY` in `.env.docker` before using this as a movable production office node. Open `http://127.0.0.1:8001` after the containers are healthy. See `docs/docker-intranet-node.md` for the router profile, persistent volumes, and CRM sidecar notes.

The generated node installer can also start CreditSoft on PostgreSQL with `bash install.sh --postgres` or `.\install.ps1 -Postgres`. That matches the CRM database engine while keeping CreditSoft and the CRM sidecar in separate databases/users.

## Seeded access

- Admin: `test@example.com` / `password`
- Staff: `staff@example.com` / `password`

## Important paths

- Runtime YAML config: `/Users/mmurphy/Code/CreditSoft/creditsoft`
- Public marketing site source: `/Users/mmurphy/Code/CreditSoft/web`
- Public pricing page source: `/Users/mmurphy/Code/CreditSoft/web/pricing.php`
- Website deploy handoff: `/Users/mmurphy/Code/CreditSoft/web-meta/SETUP.md`
- Website SCP/SFTP handoff: `/Users/mmurphy/Code/CreditSoft/web-meta/SFTP.toon`
- Operator shell layout: `/Users/mmurphy/Code/CreditSoft/resources/js/layouts/CreditsoftLayout.vue`
- Admin panel provider: `/Users/mmurphy/Code/CreditSoft/app/Providers/Filament/AdminPanelProvider.php`
- Comparison service: `/Users/mmurphy/Code/CreditSoft/app/Services/CreditReportComparisonService.php`
- Signal privacy filter: `/Users/mmurphy/Code/CreditSoft/app/Services/SignalSanitizer.php`

## Live website deploy

- Public site: `https://creditsoft.app`
- Public pricing page: `https://creditsoft.app/pricing.php`
- WWW pricing page: `https://www.creditsoft.app/pricing.php`
- API host: `https://api.creditsoft.app`
- AIetherPanel website ID: `6`
- Deploy host: `assets101.aietherpanel.com`
- SSH/SCP target: `mmurphy@100.126.221.83:/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/public_html`
- Shared deploy key: `/Users/Shared/aiether/keys/m1_server_ed25519`
- Verified on `2026-04-10`: direct `scp` to `public_html` works with the shared key
- Important: direct upload is for `public_html` only; the site runtime root and sibling `tmp` folder are not the writable SCP target

Preferred deploy:

```bash
cd /opt/aiether/aietherpanel && scripts/deploy-public-website.sh --via mmurphy@100.80.51.78 --target mmurphy@100.126.221.83 --source /Users/mmurphy/Code/CreditSoft/web --domain creditsoft.app
```

Direct SCP proof:

```bash
TMPFILE=$(mktemp /tmp/creditsoft-publichtml-check.XXXXXX)
echo 'creditsoft scp check' > "$TMPFILE"
scp -oBatchMode=yes -oStrictHostKeyChecking=accept-new -i /Users/Shared/aiether/keys/m1_server_ed25519 "$TMPFILE" mmurphy@100.126.221.83:/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/public_html/creditsoft-scp-check.txt
```

## Useful commands

```bash
php artisan migrate:fresh --seed
php artisan creditsoft:config:reload
php artisan route:list
npm run types:check
npm run build
php artisan test
php artisan backup:run
php artisan backup:monitor
```

## Privacy model

- Raw reports, client identity data, tradelines, and internal notes stay local.
- Only approved shareable case briefs and sanitized operational signals may be staged for outbound sync.
- `private_note` and `working_note` content should never leave the installation.

## Backups

Backup config is pre-wired for `local` and `wasabi` disks. Set the Wasabi credentials and `BACKUP_ARCHIVE_PASSWORD` in `.env` before enabling remote backup jobs in production.
