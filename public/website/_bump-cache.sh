#!/usr/bin/env bash
#
# SHF marketing site — cache-bust helper
# ----------------------------------------
# Bumps the server-side asset version (so every ?v= query changes) and
# optionally purges the Cloudflare edge cache.
#
# Works on any POSIX shell with `curl`:
#   • Git Bash on Windows
#   • WSL / Linux / macOS Terminal
#
# One-time setup:
#   1. Change CACHE_BUST_KEY below to match the value you set in cache-bust.php.
#   2. (Optional) Fill CF_ZONE_ID + CF_API_TOKEN for Cloudflare edge purge.
#   3. Make executable:   chmod +x _bump-cache.sh
#
# Usage:
#   ./_bump-cache.sh                   # bump version + clear rate-limit cache
#   ./_bump-cache.sh --keep-rate       # bump version, keep rate counters
#   ./_bump-cache.sh --verbose         # dump raw response bodies
#   SHF_CACHE_BUST_KEY=xxx ./_bump-cache.sh   # override the secret via env
#

set -euo pipefail

# ==========================================================================
# CONFIG — edit these, or override via environment variables
# ==========================================================================

# Your live site URL (no trailing slash)
SITE_URL="${SHF_SITE_URL:-https://shfworld.com}"

# Must match CACHE_BUST_KEY inside cache-bust.php on the server.
# CHANGE THIS BEFORE COMMITTING OR SHARING THIS FILE!
CACHE_BUST_KEY="${SHF_CACHE_BUST_KEY:-shf-refresh-2026}"

# Cloudflare edge-cache purge (OPTIONAL — leave blank to skip)
#   Zone ID  : Cloudflare Dashboard → your domain → Overview → sidebar bottom-right
#   API Token: Cloudflare Dashboard → My Profile → API Tokens → Create
#              → "Zone.Cache Purge" permission on your zone
CF_ZONE_ID="${CF_ZONE_ID:-}"
CF_API_TOKEN="${CF_API_TOKEN:-}"

VERBOSE=0
KEEP_RATE=0
for arg in "$@"; do
    case "$arg" in
        -v|--verbose)   VERBOSE=1 ;;
        -k|--keep-rate) KEEP_RATE=1 ;;
        -h|--help)
            sed -n '1,30p' "$0"; exit 0 ;;
    esac
done

# ==========================================================================
# Helpers
# ==========================================================================
if [ -t 1 ]; then
    BOLD='\033[1m'; ORANGE='\033[38;5;208m'; GREEN='\033[32m'; RED='\033[31m'; GREY='\033[90m'; RESET='\033[0m'
else
    BOLD=''; ORANGE=''; GREEN=''; RED=''; GREY=''; RESET=''
fi

say()  { printf "%b\n" "$*"; }
ok()   { say "${GREEN}✓${RESET} $*"; }
err()  { say "${RED}✖${RESET} $*" >&2; }
info() { say "${GREY}$*${RESET}"; }

if ! command -v curl >/dev/null 2>&1; then
    err "curl is required but not installed."
    exit 1
fi

# ==========================================================================
# Step 1 — bump the server-side asset version
# ==========================================================================

say "${BOLD}${ORANGE}SHF — Cache Bust${RESET}"
info "Target: ${SITE_URL}"
echo

URL="${SITE_URL}/cache-bust?key=$(printf '%s' "${CACHE_BUST_KEY}" | sed 's/ /%20/g')&format=json"
if [ "${KEEP_RATE}" -eq 1 ]; then
    URL="${URL}&keep-rate=1"
    info "(Rate-limit counters will be preserved via --keep-rate.)"
else
    info "(Rate-limit counters will be cleared — pass --keep-rate to preserve.)"
fi

say "→ Calling cache-bust endpoint..."
set +e
HTTP_RESPONSE=$(curl -sS -m 20 -w $'\n---HTTP_CODE---\n%{http_code}' "${URL}")
CURL_EXIT=$?
set -e

if [ ${CURL_EXIT} -ne 0 ]; then
    err "curl failed (exit ${CURL_EXIT}). Is the server reachable?"
    exit 2
fi

HTTP_CODE="${HTTP_RESPONSE##*---HTTP_CODE---$'\n'}"
HTTP_BODY="${HTTP_RESPONSE%$'\n'---HTTP_CODE---$'\n'*}"

if [ "${HTTP_CODE}" != "200" ]; then
    err "Cache-bust failed. HTTP ${HTTP_CODE}"
    echo "${HTTP_BODY}"
    if [ "${HTTP_CODE}" = "403" ]; then
        err "Likely cause: CACHE_BUST_KEY in this script doesn't match the one inside cache-bust.php on the server."
    fi
    exit 3
fi

NEW_VERSION=$(printf '%s' "${HTTP_BODY}" | grep -oE '"new_version":"[^"]+"' | head -1 | cut -d'"' -f4)
OPCACHE=$(printf '%s' "${HTTP_BODY}" | grep -oE '"opcache_cleared":(true|false)' | head -1 | cut -d':' -f2)
RATE_CLEARED=$(printf '%s' "${HTTP_BODY}" | grep -oE '"rate_limit_cleared":(null|[0-9]+)' | head -1 | cut -d':' -f2)

ok "Asset version bumped → ${BOLD}${NEW_VERSION:-?}${RESET}"
info "OPcache cleared: ${OPCACHE:-unknown}"
if [ "${KEEP_RATE}" -eq 1 ]; then
    info "Rate-limit cache: preserved"
else
    if [ -n "${RATE_CLEARED}" ] && [ "${RATE_CLEARED}" != "null" ]; then
        ok "Rate-limit cache: ${BOLD}${RATE_CLEARED}${RESET} counter file(s) removed"
    else
        info "Rate-limit cache: server did not confirm (check manually)"
    fi
fi

if [ "${VERBOSE}" -eq 1 ]; then
    echo; info "--- raw response ---"
    echo "${HTTP_BODY}"
fi
echo

# ==========================================================================
# Step 2 — purge Cloudflare edge cache (optional)
# ==========================================================================

if [ -n "${CF_ZONE_ID}" ] && [ -n "${CF_API_TOKEN}" ]; then
    say "→ Purging Cloudflare edge cache..."
    CF_RESPONSE=$(curl -sS -m 20 -X POST \
        "https://api.cloudflare.com/client/v4/zones/${CF_ZONE_ID}/purge_cache" \
        -H "Authorization: Bearer ${CF_API_TOKEN}" \
        -H "Content-Type: application/json" \
        --data '{"purge_everything":true}' || echo '{"success":false,"errors":[{"message":"curl failed"}]}')

    if printf '%s' "${CF_RESPONSE}" | grep -q '"success":true'; then
        ok "Cloudflare edge cache purged."
    else
        err "Cloudflare purge failed."
        echo "${CF_RESPONSE}"
    fi

    if [ "${VERBOSE}" -eq 1 ]; then
        echo; info "--- CF response ---"
        echo "${CF_RESPONSE}"
    fi
else
    info "(Skipping Cloudflare purge — set CF_ZONE_ID and CF_API_TOKEN to enable.)"
fi

echo
say "${BOLD}${GREEN}Done.${RESET} Visitors will load fresh CSS, JS and images on next page load."
