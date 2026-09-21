<?php
/**
 * Phase 10 core REST input inventory.
 *
 * Source manifests supply every mounted declaration. This file adds input-family,
 * behavior-coverage, prior-coverage, and explicit deferment/exclusion metadata.
 */

$wpsr_rows = [];
$wpsr_seen = [];
$wpsr_smoke = require dirname(__DIR__) . '/smoke/routes.manifest.php';
$wpsr_permissions = require dirname(__DIR__) . '/permissions/routes.manifest.php';

foreach ($wpsr_smoke as $wpsr_case) {
    $wpsr_key = 'GET ' . $wpsr_case['declaration'];
    if (isset($wpsr_seen[$wpsr_key])) {
        continue;
    }
    $wpsr_seen[$wpsr_key] = true;
    $wpsr_rows[] = [
        'declaration' => $wpsr_key,
        'method' => 'GET',
        'route' => $wpsr_case['route'],
    ];
}
foreach ($wpsr_permissions as $wpsr_case) {
    $wpsr_key = $wpsr_case['declaration'];
    if (isset($wpsr_seen[$wpsr_key])) {
        throw new RuntimeException('Duplicate validation inventory seed: ' . $wpsr_key);
    }
    $wpsr_seen[$wpsr_key] = true;
    $wpsr_rows[] = [
        'declaration' => $wpsr_key,
        'method' => $wpsr_case['method'],
        'route' => $wpsr_case['route'],
    ];
}

$wpsr_classify = function (array $wpsr_row) {
    $wpsr_method = $wpsr_row['method'];
    $wpsr_route = $wpsr_row['route'];
    $wpsr_inputs = [];
    $wpsr_disposition = 'prior';
    $wpsr_coverage = ['phase1-9-route-health-permission'];
    $wpsr_reason = 'No material request-controlled persistence or query-shaping input beyond prior smoke/permission/round-trip coverage.';

    if (strpos($wpsr_route, '{') !== false || preg_match('#/(reviews|testimonials|templates|chat-widgets|notifications)(/|$)#', $wpsr_route)) {
        $wpsr_inputs[] = 'ids-object-scope';
    }

    if ($wpsr_method === 'GET' && preg_match('#^/(reviews|testimonials|templates|chat-widgets|notifications)$#', $wpsr_route)) {
        $wpsr_inputs = array_merge($wpsr_inputs, ['order-sort', 'search-like', 'pagination-bounds', 'action-status-enum']);
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['query-shaping', 'like-wildcards', 'known-admin-like-gap'];
        $wpsr_reason = 'Real listing queries exercise sort fallback, SELECT-shaped terms, literal wildcard semantics, pagination, and screen scope.';
    } elseif ($wpsr_method === 'GET' && $wpsr_route === '/platforms/reviews/configs') {
        $wpsr_inputs = ['pagination-bounds', 'nested-settings'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['pagination-bounds', 'phase7-option-pagination'];
        $wpsr_reason = 'Option-backed connected-business pagination is bounded and value-asserted.';
    } elseif ($wpsr_method === 'GET' && $wpsr_route === '/pages/search') {
        $wpsr_inputs = ['search-like', 'pagination-bounds', 'ids-object-scope'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['pagination-bounds', 'known-negative-id-gap'];
        $wpsr_reason = 'WP_Query selector behavior and malformed ID normalization are covered.';
    } elseif (preg_match('#^/(reviews|testimonials)(/\{[^}]+\})?$#', $wpsr_route) && in_array($wpsr_method, ['POST', 'PUT', 'DELETE'], true)) {
        $wpsr_inputs = ['ids-object-scope', 'text-markup', 'urls-emails-colors-styles', 'nested-settings'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['review-input-output', 'limited-capability-scope', 'phase9-review-testimonial-round-trip'];
        $wpsr_reason = 'Typed fields, sanitizer outputs, rendered escaping, full persistence, and cross-screen object scope are behavior-tested.';
    } elseif (preg_match('#^/(reviews|testimonials)/(status-update|spam|bulk-category)$#', $wpsr_route)) {
        $wpsr_inputs = ['ids-object-scope', 'action-status-enum', 'text-markup'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['enum-boundaries', 'limited-capability-scope', 'phase5-status', 'phase6-category-scope'];
        $wpsr_reason = 'Enums, stale category IDs, and route-derived review/testimonial scope are value-tested.';
    } elseif (preg_match('#^/(reviews|testimonials)/categories/#', $wpsr_route)) {
        $wpsr_inputs = ['ids-object-scope', 'text-markup'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['limited-capability-scope', 'phase9-category-round-trip'];
        $wpsr_reason = 'Both-capability policy, category ID ownership, sanitization, and persistence are covered.';
    } elseif (preg_match('#^/chat-widgets/meta/chats/#', $wpsr_route)) {
        $wpsr_inputs = ['ids-object-scope', 'text-markup', 'urls-emails-colors-styles', 'nested-settings', 'action-status-enum'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['nested-sanitizers', 'cross-object-scope', 'phase9-widget-config'];
        $wpsr_reason = 'Real nested config save asserts color/URL/markup/int outputs and wrong-post IDs remain unchanged.';
    } elseif (preg_match('#^/chat-widgets$#', $wpsr_route) && $wpsr_method !== 'GET') {
        $wpsr_inputs = ['ids-object-scope', 'action-status-enum', 'nested-settings'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['enum-boundaries', 'cross-object-scope', 'phase9-widget-round-trip'];
        $wpsr_reason = 'Status fallback, post-type scope, lifecycle snapshots, and destructive ownership are covered.';
    } elseif (preg_match('#^/templates/meta/reviews/#', $wpsr_route)) {
        $wpsr_inputs = ['ids-object-scope', 'text-markup', 'urls-emails-colors-styles', 'nested-settings', 'pagination-bounds', 'order-sort', 'search-like'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['cross-object-scope', 'review-input-output', 'phase7-filtered-totals', 'phase9-review-template-config'];
        $wpsr_reason = 'Template object guard and nested sanitized persisted/query settings are covered; preview/load-more reuse same sanitizer.';
    } elseif ($wpsr_route === '/templates' && in_array($wpsr_method, ['DELETE', 'PUT'], true)) {
        $wpsr_inputs = ['ids-object-scope', 'text-markup'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['known-template-object-gap', 'phase9-template-round-trip'];
        $wpsr_reason = 'Owned template lifecycle is covered; destructive cross-screen managed-post scope is parked.';
    } elseif (preg_match('#^/templates/title/#', $wpsr_route)) {
        $wpsr_inputs = ['ids-object-scope', 'text-markup'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['known-template-object-gap', 'phase9-template-round-trip'];
        $wpsr_reason = 'Title text and full snapshots are covered; shared cross-screen object policy shares parked mechanism.';
    } elseif (preg_match('#^/notifications$#', $wpsr_route) && $wpsr_method !== 'GET') {
        $wpsr_inputs = ['ids-object-scope', 'action-status-enum', 'nested-settings'];
        $wpsr_disposition = 'prior';
        $wpsr_coverage = ['phase9-notification-round-trip', 'phase3-policy'];
        $wpsr_reason = 'Typed status fallback mirrors widget controller; notification lifecycle/object type has exact Phase 9 snapshots.';
    } elseif (preg_match('#^/settings/(translations|advance-settings)$#', $wpsr_route) && $wpsr_method === 'POST') {
        $wpsr_inputs = ['text-markup', 'urls-emails-colors-styles', 'nested-settings'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['nested-sanitizers', 'known-settings-policy-gap', 'phase9-settings-round-trip'];
        $wpsr_reason = 'Backend sanitizer maps and persisted option snapshots are covered; feature-level policy gap is parked.';
    } elseif ($wpsr_route === '/settings' && in_array($wpsr_method, ['PUT', 'DELETE'], true)) {
        $wpsr_inputs = ['ids-object-scope', 'nested-settings', 'action-status-enum'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['known-settings-policy-gap', 'phase9-platform-settings', 'phase6-cache-scope'];
        $wpsr_reason = 'Platform settings/cache IDs have prior value tests; route-group feature authorization gap is parked.';
    } elseif (strpos($wpsr_route, '/settings/') === 0 && $wpsr_method !== 'GET') {
        $wpsr_inputs = ['ids-object-scope', 'text-markup', 'nested-settings', 'action-status-enum'];
        $wpsr_disposition = 'deferred';
        $wpsr_coverage = ['phase3-policy', 'known-settings-policy-gap'];
        $wpsr_reason = 'License, filesystem resets, error reset, and global delete are external/one-way/destructive; safe execution excluded. Coarse feature policy is parked via reversible owned option.';
    } elseif ($wpsr_route === '/shoppable' && in_array($wpsr_method, ['PUT', 'DELETE'], true)) {
        $wpsr_inputs = ['text-markup', 'urls-emails-colors-styles', 'nested-settings', 'ids-object-scope'];
        $wpsr_disposition = 'phase10';
        $wpsr_coverage = ['known-shoppable-delete-gap', 'phase9-shoppable-round-trip'];
        $wpsr_reason = 'PUT nested URL/text sanitization and semantic delete snapshots are covered; DELETE sanitizer asymmetry is parked.';
    } elseif (strpos($wpsr_route, '/shoppable/template-settings/') === 0) {
        $wpsr_inputs = ['ids-object-scope', 'urls-emails-colors-styles', 'nested-settings'];
        $wpsr_disposition = 'prior';
        $wpsr_coverage = ['phase9-feed-template-config'];
        $wpsr_reason = 'Owned template nested shoppable fields have exact persisted full-snapshot coverage.';
    } elseif (preg_match('#^/platforms/(reviews|feeds)/configs/(prepare|consume)$#', $wpsr_route)) {
        $wpsr_inputs = ['text-markup', 'urls-emails-colors-styles', 'nested-settings', 'action-status-enum'];
        $wpsr_disposition = 'deferred';
        $wpsr_coverage = ['phase3-policy', 'source-contract-sanitize-map'];
        $wpsr_reason = 'Sanitize maps are source-inventoried; behavior requires outbound OAuth bridge and is deferred. No real HTTP is allowed.';
    } elseif (preg_match('#^/platforms/(reviews|feeds)#', $wpsr_route) && $wpsr_method !== 'GET') {
        $wpsr_inputs = ['text-markup', 'urls-emails-colors-styles', 'nested-settings', 'ids-object-scope'];
        $wpsr_disposition = 'deferred';
        $wpsr_coverage = ['phase3-policy', 'source-contract-sanitize-map'];
        $wpsr_reason = 'Credential verification/sync/disconnect needs third-party state or globally shared credentials; outbound mocked, mutation excluded.';
    } elseif (preg_match('#^/platforms/(update-statuses|dashboard-notices)$#', $wpsr_route)) {
        $wpsr_inputs = ['nested-settings', 'action-status-enum', 'text-markup'];
        $wpsr_disposition = 'deferred';
        $wpsr_coverage = ['phase3-policy', 'source-contract-sanitize-map'];
        $wpsr_reason = 'Writes pre-existing shared wpsr_statuses option; exact owned fixture cannot replace installed state safely.';
    } elseif ($wpsr_route === '/platforms/subscribe') {
        $wpsr_inputs = ['urls-emails-colors-styles', 'text-markup', 'nested-settings'];
        $wpsr_disposition = 'deferred';
        $wpsr_coverage = ['phase3-policy', 'source-contract-sanitize-map'];
        $wpsr_reason = 'Email/name sanitizer map is inventoried; newsletter behavior is outbound and no real HTTP is allowed.';
    } elseif ($wpsr_route === '/platforms/addons') {
        $wpsr_inputs = ['action-status-enum'];
        $wpsr_disposition = 'excluded';
        $wpsr_coverage = ['phase3-policy'];
        $wpsr_reason = 'Plugin filename/package values are internal constants; client platform is allowlisted by branches. Install/download execution is globally destructive and excluded.';
    } elseif (strpos($wpsr_route, '/onboarding') === 0 && $wpsr_method !== 'GET') {
        $wpsr_inputs = ['ids-object-scope', 'text-markup', 'nested-settings', 'action-status-enum'];
        $wpsr_disposition = 'deferred';
        $wpsr_coverage = ['phase3-policy', 'source-contract-sanitize-map'];
        $wpsr_reason = 'Sanitizer map is inventoried; volatile shared workflow and newsletter side effect remain Phase 14.';
    } elseif (preg_match('#/(duplicate|edit|load-more)$#', $wpsr_route)) {
        $wpsr_inputs = ['ids-object-scope', 'nested-settings', 'pagination-bounds'];
        $wpsr_disposition = 'deferred';
        $wpsr_coverage = ['phase3-policy', 'phase5-9-related-scope'];
        $wpsr_reason = 'Clone/preview/external-feed action is non-lifecycle or remote; safe related object scope is covered elsewhere and broader workflow deferred.';
    }

    if (!$wpsr_inputs) {
        $wpsr_inputs = ['none'];
    }
    $wpsr_row['inputs'] = array_values(array_unique($wpsr_inputs));
    $wpsr_row['disposition'] = $wpsr_disposition;
    $wpsr_row['coverage'] = $wpsr_coverage;
    $wpsr_row['reason'] = $wpsr_reason;
    return $wpsr_row;
};

return array_map($wpsr_classify, $wpsr_rows);
