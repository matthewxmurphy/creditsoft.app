# Client-Owned Remote Browser

This is the safe lane for a customer who needs a browser workspace before their
own office node is installed.

The browser can run on Ryzen, but it must not use Matthew's host Tailscale or
host ngrok account. The compose profile keeps those credentials customer-owned:

- Tailscale runs inside a Docker namespace with the client's auth key.
- ngrok runs inside that same namespace with the client's authtoken.
- The host Tailscale socket is not mounted.
- The host network is not used.
- Tailscale SSH and subnet-route acceptance are disabled by default.

That means the public URL exposes only the remote browser login. The browser can
open CreditSoft through the configured start URL, usually Ryzen's local router:

```text
http://host.docker.internal:8877/dashboard?source=client-remote-browser
```

## Start A Customer Browser

Run this on the server that will host the temporary browser, for example Ryzen:

```bash
cd ~/CreditSoft
scripts/creditsoft-remote-browser-client.sh up credit-essense
```

The setup asks for:

- Client label
- Client Tailscale auth key
- Client ngrok authtoken
- Optional client ngrok API key
- Optional reserved ngrok domain
- Remote browser username and password
- Optional extra ngrok basic-auth user and password
- The start URL inside Ryzen

Secrets are written under:

```text
~/.creditsoft/remote-browsers/<client-slug>/
```

Those files are intentionally outside the repo.

## Check URL And Logs

```bash
scripts/creditsoft-remote-browser-client.sh status credit-essense
scripts/creditsoft-remote-browser-client.sh url credit-essense
scripts/creditsoft-remote-browser-client.sh logs credit-essense
```

## Stop It

```bash
scripts/creditsoft-remote-browser-client.sh down credit-essense
```

## Security Notes

Do not reuse a personal or CreditSoft-owned Tailscale auth key for a customer
browser. Create a reusable or one-time auth key in the client's tailnet and
tag it for this temporary browser.

Do not enable subnet routing or Tailscale SSH for this container unless the
customer explicitly owns that tailnet and needs those features.

This is a migration/demo bridge. Once the customer's hardware is ready, install
their own CreditSoft node and retire this remote browser.
