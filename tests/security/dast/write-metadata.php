<?php
/** Write machine-readable scanner provenance beside each DAST result. */

if ($argc !== 8) {
    fwrite(STDERR, "metadata arguments invalid\n");
    exit(2);
}
list(, $scanner, $version, $image, $digest, $target, $started, $output) = $argv;
$files = [
    __DIR__ . '/allowlist.json',
    __DIR__ . '/run-' . $scanner . '.sh',
];
if ($scanner === 'zap') {
    $files[] = __DIR__ . '/zap-rules.conf';
}
$config = [];
foreach ($files as $file) {
    if (is_file($file)) {
        $config[basename($file)] = hash_file('sha256', $file);
    }
}
if ($scanner === 'nuclei') {
    foreach (glob(__DIR__ . '/nuclei-templates/*.yaml') ?: [] as $file) {
        $config['nuclei-templates/' . basename($file)] = hash_file('sha256', $file);
    }
}
ksort($config);
$metadata = [
    'schema' => 1,
    'scanner' => $scanner,
    'version' => trim($version),
    'image' => $image,
    'image_digest' => $digest,
    'target' => $target,
    'profile' => $scanner === 'zap' ? 'passive-baseline' : 'committed-safe-http-templates',
    'authenticated' => false,
    'started_at' => $started,
    'finished_at' => gmdate('c'),
    'config_sha256' => $config,
];
file_put_contents($output, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
