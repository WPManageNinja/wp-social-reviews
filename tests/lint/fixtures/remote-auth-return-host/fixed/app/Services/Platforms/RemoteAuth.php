<?php
/** Minimal faithful Phase 24 copy from app/Services/Platforms/RemoteAuth.php. */
class Phase24Fixture
{
    function prepareAuth($platform, $provider, $flow, $settings = [])
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

        $query = build_query([
            'return_url'      => $returnUrl,
            'auth_flow_id'    => $flowId,
            'auth_challenge'  => $challenge,
            'auth_result_key' => $resultKey,
            'source'          => $source,
        ]);
        $url = ServicesHelper::authBaseUrl() . '/connect/' . rawurlencode($provider) . '/' . rawurlencode($flow) . '?' . $query;

        wp_send_json_success([
            'url'     => $url,
            'flow_id' => $flowId,
        ], 200);
    }
}
