<?php
/** Minimal faithful Phase 24 copy from app/Services/Platforms/Reviews/GoogleMyBusiness.php. */
class GoogleMyBusinessFixture
{
    private $clientId = '';
    private $clientSecret = '';

    function refreshToken($tokens)
    {
        // Refresh through the wpsn-auth bridge — the OAuth client secret now lives only there.
        return RemoteAuth::refreshAuth('google', 'reviews', Arr::get($tokens, 'refresh_token'));
    }
}
