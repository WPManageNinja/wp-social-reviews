<?php
/** WHAT: untrusted feed HTML must parse inside inert template content so resource handlers cannot fire before sanitization. Production: resources/public/helper.js::sanitizeHtml. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('inert-feed-html', $argv);
try {
    $slice = $wpsr->jsFunction('resources/public/helper.js', 'sanitizeHtml');
    $wpsr->requirePattern($slice, '/document\.createElement\s*\(\s*[\'\"]template[\'\"]\s*\)/', 'untrusted HTML parser must use inert template element');
    $wpsr->requirePattern($slice, '/temp\.content\.childNodes/', 'sanitizer must walk inert template content');
    $wpsr->forbidPattern($slice, '/document\.createElement\s*\(\s*[\'\"]div[\'\"]\s*\)/', 'live div parsing is forbidden in sanitizeHtml');
    $wpsr->requireBefore($slice, '/document\.createElement\s*\(\s*[\'\"]template[\'\"]/', '/temp\.innerHTML\s*=\s*html/', 'inert container must exist before untrusted parse');

    $admin = $wpsr->jsFunction('resources/admin/WPSocialReviews.js', 'sanitizeHtml');
    $wpsr->requirePattern($admin, '/document\.createElement\s*\(\s*[\'\"]template[\'\"]\s*\)/', 'admin mirror must use inert template element');
    $wpsr->requirePattern($admin, '/temp\.content\.childNodes/', 'admin mirror must walk inert template content');
    $wpsr->forbidPattern($admin, '/document\.createElement\s*\(\s*[\'\"]div[\'\"]\s*\)/', 'live div parsing is forbidden in the admin mirror');
    $wpsr->requireBefore($admin, '/document\.createElement\s*\(\s*[\'\"]template[\'\"]/', '/temp\.innerHTML\s*=\s*html/', 'admin inert container must exist before untrusted parse');
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
