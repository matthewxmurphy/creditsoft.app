# Credit Error Identifier System - Project Plan

**IMPORTANT**: This project's working directory is `/Volumes/MacHome/Users/mmurphy/Websites/CreditSoft`

## Overview

SaaS credit repair software competing with Credit Repair Cloud & Client Dispute Manager.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     creditsoft.app                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐ │
│  │   Storefront │  │   Intranet  │  │   Update Server         │ │
│  │  - Stripe    │  │  - AI+RAG   │  │  - License validation   │ │
│  │  - Payments  │  │  - Clients  │  │  - Push updates         │ │
│  │  - Signup    │  │  - Reports  │  │  - Customer mgmt        │ │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
   │   Demo      │    │   Ashley    │    │  Customer   │
   │   (demo.)   │    │  (test)    │    │   Sites     │
   │ - 30min    │    │ - Testing  │    │ - Theme     │
   │   reset    │    │             │    │ - Widget    │
   │ - Simulate │    │             │    │ - API fetch│
   │   monthly  │    │             │    │   (no PII) │
   └─────────────┘    └─────────────┘    └─────────────┘
```

## Domains

| Domain | Purpose |
|--------|---------|
| **creditsoft.app** | Main SaaS - storefront + intranet + license server |
| **update.creditsoft.app** | Update server for customer sites |
| **demo.creditsoft.app** | Live demo with auto-reset |
| **ashley.matthewxmurphy.com** | Test playground |
| **matthewxmurphy.com** | Your portfolio (linked from all customer sites) |
| **net30hosting.com** | Hosting service (funnel from creditsoft) |

## The Funnel

```
creditsoft.app (knowledge + tools)
         ↓
   Trust through transparency (50-state costs)
         ↓
   Software sales + hosting customers
         ↓
net30hosting.com (high-value clients who pay)
```

**Why Net30 Hosting clients are motivated:**
- You report to business bureaus
- They know you'll cancel if they don't pay
- Credit repair + hosting = natural fit

## Security Model

### Customer Websites (Internet)
- Theme-based CodeIgniter
- **NO PII stored locally**
- "Client Portal" button fetches JSON via API
- Only non-sensitive data (score summary, messages)
- Widget embedded on customer's public site

### Intranet (creditsoft.app/intranet or ashley)
- Full credit repair system
- All sensitive PII stored here
- AI with RAG using .md knowledge base
- Onboarding: data emailed or token-secured (not stored long-term)
- When customer updates → SFTP push JSON to their website

## Features

### Core (Intranet)
- [ ] Client/Customer Management
- [ ] Credit Report Tracking (Metro2 compliant)
- [ ] Monthly Report Comparisons
- [ ] Metro2 Error Identifier (30+ error codes)
- [ ] Dispute Letter Generation (PDF)
- [ ] AI-powered (RAG from .md files)
- [ ] Email Drip Campaigns
- [ ] Staff Task Management
- [ ] 50-State Regulations Knowledge Base
- [ ] Client Portal API
- [ ] Embeddable Widget (JS)

### Storefront
- [ ] Pricing page (透明定价 - show real costs)
- [ ] Stripe integration
- [ ] License key generation
- [ ] Signup/Onboarding
- [ ] **50-State CRO Rules PUBLIC** - Show real costs/requirements per state
- [ ] "Footer branding" on all customer sites:
  - "Designed by Matthew Murphy" → links to matthewxmurphy.com
  - "Hosted by Net30 Hosting" → links to net30hosting.com

### Public Knowledge Base (SEO & Trust)
The 50-state rules should be PUBLIC on the frontend - this is a differentiator:
- Show real costs to start in each state (not hidden like CRC)
- CRO registration requirements
- Bond amounts
- Fee limits
- This builds trust and attracts serious prospects

### Funnel
```
creditsoft.app (knowledge + pricing) 
    → Trust built through transparency
    → Some become Net30 Hosting customers
    → Net30 reports to business bureaus = motivated payers
```

### Demo Site
- [ ] Auto-delete data every 30 minutes (cron job)
- [ ] Simulate monthly credit report imports
- [ ] Show score progression (Month 1: 580 → Month 2: 620 → Month 3: 680)
- [ ] Reset to fresh demo state on cron run

### Demo Site (demo.creditsoft.app)
Same as intranet, but with automated reset:
```
Cron: */30 * * * * /path/to/reset-demo.sh
```
Reset script:
1. Delete all clients/disputes/reports
2. Insert demo client with fake credit report (score: 580)
3. Schedule simulated "monthly" improvements
4. Auto-progress scores each cycle

### Updates
- [ ] License validation API
- [ ] Update server (like WordPress plugins)
  - Customers can enable auto-updates OR
  - Manual download & install button
- [ ] License key required for updates
- [ ] Pirate blocking (base64 encoded core files - later)

## AI Knowledge Base (.md files)

Location: `app/Knowledge/`

```
app/Knowledge/
├── sops/
│   ├── credit-basics.md
│   ├── dispute-workflow.md
│   ├── client-onboarding.md
│   └── ...
├── regulations/
│   ├── 50-state-cro.md
│   ├── state-rules/
│   │   ├── AL.md
│   │   ├── AK.md
│   │   └── ...
│   └── federal-fcra.md
├── errors/
│   ├── metro2-codes.md
│   └── common-errors.md
├── templates/
│   ├── dispute-letters/
│   └── ...
└── api/
    └── endpoints.md
```

## Database Schema

Tables already created in `credit_system.sql`:
- users, clients, credit_reports, report_accounts
- error_types (Metro2 codes), account_errors
- comparisons, client_notes
- dispute_templates, disputes
- drip_campaigns, drip_queue
- sops, ai_interactions
- knowledge_base, tasks
- activity_log, settings

## Competitive Analysis

| Feature | Credit Repair Cloud | Client Dispute Manager | **CreditSoft** |
|---------|-------------------|----------------------|----------------|
| Pricing | $99-299/mo | $107-329/mo | TBD |
| Metro2 | Basic | Full workflows | **First-class** |
| Error Detection | Manual | Some | **30+ codes built-in** |
| State Rules | External | Add-on | **Built-in 50-state** |
| AI Positioning | Feature | Feature | **Compliance-first** |
| Security Model | Cloud only | Cloud | **Intranet-first** |

**Why We Win:**
- Metro2 is the METHOD, not a feature
- "AI letters" = templates with different words = no better results
- Real disputes work because of Metro2 compliance, not letter variations
- Our AI knows the rules, not just grammar

## Current Status

### Working On
- [x] Installer with system check
- [x] CreditSoft landing page (index.php)
- [x] Feature comparison table
- [x] Turnstile + email validation on forms
- [x] soul.md - company values
- [ ] Fix installer permission issues
- [ ] Test intranet functionality

### To Do
- [ ] Install on ashley.matthewxmurphy.com
- [ ] Build storefront (Stripe, licensing)
- [ ] Create .md knowledge base files
- [ ] Demo site with auto-reset
- [ ] Update server setup

## Quick Start (When Working on Project)

1. Upload ZIP to server
2. Run installer (checks permissions first)
3. Login at /dashboard
4. Default: admin@credit.com / admin123

## Notes

- CodeIgniter 4.4 (bundled in ZIP, no composer needed)
- TCPDF for PDF generation
- MySQLi/MariaDB
- Config outside web root (protected by .htaccess)

## Dependencies (Bundled)

- codeigniter4/framework
- tecnickcom/tcpdf
- myclabs/deep-copy
- nikic/php-parser
- theseer/tokenizer
