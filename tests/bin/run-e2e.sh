#!/usr/bin/env bash
set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BROWSER_DIR="$PLUGIN_DIR/tests/browser"
E2E_DIR="$PLUGIN_DIR/tests/e2e"
# shellcheck source=../lab/scripts/lib.sh
source "$PLUGIN_DIR/tests/lab/scripts/lib.sh"
set +e
set -uo pipefail

FAILED=0
FIXTURES_READY=0
WARM_USER_ID=0
RUN_ID="$(printf '%08x%08x' "$RANDOM" "$RANDOM")"
CONTEXT_FILE="$(mktemp -t wpsr-e2e-context.XXXXXX)"
FILTER=""
if [ "${1:-}" != "" ]; then
    case "$1" in
        filter=*) FILTER="${1#filter=}" ;;
        *) echo "Unknown E2E argument: $1 (use filter=<flow-id>)" >&2; exit 2 ;;
    esac
fi

cleanup() {
    local code=0
    if [ "$WARM_USER_ID" -gt 0 ]; then
        wpsr_lab_wp user delete "$WARM_USER_ID" --reassign=1 --yes >/dev/null 2>&1 || code=1
        WARM_USER_ID=0
    fi
    if [ "$FIXTURES_READY" -eq 1 ]; then
        bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/e2e/fixtures.php cleanup "$RUN_ID" || code=1
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
    echo 'E2E lab missing. Run: bash tests/lab/scripts/setup.sh' >&2
    exit 1
fi
if wpsr_lab_wp plugin is-active wp-social-ninja-pro >/dev/null 2>&1; then
    echo 'E2E tier requires core-alone lab; Pro is active.' >&2
    exit 1
fi

node "$E2E_DIR/flow-coverage.js" || exit 1

if [ ! -f "$BROWSER_DIR/node_modules/playwright/package.json" ]; then
    echo 'Installing tests-local browser dependencies...'
    npm ci --prefix "$BROWSER_DIR" --ignore-scripts --no-audit --no-fund || exit 1
fi
if ! node -e "const fs=require('fs'),{chromium}=require('$BROWSER_DIR/node_modules/playwright');process.exit(fs.existsSync(chromium.executablePath())?0:1)"; then
    "$BROWSER_DIR/node_modules/.bin/playwright" install chromium || exit 1
fi
node "$BROWSER_DIR/build-assets.js" || exit 1

# A brand-new WordPress install performs one-time admin housekeeping (core schedules,
# theme mods, revision/custom-CSS rows, script-compression probe). Establish that
# deterministic baseline before fixture fingerprints. HTTP remains blocked; only exact
# WordPress update-check intents are accepted during this preflight, never during flows.
wpsr_lab_compose exec -T wordpress rm -f /tmp/wpsr-browser-outbound.log
WARM_CONTEXT="$(wpsr_lab_wp eval '
$run_id = "'"$RUN_ID"'";
$login = "wpsrwarm_" . substr($run_id, 0, 18);
$user_id = wp_insert_user([
    "user_login" => $login,
    "user_pass" => wp_generate_password(32, true, true),
    "user_email" => $login . "@example.test",
    "display_name" => "WPSR E2E Baseline Warmup",
    "role" => "administrator",
]);
if (is_wp_error($user_id) || !$user_id) { exit(1); }
$user_id = (int) $user_id;
update_user_meta($user_id, "_wpsr_e2e_warmup", "wpsr-e2e-warmup-" . $run_id);
$expiry = time() + 600;
$cookies = LOGGED_IN_COOKIE . "=" . wp_generate_auth_cookie($user_id, $expiry, "logged_in") . "; ";
$cookies .= AUTH_COOKIE . "=" . wp_generate_auth_cookie($user_id, $expiry, "auth") . "; ";
$cookies .= SECURE_AUTH_COOKIE . "=" . wp_generate_auth_cookie($user_id, $expiry, "secure_auth");
echo wp_json_encode(["user_id" => $user_id, "cookies" => $cookies]);
' 2>/dev/null)"
WARM_USER_ID="$(node -e 'try { process.stdout.write(String(JSON.parse(process.argv[1]).user_id || 0)); } catch (e) { process.stdout.write("0"); }' "$WARM_CONTEXT")"
WARM_COOKIES="$(node -e 'try { process.stdout.write(String(JSON.parse(process.argv[1]).cookies || "")); } catch (e) {}' "$WARM_CONTEXT")"
SITE_URL="$(wpsr_lab_wp option get siteurl 2>/dev/null)"
WARM_URL="$SITE_URL/wp-admin/admin.php?page=wpsocialninja.php"
WARM_PAGE="$(mktemp -t wpsr-e2e-warm.XXXXXX)"
if [ "$WARM_USER_ID" -le 0 ] || [ -z "$WARM_COOKIES" ] || ! curl --fail --silent --show-error --max-time 30 -H "Cookie: $WARM_COOKIES" "$WARM_URL" -o "$WARM_PAGE" || ! grep -Eq "id=['\"]wpsocialreviewsapp['\"]" "$WARM_PAGE"; then
    rm -f "$WARM_PAGE"
    echo 'E2E admin baseline preflight failed.' >&2
    exit 1
fi
COMPRESSION_NONCE="$(sed -n 's/.*var compressionNonce = "\([^"]*\)";.*/\1/p' "$WARM_PAGE" | head -n 1)"
if [ -n "$COMPRESSION_NONCE" ]; then
    if ! curl --fail --silent --show-error --max-time 30 -H "Cookie: $WARM_COOKIES" \
        "$SITE_URL/wp-admin/admin-ajax.php?action=wp-compression-test&test=no&_ajax_nonce=$COMPRESSION_NONCE" \
        -o /dev/null; then
        rm -f "$WARM_PAGE"
        echo 'E2E WordPress compression baseline preflight failed.' >&2
        exit 1
    fi
fi
# Chat lifecycle visits public homepage while authenticated. Block themes may lazily
# create navigation/widget defaults on that first request, so warm same surface under
# baseline administrator before fixture ownership/fingerprints begin.
if ! curl --fail --silent --show-error --max-time 30 -H "Cookie: $WARM_COOKIES" "$SITE_URL/" -o "$WARM_PAGE" || ! grep -qi '</html>' "$WARM_PAGE"; then
    rm -f "$WARM_PAGE"
    echo 'E2E admin baseline preflight failed.' >&2
    exit 1
fi
rm -f "$WARM_PAGE"
# Remove warmup identity before fixture fingerprints. Reassign any one-time block-theme
# navigation row to baseline administrator; no warmup user/usermeta survives.
wpsr_lab_wp user delete "$WARM_USER_ID" --reassign=1 --yes >/dev/null 2>&1 || {
    echo 'E2E baseline warmup user cleanup failed.' >&2
    exit 1
}
WARM_USER_ID=0
WARM_LOG="$(wpsr_lab_compose exec -T wordpress sh -c 'test -s /tmp/wpsr-browser-outbound.log && cat /tmp/wpsr-browser-outbound.log || true')"
WPSR_E2E_WARM_LOG="$WARM_LOG" node -e '
const rows = (process.env.WPSR_E2E_WARM_LOG || "").split(/\n+/).filter(Boolean).map(line => JSON.parse(line));
for (const row of rows) {
    const url = new URL(row.target);
    const allowedPath = /^\/(?:core\/version-check\/1\.7|plugins\/update-check\/1\.1|themes\/update-check\/1\.1)\/$/.test(url.pathname);
    if (row.type !== "http" || url.hostname !== "api.wordpress.org" || !allowedPath) process.exit(1);
}
console.log(`E2E admin baseline preflight: ${rows.length} narrowly classified WordPress update intent(s), all blocked.`);
' || { echo 'Unexpected outbound intent during E2E admin baseline preflight.' >&2; exit 1; }
wpsr_lab_compose exec -T wordpress rm -f /tmp/wpsr-browser-outbound.log

# Browser/E2E runs in Apache PHP, not test runner PHP. Prove both boundaries there.
wpsr_lab_compose exec -T wordpress php -r '
require "/var/www/html/wp-load.php";
$http = wp_remote_get("https://wpsr-e2e-guard-proof.invalid/http");
$mail = wp_mail("nobody@example.test", "WPSR E2E guard proof", "blocked");
if (!is_wp_error($http) || $http->get_error_code() !== "wpsr_lab_http_blocked" || $mail !== true) { exit(1); }
' || { echo 'E2E lab outbound guard proof failed.' >&2; exit 1; }
GUARD_LINES="$(wpsr_lab_compose exec -T wordpress sh -c 'test -f /tmp/wpsr-browser-outbound.log && wc -l < /tmp/wpsr-browser-outbound.log || echo 0' | tr -d '[:space:]')"
if [ "$GUARD_LINES" != '2' ]; then
    echo "E2E guard proof expected 2 audit rows; got $GUARD_LINES." >&2
    exit 1
fi
echo 'E2E lab outbound guard proof: HTTP + mail blocked and audited.'
wpsr_lab_compose exec -T wordpress rm -f /tmp/wpsr-browser-outbound.log

SETUP_OUTPUT="$(bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/e2e/fixtures.php setup "$RUN_ID" 2>&1)"
SETUP_CODE=$?
if [ "$SETUP_CODE" -ne 0 ]; then
    printf '%s\n' "$SETUP_OUTPUT" >&2
    exit "$SETUP_CODE"
fi
CONTEXT_JSON="$(printf '%s\n' "$SETUP_OUTPUT" | sed -n 's/^WPSR_E2E_CONTEXT=//p' | tail -n 1)"
if [ -z "$CONTEXT_JSON" ]; then
    echo 'E2E fixture setup returned no context.' >&2
    exit 1
fi
printf '%s\n' "$CONTEXT_JSON" > "$CONTEXT_FILE"
chmod 600 "$CONTEXT_FILE"
FIXTURES_READY=1
echo "Ephemeral E2E admin and flow-owned fixtures ready: $RUN_ID"

WPSR_E2E_CONTEXT_FILE="$CONTEXT_FILE" WPSR_E2E_FILTER="$FILTER" node "$E2E_DIR/run-flows.js"
E2E_CODE=$?
[ "$E2E_CODE" -eq 0 ] || FAILED=1

OUTBOUND_LOG="$(wpsr_lab_compose exec -T wordpress sh -c 'test -s /tmp/wpsr-browser-outbound.log && cat /tmp/wpsr-browser-outbound.log || true')"
if [ -n "$OUTBOUND_LOG" ]; then
    echo 'Unexpected server-side outbound attempts during E2E tier:' >&2
    printf '%s\n' "$OUTBOUND_LOG" >&2
    FAILED=1
else
    echo 'Server outbound audit after E2E: HTTP 0, mail 0.'
fi

CLEANUP_OUTPUT="$(bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/e2e/fixtures.php cleanup "$RUN_ID" 2>&1)"
CLEANUP_CODE=$?
printf '%s\n' "$CLEANUP_OUTPUT"
FIXTURES_READY=0
if [ "$CLEANUP_CODE" -ne 0 ] || ! printf '%s\n' "$CLEANUP_OUTPUT" | grep -q '^WPSR_E2E_CLEANUP={"clean":true'; then
    echo 'E2E fixture cleanup/fingerprint gate failed.' >&2
    FAILED=1
fi

exit "$FAILED"
