# CreditSoft - AGENTS.md

**IMPORTANT**: This project's working directory is `/Volumes/MacHome/Users/mmurphy/Websites/CreditSoft`

## Project Overview

CreditSoft is a SaaS credit repair software competing with Credit Repair Cloud & Client Dispute Manager. Built on CodeIgniter 4.4 with Metro2 compliance at its core.

## Tech Stack

- **Framework**: CodeIgniter 4.4 (bundled in ZIP, no composer needed on server)
- **Database**: MySQL/MariaDB
- **PDF**: TCPDF
- **Frontend**: Vanilla HTML/CSS/JS

## Key Files

| File | Purpose |
|------|---------|
| `plan.md` | Full project roadmap |
| `soul.md` | Company values & communication standards |
| `index.php` | Landing page (creditsoft.app) |
| `app.php` | CodeIgniter entry point |
| `installer/` | Setup wizard |
| `credit_system.sql` | Database schema |
| `.htaccess` | Routing rules |

## Setup Commands

1. Upload ZIP to server
2. Run installer at `/installer/`
3. Config saved to `credit_config.php` (outside web root)
4. Login: `admin@credit.com` / `admin123`

## Routing

- `/` → index.php (landing page)
- `/dashboard`, `/admin`, `/api`, etc. → app.php (CodeIgniter)

## Code Style

- PHP 8.1+
- PSR-2-ish formatting
- CodeIgniter conventions
- No comments unless complex logic

## Database

Tables in `credit_system.sql`:
- users, clients, credit_reports, report_accounts
- error_types (Metro2 codes), account_errors
- disputes, dispute_templates
- drip_campaigns, drip_queue
- sops, knowledge_base
- tasks, activity_log, settings

## Current Priority

1. Fix installer permission issues
2. Test intranet functionality
3. Build storefront (Stripe, licensing)
4. Create .md knowledge base files for AI RAG

## Common Tasks

```bash
# Create new ZIP (exclude dev files)
zip -r credit_system.zip . -x "*.DS_Store" -x "*.git*"
```

## Key Conventions

- Config goes in `credit_config.php` (one level up from web root)
- Controllers go in `app/Controllers/`
- Models go in `app/Models/`
- Views go in `app/Views/`
- Services go in `app/Services/`

## Testing

- Check PHP error logs on server
- Installer shows system check on Step 1
- Debug mode enabled in app.php
