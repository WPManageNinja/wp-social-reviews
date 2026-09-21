<?php
function unsafe_output() {
    $wpsr_title = $_REQUEST['title'];
    // ruleid: wpsr.php.request-to-html-output
    echo $wpsr_title;
}

function safe_output() {
    $wpsr_title = $_REQUEST['title'];
    // ok: wpsr.php.request-to-html-output
    echo esc_html($wpsr_title);
}
