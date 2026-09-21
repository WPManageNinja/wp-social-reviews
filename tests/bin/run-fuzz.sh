#!/usr/bin/env bash
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
export PYTHONDONTWRITEBYTECODE=1
MODE="${1:-audit}"
case "$MODE" in
    self-test) exec python3 "$ROOT/tests/security/fuzz/self-test.py" ;;
    audit) ;;
    *) echo 'run-fuzz.sh accepts audit or self-test.' >&2; exit 2 ;;
esac

TMP="$(mktemp -d "${TMPDIR:-/tmp}/wpsr-fuzz-guard.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT
PRO_ROOT="$(cd "$ROOT/../wp-social-ninja-pro" 2>/dev/null && pwd)" || { echo 'Pro sibling missing for status/hash guard.' >&2; exit 1; }
git -C "$ROOT" status --porcelain=v1 -z --untracked-files=all > "$TMP/core-before.status"
git -C "$PRO_ROOT" status --porcelain=v1 -z --untracked-files=all > "$TMP/pro-before.status"
production_hash() {
    local repo="$1"
    git -C "$repo" ls-files -z | python3 -c 'import hashlib,os,sys
root=sys.argv[1]; h=hashlib.sha256()
for raw in sys.stdin.buffer.read().split(b"\0"):
 p=raw.decode("utf-8")
 if not p or p=="AGENTS.md" or p=="CONCEPTS.md" or p.startswith(("tests/","dev/","docs/")): continue
 data=open(os.path.join(root,p),"rb").read(); h.update(p.encode()+b"\0"+hashlib.sha256(data).digest())
print(h.hexdigest())' "$repo"
}
CORE_HASH="$(production_hash "$ROOT")"
PRO_HASH="$(production_hash "$PRO_ROOT")"
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
OUT="$ROOT/tests/.artifacts/security/fuzz/$RUN_ID"
mkdir -p "$OUT"
python3 "$ROOT/tests/security/fuzz/run.py" --artifacts "$OUT"
RUN_EXIT=$?
git -C "$ROOT" status --porcelain=v1 -z --untracked-files=all > "$TMP/core-after.status"
git -C "$PRO_ROOT" status --porcelain=v1 -z --untracked-files=all > "$TMP/pro-after.status"
GUARD_FAILURES=0
cmp -s "$TMP/core-before.status" "$TMP/core-after.status" || { echo 'FUZZ GUARD: core status drift.' >&2; GUARD_FAILURES=$((GUARD_FAILURES + 1)); }
cmp -s "$TMP/pro-before.status" "$TMP/pro-after.status" || { echo 'FUZZ GUARD: Pro status drift.' >&2; GUARD_FAILURES=$((GUARD_FAILURES + 1)); }
[ "$(production_hash "$ROOT")" = "$CORE_HASH" ] || { echo 'FUZZ GUARD: core production hash drift.' >&2; GUARD_FAILURES=$((GUARD_FAILURES + 1)); }
[ "$(production_hash "$PRO_ROOT")" = "$PRO_HASH" ] || { echo 'FUZZ GUARD: Pro production hash drift.' >&2; GUARD_FAILURES=$((GUARD_FAILURES + 1)); }
printf 'FUZZ-SAFETY-GUARD checks=4 failures=%d core-hash=%s pro-hash=%s\n' "$GUARD_FAILURES" "$CORE_HASH" "$PRO_HASH"
[ "$RUN_EXIT" -eq 0 ] && [ "$GUARD_FAILURES" -eq 0 ]
