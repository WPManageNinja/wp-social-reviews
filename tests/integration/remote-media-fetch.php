<?php
/**
 * Phase 10: the server-side fetch boundary for stored media URLs (F-003).
 *
 * A review manager can store any URL in reviewer_img, and a public visitor
 * later triggers the optimizer, so the destination is re-checked at fetch time
 * rather than trusted because it survived validation at write time.
 */

use WPSocialReviews\App\Models\OptimizeImage;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\App\Services\Platforms\RemoteMediaFetcher;
use WPSocialReviews\App\Services\Platforms\ReviewImageOptimizationHandler;

return function () {
    $wpsr_marker = WpsrFactory::marker();

    // Smallest valid 1x1 PNG, so a permitted fetch has real bytes to verify.
    $wpsr_png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );

    // The install's own host is exempt from the private-address rule, so it is
    // the one destination that can be permitted without leaving the lab network.
    $wpsr_own = home_url('/wp-content/uploads/' . $wpsr_marker . '-avatar.png');

    $wpsr_responder = function ($wpsr_body, $wpsr_status = 200, $wpsr_location = '') {
        return function ($wpsr_url, $wpsr_args) use ($wpsr_body, $wpsr_status, $wpsr_location) {
            // The real transport truncates at limit_response_size and still
            // reports 200, so the stand-in has to truncate too or the ceiling
            // is never exercised.
            if (!empty($wpsr_args['limit_response_size'])) {
                $wpsr_body = substr($wpsr_body, 0, (int) $wpsr_args['limit_response_size']);
            }

            if (!empty($wpsr_args['filename'])) {
                file_put_contents($wpsr_args['filename'], $wpsr_body);
            }

            return [
                'headers'  => $wpsr_location === '' ? [] : ['location' => $wpsr_location],
                'body'     => empty($wpsr_args['filename']) ? $wpsr_body : '',
                'response' => ['code' => $wpsr_status, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => isset($wpsr_args['filename']) ? $wpsr_args['filename'] : null,
            ];
        };
    };

    // wp_tempnam() with no $dir writes to get_temp_dir(), not the uploads tree.
    $wpsr_temp_files = function () {
        return glob(trailingslashit(get_temp_dir()) . 'wpsr-media*') ?: [];
    };

    WpsrTest::case('fetch refuses private destinations before any transport opens', function () use ($wpsr_temp_files) {
        // Every vector avoids the loopback host itself, which is this install's
        // own host in the lab and therefore legitimately exempt.
        $wpsr_blocked = [
            'link-local metadata'  => 'http://169.254.169.254/latest/meta-data/',
            'private class A'      => 'http://10.0.0.5/avatar.jpg',
            'private class C'      => 'http://192.168.1.5/avatar.jpg',
            'unique local IPv6'    => 'http://[fd00::1]/avatar.jpg',
            'carrier-grade NAT'    => 'http://100.64.0.5/avatar.jpg',
            'IPv4-mapped loopback' => 'http://[::ffff:127.0.0.1]/avatar.jpg',
            'local file scheme'    => 'file:///etc/passwd',
            'IPv6 loopback'        => 'http://[::1]/avatar.jpg',
            'database port'        => 'http://127.0.0.1:3306/avatar.jpg',
            'redis port'           => 'http://10.0.0.5:6379/avatar.jpg',
            'credential prefix'    => 'http://cdn.example.com@169.254.169.254/a.jpg',
            'empty URL'            => '',
        ];

        $wpsr_before = $wpsr_temp_files();
        WpsrTest::resetOutbound();

        foreach ($wpsr_blocked as $wpsr_label => $wpsr_url) {
            WpsrTest::assertSame(false, RemoteMediaFetcher::isSafeUrl($wpsr_url), 'isSafeUrl refuses ' . $wpsr_label);
            WpsrTest::assertSame(false, RemoteMediaFetcher::fetch($wpsr_url), 'fetch refuses ' . $wpsr_label);
        }

        WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'refused destinations attempt zero transport');
        WpsrTest::assertSame($wpsr_before, $wpsr_temp_files(), 'refused destinations leave no temp file');
    });

    WpsrTest::case('fetch permits a public image and returns verified local bytes', function () use (
        $wpsr_own,
        $wpsr_png,
        $wpsr_responder,
        $wpsr_temp_files
    ) {
        $wpsr_before = $wpsr_temp_files();

        WpsrTest::setHttpResponder($wpsr_responder($wpsr_png));
        WpsrTest::resetOutbound();

        try {
            $wpsr_media = RemoteMediaFetcher::fetch($wpsr_own);

            WpsrTest::assertTrue(is_array($wpsr_media), 'permitted destination returns fetched media');
            WpsrTest::assertSame(1, count(WpsrTest::remoteRequests()), 'permitted destination opens exactly one transport');
            WpsrTest::assertSame($wpsr_own, WpsrTest::remoteRequests()[0]['url'] ?? null, 'transport target is the exact stored URL');
            WpsrTest::assertSame(
                RemoteMediaFetcher::MAX_REDIRECTS,
                WpsrTest::remoteRequests()[0]['args']['redirection'] ?? null,
                'transport is given the bounded redirect budget'
            );
            WpsrTest::assertSame(
                true,
                WpsrTest::remoteRequests()[0]['args']['reject_unsafe_urls'] ?? null,
                'transport runs wp_http_validate_url on the first hop and every redirect'
            );
            WpsrTest::assertSame('image/png', $wpsr_media['mime'] ?? null, 'fetched type is read from the bytes');
            WpsrTest::assertSame(1, $wpsr_media['width'] ?? null, 'fetched width is read from the bytes');
            WpsrTest::assertSame(1, $wpsr_media['height'] ?? null, 'fetched height is read from the bytes');
            WpsrTest::assertTrue(file_exists((string) ($wpsr_media['file'] ?? '')), 'fetched bytes land in a local temp file');

            RemoteMediaFetcher::release($wpsr_media);
            WpsrTest::assertSame($wpsr_before, $wpsr_temp_files(), 'release removes the temp file');
        } finally {
            WpsrTest::clearHttpResponder();
        }
    });

    WpsrTest::case('fetch never treats a redirect response as an image', function () use (
        $wpsr_own,
        $wpsr_responder,
        $wpsr_temp_files
    ) {
        // Refusing the hop itself is WP_Http::validate_redirects, which the
        // reject_unsafe_urls assertion above pins. What this case holds is the
        // plugin side: a 3xx that reaches us stores nothing and leaks nothing.
        $wpsr_before = $wpsr_temp_files();

        WpsrTest::setHttpResponder($wpsr_responder('', 302, 'http://169.254.169.254/latest/meta-data/'));
        WpsrTest::resetOutbound();

        try {
            WpsrTest::assertSame(false, RemoteMediaFetcher::fetch($wpsr_own), 'redirect response yields no media');
            WpsrTest::assertSame(1, count(WpsrTest::remoteRequests()), 'redirect response opens only the one transport');
            WpsrTest::assertSame($wpsr_before, $wpsr_temp_files(), 'redirect response leaves no temp file');
        } finally {
            WpsrTest::clearHttpResponder();
        }
    });

    WpsrTest::case('fetch refuses a response that is not an image', function () use (
        $wpsr_own,
        $wpsr_responder,
        $wpsr_temp_files
    ) {
        $wpsr_before = $wpsr_temp_files();

        // What an internal admin panel or a JSON metadata endpoint would answer.
        WpsrTest::setHttpResponder($wpsr_responder('{"AccessKeyId":"AKIA-not-an-image"}'));
        WpsrTest::resetOutbound();

        try {
            WpsrTest::assertSame(false, RemoteMediaFetcher::fetch($wpsr_own), 'non-image body is refused');
            WpsrTest::assertSame($wpsr_before, $wpsr_temp_files(), 'non-image body leaves no temp file');
        } finally {
            WpsrTest::clearHttpResponder();
        }
    });

    WpsrTest::case('fetch refuses a response over the size ceiling', function () use (
        $wpsr_own,
        $wpsr_png,
        $wpsr_responder,
        $wpsr_temp_files
    ) {
        $wpsr_before = $wpsr_temp_files();

        // A real PNG header followed by padding. The transport truncates rather
        // than failing, so the header still parses and only the byte count can
        // refuse it — the exact shape a half-written image would arrive in.
        $wpsr_oversized = $wpsr_png . str_repeat('A', RemoteMediaFetcher::MAX_BYTES);

        WpsrTest::setHttpResponder($wpsr_responder($wpsr_oversized));
        WpsrTest::resetOutbound();

        try {
            WpsrTest::assertSame(false, RemoteMediaFetcher::fetch($wpsr_own), 'oversized body is refused');
            WpsrTest::assertSame($wpsr_before, $wpsr_temp_files(), 'oversized body leaves no temp file');
        } finally {
            WpsrTest::clearHttpResponder();
        }
    });

    WpsrTest::case('saving a review keeps a private reviewer image but never fetches it', function () use ($wpsr_marker) {
        // Creating a custom-platform review writes this option as a side effect.
        WpsrFactory::trackAbsentFixedOption('wpsr_reviews_custom_business_info');

        try {
            $wpsr_result = WpsrTest::rest('POST', '/reviews', ['review' => [
                'platform_name'  => 'custom',
                'source_id'      => $wpsr_marker . '-ssrf-source',
                'review_id'      => $wpsr_marker . '-ssrf-review',
                'reviewer_name'  => 'Delegated manager',
                'reviewer_email' => 'valid@example.test',
                'reviewer_img'   => 'http://169.254.169.254/latest/meta-data/iam/',
                'reviewer_text'  => 'proof',
                'review_time'    => '2026-06-07 08:09:10',
                'rating'         => 5,
            ]]);

            // Deciding the destination at save time costs a DNS lookup per field and
            // silently drops a good URL when the resolver blinks, so the value is
            // stored as given. The next case holds the boundary that matters.
            WpsrTest::assertHealthy($wpsr_result, 'private reviewer image still saves the review', [200]);

            $wpsr_created = $wpsr_result['data']['review'] ?? null;
            $wpsr_id = (int) (is_object($wpsr_created) ? $wpsr_created->id : ($wpsr_created['id'] ?? 0));
            $wpsr_review = Review::find($wpsr_id);

            WpsrTest::assertTrue((bool) $wpsr_review, 'review row exists before value assertions');

            if ($wpsr_review) {
                WpsrFactory::trackReview($wpsr_review);
                WpsrTest::assertSame(
                    'http://169.254.169.254/latest/meta-data/iam/',
                    (string) $wpsr_review->reviewer_img,
                    'private reviewer image is stored unchanged, exactly like a legacy row'
                );
            }
        } finally {
            WpsrFactory::cleanup();
        }
    });

    WpsrTest::case('a stored private reviewer image never reaches the optimizer sinks', function () use ($wpsr_marker) {
        // Rows written before this defence existed, or by a third-party hook,
        // are covered because the check runs at fetch time and not only at save.
        $wpsr_handler = new ReviewImageOptimizationHandler('google');
        $wpsr_media_id = $wpsr_marker . '-media-ssrf';
        $wpsr_user_name = $wpsr_marker . '-user-ssrf';

        // Created up front so the measured call is the only thing under assertion.
        $wpsr_platform_dir = $wpsr_handler->getUploadDir('google');
        $wpsr_user_dir = trailingslashit($wpsr_platform_dir) . $wpsr_user_name;

        $wpsr_feed = [
            'review_id'     => $wpsr_media_id,
            'source_id'     => $wpsr_user_name,
            'platform_name' => 'google',
            'reviewer_img'  => 'http://169.254.169.254/latest/meta-data/iam/',
        ];

        WpsrTest::resetOutbound();
        $wpsr_handler->saveImage($wpsr_feed, 'google');

        WpsrTest::assertSame([], WpsrTest::remoteRequests(), 'stored private URL attempts zero transport');
        WpsrTest::assertSame(false, file_exists($wpsr_user_dir), 'stored private URL creates no upload directory');

        $wpsr_row = OptimizeImage::where('media_id', $wpsr_media_id)
            ->where('user_name', $wpsr_user_name)
            ->first();

        WpsrTest::assertTrue((bool) $wpsr_row, 'the attempt is still recorded so the rate limiter counts it');

        if ($wpsr_row) {
            // Tracked for teardown before any further assertion can abort the case.
            WpsrFactory::trackOptimizeImage($wpsr_row);
            WpsrTest::assertSame(0, (int) $wpsr_row->images_resized, 'the recorded attempt stores no resized image');
        }
    });
};
