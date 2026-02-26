# CreditSoft Demo - AI Agent Instructions

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
| `index.php` | Entry point |
| `app.php` | CodeIgniter bootstrap |
| `installer/` | Setup wizard |
| `credit_system.sql` | Database schema |

## Setup Commands

1. Upload ZIP to server
2. Run installer at `/installer/`
3. Config saved to `credit_config.php` (outside web root)
4. Login: `admin@credit.com` / `admin123`

## Routing

- `/` → index.php → CodeIgniter app
- `/dashboard` → Dashboard
- `/login` → Auth login
- `/clients` → Client management
- `/reports` → Credit reports

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

1. Test installer functionality
2. Verify client management
3. Test credit report parsing

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
