#!/usr/bin/env bash
set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BROWSER_DIR="$PLUGIN_DIR/tests/browser"
# shellcheck source=../lab/scripts/lib.sh
source "$PLUGIN_DIR/tests/lab/scripts/lib.sh"
# lab lib is fail-fast; browser runner records cleanup/audit failures explicitly.
set +e
set -uo pipefail

FAILED=0
FIXTURES_READY=0
RUN_ID="$(printf '%08x%08x' "$RANDOM" "$RANDOM")"
CONTEXT_FILE="$(mktemp -t wpsr-browser-context.XXXXXX)"

cleanup() {
    local code=0
    if [ "$FIXTURES_READY" -eq 1 ]; then
        bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/browser/fixtures.php cleanup "$RUN_ID" || code=1
        FIXTURES_READY=0
    fi
    wpsr_lab_compose exec -T wordpress rm -f /tmp/wpsr-browser-outbound.log >/dev/null 2>&1 || true
    rm -f "$CONTEXT_FILE"
    [ "$code" -eq 0 ] || FAILED=1
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

wpsr_lab_require_docker
wpsr_lab_compose up --detach --wait db wordpress >/dev/null
if ! wpsr_lab_wp core is-installed >/dev/null 2>&1; then
    echo 'Browser lab missing. Run: bash tests/lab/scripts/setup.sh' >&2
    exit 1
fi
if wpsr_lab_wp plugin is-active wp-social-ninja-pro >/dev/null 2>&1; then
    echo 'Browser tier requires core-alone lab; Pro is active.' >&2
    exit 1
fi

node "$BROWSER_DIR/route-coverage.js" || exit 1

if [ ! -f "$BROWSER_DIR/node_modules/playwright/package.json" ]; then
    echo 'Installing tests-local browser dependencies...'
    npm ci --prefix "$BROWSER_DIR" --ignore-scripts --no-audit --no-fund || exit 1
fi
if ! node -e "const fs=require('fs'),{chromium}=require('$BROWSER_DIR/node_modules/playwright');process.exit(fs.existsSync(chromium.executablePath())?0:1)"; then
    "$BROWSER_DIR/node_modules/.bin/playwright" install chromium || exit 1
fi
node "$BROWSER_DIR/build-assets.js" || exit 1

# Prove separate-process lab boundaries before browser navigation, then clear proof audit.
wpsr_lab_compose exec -T wordpress rm -f /tmp/wpsr-browser-outbound.log
wpsr_lab_compose exec -T wordpress php -r '
require "/var/www/html/wp-load.php";
$http = wp_remote_get("https://wpsr-browser-guard-proof.invalid/http");
$mail = wp_mail("nobody@example.test", "WPSR guard proof", "blocked");
if (!is_wp_error($http) || $http->get_error_code() !== "wpsr_lab_http_blocked" || $mail !== true) { exit(1); }
' || { echo 'Lab outbound guard proof failed.' >&2; exit 1; }
GUARD_LINES="$(wpsr_lab_compose exec -T wordpress sh -c 'test -f /tmp/wpsr-browser-outbound.log && wc -l < /tmp/wpsr-browser-outbound.log || echo 0' | tr -d '[:space:]')"
if [ "$GUARD_LINES" != '2' ]; then
    echo "Lab outbound guard proof expected 2 audit rows; got $GUARD_LINES." >&2
    exit 1
fi
echo 'Lab outbound guard proof: HTTP + mail blocked and audited.'
wpsr_lab_compose exec -T wordpress rm -f /tmp/wpsr-browser-outbound.log

SETUP_OUTPUT="$(bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/browser/fixtures.php setup "$RUN_ID" 2>&1)"
SETUP_CODE=$?
if [ "$SETUP_CODE" -ne 0 ]; then
    printf '%s\n' "$SETUP_OUTPUT" >&2
    exit "$SETUP_CODE"
fi
printf 'Ephemeral browser admin and owned route fixtures ready: %s\n' "$RUN_ID"
CONTEXT_JSON="$(printf '%s\n' "$SETUP_OUTPUT" | sed -n 's/^WPSR_BROWSER_CONTEXT=//p' | tail -n 1)"
if [ -z "$CONTEXT_JSON" ]; then
    echo 'Browser fixture setup returned no context.' >&2
    exit 1
fi
printf '%s\n' "$CONTEXT_JSON" > "$CONTEXT_FILE"
chmod 600 "$CONTEXT_FILE"
FIXTURES_READY=1

WPSR_BROWSER_CONTEXT_FILE="$CONTEXT_FILE" node "$BROWSER_DIR/run-smoke.js"
BROWSER_CODE=$?
[ "$BROWSER_CODE" -eq 0 ] || FAILED=1

CLEANUP_OUTPUT="$(bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/browser/fixtures.php cleanup "$RUN_ID" 2>&1)"
CLEANUP_CODE=$?
printf '%s\n' "$CLEANUP_OUTPUT"
FIXTURES_READY=0
if [ "$CLEANUP_CODE" -ne 0 ] || ! printf '%s\n' "$CLEANUP_OUTPUT" | grep -q '^WPSR_BROWSER_CLEANUP={"clean":true'; then
    echo 'Browser fixture cleanup/fingerprint gate failed.' >&2
    FAILED=1
fi

OUTBOUND_LOG="$(wpsr_lab_compose exec -T wordpress sh -c 'test -s /tmp/wpsr-browser-outbound.log && cat /tmp/wpsr-browser-outbound.log || true')"
if [ -n "$OUTBOUND_LOG" ]; then
    echo 'Unexpected server-side outbound attempts during browser tier:' >&2
    printf '%s\n' "$OUTBOUND_LOG" >&2
    FAILED=1
else
    echo 'Server outbound audit: HTTP 0, mail 0.'
fi

exit "$FAILED"
