<?php
/**
 * Phase 16 query-count and structural batch helpers.
 */
class WpsrThroughput
{
    public static function measure($label, callable $operation)
    {
        global $wpdb;

        $wpdb->last_error = '';
        WpsrTest::beginQueryCapture();
        $startedAt = microtime(true);
        try {
            $result = $operation();
        } finally {
            $elapsed = microtime(true) - $startedAt;
            $queries = WpsrTest::endQueryCapture();
        }

        WpsrTest::assertNoDatabaseError($label);
        $fingerprints = self::fingerprints($queries);
        WP_CLI::log(sprintf(
            '  THROUGHPUT %s: queries=%d time=%.4fs (informational) fingerprints=%s',
            $label,
            count($queries),
            $elapsed,
            wp_json_encode($fingerprints, JSON_UNESCAPED_SLASHES)
        ));

        return [
            'result'       => $result,
            'queries'      => $queries,
            'count'        => count($queries),
            'fingerprints' => $fingerprints,
            'elapsed'      => $elapsed,
        ];
    }

    public static function queriesForTable(array $measurement, $table)
    {
        $needle = strtolower((string) $table);
        return array_values(array_filter($measurement['queries'], function ($query) use ($needle) {
            return strpos(strtolower((string) $query), $needle) !== false;
        }));
    }

    public static function assertCountBudget(array $small, array $large, $smallBudget, $largeBudget, $growthBudget, $label)
    {
        WpsrTest::assertTrue($small['count'] <= $smallBudget, $label . ' small query budget <= ' . $smallBudget . '; observed ' . $small['count']);
        WpsrTest::assertTrue($large['count'] <= $largeBudget, $label . ' large query budget <= ' . $largeBudget . '; observed ' . $large['count']);
        WpsrTest::assertTrue(
            ($large['count'] - $small['count']) <= $growthBudget,
            $label . ' query growth budget <= ' . $growthBudget . '; observed ' . ($large['count'] - $small['count'])
        );
    }

    public static function assertStatementCaps(array $queries, $maxInItems, $maxInsertRows, $maxLimit, $label)
    {
        foreach ($queries as $query) {
            foreach (self::inListSizes($query) as $size) {
                WpsrTest::assertTrue($size <= $maxInItems, $label . ' IN-list cap <= ' . $maxInItems . '; observed ' . $size);
            }

            $insertRows = self::insertRowCount($query);
            if ($insertRows !== null) {
                WpsrTest::assertTrue($insertRows <= $maxInsertRows, $label . ' INSERT row cap <= ' . $maxInsertRows . '; observed ' . $insertRows);
            }

            foreach (self::limitValues($query) as $limit) {
                WpsrTest::assertTrue($limit <= $maxLimit, $label . ' LIMIT cap <= ' . $maxLimit . '; observed ' . $limit);
            }
        }
    }

    public static function shapeFingerprint(array $measurement)
    {
        $inSizes = [];
        $insertRows = [];
        $limits = [];
        foreach ($measurement['queries'] as $query) {
            $inSizes = array_merge($inSizes, self::inListSizes($query));
            $insertRowCount = self::insertRowCount($query);
            if ($insertRowCount !== null) {
                $insertRows[] = $insertRowCount;
            }
            $limits = array_merge($limits, self::limitValues($query));
        }
        sort($inSizes, SORT_NUMERIC);
        sort($insertRows, SORT_NUMERIC);
        sort($limits, SORT_NUMERIC);

        return [
            'query_count' => (int) $measurement['count'],
            'in_list_sizes' => $inSizes,
            'insert_row_counts' => $insertRows,
            'limit_values' => $limits,
        ];
    }

    public static function inListSizes($query)
    {
        preg_match_all('/\b(?:NOT\s+)?IN\s*\(([^()]*)\)/i', (string) $query, $matches);
        $sizes = [];
        foreach ($matches[1] as $list) {
            $list = trim($list);
            if ($list === '' || stripos($list, 'select ') !== false) {
                continue;
            }
            $sizes[] = substr_count($list, ',') + 1;
        }
        return $sizes;
    }

    public static function insertRowCount($query)
    {
        if (!preg_match('/^\s*INSERT\b[\s\S]*?\bVALUES\s*(.+)$/i', (string) $query, $matches)) {
            return null;
        }

        preg_match_all('/\([^()]*\)/', $matches[1], $rows);
        return count($rows[0]);
    }

    public static function limitValues($query)
    {
        preg_match_all('/\bLIMIT\s+(?:\d+\s*,\s*)?(\d+)/i', (string) $query, $matches);
        return array_map('intval', $matches[1]);
    }

    public static function fingerprints(array $queries)
    {
        $fingerprints = [];
        foreach ($queries as $query) {
            $fingerprint = preg_replace('/\s+/', ' ', trim((string) $query));
            $fingerprint = preg_replace_callback('/\b(?:NOT\s+)?IN\s*\(([^()]*)\)/i', function ($match) {
                $sizes = self::inListSizes($match[0]);
                return preg_replace('/\([^()]*\)/', '(?x' . (isset($sizes[0]) ? $sizes[0] : 0) . ')', $match[0], 1);
            }, $fingerprint);
            $insertRows = self::insertRowCount($fingerprint);
            if ($insertRows !== null) {
                $fingerprint = preg_replace('/\bVALUES\s+.+$/i', 'VALUES (?x' . $insertRows . ' rows)', $fingerprint);
            }
            $fingerprint = preg_replace("/'(?:''|[^'])*'/", "'?'", $fingerprint);
            $fingerprint = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $fingerprint);
            $fingerprints[$fingerprint] = isset($fingerprints[$fingerprint]) ? $fingerprints[$fingerprint] + 1 : 1;
        }
        ksort($fingerprints, SORT_STRING);
        return $fingerprints;
    }
}
