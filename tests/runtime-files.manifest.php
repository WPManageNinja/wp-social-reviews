<?php
/** Exact runtime file census. Runners reject missing, new, or silently omitted files. */
return [
    'integration' => [
        'background-automation.php',
        'coverage-gaps.php',
        'domain-invariants.php',
        'input-validation-authorization.php',
        'mutating-route-round-trips.php',
        'remote-media-fetch.php',
        'reports-and-aggregates.php',
        'review-filtering.php',
        'review-status-transitions.php',
        'shared-table-discriminators.php',
    ],
    'pure' => [
        'deterministic-helpers.php',
        'remote-media-url.php',
    ],
    'public' => [
        'public-handlers.php',
    ],
    'public_support' => [
        'handler-coverage.php',
        'handlers.manifest.php',
    ],
];
