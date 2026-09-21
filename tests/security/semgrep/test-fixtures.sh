#!/usr/bin/env bash
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
IMAGE="semgrep/semgrep:1.90.0"
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
OUT="$ROOT/tests/.artifacts/security/semgrep/fixtures/$RUN_ID"
WORK="$OUT/fixture-work"
mkdir -p "$OUT"
cp -R "$ROOT/tests/security/semgrep/fixtures" "$WORK"

run_scan() {
    docker run --rm --network none -e SEMGREP_ENABLE_VERSION_CHECK=0 -e SEMGREP_SEND_METRICS=off \
        -v "$ROOT:/src:ro" -v "$OUT:/out" \
        "$IMAGE" semgrep --disable-version-check --no-git-ignore --metrics=off \
        --config /src/tests/security/semgrep/packs/security.yml \
        --config /src/tests/security/semgrep/packs/quality.yml \
        --json --output /out/results.json /out/fixture-work
}

run_scan
SCAN_EXIT=$?
python3 "$ROOT/tests/security/semgrep/verify.py" fixtures \
    --results "$OUT/results.json" --fixtures "$WORK" \
    --config "$ROOT/tests/security/semgrep/packs/security.yml" \
    --config "$ROOT/tests/security/semgrep/packs/quality.yml"
VERIFY_EXIT=$?

# Proof-of-catch: remove one unsafe finding from derived result. Verifier must turn red.
python3 - "$OUT/results.json" "$OUT/mutated-results.json" "$OUT/removed-rule.txt" "$OUT/expected-mutant-reason.txt" <<'PY'
import json,sys
value=json.load(open(sys.argv[1]))
if not value.get('results'):
    raise SystemExit('fixture result has zero findings before mutation')
prefix='tests.security.semgrep.packs.'
def rid(value): return value[len(prefix):] if value.startswith(prefix) else value
removed=rid(value['results'].pop(0)['check_id'])
remaining=sum(rid(row['check_id']) == removed for row in value['results'])
json.dump(value,open(sys.argv[2],'w'))
open(sys.argv[3],'w').write(removed+'\n')
open(sys.argv[4],'w').write(f'{removed}: expected {remaining + 1} finding(s), got {remaining}\n')
PY
python3 "$ROOT/tests/security/semgrep/verify.py" fixtures \
    --results "$OUT/mutated-results.json" --fixtures "$WORK" \
    --config "$ROOT/tests/security/semgrep/packs/security.yml" \
    --config "$ROOT/tests/security/semgrep/packs/quality.yml" >"$OUT/mutant-policy.txt" 2>&1
MUTANT_EXIT=$?
REMOVED_RULE="$(cat "$OUT/removed-rule.txt")"
EXPECTED_MUTANT_REASON="$(cat "$OUT/expected-mutant-reason.txt")"
MUTANT_REASON=1
grep -Fq "$EXPECTED_MUTANT_REASON" "$OUT/mutant-policy.txt" && MUTANT_REASON=0

VERSION="$(docker run --rm --network none -e SEMGREP_ENABLE_VERSION_CHECK=0 -e SEMGREP_SEND_METRICS=off "$IMAGE" semgrep --version 2>/dev/null | tail -1)"
DIGEST="$(docker image inspect "$IMAGE" --format '{{index .RepoDigests 0}}' 2>/dev/null || true)"
printf '%s\n' "$VERSION" > "$OUT/version.txt"
printf '%s\n' "$DIGEST" > "$OUT/image-digest.txt"
printf '{"schema":1,"image":"%s","version":"%s","scan_exit":%d,"verify_exit":%d,"mutant_exit":%d}\n' \
    "$IMAGE" "$VERSION" "$SCAN_EXIT" "$VERIFY_EXIT" "$MUTANT_EXIT" > "$OUT/metadata.json"
rm -rf "$WORK"
ARTIFACTS="$(find "$OUT" -type f -size +0c | wc -l | tr -d '[:space:]')"

if [ "$SCAN_EXIT" -eq 0 ] && [ "$VERIFY_EXIT" -eq 0 ] && [ "$MUTANT_EXIT" -eq 1 ] && [ "$MUTANT_REASON" -eq 0 ] && [ -s "$OUT/results.json" ]; then
    printf 'SEMGREP-FIXTURE-SELF-TEST proofs=3 failures=0 artifacts=%d output=%s\n' "$ARTIFACTS" "$OUT"
    exit 0
fi
printf 'SEMGREP-FIXTURE-SELF-TEST proofs=3 failures=1 artifacts=%d output=%s\n' "$ARTIFACTS" "$OUT" >&2
exit 1
