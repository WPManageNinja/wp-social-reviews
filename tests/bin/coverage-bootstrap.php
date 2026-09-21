<?php
/**
 * PCOV capture bootstrap for one instrumented PHP process.
 *
 * Loaded before WP-CLI so plugin bootstrap and runtime lines are measured.
 */

$wpsr_output_file = getenv('WPSR_PCOV_OUTPUT');
$wpsr_suite_name = getenv('WPSR_PCOV_SUITE');

if (!$wpsr_output_file || !$wpsr_suite_name || !function_exists('\pcov\start')) {
    fwrite(STDERR, "Coverage bootstrap requires PCOV plus WPSR_PCOV_OUTPUT/WPSR_PCOV_SUITE.\n");
    exit(2);
}

\pcov\start();

register_shutdown_function(static function () use ($wpsr_output_file, $wpsr_suite_name) {
    \pcov\stop();

    $wpsr_capture = [
        'format' => 1,
        'suite'  => $wpsr_suite_name,
        'data'   => \pcov\collect(),
    ];

    $wpsr_directory = dirname($wpsr_output_file);
    if (!is_dir($wpsr_directory)
        && !mkdir($wpsr_directory, 0777, true)
        && !is_dir($wpsr_directory)) {
        fwrite(STDERR, 'Could not create PCOV capture directory: ' . $wpsr_directory . "\n");
        return;
    }

    if (file_put_contents($wpsr_output_file, serialize($wpsr_capture), LOCK_EX) === false) {
        fwrite(STDERR, 'Could not write PCOV capture for ' . $wpsr_suite_name . ".\n");
    }
});
