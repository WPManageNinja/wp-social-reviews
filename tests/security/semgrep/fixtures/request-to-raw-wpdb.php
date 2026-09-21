<?php
function unsafe_database($wpdb) {
    $wpsr_order = $_GET['order'];
    // ruleid: wpsr.php.request-to-raw-wpdb
    return $wpdb->get_results("SELECT * FROM rows ORDER BY " . $wpsr_order);
}

function unsafe_sanitized_text_database($wpdb) {
    $wpsr_order = sanitize_text_field($_GET['order']);
    // ruleid: wpsr.php.request-to-raw-wpdb
    return $wpdb->get_results("SELECT * FROM rows ORDER BY " . $wpsr_order);
}

function unsafe_escaped_database($wpdb) {
    $wpsr_order = esc_sql($_GET['order']);
    // ruleid: wpsr.php.request-to-raw-wpdb
    return $wpdb->get_results("SELECT * FROM rows ORDER BY " . $wpsr_order);
}

function safe_database($wpdb) {
    $wpsr_id = $_GET['id'];
    // ok: wpsr.php.request-to-raw-wpdb
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM rows WHERE id = %d', $wpsr_id));
}
