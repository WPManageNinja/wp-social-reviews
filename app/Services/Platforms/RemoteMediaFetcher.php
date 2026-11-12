<?php

namespace WPSocialReviews\App\Services\Platforms;

/**
 * The only way to pull a remote image into a temp file. get_headers(), getimagesize() and
 * wp_get_image_editor() skip the WP HTTP address checks, and download_url() is unbounded.
 * This adds a size bound and proof that the bytes are an image before they are stored.
 */
class RemoteMediaFetcher
{
    const MAX_BYTES = 8388608;
    const TIMEOUT = 10;
    const MAX_REDIRECTS = 3;

    /**
     * Formats wp_get_image_editor() can open. Nothing else reaches the uploads tree.
     */
    const ALLOWED_TYPES = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_GIF,
        IMAGETYPE_WEBP,
        IMAGETYPE_BMP,
    ];

    /**
     * Size ceiling for one download, in bytes. A non-positive filtered value falls back to the default.
     *
     * @return int
     */
    public static function maxBytes()
    {
        $bytes = (int) apply_filters('wpsocialreviews/remote_media_max_bytes', self::MAX_BYTES);

        return $bytes > 0 ? $bytes : self::MAX_BYTES;
    }

    /**
     * Per-request timeout, in seconds. A non-positive filtered value falls back to the default.
     *
     * @return int
     */
    public static function timeout()
    {
        $seconds = (int) apply_filters('wpsocialreviews/remote_media_timeout', self::TIMEOUT);

        return $seconds > 0 ? $seconds : self::TIMEOUT;
    }

    /**
     * @param mixed $url
     *
     * @return bool
     */
    public static function isSafeUrl($url)
    {
        return wpsr_is_media_url($url);
    }

    /**
     * Download one image to a local temp file.
     *
     * @param mixed $url
     *
     * @return array{file: string, mime: string, width: int, height: int}|false
     */
    public static function fetch($url)
    {
        $target = wpsr_sanitize_media_url($url);
        if ($target === '') {
            return false;
        }

        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tempFile = wp_tempnam('wpsr-media');
        if (!$tempFile) {
            return false;
        }

        $maxBytes = self::maxBytes();

        // reject_unsafe_urls checks every redirect hop too. Core checks only the IPv4
        // answer and then reconnects, so AAAA or short-TTL rebinding hosts can slip through.
        $response = wp_safe_remote_get($target, [
            'timeout'             => self::timeout(),
            'redirection'         => self::MAX_REDIRECTS,
            'stream'              => true,
            'filename'            => $tempFile,
            // One byte over the ceiling: the transport truncates silently and still
            // reports 200, so overflow only shows as an oversized file.
            'limit_response_size' => $maxBytes + 1,
            'user-agent'          => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            wp_delete_file($tempFile);
            return false;
        }

        return self::verifyImage($tempFile, $maxBytes);
    }

    /**
     * @param array|false $fetched return value of fetch()
     */
    public static function release($fetched)
    {
        if (is_array($fetched) && !empty($fetched['file']) && file_exists($fetched['file'])) {
            wp_delete_file($fetched['file']);
        }
    }

    /**
     * Check the downloaded bytes, never the URL. Drop anything oversized or not an accepted image.
     *
     * @param string $tempFile
     * @param int    $maxBytes
     *
     * @return array{file: string, mime: string, width: int, height: int}|false
     */
    private static function verifyImage($tempFile, $maxBytes)
    {
        $size = @filesize($tempFile);
        if (!$size || $size > $maxBytes) {
            wp_delete_file($tempFile);
            return false;
        }

        $info = @getimagesize($tempFile);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            wp_delete_file($tempFile);
            return false;
        }

        if (!in_array($info[2], self::ALLOWED_TYPES, true)) {
            wp_delete_file($tempFile);
            return false;
        }

        return [
            'file'   => $tempFile,
            'mime'   => isset($info['mime']) ? $info['mime'] : '',
            'width'  => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }
}
