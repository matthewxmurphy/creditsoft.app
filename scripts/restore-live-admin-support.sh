#!/usr/bin/env bash
set -euo pipefail

REMOTE_USER_HOST="${REMOTE_USER_HOST:-fleet@132.226.159.32}"
SSH_KEY="${SSH_KEY:-/Users/Shared/aiether/keys/fleet_ed25519}"
SITE_ROOT="${SITE_ROOT:-/var/www/0abb0757-d06a-4da8-b26e-ff885980834e}"

ssh -oBatchMode=yes -oStrictHostKeyChecking=accept-new -i "$SSH_KEY" "$REMOTE_USER_HOST" \
  "SITE_ROOT='$SITE_ROOT' bash -s" <<'REMOTE'
set -euo pipefail

backup_root="$(ls -dt "$SITE_ROOT"/public_html_php_backup_* 2>/dev/null | head -1 || true)"

if [[ -z "$backup_root" ]]; then
  echo "No public_html_php_backup_* folder found under $SITE_ROOT" >&2
  exit 1
fi

files=(
  site-content-config.php
  site-seo-config.php
  site-map-config.php
  meta-social-manager.php
)

for file in "${files[@]}"; do
  source_path="$backup_root/$file"
  target_path="$SITE_ROOT/public_html/$file"

  if [[ ! -f "$source_path" ]]; then
    echo "Missing backup file: $source_path" >&2
    exit 1
  fi

  sudo cp -a "$source_path" "$target_path"
  sudo test -r "$target_path"
  echo "Restored $file"
done

sudo php -d display_errors=1 "$SITE_ROOT/admin.creditsoft.app/index.php" >/tmp/creditsoft-admin-support-check.html

echo "Admin support files restored from $backup_root"
REMOTE
