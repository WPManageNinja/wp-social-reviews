<?php
/**
 * WP Social Ninja core test harness. Runs only through WP-CLI in Docker lab.
 */
if (!defined('WP_CLI') || !WP_CLI) {
    exit("WP Social Ninja tests must run through WP-CLI in tests/lab.\n");
}

class WpsrTest
{
    public static $config = [];
    public static $failures = [];
    public static $failedCases = 0;
    public static $passed = 0;
    public static $skipped = 0;

    private static $diagnostics = [];
    private static $skipRows = null;
    private static $skipIds = [];
    private static $currentCase = null;
    private static $caseSkipped = false;
    private static $startedAt = 0.0;
    private static $protectedFingerprints = [];
    private static $wordpressFingerprints = [];
    private static $databaseFingerprint = [];
    private static $optionSnapshot = [];
    private static $postSideEffectOptionRows = [];
    private static $cronSnapshot = [];
    private static $remoteRequests = [];
    private static $sentMails = [];
    private static $httpResponder = null;
    private static $pureMode = false;
    private static $queryCapture = null;
    private static $queryCaptureCallback = null;

    /** WordPress-backed helper tier with exact database isolation. */
    public static function bootPure()
    {
        self::$pureMode = true;
        self::$startedAt = microtime(true);
        self::$config = require dirname(__DIR__) . '/suite.config.php';
        self::assertExactLabEnvironment();

        $sentinel = self::$config['sentinel_class'];
        if (!class_exists($sentinel)) {
            WP_CLI::error('Core plugin is not active (missing ' . $sentinel . ').');
        }
        self::assertPluginEnvironment();
        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            WP_CLI::error('DISABLE_WP_CRON must be true in the Docker test lab.');
        }

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno) || !self::isProductionPluginFile($errfile)) {
                return false;
            }
            self::$diagnostics[] = self::errorLabel($errno) . ': ' . $errstr
                . ' (' . self::relPath($errfile) . ':' . $errline . ')';
            return true;
        });
        self::verifyIndependentOutboundGuard();
        self::$optionSnapshot = self::optionSnapshot();
        self::$databaseFingerprint = self::databaseFingerprint();
    }

    public static function boot()
    {
        self::$pureMode = false;
        self::$startedAt = microtime(true);
        self::$config = require dirname(__DIR__) . '/suite.config.php';

        global $wpdb;
        if (stripos((string) DB_NAME, 'test') === false) {
            WP_CLI::error('DB name "' . DB_NAME . '" lacks "test"; refusing non-lab database.');
        }
        if ($wpdb->prefix === 'wp_') {
            WP_CLI::error('Table prefix is wp_; refusing because prefix bugs would be invisible.');
        }
        self::assertExactLabEnvironment();

        $sentinel = self::$config['sentinel_class'];
        if (!class_exists($sentinel)) {
            WP_CLI::error('Core plugin is not active (missing ' . $sentinel . ').');
        }
        self::assertPluginEnvironment();
        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            WP_CLI::error('DISABLE_WP_CRON must be true in the Docker test lab.');
        }

        // rest_do_request() does not define this, but WPFluent validation and
        // WordPress JSON response behavior must see a real REST context.
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            if (!self::isProductionPluginFile($errfile)) {
                return false;
            }
            self::$diagnostics[] = self::errorLabel($errno) . ': ' . $errstr
                . ' (' . self::relPath($errfile) . ':' . $errline . ')';
            return true;
        });

        $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID']);
        if (!$admins) {
            WP_CLI::error('Lab has no administrator.');
        }
        wp_set_current_user($admins[0]->ID);
        self::verifyIndependentOutboundGuard();

        // Fail closed at WordPress outbound boundaries. No request or mail can
        // leave Docker during smoke; callers receive deterministic test doubles.
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            self::$remoteRequests[] = [
                'url'  => (string) $url,
                'args' => is_array($args) ? $args : [],
            ];

            // Cases may approve exact transport intents with a deterministic responder.
            // Everything else remains blocked before WordPress can open a socket.
            if (is_callable(self::$httpResponder)) {
                $response = call_user_func(self::$httpResponder, (string) $url, is_array($args) ? $args : []);
                if ($response !== false) {
                    return $response;
                }
            }

            if ($preempt !== false) {
                return $preempt;
            }

            return new WP_Error('wpsr_test_remote_blocked', 'Outbound HTTP blocked by test harness.');
        }, PHP_INT_MAX, 3);
        add_filter('pre_wp_mail', function ($preempt, $attributes) {
            self::$sentMails[] = is_array($attributes) ? $attributes : [];

            // Mail is always intercepted. Preserve an earlier test fixture verdict,
            // otherwise report simulated success without reaching PHPMailer/SMTP.
            return $preempt !== null ? $preempt : true;
        }, PHP_INT_MAX, 2);

        self::assertPersistentCachesEmpty();
        self::$protectedFingerprints = self::tableFingerprints(self::$config['protected_tables']);
        self::$wordpressFingerprints = self::tableFingerprints(self::$config['protected_wordpress_tables']);
        self::$optionSnapshot = self::optionSnapshot();
        self::$postSideEffectOptionRows = self::optionRows(['wp_calendar_block_has_published_posts']);
        self::$cronSnapshot = self::cronSnapshot();
        self::$databaseFingerprint = self::databaseFingerprint();
    }

    public static function finish($suiteName)
    {
        $guardFailure = function ($name, $detail) {
            self::$currentCase = $name;
            self::fail($detail);
            self::$failedCases++;
            self::$currentCase = null;
        };

        $protectedAfter = [];
        $wordpressAfter = [];
        $cronAfter = [];
        try {
            $optionAfter = self::optionSnapshot();
            $optionDiff = self::snapshotDiffKeys(self::$optionSnapshot, $optionAfter);
            $databaseAfter = self::databaseFingerprint();
            if ($databaseAfter !== self::$databaseFingerprint) {
                $changed = [];
                foreach (array_unique(array_merge(array_keys(self::$databaseFingerprint), array_keys($databaseAfter))) as $table) {
                    if (!isset(self::$databaseFingerprint[$table]) || !isset($databaseAfter[$table])
                        || self::$databaseFingerprint[$table] !== $databaseAfter[$table]) {
                        $changed[] = $table;
                    }
                }
                $detail = 'database content/schema-table set changed: ' . implode(', ', $changed);
                if ($optionDiff) {
                    $detail .= '; option keys changed: ' . implode(', ', $optionDiff);
                }
                $guardFailure('complete database fingerprint guard', $detail);
            }

            if (!self::$pureMode) {
                $protectedAfter = self::tableFingerprints(self::$config['protected_tables']);
                $wordpressAfter = self::tableFingerprints(self::$config['protected_wordpress_tables']);
                $cronAfter = self::cronSnapshot();
                if ($protectedAfter !== self::$protectedFingerprints) {
                    $guardFailure('protected plugin-table fingerprint guard', 'protected plugin table content changed');
                }
                if ($wordpressAfter !== self::$wordpressFingerprints) {
                    $detail = 'WordPress shared table content changed';
                    if ($optionDiff) {
                        $detail .= '; option keys changed: ' . implode(', ', $optionDiff);
                    }
                    $guardFailure('WordPress shared-table fingerprint guard', $detail);
                }
                if ($cronAfter !== self::$cronSnapshot) {
                    $guardFailure('WordPress cron-option fingerprint guard', 'WordPress cron schedule changed');
                }
            }
        } catch (Throwable $exception) {
            $guardFailure('database fingerprint execution', $exception->getMessage());
        }

        restore_error_handler();
        $suiteName = strtoupper((string) $suiteName);
        $suiteId = strtolower(str_replace('_', '-', $suiteName));
        foreach (self::$skipIds as $skipId) {
            $skipRow = self::$skipRows[$skipId] ?? [];
            if (($skipRow['suite'] ?? '') !== $suiteId) {
                $guardFailure($skipRow['test'] ?? $skipId, 'Registered SKIP suite mismatch: expected '
                    . ($skipRow['suite'] ?? '(missing)') . ', emitted by ' . $suiteId);
            }
            WP_CLI::log('WPSR-SKIP-JSON ' . wp_json_encode([
                'id' => $skipId,
                'owner_plugin' => $skipRow['owner_plugin'] ?? '',
                'suite' => $skipRow['suite'] ?? '',
                'test' => $skipRow['test'] ?? '',
                'reason_category' => $skipRow['reason_category'] ?? '',
                'reason' => $skipRow['reason'] ?? '',
                'evidence' => $skipRow['evidence'] ?? [],
                'expires_on' => $skipRow['expires_on'] ?? '',
                'revisit' => $skipRow['revisit'] ?? '',
            ]));
        }
        if (self::$skipped !== count(self::$skipIds)) {
            $guardFailure('strict SKIP summary count', 'SKIP count mismatch: harness=' . self::$skipped
                . ' registered-events=' . count(self::$skipIds));
        }

        $elapsed = round(microtime(true) - self::$startedAt, 3);
        $total = self::$passed + self::$skipped + self::$failedCases;
        WP_CLI::log('');
        WP_CLI::log(str_repeat('=', 72));
        WP_CLI::log(sprintf(
            '%s: %d/%d passed, %d failed, %d skipped (%ss)',
            $suiteName,
            self::$passed,
            $total,
            self::$failedCases,
            self::$skipped,
            $elapsed
        ));
        WP_CLI::log('WPSR-SUITE-JSON ' . wp_json_encode([
            'suite' => $suiteName,
            'pass' => self::$passed,
            'fail' => self::$failedCases,
            'skip' => self::$skipped,
            'total' => $total,
            'assertion_failures' => count(self::$failures),
            'runtime' => $elapsed,
            'skip_ids' => array_values(self::$skipIds),
        ]));
        WP_CLI::log('Database fingerprint: ' . hash('sha256', wp_json_encode(isset($databaseAfter) ? $databaseAfter : [])));
        if (self::$pureMode) {
            WP_CLI::log('Helper tier isolation: complete database content fingerprint restored.');
        } else {
            WP_CLI::log('Protected fingerprints: ' . json_encode($protectedAfter));
            WP_CLI::log('WordPress fingerprints: ' . json_encode($wordpressAfter));
            WP_CLI::log('Cron fingerprint: ' . hash('sha256', wp_json_encode($cronAfter)));
            WP_CLI::log('Outbound intercepted: HTTP ' . count(self::$remoteRequests) . ', mail ' . count(self::$sentMails));
        }

        if (self::$failures) {
            WP_CLI::log('');
            foreach (self::$failures as $index => $failure) {
                WP_CLI::log(($index + 1) . ') ' . $failure['name']);
                foreach (explode("\n", rtrim($failure['detail'])) as $line) {
                    WP_CLI::log('   ' . $line);
                }
            }
            WP_CLI::log(str_repeat('=', 72));
            WP_CLI::log('WPSR-SUITE-END ' . $suiteName . ' FAIL');
            WP_CLI::halt(1);
        }

        WP_CLI::log(str_repeat('=', 72));
        WP_CLI::log('WPSR-SUITE-END ' . $suiteName . ' PASS');
        WP_CLI::halt(0);
    }

    public static function case($name, callable $callback)
    {
        global $wpdb;

        self::$currentCase = $name;
        self::$diagnostics = [];
        self::$caseSkipped = false;
        $failedBefore = count(self::$failures);
        $wpdb->last_error = '';

        try {
            $callback();
        } catch (Throwable $exception) {
            self::fail('threw ' . get_class($exception) . ': ' . $exception->getMessage()
                . ' (' . self::relPath($exception->getFile()) . ':' . $exception->getLine() . ')');
        }

        if ($wpdb->last_error !== '') {
            self::fail('DATABASE ERROR: ' . $wpdb->last_error);
            $wpdb->last_error = '';
        }
        if (self::$diagnostics) {
            self::fail("PHP diagnostics raised:\n  - " . implode("\n  - ", self::$diagnostics));
        }
        if (count(self::$failures) > $failedBefore) {
            self::$failedCases++;
        } elseif (!self::$caseSkipped) {
            self::$passed++;
        }
        self::$currentCase = null;
    }

    public static function fail($detail)
    {
        self::$failures[] = ['name' => self::$currentCase ?: '(no case)', 'detail' => $detail];
    }

    public static function skip($skipId)
    {
        $skipRow = self::skipRow($skipId);
        if (!$skipRow) {
            return;
        }
        if (($skipRow['reason_category'] ?? '') === 'known-failure') {
            self::fail('Known failure ' . $skipId . ' must execute through WpsrTest::parkKnownFailure().');
            return;
        }
        self::recordSkip($skipId, $skipRow);
    }

    /**
     * Execute a real known-defect probe and park only its exact normalized fingerprint.
     * Unexpected success, another result, diagnostics drift, DB errors, or exceptions stay red.
     */
    public static function parkKnownFailure($skipId, $expectedResult, callable $probe, array $expectedDiagnostics = [])
    {
        global $wpdb;
        $skipRow = self::skipRow($skipId);
        if (!$skipRow) {
            return;
        }
        if (($skipRow['reason_category'] ?? '') !== 'known-failure') {
            self::fail('parkKnownFailure requires known-failure metadata for ' . $skipId . '.');
            return;
        }

        $failureCount = count(self::$failures);
        $wpdb->last_error = '';
        try {
            $actualResult = $probe();
        } catch (Throwable $exception) {
            self::fail('Known-failure probe ' . $skipId . ' threw ' . get_class($exception) . ': '
                . $exception->getMessage() . ' (' . self::relPath($exception->getFile()) . ':' . $exception->getLine() . ')');
            return;
        }

        $actualDiagnostics = array_map([__CLASS__, 'normalizeDiagnostic'], self::$diagnostics);
        self::$diagnostics = [];
        $databaseError = (string) $wpdb->last_error;
        $wpdb->last_error = '';
        if (count(self::$failures) !== $failureCount) {
            self::fail('Known-failure probe ' . $skipId . ' emitted assertion failures before fingerprint comparison.');
            return;
        }
        if ($databaseError !== '') {
            self::fail('Known-failure probe ' . $skipId . ' raised different database error: ' . $databaseError);
            return;
        }
        if ($actualResult !== $expectedResult || $actualDiagnostics !== $expectedDiagnostics) {
            self::fail('Known failure ' . $skipId . " changed or unexpectedly passed.\n  expected result: "
                . self::exportValue($expectedResult) . "\n  actual result:   " . self::exportValue($actualResult)
                . "\n  expected diagnostics: " . self::exportValue($expectedDiagnostics)
                . "\n  actual diagnostics:   " . self::exportValue($actualDiagnostics));
            return;
        }

        self::recordSkip($skipId, $skipRow);
    }

    public static function assertNoDiagnostics($label)
    {
        if (!self::$diagnostics) {
            return;
        }

        self::fail($label . " raised PHP diagnostics:\n  - " . implode("\n  - ", self::$diagnostics));
        self::$diagnostics = [];
    }

    public static function assertNoDatabaseError($label)
    {
        global $wpdb;

        if ($wpdb->last_error === '') {
            return;
        }

        $error = $wpdb->last_error;
        $wpdb->last_error = '';
        throw new RuntimeException($label . ' raised database error: ' . $error);
    }

    /**
     * Reset and inspect intercepted transports around one automation case.
     * Tests still cannot disable the fail-closed boundary installed in boot().
     */
    public static function resetOutbound()
    {
        self::$remoteRequests = [];
        self::$sentMails = [];
    }

    public static function setHttpResponder(callable $responder)
    {
        self::$httpResponder = $responder;
    }

    public static function clearHttpResponder()
    {
        self::$httpResponder = null;
    }

    public static function remoteRequests()
    {
        return self::$remoteRequests;
    }

    public static function sentMails()
    {
        return self::$sentMails;
    }

    /**
     * Capture only SQL issued while one measured production operation runs.
     * WordPress's `query` filter observes real $wpdb queries without replacing DB/model behavior.
     */
    public static function beginQueryCapture()
    {
        if (self::$queryCaptureCallback !== null) {
            throw new RuntimeException('Nested query capture is not allowed.');
        }

        self::$queryCapture = [];
        self::$queryCaptureCallback = function ($query) {
            self::$queryCapture[] = (string) $query;
            return $query;
        };
        add_filter('query', self::$queryCaptureCallback, PHP_INT_MAX, 1);
    }

    public static function endQueryCapture()
    {
        if (self::$queryCaptureCallback === null) {
            throw new RuntimeException('Query capture was not started.');
        }

        remove_filter('query', self::$queryCaptureCallback, PHP_INT_MAX);
        self::$queryCaptureCallback = null;
        $queries = self::$queryCapture;
        self::$queryCapture = null;

        return $queries;
    }

    public static function assertSame($expected, $actual, $label)
    {
        if ($expected === $actual) {
            return;
        }

        self::fail($label . "\n  expected: " . self::exportValue($expected)
            . "\n  actual:   " . self::exportValue($actual));
    }

    public static function assertTrue($condition, $label)
    {
        if (!$condition) {
            self::fail($label);
        }
    }

    /**
     * Compare complete normalized snapshots and require the exact leaf-level diff.
     * Expected shape: ['path.to.field' => ['before' => value, 'after' => value]].
     */
    public static function assertOnlyChanges($before, $after, array $expected, $label)
    {
        $actual = [];
        self::collectSnapshotChanges($before, $after, '', $actual);
        ksort($actual, SORT_STRING);
        ksort($expected, SORT_STRING);
        self::assertSame($expected, $actual, $label . ' exact full-snapshot diff');
    }

    /** Complete content fingerprint for every base table; independent from engine CHECKSUM support. */
    public static function databaseFingerprint()
    {
        global $wpdb;
        $wpdb->last_error = '';
        $tables = $wpdb->get_col("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Could not list database tables: ' . $wpdb->last_error);
        }
        if (!$tables) {
            throw new RuntimeException('Database fingerprint discovered zero base tables.');
        }

        sort($tables, SORT_STRING);
        $fingerprint = [];
        foreach ($tables as $table) {
            $fingerprint[$table] = self::fingerprintTable($table);
        }
        return $fingerprint;
    }

    /** Restore exact suite baseline only when do_pings is the sole cron drift. */
    public static function restoreOwnedPostCronSideEffect()
    {
        if (self::$pureMode || !is_array(self::$cronSnapshot)) {
            return;
        }
        $current = self::cronSnapshot();
        $strip = function (array $cron) {
            foreach ($cron as $timestamp => &$hooks) {
                if ($timestamp === 'version' || !is_array($hooks)) {
                    continue;
                }
                unset($hooks['do_pings']);
                if (!$hooks) {
                    unset($cron[$timestamp]);
                }
            }
            unset($hooks);
            return $cron;
        };
        if ($strip($current) === $strip(self::$cronSnapshot) && $current !== self::$cronSnapshot) {
            update_option('cron', self::$cronSnapshot);
        }
        self::restoreOptionRows(self::$postSideEffectOptionRows, ['wp_calendar_block_has_published_posts']);
    }

    public static function assertHealthy(array $result, $label, array $okStatuses = [200, 201, 204])
    {
        if ($result['db_error'] !== '') {
            self::fail($label . "\n  DATABASE ERROR: " . $result['db_error']);
            return;
        }
        if ($result['is_exception']) {
            self::fail($label . "\n  PLUGIN EXCEPTION: " . $result['message']);
            return;
        }
        if (!in_array($result['status'], $okStatuses, true)) {
            self::fail($label . "\n  unexpected status " . $result['status']
                . ' (allowed: ' . implode(',', $okStatuses) . ')'
                . ($result['message'] !== '' ? "\n  message: " . $result['message'] : ''));
        }
    }

    public static function rest($method, $route, array $params = [])
    {
        global $wpdb;
        $wpdb->last_error = '';

        $accessor = self::$config['app_bootstrap'];
        $requestClass = self::$config['request_class'];
        $app = call_user_func($accessor);
        if (!$app || !method_exists($app, 'instance')) {
            throw new RuntimeException($accessor . '() did not return booted application container.');
        }
        $app->instance($requestClass, new $requestClass($app, [], []));

        $path = '/' . trim(self::$config['rest_namespace'], '/') . '/' . ltrim($route, '/');
        $path = rtrim($path, '/');
        $request = new WP_REST_Request(strtoupper($method), $path);
        if (strtoupper($method) === 'GET') {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
        }

        // WP Social Ninja has REST handlers that terminate through
        // wp_send_json(). Force hookable AJAX wp_die(), swallow that terminal
        // marker, and read echoed JSON without losing process or DB error.
        $sentStatus = null;
        $died = false;
        $forceAjax = '__return_true';
        $swallowDie = function () use (&$died) {
            return function () use (&$died) {
                $died = true;
            };
        };
        $captureStatus = function ($header, $code) use (&$sentStatus) {
            $sentStatus = (int) $code;
            return $header;
        };
        add_filter('wp_doing_ajax', $forceAjax);
        add_filter('wp_die_ajax_handler', $swallowDie, PHP_INT_MAX);
        add_filter('status_header', $captureStatus, PHP_INT_MAX, 2);

        ob_start();
        try {
            $response = rest_do_request($request);
            $echoed = ob_get_contents();
        } finally {
            ob_end_clean();
            remove_filter('wp_doing_ajax', $forceAjax);
            remove_filter('wp_die_ajax_handler', $swallowDie, PHP_INT_MAX);
            remove_filter('status_header', $captureStatus, PHP_INT_MAX);
        }

        if ($died && $echoed !== '') {
            $decoded = json_decode($echoed, true);
            $data = json_last_error() === JSON_ERROR_NONE ? $decoded : $echoed;
            $status = $sentStatus !== null ? $sentStatus : 200;
        } else {
            $data = $response->get_data();
            $status = $response->get_status();
        }

        $isException = is_array($data)
            && isset($data['code'])
            && in_array($data['code'], ['plugin_exception', 'internal_server_error', 'rest_error'], true);
        $message = '';
        if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        } elseif (is_array($data) && isset($data['data']['message']) && is_string($data['data']['message'])) {
            $message = $data['data']['message'];
        }

        return [
            'status' => (int) $status,
            'data' => $data,
            'db_error' => (string) $wpdb->last_error,
            'is_exception' => $isException,
            'message' => $message,
        ];
    }

    public static function assertPersistentCachesEmpty()
    {
        global $wpdb;
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            WP_CLI::error('Persistent object-cache drop-ins are forbidden; entry ownership cannot be proven without enumeration.');
        }
        $rows = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}\n"
            . "WHERE option_name LIKE '\\_transient\\_wpsr%'\n"
            . "OR option_name LIKE '\\_transient\\_timeout\\_wpsr%'"
        );
        if ($wpdb->last_error !== '') {
            WP_CLI::error('Could not inspect persistent plugin transients: ' . $wpdb->last_error);
        }
        $cacheTable = $wpdb->prefix . 'wpsr_caches';
        $cacheCount = 0;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($cacheTable))) === $cacheTable) {
            $cacheCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$cacheTable}`");
        }
        if ($rows || $cacheCount) {
            WP_CLI::error('Persistent plugin cache state is not empty (transients=' . count($rows)
                . ', rows=' . $cacheCount . '). Refusing to delete pre-existing data; reset disposable lab.');
        }
        // Never flush a shared object-cache group before fingerprinting; APIs do
        // not expose enough identity to prove entries belong to this run.
        return 0;
    }

    private static function tableFingerprints(array $suffixes)
    {
        global $wpdb;
        $fingerprints = [];
        foreach ($suffixes as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ($found !== $table) {
                WP_CLI::error('Fingerprint table missing: ' . $table);
            }
            $fingerprints[$suffix] = self::fingerprintTable($table);
        }
        return $fingerprints;
    }

    private static function fingerprintTable($table)
    {
        global $wpdb;
        $safeTable = str_replace('`', '``', (string) $table);
        $wpdb->last_error = '';
        $rows = $wpdb->get_results("SELECT * FROM `{$safeTable}`", ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($rows)) {
            throw new RuntimeException('Could not read fingerprint rows for ' . $table . ': ' . $wpdb->last_error);
        }
        $rowHashes = [];
        foreach ($rows as $row) {
            ksort($row, SORT_STRING);
            $rowHashes[] = hash('sha256', serialize($row));
        }
        sort($rowHashes, SORT_STRING);
        return [
            'count' => count($rows),
            'content_sha256' => hash('sha256', implode("\n", $rowHashes)),
        ];
    }

    private static function optionSnapshot()
    {
        global $wpdb;
        $wpdb->last_error = '';
        $rows = $wpdb->get_results("SELECT option_name, option_value, autoload FROM {$wpdb->options} ORDER BY option_name ASC", ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($rows)) {
            throw new RuntimeException('Could not snapshot WordPress options: ' . $wpdb->last_error);
        }
        $snapshot = [];
        foreach ($rows as $row) {
            $snapshot[(string) $row['option_name']] = hash('sha256', serialize([
                (string) $row['option_value'],
                (string) $row['autoload'],
            ]));
        }
        return $snapshot;
    }

    private static function optionRows(array $names)
    {
        global $wpdb;
        $rows = array_fill_keys($names, null);
        if (!$names) {
            return $rows;
        }
        $placeholders = implode(',', array_fill(0, count($names), '%s'));
        $query = $wpdb->prepare(
            "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
            $names
        );
        $found = $wpdb->get_results($query, ARRAY_A);
        WpsrTest::assertNoDatabaseError('post side-effect option snapshot');
        foreach ((array) $found as $row) {
            $rows[(string) $row['option_name']] = $row;
        }
        return $rows;
    }

    private static function restoreOptionRows(array $baseline, array $names)
    {
        global $wpdb;
        $current = self::optionRows($names);
        foreach ($names as $name) {
            $expected = $baseline[$name] ?? null;
            $actual = $current[$name] ?? null;
            if ($expected === null) {
                if ($actual !== null) {
                    delete_option($name);
                }
                continue;
            }
            if ($actual !== null && (int) $actual['option_id'] !== (int) $expected['option_id']) {
                throw new RuntimeException('Refusing to restore replaced WordPress side-effect option ' . $name . '.');
            }
            if ($actual === null) {
                $inserted = $wpdb->insert($wpdb->options, $expected, ['%d', '%s', '%s', '%s']);
                if ($inserted !== 1) {
                    throw new RuntimeException('Could not restore deleted WordPress side-effect option ' . $name . '.');
                }
            } elseif ($actual !== $expected) {
                $updated = $wpdb->update(
                    $wpdb->options,
                    ['option_value' => $expected['option_value'], 'autoload' => $expected['autoload']],
                    ['option_id' => (int) $expected['option_id']],
                    ['%s', '%s'],
                    ['%d']
                );
                if ($updated === false) {
                    throw new RuntimeException('Could not restore changed WordPress side-effect option ' . $name . '.');
                }
            }
            wp_cache_delete($name, 'options');
        }
        WpsrTest::assertNoDatabaseError('post side-effect option restoration');
    }

    private static function snapshotDiffKeys(array $before, array $after)
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changed = [];
        foreach ($keys as $key) {
            if (!isset($before[$key]) || !isset($after[$key]) || $before[$key] !== $after[$key]) {
                $changed[] = (string) $key;
            }
        }
        sort($changed, SORT_STRING);
        return $changed;
    }

    private static function cronSnapshot()
    {
        $cron = get_option('cron', []);
        return is_array($cron) ? $cron : [];
    }

    private static function assertExactLabEnvironment()
    {
        global $wpdb;
        $expectedDb = trim((string) getenv('WORDPRESS_DB_NAME'));
        $expectedHost = trim((string) getenv('WORDPRESS_DB_HOST'));
        $expectedPrefix = trim((string) getenv('WORDPRESS_TABLE_PREFIX'));
        if (getenv('WPSR_TEST_LAB') !== '1') {
            WP_CLI::error('WPSR_TEST_LAB=1 is required; refusing an unverified WordPress process.');
        }
        if ($expectedDb === '' || stripos($expectedDb, 'test') === false || (string) DB_NAME !== $expectedDb) {
            WP_CLI::error('Runtime DB name does not exactly match guarded test-lab environment.');
        }
        if ($expectedHost !== 'db:3306' || !defined('DB_HOST') || (string) DB_HOST !== $expectedHost) {
            WP_CLI::error('Runtime DB host must be the internal Compose service db:3306.');
        }
        if ($expectedPrefix === '' || $expectedPrefix === 'wp_'
            || !preg_match('/^[A-Za-z][A-Za-z0-9_]*_$/', $expectedPrefix)
            || (string) $wpdb->prefix !== $expectedPrefix) {
            WP_CLI::error('Runtime table prefix does not exactly match guarded non-default lab prefix.');
        }
        if (!function_exists('wp_get_environment_type') || wp_get_environment_type() !== 'local') {
            WP_CLI::error('WP_ENVIRONMENT_TYPE must be local in the disposable lab.');
        }

        $pluginRoot = realpath(dirname(__DIR__, 2));
        $expectedRoot = realpath('/var/www/html/wp-content/plugins/' . self::$config['plugin_slug']);
        $workspaceRoot = realpath('/workspace/' . self::$config['plugin_slug']);
        if (!$pluginRoot || !$expectedRoot || !$workspaceRoot || $pluginRoot !== $expectedRoot) {
            WP_CLI::error('Plugin must execute from the active read-only Docker mount.');
        }
        $sentinel = '/wp-social-reviews.php';
        if (!is_file($workspaceRoot . $sentinel)
            || hash_file('sha256', $pluginRoot . $sentinel) !== hash_file('sha256', $workspaceRoot . $sentinel)) {
            WP_CLI::error('Workspace and active plugin dual mounts do not expose identical core source.');
        }
        if (is_writable($pluginRoot . '/tests')) {
            WP_CLI::error('Active plugin source mount is writable; refusing unsafe lab execution.');
        }
        if (trim((string) getenv('WPSR_TEST_ENV')) === 'pro') {
            $proRoot = realpath('/var/www/html/wp-content/plugins/wp-social-ninja-pro');
            $proWorkspace = realpath('/workspace/wp-social-ninja-pro');
            $proSentinel = '/wp-social-ninja-pro.php';
            if (!$proRoot || !$proWorkspace || !is_file($proRoot . $proSentinel)
                || hash_file('sha256', $proRoot . $proSentinel) !== hash_file('sha256', $proWorkspace . $proSentinel)
                || is_writable($proRoot . '/tests')) {
                WP_CLI::error('Pro must execute from identical read-only active/workspace mounts.');
            }
        }
    }

    private static function verifyIndependentOutboundGuard()
    {
        if (!defined('WPSR_TEST_LAB_OUTBOUND_GUARD') || WPSR_TEST_LAB_OUTBOUND_GUARD !== true) {
            WP_CLI::error('Independent MU outbound guard is not active.');
        }
        $http = apply_filters('pre_http_request', false, [], 'https://wpsr-guard-probe.invalid/');
        if (!is_wp_error($http) || $http->get_error_code() !== 'wpsr_lab_http_blocked') {
            WP_CLI::error('Independent MU HTTP guard did not fail closed.');
        }
        $mail = apply_filters('pre_wp_mail', null, ['to' => 'guard-probe@example.invalid']);
        if ($mail !== true) {
            WP_CLI::error('Independent MU mail guard did not intercept delivery.');
        }
    }

    private static function assertPluginEnvironment()
    {
        $environment = trim((string) getenv('WPSR_TEST_ENV'));
        $environment = $environment === '' || $environment === 'free' ? 'core' : $environment;
        if (!in_array($environment, ['core', 'pro'], true)) {
            WP_CLI::error('Unknown WPSR_TEST_ENV ' . $environment . '; use core|free|pro.');
        }
        if ($environment === 'core') {
            if (defined('WPSOCIALREVIEWS_PRO') || did_action('wp_social_ninja_pro_loaded') > 0) {
                WP_CLI::error('Core environment requires WP Social Ninja Pro to be inactive.');
            }
            return;
        }
        if (!defined('WPSOCIALREVIEWS_PRO') || !defined('WPSOCIALREVIEWS_PRO_VERSION')
            || did_action('wp_social_ninja_pro_loaded') < 1) {
            WP_CLI::error('Pro environment requires exact Pro constants and lifecycle activation.');
        }
    }

    private static function isProductionPluginFile($file)
    {
        $file = str_replace('\\', '/', (string) $file);
        $roots = [self::$config['plugin_dir_hint'], '/wp-social-ninja-pro'];
        foreach ($roots as $root) {
            if (strpos($file, $root . '/app/') !== false
                || strpos($file, $root . '/boot/') !== false
                || strpos($file, $root . '/database/') !== false
                || substr($file, -strlen($root . '/wp-social-reviews.php')) === $root . '/wp-social-reviews.php') {
                return true;
            }
        }
        return false;
    }

    private static function relPath($file)
    {
        $file = str_replace('\\', '/', (string) $file);
        foreach ([self::$config['plugin_dir_hint'], '/wp-social-ninja-pro'] as $root) {
            $position = strpos($file, $root);
            if ($position !== false) {
                return substr($file, $position);
            }
        }
        return basename($file);
    }

    private static function collectSnapshotChanges($before, $after, $path, array &$changes)
    {
        if (is_object($before)) {
            $before = get_object_vars($before);
        }
        if (is_object($after)) {
            $after = get_object_vars($after);
        }

        if (is_array($before) && is_array($after)) {
            $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
            foreach ($keys as $key) {
                $childPath = $path === '' ? (string) $key : $path . '.' . $key;
                $beforeExists = array_key_exists($key, $before);
                $afterExists = array_key_exists($key, $after);
                if (!$beforeExists || !$afterExists) {
                    $changes[$childPath] = [
                        'before' => $beforeExists ? $before[$key] : '__WPSR_MISSING__',
                        'after'  => $afterExists ? $after[$key] : '__WPSR_MISSING__',
                    ];
                    continue;
                }
                self::collectSnapshotChanges($before[$key], $after[$key], $childPath, $changes);
            }
            return;
        }

        if ($before !== $after) {
            $changes[$path === '' ? '(root)' : $path] = ['before' => $before, 'after' => $after];
        }
    }

    private static function exportValue($value)
    {
        if (is_array($value) || is_object($value)) {
            $encoded = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $encoded === false ? var_export($value, true) : $encoded;
        }

        return var_export($value, true);
    }

    private static function skipRow($skipId)
    {
        if (self::$skipRows === null) {
            $skipPath = dirname(__DIR__) . '/skips/registry.json';
            $source = is_file($skipPath) ? file_get_contents($skipPath) : false;
            $registry = $source !== false ? json_decode($source, true) : null;
            self::$skipRows = [];
            foreach ((array) ($registry['skips'] ?? []) as $row) {
                if (!empty($row['id']) && is_string($row['id'])) {
                    self::$skipRows[$row['id']] = $row;
                }
            }
        }
        if (!is_string($skipId) || !isset(self::$skipRows[$skipId])) {
            self::fail('Undeclared runtime SKIP: ' . (string) $skipId);
            return null;
        }
        return self::$skipRows[$skipId];
    }

    private static function recordSkip($skipId, array $skipRow)
    {
        if (($skipRow['test'] ?? '') !== self::$currentCase) {
            self::fail('Registered SKIP test mismatch for ' . $skipId . ': expected "' . ($skipRow['test'] ?? '') . '"');
            return;
        }
        if (in_array($skipId, self::$skipIds, true)) {
            self::fail('Duplicate runtime SKIP: ' . $skipId);
            return;
        }
        self::$caseSkipped = true;
        self::$skipped++;
        self::$skipIds[] = $skipId;
        WP_CLI::log('  SKIP ' . self::$currentCase . ' — active fingerprint reproduced; id=' . $skipId
            . ' category=' . $skipRow['reason_category'] . ' reason=' . $skipRow['reason']);
    }

    private static function normalizeDiagnostic($diagnostic)
    {
        return preg_replace('/ \(plugins\/wp-social-ninja\/[^:]+:\d+\)$/', '', (string) $diagnostic);
    }

    private static function errorLabel($errno)
    {
        $labels = [
            E_WARNING => 'Warning',
            E_NOTICE => 'Notice',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
        ];
        return isset($labels[$errno]) ? $labels[$errno] : 'Error(' . $errno . ')';
    }
}
