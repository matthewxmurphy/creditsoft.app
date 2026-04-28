# CreditSoft Intranet Client

This is the first cross-platform bootstrapper for the real intranet client idea:

- A staff member has their own personal CreditSoft API key.
- The client discovers the best office API lane instead of assuming `127.0.0.1`.
- The browser/PWA opens against the machine that actually hosts CreditSoft.
- Tailscale can be checked locally without making the PHP intranet pretend every device is localhost.

The intended order is:

1. Local host when the client runs on the CreditSoft server machine.
2. Tailscale or another private office URL when the employee is on another approved device.
3. Public API bridge or ngrok only when the office intentionally exposes that lane.

When more than one node is available, the client probes them all and picks the fastest healthy API response by default. Use `--strategy ordered` if an office wants strict priority order instead.

## Local Router Mode

The real installed client should run like a small local server on every workstation:

```bash
npm run intranet:client -- --serve --base https://creditsoft-intranet.example.ts.net/api/v1
```

Multiple nodes can be supplied:

```bash
npm run intranet:client -- --serve \
  --base https://office-mac-mini.example.ts.net/api/v1 \
  --base https://office-backup.example.ts.net/api/v1 \
  --base https://www.example-office-domain.com/api/v1
```

That opens Chrome to the workstation's own local router, for example:

```text
http://127.0.0.1:8877/dashboard?source=intranet-client
```

The router then proxies the same CreditSoft app to the real office server. This keeps the visual UI identical while avoiding the trap where another Mac tries to use the server machine's `127.0.0.1`.

## Run From This Repo

```bash
npm run intranet:client -- --token "$CREDITSOFT_API_TOKEN" --base http://127.0.0.1:8001/api/v1
```

Use `--no-open` when you only want to test discovery:

```bash
npm run intranet:client -- --no-open --json --base https://creditsoft-intranet.example.ts.net/api/v1
```

## Pairing URLs

The future installer can hand this client a pairing URL:

```text
creditsoft://pair?base=https%3A%2F%2Fcreditsoft-intranet.example.ts.net%2Fapi%2Fv1&name=Mary%20MacBook
```

For safety, the client does not save API keys to disk. A packaged desktop app should store the key in the OS keychain and pass it to this runner at launch.

## Tailscale

If `tailscale` is installed, the runner reports local Tailscale status. Later installers can use the same lane to run `tailscale up --auth-key ...` after the owner approves the device enrollment flow.

## CRM Sidecar

The CRM should run as a sidecar window/service, not be merged into the Laravel app. CreditSoft can link out to the CRM, sync contacts/opportunities through the CRM API, and receive CRM webhooks through the CreditSoft bridge.
