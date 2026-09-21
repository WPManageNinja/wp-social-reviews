#!/usr/bin/env bash
set -euo pipefail

LAB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$LAB_DIR/../.." && pwd)"
COMPOSE_PROJECT="wp-social-ninja-test-lab"
COMPOSE_FILE="$LAB_DIR/compose.yaml"
WPSR_PRO_ROOT="${WPSR_PRO_ROOT:-$REPO_ROOT/../wp-social-ninja-pro}"
if [ ! -d "$WPSR_PRO_ROOT" ]; then
    WPSR_PRO_ROOT="$REPO_ROOT/tests/.artifacts/family/absent-pro"
    mkdir -p "$WPSR_PRO_ROOT"
fi
WPSR_PRO_ROOT="$(cd "$WPSR_PRO_ROOT" && pwd)"
export WPSR_PRO_ROOT

wpsr_lab_die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
wpsr_lab_require() { command -v "$1" >/dev/null 2>&1 || wpsr_lab_die "Required command missing: $1"; }

wpsr_lab_load_env() {
    local file="$LAB_DIR/.env" line
    [ -f "$file" ] || return 0
    while IFS= read -r line || [ -n "$line" ]; do
        line="${line%$'\r'}"
        case "$line" in ''|'#'*) continue ;; esac
        [[ "$line" =~ ^WPSR_LAB_[A-Z0-9_]+= ]] || wpsr_lab_die "Malformed lab env line: $line"
        case "${line%%=*}" in
            WPSR_LAB_DB_NAME|WPSR_LAB_DB_USER|WPSR_LAB_DB_PASSWORD|WPSR_LAB_DB_ROOT_PASSWORD|WPSR_LAB_TABLE_PREFIX|WPSR_LAB_ADMIN_USER|WPSR_LAB_ADMIN_PASSWORD|WPSR_LAB_ADMIN_EMAIL) ;;
            *) wpsr_lab_die "Unknown foundation lab env key: ${line%%=*}" ;;
        esac
        export "$line"
    done < "$file"
}

wpsr_lab_assert_disposable() {
    local db="${WPSR_LAB_DB_NAME:-wpsocialninja_test_lab}"
    local prefix="${WPSR_LAB_TABLE_PREFIX:-wpsntest_}"
    [[ "$db" =~ ^[A-Za-z0-9_]+$ ]] || wpsr_lab_die "DB name contains unsafe characters: $db"
    case "$(printf '%s' "$db" | tr '[:upper:]' '[:lower:]')" in *test*) ;; *) wpsr_lab_die "DB name '$db' lacks 'test'; refusing." ;; esac
    [[ "$prefix" =~ ^[A-Za-z][A-Za-z0-9_]*_$ ]] || wpsr_lab_die "Table prefix is malformed: $prefix"
    [ "$prefix" != "wp_" ] || wpsr_lab_die "Table prefix wp_ hides prefix bugs; refusing."
}

wpsr_lab_require_docker() {
    wpsr_lab_require docker
    docker info >/dev/null 2>&1 || wpsr_lab_die "Docker daemon unavailable."
    docker compose version >/dev/null 2>&1 || wpsr_lab_die "Docker Compose v2 unavailable."
}

wpsr_lab_compose() {
    docker compose --project-name "$COMPOSE_PROJECT" --project-directory "$LAB_DIR" --file "$COMPOSE_FILE" "$@"
}

wpsr_lab_verify_compose_contract() {
    local rendered
    wpsr_lab_require php
    rendered="$(wpsr_lab_compose --profile tools config --format json)" \
        || wpsr_lab_die 'Could not render Compose contract.'
    printf '%s' "$rendered" | php "$LAB_DIR/scripts/verify-compose.php" "$REPO_ROOT" config "$WPSR_PRO_ROOT" \
        || wpsr_lab_die 'Rendered Compose contract is unsafe.'
}

wpsr_lab_verify_runtime_contract() {
    local db_id wordpress_id runtime
    db_id="$(wpsr_lab_compose ps --quiet db)"
    wordpress_id="$(wpsr_lab_compose ps --quiet wordpress)"
    [ -n "$db_id" ] && [ -n "$wordpress_id" ] || wpsr_lab_die 'Lab runtime containers are unavailable.'
    runtime="$(docker inspect "$db_id" "$wordpress_id")" || wpsr_lab_die 'Could not inspect lab containers.'
    printf '%s' "$runtime" | php "$LAB_DIR/scripts/verify-compose.php" "$REPO_ROOT" runtime "$WPSR_PRO_ROOT" \
        || wpsr_lab_die 'Runtime container contract is unsafe.'
    local network_id network_internal
    network_id="$(docker network ls --filter "label=com.docker.compose.project=$COMPOSE_PROJECT" --format '{{.ID}}' | head -n 1)"
    [ -n "$network_id" ] || wpsr_lab_die 'Lab network is unavailable.'
    network_internal="$(docker network inspect "$network_id" --format '{{.Internal}}')"
    [ "$network_internal" = 'true' ] || wpsr_lab_die 'Lab network is not internal; external egress could escape.'
}

wpsr_lab_wp() {
    wpsr_lab_compose run --rm --no-deps php-tests wp --path=/var/www/html "$@"
}

wpsr_lab_set_plugin_environment() {
    local environment="${1:-core}"
    case "$environment" in
        core|free)
            WPSR_TEST_ENV=core wpsr_lab_wp plugin deactivate wp-social-ninja-pro --skip-plugins --skip-themes >/dev/null 2>&1 || true
            WPSR_TEST_ENV=core wpsr_lab_wp plugin activate wp-social-ninja --skip-plugins --skip-themes >/dev/null
            local active
            active="$(WPSR_TEST_ENV=core wpsr_lab_wp plugin list --status=active --field=name --skip-plugins --skip-themes)"
            [ "$active" = 'wp-social-ninja' ] \
                || wpsr_lab_die "Core lab must have exactly wp-social-ninja active; found: ${active:-none}"
            ;;
        pro)
            [ -f "$WPSR_PRO_ROOT/wp-social-ninja-pro.php" ] \
                || wpsr_lab_die "Pro environment unavailable: $WPSR_PRO_ROOT/wp-social-ninja-pro.php"
            WPSR_TEST_ENV=pro wpsr_lab_wp plugin deactivate wp-social-ninja-pro wp-social-ninja --skip-plugins --skip-themes >/dev/null 2>&1 || true
            WPSR_TEST_ENV=pro wpsr_lab_wp plugin activate wp-social-ninja --skip-plugins --skip-themes >/dev/null
            WPSR_TEST_ENV=pro wpsr_lab_wp plugin activate wp-social-ninja-pro --skip-plugins --skip-themes >/dev/null
            active="$(WPSR_TEST_ENV=pro wpsr_lab_wp plugin list --status=active --field=name --skip-plugins --skip-themes)"
            [ "$active" = $'wp-social-ninja\nwp-social-ninja-pro' ] \
                || wpsr_lab_die "Pro lab must activate exactly core then Pro; found: ${active:-none}"
            ;;
        *) wpsr_lab_die "Unknown plugin environment: $environment (use core|free|pro)" ;;
    esac
}


wpsr_lab_load_env
wpsr_lab_assert_disposable
export COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT"
