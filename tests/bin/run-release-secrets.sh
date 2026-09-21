#!/usr/bin/env bash
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:-audit}"
case "$MODE" in
    self-test) exec python3 "$ROOT/tests/security/secrets/self-test.py" ;;
    audit) ;;
    *) echo 'run-release-secrets.sh accepts audit or self-test.' >&2; exit 2 ;;
esac
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
OUT="$ROOT/tests/.artifacts/security/secrets/$RUN_ID"
ZIP="$OUT/wp-social-reviews-release.zip"
mkdir -p "$OUT"
python3 "$ROOT/tests/security/secrets/package-release.py" \
    --root "$ROOT" --manifest "$ROOT/tests/security/secrets/release-manifest.json" --output "$ZIP" | tee "$OUT/package.txt"
PACKAGE_EXIT=${PIPESTATUS[0]}
python3 "$ROOT/tests/security/secrets/scan-release.py" \
    --zip "$ZIP" --allowlist "$ROOT/tests/security/secrets/allowlist.json" --output "$OUT/results.json" | tee "$OUT/policy.txt"
SCAN_EXIT=${PIPESTATUS[0]}
read -r FILES TEXT FINDINGS ALLOWED BLOCKED < <(python3 - "$OUT/results.json" <<'PY'
import json,sys
v=json.load(open(sys.argv[1])); print(v['files_scanned'],v['text_files_scanned'],len(v['findings']),len(v['allowlisted']),len(v['blocked']))
PY
)
ZIP_SHA="$(shasum -a 256 "$ZIP" | awk '{print $1}')"
MANIFEST_SHA="$(shasum -a 256 "$ROOT/tests/security/secrets/release-manifest.json" | awk '{print $1}')"
ALLOW_SHA="$(shasum -a 256 "$ROOT/tests/security/secrets/allowlist.json" | awk '{print $1}')"
printf '{"schema":1,"scanner":"wpsr-release-secrets/1.0.0","zip":"%s","zip_sha256":"%s","manifest_sha256":"%s","allowlist_sha256":"%s","files":%d,"text_files":%d,"findings":%d,"allowlisted":%d,"blocked":%d,"package_exit":%d,"scan_exit":%d}\n' \
    "$(basename "$ZIP")" "$ZIP_SHA" "$MANIFEST_SHA" "$ALLOW_SHA" "$FILES" "$TEXT" "$FINDINGS" "$ALLOWED" "$BLOCKED" "$PACKAGE_EXIT" "$SCAN_EXIT" > "$OUT/metadata.json"
ARTIFACTS="$(find "$OUT" -type f -size +0c | wc -l | tr -d '[:space:]')"
FAILURES=0
[ "$PACKAGE_EXIT" -eq 0 ] || FAILURES=$((FAILURES + 1))
[ "$SCAN_EXIT" -eq 0 ] || FAILURES=$((FAILURES + 1))
[ "$FILES" -gt 0 ] && [ "$TEXT" -gt 0 ] || FAILURES=$((FAILURES + 1))
[ "$BLOCKED" -eq 0 ] || FAILURES=$((FAILURES + 1))
[ "$ARTIFACTS" -ge 5 ] || FAILURES=$((FAILURES + 1))
printf 'RELEASE-SECRETS-AUDIT archives=1 files=%d text=%d findings=%d allowlisted=%d blocked=%d artifacts=%d failures=%d output=%s\n' \
    "$FILES" "$TEXT" "$FINDINGS" "$ALLOWED" "$BLOCKED" "$ARTIFACTS" "$FAILURES" "$OUT"
[ "$FAILURES" -eq 0 ]
