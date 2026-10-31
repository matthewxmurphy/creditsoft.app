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
not a second hand-built HTML page. Other pages keep the parent's normal templates.

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

## Explicit static marketing pages

Three additional, text-only Elementor FREE exports preserve the displayed copy
and CTA destinations from the existing public Astro `about-us.astro`,
`resources.astro` and `docs.astro` bodies. They use the same real page-content
wrapper and scoped presentation as the homepage; no runtime route is installed.
The docs page promotes its existing primary heading to an H1 without changing
its words. Resources keeps `user-guide`, `admin-guide`, `crm-guide` and
`ai-settings` exactly once each instead of copying the source's duplicate IDs.
The legacy `/resources#/user-guide/introduction` remains displayed code text;
this adds no hash router.

Prepare one deliberately selected export in WordPress:

```sh
wp eval 'echo wp_json_encode(creditsoft_helium_marketing_template("about-us"));' > creditsoft-about-us-import.json
```

The only other supported selectors are `resources` and `docs`. Import the prepared
JSON through Elementor, insert it into a chosen Page, and use the existing
**CreditSoft Elementor Home** page template as the shared content wrapper. Review
any existing page and saved edits before explicitly replacing content. No helper
creates, overwrites or publishes pages, selects permalinks, or changes navigation.
Configure the intended `/about-us`, `/resources/` and `/docs` page paths manually.
The source `/about` page points to `/about-us`; preserve that alias in the site's
chosen redirect setup if needed. This child adds no redirect handler.

API, license, update, CRM and provider routes mentioned in these pages are copied
prose/code strings, not new endpoints, calls or guarantees. Contact/login links
remain link strings only. This cohort adds no forms, account/pricing/checkout
logic, legal policy, external social/video integration or live service work.
Screenshot-backed Features and the other Astro pages are outside this small
cohort; no product screenshots or lightbox assets are copied here.

## Focused checks and rollback

```sh
node --test wordpress/creditsoft-helium/tests/source-contract.test.mjs
# New marketing cohort only:
node --test wordpress/creditsoft-helium/tests/marketing-contract.test.mjs
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

New child work: copyright 2026 Matthew C. Murphy,
[MatthewxMurphy.com](https://MatthewxMurphy.com),
[Built Not Begged](https://builtnotbegged.com), GPL-2.0-or-later; full text in COPYING.
Recorded policy identity: Matthew X Murphy. See NOTICE.md for inherited source.
