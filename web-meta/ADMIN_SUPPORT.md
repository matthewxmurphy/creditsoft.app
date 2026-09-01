# CreditSoft Admin Subdomain Support Files

`admin.creditsoft.app` is a small PHP admin app that is intentionally shared with the public site tree. The admin subdomain wrappers in `web-meta/admin-subdomain/` load files from the live server's `public_html/admin/` folder, and `public_html/admin/index.php` also requires several legacy support files from the top of `public_html`.

The active PHP support files should be kept in `site-astro/public/` and mirrored into the built `web/` folder before deploy. That keeps Astro/static deploys from wiping the admin app back to an older live-only copy. The live backup folders are emergency recovery, not the canonical source.

If an Astro/static deploy replaces `public_html`, the admin page can turn into a blank `500` when these files are missing:

- `site-content-config.php`
- `site-seo-config.php`
- `site-map-config.php`
- `meta-social-manager.php`

The live server keeps a PHP backup folder named like `public_html_php_backup_YYYYMMDDHHMMSS`. Restore the support files after a site deploy with:

```bash
/Users/mmurphy/Desktop/CreditSoft/scripts/restore-live-admin-support.sh
```

The script copies the newest backup versions back into live `public_html` and runs the admin entrypoint through PHP as a smoke test.
