<?php
/**
 * Phase 20 process-local environment canaries.
 *
 * WordPress removes strict modes while opening wpdb. Strict-axis processes put
 * back the exact hostile mode before any suite operation, then assert both the
 * process session and server global values.
 */
class WpsrEnvironmentAxis
{
    private static $wpsr_asserted = false;

    public static function current()
    {
        return trim((string) getenv('WPSR_TEST_AXIS'));
    }

    public static function assertActive($wpsr_force = false)
    {
        if (self::$wpsr_asserted && !$wpsr_force) {
            return;
        }

        $wpsr_axis = self::current();
        if ($wpsr_axis === '') {
            return;
        }

        $wpsr_axes = require __DIR__ . '/axes.php';
        if (!is_array($wpsr_axes) || !$wpsr_axes) {
            throw new RuntimeException('Environment-axis discovery returned zero axes.');
        }
        if (empty($wpsr_axes[$wpsr_axis]) || !is_array($wpsr_axes[$wpsr_axis])) {
            throw new RuntimeException('Unknown environment axis: ' . $wpsr_axis);
        }

        if ($wpsr_axis === 'strict-sql') {
            self::assertStrictSql($wpsr_axes[$wpsr_axis]);
        } elseif ($wpsr_axis === 'non-utc') {
            self::assertNonUtc($wpsr_axes[$wpsr_axis]);
        } elseif ($wpsr_axis === 'free-alone') {
            self::assertFreeAlone($wpsr_axes[$wpsr_axis]);
        } else {
            throw new RuntimeException('Environment axis has no canary implementation: ' . $wpsr_axis);
        }

        self::$wpsr_asserted = true;
    }

    private static function assertStrictSql(array $wpsr_config)
    {
        global $wpdb;

        $wpsr_expected = self::normalizeSqlMode($wpsr_config['sql_mode']);
        if (getenv('WPSR_TEST_SKIP_AXIS_SESSION_CONFIG') !== '1') {
            $wpsr_result = $wpdb->query("SET SESSION sql_mode = '" . $wpsr_config['sql_mode'] . "'");
            if ($wpsr_result === false || $wpdb->last_error !== '') {
                throw new RuntimeException('Could not apply strict SQL session mode: ' . $wpdb->last_error);
            }
        }

        $wpsr_row = $wpdb->get_row('SELECT @@SESSION.sql_mode AS session_mode, @@GLOBAL.sql_mode AS global_mode', ARRAY_A);
        if (!is_array($wpsr_row) || $wpdb->last_error !== '') {
            throw new RuntimeException('Could not read strict SQL modes: ' . $wpdb->last_error);
        }

        $wpsr_session = self::normalizeSqlMode($wpsr_row['session_mode']);
        $wpsr_global = self::normalizeSqlMode($wpsr_row['global_mode']);
        if ($wpsr_session !== $wpsr_expected) {
            throw new RuntimeException('Strict SQL session mode mismatch; expected '
                . implode(',', $wpsr_expected) . '; got ' . implode(',', $wpsr_session));
        }
        if ($wpsr_global !== $wpsr_expected) {
            throw new RuntimeException('Strict SQL global mode mismatch; expected '
                . implode(',', $wpsr_expected) . '; got ' . implode(',', $wpsr_global));
        }
        if ($wpdb->prefix === 'wp_' || $wpdb->prefix !== (string) getenv('WORDPRESS_TABLE_PREFIX')) {
            throw new RuntimeException('Strict SQL axis requires exact non-default runtime prefix; got ' . $wpdb->prefix);
        }

        // MariaDB rejects WordPress nav-menu cleanup's SELECT * ... GROUP BY ID.
        // Fixtures own no menu relationships; remove only that core callback.
        // Product queries remain fully strict.
        remove_action('delete_post', '_wp_delete_post_menu_item');

        WP_CLI::log('AXIS CANARY strict-sql: session/global=' . implode(',', $wpsr_expected)
            . '; prefix=' . $wpdb->prefix);
    }

    private static function assertNonUtc(array $wpsr_config)
    {
        $wpsr_timezone = (string) get_option('timezone_string', '');
        $wpsr_offset = (string) get_option('gmt_offset', '');
        $wpsr_local_timestamp = (int) current_time('timestamp', false);
        $wpsr_utc_timestamp = (int) current_time('timestamp', true);
        $wpsr_actual_offset = $wpsr_local_timestamp - $wpsr_utc_timestamp;
        $wpsr_local_mysql = (string) current_time('mysql', false);
        $wpsr_utc_mysql = (string) current_time('mysql', true);

        if ($wpsr_timezone !== $wpsr_config['timezone_string']) {
            throw new RuntimeException('Non-UTC timezone_string mismatch; expected '
                . $wpsr_config['timezone_string'] . '; got ' . $wpsr_timezone);
        }
        if ((float) $wpsr_offset !== (float) $wpsr_config['gmt_offset']) {
            throw new RuntimeException('Non-UTC gmt_offset mismatch; expected '
                . $wpsr_config['gmt_offset'] . '; got ' . $wpsr_offset);
        }
        if (wp_timezone_string() !== $wpsr_config['timezone_string']) {
            throw new RuntimeException('wp_timezone_string() did not expose named hostile timezone.');
        }
        if (abs($wpsr_actual_offset - (int) $wpsr_config['offset_seconds']) > 2) {
            throw new RuntimeException('current_time() local/UTC offset is vacuous or wrong; expected '
                . $wpsr_config['offset_seconds'] . '; got ' . $wpsr_actual_offset);
        }
        if ($wpsr_local_mysql === $wpsr_utc_mysql) {
            throw new RuntimeException('current_time() local and UTC values are byte-identical.');
        }

        WP_CLI::log('AXIS CANARY non-utc: timezone=' . $wpsr_timezone . '; offset=' . $wpsr_actual_offset
            . '; local=' . $wpsr_local_mysql . '; UTC=' . $wpsr_utc_mysql);
    }

    private static function assertFreeAlone(array $wpsr_config)
    {
        if (getenv('WPSR_TEST_DISABLE_FREE_CANARY') === '1') {
            throw new RuntimeException('Free-alone canary disabled; refusing false-green axis.');
        }

        // Phase 29 keeps the optional checkout mounted read-only in the family lab.
        // Free/core isolation is runtime activation, constants, hooks, and routes—not file absence.
        $wpsr_active = (array) get_option('active_plugins', []);
        if (in_array('wp-social-ninja-pro/wp-social-ninja-pro.php', $wpsr_active, true)) {
            throw new RuntimeException('Pro activation file remains active in free-alone axis.');
        }

        $wpsr_constants = [
            'WPSOCIALREVIEWS_PRO_MAIN_FILE',
            'WPSOCIALREVIEWS_PRO_VERSION',
            'WPSOCIALREVIEWS_PRO',
            'WPSOCIALREVIEWS_PRO_URL',
            'WPSOCIALREVIEWS_PRO_DIR',
        ];
        foreach ($wpsr_constants as $wpsr_constant) {
            if (defined($wpsr_constant)) {
                throw new RuntimeException('Pro constant present in free-alone axis: ' . $wpsr_constant);
            }
        }
        if (class_exists('WPSocialReviewsPro\\App\\Application', false)) {
            throw new RuntimeException('Pro application class is loaded in free-alone axis.');
        }

        $wpsr_pro_hooks = [
            'wp_ajax_nopriv_wpsr_submit_review_form',
            'wp_ajax_nopriv_wpsr_review_form_upload',
            'wp_ajax_nopriv_wpsr_twitter_cards',
            'wpsocialreviews/instagram_follow_button',
        ];
        foreach ($wpsr_pro_hooks as $wpsr_hook) {
            if (has_action($wpsr_hook) !== false) {
                throw new RuntimeException('Pro-only hook present in free-alone axis: ' . $wpsr_hook);
            }
        }
        if (did_action('wp_social_ninja_pro_loaded') !== 0) {
            throw new RuntimeException('Pro loaded lifecycle action fired in free-alone axis.');
        }

        $wpsr_server = rest_get_server();
        do_action('rest_api_init', $wpsr_server);
        $wpsr_routes = array_keys($wpsr_server->get_routes());
        foreach ($wpsr_routes as $wpsr_route) {
            if (strpos($wpsr_route, '/wpsocialreviews/v2/pro') === 0) {
                throw new RuntimeException('Pro-only REST route present in free-alone axis: ' . $wpsr_route);
            }
        }

        $wpsr_request = new WP_REST_Request('GET', $wpsr_config['pro_endpoint']);
        $wpsr_response = rest_do_request($wpsr_request);
        $wpsr_data = $wpsr_response->get_data();
        $wpsr_code = is_array($wpsr_data) && isset($wpsr_data['code']) ? $wpsr_data['code'] : '';
        if ($wpsr_response->get_status() !== 404 || $wpsr_code !== 'rest_no_route') {
            throw new RuntimeException('Pro-only endpoint was not exactly absent; got '
                . $wpsr_response->get_status() . '/' . ($wpsr_code ?: '(no code)'));
        }

        WP_CLI::log('AXIS CANARY free-alone: Pro file/constants/hooks/routes absent; endpoint=404/rest_no_route');
    }

    private static function normalizeSqlMode($wpsr_mode)
    {
        $wpsr_modes = array_values(array_filter(array_map('trim', explode(',', strtoupper((string) $wpsr_mode)))));
        sort($wpsr_modes, SORT_STRING);
        return $wpsr_modes;
    }
}
