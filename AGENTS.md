# CreditSoft public website

The public CreditSoft.app website uses WordPress and Gantry 6. Preserve the
published CreditSoft design. Do not replace it with Astra, an Astro build, a
generic Helium preset, or a newly invented layout.

* `wordpress/creditsoft-helium` owns the public theme, Gantry particles, shared
  styles and preserved design assets. Its historical directory name is retained
  for update compatibility.
* Native WordPress Pages and revisions own published page content. Seed/import
  files are setup material; never regenerate edited Pages during requests or
  theme activation.
* Gantry's saved `custom/` outlines are runtime content. Back them up outside
  the theme before replacing a theme package and restore them afterwards.
* The Laravel office application, customer account APIs, licensing, payment
  endpoints, update service and admin.creditsoft.app remain separate services.
  Preserve their routes and private configuration during website deployments.
* The Astro source/build/deployment path is retired after conversion. `web/`
  contains legacy backend service source, not a public website deployment
  payload. Never upload its marketing pages over WordPress.
* Check the actual live active theme/framework, route responses, desktop/mobile
  rendering and saved editor content before reporting a website change complete.
* Do not include credentials, customer data, database exports or private hosting
  configuration in source or public archives.

For a public theme update, use the WordPress migration/deployment instructions
under `wordpress/migration/`. Keep a verified rollback before changing routing.
