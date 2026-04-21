#!/usr/bin/env bash
set -euo pipefail

export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

container="${CREDITSOFT_INTRANET_CONTAINER:-creditsoft-intranet-intranet-1}"
target_path="${CREDITSOFT_HOST_DIAGNOSTICS_PATH:-/var/www/html/storage/app/private/host-diagnostics.json}"
host_disk_path="${CREDITSOFT_HOST_DISK_PATH:-/Volumes/MacHome}"

if [[ ! -d "$host_disk_path" ]]; then
    host_disk_path="/"
fi

json_escape() {
    printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

human_to_bytes() {
    local raw="$1"
    local number unit

    number="$(printf '%s' "$raw" | sed -E 's/^[[:space:]]*([0-9.]+).*/\1/')"
    unit="$(printf '%s' "$raw" | sed -E 's/^[[:space:]]*[0-9.]+([KMGTP]).*/\1/' | tr '[:lower:]' '[:upper:]')"

    if [[ ! "$number" =~ ^[0-9.]+$ || ! "$unit" =~ ^[KMGTP]$ ]]; then
        printf '0\n'
        return
    fi

    awk -v number="$number" -v unit="$unit" '
        BEGIN {
            powers["K"] = 1
            powers["M"] = 2
            powers["G"] = 3
            powers["T"] = 4
            powers["P"] = 5
            bytes = number
            for (i = 0; i < powers[unit]; i++) {
                bytes *= 1024
            }
            printf "%.0f\n", bytes
        }
    '
}

now="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
host_name="$(hostname 2>/dev/null || uname -n)"
product_name="$(sw_vers -productName 2>/dev/null || printf 'macOS')"
product_version="$(sw_vers -productVersion 2>/dev/null || true)"
build_version="$(sw_vers -buildVersion 2>/dev/null || true)"
os_family="$product_name"

if [[ -n "$product_version" ]]; then
    os_family="$os_family $product_version"
fi

if [[ -n "$build_version" ]]; then
    os_family="$os_family ($build_version)"
fi

kernel="$(uname -r)"
architecture="$(uname -m)"
cpu_cores="$(sysctl -n hw.logicalcpu 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || printf '1')"
memory_total="$(sysctl -n hw.memsize 2>/dev/null || printf '0')"
vm_stat_output="$(vm_stat 2>/dev/null || true)"
page_size="$(printf '%s\n' "$vm_stat_output" | awk '/page size of/ { gsub(/[^0-9]/, "", $0); print $0; exit }')"

if [[ -z "$page_size" || "$page_size" -le 0 ]]; then
    page_size="4096"
fi

vm_value() {
    local label="$1"

    printf '%s\n' "$vm_stat_output" | awk -v label="$label" '
        index($0, label) == 1 {
            value = $0
            sub(/^.*:[[:space:]]*/, "", value)
            gsub(/[^0-9]/, "", value)
            printf "%.0f\n", value + 0
            exit
        }
    '
}

free_pages="$(vm_value "Pages free")"
speculative_pages="$(vm_value "Pages speculative")"
inactive_pages="$(vm_value "Pages inactive")"
purgeable_pages="$(vm_value "Pages purgeable")"
file_backed_pages="$(vm_value "File-backed pages")"
anonymous_pages="$(vm_value "Anonymous pages")"
compressor_pages="$(vm_value "Pages occupied by compressor")"
pageouts="$(vm_value "Pageouts")"
swapins="$(vm_value "Swapins")"
swapouts="$(vm_value "Swapouts")"

free_pages="${free_pages:-0}"
speculative_pages="${speculative_pages:-0}"
inactive_pages="${inactive_pages:-0}"
purgeable_pages="${purgeable_pages:-0}"
file_backed_pages="${file_backed_pages:-0}"
anonymous_pages="${anonymous_pages:-0}"
compressor_pages="${compressor_pages:-0}"
pageouts="${pageouts:-0}"
swapins="${swapins:-0}"
swapouts="${swapouts:-0}"

basic_free_pages=$((free_pages + speculative_pages))
reclaimable_pages=$((basic_free_pages + inactive_pages + purgeable_pages))
memory_free=$((basic_free_pages * page_size))
memory_reclaimable=$((reclaimable_pages * page_size))
file_backed_bytes=$((file_backed_pages * page_size))
anonymous_bytes=$((anonymous_pages * page_size))
compressor_bytes=$((compressor_pages * page_size))

memory_pressure_output="$(memory_pressure 2>/dev/null || true)"
pressure_free_percent="$(printf '%s\n' "$memory_pressure_output" | awk -F ':' '
    /System-wide memory free percentage/ {
        value = $2
        gsub(/[^0-9.]/, "", value)
        print value
        exit
    }
')"

if [[ "$pressure_free_percent" =~ ^[0-9]+([.][0-9]+)?$ ]]; then
    memory_available="$(awk -v total="$memory_total" -v percent="$pressure_free_percent" 'BEGIN { printf "%.0f\n", total * percent / 100 }')"
else
    memory_available="$memory_reclaimable"
    pressure_free_percent=""
fi

if [[ "$memory_available" -gt "$memory_total" ]]; then
    memory_available="$memory_total"
fi

if [[ "$memory_available" -lt "$memory_free" ]]; then
    memory_available="$memory_free"
fi

memory_raw_used=$((memory_total - memory_free))
memory_used=$((memory_total - memory_available))

if [[ "$memory_used" -lt 0 ]]; then
    memory_used="0"
fi

if [[ "$memory_raw_used" -lt 0 ]]; then
    memory_raw_used="0"
fi

swap_output="$(sysctl vm.swapusage 2>/dev/null || true)"
swap_total_token="$(printf '%s\n' "$swap_output" | sed -E 's/.*total = ([0-9.]+[KMGTP]).*/\1/')"
swap_used_token="$(printf '%s\n' "$swap_output" | sed -E 's/.*used = ([0-9.]+[KMGTP]).*/\1/')"
swap_free_token="$(printf '%s\n' "$swap_output" | sed -E 's/.*free = ([0-9.]+[KMGTP]).*/\1/')"
swap_total="$(human_to_bytes "$swap_total_token")"
swap_used="$(human_to_bytes "$swap_used_token")"
swap_free="$(human_to_bytes "$swap_free_token")"

pressure_level="unknown"
pressure_label="Pressure unknown"

if [[ "$pressure_free_percent" =~ ^[0-9]+([.][0-9]+)?$ ]]; then
    pressure_level="$(awk -v percent="$pressure_free_percent" -v swap="$swap_used" '
        BEGIN {
            if (swap == 0 && percent >= 25) {
                print "healthy"
            } else if (percent >= 15 && swap < 1073741824) {
                print "watch"
            } else if (percent >= 8) {
                print "pressured"
            } else {
                print "critical"
            }
        }
    ')"
else
    if [[ "$swap_used" -eq 0 && "$swapouts" -eq 0 ]]; then
        pressure_level="healthy"
    elif [[ "$swap_used" -lt 1073741824 ]]; then
        pressure_level="watch"
    else
        pressure_level="pressured"
    fi
fi

case "$pressure_level" in
    healthy)
        pressure_label="Healthy"
        ;;
    watch)
        pressure_label="Watch"
        ;;
    pressured)
        pressure_label="Pressured"
        ;;
    critical)
        pressure_label="Critical"
        ;;
esac

disk_values="$(df -k "$host_disk_path" | tail -n 1 | awk '{ printf "%.0f %.0f %.0f\n", $2 * 1024, $3 * 1024, $4 * 1024 }')"
read -r disk_total disk_used disk_free <<< "$disk_values"

network_values="$(netstat -ibn 2>/dev/null | awk '
    NR == 1 {
        for (i = 1; i <= NF; i++) {
            if ($i == "Ibytes") ibytes = i
            if ($i == "Obytes") obytes = i
        }
        next
    }
    ibytes && obytes && $1 !~ /^lo/ && $ibytes ~ /^[0-9]+$/ && $obytes ~ /^[0-9]+$/ {
        if ($ibytes > rx[$1]) rx[$1] = $ibytes
        if ($obytes > tx[$1]) tx[$1] = $obytes
    }
    END {
        for (name in rx) {
            rx_total += rx[name]
            tx_total += tx[name]
        }
        printf "%.0f %.0f\n", rx_total, tx_total
    }
')"
read -r rx_bytes tx_bytes <<< "$network_values"

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

{
    printf '{\n'
    printf '  "captured_at": "%s",\n' "$(json_escape "$now")"
    printf '  "machine": {\n'
    printf '    "hostname": "%s",\n' "$(json_escape "$host_name")"
    printf '    "os_family": "%s",\n' "$(json_escape "$os_family")"
    printf '    "kernel": "%s",\n' "$(json_escape "$kernel")"
    printf '    "architecture": "%s",\n' "$(json_escape "$architecture")"
    printf '    "cpu_cores": %s\n' "$cpu_cores"
    printf '  },\n'
    printf '  "memory": {\n'
    printf '    "total_bytes": %s,\n' "$memory_total"
    printf '    "used_bytes": %s,\n' "$memory_used"
    printf '    "free_bytes": %s,\n' "$memory_free"
    printf '    "available_bytes": %s,\n' "$memory_available"
    printf '    "reclaimable_bytes": %s,\n' "$memory_reclaimable"
    printf '    "raw_used_bytes": %s,\n' "$memory_raw_used"
    printf '    "file_backed_bytes": %s,\n' "$file_backed_bytes"
    printf '    "anonymous_bytes": %s,\n' "$anonymous_bytes"
    printf '    "compressor_bytes": %s,\n' "$compressor_bytes"
    printf '    "pressure_free_percent": %s,\n' "${pressure_free_percent:-0}"
    printf '    "pressure_level": "%s",\n' "$(json_escape "$pressure_level")"
    printf '    "pressure_label": "%s",\n' "$(json_escape "$pressure_label")"
    printf '    "pageouts": %s,\n' "$pageouts"
    printf '    "swapins": %s,\n' "$swapins"
    printf '    "swapouts": %s,\n' "$swapouts"
    printf '    "platform_note": "macOS available memory uses memory_pressure when available; high cached memory is not treated as a hardware failure."\n'
    printf '  },\n'
    printf '  "swap": {\n'
    printf '    "total_bytes": %s,\n' "$swap_total"
    printf '    "used_bytes": %s,\n' "$swap_used"
    printf '    "free_bytes": %s\n' "$swap_free"
    printf '  },\n'
    printf '  "disk": {\n'
    printf '    "path": "%s",\n' "$(json_escape "$host_disk_path")"
    printf '    "total_bytes": %s,\n' "$disk_total"
    printf '    "used_bytes": %s,\n' "$disk_used"
    printf '    "free_bytes": %s\n' "$disk_free"
    printf '  },\n'
    printf '  "network": {\n'
    printf '    "rx_bytes": %s,\n' "${rx_bytes:-0}"
    printf '    "tx_bytes": %s\n' "${tx_bytes:-0}"
    printf '  }\n'
    printf '}\n'
} > "$tmp"

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is not available on this Mac."
    exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$container"; then
    echo "Docker container $container is not running."
    exit 1
fi

docker exec -i -e TARGET_PATH="$target_path" "$container" sh -c \
    'mkdir -p "$(dirname "$TARGET_PATH")" && cat > "$TARGET_PATH" && chmod 0644 "$TARGET_PATH"' < "$tmp"

if [[ "${CREDITSOFT_CAPTURE_AFTER_HOST_DIAGNOSTICS:-1}" != "0" ]]; then
    docker exec "$container" php artisan creditsoft:diagnostics:capture >/dev/null 2>&1 || true
fi

echo "Wrote host diagnostics for $host_name to $container:$target_path"
