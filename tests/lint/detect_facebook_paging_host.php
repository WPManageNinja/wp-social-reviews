<?php
/** WHAT: attacker-controlled Facebook paging cursors may only be followed on graph.facebook.com. Production: Helper::fetchFacebookManagedPages. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('facebook-paging-host', $argv);
try {
    $slice = $wpsr->phpMethod('app/Services/Helper.php', 'fetchFacebookManagedPages');
    $wpsr->requirePattern($slice, '/wp_parse_url\s*\(\s*\$nextUrl\s*,\s*PHP_URL_HOST\s*\)\s*\)\s*!==\s*[\'\"]graph\.facebook\.com[\'\"]/', 'paging URL host must equal Graph host');
    $wpsr->requirePattern($slice, '/if\s*\(\s*\$nextUrl\s*&&.*?graph\.facebook\.com.*?\)\s*\{\s*\$nextUrl\s*=\s*null\s*;/s', 'foreign paging cursor must be discarded fail-closed');
    $wpsr->requireBefore($slice, '/wp_parse_url\s*\(\s*\$nextUrl/', '/\}\s*while\s*\(\s*\$nextUrl\s*\)/', 'host validation must occur before pagination continues');
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
