<?php
/** Refuse every DAST destination except WordPress loopback in shared network namespace. */

$target = $argv[1] ?? '';
$expected = 'http://127.0.0.1:80';
$parts = parse_url($target);
$valid = $target === $expected
    && is_array($parts)
    && ($parts['scheme'] ?? '') === 'http'
    && ($parts['host'] ?? '') === '127.0.0.1'
    && (int) ($parts['port'] ?? 0) === 80
    && empty($parts['user'])
    && empty($parts['pass'])
    && empty($parts['query'])
    && empty($parts['fragment'])
    && empty($parts['path']);

if (!$valid) {
    fwrite(STDERR, "DAST TARGET REFUSED: expected {$expected}; got " . var_export($target, true) . "\n");
    exit(64);
}

echo "DAST TARGET ACCEPTED: {$expected}\n";
