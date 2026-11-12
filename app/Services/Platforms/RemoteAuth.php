<?php

namespace WPSocialReviews\App\Services\Platforms;

use WPSocialReviews\App\Services\DataProtector;
use WPSocialReviews\App\Services\Helper as ServicesHelper;
use WPSocialReviews\Framework\Support\Arr;

if (!defined('ABSPATH')) {
    exit;
}

class RemoteAuth
{
    /** Provider/flow pairs the bridge can refresh server-to-server; everything else uses interactive reconnect. */
    const SUPPORTED_REFRESH_FLOWS = [
        'google/reviews',
        'google/youtube',
        'tiktok/feed',
    ];

    /** This site's bridge identity { site_id, site_secret }, secret encrypted at rest. Signs every refresh call. */
    const IDENTITY_OPTION = 'wpsr_auth_bridge_identity';

    /**
     * Refresh an OAuth token through the wpsn-auth bridge, HMAC-signed; returns the provider token array or a typed WP_Error.
     *
     * @param string $provider     Bridge provider id (e.g. 'google', 'tiktok').
     * @param string $flow         Bridge flow id (e.g. 'reviews', 'youtube', 'feed').
     * @param string $refreshToken Current refresh token.
     * @return array<string, mixed>|\WP_Error
     */
    public static function refreshAuth($provider, $flow, $refreshToken)
    {
        $pair = $provider . '/' . $flow;
        if (!in_array($pair, self::SUPPORTED_REFRESH_FLOWS, true)) {
            return self::refreshError(422);
        }

        if (empty($refreshToken)) {
            return new \WP_Error(
                'wpsr_refresh_reconnect',
                __('Missing refresh token. Please reconnect your account.', 'wp-social-reviews'),
                ['status' => 0]
            );
        }

        // Per-site key issued in the connect handshake. No shared secret ships with the plugin.
        $identity   = self::bridgeIdentity();
        $siteId     = (string) Arr::get($identity, 'site_id', '');
        $siteSecret = (string) Arr::get($identity, 'site_secret', '');

        if (empty($siteId) || empty($siteSecret)) {
            // Not provisioned yet; reconnecting issues one. Never send an unsigned request.
            return new \WP_Error(
                'wpsr_refresh_reconnect',
                __('Your connection needs to be re-established. Please reconnect your account.', 'wp-social-reviews'),
                ['status' => 0]
            );
        }

        $body      = ['refresh_token' => (string) $refreshToken];
        $canonical = self::canonicalJson($body);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $canonical, $siteSecret);

        $url = ServicesHelper::authBaseUrl()
            . '/internal/refresh/' . rawurlencode($provider) . '/' . rawurlencode($flow);

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'     => 'application/json',
                // Public id that tells the bridge which key to verify with. Not a secret.
                'X-WPSN-Site-Id'   => $siteId,
                'X-WPSN-Timestamp' => $timestamp,
                'X-WPSN-Signature' => $signature,
            ],
            // Send the canonical bytes verbatim so the bridge's re-canonicalisation matches.
            'body'    => $canonical,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('wpsr_refresh_http', $response->get_error_message(), ['status' => 0]);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        $data    = is_array($decoded) ? $decoded : [];

        if (200 !== $status || empty($data['ok']) || empty($data['token']) || !is_array($data['token'])) {
            return self::refreshError($status);
        }

        return $data['token'];
    }

    /**
     * True when the refresh failure means the user must reconnect (vs a transient/config error).
     *
     * @param mixed $error
     * @return bool
     */
    public static function isReconnectError($error)
    {
        return is_wp_error($error)
            && in_array($error->get_error_code(), ['wpsr_refresh_reconnect', 'wpsr_refresh_unsupported'], true);
    }

    /**
     * This site's bridge identity for signing refresh requests; resolves stored option → wp-config overrides → filter.
     *
     * @return array{site_id: string, site_secret: string}
     */
    public static function bridgeIdentity()
    {
        $stored     = get_option(self::IDENTITY_OPTION, []);
        $siteId     = is_array($stored) ? (string) Arr::get($stored, 'site_id', '') : '';
        $siteSecret = is_array($stored) ? self::unprotectValue((string) Arr::get($stored, 'site_secret', '')) : '';

        // Manual override for self-hosted / single-site deployments and local testing, where the
        // operator controls both the plugin and the bridge and provisions the pair by hand.
        if (defined('WPSN_AUTH_SITE_ID') && WPSN_AUTH_SITE_ID) {
            $siteId = (string) WPSN_AUTH_SITE_ID;
        }
        if (defined('WPSN_AUTH_REFRESH_SECRET') && WPSN_AUTH_REFRESH_SECRET) {
            $siteSecret = (string) WPSN_AUTH_REFRESH_SECRET;
        }

        $identity = apply_filters('wpsocialreviews/auth_bridge_identity', [
            'site_id'     => $siteId,
            'site_secret' => $siteSecret,
        ]);

        return is_array($identity) ? $identity : ['site_id' => '', 'site_secret' => ''];
    }

    /**
     * Store the per-site credential from the connect handshake. A result without one
     * (older bridge) leaves the existing identity in place.
     *
     * @param mixed $result
     * @return void
     */
    private static function storeBridgeIdentity($result)
    {
        if (!is_array($result)) {
            return;
        }

        $siteId     = (string) Arr::get($result, 'site_id', '');
        $siteSecret = (string) Arr::get($result, 'site_secret', '');

        if (empty($siteId) || empty($siteSecret)) {
            return;
        }

        update_option(self::IDENTITY_OPTION, [
            'site_id'     => $siteId,
            'site_secret' => self::protectValue($siteSecret),
        ], 'no');
    }

    /**
     * Map an HTTP status from the bridge to a typed WP_Error with a generic, admin-safe message.
     *
     * @param int $status
     * @return \WP_Error
     */
    private static function refreshError($status)
    {
        switch ($status) {
            case 401:
                return new \WP_Error(
                    'wpsr_refresh_auth_mismatch',
                    __('Could not refresh the connection due to a configuration issue.', 'wp-social-reviews'),
                    ['status' => 401]
                );
            case 404:
                return new \WP_Error(
                    'wpsr_refresh_unknown',
                    __('Unknown authentication provider.', 'wp-social-reviews'),
                    ['status' => 404]
                );
            case 422:
                return new \WP_Error(
                    'wpsr_refresh_unsupported',
                    __('This connection cannot be refreshed automatically. Please reconnect your account.', 'wp-social-reviews'),
                    ['status' => 422]
                );
            case 500:
                return new \WP_Error(
                    'wpsr_refresh_misconfigured',
                    __('Could not refresh the connection due to a configuration issue.', 'wp-social-reviews'),
                    ['status' => 500]
                );
            case 502:
                return new \WP_Error(
                    'wpsr_refresh_reconnect',
                    __('Your connection has expired. Please reconnect your account.', 'wp-social-reviews'),
                    ['status' => 502]
                );
            default:
                return new \WP_Error(
                    'wpsr_refresh_failed',
                    __('Token refresh failed. Please try again.', 'wp-social-reviews'),
                    ['status' => $status]
                );
        }
    }

    /**
     * Canonical JSON matching what the bridge signs: unescaped slashes, escaped unicode, compact.
     * A body with more than one key or a nested object needs a recursive ksort, or signatures 401.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    private static function canonicalJson($data)
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    public static function prepareAuth($platform, $provider, $flow, $settings = [])
    {
        $provider  = Arr::get($settings, 'provider', $provider);
        $flow      = Arr::get($settings, 'flow', $flow);
        $returnUrl = Arr::get($settings, 'return_url', '');
        $source    = Arr::get($settings, 'source', '');

        if (!$provider || !$flow || !$returnUrl) {
            wp_send_json_error([
                'message' => __('Missing auth preparation data.', 'wp-social-reviews')
            ], 423);
        }

        $siteHost   = wp_parse_url(home_url(), PHP_URL_HOST);
        $returnHost = wp_parse_url($returnUrl, PHP_URL_HOST);
        if (!$returnHost || strtolower($returnHost) !== strtolower($siteHost)) {
            wp_send_json_error([
                'message' => __('Invalid return URL.', 'wp-social-reviews')
            ], 400);
        }

        $verifier  = ServicesHelper::createAuthVerifier();
        $resultKey = ServicesHelper::createAuthVerifier();
        $flowId    = wp_generate_uuid4();
        $challenge = ServicesHelper::base64UrlEncode(hash('sha256', $verifier, true));

        set_transient(self::transientKey($platform, $flowId), [
            'verifier'   => $verifier,
            'result_key' => self::protectValue($resultKey),
            'platform'   => $platform,
            'provider'   => $provider,
            'flow'       => $flow,
        ], 15 * MINUTE_IN_SECONDS);

        // Sent as a POST form (not a query string) so auth_result_key never lands in a URL,
        // browser history, or server logs.
        // Core fields go last so the user-fields filter can never override them.
        $fields = array_merge(self::bridgeUserFields(), [
            'return_url'      => $returnUrl,
            'auth_flow_id'    => $flowId,
            'auth_challenge'  => $challenge,
            'auth_result_key' => $resultKey,
            'source'          => $source,
        ]);
        $action = ServicesHelper::authBaseUrl() . '/connect/' . rawurlencode($provider) . '/' . rawurlencode($flow);

        wp_send_json_success([
            'action'  => $action,
            'fields'  => $fields,
            'flow_id' => $flowId,
        ], 200);
    }

    /**
     * Connecting admin + plugin tier, recorded by the bridge for connected-user tracking only
     * (never echoed back in the result payload). Filterable so site owners can drop or alter it.
     *
     * @return array<string, string>
     */
    private static function bridgeUserFields()
    {
        $user = wp_get_current_user();

        $fields = [
            'wp_user_email'   => $user && $user->exists() ? (string) $user->user_email : '',
            'wp_display_name' => $user && $user->exists() ? (string) $user->display_name : '',
            'plugin_tier'     => defined('WPSOCIALREVIEWS_PRO') ? 'pro' : 'free',
        ];

        $fields = apply_filters('wpsocialreviews/auth_bridge_user_fields', $fields, $user);
        if (!is_array($fields)) {
            return [];
        }

        // Only these three keys may reach the bridge: anything else (core auth fields, bridge
        // control fields like launch or marketing_*) would change the connect flow itself.
        $fields = array_intersect_key($fields, array_flip(['wp_user_email', 'wp_display_name', 'plugin_tier']));

        return array_map('strval', array_filter($fields, 'is_scalar'));
    }

    public static function consumeAuth($platform, $settings, callable $persistCallback, callable $formatCallback)
    {
        $payload = Arr::get($settings, 'payload', '');
        $flowId  = Arr::get($settings, 'flow_id', '');

        if (!$payload || !$flowId) {
            wp_send_json_error([
                'message' => __('Missing auth result data.', 'wp-social-reviews')
            ], 423);
        }

        $transientKey = self::transientKey($platform, $flowId);
        $prepared     = get_transient($transientKey);

        if (empty($prepared['verifier']) || Arr::get($prepared, 'platform') !== $platform) {
            wp_send_json_error([
                'message' => __('Auth verifier has expired. Please try connecting again.', 'wp-social-reviews')
            ], 423);
        }

        try {
            $result = self::decryptPayload($payload, $prepared);
            delete_transient($transientKey);

            // Store the refresh credential, then drop the secret so it never lands in platform token configs.
            self::storeBridgeIdentity($result);
            if (is_array($result)) {
                unset($result['site_secret']);
            }

            $result = $persistCallback($result);

            wp_send_json_success([
                'message' => __('Authentication completed successfully.', 'wp-social-reviews'),
                'result'  => $formatCallback($result),
            ], 200);
        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 423);
        }
    }

    private static function decryptPayload($payload, $prepared)
    {
        $resultKey = self::unprotectValue(Arr::get($prepared, 'result_key', ''));

        if (empty($resultKey) || empty($prepared['verifier'])) {
            throw new \RuntimeException(esc_html__('Auth verifier has expired. Please try connecting again.', 'wp-social-reviews'));
        }

        $json   = ServicesHelper::decryptAuthPayload($payload, $resultKey);
        $result = json_decode($json, true);
        if (!is_array($result)) {
            throw new \RuntimeException(esc_html__('Invalid auth payload data.', 'wp-social-reviews'));
        }

        return ServicesHelper::verifyAuthConsumeChallenge($result, $prepared['verifier']);
    }

    private static function transientKey($platform, $flowId)
    {
        return 'wpsr_auth_' . $platform . '_' . get_current_user_id() . '_' . sanitize_key($flowId);
    }

    private static function protectValue($value)
    {
        $protected = (new DataProtector())->encrypt($value);
        return $protected ?: $value;
    }

    private static function unprotectValue($value)
    {
        $unprotected = (new DataProtector())->decrypt($value);
        return $unprotected ?: $value;
    }
}
