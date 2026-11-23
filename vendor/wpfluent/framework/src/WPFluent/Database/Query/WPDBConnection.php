<?php

/*
 * WPDB Connection
 */

namespace WPSocialReviews\Framework\Database\Query;

use Closure;
use Exception;
use LogicException;
use RuntimeException;
use DateTimeInterface;
use WPSocialReviews\Framework\Foundation\App;
use WPSocialReviews\Framework\Support\DebugLog;
use WPSocialReviews\Framework\Database\Schema;
use WPSocialReviews\Framework\Database\QueryException;
use WPSocialReviews\Framework\Database\UniqueConstraintViolationException;
use WPSocialReviews\Framework\Database\ConnectionInterface;
use WPSocialReviews\Framework\Database\MultipleColumnsSelectedException;
use WPSocialReviews\Framework\Database\Events\QueryExecuted;
use WPSocialReviews\Framework\Database\Query\Expression;
use WPSocialReviews\Framework\Database\Query\Processors\Processor;
use WPSocialReviews\Framework\Database\Query\Processors\MySqlProcessor;
use WPSocialReviews\Framework\Database\Query\Builder as QueryBuilder;
use WPSocialReviews\Framework\Database\Query\Grammars\Grammar;
use WPSocialReviews\Framework\Database\Query\Grammars\MySqlGrammar;
use WPSocialReviews\Framework\Database\Concerns\ManagesTransactions;
use WPSocialReviews\Framework\Database\DetectsLostConnections;
use WPSocialReviews\Framework\Database\DetectsConcurrencyErrors;

use WPSocialReviews\Framework\Database\Events\TransactionBeginning;
use WPSocialReviews\Framework\Database\Events\TransactionCommitted;
use WPSocialReviews\Framework\Database\Events\TransactionCommitting;
use WPSocialReviews\Framework\Database\Events\TransactionRolledBack;

class WPDBConnection implements ConnectionInterface
{
    use DetectsLostConnections, DetectsConcurrencyErrors, ManagesTransactions;

    /**
     * $wpdb Global $wpdb instance
     * @var Object
     */
    protected $wpdb;

    /**
     * The name of the connected database.
     *
     * @var string
     */
    protected $database;

    /**
     * The database connection configuration options.
     *
     * @var array
     */
    protected $config = [];

    /**
     * The query grammar implementation.
     *
     * @var \WPSocialReviews\Framework\Database\Query\Grammars\Grammar
     */
    protected $queryGrammar;

    /**
     * The query post processor implementation.
     *
     * @var \WPSocialReviews\Framework\Database\Query\Processors\Processor
     */
    protected $postProcessor;

    /**
     * The number of active transactions.
     *
     * @var int
     */
    protected $transactions = 0;

    /**
     * The transaction manager instance.
     *
     * @var \WPSocialReviews\Framework\Database\DatabaseTransactionsManager|null
     */
    protected $transactionsManager;

    /**
     * All of the callbacks that should be invoked before a transaction is started.
     *
     * @var \Closure[]
     */
    protected $beforeStartingTransaction = [];

    /**
     * The event dispatcher.
     *
     * @var \WPSocialReviews\Framework\Events\Dispatcher
     */
    protected $event = null;

    /**
     * Create a new database connection instance.
     *
     * @param \wpdb $wpdb The WordPress database instance.
     * @return void
     */
    public function __construct($wpdb)
    {
        $this->setupWpdbInstance($wpdb);

        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();

        $this->event = App::make('events');
    }

    /**
     * Populate $wpdb instance.
     *
     * Whether $wpdb prints SQL errors is left to WordPress (WP_DEBUG and
     * WP_DEBUG_DISPLAY): $wpdb is shared by every plugin on the site. Only
     * run() holds the printing back, for the length of its own query.
     *
     * @param  $wpdb Global $wpdb instance
     * @return Null
     */
    protected function setupWpdbInstance($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    /**
     * Set the query grammar to the default implementation.
     *
     * @return void
     */
    public function useDefaultQueryGrammar()
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \WPSocialReviews\Framework\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new MySqlGrammar;
    }

    /**
     * Set the query post processor to the default implementation.
     *
     * @return void
     */
    public function useDefaultPostProcessor()
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Get the default post processor instance.
     *
     * @return \WPSocialReviews\Framework\Database\Query\Processors\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new MySqlProcessor;
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param \Closure|\WPSocialReviews\Framework\Database\Query\Builder|string $table
     * @param string|null $as
     * @return \WPSocialReviews\Framework\Database\Query\Builder
     */
    public function table($table, $as = null)
    {
        return $this->query()->from($table, $as);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \WPSocialReviews\Framework\Database\Query\Builder
     */
    public function query()
    {
        return new QueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param string $query
     * @param array $bindings
     * @return mixed
     */
    public function selectOne($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $query = $this->bindParams($query, $bindings);

            $result = $this->wpdb->get_row($query);

            if ($result === false || $this->wpdb->last_error) {
                throw $this->queryException(
                    $query, $bindings, $this->wpdb->last_error
                );
            }

            return $result;
        });
    }

    /**
     * Run a select statement and return the first column of the first row.
     *
     * @param string $query
     * @param array $bindings
     * @return mixed
     *
     * @throws \WPSocialReviews\Framework\Database\MultipleColumnsSelectedException
     */
    public function scalar($query, $bindings = [])
    {
        $record = $this->selectOne($query, $bindings);

        if (is_null($record)) {
            return null;
        }

        $record = (array)$record;

        if (count($record) > 1) {
            throw new MultipleColumnsSelectedException(
                'The query returned more than one column.'
            );
        }

        return reset($record);
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return array
     */
    public function select($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $query = $this->bindParams($query, $bindings);

            $result = $this->wpdb->get_results($query);

            if ($result === false || $this->wpdb->last_error) {
                throw $this->queryException(
                    $query, $bindings, $this->wpdb->last_error
                );
            }

            return $result;
        });
    }

    /**
     * The query as the database receives it: its values filled in and
     * escaped as they are when it runs, and each % as written.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return string
     */
    public function toRawSql($query, array $bindings = [])
    {
        return $this->wpdb->remove_placeholder_escape(
            $this->bindParams($query, $bindings)
        );
    }

    /**
     * Escape a value for use in SQL, as WordPress escapes it.
     *
     * @param  string|float|int|bool|null  $value
     * @param  bool  $binary
     * @return string
     *
     * @throws \RuntimeException
     */
    public function escape($value, $binary = false)
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($binary) {
            return "x'" . bin2hex($value) . "'";
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            throw new RuntimeException(
                'The database connection does not support escaping arrays.'
            );
        }

        $value = (string) $value;

        if (strpos($value, "\0") !== false) {
            throw new RuntimeException(
                'Strings with null bytes cannot be escaped. Use the binary escape option.'
            );
        }

        if (preg_match('//u', $value) === false) {
            throw new RuntimeException(
                'Strings with invalid UTF-8 byte sequences cannot be escaped.'
            );
        }

        $escaped = $this->wpdb->remove_placeholder_escape(
            $this->wpdb->_real_escape($value)
        );

        return "'{$escaped}'";
    }

    /**
     * Bind the parameters into SQL query
     *
     * @param $query
     * @param $bindings
     * @return string
     */
    protected function bindParams(string $query, array $bindings)
    {
        $bindings = $this->prepareBindings($bindings);

        [$sql, $values] = $this->compileQuery($query, $bindings, '%s', true);

        if (empty($values)) {
            return $this->compileQuery($query, $bindings, '%s', false)[0];
        }

        if ($this->wpdb->dbh instanceof \mysqli) {
            return $this->wpdb->prepare($sql, ...$values);
        }

        return vsprintf($sql, array_map(function ($value) {
            if (is_string($value)) return "'" . esc_sql($value) . "'";
            return (string) $value;
        }, $values));
    }

    /**
     * Rewrite a query for wpdb, outside quoted values and comments only:
     * a "quoted" identifier gets backticks, each ? placeholder becomes
     * $placeholder (or NULL for a null binding, which a prepared %s or a
     * bound parameter would turn into ''), and with $escapePercent every
     * % is doubled for wpdb->prepare(). A " or ? inside 'a value', a
     * `name` or a comment is left as it is.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @param  string  $placeholder  '%s' for wpdb->prepare(), '?' for mysqli.
     * @param  bool    $escapePercent
     * @return array{0:string,1:array}  The query and the bindings its placeholders take.
     */
    protected function compileQuery(string $query, array $bindings, string $placeholder, bool $escapePercent): array
    {
        $bindings = array_values($bindings);
        $percent = $escapePercent ? '%%' : '%';
        $length = strlen($query);
        $sql = '';
        $values = [];
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            // Copy the run of characters that need no rewriting at once.
            if ($run = strcspn($query, "'`-#/?\"%", $i)) {
                $sql .= substr($query, $i, $run);

                if (($i += $run) >= $length) {
                    break;
                }
            }

            $char = $query[$i];

            if ($char === "'" || $char === '`') {
                $end = $this->endOfQuoted($query, $i, $char);
            } elseif ($char === '-' && preg_match('/\G--(\s|$)/', $query, $m, 0, $i)) {
                $end = $this->endOfLine($query, $i);
            } elseif ($char === '#') {
                $end = $this->endOfLine($query, $i);
            } elseif ($char === '/' && substr($query, $i, 2) === '/*') {
                $end = ($close = strpos($query, '*/', $i + 2)) === false ? $length - 1 : $close + 1;
            } else {
                $end = null;
            }

            if ($end !== null) {
                $sql .= str_replace('%', $percent, substr($query, $i, $end - $i + 1));
                $i = $end;
            } elseif ($char === '?') {
                if (array_key_exists($index, $bindings) && $bindings[$index] === null) {
                    $sql .= 'NULL';
                } else {
                    $sql .= $placeholder;

                    if (array_key_exists($index, $bindings)) {
                        $values[] = $bindings[$index];
                    }
                }

                $index++;
            } elseif ($char === '"') {
                $sql .= '`';
            } elseif ($char === '%') {
                $sql .= $percent;
            } else {
                $sql .= $char;
            }
        }

        // An extra binding shifts every later one; a missing one empties
        // the query.
        if ($index !== count($bindings)) {
            DebugLog::warn(sprintf(
                'The query "%s" has %d placeholder(s) (?) and %d binding(s); each ? takes one binding, in order.',
                strlen($query) > 200 ? substr($query, 0, 200) . '…' : $query,
                $index,
                count($bindings)
            ));
        }

        return [$sql, $values];
    }

    /**
     * Find the closing quote of the value or name that opens at $start. A
     * doubled quote ('' or ``) and, in a value, a backslash escape stay
     * inside it.
     *
     * @param  string  $query
     * @param  int     $start
     * @param  string  $quote
     * @return int
     */
    protected function endOfQuoted(string $query, int $start, string $quote): int
    {
        $length = strlen($query);

        for ($i = $start + 1; $i < $length; $i++) {
            if ($query[$i] === '\\' && $quote === "'") {
                $i++;
            } elseif ($query[$i] === $quote) {
                if (($query[$i + 1] ?? '') !== $quote) {
                    return $i;
                }

                $i++;
            }
        }

        return $length - 1;
    }

    /**
     * Find the end of the line comment that starts at $start.
     *
     * @param  string  $query
     * @param  int     $start
     * @return int
     */
    protected function endOfLine(string $query, int $start): int
    {
        $end = strpos($query, "\n", $start);

        return $end === false ? strlen($query) - 1 : $end;
    }

    /**
     * Run a select statement and yield its rows one at a time.
     *
     * On MySQL the rows are fetched from a prepared statement one by one,
     * so PHP never holds them all as objects; mysqli still buffers the raw
     * result (get_result()), so other queries can run inside the loop.
     * On other drivers (the WordPress SQLite plugin) it runs as select().
     *
     * @param string $query
     * @param array $bindings
     * @return \Generator
     * @throws \WPSocialReviews\Framework\Database\QueryException
     */
    public function cursor($query, $bindings = [])
    {
        if (!$this->wpdb->dbh instanceof \mysqli) {
            yield from $this->select($query, $bindings);

            return;
        }

        [$statement, $result] = $this->run($query, $bindings, function ($query, $bindings) {
            return $this->executeCursorStatement($query, $bindings);
        });

        try {
            while ($result && ($row = $result->fetch_assoc())) {
                yield (object) $row;
            }
        } finally {
            if ($result) {
                $result->free();
            }

            $statement->close();
        }
    }

    /**
     * Prepare, bind and execute a cursor's statement.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return array{0: \mysqli_stmt, 1: \mysqli_result|false}
     * @throws \WPSocialReviews\Framework\Database\QueryException
     */
    protected function executeCursorStatement($query, $bindings)
    {
        [$preparedQuery, $values] = $this->compileQuery(
            $query, $this->prepareBindings($bindings), '?', false
        );

        $this->prepareWpdbForCursor($query, $bindings);

        $start = microtime(true);

        $statement = $this->wpdb->dbh->prepare($preparedQuery);

        if ($statement === false) {
            $this->logCursorQuery($preparedQuery, $start);

            throw $this->cursorException(
                $query, $bindings, $this->wpdb->dbh->error, $this->wpdb->dbh->errno
            );
        }

        if (!empty($values)) {
            $types = '';

            foreach ($values as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }

            $statement->bind_param($types, ...$values);
        }

        $executed = $statement->execute();

        $result = $executed ? $statement->get_result() : false;

        $this->logCursorQuery($preparedQuery, $start);

        if (!$executed || ($result === false && $statement->errno)) {
            $error = $statement->error ?: 'Mysqli Error No: ' . $statement->errno;
            $errno = $statement->errno;

            $statement->close();

            throw $this->cursorException($query, $bindings, $error, $errno);
        }

        return [$statement, $result];
    }

    /**
     * Run a select statement and stream its rows from MySQL unbuffered.
     *
     * Nothing is held in memory, but the connection is busy until the last
     * row is read or the loop stops, so every other query in the loop (the
     * plugin's own or WordPress's, which all go through wpdb::query()) is
     * refused with a LogicException instead of failing with "Commands out
     * of sync", or silently inside WordPress functions. On other drivers
     * (the WordPress SQLite plugin) it runs as select().
     *
     * @param  string $query
     * @param  array  $bindings
     * @return \Generator
     * @throws \WPSocialReviews\Framework\Database\QueryException
     */
    public function rawCursor($query, $bindings = [])
    {
        if (!$this->wpdb->dbh instanceof \mysqli) {
            yield from $this->select($query, $bindings);

            return;
        }

        $result = $this->run($query, $bindings, function ($query, $bindings) {
            $sql = $this->bindParams($query, $bindings);

            $this->prepareWpdbForCursor($query, $bindings);

            $start = microtime(true);

            $result = $this->wpdb->dbh->query($sql, MYSQLI_USE_RESULT);

            $this->logCursorQuery($sql, $start);

            if ($result === false) {
                throw $this->cursorException(
                    $query, $bindings, $this->wpdb->dbh->error, $this->wpdb->dbh->errno
                );
            }

            return $result;
        });

        if (!$result instanceof \mysqli_result) {
            return;
        }

        $refuseQueries = function () {
            throw new LogicException(
                'The database connection is busy streaming a rawCursor() result: '
                . 'no other query can run until that loop ends. Use lazyById() '
                . 'when the loop needs the database.'
            );
        };

        add_filter('query', $refuseQueries, PHP_INT_MIN);

        try {
            while ($row = $result->fetch_assoc()) {
                yield (object) $row;
            }
        } finally {
            remove_filter('query', $refuseQueries, PHP_INT_MIN);

            $result->free();
        }
    }

    /**
     * Reset wpdb's per-query state and make sure it is connected, as
     * wpdb::query() does before running a query.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return void
     * @throws \WPSocialReviews\Framework\Database\QueryException
     */
    protected function prepareWpdbForCursor($query, $bindings)
    {
        $this->wpdb->flush();
        $this->wpdb->insert_id = 0;

        if (!$this->wpdb->check_connection(false)) {
            throw new QueryException(
                $query, $bindings, new Exception(
                    $this->wpdb->last_error ?: 'Error reconnecting to database.'
                )
            );
        }
    }

    /**
     * Count a cursor's query as wpdb counts its own, and add it to
     * WordPress's query log when queries are saved (SAVEQUERIES).
     *
     * @param  string  $sql  The query as the database received it.
     * @param  float  $start  When it started, from microtime(true).
     * @return void
     */
    protected function logCursorQuery($sql, $start)
    {
        $this->wpdb->num_queries++;

        if ($this->savesQueries() && method_exists($this->wpdb, 'log_query')) {
            $this->wpdb->log_query(
                $sql, microtime(true) - $start, $this->wpdb->get_caller(), $start, []
            );
        }
    }

    /**
     * Whether WordPress saves the queries it runs (SAVEQUERIES).
     *
     * @return bool
     */
    protected function savesQueries()
    {
        return defined('SAVEQUERIES') && SAVEQUERIES;
    }

    /**
     * Record a cursor's error on wpdb, as wpdb records its own, and make
     * the exception for it.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  string  $error
     * @param  int|null  $errno
     * @return \WPSocialReviews\Framework\Database\QueryException
     */
    protected function cursorException($query, $bindings, $error, $errno = null)
    {
        $this->wpdb->last_error = $error;

        return $this->queryException($query, $bindings, $error, $errno);
    }

    /**
     * Run an insert statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $query = $this->bindParams($query, $bindings, true);

            $result = $this->unprepared($query);

            if ($result === false || $this->wpdb->last_error) {
                throw $this->queryException(
                    $query, $bindings, $this->wpdb->last_error
                );
            }

            return $result;
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $query = $this->bindParams($query, $bindings, true);

            $result = $this->wpdb->query($query);

            if ($result === false || $this->wpdb->last_error) {
                throw $this->queryException(
                    $query, $bindings, $this->wpdb->last_error
                );
            }

            return intval($result);
        });
    }

    /**
     * Run a statement that opens, closes or nests a transaction. It throws
     * when the database refuses it, as any query does, so a failed COMMIT
     * or SAVEPOINT isn't taken for a success. $wpdb's error box is held
     * back meanwhile, as in run().
     *
     * @param  string  $sql
     * @return int|bool
     *
     * @throws \WPSocialReviews\Framework\Database\QueryException
     */
    protected function transactionStatement($sql)
    {
        $wasShowingErrors = $this->wpdb->hide_errors();

        try {
            $result = $this->wpdb->query($sql);

            if ($result === false || $this->wpdb->last_error) {
                throw $this->queryException(
                    $sql, [], $this->wpdb->last_error ?: 'The statement failed.'
                );
            }

            return $result;
        } finally {
            $this->wpdb->show_errors($wasShowingErrors);
        }
    }

    /**
     * Connect to the database again, when the connection was lost.
     *
     * @return void
     */
    public function reconnect()
    {
        $this->wpdb->check_connection(false);
    }

    /**
     * Run a raw, unprepared query.
     *
     * @param string $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->wpdb->query($query);
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param \Closure $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {
        // ...
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param array $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into
            // the actual date string. Each query grammar maintains its
            // own date string format so we'll just ask the grammar
            // for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (int)$value;
            }
        }

        return $bindings;
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  string $query
     * @param  array $bindings
     * @param  \Closure $callback
     * @return mixed
     */
    public function run($query, $bindings, $callback)
    {
        // A failed query throws a QueryException, which a route sends as a
        // JSON error. $wpdb would print its HTML error box before that JSON,
        // so the box is held back while the query runs; $wpdb still logs it.
        $wasShowingErrors = $this->wpdb->hide_errors();

        $start = microtime(true);

        try {
            return $callback($query, $bindings);
        } finally {
            // Back to the site's own setting: WordPress and the other
            // plugins print their errors through this same $wpdb.
            $this->wpdb->show_errors($wasShowingErrors);

            $time = $this->getElapsedTime($start);
            $this->event->dispatch(
                new QueryExecuted($query, $bindings, $time, $this)
            );
        }
    }

    /**
     * Get a new raw query expression.
     *
     * @param mixed $value
     * @return \WPSocialReviews\Framework\Database\Query\Expression
     */
    public function raw($value)
    {
        return new Expression($value);
    }

    /**
     * Get the query grammar used by the connection.
     *
     * @return \WPSocialReviews\Framework\Database\Query\Grammars\Grammar
     */
    public function getQueryGrammar()
    {
        $this->queryGrammar->setTablePrefix($this->wpdb);

        // The grammar escapes values through the connection.
        $this->queryGrammar->setConnection($this);

        return $this->queryGrammar;
    }

    /**
     * Set the query grammar used by the connection.
     *
     * @param \WPSocialReviews\Framework\Database\Query\Grammars\Grammar $grammar
     * @return $this
     */
    public function setQueryGrammar(Grammar $grammar)
    {
        $this->queryGrammar = $grammar;

        return $this;
    }

    /**
     * Get the query post processor used by the connection.
     *
     * @return \WPSocialReviews\Framework\Database\Query\Processors\Processor
     */
    public function getPostProcessor()
    {
        return $this->postProcessor;
    }

    /**
     * Set the query post processor used by the connection.
     *
     * @param \WPSocialReviews\Framework\Database\Query\Processors\Processor $processor
     * @return $this
     */
    public function setPostProcessor(Processor $processor)
    {
        $this->postProcessor = $processor;

        return $this;
    }

    /**
     * Return the last insert id
     *
     * @param string $args
     *
     * @return int
     */
    public function lastInsertId($args)
    {
        return $this->wpdb->insert_id;
    }

    /**
     * Return self as PDO, the Processor instance uses it.
     *
     * @return \WPSocialReviews\Framework\Database\Query\WPDBConnection
     */
    public function getPdo()
    {
        return $this;
    }

    /**
     * Returns the $wpdb object.
     *
     * @return Object $wpdb
     */
    public function getWPDB()
    {
        return $this->wpdb;
    }

    /**
     * Get the database connection name: mysql, on MySQL and on the SQLite
     * drop-in alike, since it names WordPress's one connection, not its
     * database (isSqlite() tells which).
     *
     * @return string
     */
    public function getName()
    {
        return 'mysql';
    }

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        if ($this->isSqlite()) {
            return 'sqlite';
        }

        return $this->wpdb->dbname;
    }

    /**
     * Get the server version for the connection.
     *
     * @return string
     *
     * @phpstan-ignore-next-line
     */
    public function getServerVersion(): string
    {
        return $this->getWPDB()->db_version();
    }

    /**
     * Get the column listing for a given table.
     *
     * @param string $table
     * @return array
     */
    public function getColumnListing($table)
    {
        return Schema::getColumns($table);
    }

    /**
     * Alias for getColumnListing.
     *
     * @param  string  $t
     * @return array
     */
    public function getColumns($t)
    {
        return $this->getColumnListing($t);
    }

    /**
     * Determine if the connected database is a sqlite database.
     *
     * @return bool
     */
    public function isSqlite()
    {
        return Schema::isSqlite();
    }

    /**
     * Whether the site runs on the SQLite drop-in's old engine
     * (WP_SQLite_Translator, the 2.x default), which rewrites MySQL token by
     * token. Its MySQL engine (every 3.x, and 2.x with WP_SQLITE_AST_DRIVER)
     * parses MySQL, and behaves as MySQL where the old one doesn't.
     *
     * @return bool
     */
    public function usesSqliteTranslator()
    {
        return is_a($this->wpdb->dbh, 'WP_SQLite_Translator');
    }

    /**
     * Whether the database nests transactions itself.
     *
     * The SQLite drop-in's old engine doesn't know SAVEPOINT, and reads
     * ROLLBACK TO SAVEPOINT as a ROLLBACK of everything. It nests START
     * TRANSACTION instead: inside a transaction it opens a savepoint, and
     * COMMIT or ROLLBACK closes the innermost one. MySQL, and the drop-in's
     * MySQL engine, use savepoints.
     *
     * @return bool
     */
    public function nestsTransactionsItself()
    {
        return $this->usesSqliteTranslator();
    }

    /**
     * Determine if the connected database is a mariadb database.
     *
     * @return bool
     */
    public function isMaria()
    {
        return Schema::isMaria();
    }

    /**
     * Register a hook to be run just before a database transaction is started.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function beforeStartingTransaction(Closure $callback)
    {
        $this->beforeStartingTransaction[] = $callback;

        return $this;
    }

    /**
     * Register a database query listener with the connection.
     *
     * @param \Closure $callback
     * @return void
     */
    public function listen(Closure $callback)
    {
        $this->event->listen(QueryExecuted::class, $callback);
    }

    /**
     * Fire an event for this connection.
     *
     * @param  string  $event
     * @return array|null
     */
    protected function fireConnectionEvent($event)
    {
        if (!$this->event) {
            return;
        }

        switch ($event) {
            case 'beganTransaction':
                $payload = new TransactionBeginning($this);
                break;
            case 'committed':
                $payload = new TransactionCommitted($this);
                break;
            case 'committing':
                $payload = new TransactionCommitting($this);
                break;
            case 'rollingBack':
                $payload = new TransactionRolledBack($this);
                break;
            default:
                $payload = null;
                break;
        }

        if ($payload !== null) {
            return $this->event->dispatch($payload);
        }
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param int $start
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Get the current site's table prefix: after switch_to_blog(), the
     * site switched to.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->wpdb->prefix;
    }

    /**
     * Get the table name with the table prefix.
     * 
     * @param  string $table
     * @return string       
     */
    public function getTableName($table)
    {
        return $this->getTablePrefix() . $table;
    }

    /**
     * Build the exception for a failed query: a unique key it broke
     * gives a UniqueConstraintViolationException, so createOrFirst() can
     * find the row another request created; anything else a
     * QueryException.
     *
     * @param  string   $query
     * @param  array    $bindings
     * @param  string   $message
     * @param  int|null $errno The MySQL error number, when known.
     * @return \WPSocialReviews\Framework\Database\QueryException
     */
    protected function queryException($query, $bindings, $message, $errno = null)
    {
        $previous = new Exception($message);

        return $this->isUniqueConstraintViolation($message, $errno)
            ? new UniqueConstraintViolationException($query, $bindings, $previous)
            : new QueryException($query, $bindings, $previous);
    }

    /**
     * Whether a failed query broke a unique key. On MySQL the error
     * number (1062) decides; otherwise the message does, in the exact
     * form MySQL or SQLite writes it, since the SQLite plugin's message
     * also carries the whole query and its values.
     *
     * @param  string   $message
     * @param  int|null $errno
     * @return bool
     */
    protected function isUniqueConstraintViolation($message, $errno = null)
    {
        if (is_null($errno) && $this->wpdb->dbh instanceof \mysqli) {
            $errno = $this->wpdb->dbh->errno;
        }

        if ($errno) {
            return (int) $errno === 1062;
        }

        // MySQL: "Duplicate entry 'a' for key 'sku'", the whole message.
        // SQLite: "SQLSTATE[23000]: Integrity constraint violation: 19
        // UNIQUE constraint failed: wp_items.sku".
        return (bool) preg_match(
            "/^Duplicate entry '.*' for key '|Integrity constraint violation: 19 UNIQUE constraint failed/s",
            (string) $message
        );
    }
}
