#!/usr/bin/env bash
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FAILURES=0
LAYERS=0

run_layer() {
    local name="$1"; shift
    LAYERS=$((LAYERS + 1))
    printf '\n== security self-test: %s ==\n' "$name"
    "$@"
    local code=$?
    [ "$code" -eq 0 ] || FAILURES=$((FAILURES + 1))
}

run_layer 'registry' php "$ROOT/tests/security/registry-self-test.php"
run_layer 'source-contract fixtures' php "$ROOT/tests/bin/run-source-contract-fixtures.php"
run_layer 'Docker lab doctor mutations' php "$ROOT/tests/security/lab/self-test.php"
run_layer 'DAST policy' php "$ROOT/tests/security/dast/self-test.php"
run_layer 'public-input corpus/policy' bash "$ROOT/tests/bin/run-fuzz.sh" self-test
run_layer 'release credential policy' bash "$ROOT/tests/bin/run-release-secrets.sh" self-test
run_layer 'Semgrep rule fixtures' bash "$ROOT/tests/bin/run-semgrep.sh" fixtures

printf '\nSECURITY-SELF-TEST layers=%d failures=%d\n' "$LAYERS" "$FAILURES"
if [ "$LAYERS" -gt 0 ] && [ "$FAILURES" -eq 0 ]; then exit 0; fi
exit 1
