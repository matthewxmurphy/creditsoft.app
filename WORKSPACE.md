# CreditSoft Workspace Map

This local folder is now the combined working workspace.

## Canonical Workspace Guard

- Canonical source path: `/Users/mmurphy/Code/CreditSoft`
- Desktop shortcut path: `/Users/mmurphy/Desktop/CreditSoft`
- The Desktop path must stay a symlink to the canonical source path. Do not work from copied Desktop folders, iCloud drift archives, or legacy quarantine folders.
- Before publishing a release or doing broad deploy work, run:

```bash
bash /Users/mmurphy/Code/CreditSoft/scripts/creditsoft-workspace-doctor.sh
```

- For a manual local safety snapshot, run:

```bash
bash /Users/mmurphy/Code/CreditSoft/scripts/creditsoft-workspace-doctor.sh snapshot
```

- The `creditsoft:release` command refuses to publish when `.git` is missing unless `CREDITSOFT_ALLOW_RELEASE_WITHOUT_GIT=true` is explicitly set. That guard exists to stop detached iCloud/Desktop copies from becoming update packages.

## Intranet

- Path: `/Users/mmurphy/Code/CreditSoft`
- Desktop shortcut: `/Users/mmurphy/Desktop/CreditSoft`
- Purpose: Laravel/Vue intranet app, browser companion, local installer, API, and client workflow system.

## Web

- Path: `/Users/mmurphy/Code/CreditSoft/web`
- Desktop shortcut: `/Users/mmurphy/Desktop/CreditSoft/web`
- Purpose: Public `creditsoft.app` website source copied locally so website edits can be made from the same workspace.

## Live Web Targets

- Primary site: `https://creditsoft.app`
- Primary pricing page: `https://creditsoft.app/pricing.php`
- WWW alias: `https://www.creditsoft.app`
- WWW pricing page: `https://www.creditsoft.app/pricing.php`
- License/API host: `https://api.creditsoft.app`

## Website Deploy Lane

- Local public website source: `/Users/mmurphy/Code/CreditSoft/web`
- AIetherPanel website ID: `6`
- Deploy host identity: `assets101.aietherpanel.com`
- Deploy SSH lane: `mmurphy@100.126.221.83`
- Remote public_html: `/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/public_html`
- Shared SSH key: `/Users/Shared/aiether/keys/m1_server_ed25519`
- Verified on `2026-04-10`: direct `scp` to `public_html` works
- Do not aim SCP at `/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/tmp`

## Web Meta

- Path: `/Users/mmurphy/Code/CreditSoft/web-meta`
- Purpose: Website deployment notes and supporting files pulled from the website project root.
- Includes:
  - `README.md`
  - `SFTP.toon`
  - `API.toon`
  - `SETUP.md`
  - `SETUP.toon`
  - `credit_config.php`

## Clients

- Path: `/Users/mmurphy/Code/CreditSoft/clients/credit-sense`
- Purpose: Local customer website copy for portal/API integration work against the CreditSoft intranet and ngrok lane.

## Notes

- The intranet app remains at the workspace root to avoid breaking the current Laravel paths.
- The public website now has a local working copy under `web/` instead of living only under `/Volumes/mmurphy/Websites/CreditSoft/www`.
