<?php
/** WHAT: OAuth prepare accepts return URLs only from current site's host before state/transient creation. Production: RemoteAuth::prepareAuth. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('remote-auth-return-host', $argv);
try {
    $slice = $wpsr->phpMethod('app/Services/Platforms/RemoteAuth.php', 'prepareAuth');
    $wpsr->requirePattern($slice, '/\$siteHost\s*=\s*wp_parse_url\s*\(\s*home_url\s*\(\s*\)\s*,\s*PHP_URL_HOST\s*\)/', 'site host must come from parsed home URL');
    $wpsr->requirePattern($slice, '/\$returnHost\s*=\s*wp_parse_url\s*\(\s*\$returnUrl\s*,\s*PHP_URL_HOST\s*\)/', 'return host must be parsed as host, not prefix-matched');
    $wpsr->requirePattern($slice, '/!\s*\$returnHost\s*\|\|\s*strtolower\s*\(\s*\$returnHost\s*\)\s*!==\s*strtolower\s*\(\s*\$siteHost\s*\)/', 'missing or cross-host return URL must fail closed');
    $wpsr->requireBefore($slice, '/\$siteHost\s*=/', '/set_transient\s*\(/', 'return-host validation must precede auth state creation');
    $wpsr->requireBefore($slice, '/\$siteHost\s*=/', '/build_query\s*\(/', 'return-host validation must precede bridge redirect construction');
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
