#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p \
  bootstrap/cache \
  storage/app/private \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

# The cache directory is a persistent Docker volume. Clear old optimized files
# before Laravel boots so dev-only providers from a previous build cannot brick
# startup.
rm -f bootstrap/cache/*.php

php_opcache_memory="${PHP_OPCACHE_MEMORY_CONSUMPTION:-192}"
php_opcache_files="${PHP_OPCACHE_MAX_ACCELERATED_FILES:-20000}"
php_opcache_validate="${PHP_OPCACHE_VALIDATE_TIMESTAMPS:-0}"

cat > /usr/local/etc/php/conf.d/zz-creditsoft-cache.ini <<EOF
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=${php_opcache_memory}
opcache.max_accelerated_files=${php_opcache_files}
opcache.validate_timestamps=${php_opcache_validate}
apc.enable_cli=1
EOF

if [ -z "${APP_KEY:-}" ]; then
  app_key_file="storage/app/private/container-app-key"

  if [ -s "$app_key_file" ]; then
    export APP_KEY="$(cat "$app_key_file")"
  else
    export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
    printf '%s\n' "$APP_KEY" > "$app_key_file"
    chmod 600 "$app_key_file"
    echo "Generated a persistent Docker APP_KEY at ${app_key_file}. Set APP_KEY in .env.docker before production moves."
  fi
fi

if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
  echo "Waiting for PostgreSQL at ${DB_HOST:-office-db}:${DB_PORT:-5432}/${DB_DATABASE:-creditsoft}..."
  postgres_ready="false"

  for _attempt in $(seq 1 60); do
    if php -r '
      $host = getenv("DB_HOST") ?: "office-db";
      $port = getenv("DB_PORT") ?: "5432";
      $database = getenv("DB_DATABASE") ?: "creditsoft";
      $username = getenv("DB_USERNAME") ?: "creditsoft";
      $password = getenv("DB_PASSWORD") ?: "";

      try {
          new PDO("pgsql:host={$host};port={$port};dbname={$database}", $username, $password);
          exit(0);
      } catch (Throwable $exception) {
          fwrite(STDERR, $exception->getMessage().PHP_EOL);
          exit(1);
      }
    ' >/dev/null 2>&1; then
      postgres_ready="true"
      break
    fi

    sleep 2
  done

  if [ "$postgres_ready" != "true" ]; then
    echo "PostgreSQL was not reachable before startup timeout."
    exit 1
  fi
fi

php artisan config:clear --no-interaction || true
php artisan migrate --force --no-interaction

exec "$@"
