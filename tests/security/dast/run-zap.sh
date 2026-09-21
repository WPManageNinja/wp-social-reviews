#!/bin/sh
set -eu

TARGET="${WPSR_DAST_TARGET:-}"
RUN_ID="${WPSR_DAST_RUN_ID:-}"
[ "$TARGET" = 'http://127.0.0.1:80' ] || { echo "DAST TARGET REFUSED: $TARGET" >&2; exit 64; }
echo "DAST TARGET ACCEPTED: $TARGET"
printf '%s' "$RUN_ID" | grep -Eq '^[A-Za-z0-9._-]+$' || { echo 'Invalid or missing WPSR_DAST_RUN_ID.' >&2; exit 64; }

OUT="/zap/wrk/zap/$RUN_ID"
mkdir -p "$OUT"
cd /zap/wrk

# Passive baseline only. No Ajax spider, active scan, marketplace update, or external context.
exec zap-baseline.py \
    -t "$TARGET" \
    -m 1 \
    -T 5 \
    -I \
    -c /scanner/zap-rules.conf \
    -J "zap/$RUN_ID/report.json" \
    -w "zap/$RUN_ID/report.md" \
    -r "zap/$RUN_ID/report.html" \
    -x "zap/$RUN_ID/report.xml" \
    -z '-config addon.autoupdate=false -config connection.timeoutInSecs=10'
