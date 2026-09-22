# CreditSoft financial tools

The public collection at `https://www.creditsoft.app/tools/` adapts all twelve Credit Sense portal tools. Every tool and the collection index include a visible, ordinary HTML anchor to `https://credit-essense.com/tools/`. Astro emits the links before JavaScript runs. The homepage links to the collection in desktop/mobile navigation and its footer; the sitemap includes all thirteen URLs.

Tools: budget coaching, payoff planner, utilization targets, consultation prep, strategy planner, score scenario planner (the previous score simulator), utilization optimizer, funding readiness, negative-item review, debt comparison, action timeline and account helper.

Calculators and worksheets run entirely in the browser. No account, client API, office/ngrok connection, database write or financial-input storage is used. The tools pages omit the site's Analytics, Meta and chat scripts. Print uses the browser's local print dialog. Other pages retain their existing behavior.

The score scenario planner reports utilization changes without the former unvalidated score-point estimates. Funding readiness counts preparation checks, not a lending score. Negative-item review organizes entries without arbitrary severity weights. The account helper compares questions about categories without promising eligibility. Debt simulation rolls paid-off minimums and unused payments into the next debt, caps the final payment, and distinguishes plans still unpaid at 600 months. APR assumptions and official CFPB references are visible on the applicable pages.

## Source and checks

- `site-astro/src/lib/financial-tools/catalog.mjs`: twelve-tool inventory, input definitions and assumptions.
- `model.mjs`: deterministic calculations and worksheet output.
- `client.mjs`: accessible input/result handling; DOM output uses textContent.
- `site-astro/src/pages/tools/`: static tool index and individual routes.
- `site-astro/tests/financial-tools.test.mjs`: numeric, boundary, rollover and built-HTML checks.

Build with `npm run build --prefix site-astro`, then run `node --test site-astro/tests/financial-tools.test.mjs`. Twelve test groups passed. Chrome verified all twelve default outputs and source links, a known $100 / 0% / $30 payoff result of four months, worksheet editing/reset, card add/remove/reset, invalid zero-limit handling and mobile navigation. At a 390px viewport, the page width was 390px with no horizontal overflow. These are browser observations; no screenshot file is claimed.

## Bounded deployment and rollback

The canonical repository at `/Users/mmurphy/Code/CreditSoft` was clean and matched GitHub main at `174d60b281384deb839562f7306e0d6b157086bd` before work. A private source archive was created before changes. Work uses a separate Git worktree. The legacy `Projects/CreditSoft` checkout contains unrelated changes and is not the deployment source.

The actual Apache vhost and public root were inspected over the existing authorized SSH connection. The original homepage and sitemap matched the local baseline. A baseline Astro build reproduced the homepage exactly. Removing only the new tools links from the rebuilt homepage restores the original content. Existing fingerprinted assets are unchanged.

`prepare-financial-tools-release.py` assembles only 17 files: thirteen tools HTML pages, two new fingerprinted assets, the homepage and sitemap. Existing sitemap entries and modification dates are retained. The release manifest records every new hash and expected previous hash. It must be prepared against the pre-release `web` snapshot, not rerun over the already updated generated output.

`deploy-financial-tools.py` defaults to a read-only preflight. `--apply` verifies the expected live hashes again, saves changed originals in a private `/var/backups/creditsoft-financial-tools/` directory, then atomically replaces assets, tool pages and discovery links in that order. It restores its own completed replacements if deployment fails. No vhost, PHP configuration, API, admin, database, office runtime or other existing page is replaced.

For later rollback, first compare the live files with the release manifest so newer work is preserved. Restore index.html and sitemap.xml from the private deployment backup using atomic replacements and their original ownership. Archive this release's new tools directory and two new assets only if their hashes still match this release and nothing newer depends on them. Recheck the original homepage, sitemap and existing private routes. The source commit can be reverted independently; it does not by itself roll back the live files.
