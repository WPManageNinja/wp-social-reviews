<?php
/**
 * Plugin Name: WP Social Ninja Test Lab Outbound Guard
 * Description: Blocks and audits server-side HTTP and mail in disposable suite lab.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPSR_TEST_LAB_OUTBOUND_GUARD', true);

function wpsr_test_lab_record_outbound($type, $target)
{
    $row = wp_json_encode([
        'type' => (string) $type,
        'target' => (string) $target,
        'time' => gmdate('c'),
    ]);
    if ($row === false || file_put_contents('/tmp/wpsr-test-lab-outbound.log', $row . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Disposable lab outbound audit log is not writable.');
    }
}

function wpsr_test_lab_block_http($preempt, $args, $url)
{
    wpsr_test_lab_record_outbound('http', $url);
    return new WP_Error('wpsr_lab_http_blocked', 'Outbound HTTP blocked by disposable test lab.');
}

function wpsr_test_lab_block_mail($preempt, $attributes)
{
    $target = is_array($attributes) && isset($attributes['to']) ? wp_json_encode($attributes['to']) : '';
    wpsr_test_lab_record_outbound('mail', $target);
    return true;
}

add_filter('pre_http_request', 'wpsr_test_lab_block_http', PHP_INT_MAX, 3);
add_filter('pre_wp_mail', 'wpsr_test_lab_block_mail', PHP_INT_MAX, 2);
