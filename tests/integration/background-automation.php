<?php
/**
 * Phase 14: unattended scheduler, refresh, report, and local batch progression.
 */

use WPSocialReviews\App\Models\Cache;
use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\InstagramFeed;
use WPSocialReviews\App\Services\Platforms\Feeds\Instagram\RefreshToken;
use WPSocialReviews\App\Services\Platforms\Feeds\Youtube\OAuth;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;
use WPSocialReviews\App\Services\Platforms\Reviews\GoogleMyBusiness;

return function () {
    $wpsr_http_response = function ($body = ['ok' => true], $status = 200) {
        return [
            'headers'  => [],
            'body'     => wp_json_encode($body),
            'response' => ['code' => (int) $status, 'message' => $status === 200 ? 'OK' : 'Error'],
            'cookies'  => [],
            'filename' => null,
        ];
    };

    WpsrTest::case('hourly scheduler dispatches exact active feed/review branches and repeat is state-idempotent', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_filter = function () {
            return ['instagram', 'google', 'youtube'];
        };
        $wpsr_hits = [
            'instagram_feed'  => 0,
            'instagram_token' => 0,
            'google_reviews'  => 0,
            'youtube_feed'    => 0,
        ];
        $wpsr_observers = [
            'wpsr_instagram_feed_update' => function () use (&$wpsr_hits) {
                $wpsr_hits['instagram_feed']++;
            },
            'wpsr_instagram_access_token_refresh_weekly' => function () use (&$wpsr_hits) {
                $wpsr_hits['instagram_token']++;
            },
            'wpsr_google_reviews_update' => function () use (&$wpsr_hits) {
                $wpsr_hits['google_reviews']++;
            },
            'wpsr_youtube_feed_update' => function () use (&$wpsr_hits) {
                $wpsr_hits['youtube_feed']++;
            },
        ];

        WpsrFactory::ownedOption('wpsr_instagram_verification_configs', [
            '_wpsr_test_marker' => $wpsr_marker,
            'connected_accounts' => [],
        ]);
        WpsrFactory::ownedOption('wpsr_reviews_google_settings', [
            '_wpsr_test_marker' => $wpsr_marker,
        ]);
        WpsrFactory::trackAbsentFixedOption('wpsr_youtube_verification_configs');
        add_filter('wpsocialreviews/platforms', $wpsr_filter, PHP_INT_MAX);
        foreach ($wpsr_observers as $wpsr_hook => $wpsr_observer) {
            add_action($wpsr_hook, $wpsr_observer, PHP_INT_MAX);
        }

        WpsrTest::resetOutbound();
        try {
            do_action('wpsr_cron_job');
            WpsrTest::assertSame([
                'instagram_feed'  => 1,
                'instagram_token' => 1,
                'google_reviews'  => 1,
                'youtube_feed'    => 0,
            ], $wpsr_hits, 'first hourly callback selects feed/review hooks only for active platforms');
            WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'empty-cache hourly callbacks attempt no remote transport');
            WpsrTest::assertSame([], WpsrTest::sentMails(), 'hourly callbacks attempt no mail');

            do_action('wpsr_cron_job');
            WpsrTest::assertSame([
                'instagram_feed'  => 2,
                'instagram_token' => 2,
                'google_reviews'  => 2,
                'youtube_feed'    => 0,
            ], $wpsr_hits, 'repeat hourly callback preserves exact branch selection');
            WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'repeat empty-cache callback remains transport no-op');
        } finally {
            remove_filter('wpsocialreviews/platforms', $wpsr_filter, PHP_INT_MAX);
            foreach ($wpsr_observers as $wpsr_hook => $wpsr_observer) {
                remove_action($wpsr_hook, $wpsr_observer, PHP_INT_MAX);
            }
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('weekly scheduler captures maintenance intent once, dispatches owned account and completion hooks, then no-ops transport', function () use ($wpsr_http_response) {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_image = WpsrFactory::optimizeImage('instagram');
        $wpsr_user_id = (string) $wpsr_image->user_name;
        $wpsr_account = [
            'user_id'                  => $wpsr_user_id,
            'username'                 => $wpsr_marker . '-weekly-user',
            'status'                   => 'success',
            'has_app_permission_error' => false,
        ];
        WpsrFactory::ownedOption('wpsr_instagram_verification_configs', [
            '_wpsr_test_marker'  => $wpsr_marker,
            'connected_accounts' => [$wpsr_user_id => $wpsr_account],
        ]);
        WpsrFactory::trackAbsentFixedOption('wpsr_last_m_run');

        $wpsr_allow = '__return_true';
        $wpsr_validity_payloads = [];
        $wpsr_ai_hits = 0;
        $wpsr_validity_observer = function ($account) use (&$wpsr_validity_payloads) {
            $wpsr_validity_payloads[] = $account;
        };
        $wpsr_ai_observer = function () use (&$wpsr_ai_hits) {
            $wpsr_ai_hits++;
        };
        $wpsr_transport = function ($url, $args) use ($wpsr_http_response) {
            if ($url === 'https://wpsocialninja.com/?wp_plug_opt=1') {
                return $wpsr_http_response(['ok' => true]);
            }
            return false;
        };

        add_filter('wpsocialreviews/allow_opt_in', $wpsr_allow, PHP_INT_MAX);
        add_action('wpsocialreviews/check_instagram_access_token_validity_weekly', $wpsr_validity_observer, PHP_INT_MAX, 1);
        add_action('wpsocialreviews/regenerate_ai_summary_cache', $wpsr_ai_observer, PHP_INT_MAX);
        WpsrTest::setHttpResponder($wpsr_transport);
        WpsrTest::resetOutbound();
        try {
            do_action('wpsr_scheduled_weekly');
            $wpsr_requests = WpsrTest::remoteRequests();
            WpsrTest::assertSame(1, count($wpsr_requests), 'first weekly pass captures one approved maintenance request');
            WpsrTest::assertSame('https://wpsocialninja.com/?wp_plug_opt=1', $wpsr_requests[0]['url'] ?? null, 'maintenance request target is exact');
            WpsrTest::assertSame('POST', $wpsr_requests[0]['args']['method'] ?? null, 'maintenance request method is POST');
            WpsrTest::assertSame(30, $wpsr_requests[0]['args']['timeout'] ?? null, 'maintenance request timeout is exact');
            WpsrTest::assertSame(WPSOCIALREVIEWS_VERSION, $wpsr_requests[0]['args']['body']['plugin_version'] ?? null, 'maintenance payload includes core version');
            WpsrTest::assertSame(
                false,
                $wpsr_requests[0]['args']['body']['has_pro'] ?? null,
                'core-only maintenance payload records no add-on'
            );
            WpsrTest::assertTrue((int) get_option('wpsr_last_m_run') > 0, 'successful maintenance transport writes completion timestamp');
            WpsrTest::assertSame([$wpsr_account], $wpsr_validity_payloads, 'weekly validity branch dispatches exact matching optimized account payload');
            WpsrTest::assertSame(1, $wpsr_ai_hits, 'weekly pass dispatches AI-cache extension hook once');
            WpsrTest::assertSame([], WpsrTest::sentMails(), 'weekly success path sends no mail');

            do_action('wpsr_scheduled_weekly');
            WpsrTest::assertSame(1, count(WpsrTest::remoteRequests()), 'repeat weekly pass inside six-day window does not retry completed maintenance transport');
            WpsrTest::assertSame([$wpsr_account, $wpsr_account], $wpsr_validity_payloads, 'repeat weekly pass rechecks exact eligible account without state drift');
            WpsrTest::assertSame(2, $wpsr_ai_hits, 'repeat weekly pass preserves extension-hook dispatch');
        } finally {
            remove_filter('wpsocialreviews/allow_opt_in', $wpsr_allow, PHP_INT_MAX);
            remove_action('wpsocialreviews/check_instagram_access_token_validity_weekly', $wpsr_validity_observer, PHP_INT_MAX);
            remove_action('wpsocialreviews/regenerate_ai_summary_cache', $wpsr_ai_observer, PHP_INT_MAX);
            WpsrTest::clearHttpResponder();
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('scheduled Instagram token refresh advances token/cache once and repeat is a no-op', function () use ($wpsr_http_response) {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_user_id = $wpsr_marker . '-ig-user';
        $wpsr_old_token = (new DataProtector())->encrypt('IG-' . $wpsr_marker . '-old');
        WpsrFactory::ownedOption('wpsr_instagram_verification_configs', [
            '_wpsr_test_marker' => $wpsr_marker,
            'connected_accounts' => [
                $wpsr_user_id => [
                    'access_token' => $wpsr_old_token,
                    'expires_in'   => 5184000,
                    'created_at'   => time() - 950000,
                    'user_id'      => $wpsr_user_id,
                    'username'     => $wpsr_marker . '-ig-name',
                    'api_type'     => 'business_basic',
                    'user_avatar'  => '',
                    'name'         => 'Phase 14 account',
                ],
            ],
        ]);
        $wpsr_cache = WpsrFactory::cacheExact(
            'instagram',
            'wpsr_instagram_verification_configs_' . $wpsr_user_id,
            $wpsr_user_id,
            ['expiration' => '2025-01-01 00:00:00']
        );
        $wpsr_new_token = 'IG-' . $wpsr_marker . '-new';
        $wpsr_transport = function ($url, $args) use ($wpsr_http_response, $wpsr_new_token) {
            if (strpos($url, 'https://graph.instagram.com/refresh_access_token?') === 0) {
                return $wpsr_http_response([
                    'access_token' => $wpsr_new_token,
                    'expires_in'   => 5184000,
                ]);
            }
            return false;
        };
        WpsrTest::setHttpResponder($wpsr_transport);
        WpsrTest::resetOutbound();
        try {
            do_action('wpsr_instagram_access_token_refresh_weekly');
            $wpsr_requests = WpsrTest::remoteRequests();
            WpsrTest::assertSame(1, count($wpsr_requests), 'expired token cache dispatches exactly one approved refresh request');
            WpsrTest::assertSame(
                'https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=' . rawurlencode('IG-' . $wpsr_marker . '-old'),
                $wpsr_requests[0]['url'] ?? null,
                'refresh URL carries exact decrypted token intent'
            );

            $wpsr_saved = get_option('wpsr_instagram_verification_configs');
            $wpsr_account = $wpsr_saved['connected_accounts'][$wpsr_user_id] ?? [];
            WpsrTest::assertSame($wpsr_new_token, $wpsr_account['access_token'] ?? null, 'successful callback advances persisted access token');
            WpsrTest::assertSame(5184000, $wpsr_account['expires_in'] ?? null, 'successful callback persists returned expiry');
            WpsrTest::assertTrue((int) ($wpsr_account['created_at'] ?? 0) >= time() - 5, 'successful callback advances refresh creation time');
            WpsrTest::assertSame($wpsr_user_id, $wpsr_account['user_id'] ?? null, 'successful callback preserves account identity');

            $wpsr_cache_after = Cache::find((int) $wpsr_cache->id);
            WpsrTest::assertTrue($wpsr_cache_after && strtotime($wpsr_cache_after->expiration) > time(), 'successful callback advances token-cache completion expiry');
            do_action('wpsr_instagram_access_token_refresh_weekly');
            WpsrTest::assertSame(1, count(WpsrTest::remoteRequests()), 'repeat callback sees fresh cache and performs no second transport');
            WpsrTest::assertSame(1, Cache::where('platform', 'instagram')->where('name', 'wpsr_instagram_verification_configs_' . $wpsr_user_id)->count(), 'repeat callback keeps one cache row');
            WpsrTest::assertSame([], WpsrTest::sentMails(), 'token refresh sends no mail');
        } finally {
            WpsrTest::clearHttpResponder();
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('missing bridge identity leaves expired YouTube token unchanged without unsigned transport', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_tokens = [
            '_wpsr_test_marker' => $wpsr_marker,
            'access_token'      => 'yt-' . $wpsr_marker . '-old',
            'refresh_token'     => 'yr-' . $wpsr_marker . '-refresh',
            'expires_in'       => 3600,
            'created_at'       => time() - 7200,
            'credentials_type' => 'oauth2.0',
            'api_key'          => '',
            'access_code'      => '',
        ];
        $wpsr_encrypted = (new DataProtector())->maybe_encrypt_tokens(
            $wpsr_tokens,
            ['access_token', 'refresh_token', 'api_key', 'access_code']
        );
        WpsrFactory::ownedOption('wpsr_youtube_verification_configs', $wpsr_encrypted);
        WpsrFactory::trackAbsentFixedOption('wpsr_auth_bridge_identity');
        WpsrTest::resetOutbound();

        try {
            $wpsr_oauth = new OAuth();
            WpsrTest::assertSame(false, $wpsr_oauth->getAccessToken(), 'unprovisioned bridge returns reconnect/no-token result');
            WpsrTest::assertSame($wpsr_encrypted, get_option('wpsr_youtube_verification_configs'), 'missing-provider branch preserves complete encrypted token state');
            WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'missing bridge identity never attempts unsigned HTTP');

            WpsrTest::assertSame(false, $wpsr_oauth->getAccessToken(), 'repeat missing-provider branch remains a no-op');
            WpsrTest::assertSame($wpsr_encrypted, get_option('wpsr_youtube_verification_configs'), 'repeat missing-provider branch remains state-idempotent');
            WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'repeat missing-provider branch remains transport-free');
        } finally {
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('image optimization item state progresses failed to complete and repeats without duplicate row', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_media_id = $wpsr_marker . '-media-local-progression';
        $wpsr_user_name = $wpsr_marker . '-user-local-progression';
        $wpsr_handler = new ReviewImageOptimizationHandler(['google']);
        WpsrTest::resetOutbound();

        try {
            $wpsr_handler->updateImageInDb($wpsr_user_name, $wpsr_media_id, false, ['width' => 1, 'height' => 1], 'google');
            $wpsr_row = OptimizeImage::where('media_id', $wpsr_media_id)->where('user_name', $wpsr_user_name)->first();
            WpsrFactory::trackOptimizeImage($wpsr_row);
            WpsrTest::assertSame(1, OptimizeImage::where('media_id', $wpsr_media_id)->where('user_name', $wpsr_user_name)->count(), 'first attempted item creates one local state row');
            WpsrTest::assertSame(0, (int) $wpsr_row->images_resized, 'failed item remains incomplete');
            WpsrTest::assertSame([], OptimizeImage::where('media_id', $wpsr_media_id)->where('aspect_ratio', 1)->pluck('media_id')->toArray(), 'failed item is absent from completion result');

            $wpsr_handler->updateImageInDb($wpsr_user_name, $wpsr_media_id, true, ['width' => 200, 'height' => 200], 'google');
            $wpsr_completed = OptimizeImage::where('media_id', $wpsr_media_id)->where('user_name', $wpsr_user_name)->first();
            WpsrTest::assertSame((int) $wpsr_row->id, (int) $wpsr_completed->id, 'successful retry advances same item row');
            WpsrTest::assertSame(1, (int) $wpsr_completed->images_resized, 'successful retry marks item complete');
            WpsrTest::assertSame(1.0, (float) $wpsr_completed->aspect_ratio, 'successful square item stores exact completion ratio');
            WpsrTest::assertSame([$wpsr_media_id], OptimizeImage::where('media_id', $wpsr_media_id)->where('aspect_ratio', 1)->pluck('media_id')->toArray(), 'completed item enters exact batch completion result');

            $wpsr_handler->updateImageInDb($wpsr_user_name, $wpsr_media_id, true, ['width' => 300, 'height' => 300], 'google');
            WpsrTest::assertSame(1, OptimizeImage::where('media_id', $wpsr_media_id)->where('user_name', $wpsr_user_name)->count(), 'completed repeat is row-idempotent');
            WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'local image-state progression performs no remote transport');
            WpsrTest::assertSame([], WpsrTest::sentMails(), 'local image-state progression performs no mail');
        } finally {
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('daily report scheduler captures exact eligible mail and suppresses wrong-day and disabled branches', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_recipient_a = $wpsr_marker . '-a@example.test';
        $wpsr_recipient_b = $wpsr_marker . '-b@example.test';
        $wpsr_today = gmdate('D');
        $wpsr_other_day = gmdate('D', time() + DAY_IN_SECONDS);

        WpsrFactory::ownedOption('wpsr_instagram_verification_configs', [
            '_wpsr_test_marker' => $wpsr_marker,
            'connected_accounts' => [],
        ]);
        WpsrFactory::ownedOption('wpsr_errors', [
            '_wpsr_test_marker' => $wpsr_marker,
            'instagram' => [
                'connection' => [
                    $wpsr_marker . '-account' => ['critical' => true],
                ],
            ],
        ]);
        $wpsr_global = [
            '_wpsr_test_marker' => $wpsr_marker,
            'global_settings' => [
                'advance_settings' => [
                    'email_report' => [
                        'status'      => 'true',
                        'sending_day' => $wpsr_today,
                        'recipients'  => $wpsr_recipient_a . ', invalid-address, ' . $wpsr_recipient_b,
                    ],
                ],
            ],
        ];
        WpsrFactory::ownedOption('wpsr_global_settings', $wpsr_global);

        // Plugin boot created its long-lived Instagram service before this case's
        // marker option existed. Construct one production service after fixture setup,
        // then remove its two direct callbacks exactly in finally.
        $wpsr_instagram = new InstagramFeed();
        $wpsr_daily_hits = array_fill_keys(['instagram', 'twitter', 'youtube', 'facebook_feed', 'tiktok'], 0);
        $wpsr_daily_observers = [];
        foreach (array_keys($wpsr_daily_hits) as $wpsr_daily_platform) {
            $wpsr_daily_observers[$wpsr_daily_platform] = function () use (&$wpsr_daily_hits, $wpsr_daily_platform) {
                $wpsr_daily_hits[$wpsr_daily_platform]++;
            };
            add_action('wpsr_' . $wpsr_daily_platform . '_send_email_report', $wpsr_daily_observers[$wpsr_daily_platform], PHP_INT_MAX);
        }
        WpsrTest::resetOutbound();
        try {
            do_action('wpsr_do_email_report_scheduled_tasks');
            $wpsr_mails = WpsrTest::sentMails();
            WpsrTest::assertSame([
                'instagram'     => 1,
                'twitter'       => 0,
                'youtube'       => 0,
                'facebook_feed' => 0,
                'tiktok'        => 0,
            ], $wpsr_daily_hits, 'daily coordinator dispatches exact active feed hook only');
            WpsrTest::assertSame(1, count($wpsr_mails), 'eligible active critical platform emits one intercepted report');
            WpsrTest::assertSame([$wpsr_recipient_a, $wpsr_recipient_b], $wpsr_mails[0]['to'] ?? null, 'mail boundary receives exact valid recipient list only');
            WpsrTest::assertTrue(strpos((string) ($wpsr_mails[0]['subject'] ?? ''), 'Instagram Feed Report for ') === 0, 'mail boundary receives exact platform report subject branch');
            WpsrTest::assertTrue(strpos((string) ($wpsr_mails[0]['message'] ?? ''), 'unable to connect to the Instagram Feed API') !== false, 'mail body captures critical-feed report intent');
            WpsrTest::assertSame([
                'Content-Type: text/html; charset=utf-8',
                'From: ' . esc_html(wp_specialchars_decode(get_bloginfo('name'))) . ' <' . get_option('admin_email') . '>',
            ], $wpsr_mails[0]['headers'] ?? null, 'mail boundary receives exact HTML/from headers');
            WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'daily report rendering performs no HTTP');

            $wpsr_global['global_settings']['advance_settings']['email_report']['sending_day'] = $wpsr_other_day;
            update_option('wpsr_global_settings', $wpsr_global, 'no');
            WpsrTest::resetOutbound();
            do_action('wpsr_do_email_report_scheduled_tasks');
            WpsrTest::assertSame([], WpsrTest::sentMails(), 'wrong sending day is exact mail no-op');
            WpsrTest::assertSame(2, $wpsr_daily_hits['instagram'], 'wrong-day pass still dispatches active platform callback once');

            $wpsr_global['global_settings']['advance_settings']['email_report']['sending_day'] = $wpsr_today;
            $wpsr_global['global_settings']['advance_settings']['email_report']['status'] = 'false';
            update_option('wpsr_global_settings', $wpsr_global, 'no');
            do_action('wpsr_do_email_report_scheduled_tasks');
            WpsrTest::assertSame([], WpsrTest::sentMails(), 'disabled report setting remains mail no-op');
            WpsrTest::assertSame(3, $wpsr_daily_hits['instagram'], 'disabled pass still dispatches active platform callback once');
            WpsrTest::assertSame(0, $wpsr_daily_hits['facebook_feed'], 'inactive report platform remains undispatched across repeats');
        } finally {
            foreach ($wpsr_daily_observers as $wpsr_daily_platform => $wpsr_daily_observer) {
                remove_action('wpsr_' . $wpsr_daily_platform . '_send_email_report', $wpsr_daily_observer, PHP_INT_MAX);
            }
            remove_action('wpsr_instagram_access_token_refresh_weekly', [$wpsr_instagram, 'doTokenRefreshes']);
            remove_action('wpsr_instagram_send_email_report', [$wpsr_instagram, 'maybeSendFeedIssueEmail']);
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('KNOWN-FAILURE Instagram refresh option has no stray positional value', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_user_id = $wpsr_marker . '-ig-shape';
        WpsrFactory::ownedOption('wpsr_instagram_verification_configs', [
            '_wpsr_test_marker' => $wpsr_marker,
            'connected_accounts' => [
                $wpsr_user_id => [
                    'access_token' => (new DataProtector())->encrypt('IG-' . $wpsr_marker . '-shape-old'),
                    'expires_in'   => 5184000,
                    'created_at'   => time() - 950000,
                    'user_id'      => $wpsr_user_id,
                    'username'     => $wpsr_marker,
                    'api_type'     => 'business_basic',
                ],
            ],
        ]);
        WpsrFactory::cacheExact(
            'instagram',
            'wpsr_instagram_verification_configs_' . $wpsr_user_id,
            $wpsr_user_id,
            ['expiration' => '2025-01-01 00:00:00']
        );
        WpsrTest::setHttpResponder(function ($url, $args) use ($wpsr_marker) {
            if (strpos($url, 'https://graph.instagram.com/refresh_access_token?') !== 0) {
                return false;
            }
            return [
                'headers'  => [],
                'body'     => wp_json_encode(['access_token' => 'IG-' . $wpsr_marker . '-shape-new', 'expires_in' => 5184000]),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        });
        WpsrTest::resetOutbound();

        try {
            // The 'no' autoload argument was written inside the array literal instead of
            // as update_option()'s third parameter, so it persisted as element 0.
            do_action('wpsr_instagram_access_token_refresh_weekly');
            $wpsr_saved = get_option('wpsr_instagram_verification_configs');

            WpsrTest::assertSame(['connected_accounts'], array_keys($wpsr_saved),
                'refresh persists only the connected accounts key');
            WpsrTest::assertSame(1, count(WpsrTest::remoteRequests()),
                'refresh still performs its single token request');
            WpsrTest::assertSame(true, is_array($wpsr_saved['connected_accounts'] ?? null),
                'connected accounts survive as an array');
            // WordPress 6.6 renamed the stored values, so accept either spelling.
            global $wpdb;
            $wpsr_autoload = $wpdb->get_var($wpdb->prepare(
                "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
                'wpsr_instagram_verification_configs'
            ));
            WpsrTest::assertSame(true, in_array($wpsr_autoload, ['no', 'off'], true),
                'refreshed token option is stored without autoload');
        } finally {
            WpsrTest::clearHttpResponder();
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('KNOWN-FAILURE failed Google refresh keeps expired cache eligible for retry', function () {
        $wpsr_marker = WpsrFactory::marker();
        $wpsr_place = $wpsr_marker . '-google-place';
        $wpsr_tokens = (new DataProtector())->maybe_encrypt_tokens([
            '_wpsr_test_marker' => $wpsr_marker,
            'access_token'      => 'ga-' . $wpsr_marker,
            'refresh_token'     => 'gr-' . $wpsr_marker,
            'expires_in'       => 3600,
            'created_at'       => time() - 7200,
        ]);
        WpsrFactory::ownedOption('wpsr_reviews_google_verification_configs', $wpsr_tokens);
        WpsrFactory::ownedOption('wpsr_reviews_google_settings', [
            $wpsr_place => ['place_id' => $wpsr_place, 'name' => $wpsr_marker, 'url_value' => ''],
        ]);
        WpsrFactory::trackAbsentFixedOption('wpsr_auth_bridge_identity');
        $wpsr_cache = WpsrFactory::cacheExact(
            'google',
            'wpsr_reviews_google_business_info_' . $wpsr_place,
            $wpsr_place,
            ['expiration' => '2025-01-01 00:00:00']
        );

        WpsrTest::resetOutbound();
        try {
            $wpsr_before = Cache::find((int) $wpsr_cache->id)->expiration;
            // The cursor was advanced at the end of every iteration, even when no refresh
            // was attempted, so an unrefreshable business looked fresh to the next cron.
            do_action('wpsr_google_reviews_update');
            $wpsr_after = Cache::find((int) $wpsr_cache->id)->expiration;

            WpsrTest::assertSame(0, count(WpsrTest::remoteRequests()),
                'a business with no URL performs no refresh request');
            WpsrTest::assertSame($wpsr_before, $wpsr_after,
                'an unattempted refresh leaves the cache expiration untouched');
            WpsrTest::assertSame(true, strtotime($wpsr_after) < time(),
                'the cache stays expired so the next cron retries it');
        } finally {
            WpsrTest::resetOutbound();
            WpsrFactory::cleanup();
        }
    });
};
