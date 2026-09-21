<?php
/** WHAT: scraped Twitter-card metadata must reach DOM as text/attributes with http(s)-only URLs, never concatenated HTML. Production: twitter-card.js::wpsrAddTwitterCards/wpsrSafeUrl. */
require_once __DIR__ . '/source-contract-lib.php';

$wpsr = wpsr_source_contract_start('twitter-card-dom-sinks', $argv);
try {
    $safeUrl = $wpsr->jsFunction('resources/public/twitter-card.js', 'wpsrSafeUrl');
    $wpsr->requirePattern($safeUrl, '/protocol\s*===\s*[\'\"]http:[\'\"]\s*\|\|\s*protocol\s*===\s*[\'\"]https:[\'\"]/', 'URL sink allowlist must remain exact http/https');

    $slice = $wpsr->jsFunction('resources/public/twitter-card.js', 'wpsrAddTwitterCards');
    $wpsr->requirePattern($slice, '/\$\(\s*[\'\"]<a>[\'\"]\s*,/', 'card root must be created as DOM node');
    $wpsr->requirePattern($slice, '/wpsrSafeUrl\s*\(/', 'href and image URLs must pass safe URL helper', 2);
    $wpsr->requirePattern($slice, '/\.text\s*\(/', 'title, description, and domain must use text sinks', 3);
    $wpsr->requirePattern($slice, '/\.append\s*\(\s*\$card\s*\)/', 'completed DOM node must be appended as object');
    $wpsr->forbidPattern($slice, '/\.append\s*\(\s*[\'\"]<a/', 'attacker metadata must not be concatenated into card HTML');
    $wpsr->forbidPattern($slice, '/href\s*=\s*[\'\"]\s*\+/', 'attacker URL must not be concatenated into href markup');
} catch (Throwable $throwable) {
    $wpsr->fatal($throwable);
}
$wpsr->finish();
