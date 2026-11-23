<?php

namespace WPSocialReviews\Framework\Support;

use RuntimeException;

class Env
{
    protected static $localStore = [];

    /**
     * Load environment variables from a file.
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(
                "Environment file not found at: $filePath"
            );
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $line, 2), 2, null);
            $name = trim($name);
            $value = trim((string) $value, "\"'");

            if ($name === '') {
                continue;
            }

            static::set($name, static::normalize($value));
        }
    }

    /**
     * Get an environment variable, or the default when it is missing or
     * null (KEY=null in the file), as in Laravel. false, '' and 0 are values.
     */
    public static function get(string $key, $default = null)
    {
        return static::$localStore[$key] ?? $default;
    }

    /**
     * Set an environment variable.
     */
    public static function set(string $key, $value): void
    {
        static::$localStore[$key] = $value;
    }

    /**
     * Determine whether a variable is in the store, null or not.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, static::$localStore);
    }

    /**
     * Remove a variable from the store.
     */
    public static function forget(string $key): void
    {
        unset(static::$localStore[$key]);
    }

    /**
     * Get all environment variables.
     */
    public static function all(): array
    {
        return static::$localStore;
    }

    /**
     * Dump and die.
     */
    public static function dd(): void
    {
        if (function_exists('dd')) {
            dd(static::all());
        } else {
            print_r(static::all());
            die;
        }
    }

    /**
     * Normalize string values to PHP native types.
     */
    protected static function normalize($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = strtolower(trim($value));

        // As in Laravel: only these words change; 1, 0 and any other text
        // stay text, for the code that reads them to cast.
        switch ($trimmed) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'null':
            case '(null)':
                return null;

            case 'empty':
            case '(empty)':
                return '';

            default:
                return $value;
        }
    }
}
