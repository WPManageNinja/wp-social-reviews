<?php
/** Prior behavioral cases allowed to satisfy covered-prior route dispositions. */
return [
    'phase5-review-status' => [
        'file' => 'tests/integration/review-status-transitions.php',
        'test' => 'Phase 5 [phase5-review-status] review routes persist legitimate transitions without crossing scope',
        'routes' => ['/reviews/status-update', '/reviews/spam'],
    ],
    'phase5-testimonial-status' => [
        'file' => 'tests/integration/review-status-transitions.php',
        'test' => 'Phase 5 [phase5-testimonial-status] testimonial routes persist legitimate transitions without crossing scope',
        'routes' => ['/testimonials/status-update', '/testimonials/spam'],
    ],
    'phase6-category-scope' => [
        'file' => 'tests/integration/shared-table-discriminators.php',
        'test' => 'Phase 6 [phase6-category-scope] category pivot relations and bulk handlers isolate both review and category ownership keys',
        'routes' => ['/testimonials/bulk-category'],
    ],
];
