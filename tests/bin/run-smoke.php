<?php
/**
 * Phase 1: in-process REST smoke over every core GET declaration.
 */
require_once __DIR__ . '/../lib/harness.php';
require_once __DIR__ . '/../lib/runner.php';

WpsrTest::boot();
$manifest = require __DIR__ . '/../smoke/routes.manifest.php';
if (!is_array($manifest) || !$manifest) {
    WP_CLI::error('Smoke manifest discovered zero cases; suite wiring is broken.');
}
$declarations = array_unique(array_column($manifest, 'declaration'));
if (!$declarations) {
    WP_CLI::error('Smoke manifest discovered zero declaration mappings; suite wiring is broken.');
}

$filter = WpsrRunner::filter((array) $args, 'Smoke');

$fixtures = get_option('wpsr_test_phase1_fixture_ids', []);
$resolvers = [
    'review_template' => ['id' => isset($fixtures['review_template']) ? (int) $fixtures['review_template'] : 0],
    'feed_template'   => ['id' => isset($fixtures['feed_template']) ? (int) $fixtures['feed_template'] : 0],
    'chat_widget'     => ['id' => isset($fixtures['chat_widget']) ? (int) $fixtures['chat_widget'] : 0],
    'page'            => ['id' => isset($fixtures['page']) ? (int) $fixtures['page'] : 0],
];
$expectedTypes = [
    'review_template' => 'wp_social_reviews',
    'feed_template'   => 'wp_social_reviews',
    'chat_widget'     => 'wpsr_social_chats',
    'page'            => 'page',
];

$replaceTokens = function ($value, array $tokens) use (&$replaceTokens) {
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = $replaceTokens($item, $tokens);
        }
        return $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($tokens as $token => $replacement) {
        if ($value === '{' . $token . '}') {
            return $replacement;
        }
        $value = str_replace('{' . $token . '}', (string) $replacement, $value);
    }
    return $value;
};

$selectedManifest = array_values(array_filter($manifest, function ($entry) use ($filter) {
    return $filter === null || stripos($entry['label'] . ' ' . $entry['route'], $filter) !== false;
}));
if (!$selectedManifest) {
    WP_CLI::error('Smoke filter selected zero routes: ' . $filter);
}

WP_CLI::log('WP Social Ninja core smoke — ' . count($manifest) . ' cases / ' . count($declarations) . ' GET declarations'
    . ($filter !== null ? ' — selected ' . count($selectedManifest) . ' case(s), filter: ' . $filter : '')
    . ' — persistent cache baseline verified clean' . "\n");

foreach ($selectedManifest as $entry) {
    $label = $entry['label'];

    WpsrTest::case($label, function () use ($entry, $resolvers, $expectedTypes, $replaceTokens, $label) {
        $route = $entry['route'];
        $params = isset($entry['params']) ? $entry['params'] : [];
        if (isset($entry['needs'])) {
            $need = $entry['needs'];
            $tokens = isset($resolvers[$need]) ? $resolvers[$need] : [];
            $id = isset($tokens['id']) ? (int) $tokens['id'] : 0;
            if (!$id || get_post_type($id) !== $expectedTypes[$need]) {
                WpsrTest::fail('missing or invalid lab fixture for ' . $need . '; rerun tests/lab/scripts/setup.sh');
                return;
            }
            $route = $replaceTokens($route, $tokens);
            $params = $replaceTokens($params, $tokens);
        }

        if (preg_match('/\{[^}]+\}/', $route)) {
            WpsrTest::fail('unresolved route placeholder: ' . $route);
            return;
        }

        $result = WpsrTest::rest('GET', $route, $params);
        WpsrTest::assertHealthy(
            $result,
            $label . ' [GET ' . $route . ']',
            isset($entry['ok']) ? $entry['ok'] : [200]
        );
    });
}

WpsrTest::finish('SMOKE');
