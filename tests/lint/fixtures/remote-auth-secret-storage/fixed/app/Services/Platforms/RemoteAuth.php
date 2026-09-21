<?php
/** Minimal faithful Phase 24 copy from app/Services/Platforms/RemoteAuth.php. */
class Phase24Fixture
{
    function storeBridgeIdentity($result)
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

    function consumeAuth($platform, $settings, callable $persistCallback, callable $formatCallback)
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

            // Capture the per-site refresh credential the bridge issues alongside the token, then
            // drop the secret so it never leaks into platform-specific token configs.
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
}
