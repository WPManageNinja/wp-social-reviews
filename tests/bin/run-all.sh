#!/usr/bin/env bash
# Fail-closed core gate plus opt-in family, add-on, compatibility, and advanced tiers.
set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SUITE=all
TEST_ENV=core
SUITE_SET=0
REMAINING=()

usage() {
    cat >&2 <<'USAGE'
Usage: bash tests/bin/run-all.sh [suite] [--env core|free|pro] [suite arguments]
Suites: all|static|php|family|canary|addon|pro|compatibility|pure|smoke|permissions|integration|js|browser|e2e|throughput|public|coverage|mutation|security|dast|fuzz|secrets|semgrep|audit
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --env)
            [ "$#" -ge 2 ] || { usage; exit 2; }
            TEST_ENV="$2"
            shift 2
            ;;
        --env=*)
            TEST_ENV="${1#--env=}"
            shift
            ;;
        --family)
            SUITE=family
            SUITE_SET=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            if [ "$SUITE_SET" -eq 0 ] && [[ "$1" != --* ]]; then
                SUITE="$1"
                SUITE_SET=1
            else
                REMAINING+=("$1")
            fi
            shift
            ;;
    esac
done
[ "$TEST_ENV" != free ] || TEST_ENV=core
case "$TEST_ENV" in
    core|pro) ;;
    *) printf 'Unknown environment: %s (use core|free|pro)\n' "$TEST_ENV" >&2; exit 2 ;;
esac
set -- "${REMAINING[@]}"

if [ "$SUITE" = family ]; then
    [ "$#" -eq 0 ] || { printf 'Family sweep accepts no child-suite arguments.\n' >&2; exit 2; }
    exec php "$PLUGIN_DIR/tests/family/sweep.php"
fi
if [ "$SUITE" = pro ]; then
    TEST_ENV=pro
    SUITE=addon
fi
export WPSR_TEST_ENV="$TEST_ENV"

FAILED=0
RESULTS=''

record() {
    local wpsr_label="$1" wpsr_status="$2"
    if [ "$wpsr_status" -eq 0 ]; then
        printf 'PASS  %s\n' "$wpsr_label"
        RESULTS="${RESULTS}PASS  ${wpsr_label}\n"
    else
        printf 'FAIL  %s\n' "$wpsr_label" >&2
        RESULTS="${RESULTS}FAIL  ${wpsr_label}\n"
        FAILED=1
    fi
}

run_gate() {
    local wpsr_label="$1"
    shift
    printf '\n== %s ==\n' "$wpsr_label"
    "$@"
    record "$wpsr_label" "$?"
}

run_host_static() {
    run_gate 'canonical host static gates' php "$PLUGIN_DIR/tests/bin/run-host-static.php"
}

run_family_metadata() {
    run_gate 'family census' env WPSR_FAMILY_SWEEP_CHILD=1 php "$PLUGIN_DIR/tests/family/census.php"
    run_gate 'family census proof' php "$PLUGIN_DIR/tests/family/test-proof-of-catch.php"
    run_gate 'add-on test-tree integrity' env WPSR_FAMILY_SWEEP_CHILD=1 php "$PLUGIN_DIR/tests/family/test-trees.php"
    run_gate 'family sweep plan' php "$PLUGIN_DIR/tests/family/sweep.php" --lint
    run_gate 'family sweep proof' php "$PLUGIN_DIR/tests/family/test-sweep-proof.php"
}

run_static() {
    run_host_static
    run_family_metadata
}

run_core_php() {
    local wpsr_env_args=()
    if [ "$TEST_ENV" = pro ]; then
        wpsr_env_args=(--env pro)
    fi
    run_gate 'REST smoke' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" "${wpsr_env_args[@]}" tests/bin/run-smoke.php
    run_gate 'permission smoke' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" "${wpsr_env_args[@]}" tests/bin/run-permissions.php
    run_gate 'pure functions' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" "${wpsr_env_args[@]}" tests/bin/run-pure.php
    run_gate 'integration' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" "${wpsr_env_args[@]}" tests/bin/run-integration.php
    run_gate 'public handlers' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" "${wpsr_env_args[@]}" tests/bin/run-public.php
}

run_canary() {
    run_gate "family environment canary [$TEST_ENV]" bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" --env "$TEST_ENV" tests/bin/run-family-canary.php
}

run_addon() {
    [ "$TEST_ENV" = pro ] || { printf 'Add-on suite requires --env pro or suite pro.\n' >&2; exit 2; }
    run_gate 'registered Pro add-on tests' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" --env pro tests/bin/run-addon.php plugin=pro "$@"
}

run_family_host() {
    [ "$TEST_ENV" = core ] || { printf 'Family host suite requires core environment.\n' >&2; exit 2; }
    run_host_static
    run_canary
    run_gate 'family host integration' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/bin/run-integration.php
    run_gate 'family host JavaScript' bash "$PLUGIN_DIR/tests/bin/run-js.sh"
    run_gate 'family host permissions' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/bin/run-permissions.php
    run_gate 'family host public handlers' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/bin/run-public.php
    run_gate 'family host pure' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/bin/run-pure.php
    run_gate 'family host smoke' bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" tests/bin/run-smoke.php
}

case "$SUITE" in
    all)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_static
        run_core_php
        if [ "$TEST_ENV" = pro ]; then
            run_canary
            run_addon
        fi
        ;;
    static)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_static
        ;;
    php)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_static
        run_core_php
        if [ "$TEST_ENV" = pro ]; then
            run_canary
            run_addon
        fi
        ;;
    canary)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_canary
        ;;
    addon)
        run_addon "$@"
        ;;
    family-host)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_family_host
        ;;
    compatibility)
        [ "$#" -le 1 ] || { usage; exit 2; }
        run_gate 'family compatibility matrix' bash "$PLUGIN_DIR/tests/bin/run-compatibility-matrix.sh" "${1:-all}"
        ;;
    pure|smoke|permissions|integration)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_gate "$SUITE" bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" --env "$TEST_ENV" "tests/bin/run-$SUITE.php"
        ;;
    js|browser|e2e|coverage)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_gate "$SUITE" bash "$PLUGIN_DIR/tests/bin/run-$SUITE.sh"
        ;;
    throughput|public)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_gate "$SUITE" bash "$PLUGIN_DIR/tests/lab/scripts/php-tests.sh" --env "$TEST_ENV" "tests/bin/run-$SUITE.php"
        ;;
    mutation)
        run_gate 'mutation audit' php "$PLUGIN_DIR/tests/bin/run-mutation.php" "$@"
        ;;
    security)
        run_gate 'security registry' "$PLUGIN_DIR/tests/bin/security" "$@"
        ;;
    dast)
        run_gate 'DAST' "$PLUGIN_DIR/tests/bin/security" dast "${1:-all}"
        ;;
    fuzz)
        run_gate 'public-input robustness' "$PLUGIN_DIR/tests/bin/security" fuzz "${1:-audit}"
        ;;
    secrets)
        run_gate 'release secrets' "$PLUGIN_DIR/tests/bin/security" secrets "${1:-audit}"
        ;;
    semgrep)
        run_gate 'Semgrep' "$PLUGIN_DIR/tests/bin/security" semgrep "${1:-all}"
        ;;
    audit)
        [ "$#" -eq 0 ] || { usage; exit 2; }
        run_gate 'security audit' "$PLUGIN_DIR/tests/bin/security" audit
        ;;
    *)
        printf 'Unknown suite: %s\n' "$SUITE" >&2
        usage
        exit 2
        ;;
esac

printf '\n========================================================================\n'
printf '%b' "$RESULTS"
if [ "$FAILED" -ne 0 ]; then
    printf 'TEST GATE: FAIL\n' >&2
    exit 1
fi
printf 'TEST GATE: PASS\n'
