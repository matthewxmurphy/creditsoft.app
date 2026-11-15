# CreditSoft Gantry 6 WordPress theme

The public CreditSoft.app website uses this dedicated child theme with Gantry
**6.0.0-alpha.1** and the `g5_helium` parent. The historical directory name stays
`creditsoft-helium` for update compatibility. The current theme build is recorded
in `VERSION`. The migration was verified on WordPress 7.1.4 and PHP 8.5.

The published warm black/gold CreditSoft design is preserved. Native WordPress
Pages and revisions own editable page content. Native Gantry particles own the
shared header and footer. The former public static-site source and build pipeline
have been removed; no Astro server, npm build or static deployment is required.

## Content and layout ownership

- Edit published text and page HTML through the existing WordPress Pages.
- Edit the CreditSoft header/footer particles through Gantry Layout Manager and
  save the existing outline. Do not apply a demo preset to the parent.
- Preserve the child theme's `custom/` directory before replacing a theme ZIP;
  restore it after the update. These saved outlines are runtime content.
- `content/preserved/manifest.json` records the 47 initial route captures, source
  hashes, page-specific styles and scripts. These files seed missing Pages only
  during explicit migration; they never overwrite an edited Page on a request
  or theme activation.
- `assets/preserved/` contains the captured public CSS, images, scripts and
  media. Historical fingerprinted filenames and scoped `data-astro-cid` selectors
  remain static design assets. They require no Astro runtime or source build.

The preserved page template keeps each source main landmark, including calculator
initialization attributes and spacing classes. Per-page presentation and browser
behavior come from the captured fragments. Existing account, checkout, lead,
license and office APIs remain separate services with their original routes.

## Deployment and rollback

Use the [migration and deployment instructions](../migration/README.md). Test
theme changes on staging and verify actual WordPress page content, Gantry
framework/theme versions, desktop/mobile rendering, navigation, calculators,
videos and service routes. Keep a verified private rollback outside public roots.
Never deploy `web/`, an intranet Vite build or a static-site package over WordPress.

For a route/asset smoke check:

```sh
python3 wordpress/migration/verify-routes.py --help
```

Current source checks:

```sh
node --test wordpress/creditsoft-helium/tests/source-contract.test.mjs
node --test wordpress/creditsoft-helium/tests/contact-contract.test.mjs
node --test wordpress/creditsoft-financial-tools/tests/*.test.mjs
```

The retained `tests/native-*.php` files exercise actual disposable WordPress,
Elementor, Fluent Forms and calculator integrations. They retain their strict
fixture guards; do not run them against production. Earlier source-to-Astro
parity tests were retired with that source tree. Formula/boundary/privacy tests
remain in the WordPress calculator component.

## Optional legacy import helpers

The `*.elementor.json` exports and `creditsoft_helium_*_template()` helpers remain
available for deliberate manual imports into a chosen Page. They are setup
material from the earlier migration proposal, not the live content authority.
Their preparation helpers do not import or publish Pages. Existing native
WordPress Pages take precedence over calculator fallback routes.

The `[creditsoft_intake form_id="123"]` helper binds a deliberately selected,
published Fluent Forms FREE local-capture form. Use this site's actual reviewed
form ID. No real lead, checkout or email submission is required for theme QA.

## Notices

Child work: copyright 2026 Matthew C. Murphy,
[MatthewxMurphy.com](https://MatthewxMurphy.com),
[Built Not Begged](https://builtnotbegged.com), GPL-2.0-or-later.
Inherited theme and artwork notices remain in [NOTICE.md](NOTICE.md).
