<?php
/** Minimal faithful Phase 24 copy from app/Services/Platforms/RemoteAuth.php. */
class Phase24Fixture
{
    function refreshAuth($provider, $flow, $refreshToken)
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

        // Per-site credential issued by the bridge during the connect handshake. There is no
        // global shipped secret to leak — each install signs with its own key.
        $identity   = self::bridgeIdentity();
        $siteId     = (string) Arr::get($identity, 'site_id', '');
        $siteSecret = (string) Arr::get($identity, 'site_secret', '');

        if (empty($siteId) || empty($siteSecret)) {
            // Not provisioned yet (e.g. connected before per-site auth existed, or the option was
            // cleared). Reconnecting re-establishes the identity. Never send an unsigned request.
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
                // Identifies which per-site key the bridge derives to verify the signature.
                // Public identifier, not a secret — the secret never leaves this site.
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
}
