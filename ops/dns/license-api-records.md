# License API DNS

The following authoritative records route every approved license API hostname to
the same CreditSoft service on `assets101.aietherpanel.com`:

| Zone | Owner | Type | TTL | Value |
| --- | --- | --- | ---: | --- |
| `creditsoft.app` | `api.creditsoft.app` | A | 300 | `132.226.159.32` |
| `creditsoft.app` | `api.creditsoft.app` | AAAA | 300 | `2603:c020:1c:3a00:0:13a3:b9de:a53` |
| `creatorpublishinghub.com` | `api.creatorpublishinghub.com` | A | 300 | `132.226.159.32` |
| `creatorpublishinghub.com` | `api.creatorpublishinghub.com` | AAAA | 300 | `2603:c020:1c:3a00:0:13a3:b9de:a53` |
| `net30hosting.com` | `api.net30hosting.com` | A | 300 | `132.226.159.32` |
| `net30hosting.com` | `api.net30hosting.com` | AAAA | 300 | `2603:c020:1c:3a00:0:13a3:b9de:a53` |
| `matthewxmurphy.com` | `api.matthewxmurphy.com` | A | 300 | `132.226.159.32` |
| `matthewxmurphy.com` | `api.matthewxmurphy.com` | AAAA | 300 | `2603:c020:1c:3a00:0:13a3:b9de:a53` |

The four zones are authoritative on the Net30 PowerDNS fleet. Back up each zone
before replacing the RRsets. The Apache TLS virtual host and certificate must cover
all four hostnames before the HTTPS cutover is considered complete.
