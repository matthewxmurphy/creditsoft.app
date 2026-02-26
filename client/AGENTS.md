# CreditSoft Client - AI Agent Instructions

## Project Overview

CreditSoft Client is the client-facing portal for credit repair software. Built on CodeIgniter 4.4 with Metro2 compliance at its core.

## Tech Stack

- **Framework**: CodeIgniter 4.4
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
3. Config saved to `credit_config.php`
4. Login: `admin@credit.com` / `admin123`

## Code Style

- PHP 8.1+
- PSR-2-ish formatting
- CodeIgniter conventions

## Database

Tables in `credit_system.sql`:
- users, clients, credit_reports, report_accounts
- error_types (Metro2 codes), account_errors
- disputes, dispute_templates
- drip_campaigns, drip_queue

## Key Conventions

- Config goes in `credit_config.php` (one level up from web root)
- Controllers go in `app/Controllers/`
- Models go in `app/Models/`
- Views go in `app/Views/`
- Services go in `app/Services/`
