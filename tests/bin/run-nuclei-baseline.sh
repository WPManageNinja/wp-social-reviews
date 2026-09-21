#!/usr/bin/env bash
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=../lab/scripts/lib.sh
source "$ROOT/tests/lab/scripts/lib.sh"
set +e

TARGET='http://127.0.0.1:80'
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
OUT="$ROOT/tests/.artifacts/security/nuclei/$RUN_ID"
STARTED="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
mkdir -p "$OUT"
chmod 0777 "$ROOT/tests/.artifacts/security" "$ROOT/tests/.artifacts/security/nuclei" "$OUT" 2>/dev/null || true

php "$ROOT/tests/security/lab/doctor.php" --config-only || exit 1
wpsr_lab_compose up --detach --wait db wordpress >/dev/null || exit 1
php "$ROOT/tests/security/lab/doctor.php" || exit 1

IMAGE="projectdiscovery/nuclei:v3.11.0"
VERSION="$(docker run --rm "$IMAGE" -version 2>&1 | sed -n 's/.*Nuclei Engine Version: //p' | tail -n 1)"
DIGEST="$(docker image inspect "$IMAGE" --format '{{index .RepoDigests 0}}' 2>/dev/null || true)"
printf '%s\n' "$VERSION" > "$OUT/version.txt"

WPSR_DAST_RUN_ID="$RUN_ID" wpsr_lab_compose --profile security run --rm --no-deps \
    -e WPSR_DAST_RUN_ID="$RUN_ID" -e WPSR_DAST_TARGET="$TARGET" nuclei 2>&1 | tee "$OUT/scanner.log"
SCAN_EXIT=${PIPESTATUS[0]}

POLICY_EXIT=1
if [ -s "$OUT/findings.jsonl" ]; then
    php "$ROOT/tests/security/dast/policy.php" nuclei "$OUT/findings.jsonl" "$OUT/policy.json" | tee "$OUT/policy.log"
    POLICY_EXIT=${PIPESTATUS[0]}
else
    printf 'DAST-POLICY FAIL nuclei findings artifact missing or empty\n' | tee "$OUT/policy.log" >&2
fi
php "$ROOT/tests/security/dast/write-metadata.php" nuclei "$VERSION" "$IMAGE" "$DIGEST" "$TARGET" "$STARTED" "$OUT/metadata.json"

ARTIFACTS="$(find "$OUT" -type f -size +0c | wc -l | tr -d '[:space:]')"
SCANNED="$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo (int)($j["scanned"]??0);' "$OUT/policy.json" 2>/dev/null || echo 0)"
FINDINGS="$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo (int)($j["findings"]??0);' "$OUT/policy.json" 2>/dev/null || echo 0)"
FAILURES=0
[ "$SCAN_EXIT" -eq 0 ] || FAILURES=$((FAILURES + 1))
[ "$POLICY_EXIT" -eq 0 ] || FAILURES=$((FAILURES + 1))
[ "$SCANNED" -gt 0 ] || FAILURES=$((FAILURES + 1))
[ "$FINDINGS" -gt 0 ] || FAILURES=$((FAILURES + 1))
[ "$ARTIFACTS" -ge 5 ] || FAILURES=$((FAILURES + 1))
printf 'NUCLEI-BASELINE scanned=%d findings=%d artifacts=%d scanner-exit=%d policy-exit=%d failures=%d runtime-artifacts=%s\n' \
    "$SCANNED" "$FINDINGS" "$ARTIFACTS" "$SCAN_EXIT" "$POLICY_EXIT" "$FAILURES" "$OUT"
if [ "$FAILURES" -eq 0 ]; then exit 0; fi
exit 1
