# CreditSoft Admin Subdomain Support Files

`admin.creditsoft.app` remains a separate PHP office/admin service. The public
site now runs WordPress and Gantry 6. Existing admin subdomain wrappers and API
routes reach their retained service files through scoped links in the
panel-managed public root.

The maintained PHP support source is `web/`. The former duplicate public build
tree has been removed. Do not copy the whole `web/` folder over WordPress.

These existing support files remain required by the separate admin service:

- `site-content-config.php`
- `site-seo-config.php`
- `site-map-config.php`
- `meta-social-manager.php`

Use `wordpress/migration/link-services.sh` and the adjacent migration instructions
to preserve the current service links. It is not a blanket restore from an older
PHP backup. Private backups and customer/service data stay outside theme
packages and public source archives. Verify the actual admin and API responses
after a routing or service update.
