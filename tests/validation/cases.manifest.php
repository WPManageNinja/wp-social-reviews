<?php
/** Phase 10 behavior-case registry. Integration test must register every ID exactly once. */
return [
    'query-shaping' => 'sort allowlists, safe fallback, SELECT-shaped tokens',
    'like-wildcards' => 'literal LIKE wildcard escaping',
    'pagination-bounds' => 'integer conversion and lower bounds',
    'known-negative-id-gap' => 'parked negative include ID normalization gap',
    'review-input-output' => 'typed review input, markup, URLs, email, rendered escaping',
    'nested-sanitizers' => 'backend sanitizer maps, colors, styles, URLs, nested settings',
    'enum-boundaries' => 'action/status allowlists and safe fallback',
    'limited-capability-scope' => 'cross-review/testimonial IDs and category policy',
    'cross-object-scope' => 'wrong post-type IDs remain unchanged',
    'known-admin-like-gap' => 'parked admin-table LIKE wildcard escaping gap',
    'known-settings-policy-gap' => 'parked coarse SettingsPolicy capability gap',
    'known-template-object-gap' => 'parked template destructive cross-screen object gap',
    'known-shoppable-delete-gap' => 'parked shoppable delete sanitization gap',
];
