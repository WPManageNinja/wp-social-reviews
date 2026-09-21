<?php
function unsafe_state_change() {
    $wpsr_value = $_REQUEST['setting'];
    // ruleid: wpsr.php.request-state-change-without-guard
    update_option('wpsr_fixture_setting', $wpsr_value, 'no');
}

function safe_state_change() {
    check_ajax_referer('wpsr-ajax-nonce', 'security');
    $wpsr_value = $_REQUEST['setting'];
    // ok: wpsr.php.request-state-change-without-guard
    update_option('wpsr_fixture_setting', $wpsr_value, 'no');
}
