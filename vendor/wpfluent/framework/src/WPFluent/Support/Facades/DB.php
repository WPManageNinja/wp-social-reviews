<?php

namespace WPSocialReviews\Framework\Support\Facades;

use WPSocialReviews\Framework\Support\Facade;

/**
 * Static calls to the database manager.
 *
 * @method static \WPSocialReviews\Framework\Database\Query\WPDBConnection connection(string|null $name = null)
 * @method static \WPSocialReviews\Framework\Database\Query\Builder table(string $table, string|null $as = null)
 * @method static \WPSocialReviews\Framework\Database\Query\Expression raw(mixed $value)
 * @method static object|null selectOne(string $query, array $bindings = [])
 * @method static mixed scalar(string $query, array $bindings = [])
 * @method static array select(string $query, array $bindings = [])
 * @method static bool insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static bool unprepared(string $query)
 * @method static mixed transaction(\Closure $callback, int $attempts = 1)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 *
 * @see \WPSocialReviews\Framework\Database\DatabaseManager
 * @see \WPSocialReviews\Framework\Database\Query\WPDBConnection
 */
class DB extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'db';
    }
}
