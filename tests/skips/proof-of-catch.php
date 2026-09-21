<?php
/** Direct adversarial proofs for strict runtime SKIP event parser and registry reconciliation. */
require_once __DIR__ . '/skip-lib.php';

$wpsr_root = dirname(__DIR__, 2);
$wpsr_registry = wpsr_skip_load(__DIR__ . '/registry.json');
$wpsr_rows = wpsr_skip_rows_by_id($wpsr_registry);
$wpsr_row = reset($wpsr_rows);
$wpsr_temp = tempnam(sys_get_temp_dir(), 'wpsr-skip-proof-');
$wpsr_cases = 0;
$wpsr_red = 0;
$wpsr_failures = 0;

function wpsr_skip_proof_record($wpsr_name, $wpsr_ok)
{
    global $wpsr_cases, $wpsr_red, $wpsr_failures;
    $wpsr_cases++;
    if ($wpsr_ok) {
        $wpsr_red++;
        echo 'PROOF ' . $wpsr_name . ": RED expected\n";
    } else {
        $wpsr_failures++;
        echo 'PROOF ' . $wpsr_name . ": FAIL\n";
    }
}

function wpsr_skip_proof_has(array $wpsr_errors, $wpsr_fragment)
{
    foreach ($wpsr_errors as $wpsr_error) {
        if (strpos($wpsr_error, $wpsr_fragment) !== false) {
            return true;
        }
    }
    return false;
}

try {
    if (!is_array($wpsr_row) || $wpsr_temp === false) {
        throw new RuntimeException('proof fixture unavailable');
    }
    $wpsr_event = [];
    foreach (['id', 'owner_plugin', 'suite', 'test', 'reason_category', 'reason', 'evidence', 'expires_on', 'revisit'] as $wpsr_key) {
        $wpsr_event[$wpsr_key] = $wpsr_row[$wpsr_key];
    }
    $wpsr_green = wpsr_skip_validate($wpsr_registry, $wpsr_root, [$wpsr_event]);
    if ($wpsr_green) {
        throw new RuntimeException('valid runtime event rejected: ' . implode('; ', $wpsr_green));
    }

    foreach ([
        'malformed-json' => '{broken}',
        'null-json' => 'null',
        'object-instead-of-array' => '{}',
    ] as $wpsr_name => $wpsr_source) {
        file_put_contents($wpsr_temp, $wpsr_source);
        $wpsr_caught = false;
        try {
            wpsr_skip_load_runtime_events($wpsr_temp);
        } catch (Throwable $wpsr_exception) {
            $wpsr_caught = strpos($wpsr_exception->getMessage(), 'JSON array') !== false;
        }
        wpsr_skip_proof_record($wpsr_name, $wpsr_caught);
    }

    $wpsr_partial = $wpsr_event;
    unset($wpsr_partial['revisit']);
    wpsr_skip_proof_record('partial-event', wpsr_skip_proof_has(wpsr_skip_validate($wpsr_registry, $wpsr_root, [$wpsr_partial]), 'keys mismatch'));

    $wpsr_extra = $wpsr_event;
    $wpsr_extra['foreign'] = true;
    wpsr_skip_proof_record('unknown-event-key', wpsr_skip_proof_has(wpsr_skip_validate($wpsr_registry, $wpsr_root, [$wpsr_extra]), 'keys mismatch'));

    wpsr_skip_proof_record('duplicate-event', wpsr_skip_proof_has(wpsr_skip_validate($wpsr_registry, $wpsr_root, [$wpsr_event, $wpsr_event]), 'duplicate runtime skip'));

    foreach (['owner_plugin', 'suite', 'test', 'reason_category', 'reason', 'evidence', 'expires_on', 'revisit'] as $wpsr_field) {
        $wpsr_mutant = $wpsr_event;
        $wpsr_mutant[$wpsr_field] = $wpsr_field === 'evidence' ? ['type' => 'foreign'] : 'foreign';
        wpsr_skip_proof_record('metadata-' . str_replace('_', '-', $wpsr_field), wpsr_skip_proof_has(
            wpsr_skip_validate($wpsr_registry, $wpsr_root, [$wpsr_mutant]),
            'runtime ' . $wpsr_field . ' mismatch'
        ));
    }
} catch (Throwable $wpsr_exception) {
    $wpsr_failures++;
    fwrite(STDERR, 'SKIP-PROOF bootstrap failure: ' . $wpsr_exception->getMessage() . "\n");
} finally {
    if (is_string($wpsr_temp) && is_file($wpsr_temp)) {
        unlink($wpsr_temp);
    }
}
printf("SKIP-PROOF cases=%d red=%d failures=%d\n", $wpsr_cases, $wpsr_red, $wpsr_failures);
exit($wpsr_failures ? WPSR_SKIP_FAIL : WPSR_SKIP_PASS);
