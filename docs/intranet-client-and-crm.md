# CreditSoft Intranet Client And CRM Sidecar

## Decision

The CRM can extend CreditSoft, but it should be a white-label sidecar app/window instead of being merged directly into the Laravel intranet.

Reasons:

- The product goal is to remove dependency on paid marketing automation glue like ActiveCampaign, GHL, and Zapier for common credit repair office workflows.
- The CRM is a full TypeScript/NestJS/React application with PostgreSQL and Redis.
- CreditSoft is a Laravel/Vue local-first intranet with SQLite-friendly backup and API bridge lanes.
- Keeping the CRM beside CreditSoft protects the local casework app while still letting us sync contacts, companies, opportunities, and activities.
- The upstream CRM project has license boundaries that need review before CreditSoft distributes modified or bundled builds.

## Current CreditSoft Foundation

CreditSoft already has most of the support needed for this vision:

- Personal user API keys with abilities for browser companion and intranet client use.
- Partner API routes under `/api/v1`.
- Tailscale, ngrok, and public API bridge detection.
- PWA manifest and service worker support.
- Browser companion bundle download.
- Cluster backup mirroring for multi-office backup safety.

## Intranet Client Flow

The desktop client should be the thing that makes another machine useful. `127.0.0.1` is only valid on the machine running the server.

Flow:

1. Staff logs into CreditSoft on the host machine and creates a personal API key from Profile.
2. The intranet client receives a pairing URL, pasted base URL, or saved config.
3. The client checks candidate API lanes:
   - `http://127.0.0.1:8001/api/v1`
   - Tailscale or private office URL
   - public API bridge or ngrok fallback
4. When no key was supplied by the installer/keychain, the interactive runner asks for the staff member's personal API key without saving it.
5. The client validates the personal key against `/api/v1/client/handshake`, then falls back to `/api/v1/office-stats`.
6. If Tailscale is running, the client reads MagicDNS peers from `tailscale status --json`, pings those peers, and probes likely CreditSoft ports (`8001`, `8000`, `8877`, `443`, and `80`) for `/api/v1`.
7. The client opens the dashboard/PWA against the real host, not the employee machine's localhost.

The first Node runner lives in `intranet-client/`.

The installed version should normally run as a loopback router:

- Each employee machine gets its own `127.0.0.1` listener.
- Chrome opens that local listener so the app feels the same on every machine.
- The router forwards UI and API traffic to the real CreditSoft server over localhost, LAN, Tailscale, ngrok, or the public API bridge.
- The router can inject the personal API key into API requests while the browser remains the front end.
- Later native installers can run the router at login and store the key in macOS Keychain, Windows Credential Manager, or Linux Secret Service.

## Node Pool / Load Balance Model

Multiple CreditSoft-capable nodes should behave like a small office pool:

- The client probes every candidate node with a real `/api/v1` request.
- The default selection strategy is fastest healthy app response, not raw ping or traceroute.
- App-level timing is better than ICMP because it proves the network, web server, PHP/Laravel app, and API route are all responding.
- A strict ordered strategy can still exist for offices that always want primary then secondary.
- The chosen node handles the user session through the local router.
- Database snapshots and backup archives should still mirror between office nodes through the existing cluster backup lane.
- The intranet handshake exposes a safe cluster summary so clients and sidecars can see whether backup mirroring is enabled without receiving shared secrets.
- Future node sidecars can add health scoring, sync lag checks, and read/write leadership so a stale backup node is not chosen just because it responds quickly.

## PWA Rule

Chrome PWA install works on localhost and secure HTTPS origins. CreditSoft should allow installation from:

- `127.0.0.1`
- `localhost`
- HTTPS Tailscale, ngrok, or owned domains

HTTP LAN IPs can open the site, but should not be treated as the final install path unless the installer adds a trusted HTTPS wrapper.

## CRM Sidecar

Recommended first integration:

1. Run the CRM separately through its supported Docker Compose flow.
2. Add a CreditSoft setting for `CRM_BASE_URL` and `CRM_API_KEY`.
3. Add a left-rail or app-switcher CRM button that opens the CRM in its own window.
4. Add a CreditSoft-to-CRM sync job:
   - Client to Person
   - Business/company fields to Company
   - Case status or lead stage to Opportunity
   - CreditSoft tasks/notes to Notes or activities where appropriate
5. Add a CRM webhook receiver in CreditSoft so CRM changes can update the local client record when allowed by ACL.

## Second Office / Node Server

The Node server should not replace the Laravel intranet. It should be a sidecar agent host for offices that need background workers or CRM bridge work:

- Runs on the second office machine.
- Stores no master secrets in source.
- Uses personal or office-scoped API keys.
- Connects over Tailscale first.
- Can run sync workers, bridge CRM webhooks, and later host agent jobs.

That gives us a clean split:

- Laravel/Vue: private office system of record.
- Node client: cross-platform staff launcher and device bridge.
- Node sidecar server: second-office workers, CRM bridge, agent jobs.
- CRM sidecar: optional CRM window and CRM data model.
