<?php

namespace WpsrTests\Mutation;

use RuntimeException;
use Throwable;

final class Runner
{
    private $root;
    private $guardRepos;
    private $restoreMode;
    private $baselineStatuses = [];
    private $baselineHashes = [];

    public function __construct($root, array $guardRepos = [], $restoreMode = 'git')
    {
        $this->root = rtrim((string) $root, DIRECTORY_SEPARATOR);
        $this->guardRepos = $guardRepos ?: [$this->root];
        $this->restoreMode = $restoreMode;
    }

    public function run(array $mutants, $fullCommand, $runBaselines = true)
    {
        if (empty($mutants)) {
            throw new RuntimeException('Mutation manifest selected zero mutants. Suite wiring is broken.');
        }

        $this->validateManifest($mutants);
        $this->captureGuards($mutants);

        $baseline = [
            'captured_at_utc' => gmdate('c'),
            'root' => $this->root,
            'status' => $this->statusMetadata(),
            'production_file_sha256' => $this->baselineHashes,
            'commands' => [],
        ];

        if ($runBaselines) {
            $commands = ['default' => $fullCommand];
            foreach ($mutants as $mutant) {
                $commands[$mutant['narrow_tier']] = $mutant['command'];
            }

            foreach ($commands as $tier => $command) {
                $result = $this->runCommand($command);
                $baseline['commands'][$tier] = $this->commandSummary($command, $result);
                if ($result['exit_code'] !== 0) {
                    throw new RuntimeException("Mutation baseline failed for {$tier}: {$command}\n" . $result['output']);
                }
                $this->assertGuards('baseline command ' . $tier);
            }
        }

        $results = [];
        foreach ($mutants as $mutant) {
            $results[] = $this->runMutant($mutant, $fullCommand);
        }

        return [
            'baseline' => $baseline,
            'results' => $results,
            'summary' => $this->summarize($results),
        ];
    }

    private function validateManifest(array $mutants)
    {
        $ids = [];
        foreach ($mutants as $index => $mutant) {
            foreach (['id', 'file', 'operator', 'search', 'replace', 'narrow_tier', 'command', 'source_trace'] as $key) {
                if (!isset($mutant[$key]) || $mutant[$key] === '') {
                    throw new RuntimeException("Mutant index {$index} missing {$key}.");
                }
            }
            if (isset($ids[$mutant['id']])) {
                throw new RuntimeException('Duplicate mutant id: ' . $mutant['id']);
            }
            $ids[$mutant['id']] = true;

            $file = str_replace('\\', '/', $mutant['file']);
            if (preg_match('#(^|/)(tests|vendor|node_modules|dev|assets|builds)(/|$)#', $file)
                || stripos($file, 'uninstall') !== false
                || !preg_match('#^(app|boot|config|database)/.*\.php$|^wp-social-reviews\.php$#', $file)
            ) {
                throw new RuntimeException("Forbidden mutation target {$file} ({$mutant['id']}).");
            }
        }
    }

    private function captureGuards(array $mutants)
    {
        foreach ($this->guardRepos as $repo) {
            $this->baselineStatuses[$repo] = $this->gitStatus($repo);
        }

        foreach ($mutants as $mutant) {
            $file = $mutant['file'];
            if (isset($this->baselineHashes[$file])) {
                continue;
            }
            $absolute = $this->root . DIRECTORY_SEPARATOR . $file;
            if (!is_file($absolute)) {
                throw new RuntimeException("Mutation target does not exist: {$file}");
            }
            if ($this->restoreMode === 'git') {
                $tracked = $this->runCommand('git ls-files --error-unmatch -- ' . escapeshellarg($file));
                if ($tracked['exit_code'] !== 0) {
                    throw new RuntimeException("Production mutation target is not tracked: {$file}");
                }
                $dirty = $this->runCommand('git diff --quiet -- ' . escapeshellarg($file) . ' && git diff --cached --quiet -- ' . escapeshellarg($file));
                if ($dirty['exit_code'] !== 0) {
                    throw new RuntimeException("Production mutation target starts dirty: {$file}");
                }
            }
            $this->baselineHashes[$file] = hash_file('sha256', $absolute);
        }
    }

    private function runMutant(array $mutant, $fullCommand)
    {
        $id = $mutant['id'];
        $file = $mutant['file'];
        $absolute = $this->root . DIRECTORY_SEPARATOR . $file;
        $originalHash = $this->baselineHashes[$file];
        $backup = tempnam(sys_get_temp_dir(), 'wpsr-mutant-');
        if ($backup === false || !copy($absolute, $backup)) {
            throw new RuntimeException("{$id}: could not create byte backup.");
        }

        $started = microtime(true);
        $line = null;
        $narrow = null;
        $full = null;
        $outcome = 'error';
        $classification = 'runner-error';
        $corrected = false;
        $error = null;
        $mutantHash = null;

        try {
            $source = file_get_contents($absolute);
            if ($source === false) {
                throw new RuntimeException("{$id}: could not read {$file}.");
            }
            $count = substr_count($source, $mutant['search']);
            if ($count !== 1) {
                throw new RuntimeException("{$id}: mutation target count {$count}; expected exactly 1.");
            }
            $offset = strpos($source, $mutant['search']);
            $line = substr_count(substr($source, 0, $offset), "\n") + 1;
            $changed = str_replace($mutant['search'], $mutant['replace'], $source, $replacements);
            if ($replacements !== 1 || $changed === $source) {
                throw new RuntimeException("{$id}: mutation did not change exactly one source region.");
            }
            if (file_put_contents($absolute, $changed) === false) {
                throw new RuntimeException("{$id}: could not write mutant.");
            }
            $mutantHash = hash_file('sha256', $absolute);
            if ($mutantHash === $originalHash) {
                throw new RuntimeException("{$id}: mutant SHA equals baseline SHA.");
            }

            $syntax = $this->runCommand('php -l ' . escapeshellarg($file));
            if ($syntax['exit_code'] !== 0) {
                $outcome = 'error';
                $classification = 'invalid-mutant';
                $error = $this->evidence($syntax['output']);
            } else {
                $narrowResult = $this->runCommand($mutant['command']);
                $narrow = $this->commandSummary($mutant['command'], $narrowResult);
                if ($narrowResult['exit_code'] !== 0) {
                    $outcome = 'killed';
                    $classification = 'narrow-kill';
                } else {
                    $fullResult = $this->runCommand($fullCommand);
                    $full = $this->commandSummary($fullCommand, $fullResult);
                    if ($fullResult['exit_code'] !== 0) {
                        $outcome = 'killed';
                        $classification = 'full-suite-corrected-kill';
                        $corrected = true;
                    } else {
                        $classification = isset($mutant['survivor_classification'])
                            ? $mutant['survivor_classification']
                            : 'test-gap';
                        $outcome = $classification === 'equivalent' ? 'equivalent' : 'survived';
                    }
                }
            }
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
            $outcome = 'error';
            $classification = 'runner-error';
        } finally {
            $restoreError = $this->restore($file, $absolute, $backup, $originalHash);
            @unlink($backup);
            if ($restoreError !== null) {
                throw new RuntimeException("{$id}: {$restoreError}");
            }
            $this->assertGuards('after mutant ' . $id);
        }

        return [
            'id' => $id,
            'file' => $file,
            'line' => $line,
            'operator' => $mutant['operator'],
            'description' => isset($mutant['description']) ? $mutant['description'] : '',
            'source_trace' => $mutant['source_trace'],
            'narrow_tier' => $mutant['narrow_tier'],
            'outcome' => $outcome,
            'classification' => $classification,
            'narrow' => $narrow,
            'full_suite_rechecked' => $full !== null,
            'full_suite_correction' => $corrected,
            'full' => $full,
            'failure_evidence' => $narrow && $narrow['exit_code'] !== 0
                ? $narrow['evidence']
                : ($full && $full['exit_code'] !== 0 ? $full['evidence'] : $error),
            'baseline_sha256' => $originalHash,
            'mutant_sha256' => $mutantHash,
            'restored_sha256' => hash_file('sha256', $absolute),
            'runtime_seconds' => round(microtime(true) - $started, 3),
        ];
    }

    private function restore($file, $absolute, $backup, $originalHash)
    {
        if ($this->restoreMode === 'git') {
            $restore = $this->runCommand('git checkout -- ' . escapeshellarg($file));
            if ($restore['exit_code'] !== 0 && !copy($backup, $absolute)) {
                return 'git checkout and byte-backup restoration failed: ' . $this->evidence($restore['output']);
            }
        } elseif (!copy($backup, $absolute)) {
            return 'byte-backup restoration failed.';
        }

        if (!is_file($absolute) || hash_file('sha256', $absolute) !== $originalHash || filesize($backup) !== filesize($absolute)) {
            return 'restored production SHA/bytes do not match baseline.';
        }
        if (!hash_equals(hash_file('sha256', $backup), hash_file('sha256', $absolute))) {
            return 'restored file differs from byte backup.';
        }
        return null;
    }

    private function assertGuards($context)
    {
        foreach ($this->guardRepos as $repo) {
            $current = $this->gitStatus($repo);
            if (!hash_equals(hash('sha256', $this->baselineStatuses[$repo]), hash('sha256', $current)) || $current !== $this->baselineStatuses[$repo]) {
                throw new RuntimeException("Dirty drift detected {$context} in {$repo}; exact baseline git status changed.");
            }
        }
        foreach ($this->baselineHashes as $file => $hash) {
            $absolute = $this->root . DIRECTORY_SEPARATOR . $file;
            if (!is_file($absolute) || !hash_equals($hash, hash_file('sha256', $absolute))) {
                throw new RuntimeException("Production SHA drift detected {$context}: {$file}");
            }
        }
    }

    private function gitStatus($repo)
    {
        $command = 'git -C ' . escapeshellarg($repo) . ' status --porcelain=v1 -z --untracked-files=all';
        $result = $this->runCommand($command, dirname($repo));
        if ($result['exit_code'] !== 0) {
            throw new RuntimeException("Could not capture git status for {$repo}: " . $result['output']);
        }
        return $result['output'];
    }

    private function statusMetadata()
    {
        $metadata = [];
        foreach ($this->baselineStatuses as $repo => $status) {
            $entries = array_values(array_filter(explode("\0", $status), 'strlen'));
            $metadata[$repo] = [
                'sha256' => hash('sha256', $status),
                'bytes' => strlen($status),
                'entries' => $entries,
            ];
        }
        return $metadata;
    }

    private function runCommand($command, $cwd = null)
    {
        $started = microtime(true);
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['/usr/bin/env', 'bash', '-c', $command], $descriptor, $pipes, $cwd ?: $this->root);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start command: ' . $command);
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        return [
            'exit_code' => $exit,
            'runtime_seconds' => round(microtime(true) - $started, 3),
            'output' => (string) $stdout . (string) $stderr,
        ];
    }

    private function commandSummary($command, array $result)
    {
        return [
            'command' => $command,
            'exit_code' => $result['exit_code'],
            'runtime_seconds' => $result['runtime_seconds'],
            'evidence' => $this->evidence($result['output']),
        ];
    }

    private function evidence($output)
    {
        $output = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', (string) $output);
        $lines = preg_split('/\R/', trim($output));
        $lines = array_values(array_filter($lines, function ($line) {
            return trim($line) !== '';
        }));
        return implode("\n", array_slice($lines, -18));
    }

    private function summarize(array $results)
    {
        $summary = [
            'total' => count($results),
            'killed' => 0,
            'survived' => 0,
            'equivalent' => 0,
            'skipped' => 0,
            'errors' => 0,
            'corrected_outcomes' => 0,
            'runtime_seconds' => 0,
            'survivors' => [],
        ];
        foreach ($results as $result) {
            if ($result['outcome'] === 'killed') {
                $summary['killed']++;
            } elseif ($result['outcome'] === 'survived') {
                $summary['survived']++;
                $summary['survivors'][] = $result['id'];
            } elseif ($result['outcome'] === 'equivalent') {
                $summary['equivalent']++;
            } elseif ($result['outcome'] === 'skipped') {
                $summary['skipped']++;
            } else {
                $summary['errors']++;
            }
            if (!empty($result['full_suite_correction'])) {
                $summary['corrected_outcomes']++;
            }
            $summary['runtime_seconds'] += $result['runtime_seconds'];
        }
        $denominator = $summary['killed'] + $summary['survived'];
        $summary['kill_rate_percent'] = $denominator > 0
            ? round(($summary['killed'] / $denominator) * 100, 2)
            : 0;
        $summary['runtime_seconds'] = round($summary['runtime_seconds'], 3);
        return $summary;
    }
}
