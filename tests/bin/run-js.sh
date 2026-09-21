#!/usr/bin/env bash
set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
JS_DIR="$PLUGIN_DIR/tests/js"
FILTER=""

if [ "$#" -gt 1 ]; then
    echo 'JS runner accepts at most one exact filter=NAME argument.' >&2
    exit 2
fi
if [ "$#" -eq 1 ]; then
    case "$1" in
        filter=*) FILTER="${1#filter=}" ;;
        *) echo "Unknown JS argument: $1 (use filter=request-layer|admin-utils|state-errors)" >&2; exit 2 ;;
    esac
    if [ -z "$FILTER" ]; then
        echo 'JS filter cannot be empty.' >&2
        exit 2
    fi
fi

node "$JS_DIR/inventory.mjs" || exit 1

mapfile -t TEST_FILES < <(find "$JS_DIR/specs" -maxdepth 1 -type f -name '*.test.js' | sort)
if [ -n "$FILTER" ]; then
    WPSR_SELECTED="$JS_DIR/specs/$FILTER.test.js"
    if [ ! -f "$WPSR_SELECTED" ]; then
        echo "JS filter selected zero test files: $FILTER. Suite wiring is broken." >&2
        exit 2
    fi
    TEST_FILES=("$WPSR_SELECTED")
fi
if [ "${#TEST_FILES[@]}" -eq 0 ]; then
    echo 'JS runner discovered zero test files. Suite wiring is broken.' >&2
    exit 1
fi

if [ ! -x "$JS_DIR/node_modules/.bin/vitest" ]; then
    echo 'Installing tests-local Vitest dependencies...'
    npm ci --prefix "$JS_DIR" --ignore-scripts --no-audit --no-fund || exit 1
fi

WPSR_ZERO_OUTPUT="$(mktemp -t wpsr-js-zero.XXXXXX)"
if node "$JS_DIR/verify-results.mjs" "$JS_DIR/fixtures/zero-results.json" 0 >"$WPSR_ZERO_OUTPUT" 2>&1; then
    cat "$WPSR_ZERO_OUTPUT" >&2
    rm -f "$WPSR_ZERO_OUTPUT"
    echo 'JS zero-test verifier accepted an exit-0 zero summary.' >&2
    exit 1
else
    WPSR_ZERO_CODE=$?
fi
if [ "$WPSR_ZERO_CODE" -ne 1 ] || ! grep -q 'executed zero tests' "$WPSR_ZERO_OUTPUT"; then
    cat "$WPSR_ZERO_OUTPUT" >&2
    rm -f "$WPSR_ZERO_OUTPUT"
    echo 'JS zero-test verifier did not fail for expected reason.' >&2
    exit 1
fi
rm -f "$WPSR_ZERO_OUTPUT"
echo 'JS-ZERO-TEST-SELF-TEST: expected exit 1; exit-0 zero summary rejected.'

WPSR_RESULTS="$(mktemp -t wpsr-vitest-results.XXXXXX)"
WPSR_START_MS="$(node -e 'process.stdout.write(String(Date.now()))')"
"$JS_DIR/node_modules/.bin/vitest" run \
    --config "$JS_DIR/vitest.config.js" \
    --reporter=default \
    --reporter=json \
    --outputFile="$WPSR_RESULTS" \
    "${TEST_FILES[@]}"
WPSR_VITEST_CODE=$?
WPSR_END_MS="$(node -e 'process.stdout.write(String(Date.now()))')"

node "$JS_DIR/verify-results.mjs" "$WPSR_RESULTS" "${#TEST_FILES[@]}"
WPSR_VERIFY_CODE=$?
rm -f "$WPSR_RESULTS"

WPSR_RUNTIME_MS=$((WPSR_END_MS - WPSR_START_MS))
printf 'JS runtime: %d.%03ds\n' "$((WPSR_RUNTIME_MS / 1000))" "$((WPSR_RUNTIME_MS % 1000))"

if [ "$WPSR_VITEST_CODE" -ne 0 ] || [ "$WPSR_VERIFY_CODE" -ne 0 ]; then
    exit 1
fi
