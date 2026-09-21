#!/usr/bin/env bash
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
IMAGE="semgrep/semgrep:1.90.0"
PACK="${1:-all}"
case "$PACK" in
    fixtures) exec bash "$ROOT/tests/security/semgrep/test-fixtures.sh" ;;
    security|quality) ;;
    all)
        bash "$0" security; SECURITY_EXIT=$?
        bash "$0" quality; QUALITY_EXIT=$?
        [ "$SECURITY_EXIT" -eq 0 ] && [ "$QUALITY_EXIT" -eq 0 ]; exit $?
        ;;
    *) echo 'run-semgrep.sh accepts fixtures, security, quality, or all.' >&2; exit 2 ;;
esac

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
OUT="$ROOT/tests/.artifacts/security/semgrep/$PACK/$RUN_ID"
mkdir -p "$OUT"
CONFIG="$ROOT/tests/security/semgrep/packs/$PACK.yml"
POLICY="$ROOT/tests/security/semgrep/$PACK-policy.json"

docker run --rm --network none -e SEMGREP_ENABLE_VERSION_CHECK=0 -e SEMGREP_SEND_METRICS=off \
    -v "$ROOT:/src:ro" -v "$OUT:/out" \
    "$IMAGE" semgrep --disable-version-check --metrics=off --config "/src/tests/security/semgrep/packs/$PACK.yml" \
    --json --output /out/results.json /src/app /src/boot /src/resources/admin /src/resources/public
SCAN_EXIT=$?

read -r RULES SCANNED PARKED < <(python3 - "$CONFIG" "$OUT/results.json" "$POLICY" <<'PY'
import json,re,sys
rules=len(re.findall(r'^\s*- id: wpsr\.',open(sys.argv[1]).read(),re.M))
data=json.load(open(sys.argv[2]))
policy=json.load(open(sys.argv[3]))
parked=len(policy.get('known_findings',[]))+len(policy.get('benign_allowlist',[]))
print(rules,len(data.get('paths',{}).get('scanned',[])),parked)
PY
)
python3 "$ROOT/tests/security/semgrep/verify.py" source \
    --results "$OUT/results.json" --policy "$POLICY" --rules "$RULES" --scanned "$SCANNED" | tee "$OUT/policy.txt"
POLICY_EXIT=${PIPESTATUS[0]}
VERSION="$(docker run --rm --network none -e SEMGREP_ENABLE_VERSION_CHECK=0 -e SEMGREP_SEND_METRICS=off "$IMAGE" semgrep --version 2>/dev/null | tail -1)"
DIGEST="$(docker image inspect "$IMAGE" --format '{{index .RepoDigests 0}}' 2>/dev/null || true)"
printf '%s\n' "$VERSION" > "$OUT/version.txt"
printf '%s\n' "$DIGEST" > "$OUT/image-digest.txt"
CONFIG_SHA="$(shasum -a 256 "$CONFIG" | awk '{print $1}')"
POLICY_SHA="$(shasum -a 256 "$POLICY" | awk '{print $1}')"
printf '{"schema":1,"pack":"%s","image":"%s","version":"%s","rules":%d,"scanned":%d,"scan_exit":%d,"policy_exit":%d,"config_sha256":"%s","policy_sha256":"%s"}\n' \
    "$PACK" "$IMAGE" "$VERSION" "$RULES" "$SCANNED" "$SCAN_EXIT" "$POLICY_EXIT" "$CONFIG_SHA" "$POLICY_SHA" > "$OUT/metadata.json"
ARTIFACTS="$(find "$OUT" -type f -size +0c | wc -l | tr -d '[:space:]')"
FINDINGS="$(python3 -c 'import json,sys; print(len(json.load(open(sys.argv[1])).get("results",[])))' "$OUT/results.json")"
printf 'SEMGREP-%s rules=%d scanned=%d findings=%d artifacts=%d scanner-exit=%d policy-exit=%d failures=%d output=%s\n' \
    "$(printf '%s' "$PACK" | tr '[:lower:]' '[:upper:]')" "$RULES" "$SCANNED" "$FINDINGS" "$ARTIFACTS" "$SCAN_EXIT" "$POLICY_EXIT" \
    "$([ "$SCAN_EXIT" -eq 0 ] && [ "$POLICY_EXIT" -eq 0 ] && [ "$RULES" -gt 0 ] && [ "$SCANNED" -gt 0 ] && { [ "$FINDINGS" -gt 0 ] || [ "$PARKED" -eq 0 ]; } && echo 0 || echo 1)" "$OUT"
# A pack with nothing parked is allowed to find nothing; rules/scanned still prove it ran.
if [ "$SCAN_EXIT" -eq 0 ] && [ "$POLICY_EXIT" -eq 0 ] && [ "$RULES" -gt 0 ] && [ "$SCANNED" -gt 0 ] && { [ "$FINDINGS" -gt 0 ] || [ "$PARKED" -eq 0 ]; } && [ "$ARTIFACTS" -ge 5 ]; then exit 0; fi
exit 1
