<?php
/** Phase 28 authoritative product-family census lint. */

require_once __DIR__ . '/census-lib.php';

$wpsr_options = [
    'registry' => __DIR__ . '/plugins.json',
    'host-root' => dirname(__DIR__, 2),
    'family-root' => null,
    'use-env' => true,
];
foreach (array_slice($argv, 1) as $wpsr_argument) {
    if (strpos($wpsr_argument, '--registry=') === 0) {
        $wpsr_options['registry'] = substr($wpsr_argument, 11);
    } elseif (strpos($wpsr_argument, '--host-root=') === 0) {
        $wpsr_options['host-root'] = substr($wpsr_argument, 12);
    } elseif (strpos($wpsr_argument, '--family-root=') === 0) {
        $wpsr_options['family-root'] = substr($wpsr_argument, 14);
    } elseif ($wpsr_argument === '--ignore-env') {
        $wpsr_options['use-env'] = false;
    } else {
        fwrite(STDERR, 'FAMILY-CENSUS misuse: ' . $wpsr_argument . "\n");
        exit(WPSR_FAMILY_MISUSE);
    }
}

try {
    $wpsr_registry = wpsr_family_load_json($wpsr_options['registry']);
    $wpsr_result = wpsr_family_validate(
        $wpsr_registry,
        wpsr_family_normalize_path($wpsr_options['host-root']),
        $wpsr_options['family-root'],
        $wpsr_options['use-env'],
        dirname($wpsr_options['registry'])
    );
} catch (Throwable $wpsr_exception) {
    fwrite(STDERR, 'FAMILY-CENSUS FAIL ' . $wpsr_exception->getMessage() . "\n");
    exit(WPSR_FAMILY_FAIL);
}

foreach ($wpsr_result['metadata'] as $wpsr_row) {
    printf(
        "FAMILY-CENSUS PLUGIN id=%s role=%s slug=%s version=%s runtime=%s selector=%s branch=%s commit=%s path=%s\n",
        $wpsr_row['id'],
        $wpsr_row['role'],
        $wpsr_row['slug'],
        $wpsr_row['version'],
        $wpsr_row['runtime'],
        $wpsr_row['selector'],
        $wpsr_row['branch'],
        $wpsr_row['commit'],
        $wpsr_row['path']
    );
}
foreach ($wpsr_result['skips'] as $wpsr_skip) {
    printf(
        getenv('WPSR_FAMILY_SWEEP_CHILD') === '1'
            ? "FAMILY-CENSUS UNAVAILABLE id=%s owner=%s slug=%s reason=%s path=%s\n"
            : "FAMILY-CENSUS SKIP id=%s owner=%s slug=%s reason=%s path=%s\n",
        $wpsr_skip['id'],
        $wpsr_skip['owner'],
        $wpsr_skip['slug'],
        $wpsr_skip['code'],
        $wpsr_skip['path']
    );
}
foreach ($wpsr_result['ignored'] ?? [] as $wpsr_ignored_path) {
    printf("FAMILY-CENSUS IGNORED path=%s source=tests/family/ignored-neighbours.txt\n", $wpsr_ignored_path);
}
foreach ($wpsr_result['errors'] as $wpsr_error) {
    fwrite(STDERR, 'FAMILY-CENSUS FAIL ' . $wpsr_error . "\n");
}

$wpsr_stats = $wpsr_result['stats'];
printf(
    "FAMILY-CENSUS candidates=%d classified=%d in-family=%d out-of-family=%d available=%d skipped=%d failures=%d\n",
    $wpsr_stats['candidates'] ?? 0,
    $wpsr_stats['classified'] ?? 0,
    $wpsr_stats['in_family'] ?? 0,
    $wpsr_stats['out_of_family'] ?? 0,
    $wpsr_stats['available'] ?? 0,
    $wpsr_stats['skipped'] ?? 0,
    count($wpsr_result['errors'])
);
exit($wpsr_result['errors'] ? WPSR_FAMILY_FAIL : WPSR_FAMILY_PASS);
