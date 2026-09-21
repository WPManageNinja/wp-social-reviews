<?php
/** WHAT: distributed core must not ship Google OAuth client credentials; refresh stays delegated to authenticated bridge. Production: YouTube OAuth + GoogleMyBusiness. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('no-shipped-oauth-secrets', $argv);
try {
    $files = [
        'app/Services/Platforms/Feeds/Youtube/OAuth.php' => ['youtube', '/RemoteAuth::refreshAuth\s*\(\s*[\'\"]google[\'\"]\s*,\s*[\'\"]youtube[\'\"]/'],
        'app/Services/Platforms/Reviews/GoogleMyBusiness.php' => ['reviews', '/RemoteAuth::refreshAuth\s*\(\s*[\'\"]google[\'\"]\s*,\s*[\'\"]reviews[\'\"]/'],
    ];
    foreach ($files as $file => $contract) {
        $source = $wpsr->source($file);
        $wpsr->requirePattern($source, '/private\s+\$clientId\s*=\s*[\'\"]{2}\s*;/', $contract[0] . ' shipped client ID must remain blank');
        $wpsr->requirePattern($source, '/private\s+\$clientSecret\s*=\s*[\'\"]{2}\s*;/', $contract[0] . ' shipped client secret must remain blank');
        $wpsr->forbidPattern($source, '/GOCSPX-|[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com/i', $contract[0] . ' source must contain no embedded Google OAuth credential');
        $refresh = $wpsr->phpMethod($file, 'refreshToken');
        $wpsr->requirePattern($refresh, $contract[1], $contract[0] . ' refresh must use bridge instead of local client secret');
    }
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
