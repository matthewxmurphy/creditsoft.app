# CreditSoft Financial Tools — WordPress adapter

Unreleased source proposal **2026.10.1** (`YYYY.M.P`, unpadded month and patch per
Task 118). This is not a reserved or published release. It prepares the existing
12 calculators for WordPress while leaving the Astro site and all other pages
untouched. It does not complete the full Gantry/Elementor marketing-site rebuild.

## Interfaces

- Shortcode: `[creditsoft_tool slug="budget"]`.
- Dynamic block: **CreditSoft financial tool**, with the same 12-slug selector.
- Virtual routes: `/tools/` and `/tools/<slug>/`, only when WordPress otherwise
  returns a front-end 404. Existing pages, including private or draft pages at that exact path, win;
  custom routes that resolve the WordPress query also win.
  The adapter registers no rewrite rules, creates no pages and flushes no rules.
- Slugs: `budget`, `payoff`, `utilization`, `prep`, `strategy`, `simulator`,
  `optimizer`, `readiness`, `negative`, `comparison`, `timeline`, `recommendation`.

One calculator may run per page, matching the existing client's first-root
contract. The block inserter disallows a second block. Mixed/shortcode duplicates
produce a link-only notice rather than another apparently working form. Invalid
selectors produce a bounded notice; unknown virtual paths retain their 404.

## Source and installation

Requires WordPress 6.5+ and PHP 8.1+. For a disposable WordPress fixture, copy this
folder into `wp-content/plugins/creditsoft-financial-tools` and activate it.
No production install, DNS change or publication is part of this source work.
The route template uses the site's existing classic or block-theme header/footer.
Gantry and Elementor are not bundled, activated or claimed compatible by inference;
shortcode/block integration is the intended boundary for their later site shell.

The adopted catalog, model and browser client are byte-for-byte copies of the
current public Astro modules. No formula, input bound, default, result wording,
source attribution or score/approval disclaimer is redefined. `assets/catalog.json`
is the PHP-readable descriptor generated from that catalog, not a second model.

```sh
node wordpress/creditsoft-financial-tools/scripts/prepare-catalog.mjs
node --test wordpress/creditsoft-financial-tools/tests/source-contract.test.mjs
```

Run `tests/native-acceptance.php` with WP-CLI `eval-file` only in a disposable
WordPress/MariaDB site. Define `CREDITSOFT_TEST_SITE=task118-disposable`, use a
fixture database named `csft118_<8 lowercase hex>` and a loopback home URL. Before
activating the plugin, capture `CREDITSOFT_NATIVE_BASELINE` as JSON containing
`posts` (all original integer post IDs) and `rewrite` (`get_option('rewrite_rules')`).
Pass that JSON and `CREDITSOFT_TEST_HTTP_ORIGIN=http://csft118-<same hex>-wp` to
WP-CLI, where that host is the site's owned internal Docker-network name:

```sh
wp eval-file wp-content/plugins/creditsoft-financial-tools/tests/native-acceptance.php
```

The fixture must disable cron/mail and deny external HTTP while allowing that
exact internal host. The test makes real HTTP requests and removes its synthetic
pages in `finally`; core theme rendering can still create its own navigation row.
Remove the owned containers/network after browser checks. Native/browser
acceptance remains separate from source checks and does not prove Gantry or
Elementor compatibility.
Do not run deployment/release helpers or submit lead/checkout forms to test this
adapter. The existing Astro model suite need not be repeated for an unchanged model.

## Privacy and compatibility

Calculations use the existing browser module. This adapter adds no account,
submission, persistence, analytics, provider call or custom REST endpoint. It does
not disable unrelated theme/plugin tracking. Input values remain in the rendered
calculator; normal navigation and reference links keep their existing meaning.
CSS, including print styles, is scoped to this component. JavaScript and CSS are
local plugin assets. Without JavaScript the controls do not calculate; no successful
result is advertised. Browser printing invokes the normal print dialog only.

Existing page content wins even at a matching `/tools/` URL, so site owners can
replace any virtual route with their own page and embed the shortcode/block there.
WordPress's existing canonical redirects remain in control for resolved content
and unrelated paths. Only core's 404 permalink guess is suppressed for an exact
otherwise-unowned tools route, so a root `/budget/` page cannot redirect
`/tools/budget/` away. Permalink rules, access policy and other plugins remain in
control. Removing this plugin removes only its fallback routes
and rendering; no created pages, stored entries or schema need rollback.

Each rendered fallback page declares the same canonical URL as the tools
sitemap: its slash-terminated path under the configured WordPress home,
including any preview mount. The canonical URL omits request query parameters.
Native WordPress pages keep their own canonical output, and the site’s indexing
setting remains in effect. The fallback also prints the published description
for the tools index or the requested calculator.

The `creditsoft_financial_tools_virtual_after_content` filter accepts trusted HTML
to append below the calculator or index on fallback routes. The template runs
it before the header so integrations can enqueue their assets. The calculator
shortcode, block and saved WordPress Pages use their own content.

A classic theme that supplies the surrounding main landmark can return `div`
from `creditsoft_financial_tools_classic_content_tag`. The wrapper retains its
`primary` ID. The block theme template uses its own main landmark.

The native WordPress sitemap index includes the virtual tools index and calculator
URLs when core indexing and pretty permalinks are enabled. Entries use the
configured site URL and omit exact Page reservations (including draft and private
pages) and URLs WordPress resolves to a post. Core remains responsible for its
own content entries. An integration with a custom virtual route can return
`false` from `creditsoft_financial_tools_sitemap_include` for that root-relative
path. This does not change routing or override a sitemap plugin or a site’s
indexing setting.

## License and maintenance

New adapter files: copyright 2026 Matthew C. Murphy, GPL-2.0-or-later; full text in
[COPYING](COPYING). The unchanged adopted modules had no explicit license identifier
in the public source tree; see [NOTICE.md](NOTICE.md). The new adapter notice does
not relicense those modules or the wider CreditSoft repository. This is source and
owned-fixture preparation, not a released distributable license audit.

Recorded project-policy identity: Matthew X Murphy.
Author: [MatthewxMurphy.com](https://MatthewxMurphy.com).
Product/support: [Built Not Begged](https://builtnotbegged.com).
