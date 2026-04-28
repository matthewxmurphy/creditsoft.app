# Windows 11 Client Pilot

This is the temporary workstation setup for a credit repair operator who needs to use CreditSoft before the permanent office hardware arrives.

The Windows 11 machine is an employee client, not a server node. It runs a local loopback router/PWA on `127.0.0.1`, joins the temporary Tailscale lane, and talks to the real CreditSoft office nodes across the country.

## What Gets Installed

- Current Microsoft PowerShell 7 through `winget`.
- Node.js LTS when Node 20+ is not already available.
- Tailscale when requested.
- CreditSoft intranet client files under the current Windows user's local app data folder.
- A per-user scheduled task named `CreditSoft Intranet Client Router`.
- Per-user client config under `%USERPROFILE%\.creditsoft`.

It does not install Docker, PostgreSQL, SQLite server storage, CRM sidecar containers, cluster SSH keys, or office-node backup jobs.

## Before Calling The Client

Prepare these values first:

- A short-lived Tailscale auth key from the tailnet admin console.
- A hostname for the Windows client, such as `creditsoft-client-marilyn` or `creditsoft-client-office`.
- One staff API key from CreditSoft Profile settings for the person testing the system.
- One or more server API bases, usually Tailscale URLs or IPs:
  - `http://100.80.51.78:8001/api/v1` for the Ryzen node.
  - Add the M4 node's Tailscale API URL too when it should be part of the candidate pool.

Use an ephemeral or short-expiration Tailscale key for the pilot. Revoke it after the workstation is enrolled or after the test period ends.

## Run On Windows 11

Open PowerShell as Administrator for the first run if Tailscale needs to be installed or enrolled.

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\install.ps1 `
  -OfficeName "Client Office" `
  -InstallTailscale `
  -TailscaleAuthKey "paste-temporary-tailscale-auth-key" `
  -TailscaleHostname "creditsoft-client-office" `
  -ApiBase "http://100.80.51.78:8001/api/v1" `
  -ApiToken "paste-staff-api-key" `
  -ListenPort 8877 `
  -StartNow
```

For a second node, pass another `-ApiBase` value:

```powershell
.\install.ps1 `
  -OfficeName "Client Office" `
  -ApiBase "http://100.80.51.78:8001/api/v1","http://M4_TAILSCALE_IP_OR_DNS:8001/api/v1" `
  -ApiToken "paste-staff-api-key" `
  -StartNow
```

After it starts, open:

```text
http://127.0.0.1:8877/dashboard?source=intranet-client
```

In Edge or Chrome, install that page as an app/PWA so it feels like the Mac setup.

## Feedback Checklist

Ask the tester to keep notes on real workflow problems, not just visual preferences:

- Client intake: fields missing from DisputeFox-era workflows.
- Client roster: search, filters, ownership, and status labels.
- Credit report import: whether the bureau data lands where she expects it.
- Documents: IDs, proof of address, agreements, letters, and staged/pending files.
- Letters: print readiness, attachments, dispute reasons, and handoff to mailing.
- Metro2 issues: whether the AI suggestions match how she would dispute today.
- FCRA/FDCPA/legal workflow: missing categories, deadlines, or evidence handling.
- CRM handoff: whether leads, tasks, and follow-ups make sense.
- Anything that makes her reach back into DisputeFox.

## Migration Later

When the permanent hardware arrives, install a real CreditSoft office node there, join it to Tailscale, restore or mirror the data, then point this Windows client at the new node API base. The Windows workstation should not become the source of truth unless you intentionally install the full office node stack.
