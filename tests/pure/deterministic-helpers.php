<?php
/**
 * Phase 12: table-driven production helper tests. No models/query builders/fixtures.
 */

use WPSocialReviews\App\Hooks\Handlers\ShortcodeHandler;
use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\Helper as GlobalHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Facebook\Helper as FacebookHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Twitter\Helper as TwitterHelper;
use WPSocialReviews\App\Services\Platforms\Feeds\Youtube\Helper as YoutubeHelper;
use WPSocialReviews\App\Services\Platforms\Chats\Helper as ChatHelper;
use WPSocialReviews\App\Services\Platforms\PlatformManager;
use WPSocialReviews\App\Services\Platforms\RemoteAuth;
use WPSocialReviews\App\Services\Platforms\Reviews\Airbnb;
use WPSocialReviews\App\Services\Platforms\Reviews\GoogleMyBusiness;
use WPSocialReviews\App\Services\Platforms\Reviews\Helper as ReviewsHelper;
use WPSocialReviews\App\Services\UpgradeUrl;

return function () {
    $wpsr_table = function ($wpsr_label, array $wpsr_vectors, callable $wpsr_callback) {
        foreach ($wpsr_vectors as $wpsr_name => $wpsr_vector) {
            WpsrTest::assertSame(
                $wpsr_vector['expected'],
                $wpsr_callback($wpsr_vector['input'], $wpsr_vector),
                $wpsr_label . ' [' . $wpsr_name . ']'
            );
        }
    };

    WpsrTest::case('pure visitor search parsing and order normalization tables', function () use ($wpsr_table) {
        $wpsr_table('visitor search exact normalization', [
            'empty' => ['input' => '', 'expected' => ''],
            'non-scalar' => ['input' => ['two', 'words'], 'expected' => ''],
            'one Unicode character floor' => ['input' => '界', 'expected' => ''],
            'whitespace and Unicode preserved' => ['input' => "  Café\t東京  ", 'expected' => 'Café 東京'],
            'numeric scalar coercion' => ['input' => 12.5, 'expected' => '12.5'],
            '100-character ceiling' => ['input' => str_repeat('界', 101), 'expected' => str_repeat('界', 100)],
        ], function ($wpsr_input) {
            return ReviewsHelper::sanitizeVisitorSearch($wpsr_input);
        });

        $wpsr_table('visitor search word parser', [
            'empty' => ['input' => '', 'expected' => []],
            'all one-character terms retain bounded phrase' => ['input' => 'a b', 'expected' => ['a b']],
            'Unicode and six-word ceiling' => [
                'input' => 'a café b 東京 two three four five six seven',
                'expected' => ['café', '東京', 'two', 'three', 'four', 'five'],
            ],
        ], function ($wpsr_input) {
            return ReviewsHelper::visitorSearchWords($wpsr_input);
        });

        $wpsr_table('visitor order allowlist', [
            'legacy descending' => ['input' => 'DESC', 'expected' => 'newest'],
            'legacy ascending' => ['input' => 'asc', 'expected' => 'oldest'],
            'canonical rating' => ['input' => 'rating_asc', 'expected' => 'rating_asc'],
            'server-only random' => ['input' => 'random', 'expected' => 'random'],
            'malformed fallback' => ['input' => 'rating desc; SELECT', 'expected' => 'newest'],
            'integer coercion fallback' => ['input' => 5, 'expected' => 'newest'],
        ], function ($wpsr_input) {
            return ReviewsHelper::resolveOrderKey($wpsr_input);
        });
    });

    WpsrTest::case('pure visitor rate-limit identity and budget boundaries', function () use ($wpsr_table) {
        $wpsr_network = new ReflectionMethod(ReviewsHelper::class, 'visitorRateIpNetwork');
        $wpsr_network->setAccessible(true);

        $wpsr_table('visitor limiter network key', [
            'empty' => ['input' => '', 'expected' => ''],
            'IPv4 remains whole' => ['input' => '198.51.100.42', 'expected' => '198.51.100.42'],
            'invalid remains visible' => ['input' => 'not-an-ip', 'expected' => 'not-an-ip'],
            'IPv6 collapses to slash 64' => [
                'input' => '2001:db8:abcd:12:1234:5678:9abc:def0',
                'expected' => '2001:db8:abcd:12::/64',
            ],
            'IPv6 lower-half boundary' => [
                'input' => '2001:db8::1',
                'expected' => '2001:db8::/64',
            ],
        ], function ($wpsr_input) use ($wpsr_network) {
            return $wpsr_network->invoke(null, $wpsr_input);
        });

        $wpsr_original_server = $_SERVER;
        $wpsr_original_user = get_current_user_id();
        $wpsr_header_filter = function () {
            return 'HTTP_X_WPSR_CLIENT';
        };
        add_filter('wpsocialreviews/visitor_rate_client_ip_header', $wpsr_header_filter);
        try {
            wp_set_current_user(0);
            $_SERVER['HTTP_X_WPSR_CLIENT'] = '2001:db8:abcd:12:ffff::1, 192.0.2.10';
            $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
            $wpsr_key_method = new ReflectionMethod(ReviewsHelper::class, 'visitorFeedRateKey');
            $wpsr_key_method->setAccessible(true);
            WpsrTest::assertSame(
                '2001:db8:abcd:12::/64',
                $wpsr_key_method->invoke(null),
                'forwarded first valid IPv6 candidate produces exact network key'
            );

            $_SERVER['HTTP_X_WPSR_CLIENT'] = 'junk, 192.0.2.10';
            WpsrTest::assertSame(
                '198.51.100.9',
                $wpsr_key_method->invoke(null),
                'invalid trusted header falls back to remote address'
            );
        } finally {
            remove_filter('wpsocialreviews/visitor_rate_client_ip_header', $wpsr_header_filter);
            $_SERVER = $wpsr_original_server;
            wp_set_current_user($wpsr_original_user);
        }

        $wpsr_caller = 'phase12-fixed-caller';
        $wpsr_digest = md5($wpsr_caller);
        $wpsr_keys = [
            'wpsr_rl_sb_' . $wpsr_digest,
            'wpsr_rl_sh_' . $wpsr_digest,
            'wpsr_rl_fb_' . $wpsr_digest,
            'wpsr_rl_fh_' . $wpsr_digest,
        ];
        foreach ($wpsr_keys as $wpsr_key) {
            delete_transient($wpsr_key);
        }

        $wpsr_filters = [
            'wpsocialreviews/visitor_search_rate_key' => function () use ($wpsr_caller) {
                return $wpsr_caller;
            },
            'wpsocialreviews/visitor_search_rate_window' => function () {
                return 17;
            },
            'wpsocialreviews/visitor_search_rate_limit' => function () {
                return 2;
            },
            'wpsocialreviews/visitor_search_hourly_window' => function () {
                return 31;
            },
            'wpsocialreviews/visitor_search_hourly_limit' => function () {
                return 3;
            },
        ];
        foreach ($wpsr_filters as $wpsr_hook => $wpsr_callback) {
            add_filter($wpsr_hook, $wpsr_callback, 10, 2);
        }

        try {
            $wpsr_before = time();
            WpsrTest::assertSame(true, ReviewsHelper::allowVisitorFeedRequest(true), 'first search request passes both budgets');
            WpsrTest::assertSame(1, (int) get_transient($wpsr_keys[0]), 'short search counter exact first charge');
            WpsrTest::assertSame(1, (int) get_transient($wpsr_keys[1]), 'long search counter exact first charge');
            $wpsr_short_timeout = (int) get_option('_transient_timeout_' . $wpsr_keys[0]);
            $wpsr_long_timeout = (int) get_option('_transient_timeout_' . $wpsr_keys[1]);
            WpsrTest::assertTrue(
                $wpsr_short_timeout >= $wpsr_before + 17 && $wpsr_short_timeout <= time() + 17,
                'short budget expiry uses filtered 17-second window'
            );
            WpsrTest::assertTrue(
                $wpsr_long_timeout >= $wpsr_before + 31 && $wpsr_long_timeout <= time() + 31,
                'long budget expiry uses filtered 31-second window'
            );

            WpsrTest::assertSame(true, ReviewsHelper::allowVisitorFeedRequest(true), 'second search request reaches exact limit');
            WpsrTest::assertSame(false, ReviewsHelper::allowVisitorFeedRequest(true), 'third search request rejects at short limit');
            WpsrTest::assertSame(2, (int) get_transient($wpsr_keys[0]), 'rejected request does not increment short counter');
            WpsrTest::assertSame(2, (int) get_transient($wpsr_keys[1]), 'rejected request makes no partial long-window write');

            WpsrTest::assertSame(true, ReviewsHelper::allowVisitorFeedRequest(false), 'unsearched feed owns separate budget scope');
            WpsrTest::assertSame(1, (int) get_transient($wpsr_keys[2]), 'feed short counter uses exact separate key');
            WpsrTest::assertSame(1, (int) get_transient($wpsr_keys[3]), 'feed long counter uses exact separate key');
        } finally {
            foreach ($wpsr_filters as $wpsr_hook => $wpsr_callback) {
                remove_filter($wpsr_hook, $wpsr_callback, 10);
            }
            foreach ($wpsr_keys as $wpsr_key) {
                delete_transient($wpsr_key);
            }
        }

        foreach ($wpsr_keys as $wpsr_key) {
            WpsrTest::assertSame(false, get_transient($wpsr_key), 'rate-limit transient cleanup [' . $wpsr_key . ']');
            WpsrTest::assertSame(false, get_option('_transient_timeout_' . $wpsr_key, false), 'rate-limit timeout cleanup [' . $wpsr_key . ']');
        }
    });

    WpsrTest::case('pure scalar encoding formatting and Unicode normalization tables', function () use ($wpsr_table) {
        $wpsr_table('base64url encoding', [
            'empty' => ['input' => '', 'expected' => ''],
            'ASCII padding removed' => ['input' => 'hello?', 'expected' => 'aGVsbG8_'],
            'binary alphabet replaced' => ['input' => "\xfb\xff", 'expected' => '-_8'],
        ], function ($wpsr_input) {
            return GlobalHelper::base64UrlEncode($wpsr_input);
        });

        $wpsr_table('compact number formatting', [
            'zero keeps integer contract' => ['input' => 0, 'expected' => 0],
            'below threshold' => ['input' => 999, 'expected' => '999'],
            'thousand boundary' => ['input' => 1000, 'expected' => '1K'],
            'decimal rounding' => ['input' => 1550, 'expected' => '1.6K'],
            'million boundary' => ['input' => 1000000, 'expected' => '1M'],
            'numeric string coercion' => ['input' => '2500000000', 'expected' => '2.5B'],
        ], function ($wpsr_input) {
            return GlobalHelper::shortNumberFormat($wpsr_input);
        });

        $wpsr_table('ISO-8601 duration formatting', [
            'seconds' => ['input' => 'PT9S', 'expected' => '0:09'],
            'minutes' => ['input' => 'PT12M3S', 'expected' => '12:03'],
            'hours' => ['input' => 'PT1H2M3S', 'expected' => '1:02:03'],
        ], function ($wpsr_input) {
            return GlobalHelper::getVideoDuration($wpsr_input);
        });

        $wpsr_table('safe unserialize', [
            'plain Unicode unchanged' => ['input' => 'Málaga 東京', 'expected' => 'Málaga 東京'],
            'serialized array' => ['input' => 'a:2:{s:1:"a";i:1;s:1:"b";s:3:"界";}', 'expected' => ['a' => 1, 'b' => '界']],
            'malformed serialization unchanged' => ['input' => 'a:1:{broken', 'expected' => 'a:1:{broken'],
        ], function ($wpsr_input) {
            return GlobalHelper::safeUnserialize($wpsr_input);
        });

        $wpsr_table('Unicode matching normalization', [
            'empty' => ['input' => '', 'expected' => ''],
            'trim accents and case' => ['input' => '  MÁLAGA  ', 'expected' => 'malaga'],
            'stylized hashtags and mentions' => ['input' => '#𝕾𝖍𝖚𝖓𝖆𝖗 @Málaga', 'expected' => 'hashtagshunar mentionmalaga'],
            'integer coercion' => ['input' => 123, 'expected' => '123'],
        ], function ($wpsr_input) {
            return GlobalHelper::normalizeText($wpsr_input);
        });

        $wpsr_table('Facebook media duration formatting', [
            'zero floor' => ['input' => 0, 'expected' => '0:01'],
            'seconds' => ['input' => 59, 'expected' => '0:59'],
            'minute' => ['input' => 60, 'expected' => '1:00'],
            'hour' => ['input' => 3600, 'expected' => '1:00:00'],
        ], function ($wpsr_input) {
            return FacebookHelper::secondsToMinutes($wpsr_input);
        });
    });

    WpsrTest::case('pure auth challenge and data-protection structural contracts', function () {
        $wpsr_verifier = 'phase12 verifier / 東京';
        $wpsr_challenge = GlobalHelper::base64UrlEncode(hash('sha256', $wpsr_verifier, true));
        $wpsr_result = ['token' => 'abc', 'consume_challenge' => $wpsr_challenge, 'nested' => ['keep' => true]];
        $wpsr_before = $wpsr_result;
        WpsrTest::assertSame(
            ['token' => 'abc', 'nested' => ['keep' => true]],
            GlobalHelper::verifyAuthConsumeChallenge($wpsr_result, $wpsr_verifier),
            'valid consume challenge is removed and all payload fields remain exact'
        );
        WpsrTest::assertSame($wpsr_before, $wpsr_result, 'challenge verifier does not mutate caller input');

        $wpsr_thrown = null;
        try {
            GlobalHelper::verifyAuthConsumeChallenge(['consume_challenge' => 'wrong'], $wpsr_verifier);
        } catch (Throwable $wpsr_exception) {
            $wpsr_thrown = [get_class($wpsr_exception), $wpsr_exception->getMessage()];
        }
        WpsrTest::assertSame(
            [RuntimeException::class, 'Invalid auth verifier.'],
            $wpsr_thrown,
            'malformed consume challenge throws exact production exception'
        );

        $wpsr_protector = new DataProtector(str_repeat('k', 32), 'phase12-salt');
        $wpsr_ciphertext = $wpsr_protector->encrypt('secret 東京');
        WpsrTest::assertTrue(is_string($wpsr_ciphertext) && $wpsr_ciphertext !== '', 'encrypted payload has nonempty structural output');
        WpsrTest::assertSame('secret 東京', $wpsr_protector->decrypt($wpsr_ciphertext), 'random-IV ciphertext round-trips exact plaintext');
        WpsrTest::assertSame(false, (new DataProtector(str_repeat('x', 32), 'wrong-salt'))->decrypt($wpsr_ciphertext), 'wrong key and salt reject ciphertext');
        WpsrTest::assertSame('', $wpsr_protector->encrypt(''), 'empty encryption input remains exact empty string');

        $wpsr_json = new ReflectionMethod(RemoteAuth::class, 'canonicalJson');
        $wpsr_json->setAccessible(true);
        WpsrTest::assertSame(
            '{"path":"https://example.test/a/b","label":"M\u00e1laga"}',
            $wpsr_json->invoke(null, ['path' => 'https://example.test/a/b', 'label' => 'Málaga']),
            'bridge signing JSON keeps slashes and canonical compact encoding'
        );
    });

    WpsrTest::case('pure global scalar sanitizer boundary tables', function () use ($wpsr_table) {
        $wpsr_table('boolean sanitizer string mode', [
            'true boolean' => ['input' => true, 'expected' => 'true'],
            'false boolean' => ['input' => false, 'expected' => 'false'],
            'false string' => ['input' => 'false', 'expected' => 'false'],
            'zero string' => ['input' => '0', 'expected' => 'false'],
            'one integer' => ['input' => 1, 'expected' => 'true'],
            'arbitrary nonempty string follows REST contract' => ['input' => 'yes', 'expected' => 'true'],
        ], function ($wpsr_input) {
            return wpsr_sanitize_boolean($wpsr_input);
        });

        $wpsr_table('opaque auth token sanitizer', [
            'empty' => ['input' => '', 'expected' => ''],
            'standard and URL-safe base64' => ['input' => 'Ab9+/=_-', 'expected' => 'Ab9+/=_-'],
            'UUID' => ['input' => '123e4567-e89b-12d3-a456-426614174000', 'expected' => '123e4567-e89b-12d3-a456-426614174000'],
            'whitespace rejected' => ['input' => 'abc def', 'expected' => ''],
            'markup rejected' => ['input' => '<abc>', 'expected' => ''],
            'Unicode rejected' => ['input' => 'tökén', 'expected' => ''],
            'integer coercion' => ['input' => 12345, 'expected' => '12345'],
        ], function ($wpsr_input) {
            return wpsr_sanitize_opaque_token($wpsr_input);
        });

        $wpsr_table('recipient sanitizer', [
            'null string mode' => ['input' => null, 'expected' => ''],
            'mixed comma list' => [
                'input' => ' one@example.com, invalid@@, üser@example.com ,two+tag@example.org ',
                'expected' => 'one@example.com,ser@example.com,two+tag@example.org',
            ],
            'array mode preserves order and duplicates' => [
                'input' => ['a@example.test', '', 'a@example.test', 'bad@@'],
                'expected' => ['a@example.test', 'a@example.test'],
                'as_string' => false,
            ],
        ], function ($wpsr_input, $wpsr_vector) {
            return wpsr_sanitize_recipients($wpsr_input, $wpsr_vector['as_string'] ?? true);
        });

        $wpsr_table('CSS unit sanitizer', [
            'unitless zero' => ['input' => '0', 'expected' => '0'],
            'negative decimal' => ['input' => '-.5rem', 'expected' => '-.5rem'],
            'percentage' => ['input' => '100%', 'expected' => '100%'],
            'time unit' => ['input' => '250ms', 'expected' => '250ms'],
            'whitespace rejected' => ['input' => '12 px', 'expected' => ''],
            'expression rejected' => ['input' => 'calc(100% - 1px)', 'expected' => ''],
        ], function ($wpsr_input) {
            return wpsr_sanitize_css_unit($wpsr_input);
        });

        $wpsr_table('CSS selector sanitizer', [
            'allowed combinators' => ['input' => '.wpsr-card[data-x="ok"] > #item:hover', 'expected' => '.wpsr-card[data-x="ok"] > #item:hover'],
            'declaration punctuation stripped' => ['input' => '.wpsr-card{color:red;}', 'expected' => '.wpsr-cardcolor:red'],
            'Unicode and script punctuation stripped' => ['input' => '.界<script>()', 'expected' => '.script>'],
        ], function ($wpsr_input) {
            return wpsr_sanitize_css_selector($wpsr_input);
        });
    });

    WpsrTest::case('pure Facebook identifier and relative-date parser tables', function () use ($wpsr_table) {
        $wpsr_table('Facebook album identifier parser', [
            'numeric string' => ['input' => '145555168501702', 'expected' => '145555168501702'],
            'canonical URL' => [
                'input' => 'https://www.facebook.com/media/set/?set=a.145555168501702&type=3',
                'expected' => '145555168501702',
            ],
            'bare Facebook host' => [
                'input' => 'https://facebook.com/media/set/?set=a.42&type=3',
                'expected' => '42',
            ],
            'foreign host' => ['input' => 'https://example.test/media/set/?set=a.42', 'expected' => null],
        ], function ($wpsr_input) {
            return FacebookHelper::getAlbumId($wpsr_input);
        });

        $wpsr_table('Facebook video-playlist identifier parser', [
            'numeric integer' => ['input' => 42, 'expected' => 42],
            'canonical URL' => [
                'input' => 'https://www.facebook.com/watch/100091416590270/1336242490940769',
                'expected' => '1336242490940769',
            ],
            'trailing slash' => [
                'input' => 'https://facebook.com/watch/100/200/',
                'expected' => '200',
            ],
            'wrong path' => ['input' => 'https://www.facebook.com/videos/200', 'expected' => false],
            'foreign host' => ['input' => 'https://example.test/watch/100/200', 'expected' => false],
            'empty' => ['input' => '', 'expected' => false],
        ], function ($wpsr_input) {
            return FacebookHelper::validateAndRetrieveSingleVideoPlaylistId($wpsr_input);
        });

        $wpsr_table('Facebook relative-date repair', [
            'now keyword' => ['input' => 'NOW', 'expected' => 'now'],
            'negative bare days' => ['input' => '-50', 'expected' => '-50 days'],
            'past day words' => ['input' => '5 days', 'expected' => '-5 days'],
            'past week words' => ['input' => '2 weeks', 'expected' => '-2 weeks'],
            'future end words' => ['input' => '5 days', 'expected' => '5 days', 'context' => 'end'],
            'already negative' => ['input' => '-3 hours', 'expected' => '-3 hours'],
            'unrecognized unchanged' => ['input' => 'yesterday noon', 'expected' => 'yesterday noon'],
        ], function ($wpsr_input, $wpsr_vector) {
            return FacebookHelper::fixRelativeDateInput($wpsr_input, $wpsr_vector['context'] ?? 'start');
        });
    });

    WpsrTest::case('pure Airbnb review-shape and numeric parser tables', function () use ($wpsr_table) {
        $wpsr_reflection = new ReflectionClass(Airbnb::class);
        $wpsr_airbnb = $wpsr_reflection->newInstanceWithoutConstructor();
        $wpsr_place = $wpsr_reflection->getProperty('placeId');
        $wpsr_place->setAccessible(true);
        $wpsr_place->setValue($wpsr_airbnb, 'listing-42');

        $wpsr_table('Airbnb review ID shape mapping', [
            'experience GraphQL' => [
                'input' => ['node' => ['review' => ['id' => 'experience-id']]],
                'expected' => 'experience-id',
            ],
            'regular GraphQL' => [
                'input' => ['createdAt' => '2024-01-02', 'id' => 'regular-id'],
                'expected' => 'regular-id',
            ],
            'legacy API' => [
                'input' => [
                    'created_at' => '2024-01-02', 'id' => 'legacy-id',
                    'reviewer' => ['first_name' => 'Ada', 'profile_path' => '/u/1', 'picture_url' => 'pic'],
                ],
                'expected' => 'legacy-id',
            ],
            'deterministic missing-ID fallback' => [
                'input' => [
                    'createdAt' => '2024-01-02', 'comments' => 'Great stay',
                    'reviewer' => ['firstName' => 'Ada'],
                ],
                'expected' => 'airbnb_' . md5('listing-42|Ada|2024-01-02|Great stay'),
            ],
        ], function ($wpsr_input) use ($wpsr_airbnb) {
            return $wpsr_airbnb->getReviewId($wpsr_input);
        });

        $wpsr_table('Airbnb numeric extraction', [
            'empty' => ['input' => '', 'expected' => null],
            'short decimal' => ['input' => '4.8', 'expected' => 4.8],
            'decimal before delimiter' => ['input' => '4.75, rating', 'expected' => 4.75],
            'integer before delimiter' => ['input' => '123, reviews', 'expected' => 123.0],
            'malformed text' => ['input' => 'reviews only', 'expected' => null],
        ], function ($wpsr_input) use ($wpsr_airbnb) {
            return $wpsr_airbnb->validateAndCleanNumericInput($wpsr_input);
        });
    });

    WpsrTest::case('pure chat URL shortcode and timezone normalization tables', function () use ($wpsr_table) {
        $wpsr_table('Fluent Forms shortcode normalization', [
            'numeric ID' => ['input' => '42', 'expected' => '[fluentform id="42"]'],
            'quoted shortcode' => ['input' => " [fluentform id='7'] ", 'expected' => '[fluentform id="7"]'],
            'unquoted shortcode' => ['input' => '[fluentform id=9]', 'expected' => '[fluentform id="9"]'],
            'modal shortcode' => ['input' => '[fluentform_modal form_id="11"]', 'expected' => '[fluentform_modal form_id="11"]'],
            'zero rejected' => ['input' => '0', 'expected' => ''],
            'extra attributes rejected' => ['input' => '[fluentform id="7" class="x"]', 'expected' => ''],
            'other shortcode rejected' => ['input' => '[contact-form-7 id="7"]', 'expected' => ''],
            'Unicode digits rejected' => ['input' => '４２', 'expected' => ''],
        ], function ($wpsr_input) {
            return ChatHelper::getFluentFormsShortcode($wpsr_input);
        });

        $wpsr_table('channel URI scheme extraction', [
            'HTTPS normalized' => ['input' => 'HTTPS://example.test/path', 'expected' => 'https'],
            'custom Teams scheme' => ['input' => 'msteams:chat?users=x', 'expected' => 'msteams'],
            'relative value' => ['input' => 'example.test/path', 'expected' => ''],
            'leading whitespace has no scheme' => ['input' => ' tel:+1555', 'expected' => ''],
            'invalid numeric prefix' => ['input' => '1tel:+1555', 'expected' => ''],
        ], function ($wpsr_input) {
            return ChatHelper::getUriScheme($wpsr_input);
        });

        $wpsr_table('channel scheme allowlist', [
            'phone tel' => ['input' => ['tel:+1555010', 'phone'], 'expected' => true],
            'phone HTTPS rejected' => ['input' => ['https://example.test', 'phone'], 'expected' => false],
            'Teams custom scheme' => ['input' => ['msteams:chat?users=x', 'microsoft-teams'], 'expected' => true],
            'default HTTPS' => ['input' => ['https://example.test/path', 'whatsapp'], 'expected' => true],
            'default javascript rejected' => ['input' => ['javascript:alert(1)', 'whatsapp'], 'expected' => false],
            'Fluent Forms has no URL schemes' => ['input' => ['https://example.test', 'fluent_forms'], 'expected' => false],
            'empty' => ['input' => ['', 'email'], 'expected' => false],
        ], function ($wpsr_input) {
            return ChatHelper::isSafeChannelUrl($wpsr_input[0], $wpsr_input[1]);
        });

        $wpsr_timezone_filter = function () {
            return '+05:30';
        };
        add_filter('pre_option_timezone_string', $wpsr_timezone_filter);
        try {
            WpsrTest::assertSame('09:37:26', ChatHelper::formatedLocalTimeToUTCTime('03:07:26 PM'), 'fixed-offset local time converts to exact UTC');
            WpsrTest::assertSame('18:30:00', ChatHelper::formatedLocalTimeToUTCTime('12:00:00 AM'), 'timezone conversion crosses previous UTC day');
            WpsrTest::assertSame(null, ChatHelper::formatedLocalTimeToUTCTime('25:99'), 'malformed chat time returns null');
        } finally {
            remove_filter('pre_option_timezone_string', $wpsr_timezone_filter);
        }
    });

    WpsrTest::case('pure platform mapping and feed media normalization tables', function () use ($wpsr_table) {
        $wpsr_table('YouTube video ID shape mapping', [
            'playlist item wins' => [
                'input' => ['snippet' => ['resourceId' => ['videoId' => 'playlist-id']], 'id' => ['videoId' => 'search-id']],
                'expected' => 'playlist-id',
            ],
            'search result fallback' => ['input' => ['id' => ['videoId' => 'search-id']], 'expected' => 'search-id'],
            'flat video fallback' => ['input' => ['id' => 'flat-id'], 'expected' => 'flat-id'],
            'empty' => ['input' => [], 'expected' => ''],
        ], function ($wpsr_input) {
            return YoutubeHelper::getVideoId($wpsr_input);
        });

        $wpsr_table('Twitter nested media type precedence', [
            'direct' => ['input' => ['extended_entities' => ['media' => [['type' => 'photo']]]], 'expected' => 'photo'],
            'retweeted' => ['input' => ['retweeted_status' => ['extended_entities' => ['media' => [['type' => 'video']]]]], 'expected' => 'video'],
            'quoted' => ['input' => ['quoted_status' => ['extended_entities' => ['media' => [['type' => 'animated_gif']]]]], 'expected' => 'animated_gif'],
            'empty' => ['input' => [], 'expected' => ''],
        ], function ($wpsr_input) {
            return TwitterHelper::getMediaType($wpsr_input);
        });

        $wpsr_variants = ['variants' => [
            ['content_type' => 'application/x-mpegURL', 'url' => 'stream'],
            ['content_type' => 'video/mp4', 'bit_rate' => 256000, 'url' => 'low.mp4'],
            ['content_type' => 'video/mp4', 'bit_rate' => 832000, 'url' => 'high.mp4'],
            ['content_type' => 'video/mp4', 'bit_rate' => 512000, 'url' => 'mid.mp4'],
        ]];
        $wpsr_variants_before = $wpsr_variants;
        WpsrTest::assertSame('high.mp4', TwitterHelper::getHighQualityVideo($wpsr_variants), 'highest MP4 bitrate selected exactly');
        WpsrTest::assertSame($wpsr_variants_before, $wpsr_variants, 'Twitter variant selection does not mutate input');
        WpsrTest::assertSame(null, TwitterHelper::getHighQualityVideo(['variants' => [['content_type' => 'video/webm']]]), 'missing MP4 returns null');

        $wpsr_table('external media platform mapping', [
            'YouTube short' => ['input' => ['expanded_url' => 'https://youtu.be/abcdefghijk'], 'expected' => 'youtube'],
            'YouTube watch' => ['input' => ['expanded_url' => 'https://youtube.com/watch?v=abcdefghijk'], 'expected' => 'youtube'],
            'Vimeo' => ['input' => ['expanded_url' => 'https://vimeo.com/123'], 'expected' => 'vimeo'],
            'Spotify' => ['input' => ['expanded_url' => 'https://open.spotify.com/track/123'], 'expected' => 'spotify'],
            'unknown' => ['input' => ['expanded_url' => 'https://example.test/video'], 'expected' => ''],
        ], function ($wpsr_input) {
            return TwitterHelper::externalPlatformName($wpsr_input);
        });

        $wpsr_manager = new PlatformManager();
        $wpsr_table('platform media owner mapping', [
            'Instagram' => ['input' => [['username' => 'ig-user'], 'instagram'], 'expected' => 'ig-user'],
            'Facebook' => ['input' => [['page_id' => 'fb-page'], 'facebook_feed'], 'expected' => 'fb-page'],
            'TikTok' => ['input' => [['user' => ['name' => 'tt-user']], 'tiktok'], 'expected' => 'tt-user'],
            'YouTube' => ['input' => [['snippet' => ['channelId' => 'yt-channel']], 'youtube'], 'expected' => 'yt-channel'],
            'review fallback' => ['input' => [['source_id' => 'review-source'], 'reviews'], 'expected' => 'review-source'],
            'missing' => ['input' => [[], 'instagram'], 'expected' => ''],
        ], function ($wpsr_input) use ($wpsr_manager) {
            return $wpsr_manager->getUserName($wpsr_input[0], $wpsr_input[1]);
        });

        $wpsr_configs = [
            'first' => ['page_id' => '10', 'name' => 'A'],
            'second' => ['page_id' => '20', 'name' => 'B'],
        ];
        $wpsr_configs_before = $wpsr_configs;
        WpsrTest::assertSame(
            ['second' => ['page_id' => '20', 'name' => 'B']],
            $wpsr_manager->getFeedVerificationConfigsBySourceId('facebook_feed', $wpsr_configs, ['20']),
            'selected feed account map preserves exact source key and value'
        );
        WpsrTest::assertSame($wpsr_configs_before, $wpsr_configs, 'platform config filtering does not mutate input');

        $wpsr_google = new GoogleMyBusiness();
        $wpsr_table('Google enum rating mapping', [
            'five' => ['input' => 'FIVE', 'expected' => 5],
            'one' => ['input' => 'ONE', 'expected' => 1],
            'lowercase rejected' => ['input' => 'five', 'expected' => 0],
            'unknown rejected' => ['input' => 'SIX', 'expected' => 0],
            'null rejected' => ['input' => null, 'expected' => 0],
        ], function ($wpsr_input) use ($wpsr_google) {
            return $wpsr_google->convertRating($wpsr_input);
        });
    });

    WpsrTest::case('pure feed pagination math tables and input non-mutation', function () {
        $wpsr_handler = new ShortcodeHandler();
        $wpsr_feed = [
            'feed_settings' => [
                'layout_type' => 'grid',
                'filters' => ['total_posts_number' => ['desktop' => 8, 'mobile' => 4]],
                'pagination_settings' => [
                    'pagination_type' => 'load_more',
                    'paginate' => 6,
                    'paginate_number' => ['desktop' => 3, 'mobile' => 2],
                ],
            ],
            'dynamic' => ['items' => array_map(function ($wpsr_id) {
                return ['id' => $wpsr_id];
            }, range(1, 10))],
        ];
        $wpsr_feed_before = $wpsr_feed;
        $wpsr_original_agent_exists = array_key_exists('HTTP_USER_AGENT', $_SERVER);
        $wpsr_original_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            unset($_SERVER['HTTP_USER_AGENT']);
            WpsrTest::assertSame([
                'sinceId' => 0, 'maxId' => 2, 'paginate' => 3, 'total' => 8, 'pagination_type' => 'load_more',
            ], $wpsr_handler->formatPaginationSettings($wpsr_feed), 'desktop pagination uses exact responsive count and inclusive max index');

            $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile';
            WpsrTest::assertSame([
                'sinceId' => 0, 'maxId' => 1, 'paginate' => 2, 'total' => 4, 'pagination_type' => 'load_more',
            ], $wpsr_handler->formatPaginationSettings($wpsr_feed), 'mobile pagination uses exact responsive count and total');

            unset($_SERVER['HTTP_USER_AGENT']);
            $wpsr_none = $wpsr_feed;
            $wpsr_none['feed_settings']['pagination_settings']['pagination_type'] = 'none';
            WpsrTest::assertSame(10, $wpsr_handler->formatPaginationSettings($wpsr_none)['maxId'], 'none pagination exposes complete feed boundary');

            $wpsr_carousel = $wpsr_feed;
            $wpsr_carousel['feed_settings']['layout_type'] = 'carousel';
            WpsrTest::assertSame(10, $wpsr_handler->formatPaginationSettings($wpsr_carousel)['maxId'], 'carousel exposes complete feed boundary');

            $wpsr_short = $wpsr_feed;
            $wpsr_short['dynamic']['items'] = [['id' => 1], ['id' => 2]];
            WpsrTest::assertSame(2, $wpsr_handler->formatPaginationSettings($wpsr_short)['total'], 'configured total clamps to actual feed count');
        } finally {
            if ($wpsr_original_agent_exists) {
                $_SERVER['HTTP_USER_AGENT'] = $wpsr_original_agent;
            } else {
                unset($_SERVER['HTTP_USER_AGENT']);
            }
        }

        WpsrTest::assertSame($wpsr_feed_before, $wpsr_feed, 'pagination formatter does not mutate caller feed');
    });

    WpsrTest::case('pure upgrade placement and URL formatting tables', function () use ($wpsr_table) {
        $wpsr_table('upgrade feature placement slugging', [
            'empty fallback' => ['input' => ['', ''], 'expected' => 'upgrade_page'],
            'feature only' => ['input' => ['General Settings', ''], 'expected' => 'feature_lock_general_settings'],
            'platform and punctuation' => ['input' => ['Booking.com Reviews', 'Reviews'], 'expected' => 'feature_lock_reviews_booking_com_reviews'],
            'Unicode-only components drop' => ['input' => ['東京', ''], 'expected' => 'upgrade_page'],
            'mixed repeated separators' => ['input' => ['  TikTok---Feed  ', 'Social Wall'], 'expected' => 'feature_lock_social_wall_tiktok_feed'],
        ], function ($wpsr_input) {
            return UpgradeUrl::featureLock($wpsr_input[0], $wpsr_input[1]);
        });

        $wpsr_table('upgrade settings placement slugging', [
            'screen' => ['input' => 'License Management', 'expected' => 'settings_license_management'],
            'punctuation' => ['input' => 'booking.com/settings', 'expected' => 'settings_booking_com_settings'],
            'empty fallback' => ['input' => '', 'expected' => 'upgrade_page'],
        ], function ($wpsr_input) {
            return UpgradeUrl::settings($wpsr_input);
        });

        $wpsr_args = [
            'campaign' => 'renew now',
            'id' => ' Promo.42 ',
            'base' => 'https://example.test/buy?existing=yes',
            'extra' => ['coupon' => 'SAVE 20', 'empty' => ''],
        ];
        $wpsr_args_before = $wpsr_args;
        $wpsr_upgrade_medium = defined('WPSOCIALREVIEWS_PRO') ? 'pro_plugin' : 'free_plugin';
        WpsrTest::assertSame(
            'https://example.test/buy?existing=yes&utm_source=wp-social-ninja&utm_medium=' . $wpsr_upgrade_medium . '&utm_campaign=renew%20now&utm_content=feature%20lock%2FUnicode&utm_term=' . rawurlencode(WPSOCIALREVIEWS_VERSION) . '&utm_id=promo_42&coupon=SAVE%2020',
            UpgradeUrl::build('feature lock/Unicode', $wpsr_args),
            'upgrade URL keeps existing query and exact encoded tracking vocabulary'
        );
        WpsrTest::assertSame($wpsr_args_before, $wpsr_args, 'upgrade URL builder does not mutate caller args');
    });

    WpsrTest::case('pure reviewer-name formatting tables and input non-mutation', function () {
        $wpsr_reviews = [
            ['reviewer_name' => '  Ada Lovelace  ', 'id' => 1],
            ['reviewer_name' => 'Grace Hopper', 'id' => 2],
            ['reviewer_name' => '', 'id' => 3],
        ];
        $wpsr_before = $wpsr_reviews;

        WpsrTest::assertSame([
            ['reviewer_name' => 'Ada', 'id' => 1],
            ['reviewer_name' => 'Grace', 'id' => 2],
            ['reviewer_name' => '', 'id' => 3],
        ], ReviewsHelper::handleReviewerName($wpsr_reviews, [
            'reviewer_name' => 'true', 'reviewer_name_format' => 'first-name',
        ]), 'first-name format trims and maps exact values');

        WpsrTest::assertSame([
            ['reviewer_name' => 'Ada L.', 'id' => 1],
            ['reviewer_name' => 'Grace H.', 'id' => 2],
            ['reviewer_name' => '', 'id' => 3],
        ], ReviewsHelper::handleReviewerName($wpsr_reviews, [
            'reviewer_name' => true, 'reviewer_name_format' => 'first-name-last-initial',
        ]), 'last-initial format maps exact values');

        WpsrTest::assertSame([
            ['reviewer_name' => 'A. L.', 'id' => 1],
            ['reviewer_name' => 'G. H.', 'id' => 2],
            ['reviewer_name' => '', 'id' => 3],
        ], ReviewsHelper::handleReviewerName($wpsr_reviews, [
            'reviewer_name' => 'true', 'reviewer_name_format' => 'initials-only',
        ]), 'ASCII initials format maps exact values');

        WpsrTest::assertSame($wpsr_before, ReviewsHelper::handleReviewerName($wpsr_reviews, [
            'reviewer_name' => 'false', 'reviewer_name_format' => 'initials-only',
        ]), 'disabled reviewer display leaves rows exact');
        WpsrTest::assertSame($wpsr_before, $wpsr_reviews, 'reviewer-name formatter does not mutate caller input');
    });

    WpsrTest::case('Airbnb plain integer parser accepts numeric input', function () use ($wpsr_table) {
        $wpsr_reflection = new ReflectionClass(Airbnb::class);
        $wpsr_airbnb = $wpsr_reflection->newInstanceWithoutConstructor();
        // Every pattern in the parser looks for a separator, so a bare integer was
        // rejected. The separator-bearing forms are unchanged.
        $wpsr_table('airbnb numeric input', [
            'bare integer' => ['input' => '123', 'expected' => 123],
            'bare decimal' => ['input' => '4.9', 'expected' => 4.9],
            'trailing comma decimal' => ['input' => '4.9,', 'expected' => 4.9],
            'trailing comma integer' => ['input' => '12,', 'expected' => 12.0],
            'non-numeric' => ['input' => 'abc', 'expected' => null],
            'empty' => ['input' => '', 'expected' => null],
        ], function ($wpsr_input) use ($wpsr_airbnb) {
            return $wpsr_airbnb->validateAndCleanNumericInput($wpsr_input);
        });

        WpsrTest::assertSame('integer', gettype($wpsr_airbnb->validateAndCleanNumericInput('123')),
            'bare integer input keeps an integer type');
    });

    WpsrTest::case('malformed visitor search returns valid UTF-8', function () {
        $wpsr_malformed = ReviewsHelper::sanitizeVisitorSearch("ab\xC3\x28   cd");

        // The stray lead byte is dropped, not carried through or replaced, so the
        // characters the visitor did type remain searchable.
        WpsrTest::assertSame('616228206364', bin2hex($wpsr_malformed),
            'malformed visitor search drops the invalid byte and collapses whitespace');
        WpsrTest::assertSame(true, mb_check_encoding($wpsr_malformed, 'UTF-8'),
            'malformed visitor search returns a valid UTF-8 string');

        // Positive control: valid multibyte input is untouched apart from whitespace.
        WpsrTest::assertSame('café münchen', ReviewsHelper::sanitizeVisitorSearch('café   münchen'),
            'valid multibyte visitor search survives normalization intact');
        WpsrTest::assertSame(100, mb_strlen(ReviewsHelper::sanitizeVisitorSearch(str_repeat('é', 200)), 'UTF-8'),
            'visitor search still truncates by character, not byte');
    });

    WpsrTest::case('plaintext token encryption raises no OpenSSL diagnostic', function () {
        $wpsr_protector = new DataProtector(str_repeat('k', 32), 'phase12-salt');

        // maybe_encrypt() probes plaintext through decrypt(). "access" is valid base64,
        // so it decoded to four bytes and reached openssl_decrypt() as a short IV.
        WpsrTest::assertSame(false, $wpsr_protector->decrypt('access'),
            'plaintext is rejected as ciphertext without an OpenSSL diagnostic');

        $wpsr_tokens = ['access_token' => 'access', 'refresh_token' => '', 'other' => 'keep'];
        $wpsr_before = $wpsr_tokens;
        $wpsr_encrypted = $wpsr_protector->maybe_encrypt_tokens($wpsr_tokens);

        WpsrTest::assertSame('', $wpsr_encrypted['refresh_token'], 'empty token is left untouched');
        WpsrTest::assertSame('keep', $wpsr_encrypted['other'], 'unlisted key is left untouched');
        WpsrTest::assertSame('access', $wpsr_protector->maybe_decrypt_tokens($wpsr_encrypted)['access_token'],
            'encrypted token round-trips back to its plaintext');
        WpsrTest::assertSame($wpsr_before, $wpsr_tokens, 'token encryption does not mutate caller input');

        // Encrypting an already-encrypted value must stay stable rather than double-wrap.
        $wpsr_cipher = $wpsr_protector->encrypt('access');
        WpsrTest::assertSame('access', $wpsr_protector->decrypt($wpsr_protector->maybe_encrypt($wpsr_cipher)),
            'maybe_encrypt is idempotent on an existing payload');
        WpsrTest::assertSame('access', $wpsr_protector->decrypt($wpsr_protector->maybe_encrypt('access')),
            'maybe_encrypt still encrypts plaintext');

        // Anything shorter than the IV cannot be a payload, including exactly one IV.
        foreach (['a', '!!!not base64!!!', str_repeat('A', 16)] as $wpsr_short) {
            WpsrTest::assertSame(false, $wpsr_protector->decrypt($wpsr_short),
                'undersized candidate "' . $wpsr_short . '" is rejected quietly');
        }
    });

    WpsrTest::case('compact number rolls rounded threshold into next suffix', function () use ($wpsr_table) {
        $wpsr_table('compact number format', [
            // Rounding runs after the unit is chosen, so these carried to "1,000K"/"1,000M".
            'rounds up into the next unit' => ['input' => 999999, 'expected' => '1M'],
            'rounds up two units along' => ['input' => 999999999, 'expected' => '1B'],
            'exact threshold' => ['input' => 1000000, 'expected' => '1M'],
            'below any threshold' => ['input' => 999, 'expected' => '999'],
            'keeps a meaningful decimal' => ['input' => 1600, 'expected' => '1.6K'],
            'drops a trailing zero' => ['input' => 2000, 'expected' => '2K'],
            'largest named unit' => ['input' => 1500000000000, 'expected' => '1.5T'],
        ], function ($wpsr_input) {
            return GlobalHelper::shortNumberFormat($wpsr_input);
        });

        WpsrTest::assertSame(0, GlobalHelper::shortNumberFormat(0), 'zero short-circuits to an integer zero');
    });

    WpsrTest::case('CSS colors reject out-of-range functional channels', function () use ($wpsr_table) {
        $wpsr_table('functional color range', [
            // The pattern checked shape only, so these were returned verbatim.
            'rgb channel above 255' => ['input' => 'rgb(999, 0, 0)', 'expected' => ''],
            'rgb channel just above 255' => ['input' => 'rgb(0,0,256)', 'expected' => ''],
            'alpha above one' => ['input' => 'rgba(0, 0, 0, 1.5)', 'expected' => ''],
            'hue above 360' => ['input' => 'hsl(361, 50, 50)', 'expected' => ''],
            // Positive controls: every in-range form is still accepted verbatim.
            'rgb in range' => ['input' => 'rgb(255, 128, 0)', 'expected' => 'rgb(255, 128, 0)'],
            'alpha at one' => ['input' => 'rgba(0,0,0,1)', 'expected' => 'rgba(0,0,0,1)'],
            'fractional alpha' => ['input' => 'rgba(0, 0, 0, 0.5)', 'expected' => 'rgba(0, 0, 0, 0.5)'],
            'percentage channels' => ['input' => 'rgb(100%, 0%, 0%)', 'expected' => 'rgb(100%, 0%, 0%)'],
            'hsl in range' => ['input' => 'hsl(200, 50, 50)', 'expected' => 'hsl(200, 50, 50)'],
            'hsla with percentages' => ['input' => 'hsla(200, 50%, 50%, 0.4)', 'expected' => 'hsla(200, 50%, 50%, 0.4)'],
            // Other branches are unaffected.
            'hex' => ['input' => '#fff', 'expected' => '#fff'],
            'named' => ['input' => 'transparent', 'expected' => 'transparent'],
            'hostile' => ['input' => 'javascript:alert(1)', 'expected' => ''],
        ], function ($wpsr_input) {
            return wpsr_sanitize_color($wpsr_input);
        });
    });

    WpsrTest::case('Facebook positive bare start date means past days', function () use ($wpsr_table) {
        $wpsr_table('bare relative date sign', [
            // A start window counts backwards, so a bare positive number is past days.
            'bare positive start' => ['input' => ['50', 'start'], 'expected' => '-50 days'],
            'bare negative start' => ['input' => ['-50', 'start'], 'expected' => '-50 days'],
            // An end date may legitimately be a future event, so it keeps its sign.
            'bare positive end' => ['input' => ['50', 'end'], 'expected' => '50 days'],
            'bare negative end' => ['input' => ['-50', 'end'], 'expected' => '-50 days'],
            'explicit future end' => ['input' => ['+3 hours', 'end'], 'expected' => '+3 hours'],
            'unit already present' => ['input' => ['5 days', 'start'], 'expected' => '-5 days'],
            'now short-circuits' => ['input' => ['now', 'start'], 'expected' => 'now'],
        ], function ($wpsr_input) {
            return FacebookHelper::fixRelativeDateInput($wpsr_input[0], $wpsr_input[1]);
        });
    });

    WpsrTest::case('Facebook absolute cache date returns documented integer', function () {
        // floor() yields a float, so the documented `@return int` never held. The value
        // is unchanged; only the type is corrected, which keeps cache keys stable.
        $wpsr_absolute = FacebookHelper::normalizeDateForCache('2024-02-29 23:59:59 UTC');
        WpsrTest::assertSame(1709247600, $wpsr_absolute, 'absolute cache date floors to its exact hour');
        WpsrTest::assertSame('integer', gettype($wpsr_absolute), 'absolute cache date honours the documented int contract');

        // The remaining branches are clock-dependent, so only the contract is asserted.
        foreach (['now' => 'now', 'relative' => '-3 days', 'unparsable' => 'total nonsense !!'] as $wpsr_name => $wpsr_input) {
            WpsrTest::assertSame('integer', gettype(FacebookHelper::normalizeDateForCache($wpsr_input)),
                $wpsr_name . ' cache date honours the documented int contract');
        }
    });

    WpsrTest::case('Facebook malformed identifiers reject without diagnostics', function () use ($wpsr_table) {
        // wp_parse_url() on a non-URL yields no host key; reading it raised a warning
        // on every malformed input. The rejection values themselves never changed.
        $wpsr_table('album id from value', [
            'malformed rejects quietly' => ['input' => 'not a URL', 'expected' => null],
            'empty rejects quietly' => ['input' => '', 'expected' => null],
            'numeric passes through' => ['input' => '145555168501702', 'expected' => '145555168501702'],
            'album URL yields its id' => [
                'input' => 'https://www.facebook.com/media/set/?set=a.145555168501702&type=3',
                'expected' => '145555168501702',
            ],
        ], function ($wpsr_input) {
            return FacebookHelper::getAlbumId($wpsr_input);
        });

        $wpsr_table('single video playlist id', [
            'malformed rejects quietly' => ['input' => 'not a URL', 'expected' => false],
            'empty rejects quietly' => ['input' => '', 'expected' => false],
            'watch URL yields the video id' => [
                'input' => 'https://www.facebook.com/watch/100091416590270/1336242490940769',
                'expected' => '1336242490940769',
            ],
        ], function ($wpsr_input) {
            return FacebookHelper::validateAndRetrieveSingleVideoPlaylistId($wpsr_input);
        });
    });

    WpsrTest::case('reviewer initials preserve Unicode characters', function () {
        $wpsr_names = function ($wpsr_rows, $wpsr_format) {
            $wpsr_result = ReviewsHelper::handleReviewerName($wpsr_rows, [
                'reviewer_name' => 'true', 'reviewer_name_format' => $wpsr_format,
            ]);
            return array_map(function ($wpsr_row) {
                return (string) ($wpsr_row['reviewer_name'] ?? '');
            }, $wpsr_result);
        };

        // Cutting by byte left a lone lead byte behind; cutting by character keeps
        // the accent, and uppercasing is multibyte-aware.
        WpsrTest::assertSame(['É. Å.', '李. 小.'], $wpsr_names([
            ['reviewer_name' => 'Élodie Ångström'],
            ['reviewer_name' => '李 小龍'],
        ], 'initials-only'), 'initials-only keeps whole multibyte characters');

        // Positive control: ASCII initials are unchanged, including the uppercasing.
        WpsrTest::assertSame(['J. S.', 'A.'], $wpsr_names([
            ['reviewer_name' => 'john smith'],
            ['reviewer_name' => 'ada'],
        ], 'initials-only'), 'initials-only still uppercases plain ASCII names');

        // The sibling format shares the same byte-substring defect.
        WpsrTest::assertSame(['Élodie Å.', 'john S.'], $wpsr_names([
            ['reviewer_name' => 'Élodie Ångström'],
            ['reviewer_name' => 'john smith'],
        ], 'first-name-last-initial'), 'last-initial format keeps whole multibyte characters');

        WpsrTest::assertSame(true, mb_check_encoding(implode('', $wpsr_names([
            ['reviewer_name' => 'Élodie Ångström'],
        ], 'initials-only')), 'UTF-8'), 'formatted initials are valid UTF-8');
    });
};
