#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "$SCRIPT_DIR/lib.sh"

[ "${1:-}" = '--yes' ] || { printf 'Refusing volume deletion without --yes.\n'; exit 2; }
wpsr_lab_require_docker
wpsr_lab_compose down --volumes --remove-orphans
printf 'WP Social Ninja test lab removed. Plugin source untouched.\n'
