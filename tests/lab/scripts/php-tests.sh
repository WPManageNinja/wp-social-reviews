#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "$SCRIPT_DIR/lib.sh"

wpsr_lab_require_docker
wpsr_lab_verify_compose_contract
wpsr_lab_verify_runtime_contract
if ! wpsr_lab_wp core is-installed >/dev/null 2>&1; then
    wpsr_lab_die 'Lab not installed. Run: bash tests/lab/scripts/setup.sh'
fi

environment="${WPSR_TEST_ENV:-core}"
if [ "${1:-}" = "--env" ]; then
    [ "$#" -ge 2 ] || wpsr_lab_die '--env requires core|free|pro'
    environment="$2"
    shift 2
elif [[ "${1:-}" == --env=* ]]; then
    environment="${1#--env=}"
    shift
fi
[ "$environment" != "free" ] || environment="core"
case "$environment" in core|pro) ;; *) wpsr_lab_die "Unknown plugin environment: $environment (use core|free|pro)" ;; esac
export WPSR_TEST_ENV="$environment"
wpsr_lab_set_plugin_environment "$environment"

runner="${1:-tests/bin/run-smoke.php}"
shift || true
case "$runner" in
    tests/bin/run-smoke.php|tests/bin/run-permissions.php|tests/bin/run-pure.php|tests/bin/run-integration.php|tests/bin/run-public.php|tests/bin/run-throughput.php|tests/bin/run-family-canary.php|tests/bin/run-addon.php) ;;
    *) wpsr_lab_die "Unknown or unsafe registered runner: $runner" ;;
esac
[ -f "$REPO_ROOT/$runner" ] || wpsr_lab_die "Runner file is missing: $runner"

transcript="$(mktemp "${TMPDIR:-/tmp}/wpsr-suite-output.XXXXXX")"
cleanup_transcript() { rm -f "$transcript"; }
trap cleanup_transcript EXIT
set +e
wpsr_lab_wp eval-file "/var/www/html/wp-content/plugins/wp-social-ninja/$runner" "$@" 2>&1 | tee "$transcript"
child_status=${PIPESTATUS[0]}
set -e
if [ "$child_status" -ne 0 ]; then
    printf 'ERROR: child runner failed with exit %d: %s\n' "$child_status" "$runner" >&2
    exit "$child_status"
fi
php "$REPO_ROOT/tests/lib/validate-suite-output.php" "$transcript" \
    || wpsr_lab_die "Runner returned zero but emitted an invalid/partial summary: $runner"
