# Creditsoft.app Intranet Platform

Creditsoft is a local-first Laravel 13 intranet for credit repair teams. It replaces spreadsheet casework with a private client dossier, bureau snapshot intake, side-by-side report comparison, Metro 2 style violation tracking, AI-assisted briefs and letters, SOP execution, audit trails, and CFO reporting.

The public **CreditSoft.app** website runs on **WordPress and Gantry 6**. Its
dedicated theme is [`wordpress/creditsoft-helium`](wordpress/creditsoft-helium).
Native WordPress Pages and revisions own published content; Gantry particles
own the shared header and footer. Follow the [WordPress deployment and rollback
instructions](wordpress/migration/README.md) for website changes. The former
public static-site source and deployment pipeline have been retired.

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
- Public WordPress theme: `wordpress/creditsoft-helium`
- Calculator source and adapter: `wordpress/creditsoft-financial-tools`
- Website deployment and rollback: `wordpress/migration/README.md`
- Existing PHP service source: `web/` (not a website deployment payload)
- Separate update service: `updates-astro/` and `update.creditsoft.app/`
- Operator shell layout: `/Users/mmurphy/Code/CreditSoft/resources/js/layouts/CreditsoftLayout.vue`
- Admin panel provider: `/Users/mmurphy/Code/CreditSoft/app/Providers/Filament/AdminPanelProvider.php`
- Comparison service: `/Users/mmurphy/Code/CreditSoft/app/Services/CreditReportComparisonService.php`
- Signal privacy filter: `/Users/mmurphy/Code/CreditSoft/app/Services/SignalSanitizer.php`

## Live website maintenance

- Public site: `https://creditsoft.app`
- Public pricing page: `https://creditsoft.app/pricing-plan/`
- API host: `https://api.creditsoft.app`
- AIetherPanel website ID: `6`
- Deploy host: `assets101.aietherpanel.com`
- Panel-managed WordPress root: `/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/public_html`
- Website source and migration tools: `wordpress/`

Edit published page content in WordPress. Edit shared navigation/footer through
the CreditSoft Gantry particles and save the existing outline. Before replacing
a theme ZIP, back up its saved `custom/` outline directory and restore it after
the update. Test on staging, preserve a private rollback, and verify the actual
desktop/mobile pages before promotion. Never deploy `web/` or an intranet Vite
build over the WordPress public root. PHP account, licensing, checkout, API and
office services are maintained separately; preserve their service links.

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

## Security maintenance

See [unreleased security changes and rollout limits](docs/AUDIT-SECURITY-2026-10-16.md).
