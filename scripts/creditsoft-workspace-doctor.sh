#!/usr/bin/env bash
set -euo pipefail

CANONICAL_PATH="${CREDITSOFT_CANONICAL_PATH:-/Users/mmurphy/Code/CreditSoft}"
DESKTOP_PATH="${CREDITSOFT_DESKTOP_PATH:-/Users/mmurphy/Desktop/CreditSoft}"
EXPECTED_REMOTE="${CREDITSOFT_EXPECTED_REMOTE:-github.com/matthewxmurphy/creditsoft.app.git}"
MODE="${1:-check}"

failures=0

say() {
  printf '%s\n' "$*"
}

pass() {
  say "PASS $*"
}

warn() {
  say "WARN $*"
}

fail() {
  say "FAIL $*"
  failures=$((failures + 1))
}

real_path() {
  perl -MCwd=realpath -e 'print realpath($ARGV[0]) // $ARGV[0]' "$1"
}

check_workspace() {
  say "CreditSoft workspace doctor"
  say "Canonical: ${CANONICAL_PATH}"
  say "Desktop:   ${DESKTOP_PATH}"
  say

  if [[ -d "${CANONICAL_PATH}" ]]; then
    pass "canonical folder exists"
  else
    fail "canonical folder is missing: ${CANONICAL_PATH}"
    return
  fi

  local canonical_real
  canonical_real="$(real_path "${CANONICAL_PATH}")"

  if [[ -L "${DESKTOP_PATH}" ]]; then
    local desktop_real
    desktop_real="$(real_path "${DESKTOP_PATH}")"

    if [[ "${desktop_real}" == "${canonical_real}" ]]; then
      pass "Desktop shortcut points to canonical folder"
    else
      fail "Desktop shortcut points to ${desktop_real}, expected ${canonical_real}"
    fi
  elif [[ -e "${DESKTOP_PATH}" ]]; then
    fail "Desktop path exists but is not a symlink; archive it before using Desktop/CreditSoft"
  else
    warn "Desktop shortcut is missing"
  fi

  if [[ -e "${CANONICAL_PATH}/.git" ]]; then
    pass "Git metadata exists"
  else
    fail "Git metadata is missing at ${CANONICAL_PATH}/.git"
  fi

  if git -C "${CANONICAL_PATH}" rev-parse --show-toplevel >/dev/null 2>&1; then
    local git_root
    git_root="$(git -C "${CANONICAL_PATH}" rev-parse --show-toplevel)"

    if [[ "$(real_path "${git_root}")" == "${canonical_real}" ]]; then
      pass "Git root matches canonical folder"
    else
      fail "Git root is ${git_root}, expected ${CANONICAL_PATH}"
    fi

    local remote
    remote="$(git -C "${CANONICAL_PATH}" remote get-url origin 2>/dev/null || true)"

    if [[ "${remote}" == *"${EXPECTED_REMOTE}"* ]]; then
      pass "origin remote is CreditSoft"
    elif [[ -n "${remote}" ]]; then
      fail "origin remote is ${remote}, expected ${EXPECTED_REMOTE}"
    else
      fail "origin remote is not configured"
    fi
  else
    fail "Git cannot open the canonical folder"
  fi

  local desktop_drift
  desktop_drift="$(find /Users/mmurphy/Desktop -maxdepth 1 -type d \
    \( -name 'CreditSoft.icloud-drift-archive-*' -o -name 'CreditSoft-legacy-0x-quarantine-*' \) \
    -print 2>/dev/null | sort || true)"

  if [[ -n "${desktop_drift}" ]]; then
    warn "old CreditSoft drift/quarantine folders still exist:"
    say "${desktop_drift}"
  else
    pass "no Desktop drift/quarantine folders found"
  fi

  say

  if [[ "${failures}" -gt 0 ]]; then
    say "Workspace doctor found ${failures} blocking issue(s)."
    return 1
  fi

  say "Workspace doctor passed."
}

snapshot_workspace() {
  check_workspace

  local stamp backup_root target
  stamp="$(date +%Y%m%d-%H%M%S)"
  backup_root="${CREDITSOFT_SNAPSHOT_ROOT:-/Users/mmurphy/Backups/CreditSoft/snapshots}"
  target="${backup_root}/${stamp}"

  mkdir -p "${target}"

  rsync -a --delete \
    --exclude '/node_modules/' \
    --exclude '/site-astro/node_modules/' \
    --exclude '/updates-astro/node_modules/' \
    --exclude '/vendor/' \
    --exclude '/storage/framework/cache/' \
    --exclude '/storage/framework/sessions/' \
    --exclude '/storage/framework/views/' \
    --exclude '/storage/logs/' \
    "${CANONICAL_PATH}/" "${target}/"

  say "Snapshot written to ${target}"
}

repair_desktop_symlink() {
  if [[ ! -d "${CANONICAL_PATH}" ]]; then
    fail "canonical folder is missing: ${CANONICAL_PATH}"
    return 1
  fi

  if [[ -L "${DESKTOP_PATH}" ]]; then
    rm "${DESKTOP_PATH}"
  elif [[ -e "${DESKTOP_PATH}" ]]; then
    local archive
    archive="${DESKTOP_PATH}.pre-symlink-$(date +%Y%m%d-%H%M%S)"
    mv "${DESKTOP_PATH}" "${archive}"
    say "Archived existing Desktop path to ${archive}"
  fi

  ln -s "${CANONICAL_PATH}" "${DESKTOP_PATH}"
  say "Desktop shortcut now points to ${CANONICAL_PATH}"
}

case "${MODE}" in
  check)
    check_workspace
    ;;
  snapshot)
    snapshot_workspace
    ;;
  repair-desktop-symlink)
    repair_desktop_symlink
    ;;
  *)
    say "Usage: $0 [check|snapshot|repair-desktop-symlink]"
    exit 64
    ;;
esac
