#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p \
  bootstrap/cache \
  database \
  storage/app/private \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
fi

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

format_env_value() {
  value="${1:-}"

  case "$value" in
    *[!A-Za-z0-9_./:@%+=,-]*)
      value="${value//\\/\\\\}"
      value="${value//\"/\\\"}"
      printf '"%s"' "$value"
      ;;
    *)
      printf '%s' "$value"
      ;;
  esac
}

set_laravel_env_value() {
  key="$1"
  value="${2:-}"

  if [ -z "$value" ]; then
    return
  fi

  formatted="$(format_env_value "$value")"

  if grep -q "^${key}=" .env 2>/dev/null; then
    awk -v key="$key" -v value="$formatted" '
      BEGIN { prefix = key "=" }
      index($0, prefix) == 1 { print key "=" value; next }
      { print }
    ' .env > .env.tmp
    mv .env.tmp .env
  else
    printf '%s=%s\n' "$key" "$formatted" >> .env
  fi
}

sync_laravel_env_family_values() {
  env | while IFS='=' read -r key value; do
    case "$key" in
      APP_*|CREDITSOFT_*|DB_*|CACHE_*|SESSION_*|QUEUE_*|MAIL_*|\
      OPENROUTER_*|OPENAI_*|ANTHROPIC_*|GEMINI_*|GOOGLE_*|OLLAMA_*|OPENCODE_*|COHERE_*|\
      NGROK_*|TAILSCALE_*|WASABI_*|BACKUP_*|STRIPE_*|CASH_APP_*|ZELLE_*|\
      CRM_*|TWENTY_*|OFFICE_*|AWS_*|SENDGRID_*|POSTMARK_*|MAILGUN_*|BREVO_*|\
      META_*|FACEBOOK_*)
        set_laravel_env_value "$key" "$value"
        ;;
    esac
  done
}

if [ -d .env ]; then
  rm -rf .env
fi

touch .env

set_laravel_env_value APP_NAME "${APP_NAME:-CreditSoft}"
set_laravel_env_value APP_ENV "${APP_ENV:-production}"
set_laravel_env_value APP_KEY "$APP_KEY"
set_laravel_env_value APP_DEBUG "${APP_DEBUG:-false}"
set_laravel_env_value APP_URL "${APP_URL:-http://127.0.0.1:8001}"
set_laravel_env_value CREDITSOFT_APP_VERSION "${CREDITSOFT_APP_VERSION:-}"
set_laravel_env_value CREDITSOFT_APP_BUILD "${CREDITSOFT_APP_BUILD:-}"
set_laravel_env_value DB_CONNECTION "${DB_CONNECTION:-sqlite}"
set_laravel_env_value DB_HOST "${DB_HOST:-}"
set_laravel_env_value DB_PORT "${DB_PORT:-}"
set_laravel_env_value DB_DATABASE "${DB_DATABASE:-}"
set_laravel_env_value DB_USERNAME "${DB_USERNAME:-}"
set_laravel_env_value DB_PASSWORD "${DB_PASSWORD:-}"
set_laravel_env_value DB_SSLMODE "${DB_SSLMODE:-}"
set_laravel_env_value CACHE_STORE "${CACHE_STORE:-database}"
set_laravel_env_value SESSION_DRIVER "${SESSION_DRIVER:-database}"
set_laravel_env_value SESSION_ENCRYPT "${SESSION_ENCRYPT:-false}"
set_laravel_env_value SESSION_DOMAIN "${SESSION_DOMAIN:-null}"
set_laravel_env_value SESSION_LIFETIME "${SESSION_LIFETIME:-120}"
set_laravel_env_value SESSION_PATH "${SESSION_PATH:-/}"
set_laravel_env_value QUEUE_CONNECTION "${QUEUE_CONNECTION:-database}"
sync_laravel_env_family_values

if [ "${DB_CONNECTION:-sqlite}" = "pgsql" ]; then
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
