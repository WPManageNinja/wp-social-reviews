#!/usr/bin/env bash
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
OUT="$ROOT/tests/.artifacts/security/audit/$RUN_ID"
mkdir -p "$OUT"
START="$(date +%s)"
FAILURES=0
LAYERS=0

run_layer() {
    local name="$1"; shift
    LAYERS=$((LAYERS + 1))
    printf '\n== security audit: %s ==\n' "$name"
    "$@" 2>&1 | tee "$OUT/${name}.log"
    local code=${PIPESTATUS[0]}
    [ "$code" -eq 0 ] || FAILURES=$((FAILURES + 1))
    [ -s "$OUT/${name}.log" ] || FAILURES=$((FAILURES + 1))
}

run_layer doctor php "$ROOT/tests/security/lab/doctor.php"
# Fast source/inventory registry slice. Runtime behavior and 40-mutant audit stay
# available through selector CLI but do not delay DAST feedback in this verb.
run_layer registry-fast php "$ROOT/tests/security/run.php" \
    --category=inventory-drift,table-prefix-safety,source-contract-proof
run_layer secrets bash "$ROOT/tests/bin/run-release-secrets.sh" audit
run_layer semgrep bash "$ROOT/tests/bin/run-semgrep.sh" all
run_layer fuzz bash "$ROOT/tests/bin/run-fuzz.sh" audit
run_layer zap bash "$ROOT/tests/bin/run-zap-baseline.sh"
run_layer nuclei bash "$ROOT/tests/bin/run-nuclei-baseline.sh"

RUNTIME=$(($(date +%s) - START))
ARTIFACTS="$(find "$OUT" -type f -size +0c | wc -l | tr -d '[:space:]')"
printf '{"schema":1,"layers":%d,"failures":%d,"runtime_seconds":%d,"artifacts":%d}\n' \
    "$LAYERS" "$FAILURES" "$RUNTIME" "$ARTIFACTS" > "$OUT/summary.json"
printf '\nSECURITY-AUDIT layers=%d artifacts=%d failures=%d runtime=%ds output=%s\n' \
    "$LAYERS" "$ARTIFACTS" "$FAILURES" "$RUNTIME" "$OUT"
if [ "$LAYERS" -eq 7 ] && [ "$ARTIFACTS" -ge 7 ] && [ "$FAILURES" -eq 0 ]; then exit 0; fi
exit 1
