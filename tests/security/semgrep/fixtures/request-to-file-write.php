<?php
function unsafe_file_write() {
    $wpsr_path = $_POST['path'];
    // ruleid: wpsr.php.request-to-file-write
    file_put_contents($wpsr_path, 'fixture');
}

function safe_file_write($wpsr_root) {
    $wpsr_name = sanitize_file_name($_POST['path']);
    // ok: wpsr.php.request-to-file-write
    file_put_contents($wpsr_root . '/' . $wpsr_name, 'fixture');
}
