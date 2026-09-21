#!/bin/sh
set -eu

TARGET="${WPSR_DAST_TARGET:-}"
RUN_ID="${WPSR_DAST_RUN_ID:-}"
[ "$TARGET" = 'http://127.0.0.1:80' ] || { echo "DAST TARGET REFUSED: $TARGET" >&2; exit 64; }
echo "DAST TARGET ACCEPTED: $TARGET"
printf '%s' "$RUN_ID" | grep -Eq '^[A-Za-z0-9._-]+$' || { echo 'Invalid or missing WPSR_DAST_RUN_ID.' >&2; exit 64; }

OUT="/zap/wrk/nuclei/$RUN_ID"
mkdir -p "$OUT" /tmp/nuclei-home/.config/nuclei
printf '%s\n' 'tags: []' 'files: []' > /tmp/nuclei-home/.config/nuclei/.nuclei-ignore
export HOME=/tmp/nuclei-home

# Local committed HTTP templates only. Disable updates, stdin targets, OAST,
# code/headless/file protocols, fuzz/DAST templates, and cross-host redirects.
exec nuclei \
    -u "$TARGET" \
    -t /scanner/nuclei-templates \
    -pt http \
    -severity info,low,medium,high,critical \
    -exclude-tags dos,fuzz,intrusive,headless,code,oast \
    -disable-update-check \
    -no-interactsh \
    -no-stdin \
    -disable-redirects \
    -rate-limit 10 \
    -concurrency 2 \
    -bulk-size 1 \
    -timeout 10 \
    -retries 0 \
    -jsonl-export "$OUT/findings.jsonl" \
    -stats -stats-json -stats-interval 1 \
    -no-color
