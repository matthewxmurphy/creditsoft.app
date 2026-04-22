#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker/remote-browser/docker-compose.yml"
STATE_ROOT="${CREDITSOFT_REMOTE_BROWSER_HOME:-$HOME/.creditsoft/remote-browsers}"

usage() {
    cat <<'USAGE'
Usage:
  scripts/creditsoft-remote-browser-client.sh up [client-slug]
  scripts/creditsoft-remote-browser-client.sh down [client-slug]
  scripts/creditsoft-remote-browser-client.sh status [client-slug]
  scripts/creditsoft-remote-browser-client.sh logs [client-slug]
  scripts/creditsoft-remote-browser-client.sh url [client-slug]

This starts a client-owned remote Chrome workspace:
- Tailscale runs inside the Docker namespace using the client's auth key.
- ngrok runs inside the same namespace using the client's authtoken.
- The host tailnet socket, host ngrok config, and host network are not mounted.
USAGE
}

slugify() {
    printf '%s' "$1" \
        | tr '[:upper:]' '[:lower:]' \
        | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//'
}

prompt() {
    local label="$1"
    local default="${2:-}"
    local value

    if [[ -n "$default" ]]; then
        read -r -p "$label [$default]: " value
        printf '%s' "${value:-$default}"
    else
        read -r -p "$label: " value
        printf '%s' "$value"
    fi
}

prompt_secret() {
    local label="$1"
    local value

    read -r -s -p "$label: " value
    printf '\n' >&2
    printf '%s' "$value"
}

random_password() {
    openssl rand -base64 24 | tr -d '=+/' | cut -c1-24
}

client_slug_from_arg() {
    local supplied="${1:-}"

    if [[ -n "$supplied" ]]; then
        slugify "$supplied"
        return
    fi

    if [[ -f "$STATE_ROOT/.last-client" ]]; then
        cat "$STATE_ROOT/.last-client"
        return
    fi

    printf ''
}

env_file_for() {
    printf '%s/%s/.env' "$STATE_ROOT" "$1"
}

state_dir_for() {
    printf '%s/%s' "$STATE_ROOT" "$1"
}

write_ngrok_config() {
    local path="$1"
    local authtoken="$2"
    local api_key="$3"
    local domain="$4"

    umask 077
    {
        printf 'version: "3"\n'
        printf 'agent:\n'
        printf '  authtoken: "%s"\n' "$authtoken"
        if [[ -n "$api_key" ]]; then
            printf '  api_key: "%s"\n' "$api_key"
        fi
        printf 'endpoints:\n'
        printf '  - name: creditsoft-client-browser\n'
        if [[ -n "$domain" ]]; then
            printf '    url: "%s"\n' "$domain"
        fi
        printf '    upstream:\n'
        printf '      url: "http://127.0.0.1:3000"\n'
    } > "$path"
}

create_env() {
    local slug="$1"
    local dir
    local env_file

    dir="$(state_dir_for "$slug")"
    env_file="$(env_file_for "$slug")"
    mkdir -p "$dir"/{chromium,downloads,tailscale}

    local client_label
    local tail_hostname
    local tail_key
    local ngrok_token
    local ngrok_api_key
    local ngrok_domain
    local browser_user
    local browser_password
    local start_url

    client_label="$(prompt "Client label" "$slug")"
    tail_hostname="$(prompt "Client Tailscale hostname for this browser" "creditsoft-${slug}-browser")"
    tail_key="$(prompt_secret "Client Tailscale auth key")"
    ngrok_token="$(prompt_secret "Client ngrok authtoken")"
    ngrok_api_key="$(prompt_secret "Client ngrok API key (optional, press Enter to skip)")"
    ngrok_domain="$(prompt "Client ngrok reserved domain/url (optional)" "")"
    browser_user="$(prompt "Remote browser login user" "creditsoft")"
    browser_password="$(prompt "Remote browser login password" "$(random_password)")"
    start_url="$(prompt "Start URL inside Ryzen" "http://host.docker.internal:8877/dashboard?source=client-remote-browser")"

    umask 077
    cat > "$env_file" <<ENV
REMOTE_BROWSER_PROJECT=creditsoft-browser-${slug}
REMOTE_BROWSER_CONTAINER_PREFIX=creditsoft-browser-${slug}
REMOTE_BROWSER_STATE_DIR=${dir}
REMOTE_BROWSER_NGROK_CONFIG=${dir}/ngrok.yml
REMOTE_BROWSER_PUID=$(id -u)
REMOTE_BROWSER_PGID=$(id -g)
REMOTE_BROWSER_USER=${browser_user}
REMOTE_BROWSER_PASSWORD=${browser_password}
REMOTE_BROWSER_TITLE=CreditSoft Remote Browser - ${client_label}
REMOTE_BROWSER_START_URL=${start_url}
TAILSCALE_AUTHKEY=${tail_key}
TAILSCALE_HOSTNAME=${tail_hostname}
TAILSCALE_EXTRA_ARGS=--accept-routes=false --ssh=false
TZ=${TZ:-America/Los_Angeles}
ENV

    write_ngrok_config "$dir/ngrok.yml" "$ngrok_token" "$ngrok_api_key" "$ngrok_domain"
    printf '%s' "$slug" > "$STATE_ROOT/.last-client"

    cat <<SUMMARY

Saved client-owned remote browser config:
  $env_file

Remote browser credentials:
  user: $browser_user
  password: $browser_password
SUMMARY
}

compose() {
    local slug="$1"
    local env_file

    env_file="$(env_file_for "$slug")"
    if [[ ! -f "$env_file" ]]; then
        echo "No config exists for '$slug'. Run: $0 up $slug" >&2
        exit 1
    fi

    docker compose --env-file "$env_file" -f "$COMPOSE_FILE" -p "creditsoft-browser-${slug}" "${@:2}"
}

print_url() {
    local slug="$1"
    local prefix="creditsoft-browser-${slug}-ngrok"

    docker logs "$prefix" 2>&1 \
        | grep -Eo 'https://[^[:space:]]+' \
        | grep -E 'ngrok|ngrok-free|ngrok.app' \
        | tail -n 1 \
        || true
}

cmd="${1:-}"
slug="$(client_slug_from_arg "${2:-}")"

case "$cmd" in
    up)
        if [[ -z "$slug" ]]; then
            slug="$(slugify "$(prompt "Client slug" "client1")")"
        fi
        mkdir -p "$STATE_ROOT"
        if [[ ! -f "$(env_file_for "$slug")" ]]; then
            create_env "$slug"
        fi
        compose "$slug" up -d
        echo
        echo "Waiting for ngrok URL..."
        sleep 4
        print_url "$slug"
        ;;
    down)
        [[ -n "$slug" ]] || { echo "Client slug required." >&2; exit 1; }
        compose "$slug" down
        ;;
    status)
        [[ -n "$slug" ]] || { echo "Client slug required." >&2; exit 1; }
        compose "$slug" ps
        ;;
    logs)
        [[ -n "$slug" ]] || { echo "Client slug required." >&2; exit 1; }
        compose "$slug" logs -f --tail=160
        ;;
    url)
        [[ -n "$slug" ]] || { echo "Client slug required." >&2; exit 1; }
        print_url "$slug"
        ;;
    *)
        usage
        exit 1
        ;;
esac
