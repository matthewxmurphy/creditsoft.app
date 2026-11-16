#!/usr/bin/env bash
set -euo pipefail
release="${1:?Pass the extracted release directory}"
site=/var/www/0abb0757-d06a-4da8-b26e-ff885980834e
stage="$site/public_html-wp"
test -s "$release/creditsoft-helium/content/preserved/manifest.json"
test -d "$release/creditsoft-helium/assets/preserved/_astro"
test -f "$stage/wp-config.php"
test ! -e "$stage/wp-content/themes/creditsoft-helium"
wp --allow-root --path="$stage" theme install "$release/wordpress-tpl_g5_helium_v6.0.0-alpha.1.zip" >/dev/null
cp -a "$release/creditsoft-helium" "$stage/wp-content/themes/creditsoft-helium"
chown -R www-data:www-data "$stage/wp-content/themes/creditsoft-helium" "$stage/wp-content/themes/g5_helium"
wp --allow-root --path="$stage" theme activate creditsoft-helium
wp --allow-root --path="$stage" eval-file "$release/migration/import-pages.php" "$stage/wp-content/themes/creditsoft-helium/content/preserved/manifest.json" adopt-existing
# The captured, per-route scripts replace the temporary theme-switch adapter.
wp --allow-root --path="$stage" plugin deactivate creditsoft-flow-assets
wp --allow-root --path="$stage" cache flush
wp --allow-root --path="$stage" rewrite flush --hard
wp --allow-root --path="$stage" eval 'require_once ABSPATH . "wp-admin/includes/plugin.php"; $plugins=get_plugins(); echo wp_json_encode(["theme"=>get_stylesheet(),"parent"=>get_template(),"gantry"=>$plugins["gantry5/gantry5.php"]["Version"] ?? null,"pages"=>count(get_pages()),"front"=>get_option("page_on_front")]);'
chown -R www-data:www-data "$stage/wp-content/cache"
