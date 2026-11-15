#!/usr/bin/env bash
set -euo pipefail
site=/var/www/0abb0757-d06a-4da8-b26e-ff885980834e
target="${1:-$site/public_html-wp}"
services="${2:-$site/public_html}"
test -f "$target/wp-config.php"
test -f "$services/api/checkout-request.php"
for name in api admin admin-host account updates updates.creditsoft.app app vendor data _aietherpanel downloads; do
    if [[ -d "$services/$name" ]]; then
        if [[ -L "$target/$name" ]]; then
            ln -sfn "$services/$name" "$target/$name"
        elif [[ ! -e "$target/$name" ]]; then
            ln -s "$services/$name" "$target/$name"
        else
            echo "Refusing occupied service path: $target/$name" >&2
            exit 1
        fi
    fi
done
for source in "$services"/*.php; do
    [[ -f "$source" ]] || continue
    name="${source##*/}"
    [[ "$name" != index.php && "$name" != wp-* ]] || continue
    if [[ -L "$target/$name" ]]; then
        ln -sfn "$source" "$target/$name"
    elif [[ ! -e "$target/$name" ]]; then
        ln -s "$source" "$target/$name"
    else
        echo "Refusing occupied support path: $target/$name" >&2
        exit 1
    fi
done
for name in assets _astro brand; do
    if [[ -d "$target/wp-content/themes/creditsoft-helium/assets/preserved/$name" && ! -e "$target/$name" ]]; then
        ln -s "wp-content/themes/creditsoft-helium/assets/preserved/$name" "$target/$name"
    fi
done
cat > "$target/.htaccess" <<'HTACCESS'
# CreditSoft WordPress/Gantry public site. Legacy PHP services stay isolated.
Options -Indexes
DirectoryIndex index.php index.html
RewriteEngine On
RewriteRule ^(?:license/validate(?:\.json)?|api/verify)/?$ /api/license-validate.php [END]
# These are native WP pages even when legacy service files share their directory.
RewriteRule ^(?:account|downloads)/?$ index.php [L]
RewriteRule ^updates/?$ /downloads/ [R=301,L]
RewriteRule ^index\.html$ / [R=301,L]
RewriteRule ^(.+)/index\.html$ /$1/ [R=301,L]
# BEGIN WordPress
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
# END WordPress
HTACCESS
chmod 644 "$target/.htaccess"
printf 'Linked legacy service files without importing their private state.\n'
