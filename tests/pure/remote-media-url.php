<?php
/**
 * Phase 12: destination rules for URLs the server fetches by itself (F-003).
 *
 * The rule is wp_http_validate_url(), so these cases pin the plugin's use of it
 * rather than restating WordPress's address list. Only vectors that need no DNS
 * are asserted unconditionally, because the lab network has no egress.
 */

return function () {
    // Home and site URLs are pinned so the same-host branch cannot make the
    // private-address corpus pass by accident on a loopback lab install.
    $wpsr_pin_home = function ($wpsr_home) {
        $wpsr_filter = function () use ($wpsr_home) {
            return $wpsr_home;
        };

        add_filter('home_url', $wpsr_filter, 99);
        add_filter('site_url', $wpsr_filter, 99);
        add_filter('option_home', $wpsr_filter, 99);
        add_filter('option_siteurl', $wpsr_filter, 99);

        return function () use ($wpsr_filter) {
            remove_filter('home_url', $wpsr_filter, 99);
            remove_filter('site_url', $wpsr_filter, 99);
            remove_filter('option_home', $wpsr_filter, 99);
            remove_filter('option_siteurl', $wpsr_filter, 99);
        };
    };

    $wpsr_table = function ($wpsr_label, array $wpsr_vectors) {
        foreach ($wpsr_vectors as $wpsr_name => $wpsr_expected) {
            WpsrTest::assertSame(
                $wpsr_expected,
                wpsr_is_media_url($wpsr_name),
                $wpsr_label . ' [' . $wpsr_name . ']'
            );
        }
    };

    WpsrTest::case('media URL refuses literal private and reserved addresses', function () use ($wpsr_pin_home, $wpsr_table) {
        $wpsr_restore = $wpsr_pin_home('https://reviews.example.com');

        try {
            // Literal IPv4 skips resolution in wp_http_validate_url(), so these
            // are decided without touching DNS.
            $wpsr_table('IPv4 literals', [
                'http://127.0.0.1/avatar.jpg'        => false,
                'http://0.0.0.1/avatar.jpg'          => false,
                'http://10.0.0.5/avatar.jpg'         => false,
                'http://172.16.0.5/avatar.jpg'       => false,
                'http://192.168.1.5/avatar.jpg'      => false,
                'http://169.254.169.254/latest/meta' => false,
                'http://100.64.0.5/avatar.jpg'       => false,
                'http://192.0.0.1/avatar.jpg'        => false,
                'http://192.0.2.1/avatar.jpg'        => false,
                'http://192.88.99.1/avatar.jpg'      => false,
                'http://198.51.100.1/avatar.jpg'     => false,
                'http://203.0.113.1/avatar.jpg'      => false,
                'http://198.18.0.1/avatar.jpg'       => false,
                'http://224.0.0.1/avatar.jpg'        => false,
                'http://239.255.255.255/avatar.jpg'  => false,
                'http://240.0.0.1/avatar.jpg'        => false,
                'http://255.255.255.255/avatar.jpg'  => false,
            ]);

            // Any host holding : [ ] is refused outright, which covers IPv6.
            $wpsr_table('IPv6 literals', [
                'http://[::1]/avatar.jpg'         => false,
                'http://[fe80::1]/avatar.jpg'     => false,
                'http://[fc00::1]/avatar.jpg'     => false,
                'http://[::ffff:127.0.0.1]/a.jpg' => false,
            ]);

            $wpsr_table('non-http schemes', [
                'file:///etc/passwd'                 => false,
                'gopher://127.0.0.1:11211/'          => false,
                'ftp://example.com/avatar.jpg'       => false,
                'php://filter/resource=index.php'    => false,
                'javascript:alert(1)'                => false,
                'data:image/gif;base64,R0lGODlhAQ==' => false,
            ]);

            // The part before @ reads as a host to a careless parser and as
            // credentials to curl, so no credential form is accepted at all.
            $wpsr_table('credential forms', [
                'http://cdn.example.com@127.0.0.1/a.jpg' => false,
                'https://user:pass@192.168.1.5/a.jpg'    => false,
            ]);

            $wpsr_table('shapes with no fetchable host', [
                ''                          => false,
                '/wp-content/uploads/a.jpg' => false,
                '//example.com/a.jpg'       => false,
                'example.com/a.jpg'         => false,
                'http:///a.jpg'             => false,
            ]);

            $wpsr_table('ports outside the HTTP API set', [
                'http://10.0.0.5:6379/a.jpg'    => false,
                'http://192.168.1.5:3306/a.jpg' => false,
            ]);
        } finally {
            $wpsr_restore();
        }
    });

    WpsrTest::case('media URL keeps the install\'s own host fetchable', function () use ($wpsr_pin_home) {
        // A local or intranet install resolves its own host to a private address;
        // blocking it would break every testimonial image on those sites. The
        // same-host branch also skips resolution, so this needs no DNS.
        $wpsr_restore = $wpsr_pin_home('http://127.0.0.1:8080');

        try {
            WpsrTest::assertTrue(
                wpsr_is_media_url('http://127.0.0.1:8080/wp-content/uploads/a.jpg'),
                'own host on its own port is allowed'
            );

            WpsrTest::assertSame(
                false,
                wpsr_is_media_url('http://127.0.0.1:3306/a.jpg'),
                'own host on a foreign port is still refused'
            );

            WpsrTest::assertSame(
                false,
                wpsr_is_media_url('http://192.168.1.9:8080/a.jpg'),
                'a different private host is still refused'
            );
        } finally {
            $wpsr_restore();
        }
    });

    WpsrTest::case('media URL sanitizer returns the normalized URL or an empty string', function () use ($wpsr_pin_home) {
        $wpsr_restore = $wpsr_pin_home('https://reviews.example.com');

        try {
            WpsrTest::assertSame(
                'https://reviews.example.com/wp-content/a.jpg',
                wpsr_sanitize_media_url("  https://reviews.example.com/wp-content/a.jpg\t"),
                'surrounding whitespace is trimmed off an allowed URL'
            );

            WpsrTest::assertSame('', wpsr_sanitize_media_url('http://127.0.0.1/a.jpg'), 'loopback collapses to empty');
            WpsrTest::assertSame('', wpsr_sanitize_media_url(['https://reviews.example.com/a.jpg']), 'array input is empty');
            WpsrTest::assertSame('', wpsr_sanitize_media_url(null), 'null input is empty');
            WpsrTest::assertSame('', wpsr_sanitize_media_url(12345), 'numeric input is empty');
        } finally {
            $wpsr_restore();
        }
    });

};
