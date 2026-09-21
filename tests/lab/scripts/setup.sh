#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
source "$SCRIPT_DIR/lib.sh"

wpsr_lab_require_docker
printf 'Validating Docker lab contract...\n'
wpsr_lab_verify_compose_contract
printf 'Building PHP test runner...\n'
wpsr_lab_compose build php-tests
printf 'Starting internal-only DB and WordPress...\n'
wpsr_lab_compose up --detach --wait --force-recreate db wordpress
wpsr_lab_verify_runtime_contract

LAB_URL="http://wordpress"

if ! wpsr_lab_wp core is-installed >/dev/null 2>&1; then
    printf 'Installing suite-only WordPress...\n'
    wpsr_lab_wp core install \
        --url="$LAB_URL" \
        --title='WP Social Ninja Test Lab' \
        --admin_user="${WPSR_LAB_ADMIN_USER:-wpsradmin}" \
        --admin_password="${WPSR_LAB_ADMIN_PASSWORD:-wpsr-lab-only}" \
        --admin_email="${WPSR_LAB_ADMIN_EMAIL:-wpsradmin@example.test}" \
        --skip-email
fi

wpsr_lab_wp option update home "$LAB_URL" >/dev/null
wpsr_lab_wp option update siteurl "$LAB_URL" >/dev/null
wpsr_lab_wp option update blog_public 0 >/dev/null

actual_db="$(wpsr_lab_wp eval 'echo DB_NAME;' --skip-plugins --skip-themes | tail -n 1)"
actual_prefix="$(wpsr_lab_wp eval 'global $wpdb; echo $wpdb->prefix;' --skip-plugins --skip-themes | tail -n 1)"
case "$(printf '%s' "$actual_db" | tr '[:upper:]' '[:lower:]')" in *test*) ;; *) wpsr_lab_die "Runtime DB '$actual_db' lacks test." ;; esac
[ "$actual_prefix" = "${WPSR_LAB_TABLE_PREFIX:-wpsntest_}" ] \
    || wpsr_lab_die "Runtime prefix '$actual_prefix' differs from configured prefix."
[ "$actual_prefix" != 'wp_' ] || wpsr_lab_die 'Runtime prefix is wp_.'

printf 'Activating core only...\n'
export WPSR_TEST_ENV=core
wpsr_lab_set_plugin_environment core

wpsr_lab_wp eval-file /var/www/html/wp-content/plugins/wp-social-ninja/tests/lab/seed.php
printf 'Lab ready: internal %s (DB=%s, prefix=%s, core-alone, no host ports)\n' "$LAB_URL" "$actual_db" "$actual_prefix"
