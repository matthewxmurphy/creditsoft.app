# CreditSoft workspace map

This repository contains the private office application and the public WordPress
theme. Use its tracked source and actual deployed products as the authority;
avoid detached Desktop/iCloud copies. The established local development checkout
is `/Users/mmurphy/Code/CreditSoft`; a Desktop shortcut should be a symlink to it.

## Public CreditSoft.app website

- `wordpress/creditsoft-helium/`: dedicated Gantry 6 child theme, native particles,
  preserved design assets and initial content snapshots.
- `wordpress/creditsoft-financial-tools/`: maintained browser calculator source,
  WordPress shortcode/block adapter and functional tests.
- `wordpress/migration/`: backup, explicit page import/adoption, service linking,
  verification and rollback tools.
- Live: https://creditsoft.app; pricing: https://creditsoft.app/pricing-plan/.

Native WordPress Pages/revisions own published content. Gantry saved outlines own
shared layout settings. The public static-site source/build/deploy pipeline is
retired. Do not copy the whole repository, `web/`, or the intranet Vite build over
the WordPress public root.

## Private office and services

- Repository root: Laravel/Vue intranet, browser companion, local installer, API
  and client workflows.
- `creditsoft/`: YAML runtime configuration.
- `web/`: retained PHP backend/admin, account, licensing, payment and support
  sources. These are separate services, not a public website payload.
- `web-meta/`: admin-subdomain support and private operator bridge examples.
- `updates-astro/` and `update.creditsoft.app/`: separate update-service sources;
  the website conversion does not retire the update service.

The panel-managed website root is
`/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/public_html` on
`assets101.aietherpanel.com` (AIetherPanel website 6). Existing scoped links keep
service routes available. Follow `wordpress/migration/README.md` for public site
changes and preserve private backups/customer data outside theme packages.

## Workspace checks

```sh
bash scripts/creditsoft-workspace-doctor.sh
```

The office `creditsoft:release` command retains its canonical Git/workspace guard.
Its release-feed mirrors now target retained backend/update-service sources,
without recreating the removed website build tree.
