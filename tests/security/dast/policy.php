<?php
/** Deterministic DAST policy gate. High/critical or off-target results always fail. */

if ($argc !== 4 || !in_array($argv[1], ['zap', 'nuclei'], true)) {
    fwrite(STDERR, "Usage: php policy.php zap|nuclei RESULT OUTPUT_JSON\n");
    exit(2);
}

$scanner = $argv[1];
$resultPath = $argv[2];
$outputPath = $argv[3];
$target = 'http://127.0.0.1:80';
$allowlist = json_decode((string) file_get_contents(__DIR__ . '/allowlist.json'), true);
$rank = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
$findings = [];
$scanned = 0;
$errors = [];
if (!is_array($allowlist) || ($allowlist['schema'] ?? null) !== 1) {
    $errors[] = 'DAST allowlist schema must be 1';
    $allowlist = ['zap' => [], 'nuclei' => []];
}
foreach (['zap', 'nuclei'] as $wpsr_allow_scanner) {
    if (!isset($allowlist[$wpsr_allow_scanner]) || !is_array($allowlist[$wpsr_allow_scanner])) {
        $errors[] = 'DAST allowlist scanner map missing: ' . $wpsr_allow_scanner;
        $allowlist[$wpsr_allow_scanner] = [];
        continue;
    }
    foreach ($allowlist[$wpsr_allow_scanner] as $wpsr_allow_id => $wpsr_allow_rule) {
        if ((!is_string($wpsr_allow_id) && !is_int($wpsr_allow_id)) || (string) $wpsr_allow_id === '' || !is_array($wpsr_allow_rule)
            || !isset($rank[$wpsr_allow_rule['max_severity'] ?? ''])
            || $rank[$wpsr_allow_rule['max_severity']] >= $rank['high']
            || !is_string($wpsr_allow_rule['reason'] ?? null)
            || strlen(trim($wpsr_allow_rule['reason'])) < 30) {
            $errors[] = 'malformed DAST allowlist row: ' . $wpsr_allow_scanner . '/' . (string) $wpsr_allow_id;
        }
    }
}

$targetIsSafe = static function ($url) use ($target) {
    $parts = parse_url((string) $url);
    return is_array($parts)
        && ($parts['scheme'] ?? '') === 'http'
        && ($parts['host'] ?? '') === '127.0.0.1'
        && (int) ($parts['port'] ?? 80) === 80
        && empty($parts['user'])
        && empty($parts['pass']);
};

if (!is_file($resultPath) || filesize($resultPath) === 0) {
    $errors[] = 'scanner result artifact missing or empty';
} elseif ($scanner === 'zap') {
    $document = json_decode((string) file_get_contents($resultPath), true);
    if (!is_array($document) || !isset($document['site']) || !is_array($document['site'])) {
        $errors[] = 'ZAP JSON malformed or has no site layer';
    } else {
        $scanned = count($document['site']);
        foreach ($document['site'] as $site) {
            $siteName = $site['@name'] ?? $site['name'] ?? '';
            if (!$targetIsSafe($siteName)) {
                $errors[] = 'ZAP reported off-target site: ' . (string) $siteName;
            }
            foreach ((array) ($site['alerts'] ?? []) as $alert) {
                $severityText = strtolower(trim((string) ($alert['riskdesc'] ?? $alert['risk'] ?? '')));
                preg_match('/^(informational|info|low|medium|high|critical)/', $severityText, $severityMatch);
                if (empty($severityMatch[1])) {
                    $errors[] = 'ZAP finding has unknown/missing severity';
                }
                $severity = ($severityMatch[1] ?? 'unknown') === 'informational' ? 'info' : ($severityMatch[1] ?? 'unknown');
                $instances = (array) ($alert['instances'] ?? []);
                foreach ($instances as $instance) {
                    $instanceUrl = $instance['uri'] ?? $instance['url'] ?? '';
                    if (!$targetIsSafe($instanceUrl)) {
                        $errors[] = 'ZAP reported off-target finding instance: ' . (string) $instanceUrl;
                    }
                }
                $findings[] = [
                    'id' => (string) ($alert['pluginid'] ?? $alert['alertRef'] ?? 'unknown'),
                    'severity' => $severity,
                    'name' => (string) ($alert['alert'] ?? $alert['name'] ?? ''),
                    'instances' => max(1, count($instances)),
                ];
            }
        }
    }
} else {
    $lines = file($resultPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        $errors[] = 'nuclei JSONL has zero result rows';
    } else {
        foreach ($lines as $number => $line) {
            $row = json_decode($line, true);
            if (!is_array($row)) {
                $errors[] = 'nuclei JSONL malformed at line ' . ($number + 1);
                continue;
            }
            $matched = $row['matched-at'] ?? $row['url'] ?? $row['host'] ?? '';
            if (!$targetIsSafe($matched)) {
                $errors[] = 'nuclei reported off-target result: ' . (string) $matched;
            }
            $findings[] = [
                'id' => (string) ($row['template-id'] ?? 'unknown'),
                'severity' => strtolower((string) ($row['info']['severity'] ?? 'unknown')),
                'name' => (string) ($row['info']['name'] ?? ''),
                'instances' => 1,
            ];
        }
        $scanned = count($lines);
    }
}

$high = 0;
$allowed = 0;
$review = 0;
foreach ($findings as $finding) {
    $severity = $finding['severity'];
    $severityRank = $rank[$severity] ?? 99;
    $rule = $allowlist[$scanner][$finding['id']] ?? null;
    $maxRank = is_array($rule) ? ($rank[$rule['max_severity']] ?? -1) : -1;
    if ($severityRank >= $rank['high']) {
        $high += $finding['instances'];
        $errors[] = sprintf('%s %s finding is not permitted: %s', strtoupper($severity), $finding['id'], $finding['name']);
    } elseif ($rule && $severityRank <= $maxRank) {
        $allowed += $finding['instances'];
    } else {
        $review += $finding['instances'];
        $errors[] = sprintf('unallowlisted %s finding requires review: %s %s', strtoupper($severity), $finding['id'], $finding['name']);
    }
}

if ($scanned < 1) {
    $errors[] = 'scanner reported zero scanned/result layers';
}

$summary = [
    'schema' => 1,
    'scanner' => $scanner,
    'target' => $target,
    'scanned' => $scanned,
    'findings' => array_sum(array_column($findings, 'instances')),
    'allowed' => $allowed,
    'review' => $review,
    'high_critical' => $high,
    'failures' => array_values(array_unique($errors)),
    'rows' => $findings,
];
if (!is_dir(dirname($outputPath)) && !mkdir(dirname($outputPath), 0777, true) && !is_dir(dirname($outputPath))) {
    fwrite(STDERR, "DAST-POLICY FAIL could not create policy output directory\n");
    exit(1);
}
if (file_put_contents($outputPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
    fwrite(STDERR, "DAST-POLICY FAIL could not write policy output artifact\n");
    exit(1);
}

printf(
    "DAST-POLICY scanner=%s scanned=%d findings=%d allowed=%d review=%d high-critical=%d failures=%d\n",
    $scanner,
    $scanned,
    $summary['findings'],
    $allowed,
    $review,
    $high,
    count($summary['failures'])
);
foreach ($summary['failures'] as $error) {
    fwrite(STDERR, "DAST-POLICY FAIL {$error}\n");
}
exit($summary['failures'] ? 1 : 0);
