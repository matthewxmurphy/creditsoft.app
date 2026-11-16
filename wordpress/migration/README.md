# CreditSoft WordPress / Gantry 6 migration

The public site uses the dedicated `creditsoft-helium` child theme and the Gantry
6 Helium parent. The historical child directory is retained for compatibility.
The installed framework baseline is **6.0.0-alpha.1**; this is the maintained
Gantry 6 build, not an upstream stable-release claim. WordPress is 7.1.4, PHP 8.5.

The theme owns the shared header/footer as native Gantry particles. Layout
Manager saves override HTML in Gantry's normal custom outline files. Native
WordPress Pages own page content and revisions. The preserved design files are
initial migration material, never an automatic page replacement mechanism.

## Verified migration material

`content/preserved/manifest.json` records 47 page routes, their source hashes,
native content seeds and captured page-specific presentation/behavior. The
theme contains 57 public design assets. Scripts continue to use the existing
PHP account, checkout, lead and license endpoints; no payment provider is called
by the migration tools.

`verify-routes.py` checks all page headings, a single main landmark, absence of
the old Pay demo, exact asset bytes and the real branded HTTP 404. Browser QA
also checks desktop, mobile, navigation and calculator behavior.

## Deployment and rollback

1. `backup-staging.sh` creates a private database/content/routing backup outside
   all web roots. It verifies the saved content archive.
2. `deploy-staging.sh` installs the explicit parent and child, imports only
   missing Pages, and adopts only presentation metadata on existing Pages. It
   preserves their content, IDs, revisions and saved edits. The temporary flow
   adapter is deactivated because its scripts are now supplied by the theme.
3. `link-services.sh` connects the existing backend directories and support PHP
   files. It keeps private service data separate, preserves the license aliases,
   and routes account/download landing pages through WordPress.
4. `cutover.py` verifies the captured public homepage has not changed, backs up
   the complete previous public tree and WordPress database, changes the native
   WordPress URL, and promotes WordPress at the existing panel-managed
   `public_html` path. The old tree becomes a sibling service directory. The
   panel, API and admin vhosts retain their configured public-root path. Scoped
   links keep their existing PHP service files available. A failed public
   verification restores the two directories and WordPress URLs automatically.
5. After public route and browser acceptance, retire old marketing HTML and the
   Astro source/build/deploy path. Private rollback archives remain outside the
   web roots. Do not remove service PHP, database state, API routes or old
   customer files as part of Astro retirement.

The private server backup is the rollback authority. Record its path in the
deployment receipt. Before a later manual rollback, check for newer WordPress
content/orders; restore only this deployment's source/routing unless a database
restore is explicitly intended. Do not overwrite new customer or editor data.

For later theme updates, preserve `wp-content/themes/creditsoft-helium/custom/`
outside the theme before ZIP replacement, restore it afterwards, and repeat
route/browser verification. Update the dedicated child rather than applying
demo presets to the parent.
