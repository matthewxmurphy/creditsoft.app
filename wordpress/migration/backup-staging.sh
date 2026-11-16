#!/usr/bin/env bash
set -euo pipefail
site=/var/www/0abb0757-d06a-4da8-b26e-ff885980834e
stage="$site/public_html-wp"
backup="$site/backups/gantry6-wordpress-$(date -u +%Y%m%dT%H%M%SZ)"
test -f "$stage/wp-config.php"
install -d -m 700 "$backup"
wp --allow-root --path="$stage" db export "$backup/staging.sql" --skip-comments >/dev/null
tar -czf "$backup/staging-wp-content.tar.gz" -C "$stage" wp-content .htaccess wp-config.php
wp --allow-root --path="$stage" eval 'echo wp_json_encode(array_combine($keys = ["home","siteurl","stylesheet","template","page_on_front","show_on_front","blog_public"], array_map("get_option", $keys)));' > "$backup/staging-options.json"
wp --allow-root --path="$stage" post list --post_type=page --post_status=any --fields=ID,post_name,post_title,post_modified --format=json > "$backup/staging-pages.json"
cp -a /etc/apache2/sites-available/mxm/0abb0757-d06a-4da8-b26e-ff885980834e.conf "$backup/public-http.conf"
cp -a /etc/apache2/sites-available/mxm/0abb0757-d06a-4da8-b26e-ff885980834e-ssl.conf "$backup/public-https.conf"
cp -a "$site/public_html/.htaccess" "$backup/astro.htaccess"
find "$backup" -type f -exec chmod 600 {} +
tar -tzf "$backup/staging-wp-content.tar.gz" >/dev/null
sha256sum "$backup/staging.sql" "$backup/staging-wp-content.tar.gz"
printf 'PRIVATE_BACKUP=%s\n' "$backup"
