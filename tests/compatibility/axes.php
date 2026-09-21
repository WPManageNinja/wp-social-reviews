<?php
/**
 * Phase 20 hostile-environment matrix. Keep IDs stable: launchers and canaries
 * reject unknown or empty selections.
 */
return [
    'strict-sql' => [
        'label' => 'strict SQL modes',
        'sql_mode' => 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES',
    ],
    'non-utc' => [
        'label' => 'non-UTC site timezone',
        'timezone_string' => 'Asia/Kolkata',
        'gmt_offset' => '5.5',
        'offset_seconds' => 19800,
    ],
    'free-alone' => [
        'label' => 'core/free without Pro',
        'pro_endpoint' => '/wpsocialreviews/v2/pro/settings/managers',
    ],
];
