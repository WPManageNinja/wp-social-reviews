<?php
function wpsr_unsafe_decode_then_unslash($wpsr_raw) {
    // ruleid: wpsr.php.unslash-around-json-decode
    $wpsr_settings = json_decode($wpsr_raw, true);
    $wpsr_settings = wp_unslash($wpsr_settings);
    return $wpsr_settings;
}

function wpsr_unsafe_unslash_then_decode($wpsr_raw) {
    // ruleid: wpsr.php.unslash-around-json-decode
    $wpsr_raw = wp_unslash($wpsr_raw);
    $wpsr_decoded = json_decode($wpsr_raw, true);
    return $wpsr_decoded;
}

function wpsr_unsafe_inline_unslash($wpsr_raw) {
    // ruleid: wpsr.php.unslash-around-json-decode
    return json_decode(wp_unslash($wpsr_raw), true);
}

function wpsr_safe_decode($wpsr_raw) {
    // ok: wpsr.php.unslash-around-json-decode
    $wpsr_decoded = json_decode($wpsr_raw, true);
    return is_array($wpsr_decoded) ? $wpsr_decoded : null;
}

function wpsr_safe_superglobal_inline() {
    // ok: wpsr.php.unslash-around-json-decode
    return json_decode(wp_unslash($_POST['wpsr_payload']), true);
}

function wpsr_safe_superglobal_two_step() {
    // ok: wpsr.php.unslash-around-json-decode
    $wpsr_raw = wp_unslash($_REQUEST['wpsr_payload']);
    $wpsr_decoded = json_decode($wpsr_raw, true);
    return $wpsr_decoded;
}
