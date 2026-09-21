<?php
/** Shared fail-closed parser/assertion support for Phase 23 source-contract detectors. */

function wpsr_source_contract_start($id, array $argv)
{
    try {
        return new WpsrSourceContractRunner($id, $argv);
    } catch (Throwable $throwable) {
        fwrite(STDERR, 'SOURCE-CONTRACT FAIL ' . $id . ':1 bootstrap/CLI error: ' . $throwable->getMessage() . "\n");
        printf("SOURCE-CONTRACT id=%s checked=0 failures=1 runtime=0.0000s\n", $id);
        exit(1);
    }
}

class WpsrSourceContractRunner
{
    private $id;
    private $root;
    private $overrides = [];
    private $checked = 0;
    private $failures = [];
    private $started;

    public function __construct($id, array $argv)
    {
        $this->id = (string) $id;
        $this->root = dirname(__DIR__, 2);
        $this->started = microtime(true);

        foreach (array_slice($argv, 1) as $argument) {
            if (strpos($argument, '--source-root=') === 0) {
                $value = substr($argument, 14);
                if ($value === '') {
                    throw new InvalidArgumentException('--source-root requires a path');
                }
                $this->root = rtrim($value, DIRECTORY_SEPARATOR);
                continue;
            }
            if (strpos($argument, '--source-file=') === 0) {
                $mapping = substr($argument, 14);
                $separator = strpos($mapping, '=');
                if ($separator === false) {
                    throw new InvalidArgumentException('--source-file requires RELATIVE=PATH');
                }
                $relative = substr($mapping, 0, $separator);
                $path = substr($mapping, $separator + 1);
                if ($relative === '' || $path === '' || isset($this->overrides[$relative])) {
                    throw new InvalidArgumentException('--source-file mapping is empty or duplicated: ' . $mapping);
                }
                $this->overrides[$relative] = $path;
                continue;
            }
            if ($argument === '-h' || $argument === '--help') {
                echo "Usage: php detector.php [--source-root=ROOT] [--source-file=RELATIVE=PATH]\n";
                exit(0);
            }
            throw new InvalidArgumentException('unknown option: ' . $argument);
        }

        if ($this->root === '' || !is_dir($this->root)) {
            throw new RuntimeException('source root missing: ' . $this->root);
        }
    }

    public function path($relative)
    {
        return isset($this->overrides[$relative])
            ? $this->overrides[$relative]
            : $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    public function source($relative)
    {
        $path = $this->path($relative);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException($relative . ':1 source missing or unreadable: ' . $path);
        }
        $source = file_get_contents($path);
        if ($source === false || $source === '') {
            throw new RuntimeException($relative . ':1 source is empty or unreadable: ' . $path);
        }
        return [
            'relative' => $relative,
            'path' => $path,
            'code' => $source,
            'line' => 1,
        ];
    }

    public function phpMethod($relative, $method)
    {
        $file = $this->source($relative);
        $tokens = token_get_all($file['code']);
        $offset = 0;
        $matches = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            $text = is_array($token) ? $token[1] : $token;
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                $offset += strlen($text);
                continue;
            }

            $cursor = $index + 1;
            while ($cursor < $count && is_array($tokens[$cursor])
                && in_array($tokens[$cursor][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $cursor++;
            }
            if ($cursor < $count && $tokens[$cursor] === '&') {
                $cursor++;
                while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
                    $cursor++;
                }
            }
            if ($cursor >= $count || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_STRING
                || $tokens[$cursor][1] !== $method) {
                $offset += strlen($text);
                continue;
            }

            $start = $offset;
            $open = null;
            for ($body = $cursor + 1; $body < $count; $body++) {
                if ($tokens[$body] === ';') {
                    break;
                }
                if ($tokens[$body] === '{') {
                    $open = $body;
                    break;
                }
            }
            if ($open === null) {
                throw new RuntimeException($relative . ':' . $this->lineAt($file['code'], $start)
                    . ' method parse ambiguity: ' . $method . ' has no body');
            }

            $bodyOffset = 0;
            for ($part = 0; $part <= $open; $part++) {
                $bodyOffset += strlen(is_array($tokens[$part]) ? $tokens[$part][1] : $tokens[$part]);
            }
            $depth = 1;
            $endOffset = $bodyOffset;
            for ($body = $open + 1; $body < $count; $body++) {
                $piece = is_array($tokens[$body]) ? $tokens[$body][1] : $tokens[$body];
                $endOffset += strlen($piece);
                if ($tokens[$body] === '{') {
                    $depth++;
                } elseif ($tokens[$body] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            if ($depth !== 0) {
                throw new RuntimeException($relative . ':' . $this->lineAt($file['code'], $start)
                    . ' method parse ambiguity: unbalanced body for ' . $method);
            }
            $methodCode = substr($file['code'], $start, $endOffset - $start);
            $matches[] = [
                'relative' => $relative,
                'path' => $file['path'],
                'code' => $methodCode,
                'scan' => $this->maskPhpComments($methodCode),
                'line' => $this->lineAt($file['code'], $start),
            ];
            $offset += strlen($text);
        }

        if (count($matches) !== 1) {
            throw new RuntimeException($relative . ':1 expected exactly one PHP method ' . $method
                . ', found ' . count($matches));
        }
        return $matches[0];
    }

    public function jsFunction($relative, $name)
    {
        $file = $this->source($relative);
        $pattern = '/(?:\bfunction\s+' . preg_quote($name, '/') . '\s*|\b' . preg_quote($name, '/') . '\s*)\([^)]*\)\s*\{/';
        if (preg_match_all($pattern, $file['code'], $matches, PREG_OFFSET_CAPTURE) !== 1) {
            $found = isset($matches[0]) ? count($matches[0]) : 0;
            throw new RuntimeException($relative . ':1 expected exactly one JavaScript function/method ' . $name
                . ', found ' . $found);
        }
        $start = $matches[0][0][1];
        $open = $start + strrpos($matches[0][0][0], '{');
        $end = $this->balancedJsBrace($file['code'], $open, $relative);
        $methodCode = substr($file['code'], $start, $end - $start + 1);
        return [
            'relative' => $relative,
            'path' => $file['path'],
            'code' => $methodCode,
            'scan' => $this->maskJsComments($methodCode),
            'line' => $this->lineAt($file['code'], $start),
        ];
    }

    private function balancedJsBrace($source, $open, $relative)
    {
        $depth = 0;
        $state = 'code';
        $length = strlen($source);
        for ($index = $open; $index < $length; $index++) {
            $char = $source[$index];
            $next = $index + 1 < $length ? $source[$index + 1] : '';
            if ($state === 'line-comment') {
                if ($char === "\n") $state = 'code';
                continue;
            }
            if ($state === 'block-comment') {
                if ($char === '*' && $next === '/') { $state = 'code'; $index++; }
                continue;
            }
            if (in_array($state, ['single', 'double', 'template'], true)) {
                if ($char === '\\') { $index++; continue; }
                $closing = $state === 'single' ? "'" : ($state === 'double' ? '"' : '`');
                if ($char === $closing) $state = 'code';
                continue;
            }
            if ($char === '/' && $next === '/') { $state = 'line-comment'; $index++; continue; }
            if ($char === '/' && $next === '*') { $state = 'block-comment'; $index++; continue; }
            if ($char === "'") { $state = 'single'; continue; }
            if ($char === '"') { $state = 'double'; continue; }
            if ($char === '`') { $state = 'template'; continue; }
            if ($char === '{') $depth++;
            if ($char === '}') {
                $depth--;
                if ($depth === 0) return $index;
            }
        }
        throw new RuntimeException($relative . ':' . $this->lineAt($source, $open)
            . ' JavaScript parse ambiguity: unbalanced method body');
    }

    private function maskPhpComments($source)
    {
        $masked = '';
        $tokens = token_get_all('<?php ' . $source);
        foreach ($tokens as $index => $token) {
            $text = is_array($token) ? $token[1] : $token;
            if ($index === 0 && is_array($token) && $token[0] === T_OPEN_TAG) {
                continue;
            }
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $masked .= preg_replace('/[^\r\n]/', ' ', $text);
            } else {
                $masked .= $text;
            }
        }
        return $masked;
    }

    private function maskJsComments($source)
    {
        $masked = $source;
        $state = 'code';
        $length = strlen($source);
        for ($index = 0; $index < $length; $index++) {
            $char = $source[$index];
            $next = $index + 1 < $length ? $source[$index + 1] : '';
            if ($state === 'line-comment') {
                if ($char === "\n") { $state = 'code'; } else { $masked[$index] = ' '; }
                continue;
            }
            if ($state === 'block-comment') {
                if ($char === '*' && $next === '/') {
                    $masked[$index] = $masked[$index + 1] = ' ';
                    $state = 'code';
                    $index++;
                } elseif ($char !== "\n" && $char !== "\r") {
                    $masked[$index] = ' ';
                }
                continue;
            }
            if (in_array($state, ['single', 'double', 'template'], true)) {
                if ($char === '\\') { $index++; continue; }
                $closing = $state === 'single' ? "'" : ($state === 'double' ? '"' : '`');
                if ($char === $closing) $state = 'code';
                continue;
            }
            if ($char === '/' && $next === '/') {
                $masked[$index] = $masked[$index + 1] = ' ';
                $state = 'line-comment';
                $index++;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $masked[$index] = $masked[$index + 1] = ' ';
                $state = 'block-comment';
                $index++;
                continue;
            }
            if ($char === "'") $state = 'single';
            elseif ($char === '"') $state = 'double';
            elseif ($char === '`') $state = 'template';
        }
        return $masked;
    }

    public function requirePattern(array $slice, $pattern, $message, $expected = 1)
    {
        $scan = isset($slice['scan']) ? $slice['scan'] : $slice['code'];
        $count = preg_match_all($pattern, $scan, $matches, PREG_OFFSET_CAPTURE);
        $this->checked++;
        if ($count === false || $count !== $expected) {
            $line = $slice['line'];
            if ($count > 0 && isset($matches[0][0][1])) {
                $line += substr_count(substr($scan, 0, $matches[0][0][1]), "\n");
            }
            $this->failures[] = $slice['relative'] . ':' . $line . ' ' . $message
                . ' (expected ' . $expected . ', found ' . ($count === false ? 'parse-error' : $count) . ')';
        }
    }

    public function forbidPattern(array $slice, $pattern, $message)
    {
        $this->requirePattern($slice, $pattern, $message, 0);
    }

    public function requireBefore(array $slice, $beforePattern, $afterPattern, $message)
    {
        $scan = isset($slice['scan']) ? $slice['scan'] : $slice['code'];
        $beforeCount = preg_match_all($beforePattern, $scan, $before, PREG_OFFSET_CAPTURE);
        $afterCount = preg_match_all($afterPattern, $scan, $after, PREG_OFFSET_CAPTURE);
        $this->checked++;
        if ($beforeCount !== 1 || $afterCount !== 1 || $before[0][0][1] >= $after[0][0][1]) {
            $offset = $beforeCount > 0 ? $before[0][0][1] : ($afterCount > 0 ? $after[0][0][1] : 0);
            $line = $slice['line'] + substr_count(substr($scan, 0, $offset), "\n");
            $this->failures[] = $slice['relative'] . ':' . $line . ' ' . $message
                . ' (before=' . $beforeCount . ', after=' . $afterCount . ')';
        }
    }

    public function check($condition, array $slice, $message)
    {
        $this->checked++;
        if (!$condition) {
            $this->failures[] = $slice['relative'] . ':' . $slice['line'] . ' ' . $message;
        }
    }

    public function fatal(Throwable $throwable)
    {
        $this->failures[] = $throwable->getMessage();
    }

    public function finish()
    {
        if ($this->checked === 0) {
            $this->failures[] = $this->id . ':1 detector checked zero contracts';
        }
        foreach ($this->failures as $failure) {
            fwrite(STDERR, 'SOURCE-CONTRACT FAIL ' . $failure . "\n");
        }
        printf(
            "SOURCE-CONTRACT id=%s checked=%d failures=%d runtime=%.4fs\n",
            $this->id,
            $this->checked,
            count($this->failures),
            microtime(true) - $this->started
        );
        exit($this->failures ? 1 : 0);
    }

    private function lineAt($source, $offset)
    {
        return 1 + substr_count(substr($source, 0, $offset), "\n");
    }
}
