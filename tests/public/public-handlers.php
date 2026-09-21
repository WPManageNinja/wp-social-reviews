<?php
/** Phase 18 behavioral verification for core public/unauthenticated handlers. */

use WPSocialReviews\App\Hooks\Handlers\ShortcodeHandler;
use WPSocialReviews\App\Services\Platforms\Chats\Config as ChatConfig;
use WPSocialReviews\App\Services\PermissionManager;

if (!class_exists('WpsrPublicAjaxTermination')) {
    class WpsrPublicAjaxTermination extends RuntimeException {}
}

return function () {
    $wpsr_admin_id = get_current_user_id();
    $wpsr_manifest = require __DIR__ . '/handlers.manifest.php';
    $wpsr_upload_root = trailingslashit(sys_get_temp_dir()) . 'wpsr-public-' . sanitize_key(WpsrFactory::marker());
    if (file_exists($wpsr_upload_root)) {
        throw new RuntimeException('Refusing to reuse pre-existing public upload fixture path: ' . $wpsr_upload_root);
    }

    $wpsr_upload_filter = function ($uploads) use ($wpsr_upload_root) {
        $uploads['basedir'] = $wpsr_upload_root;
        $uploads['baseurl'] = 'https://example.test/wpsr-public-' . sanitize_key(WpsrFactory::marker());
        $uploads['path'] = $wpsr_upload_root;
        $uploads['url'] = $uploads['baseurl'];
        $uploads['subdir'] = '';
        return $uploads;
    };
    add_filter('upload_dir', $wpsr_upload_filter, PHP_INT_MAX);

    $wpsr_remove_tree = function ($dir) use (&$wpsr_remove_tree) {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir()) {
                $wpsr_remove_tree($item->getPathname());
            } else {
                wp_delete_file($item->getPathname());
            }
        }
        rmdir($dir);
    };

    $wpsr_file_fingerprint = function () use ($wpsr_upload_root) {
        if (!is_dir($wpsr_upload_root)) {
            return [];
        }
        $rows = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($wpsr_upload_root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $wpsr_upload_root)) + 1);
            $rows[$relative] = [$file->getSize(), hash_file('sha256', $file->getPathname())];
        }
        ksort($rows, SORT_STRING);
        return $rows;
    };

    $wpsr_anonymous_nonce = function () use ($wpsr_admin_id) {
        wp_set_current_user(0);
        $nonce = wp_create_nonce('wpsr-ajax-nonce');
        wp_set_current_user($wpsr_admin_id);
        return $nonce;
    };

    $wpsr_ajax = function ($hook, array $params, $method = 'GET') use ($wpsr_admin_id) {
        $oldGet = $_GET;
        $oldPost = $_POST;
        $oldRequest = $_REQUEST;
        $oldMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $status = null;
        $terminated = false;
        $method = strtoupper($method);
        $params = array_merge(['action' => $hook], $params);

        $_GET = $method === 'GET' ? $params : [];
        $_POST = $method === 'POST' ? $params : [];
        $_REQUEST = $params;
        $_SERVER['REQUEST_METHOD'] = $method;
        wp_set_current_user(0);
        PermissionManager::currentUserPermissions(false);

        $forceAjax = '__return_true';
        $dieHandler = function () {
            return function () {
                throw new WpsrPublicAjaxTermination('wp_send_json terminated request');
            };
        };
        $statusHandler = function ($header, $code) use (&$status) {
            $status = (int) $code;
            return $header;
        };
        add_filter('wp_doing_ajax', $forceAjax);
        add_filter('wp_die_ajax_handler', $dieHandler, PHP_INT_MAX);
        add_filter('status_header', $statusHandler, PHP_INT_MAX, 2);

        ob_start();
        try {
            do_action('wp_ajax_nopriv_' . $hook);
        } catch (WpsrPublicAjaxTermination $exception) {
            $terminated = true;
        } finally {
            $output = ob_get_clean();
            remove_filter('wp_doing_ajax', $forceAjax);
            remove_filter('wp_die_ajax_handler', $dieHandler, PHP_INT_MAX);
            remove_filter('status_header', $statusHandler, PHP_INT_MAX);
            $_GET = $oldGet;
            $_POST = $oldPost;
            $_REQUEST = $oldRequest;
            if ($oldMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $oldMethod;
            }
            wp_set_current_user($wpsr_admin_id);
            PermissionManager::currentUserPermissions(false);
        }

        $decoded = json_decode($output, true);
        return [
            'status' => $status === null ? 200 : $status,
            'output' => $output,
            'json' => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
            'terminated' => $terminated,
        ];
    };

    $wpsr_isolated_probe = function ($mode, $templateId = 0) {
        $probe = dirname(__DIR__) . '/lib/public-isolated-probe.php';
        if (!is_file($probe) || !function_exists('proc_open')) {
            throw new RuntimeException('Isolated public probe process is unavailable.');
        }
        $pipes = [];
        $process = proc_open([
            '/usr/local/bin/wp',
            '--path=' . ABSPATH,
            'eval-file',
            $probe,
            (string) $mode,
            (string) (int) $templateId,
            '--skip-themes',
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, ABSPATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start isolated public probe.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        return [
            'exit' => (int) $status,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    };

    $wpsr_assert_noop = function (callable $request, $label) use ($wpsr_file_fingerprint) {
        $beforeDb = WpsrTest::databaseFingerprint();
        $beforeFiles = $wpsr_file_fingerprint();
        WpsrTest::resetOutbound();
        $result = $request();
        WpsrTest::assertSame($beforeDb, WpsrTest::databaseFingerprint(), $label . ' changes no database table/options/posts/cron state');
        WpsrTest::assertSame($beforeFiles, $wpsr_file_fingerprint(), $label . ' creates/deletes/changes no file');
        WpsrTest::assertSame([], WpsrTest::remoteRequests(), $label . ' makes no outbound HTTP request');
        WpsrTest::assertSame([], WpsrTest::sentMails(), $label . ' sends no mail');
        return $result;
    };

    $wpsr_review_fixture = function ($status = 'publish', $postType = 'wp_social_reviews', $password = '') {
        $marker = WpsrFactory::marker();
        $source = $marker . '-public-source';
        $visible = WpsrFactory::review([
            'platform_name' => 'custom',
            'source_id' => $source,
            'review_title' => $marker . ' visible & <script>title</script>',
            'reviewer_name' => $marker . ' Visible Reviewer',
            'reviewer_text' => '<strong>' . $marker . ' allowed</strong><img src="x" onerror="bad()"><script>hidden()</script>',
            'review_time' => '2026-07-01 01:00:00',
            'review_approved' => 1,
        ]);
        $pending = WpsrFactory::review([
            'platform_name' => 'custom',
            'source_id' => $source,
            'review_title' => $marker . ' PRIVATE PENDING',
            'review_time' => '2026-07-01 02:00:00',
            'review_approved' => 0,
        ]);
        $foreign = WpsrFactory::review([
            'platform_name' => 'custom',
            'source_id' => $marker . '-foreign-source',
            'review_title' => $marker . ' PRIVATE FOREIGN',
            'review_approved' => 1,
        ]);
        $template = WpsrFactory::post($postType, [
            'post_title' => 'Phase 18 public review template',
            'post_status' => $status,
            'post_password' => $password,
        ]);
        $meta = [
            'platform' => ['custom'],
            'selectedBusinesses' => [$source],
            'template' => 'grid1',
            'templateType' => 'grid',
            'pagination_type' => 'load_more',
            'paginate' => 1,
            'paginate_number' => ['desktop' => 1, 'mobile' => 1],
            'totalReviewsNumber' => ['desktop' => 20, 'mobile' => 20],
            'order' => 'oldest',
            'starFilterVal' => -1,
            'display_review_title' => 'true',
            'reviewer_name' => 'true',
            'display_review_text' => 'true',
            'contentType' => 'full',
            'content_length' => 0,
        ];
        update_post_meta((int) $template->ID, '_wpsr_template_config', wp_json_encode($meta));
        return compact('template', 'visible', 'pending', 'foreign', 'meta');
    };

    $wpsr_cleanup_rate_options = function (array $beforeNames) {
        global $wpdb;
        $afterNames = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpsr\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpsr\\_rl\\_%'");
        $created = array_values(array_diff((array) $afterNames, $beforeNames));
        $keys = [];
        foreach ($created as $name) {
            $key = preg_replace('/^_transient_(?:timeout_)?/', '', $name);
            $keys[$key] = true;
        }
        foreach (array_keys($keys) as $key) {
            delete_transient($key);
        }
        return [$created, array_keys($keys)];
    };

    try {
        WpsrTest::case('Phase 18 runtime inventory has every core public hook registered in core-only mode', function () use ($wpsr_manifest) {
            WpsrTest::assertSame(6, count($wpsr_manifest['ajax']), 'manifest core nopriv count');
            foreach ($wpsr_manifest['ajax'] as $row) {
                WpsrTest::assertTrue(has_action('wp_ajax_nopriv_' . $row['hook']) !== false, 'registered nopriv hook ' . $row['hook']);
            }
            WpsrTest::assertTrue(shortcode_exists('wp_social_ninja'), 'core shortcode registered');
            WpsrTest::assertTrue(has_action('template_redirect') !== false, 'core template_redirect callbacks registered');
            WpsrTest::assertTrue(!defined('WPSOCIALREVIEWS_PRO'), 'add-on remains inactive in core public tier');
        });

        WpsrTest::case('Phase 18 anonymous REST sample rejects exactly and changes full fingerprint nothing', function () use ($wpsr_assert_noop) {
            $result = $wpsr_assert_noop(function () {
                wp_set_current_user(0);
                PermissionManager::currentUserPermissions(false);
                return WpsrTest::rest('GET', '/platforms');
            }, 'anonymous policy-guarded REST read');
            WpsrTest::assertSame(401, $result['status'], 'anonymous REST status');
            WpsrTest::assertSame('rest_forbidden', $result['data']['code'] ?? '', 'anonymous REST exact code');
        });

        $wrongNonceCases = [
            ['wpsr_get_more_feeds', 'GET', ['template_id' => '../../1', 'page' => 999999, 'platform' => 'reviews', 'search' => '<script>x</script>'], 403, 'Invalid nonce.'],
            ['wpsr_youtube_popup_feed', 'POST', ['templateId' => '../../1', 'feedId' => '<script>x</script>'], 403, 'Security validation failed.'],
            ['wpsr_youtube_fluent_player', 'POST', ['templateId' => '../../1', 'videoId' => '../private.mp4', 'duration' => PHP_INT_MAX], 403, 'Security validation failed.'],
            ['wpsr_resize_images', 'POST', ['id' => '../../1', 'platform' => '../youtube', 'feed_type' => str_repeat('x', 4096)], 423, 'Security validation failed. Please try again'],
            ['wpsr_review_resize_images', 'POST', ['id' => '../../1', 'platforms' => ['../../private.php', 'image/svg+xml'], 'resize_data' => ['../escape.webp', str_repeat('x', 4096)]], 423, 'Security validation failed. Please try again'],
        ];
        foreach ($wrongNonceCases as $wrongCase) {
            WpsrTest::case('Phase 18 wrong nonce no-op: ' . $wrongCase[0], function () use ($wrongCase, $wpsr_ajax, $wpsr_assert_noop) {
                [$hook, $method, $params, $status, $message] = $wrongCase;
                $params['security'] = 'wrong-token';
                $params['nonce'] = 'wrong-token';
                $result = $wpsr_assert_noop(function () use ($hook, $params, $method, $wpsr_ajax) {
                    return $wpsr_ajax($hook, $params, $method);
                }, $hook . ' wrong nonce');
                WpsrTest::assertSame($status, $result['status'], $hook . ' exact rejection status');
                WpsrTest::assertSame(false, $result['json']['success'] ?? null, $hook . ' exact error envelope');
                WpsrTest::assertSame($message, $result['json']['data']['message'] ?? '', $hook . ' exact safe message');
            });
        }

        WpsrTest::case('Phase 18 load-more valid anonymous read returns exact approved owned scope and cleans rate state', function () use ($wpsr_review_fixture, $wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_file_fingerprint, $wpsr_cleanup_rate_options) {
            global $wpdb;
            try {
                $fixture = $wpsr_review_fixture();
                $beforeDb = WpsrTest::databaseFingerprint();
                $beforeFiles = $wpsr_file_fingerprint();
                $beforeRate = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpsr\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpsr\\_rl\\_%'");
                WpsrTest::resetOutbound();
                $result = $wpsr_ajax('wpsr_get_more_feeds', [
                    'security' => $wpsr_anonymous_nonce(),
                    'template_id' => (int) $fixture['template']->ID,
                    'page' => 1,
                    'platform' => 'reviews',
                    'order' => 'oldest',
                    'rating' => 1,
                    'search' => WpsrFactory::marker(),
                ], 'GET');
                WpsrTest::assertSame(200, $result['status'], 'valid load-more status');
                WpsrTest::assertTrue(is_array($result['json']), 'valid load-more JSON object');
                $content = (string) ($result['json']['content'] ?? '');
                WpsrTest::assertTrue(strpos($content, WpsrFactory::marker() . ' visible') !== false, 'valid load-more includes exact owned approved title');
                WpsrTest::assertTrue(strpos($content, 'PRIVATE PENDING') === false, 'valid load-more excludes pending review');
                WpsrTest::assertTrue(strpos($content, 'PRIVATE FOREIGN') === false, 'valid load-more excludes foreign source review');
                WpsrTest::assertTrue(stripos($content, '<script') === false && stripos($content, 'onerror') === false, 'valid load-more output contains no executable stored markup');
                WpsrTest::assertSame('&lt;script&gt;title&lt;/script&gt;', strpos($content, '&lt;script&gt;title&lt;/script&gt;') !== false ? '&lt;script&gt;title&lt;/script&gt;' : '', 'valid load-more title is escaped');
                WpsrTest::assertSame('', (string) ($result['json']['search'] ?? ''), 'core-alone drops unavailable visitor search');
                WpsrTest::assertSame(-1, (int) ($result['json']['exactRating'] ?? 0), 'core-alone drops unavailable exact rating');
                [$createdRate, $rateKeys] = $wpsr_cleanup_rate_options((array) $beforeRate);
                WpsrTest::assertTrue(count($createdRate) >= 2 && count($rateKeys) >= 1, 'valid review request charges only owned rate transients');
                WpsrTest::assertSame($beforeDb, WpsrTest::databaseFingerprint(), 'valid load-more state restores after exact rate cleanup');
                WpsrTest::assertSame($beforeFiles, $wpsr_file_fingerprint(), 'valid load-more changes no files');
                WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'valid load-more makes no remote HTTP');
                WpsrTest::assertSame([], WpsrTest::sentMails(), 'valid load-more sends no mail');
            } finally {
                $wpsr_cleanup_rate_options([]);
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 visitor request budget rejects at limit without partial state change', function () use ($wpsr_review_fixture, $wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_cleanup_rate_options) {
            global $wpdb;
            $limitFilter = function () { return 1; };
            add_filter('wpsocialreviews/visitor_search_rate_limit', $limitFilter, PHP_INT_MAX);
            add_filter('wpsocialreviews/visitor_search_hourly_limit', $limitFilter, PHP_INT_MAX);
            try {
                $fixture = $wpsr_review_fixture();
                $beforeRate = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpsr\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpsr\\_rl\\_%'");
                $params = [
                    'security' => $wpsr_anonymous_nonce(), 'template_id' => (int) $fixture['template']->ID,
                    'page' => 1, 'platform' => 'reviews',
                ];
                $first = $wpsr_ajax('wpsr_get_more_feeds', $params, 'GET');
                WpsrTest::assertSame(200, $first['status'], 'first budgeted public request succeeds');
                $beforeRejected = WpsrTest::databaseFingerprint();
                $second = $wpsr_ajax('wpsr_get_more_feeds', $params, 'GET');
                WpsrTest::assertSame(429, $second['status'], 'at-limit public request exact status');
                WpsrTest::assertSame('Too many requests. Please wait a moment and try again.', $second['json']['data']['message'] ?? '', 'at-limit exact safe response');
                WpsrTest::assertSame($beforeRejected, WpsrTest::databaseFingerprint(), 'rejected budget request makes no partial counter/database change');
                [$created, $keys] = $wpsr_cleanup_rate_options((array) $beforeRate);
                WpsrTest::assertTrue(count($created) >= 2 && count($keys) >= 1, 'budget case owns exact rate transient rows before cleanup');
            } finally {
                remove_filter('wpsocialreviews/visitor_search_rate_limit', $limitFilter, PHP_INT_MAX);
                remove_filter('wpsocialreviews/visitor_search_hourly_limit', $limitFilter, PHP_INT_MAX);
                $wpsr_cleanup_rate_options([]);
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 valid nonce rejects draft password and wrong-type load-more templates as full no-ops', function () use ($wpsr_review_fixture, $wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_assert_noop, $wpsr_cleanup_rate_options) {
            try {
                $fixtures = [
                    $wpsr_review_fixture('draft'),
                    $wpsr_review_fixture('publish', 'post'),
                    $wpsr_review_fixture('publish', 'wp_social_reviews', 'secret'),
                ];
                foreach ($fixtures as $fixture) {
                    $result = $wpsr_assert_noop(function () use ($fixture, $wpsr_anonymous_nonce, $wpsr_ajax) {
                        return $wpsr_ajax('wpsr_get_more_feeds', [
                            'security' => $wpsr_anonymous_nonce(), 'template_id' => (int) $fixture['template']->ID,
                            'page' => 1, 'platform' => 'reviews',
                        ], 'GET');
                    }, 'load-more hidden template');
                    WpsrTest::assertSame(403, $result['status'], 'hidden load-more exact status');
                    WpsrTest::assertSame('Invalid template.', $result['json']['data']['message'] ?? '', 'hidden load-more exact message');
                    WpsrTest::assertTrue(strpos($result['output'], WpsrFactory::marker()) === false, 'hidden load-more response leaks no fixture marker');
                }
            } finally {
                // Also cleans counters only reachable under a temporary visibility-bypass proof mutant.
                $wpsr_cleanup_rate_options([]);
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 public shortcode renders exact scoped escaped output and safe empty invalid IDs', function () use ($wpsr_review_fixture, $wpsr_assert_noop, $wpsr_admin_id) {
            try {
                $fixture = $wpsr_review_fixture();
                $handler = new ShortcodeHandler();
                wp_set_current_user(0);
                $result = $wpsr_assert_noop(function () use ($handler, $fixture) {
                    return $handler->makeShortcode(['id' => (int) $fixture['template']->ID, 'platform' => 'reviews']);
                }, 'public shortcode allowed read');
                WpsrTest::assertTrue(strpos($result, WpsrFactory::marker() . ' visible') !== false, 'shortcode includes exact approved owned title');
                WpsrTest::assertTrue(strpos($result, 'PRIVATE PENDING') === false && strpos($result, 'PRIVATE FOREIGN') === false, 'shortcode excludes hidden review rows');
                WpsrTest::assertTrue(stripos($result, '<script') === false && stripos($result, 'onerror') === false, 'shortcode output escaping removes executable markup');
                WpsrTest::assertSame('', $handler->makeShortcode(['id' => 0, 'platform' => 'reviews']), 'zero shortcode ID safe empty output');
                WpsrTest::assertSame('', $handler->makeShortcode(['id' => 99999999, 'platform' => 'reviews']), 'absent shortcode ID safe empty output');
                WpsrTest::assertSame('Provided platform name is not valid.', $handler->makeShortcode(['id' => (int) $fixture['template']->ID, 'platform' => 'private']), 'invalid shortcode platform exact safe output');
                $draft = $wpsr_review_fixture('draft');
                WpsrTest::assertSame('', $handler->makeShortcode(['id' => (int) $draft['template']->ID, 'platform' => 'reviews']), 'draft shortcode safe empty output');
            } finally {
                wp_set_current_user($wpsr_admin_id);
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 YouTube player valid token stays template-scoped and returns exact unavailable response', function () use ($wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_assert_noop) {
            try {
                $template = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 18 player']);
                update_post_meta((int) $template->ID, '_wpsr_template_config', wp_json_encode([
                    'feed_settings' => ['platform' => 'youtube', 'video_settings' => ['use_fluent_player' => 'true']],
                ]));
                $result = $wpsr_assert_noop(function () use ($template, $wpsr_anonymous_nonce, $wpsr_ajax) {
                    return $wpsr_ajax('wpsr_youtube_fluent_player', [
                        'security' => $wpsr_anonymous_nonce(), 'templateId' => (int) $template->ID,
                        'videoId' => 'ownedVideo_1', 'duration' => 30,
                    ], 'POST');
                }, 'YouTube player valid core-alone read');
                WpsrTest::assertSame(404, $result['status'], 'player valid token exact core-alone unavailable status');
                WpsrTest::assertSame('Fluent Player is not available for this feed.', $result['json']['data']['message'] ?? '', 'player exact unavailable response');

                $draft = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 18 private player', 'post_status' => 'draft']);
                $hidden = $wpsr_assert_noop(function () use ($draft, $wpsr_anonymous_nonce, $wpsr_ajax) {
                    return $wpsr_ajax('wpsr_youtube_fluent_player', [
                        'security' => $wpsr_anonymous_nonce(), 'templateId' => (int) $draft->ID, 'videoId' => 'privateVideo',
                    ], 'POST');
                }, 'YouTube player draft scope');
                WpsrTest::assertSame(403, $hidden['status'], 'player draft exact denial');
                WpsrTest::assertSame('Invalid template.', $hidden['json']['data']['message'] ?? '', 'player draft safe message');
            } finally {
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 YouTube popup valid token rejects hidden object with no leakage', function () use ($wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_assert_noop) {
            try {
                $draft = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 18 private popup', 'post_status' => 'private']);
                $result = $wpsr_assert_noop(function () use ($draft, $wpsr_anonymous_nonce, $wpsr_ajax) {
                    return $wpsr_ajax('wpsr_youtube_popup_feed', [
                        'security' => $wpsr_anonymous_nonce(), 'templateId' => (int) $draft->ID, 'feedId' => WpsrFactory::marker(),
                    ], 'POST');
                }, 'YouTube popup private scope');
                WpsrTest::assertSame(403, $result['status'], 'popup private exact status');
                WpsrTest::assertSame('Invalid template.', $result['json']['data']['message'] ?? '', 'popup private exact safe message');
                WpsrTest::assertTrue(strpos($result['output'], WpsrFactory::marker()) === false, 'popup denial leaks no requested feed marker');
            } finally {
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 feed image valid nonce is object/platform scoped with harmless empty fixture', function () use ($wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_file_fingerprint, $wpsr_assert_noop) {
            try {
                WpsrFactory::trackAbsentFixedOption('wpsr_youtube_verification_configs');
                add_option('wpsr_youtube_verification_configs', [], '', 'no');
                // The handler now refuses when this platform's optimization is off,
                // so the switch is on here to keep testing the empty-fixture path.
                WpsrFactory::trackAbsentFixedOption('wpsr_youtube_global_settings');
                add_option('wpsr_youtube_global_settings', ['global_settings' => ['optimized_images' => 'true']], '', 'no');
                $template = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 18 image owner']);
                update_post_meta((int) $template->ID, '_wpsr_template_config', wp_json_encode([
                    'feed_settings' => ['platform' => 'youtube', 'source_settings' => ['feed_type' => 'channel_feed', 'channel_id' => '']],
                ]));
                $beforeDb = WpsrTest::databaseFingerprint();
                $beforeFiles = $wpsr_file_fingerprint();
                WpsrTest::resetOutbound();
                $result = $wpsr_ajax('wpsr_resize_images', [
                    'nonce' => $wpsr_anonymous_nonce(), 'id' => (int) $template->ID,
                    'platform' => 'youtube', 'feed_type' => 'channel_feed',
                ], 'POST');
                WpsrTest::assertSame(200, $result['status'], 'empty image owner status');
                WpsrTest::assertSame(['images_data' => [], 'account_id' => null], $result['json'], 'empty image owner exact harmless response');
                WpsrTest::assertSame($beforeDb, WpsrTest::databaseFingerprint(), 'empty image owner changes no DB/options/posts/cron');
                WpsrTest::assertSame($beforeFiles, $wpsr_file_fingerprint(), 'empty image owner creates no generated files');
                WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'empty image owner performs no remote download');
                WpsrTest::assertSame([], WpsrTest::sentMails(), 'empty image owner sends no mail');

                $wrongType = WpsrFactory::post('post', ['post_title' => 'Phase 18 image foreign object']);
                $denied = $wpsr_assert_noop(function () use ($wrongType, $wpsr_anonymous_nonce, $wpsr_ajax) {
                    return $wpsr_ajax('wpsr_resize_images', [
                        'nonce' => $wpsr_anonymous_nonce(), 'id' => (int) $wrongType->ID,
                        'platform' => 'youtube', 'feed_type' => '../private.svg',
                    ], 'POST');
                }, 'feed image foreign object');
                WpsrTest::assertSame(403, $denied['status'], 'foreign image object exact denial');
                WpsrTest::assertSame('Invalid template.', $denied['json']['data']['message'] ?? '', 'foreign image object safe response');
            } finally {
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 image resize refuses a platform whose optimization is off', function () use ($wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_file_fingerprint, $wpsr_assert_noop) {
            // One resizer script serves every template on a page, so a template
            // whose platform has optimization off still gets asked to resize as
            // soon as any other template loads that script.
            try {
                WpsrFactory::trackAbsentFixedOption('wpsr_youtube_verification_configs');
                add_option('wpsr_youtube_verification_configs', [], '', 'no');
                $template = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 18 optimization off owner']);
                update_post_meta((int) $template->ID, '_wpsr_template_config', wp_json_encode([
                    'feed_settings' => ['platform' => 'youtube', 'source_settings' => ['feed_type' => 'channel_feed', 'channel_id' => '']],
                ]));

                $beforeFiles = $wpsr_file_fingerprint();
                $denied = $wpsr_assert_noop(function () use ($template, $wpsr_anonymous_nonce, $wpsr_ajax) {
                    return $wpsr_ajax('wpsr_resize_images', [
                        'nonce' => $wpsr_anonymous_nonce(), 'id' => (int) $template->ID,
                        'platform' => 'youtube', 'feed_type' => 'channel_feed',
                    ], 'POST');
                }, 'feed image optimization disabled');

                WpsrTest::assertSame(403, $denied['status'], 'disabled feed optimization exact denial');
                WpsrTest::assertSame(
                    'Image optimization is turned off.',
                    $denied['json']['data']['message'] ?? '',
                    'disabled feed optimization safe message'
                );
                WpsrTest::assertSame($beforeFiles, $wpsr_file_fingerprint(), 'disabled feed optimization creates no generated files');
                WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'disabled feed optimization performs no remote download');

                // Reviews share one site-wide switch, which is off by default here.
                $reviewTemplate = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 18 optimization off reviews']);
                $reviewDenied = $wpsr_assert_noop(function () use ($reviewTemplate, $wpsr_anonymous_nonce, $wpsr_ajax) {
                    return $wpsr_ajax('wpsr_review_resize_images', [
                        'nonce' => $wpsr_anonymous_nonce(), 'id' => (int) $reviewTemplate->ID,
                        'platforms' => ['google'], 'resize_data' => [],
                    ], 'POST');
                }, 'review image optimization disabled');

                WpsrTest::assertSame(403, $reviewDenied['status'], 'disabled review optimization exact denial');
                WpsrTest::assertSame(
                    'Image optimization is turned off.',
                    $reviewDenied['json']['data']['message'] ?? '',
                    'disabled review optimization safe message'
                );
                WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'disabled review optimization performs no remote download');
            } finally {
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 review image valid nonce rejects hidden object as full no-op', function () use ($wpsr_anonymous_nonce, $wpsr_ajax, $wpsr_assert_noop) {
            try {
                $draft = WpsrFactory::post('wp_social_reviews', ['post_title' => 'Phase 18 private review images', 'post_status' => 'draft']);
                $result = $wpsr_assert_noop(function () use ($draft, $wpsr_anonymous_nonce, $wpsr_ajax) {
                    return $wpsr_ajax('wpsr_review_resize_images', [
                        'nonce' => $wpsr_anonymous_nonce(), 'id' => (int) $draft->ID,
                        'platforms' => ['google'], 'resize_data' => ['../private.webp'],
                    ], 'POST');
                }, 'review image private scope');
                WpsrTest::assertSame(403, $result['status'], 'review image private exact denial');
                WpsrTest::assertSame('Invalid template.', $result['json']['data']['message'] ?? '', 'review image private safe message');
            } finally {
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 Facebook album core dispatch requires nonce and public template', function () use ($wpsr_isolated_probe) {
            try {
                $wpsr_published = WpsrFactory::post('wp_social_reviews', [
                    'post_title' => 'Phase 18 album template', 'post_status' => 'publish',
                ]);
                $wpsr_draft = WpsrFactory::post('wp_social_reviews', [
                    'post_title' => 'Phase 18 hidden album template', 'post_status' => 'draft',
                ]);

                // No nonce at all: the extension listener must never be reached.
                $wpsr_untrusted = $wpsr_isolated_probe('facebook-album-untrusted');
                WpsrTest::assertSame(0, $wpsr_untrusted['exit'], 'untrusted album probe terminates in the handler');
                WpsrTest::assertSame(0, substr_count($wpsr_untrusted['stdout'], 'WPSR-UNTRUSTED-DELEGATED'),
                    'unauthenticated album request never dispatches the delegated hook');
                WpsrTest::assertSame(false, strpos($wpsr_untrusted['stdout'], 'WPSR-PROBE-RETURNED') !== false,
                    'denied album request still terminates rather than returning');
                WpsrTest::assertSame(true, strpos($wpsr_untrusted['stdout'], 'Security validation failed.') !== false,
                    'denied album request reports the exact nonce failure message');

                // Valid nonce but a template no anonymous visitor may render.
                $wpsr_hidden = $wpsr_isolated_probe('facebook-album-hidden', (int) $wpsr_draft->ID);
                WpsrTest::assertSame(0, substr_count($wpsr_hidden['stdout'], 'WPSR-UNTRUSTED-DELEGATED'),
                    'draft album template never dispatches the delegated hook');
                WpsrTest::assertSame(true, strpos($wpsr_hidden['stdout'], 'Invalid template.') !== false,
                    'hidden album template reports the exact template failure message');

                // Positive control: a real visitor request still reaches the extension.
                $wpsr_allowed = $wpsr_isolated_probe('facebook-album-allowed', (int) $wpsr_published->ID);
                WpsrTest::assertSame(1, substr_count($wpsr_allowed['stdout'], 'WPSR-UNTRUSTED-DELEGATED'),
                    'published album template dispatches the delegated hook exactly once');
                WpsrTest::assertSame(false, strpos($wpsr_allowed['stdout'], 'WPSR-PROBE-RETURNED') !== false,
                    'allowed album request terminates in the handler');
            } finally {
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 template_redirect renders exact published chat only with escaped owned data', function () use ($wpsr_file_fingerprint, $wpsr_admin_id) {
            global $post;
            $chatQueryStatuses = [];
            $chatQueryProbe = function ($query) use (&$chatQueryStatuses) {
                if ($query->get('post_type') === 'wpsr_social_chats') {
                    $chatQueryStatuses[] = $query->get('post_status');
                }
            };
            try {
                $page = WpsrFactory::post('page', ['post_title' => 'Phase 18 public page']);
                $post = $page;
                $published = WpsrFactory::post('wpsr_social_chats', ['post_title' => 'Phase 18 public chat', 'post_status' => 'publish']);
                $draft = WpsrFactory::post('wpsr_social_chats', ['post_title' => 'Phase 18 private chat', 'post_status' => 'draft']);
                $settings = ChatConfig::formatConfig([
                    'chat_settings' => [
                        'channels' => [[
                            'name' => 'whatsapp', 'credential' => '15551234567', 'webUrl' => 'https://wa.me/15551234567',
                            'label' => WpsrFactory::marker() . ' <script>chat</script>',
                        ]],
                        'layout_type' => 'icons',
                        'settings' => ['page_list' => ['-1'], 'start_chat_time' => '09:00:00', 'end_chat_time' => '17:00:00'],
                    ],
                ]);
                update_post_meta((int) $published->ID, '_wpsr_template_config', $settings);
                update_post_meta((int) $draft->ID, '_wpsr_template_config', $settings);
                $beforeDb = WpsrTest::databaseFingerprint();
                $beforeFiles = $wpsr_file_fingerprint();
                WpsrTest::resetOutbound();
                WpsrTest::assertTrue(in_array((int) $published->ID, array_map('intval', wp_list_pluck(get_posts([
                    'post_type' => 'wpsr_social_chats', 'post_status' => 'publish', 'numberposts' => -1,
                ]), 'ID')), true), 'published chat exists in production query scope');
                WpsrTest::assertTrue(\WPSocialReviews\App\Services\Helper::isTemplateMatched($settings['chat_settings']['settings']), 'published chat settings match current public page');
                wp_set_current_user(0);
                // Invoke registered production callback directly: unrelated WordPress
                // template_redirect callbacks can replace global query context in WP-CLI.
                add_action('pre_get_posts', $chatQueryProbe, PHP_INT_MAX);
                $chatHandler = new \WPSocialReviews\App\Hooks\Handlers\ChatHandler();
                $chatHandler->maybeHasChats();
                remove_action('pre_get_posts', $chatQueryProbe, PHP_INT_MAX);
                WpsrTest::assertSame(['publish'], $chatQueryStatuses, 'template_redirect query is exactly publish-scoped');
                ob_start();
                $chatHandler->renderHTML($settings['chat_settings'], (int) $published->ID);
                $html = ob_get_clean();
                wp_set_current_user($wpsr_admin_id);
                WpsrTest::assertTrue(strpos($html, 'wpsr-chat-widget-' . (int) $published->ID) !== false, 'template_redirect renders exact published chat ID');
                WpsrTest::assertTrue(strpos($html, 'wpsr-chat-widget-' . (int) $draft->ID) === false, 'template_redirect excludes draft chat ID');
                WpsrTest::assertTrue(stripos($html, '<script>chat</script>') === false, 'chat output does not print executable marker label');
                WpsrTest::assertSame($beforeDb, WpsrTest::databaseFingerprint(), 'stable chat redirect changes no DB/options/posts/cron');
                WpsrTest::assertSame($beforeFiles, $wpsr_file_fingerprint(), 'chat redirect changes no files');
                WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'chat redirect performs no outbound HTTP');
                WpsrTest::assertSame([], WpsrTest::sentMails(), 'chat redirect sends no mail');
            } finally {
                remove_action('pre_get_posts', $chatQueryProbe, PHP_INT_MAX);
                wp_set_current_user($wpsr_admin_id);
                $post = null;
                WpsrFactory::cleanup();
            }
        });

        WpsrTest::case('Phase 18 notification template_redirect is exact core-alone no-output read', function () use ($wpsr_assert_noop, $wpsr_admin_id) {
            global $post;
            try {
                $page = WpsrFactory::post('page', ['post_title' => 'Phase 18 notification page']);
                $post = $page;
                $notification = WpsrFactory::post('wpsr_reviews_notify', ['post_title' => 'Phase 18 notification', 'post_status' => 'publish']);
                update_post_meta((int) $notification->ID, '_wpsr_template_config', wp_json_encode([
                    'platform' => ['custom'], 'notification_settings' => ['page_list' => ['-1']],
                ]));
                wp_set_current_user(0);
                $result = $wpsr_assert_noop(function () {
                    ob_start();
                    (new \WPSocialReviews\App\Hooks\Handlers\NotificationHandler())->maybeHasNotification();
                    return ob_get_clean();
                }, 'core-alone notification redirect');
                WpsrTest::assertSame('', $result, 'core-alone notification callback has no renderer and emits exact empty output');
            } finally {
                wp_set_current_user($wpsr_admin_id);
                $post = null;
                WpsrFactory::cleanup();
            }
        });
    } finally {
        wp_set_current_user($wpsr_admin_id);
        remove_filter('upload_dir', $wpsr_upload_filter, PHP_INT_MAX);
        $wpsr_remove_tree($wpsr_upload_root);
        WpsrFactory::cleanup();
    }
};
