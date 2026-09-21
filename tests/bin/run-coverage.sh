#!/usr/bin/env bash
# Phase 13 opt-in merged PCOV coverage across all PHP runtime tiers.
set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ARTIFACT_HOST="$PLUGIN_DIR/tests/.artifacts/coverage"
ARTIFACT_CONTAINER="/workspace/wp-social-ninja/tests/.artifacts/coverage"
RAW_HOST="$ARTIFACT_HOST/raw"
RAW_CONTAINER="$ARTIFACT_CONTAINER/raw"
REPORT_CONTAINER="$ARTIFACT_CONTAINER/report"
SOURCE_CONTAINER="/var/www/html/wp-content/plugins/wp-social-ninja"
FAILED=0
TIER_RESULTS=()
STARTED=$SECONDS

# shellcheck source=../lab/scripts/lib.sh
source "$PLUGIN_DIR/tests/lab/scripts/lib.sh"
set +e

record_tier() {
    local wpsr_tier="$1" wpsr_code="$2"
    if [ "$wpsr_code" -eq 0 ]; then
        TIER_RESULTS+=("PASS  $wpsr_tier")
    else
        TIER_RESULTS+=("FAIL  $wpsr_tier (exit $wpsr_code)")
        FAILED=1
    fi
}

summary_has_execution() {
    local wpsr_label="$1" wpsr_summary="$2" wpsr_passed wpsr_total
    wpsr_passed="$(printf '%s\n' "$wpsr_summary" | sed -nE "s/^${wpsr_label}: ([0-9]+)\/([0-9]+) passed.*/\\1/p")"
    wpsr_total="$(printf '%s\n' "$wpsr_summary" | sed -nE "s/^${wpsr_label}: ([0-9]+)\/([0-9]+) passed.*/\\2/p")"
    [ -n "$wpsr_passed" ] && [ -n "$wpsr_total" ] \
        && [ "$wpsr_passed" -gt 0 ] && [ "$wpsr_total" -gt 0 ]
}

run_tier() {
    local wpsr_tier="$1" wpsr_label="$2" wpsr_runner="$3"
    local wpsr_capture="$RAW_CONTAINER/$wpsr_tier.pcov"
    local wpsr_capture_host="$RAW_HOST/$wpsr_tier.pcov"
    local wpsr_log="$ARTIFACT_HOST/$wpsr_tier.log"
    local wpsr_code wpsr_summary

    wpsr_lab_compose run --rm --no-deps \
        -e "WPSR_PCOV_OUTPUT=$wpsr_capture" \
        -e "WPSR_PCOV_SUITE=$wpsr_tier" \
        php-tests \
        php \
            -d pcov.enabled=1 \
            -d "pcov.directory=$SOURCE_CONTAINER" \
            -d auto_prepend_file=/workspace/wp-social-ninja/tests/bin/coverage-bootstrap.php \
            /usr/local/bin/wp --path=/var/www/html eval-file "$SOURCE_CONTAINER/$wpsr_runner" \
            >"$wpsr_log" 2>&1
    wpsr_code=$?

    wpsr_summary="$(grep -E "^${wpsr_label}:" "$wpsr_log" | tail -n 1)"
    if [ "$wpsr_code" -eq 0 ] && ! summary_has_execution "$wpsr_label" "$wpsr_summary"; then
        echo "Coverage tier executed zero tests or emitted invalid summary: $wpsr_tier" >&2
        wpsr_code=2
    fi
    if [ "$wpsr_code" -eq 0 ] && [ ! -s "$wpsr_capture_host" ]; then
        echo "Coverage tier produced zero captures: $wpsr_tier" >&2
        wpsr_code=2
    fi

    if [ "$wpsr_code" -ne 0 ]; then
        cat "$wpsr_log"
    else
        printf 'captured %-12s %s\n' "$wpsr_tier" "$wpsr_summary"
    fi
    record_tier "$wpsr_tier" "$wpsr_code"
}

printf 'S13 — merged PHP line coverage (PCOV; Docker; opt-in)\n'
printf '%s\n' '------------------------------------------------------------------------'

unset WPSR_PCOV_OUTPUT WPSR_PCOV_SUITE
bash "$PLUGIN_DIR/tests/lab/scripts/setup.sh" || exit $?

rm -rf "$ARTIFACT_HOST"
mkdir -p "$RAW_HOST" "$ARTIFACT_HOST/self-test" "$ARTIFACT_HOST/report"
chmod -R 0777 "$ARTIFACT_HOST"

wpsr_module_probe="$(wpsr_lab_compose run --rm --no-deps php-tests php -r '
printf("pcov=%s enabled=%s wp=%s coverage=%s\n", extension_loaded("pcov") ? phpversion("pcov") : "missing", ini_get("pcov.enabled"), is_file("/usr/local/bin/wp") ? "yes" : "no", is_file("/opt/wpsr-coverage/vendor/autoload.php") ? "yes" : "no");
')"
echo "$wpsr_module_probe"
if ! grep -Eq '^pcov=[^ ]+ enabled=0 wp=yes coverage=yes$' <<<"$wpsr_module_probe"; then
    echo 'Coverage image preflight failed or PCOV contaminates default PHP behavior.' >&2
    exit 2
fi

# Tooling proof 1: zero captures must fail closed.
wpsr_zero_log="$ARTIFACT_HOST/self-test/zero-capture.log"
wpsr_lab_compose run --rm --no-deps php-tests php \
    /workspace/wp-social-ninja/tests/bin/run-coverage.php --self-test \
    --source=/workspace/wp-social-ninja/tests/coverage --roots=fixtures \
    >"$wpsr_zero_log" 2>&1
wpsr_zero_code=$?
if [ "$wpsr_zero_code" -eq 2 ] && grep -q 'ZERO COVERAGE CAPTURES' "$wpsr_zero_log"; then
    echo 'COVERAGE-ZERO-CAPTURE-SELF-TEST: expected exit 2; false green rejected'
    record_tier 'coverage-zero-capture-self-test' 0
else
    cat "$wpsr_zero_log"
    record_tier 'coverage-zero-capture-self-test' 1
fi

# Tooling proof 2: layer summary with zero executed tests must fail closed.
if summary_has_execution 'SELFTEST' 'SELFTEST: 0/0 passed, 0 failed, 0 skipped'; then
    echo 'Coverage zero-layer self-test unexpectedly accepted 0/0.' >&2
    record_tier 'coverage-zero-layer-self-test' 1
else
    echo 'COVERAGE-ZERO-LAYER-SELF-TEST: 0/0 summary rejected'
    record_tier 'coverage-zero-layer-self-test' 0
fi

# Tooling proof 3: executed fixture covered; untouched source visible at 0%.
wpsr_self_raw="$ARTIFACT_CONTAINER/self-test/self-test.pcov"
wpsr_lab_compose run --rm --no-deps \
    -e "WPSR_PCOV_OUTPUT=$wpsr_self_raw" \
    -e WPSR_PCOV_SUITE=self-test \
    php-tests \
    php \
        -d pcov.enabled=1 \
        -d pcov.directory=/workspace/wp-social-ninja/tests/coverage/fixtures \
        -d auto_prepend_file=/workspace/wp-social-ninja/tests/bin/coverage-bootstrap.php \
        /workspace/wp-social-ninja/tests/coverage/self-test-run.php
wpsr_self_run_code=$?
if [ "$wpsr_self_run_code" -eq 0 ]; then
    wpsr_lab_compose run --rm --no-deps php-tests php \
        /workspace/wp-social-ninja/tests/bin/run-coverage.php \
        --self-test \
        --source=/workspace/wp-social-ninja/tests/coverage \
        --roots=fixtures \
        --expect=self-test \
        "$wpsr_self_raw"
    wpsr_self_report_code=$?
else
    wpsr_self_report_code=$wpsr_self_run_code
fi
record_tier 'coverage-uncovered-file-self-test' "$wpsr_self_report_code"

run_tier pure PURE tests/bin/run-pure.php
run_tier smoke SMOKE tests/bin/run-smoke.php
run_tier permissions PERMISSIONS tests/bin/run-permissions.php
run_tier integration INTEGRATION tests/bin/run-integration.php

printf '\nCoverage tier results:\n'
for wpsr_result in "${TIER_RESULTS[@]}"; do
    printf '  %s\n' "$wpsr_result"
done

if [ "$FAILED" -ne 0 ]; then
    echo 'COVERAGE-GATE: FAIL — tier/self-test semantics failed; merged report withheld.' >&2
    exit 1
fi

wpsr_captures=()
for wpsr_tier in pure smoke permissions integration; do
    wpsr_captures+=("$RAW_CONTAINER/$wpsr_tier.pcov")
done

wpsr_lab_compose run --rm --no-deps php-tests php \
    /workspace/wp-social-ninja/tests/bin/run-coverage.php \
    --artifact="$REPORT_CONTAINER" \
    --expect=pure,smoke,permissions,integration \
    "${wpsr_captures[@]}"
wpsr_report_code=$?

if [ "$wpsr_report_code" -ne 0 ]; then
    echo 'COVERAGE-GATE: FAIL — merged report invalid or empty.' >&2
    exit "$wpsr_report_code"
fi

if [ ! -s "$ARTIFACT_HOST/report/summary.json" ] \
    || [ ! -s "$ARTIFACT_HOST/report/clover.xml" ] \
    || [ ! -s "$ARTIFACT_HOST/report/coverage.txt" ] \
    || [ ! -s "$ARTIFACT_HOST/report/html/index.html" ]; then
    echo 'COVERAGE-GATE: FAIL — required merged report artifacts missing.' >&2
    exit 2
fi

printf 'coverage runtime: %ss\n' "$((SECONDS - STARTED))"
echo 'COVERAGE-GATE: PASS — pure + smoke + permissions + integration merged; uncovered files visible; nonzero execution/data verified.'
