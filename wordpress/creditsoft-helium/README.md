# CreditSoft Helium child and editable homepage

**2026.10.1 is an unreleased child-theme source proposal**, not a reserved or
published release. It follows Task 118's `YYYY.M.P` rule and is independent of the
existing calculator component and wider CreditSoft app versions.

This adds a Gantry5 site shell and an explicit Elementor FREE homepage template.
It preserves the existing Astro/app source, calculator engine/client/catalog,
WordPress pages and menu records. Activation does not import content, create pages,
select a front page, change menu assignments or connect a provider.

## Target stack and framework boundary

The targeted native stack is WordPress 7.1.3, Gantry 5.6.4, Helium 5.6.4 (`g5_helium`)
and Elementor FREE 4.3.5. Install those separately through the normal authorized
source/dependency process; no vendor package is bundled in this child.

WordPress `Template` and Gantry `configuration.theme.parent` both name `g5_helium`.
The copied parent `includes/theme.php` retains its upstream GPL notices and adds
only the documented parent/common stream fallback plus maintenance attribution.
The child layout and two particles own the shell. The real selected WordPress page
uses `templates/creditsoft-home.php`: normal page loop/`the_content()` inside the
actual Helium header/footer buffering pipeline. There is one Gantry/Timber document,
not a second hand-built HTML page. Other content pages keep the parent's normal templates.

Missing routes use a CreditSoft recovery message and the child’s default Gantry
error layout. A saved Gantry error outline takes precedence over the layout defaults.
WordPress keeps its native HTTP 404 status. The recovery link uses the configured
homepage URL, including any subdirectory or `/~UUID/` base. The child does not
register a new route or require an imported Elementor page for the error screen.

The CreditSoft customer URLs `/login` and `/sign-in` use the branded 404 until
an explicit route is available. Published WordPress pages at those paths take
precedence. Staff continue to sign in through `/wp-login.php` and `/wp-admin/`.

The child uses the three published CreditSoft icons for browser tabs and Apple
touch shortcuts. A WordPress Site Icon takes precedence. Gantry’s configured
favicon or touch icon takes precedence for the matching icon types. The
Customizer displays WordPress’s own preview.

Direct GET and HEAD requests for `/favicon.svg`, `/favicon.ico` and
`/apple-touch-icon.png` can redirect temporarily to the published icons bundled
in the child. Existing routes take precedence, as does the WordPress Site Icon
handler for `/favicon.ico` when a Site Icon is configured. The redirect uses the
local theme asset path when the theme assets and site share an origin.

The native WordPress `robots.txt` response carries CreditSoft's published
`/admin/`, `/api/` and `/vendor/` exclusions when search indexing is enabled.
The rules use the WordPress home path, and WordPress supplies its own admin rules
and sitemap. If the web server serves a physical robots file, include the
exclusions there during cutover. On a shared tilde host, crawlers read the
host-root robots file. Verify the preview's indexing settings separately.

With pretty permalinks, WordPress supplies the legacy `/sitemap.xml` alias at
the site root. At a subdirectory base such as `/~UUID/`, the child redirects
an otherwise-missing exact GET or HEAD request to the native sitemap index
when WordPress has sitemaps enabled.

Official contracts:

- [Gantry WordPress child themes](https://docs.gantry.org/gantry5/theme-development/create-child-theme-wordpress)
- [Elementor document JSON](https://developers.elementor.com/docs/data-structure/general-structure/)
- [Elementor theme locations](https://developers.elementor.com/docs/themes/) describes
  Pro Theme Builder; this child uses free page content and does not require Pro.

## Explicit setup on an authorized site

1. Install the child as `wp-content/themes/creditsoft-helium` and select it when
   authorized. Existing edited content/front-page settings remain untouched.
2. Explicitly prepare the import in the active WordPress context so local CTA
   links include any site subdirectory or `/~UUID/` base. This read-only command
   exports data; it imports or rewrites no page:

   ```sh
   wp eval 'echo wp_json_encode(creditsoft_helium_homepage_template());' > creditsoft-homepage-import.json
   ```

   Import that prepared file through Elementor's saved-template import UI, then
   insert it into a **chosen real WordPress Page**. Select the
   **CreditSoft Elementor Home** page template and save. Do not replace another
   page automatically. Select a new front page in Settings → Reading only after
   reviewing the site's existing selection.
3. In Appearance → Menus, assign existing or deliberately created menus to the
   five CreditSoft locations. `content/navigation.json` records the published
   primary hierarchy and footer destinations as setup data; it is not an automatic
   importer. Preserve existing menus and assignments unless the owner changes them.
4. Use Gantry Layout Manager/particle defaults to edit the utility/footer copy and
   logo, or reorder the shell. Edit the homepage with Elementor and save normally;
   subsequent visits display the saved document rather than replacing it with seed
   content. No Elementor account, template-library subscription or AI opt-in is used.

The explicit preparation helper rebases only root-relative `settings.link.url`
values with `home_url`; it keeps path/query/fragment text, external and `mailto:`
targets intact. It is not a global link-rewrite filter. Header Login/Checkout
links use the same WordPress home base; native menus use their explicitly assigned
WordPress URLs.

The homepage copies the published static hero, features, FAQ and CTA defaults from
`site-astro/src/lib/preview-content.ts` and `src/pages/index.astro`. Placeholder
reviews/testimonials are deliberately omitted; they are not customer proof. The
published wordmark bytes are reused. Menu, social and CTA destinations remain link
strings, including checkout links; setup/testing does not click money/provider
paths. This slice adds no forms, CRM, checkout logic, analytics, Meta, chat SDK or
remote social widgets. The broader site migration and live integrations remain
separate from this homepage/shell source preparation.

## Video library

Import `content/videos.elementor.json` through Elementor's saved-template UI.
Insert it into a chosen Page with the `videos` slug. Use the **CreditSoft
Elementor Home** page template for the shared content shell. The heading and
intro can be edited in Elementor.

The Video Library shortcode widget starts without media. Once reviewed
replacement clips and stills are in the local WordPress Media Library, edit the
widget and enter their attachment IDs. The poster attributes are optional.

```text
[creditsoft_video_library companion="123" companion_poster="124" app="125" app_poster="126"]
```

Use the reviewed Companion walkthrough for `companion` and the reviewed app
overview for `app`. Each video must be a local, same-origin MP4 attachment.
JPEG, PNG and WebP attachments can be used as posters. If a clip has no valid
attachment, its playlist entry shows that it is unavailable. The player uses
native controls and does not autoplay. The published Page preserves
`?watch=smartcredit-companion` and `?watch=creditsoft-app`. A missing or unknown
selection opens the Companion entry.

Verify the Page, both links and each media response at the intended preview
base. The preview still needs reviewed replacement clips and stills before the
library is ready for visitors.

## Explicit static marketing pages

Three additional, text-only Elementor FREE exports preserve the displayed copy
and CTA destinations from the existing public Astro `about-us.astro`,
`resources.astro` and `docs.astro` bodies. They use the same real page-content
wrapper and scoped presentation as the homepage.
The docs page promotes its existing primary heading to an H1 without changing
its words. Resources keeps `user-guide`, `admin-guide`, `crm-guide` and
`ai-settings` exactly once each instead of copying the source's duplicate IDs.
The legacy `/resources#/user-guide/introduction` remains displayed code text;
this adds no hash router.

Prepare one deliberately selected export in WordPress:

```sh
wp eval 'echo wp_json_encode(creditsoft_helium_marketing_template("about-us"));' > creditsoft-about-us-import.json
```

The other supported selectors are `resources`, `docs`, `multi-office-nodes`,
`cookies`, `privacy`, `terms` and `releases`. Import the prepared
JSON through Elementor, insert it into a chosen Page, and use the existing
**CreditSoft Elementor Home** page template as the shared content wrapper. Review
any existing page and saved edits before explicitly replacing content. No helper
creates, overwrites or publishes pages, selects permalinks, or changes navigation.
Configure the intended page paths manually. Once the public `/about-us` Page is
published, the child preserves the Astro `/about` alias with a permanent redirect
for GET and HEAD. It uses the site's own URL base, including `/~UUID/` on a
preview. It applies only to a missing `/about` route and leaves the slug reserved
if an editor already owns a Page there, including a draft or private Page. On
that exact missing route, WordPress does not guess a similarly named page when
the intended redirect cannot run.

The old `/index.html` address redirects to the configured homepage for GET and
HEAD when WordPress would otherwise return 404. The redirect keeps the query
string and any configured subdirectory or `/~UUID/` base. WordPress applies its
usual routing rules to query parameters such as `tag` on the destination.

The `releases` export is the editable public release history from Astro. Its four
archived entries retain their order, version labels and descriptions. Import it
into a chosen WordPress Page and set the `releases` slug when ready.

API, license, update, CRM and provider routes mentioned in these pages are copied
prose/code strings, not new endpoints, calls or guarantees. Contact/login links
remain link strings only. This cohort adds no forms, account/pricing/checkout
logic, legal policy, external social/video integration or live service work.
The Features index and other Astro pages remain outside this small cohort. The
separate Multi-office export below includes exactly one published topology diagram.

### Multi-office nodes

`content/multi-office-nodes.elementor.json` is a separate, explicit export for a
chosen `/multi-office-nodes` Page. It preserves the selected lane from
`site-astro/src/lib/site-lanes.ts` and its generic `[slug].astro` body: heading,
summary, exact alt/caption, context, all three detail sections and nine items,
three bullet/detail pairs, and the original contact/login/features link strings.
Prepare it with `creditsoft_helium_marketing_template("multi-office-nodes")` and
use the same reviewed, manual Elementor import process above. No page is created
or overwritten by preparation or theme activation.

The sole diagram is an unchanged copy of the published
`multi-office-node-topology.png`, displayed full-size without cropping. Only this
export's exact known source-image path is resolved to the local child asset URI.
Preparation performs no remote fetch, accepts no arbitrary asset path and creates
no Media Library record. The editable Free image widget retains the source alt
and caption and uses Elementor's native file lightbox for full-image viewing.
The image is a conceptual role/backup diagram; its labels are not live node or
provider state. Backup/service names and CTA destinations remain copied text and
links, without connecting services or running migration/checkout workflows.

## Contact and local Fluent Forms capture

This source slice adds the faithful Contact page plus **local capture only**.
Required name/email and email-format validation use Fluent Forms FREE itself.
MX lookup, typo-domain suggestions, disposable-domain rejection, hard-bounce
suppression, Brands FluentCRM delivery and live test-lead acceptance remain
unfinished. No connection to a mail provider or the Brands hub is configured.

Install Fluent Forms FREE separately (native target 6.2.16), then deliberately
import `content/contact-form.fluentforms.json` through its standard form-import
UI. The named form is **CreditSoft local capture**. It contains required `name`
and `email` fields, no payment fields or integration feeds, empty notifications,
and a neutral local-storage confirmation. Its extra form CSS class is
`creditsoft-local-capture`, so the existing gold/dark CTA palette applies only to
this selected form, including hover and keyboard focus. Import creates a new form only when
an administrator explicitly requests it; the child never imports on activation.
Existing forms and saved edits are not overwritten. Check the selected form's
actual notification/integration settings before using it; later administrative
changes remain that form's own settings.

On a subdirectory site, the `creditsoft-local-capture` form class also marks
the intake whose source URL should retain the configured base once and keep
the original query encoding in saved Fluent Forms entries.

Use this site's real imported form ID, for example 123, to prepare Contact:

```sh
wp eval 'echo wp_json_encode(creditsoft_helium_contact_template(123));' > creditsoft-contact-import.json
```

Import the prepared Elementor file into a deliberately chosen Page through the
same manual process above. The read-only preparer binds exactly one fixed marker
and keeps all published Contact copy, list items and passive mailto/pricing/
checkout destinations. It rejects missing, unpublished, malformed or payment
forms. Runtime rendering emits no controls if its binding becomes unavailable.
No generic shortcode text, custom submission endpoint or custom nonce handler
is accepted: the wrapper delegates the numeric ID to the real Fluent Forms
shortcode, whose standard submission pipeline validates and stores the entry.

The same explicit placement works in an Elementor FREE Shortcode widget or an
ordinary WordPress page/block:

```text
[creditsoft_intake form_id="123"]
```

For a new homepage import, pass the reviewed form ID to the homepage exporter.
The returned Elementor template adds an editable Contact CreditSoft section
with the same native shortcode:

```sh
wp eval 'echo wp_json_encode(creditsoft_helium_homepage_template(123));' > creditsoft-homepage-with-intake.json
```

Import the export into the page you choose. For an existing saved homepage,
place the shortcode in Elementor where you want the form. On the tools index and all
twelve calculator fallback routes, an operator can place the same form below
the tools with one site option. Use the site's reviewed form ID:

```sh
wp option update creditsoft_tools_intake_form_id 123
```

Set the option to `0` to turn off that placement. The existing shortcode
validation suppresses the entire contact section if the selected form becomes
unavailable. The contact form receives its own inputs. Calculator values stay
in the browser. Saved WordPress Pages at the tools URLs take precedence over
the fallback routes and can place the contact shortcode alongside
`[creditsoft_tool slug="budget"]` or another calculator shortcode.

This import supplies no response-time, lead-routing or marketing-subscription
promise. Local entries remain in the selected WordPress site's native Fluent
Forms storage. No live lead, real mailbox, checkout or provider action is part of
this source checkpoint.

## Existing policy-page copy

`content/cookies.elementor.json`, `privacy.elementor.json` and
`terms.elementor.json` preserve the current public Astro policy bodies at
`site-astro/src/pages/{cookies,privacy,terms}.astro`: text, heading/list order,
inline emphasis, mailto destinations and the existing **Last Updated: February
2026** wording. There is no dynamic policy logic in these source bodies. No policy
wording, date or business rule was revised; this is a faithful content export,
not a legal/data-practice or consent-implementation assessment.

Use the existing read-only preparer, for example:

```sh
wp eval 'echo wp_json_encode(creditsoft_helium_marketing_template("privacy"));' > creditsoft-privacy-import.json
```

The other selectors for this cohort are `cookies` and `terms`. Explicitly import
into chosen Pages at the intended `/cookies`, `/privacy` and `/terms` paths using
the same Elementor content wrapper. Preserve existing pages and saved edits;
preparation and activation create, replace and publish nothing. These exports do
not enable analytics, pixels, a cookie banner, checkout, forms or account flows.
Existing forms, other exports, calculator source and version declarations remain
unchanged.

## Focused checks and rollback

```sh
node --test wordpress/creditsoft-helium/tests/source-contract.test.mjs
# New marketing cohort only:
node --test wordpress/creditsoft-helium/tests/marketing-contract.test.mjs
# New multi-office export only:
node --test wordpress/creditsoft-helium/tests/multi-office-contract.test.mjs
# New Contact/local-capture slice only:
node --test wordpress/creditsoft-helium/tests/contact-contract.test.mjs
# New policy-copy cohort only:
node --test wordpress/creditsoft-helium/tests/legal-contract.test.mjs
```

`tests/native-stack.php` exercises the actual disposable WordPress/Gantry/Elementor
stack, including preserving an existing edited front page, explicit document
create/edit/save, populated menu locations and selected calculator surfaces. It
is not an installer or production smoke test. Browser checks cover the real free
editor plus desktop/mobile shell and selected pages, without replaying the old
calculator matrix or submitting lead/checkout forms.

Rollback selects the previous theme and preserves pages, Elementor data, menus,
existing theme settings and calculator source. Delete no user data or Gantry custom
configuration as part of rollback. No deployment, DNS change, release tag or public
package publication is performed by this source checkpoint.

`tests/native-contact-capture.php` verifies only the new Contact/form slice in a
disposable WordPress/Fluent Forms installation, including actual AJAX entry
storage/rejection, explicit placements, unavailable bindings and edit preservation.

New child work: copyright 2026 Matthew C. Murphy,
[MatthewxMurphy.com](https://MatthewxMurphy.com),
[Built Not Begged](https://builtnotbegged.com), GPL-2.0-or-later; full text in COPYING.
Recorded policy identity: Matthew X Murphy. See NOTICE.md for inherited source.
