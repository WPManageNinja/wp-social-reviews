<?php
/** WHAT: refresh requests are allowlisted and signed over exact canonical body bytes; unsigned requests never dispatch. Production: RemoteAuth::refreshAuth. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('remote-auth-hmac', $argv);
try {
    $slice = $wpsr->phpMethod('app/Services/Platforms/RemoteAuth.php', 'refreshAuth');
    $wpsr->requireBefore($slice, '/in_array\s*\(\s*\$pair\s*,\s*self::SUPPORTED_REFRESH_FLOWS\s*,\s*true\s*\)/', '/wp_remote_post\s*\(/', 'provider/flow allowlist must run before transport');
    $wpsr->requireBefore($slice, '/empty\s*\(\s*\$siteId\s*\)\s*\|\|\s*empty\s*\(\s*\$siteSecret\s*\)/', '/hash_hmac\s*\(/', 'missing per-site identity must fail before signing/transport');
    $wpsr->requirePattern($slice, '/hash_hmac\s*\(\s*[\'\"]sha256[\'\"]\s*,\s*\$timestamp\s*\.\s*[\'\"]\.[\'\"]\s*\.\s*\$canonical\s*,\s*\$siteSecret\s*\)/', 'signature must cover timestamp dot exact canonical body');
    $wpsr->requirePattern($slice, '/[\'\"]X-WPSN-Signature[\'\"]\s*=>\s*\$signature/', 'HMAC signature must be sent in bridge header');
    $wpsr->requirePattern($slice, '/[\'\"]body[\'\"]\s*=>\s*\$canonical/', 'transport body must reuse signed canonical bytes');
    $wpsr->requireBefore($slice, '/hash_hmac\s*\(/', '/wp_remote_post\s*\(/', 'signature must be computed before bridge dispatch');
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
