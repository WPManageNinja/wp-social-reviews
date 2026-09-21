<?php
/** Child-process probe for literal-die public handlers. Never run directly outside WP-CLI lab. */
if (!defined('WP_CLI') || !WP_CLI || getenv('WPSR_TEST_LAB') !== '1') {
    exit(91);
}
$mode = isset($args[0]) ? (string) $args[0] : '';
$templateId = isset($args[1]) ? (int) $args[1] : 0;
if (!in_array($mode, ['facebook-album-untrusted', 'facebook-album-allowed', 'facebook-album-hidden'], true)) {
    fwrite(STDERR, "Unknown isolated public probe.\n");
    exit(92);
}

wp_set_current_user(0);
$_GET = [];
$_POST = [];
$_REQUEST = [];

// Mint the nonce here rather than in the parent: nonces are user-scoped and the
// parent suite runs as an administrator, so only the child can produce the value
// a logged-out visitor would actually send.
if ($mode !== 'facebook-album-untrusted') {
    $_REQUEST = [
        'template_id' => $templateId,
        'feed_id'     => 'probe-feed',
        'security'    => wp_create_nonce('wpsr-ajax-nonce'),
    ];
    $_POST = $_REQUEST;
}

add_action('wpsocialreviews/facebook_feed_handle_album_photo', function () {
    echo 'WPSR-UNTRUSTED-DELEGATED';
}, PHP_INT_MAX);
do_action('wp_ajax_nopriv_wpsr_facebook_album_photo');

// Every branch of the production handler terminates. Returning means behavior
// changed and parent fingerprint must turn red rather than accepting partial
// child output.
echo 'WPSR-PROBE-RETURNED';
exit(93);
