<?php
function unsafe_redirect() {
    $wpsr_return = $_GET['return_url'];
    // ruleid: wpsr.php.request-to-unsafe-redirect
    wp_redirect($wpsr_return);
}

function unsafe_escaped_redirect() {
    $wpsr_return = esc_url_raw($_GET['return_url']);
    // ruleid: wpsr.php.request-to-unsafe-redirect
    wp_redirect($wpsr_return);
}

function safe_validated_redirect() {
    $wpsr_return = wp_validate_redirect($_GET['return_url'], '/');
    // ok: wpsr.php.request-to-unsafe-redirect
    wp_redirect($wpsr_return);
}

function safe_redirect_sink() {
    $wpsr_return = $_GET['return_url'];
    // ok: wpsr.php.request-to-unsafe-redirect
    wp_safe_redirect($wpsr_return);
}
