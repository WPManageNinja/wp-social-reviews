<?php
/** Validate one fresh WP-CLI runner transcript. Exit nonzero on partial, stale, or malformed success. */
if (PHP_SAPI !== 'cli') {
    exit(2);
}

$path = isset($argv[1]) ? (string) $argv[1] : '';
if ($path === '' || !is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "suite-output: transcript missing or unreadable.\n");
    exit(1);
}

$lines = file($path, FILE_IGNORE_NEW_LINES);
if (!is_array($lines)) {
    fwrite(STDERR, "suite-output: transcript read failed.\n");
    exit(1);
}
$lines = array_map(function ($line) {
    return rtrim((string) $line, "\r");
}, $lines);
$nonempty = array_values(array_filter($lines, function ($line) {
    return trim($line) !== '';
}));
$summaryLines = array_values(array_filter($lines, function ($line) {
    return strpos($line, 'WPSR-SUITE-JSON ') === 0;
}));
$terminalLines = array_values(array_filter($lines, function ($line) {
    return strpos($line, 'WPSR-SUITE-END ') === 0;
}));

$fail = function ($message) {
    fwrite(STDERR, 'suite-output: ' . $message . "\n");
    exit(1);
};
if (count($summaryLines) !== 1) {
    $fail('expected exactly one suite summary, found ' . count($summaryLines) . '.');
}
if (count($terminalLines) !== 1) {
    $fail('expected exactly one terminal marker, found ' . count($terminalLines) . '.');
}

$json = substr($summaryLines[0], strlen('WPSR-SUITE-JSON '));
$summary = json_decode($json, true);
if (!is_array($summary) || json_last_error() !== JSON_ERROR_NONE) {
    $fail('suite summary is malformed JSON.');
}
foreach (['suite', 'pass', 'fail', 'skip', 'total', 'assertion_failures', 'runtime', 'skip_ids'] as $key) {
    if (!array_key_exists($key, $summary)) {
        $fail('suite summary missing ' . $key . '.');
    }
}
if (!is_string($summary['suite']) || !preg_match('/^[A-Z][A-Z0-9_-]*$/', $summary['suite'])) {
    $fail('suite identifier is malformed.');
}
foreach (['pass', 'fail', 'skip', 'total', 'assertion_failures'] as $key) {
    if (!is_int($summary[$key]) || $summary[$key] < 0) {
        $fail($key . ' must be a nonnegative integer.');
    }
}
if ((!is_int($summary['runtime']) && !is_float($summary['runtime'])) || $summary['runtime'] < 0) {
    $fail('runtime must be nonnegative numeric data.');
}
if (!is_array($summary['skip_ids'])) {
    $fail('skip_ids must be an array.');
}
if ($summary['total'] <= 0) {
    $fail('zero-test success is forbidden.');
}
if ($summary['total'] !== $summary['pass'] + $summary['fail'] + $summary['skip']) {
    $fail('total does not equal pass + fail + skip.');
}
if ($summary['fail'] !== 0 || $summary['assertion_failures'] !== 0) {
    $fail('successful child transcript reports failures.');
}
if (count($summary['skip_ids']) !== $summary['skip']
    || count($summary['skip_ids']) !== count(array_unique($summary['skip_ids']))) {
    $fail('skip count/IDs are inconsistent.');
}
foreach ($summary['skip_ids'] as $id) {
    if (!is_string($id) || $id === '') {
        $fail('skip_ids contains a malformed value.');
    }
}

$terminal = 'WPSR-SUITE-END ' . $summary['suite'] . ' PASS';
if ($terminalLines[0] !== $terminal) {
    $fail('terminal marker does not match suite summary.');
}
if (!$nonempty || end($nonempty) !== $terminal) {
    $fail('terminal marker is not final output; transcript is partial or has stale trailing data.');
}

echo 'suite-output: valid ' . $summary['suite'] . ' summary (' . $summary['total'] . " cases).\n";
