<?php
/** WHAT: bridge site secret is protected at rest, non-autoloaded, and stripped before platform persistence. Production: RemoteAuth::storeBridgeIdentity and consumeAuth. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('remote-auth-secret-storage', $argv);
try {
    $store = $wpsr->phpMethod('app/Services/Platforms/RemoteAuth.php', 'storeBridgeIdentity');
    $wpsr->requirePattern($store, '/[\'\"]site_secret[\'\"]\s*=>\s*self::protectValue\s*\(\s*\$siteSecret\s*\)/', 'stored bridge secret must pass through DataProtector seam');
    $wpsr->requirePattern($store, '/update_option\s*\(\s*self::IDENTITY_OPTION\s*,.*?,\s*[\'\"]no[\'\"]\s*\)/s', 'bridge identity option must disable autoload');

    $consume = $wpsr->phpMethod('app/Services/Platforms/RemoteAuth.php', 'consumeAuth');
    $wpsr->requireBefore($consume, '/storeBridgeIdentity\s*\(\s*\$result\s*\)/', '/unset\s*\(\s*\$result\s*\[\s*[\'\"]site_secret[\'\"]\s*\]\s*\)/', 'identity must be stored before secret is removed from result');
    $wpsr->requireBefore($consume, '/unset\s*\(\s*\$result\s*\[\s*[\'\"]site_secret[\'\"]\s*\]\s*\)/', '/\$persistCallback\s*\(\s*\$result\s*\)/', 'site secret must be stripped before platform callback');
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
