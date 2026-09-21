#!/usr/bin/env bash
# Docker-only WordPress root resolver. Deliberately never asks host WP-CLI.
wpsr_resolve_wp_root() {
    local root="${WPSR_LAB_WP_ROOT:-/var/www/html}"
    if [ ! -f "$root/wp-load.php" ]; then
        echo "Docker lab WordPress root missing at $root." >&2
        return 2
    fi
    printf '%s\n' "${root%/}"
}
