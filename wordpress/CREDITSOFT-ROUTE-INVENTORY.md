# CreditSoft WordPress route inventory

This is the pre-cutover source inventory for Task 118, checked against the CreditSoft Astro site and WordPress draft at `c729bc985992d908f65378028cb601070917ed89` on 2026-11-05 UTC. Astro’s published sitemap contains 40 paths. The draft supplies the tools index and 12 calculators, and it prepares seven indexed pages for import. The other 20 indexed paths still need an implementation or approved destination. Across indexed and unindexed pages, 28 of the 52 ordinary page URLs need a mapping.

The source inventory covers 56 surfaces: 52 ordinary page URLs, the Astro error document and three text/XML endpoints. Fifty public paths, including a missing-route control, were checked with HTTP HEAD. Thirty-one same-origin slash redirects were followed. The 49 existing paths returned 200 and the missing route returned 404. The account, sign-in, purchase and renewal paths were inspected in source. [Published sitemap](https://www.creditsoft.app/sitemap.xml)

## Pages and crawler endpoints

The route lists below normalize trailing slashes. Prepared pages require the explicit WordPress setup described in the child theme. Native routes are supplied by WordPress or the tools adapter when those components and settings are in place. [Theme setup](creditsoft-helium/README.md) · [Tools adapter](creditsoft-financial-tools/README.md)

| Astro paths | Draft coverage and action before cutover |
| --- | --- |
| `/`, `/about-us`, `/contact-us`, `/docs`, `/resources`, `/releases`, `/multi-office-nodes` | Seven indexed pages have Elementor exports. Import and publish at the intended slugs, select the homepage, and configure the approved contact form binding. |
| `/cookies`, `/privacy`, `/terms` | Three policy pages outside the Astro sitemap have Elementor exports. Import and publish at the original slugs. |
| `/tools/`, `/tools/budget/`, `/tools/comparison/`, `/tools/negative/`, `/tools/optimizer/`, `/tools/payoff/`, `/tools/prep/`, `/tools/readiness/`, `/tools/recommendation/`, `/tools/simulator/`, `/tools/strategy/`, `/tools/timeline/`, `/tools/utilization/` | The adapter supplies all 13 original paths on otherwise-unowned requests. Exact WordPress Pages take precedence. The native tools sitemap follows WordPress indexing settings. |
| `/about` | The draft supplies a native 301 to `/about-us/` once the target is published, subject to existing WordPress Page ownership. |
| `/404.html` | The old public error document returns 200 when requested directly. WordPress supplies a branded 404 for missing URLs. Decide whether direct requests for the old file need an explicit mapping. |
| `/sitemap.xml`, `/robots.txt` | WordPress supplies the native sitemap and robots response when configured. With pretty permalinks, core redirects the old sitemap address at the site root. Below the root, the child redirects an otherwise-missing exact request to the native index when sitemaps are enabled. The public draft carries Astro's `/admin/`, `/api/` and `/vendor/` crawler exclusions. Verify the sitemap and host-root robots file at the intended host before cutover. |
| `/social-preview-urls.txt` | Astro publishes the sitemap paths in plain text. Decide whether consumers still need this endpoint and where it should live. |
| `/features` | The overview still needs approved proof images and an Elementor page. |
| `/videos` | The editable Elementor export and native player preserve both `watch` links. Import the Page and bind reviewed replacement MP4s and optional posters through the local Media Library before preview acceptance. |
| `/downloads`, `/updates` | The Astro downloads page shows three download buttons that point to the homepage. `/updates` points to `/downloads`. Confirm the approved download destinations before building the page and alias. |
| `/pricing`, `/pricing-plan`, `/checkout`, `/subscribe`, `/renewal` | Map the pricing and payment flow together. The draft redirects `/pricing` to the exact public `/pricing-plan/` Page once published, with existing Pages taking precedence. The alias uses the fixed destination from Astro and omits the incoming query. `/subscribe` points to `/checkout/?plan=professional&billing=monthly&payment=zelle` in Astro. Preserve approved `plan`, `billing` and `payment` values and the quote/request contract below. The WordPress draft suppresses permalink guessing for an unresolved exact `/checkout` request. A published Checkout page uses native routing. |
| `/login`, `/sign-in`, `/account`, `/customer-dashboard` | Map CreditSoft customer access separately from WordPress staff access. The Astro `/login` and `/sign-in` paths show the account access form. The WordPress draft uses the branded 404 at those paths until an explicit route is available. The draft also stops WordPress from guessing a similarly named page for unresolved `/account` and `/customer-dashboard` requests. Published Pages and explicit WordPress old-slug redirects retain their native behavior. In Astro, `/customer-dashboard` leads to `/account/` and carries query parameters. |
| `/client-portal` | The editable feature page preserves the public product copy and the intake, login and features links. Import it at the original slug and add a reviewed replacement for the app screenshot. Customer sign-in is tracked separately. |
| `/backup-redundancy`, `/tech-stack` | Editable exports preserve the product copy and links. Import both at their original slugs and add reviewed replacement proof images. |
| `/reporting`, `/roadmap` | Editable exports preserve the original copy and links. Import at their original slugs and add reviewed replacement dashboard images. |
| `/compliance`, `/disputes` | Editable exports preserve the original workflow copy and links. Import at their original slugs and add a reviewed replacement for the shared tasks-board image. |
| `/local-first-crm`, `/migration` | Editable exports preserve the original copy and links. Import at their original slugs and add a reviewed replacement for the shared roster screenshot. |
| `/api-bridge`, `/email-providers` | Editable exports preserve the product copy and links. Import at the original slugs and add reviewed replacement settings screenshots. |
| `/metro2`, `/outsourcing`, `/social-media`, `/websites` | Four indexed feature lanes still need a page or approved destination. |

## Linked gaps and service routes

The proposed WordPress menus, shipped Elementor exports and fixed header links point to eight local paths that have no draft equivalent. The Videos, Client portal, Backup redundancy, Tech stack, Compliance, Local-first CRM, Migration, API Bridge and Email Providers links require imported Pages and reviewed media. The menu JSON is setup data and requires assignment. Prioritize the four links that lead into customer access or purchase: `/login`, `/subscribe`, `/pricing-plan` and `/checkout`. The other four are `/features`, `/metro2`, `/outsourcing` and `/social-media`. The Office link points to the separate `https://admin.creditsoft.app` destination. [Menu setup data](creditsoft-helium/content/navigation.json)

The Astro pricing page and checkout request quotes through `/api/checkout-request.php`. Checkout also sends its submission to that endpoint. The public source separately includes `/api/lead.php`, `/api/license-validate-request.php`, `/api/license-validate.php`, `/api/license.php`, `/api/update-feed` and `/api/update-feed.php`. Identify the approved host and routing for each service before cutover. Preserve the behavior of the three internal license rewrites at `/license/validate`, `/license/validate.json` and `/api/verify`, including their optional trailing slash. Internal rewrites keep validation parameters out of redirect locations. The draft preserves the legacy `/index.html` 301 to the configured homepage for otherwise-missing GET and HEAD requests, including query strings. The direct `/favicon.svg`, `/favicon.ico` and `/apple-touch-icon.png` URLs can redirect temporarily to the published bytes in the child theme on the same origin. WordPress takes over `/favicon.ico` when a Site Icon is configured. [Astro rewrite source](../site-astro/public/.htaccess)

Before cutover, confirm the customer and purchase destinations, service routing and approved proof assets. Then publish the prepared pages and assign the reviewed menus on the authorized preview. Verify the routes against the actual preview, followed by the approved Brands lead and checkout acceptance. Keep this inventory with the cutover review so the owner can assess every remaining path.

## Replace the private child theme on an existing preview

WordPress’s native ZIP replacement removes the installed `creditsoft-helium`
directory, including Gantry’s theme-local `custom/` files. A saved layout can
therefore disappear while the site still returns HTTP 200. The saved Elementor
page, menu assignments and Fluent Forms settings live in the WordPress database.
The installer can clear Elementor’s generated asset metadata, which rebuilds
when the page is requested.

For an authorized replacement or rollback, use the exact private ZIP and its
SHA-256 manifest. Both tested ZIPs use the same proposal version, so the version
alone cannot distinguish them.

1. Record the active child theme path, the homepage, menu and selected form.
   Archive the active child’s `custom/` directory outside the theme and verify
   that the archive contains the saved outlines. Keep the previous verified ZIP
   and its manifest available for rollback.
2. Install the selected local ZIP with `wp theme install <approved-zip> --force`.
   Restore `custom/` into the active child before verifying the pages. Confirm
   that the installed package and restored custom files match their respective
   manifests and that WordPress can read the restored files.
3. Check the homepage and budget calculator at the actual preview URL. Verify
   that the saved layout renders and the expected menu and form are present on
   both pages. Keep the pre-replacement backup through acceptance. If a rollback
   is needed, install the previous verified ZIP, restore that known-good
   `custom/` archive, and repeat the same hash and page checks.
