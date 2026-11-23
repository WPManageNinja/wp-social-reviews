<?php

namespace WPSocialReviews\Framework\Database;

use Exception;
use WPSocialReviews\Framework\Database\Concerns\MaintainsDatabase;

class Schema
{
	use MaintainsDatabase;

	/**
	 * Get the global $wpdb instance
	 * 
	 * @return \wpdb The global $wpdb
	 */
	public static function db()
	{
		return $GLOBALS['wpdb'];
	}

	/**
	 * Get schema/db information. The database password is never part of
	 * it, as in Laravel's db:show: information is for showing, and the
	 * password must not end up on a screen, in a log or in a response.
	 *
	 * @return string|array
	 */
	public static function getInfo($key = null)
	{
		$db = static::db();

		if (static::isSqlite()) {
			$info = [
			    'dbname' => 'sqlite',
			    'prefix' => $db->prefix,
			    'dbhost' => '',
			    'username' => '',
			    'charset' => 'utf8',
			    'collation' => '',
			    'tables' => static::getTableList(),
			];

			return $key ? ($info[$key] ?? null) : $info;
		}

		$info = [
		    // @phpstan-ignore-next-line
		    'dbname' => $db->dbname,
		    'prefix' => $db->prefix,
		    // @phpstan-ignore-next-line
		    'dbhost' => $db->dbhost,
		    // @phpstan-ignore-next-line
		    'username' => $db->dbuser,
		    'charset' => $db->charset,
		    'collation' => $db->collate,
		    'tables' => static::getTableList(),
		];

		return $key ? ($info[$key] ?? null) : $info;
	}

	/**
	 * Migrates database table(s)
	 * 
	 * @param  string|array $table The table name without prefix
	 *  or an array where each key is table name and value is sql.
	 *  
	 * @param  string $sql Optional
	 * @return mixed
	 */
	public static function migrate($table, $sql = null)
	{
		if (!$sql && is_array($table)) {
			$result = [];
			foreach ($table as $t => $s) {
				$result = array_merge(
					$result, (array) static::createTable($t, $s)
				);
			}
			return $result;
		} else {
			return static::createTable($table, $sql);
		}
	}

	/**
	 * Creates a new table if doesn't exist using dbDelta function
	 * 
	 * @param  string $table The table name without the prefix
	 * @param  string $sql   The sql to create table or an absolute path of a
	 * .sql file containing the column definations for creating the new table.
	 * 
	 * @return string Message
	 */
	public static function createTableIfNotExist($table, $sql)
	{
		if (!static::hasTable($table)) {
			return static::createTable($table, $sql);
		}
	}

	/**
	 * Checks if a column exists in a table
	 * 
	 * @param  string $column The column name of the table
	 * @param  string $table The table name without prefix
	 * @return boolean
	 */

	public static function hasColumn($column, $table)
    {
        $wpdb = static::db();

		$table = static::table($table);

        $columns = $wpdb->get_col("DESCRIBE $table");

        return in_array($column, $columns);
    }

	/**
	 * Checks if a table exists
	 * 
	 * @param  string $table The table name without prefix
	 * @return boolean
	 */
	public static function hasTable($table)
	{
		$wpdb = static::db();

		$table = static::table($table);

		// The name is a value. LIKE reads _ in it as any character, and the
		// SQLite drop-in's old engine ignores an escaped one, so the exact
		// name is looked for among the tables that match.
		$result = in_array($table, (array) $wpdb->get_col(
			$wpdb->prepare('SHOW TABLES LIKE %s', $table)
		), true);

		// On SQLite, SHOW TABLES lists temporary tables too, so the probe
		// below is not needed; and the drop-in's MySQL engine (3.x) rolls
		// back the open transaction on any failed query, which the probe is.
		if ($result || static::isSqlite()) {
			return $result;
		}

		// Check if any temporary table exists by this table name.

		// At first, store the original state of the error suppress
		// error and then turn off the error from being shown, so
		// error will be not shown if there is no temporary
		// table, then restore the error state.
		$isErrorSuppressed = $wpdb->suppress_errors;

		$wpdb->suppress_errors = true;

		static::query("SELECT 1 FROM %{$table}% WHERE 0");

		$hasError = !empty($wpdb->last_error);

		$wpdb->suppress_errors = $isErrorSuppressed;

		return !$hasError;
	}

	/**
	 * Resolves the table prefix and makes the table name with prefix
	 * 
	 * @param  string $table The table name without the prefix
	 * @return string The resolved table name with the prefix
	 */
	public static function table($table)
	{
		$wpdb = static::db();
		
		$prefix = $wpdb->prefix;

		if (strpos($table, $prefix) === 0) {
			return $table;
		}

		return isset($wpdb->{$table}) ? $wpdb->{$table} : ($wpdb->prefix.$table);
	}


    /**
     * Resolves the sql prefix
     *
     * @param string $sql The file name or the raw sql
     * @return string The resolved sql
     */
    public static function sql($sql = '')
    {
        $allowedSqlFileFormats = [
            'sql'
        ];

        foreach ($allowedSqlFileFormats as $format) {
            if (str_ends_with($sql, '.' . $format)) {
                $sql = @file_exists($sql) ? file_get_contents($sql) : $sql;
                break;
            }
        }
        
        return $sql;
    }

    /**
     * Swap the json column type for longtext, which is how SQLite stores it.
     *
     * @param  string $sql
     * @return string
     */
    protected static function jsonToLongtext($sql)
    {
        // Only json used as a type: right after a column name (bare, or in
        // ` or ") and spaces, as a whole word (\b), in any case. '`meta` JSON
        // NULL' -> '`meta` longtext NULL', while a column named json ('`json`
        // text') and names like json_data are left alone.
        return preg_replace(
            '/(`[^`]+`|"[^"]+"|\w+)(\s+)json\b/i', '$1$2longtext', $sql
        );
    }

    /**
     * Clean the sql string by removing the comments.
     * 
     * @param  string $sql
     * @return string
     */
	public static function cleanUp($sql)
	{
	    $sql = static::sql($sql);

	    // Remove comments: -- or # to the end of the line, and /* ... */
	    // (.*? stops at the first */; /s lets it span lines). A quoted value
	    // ('...', "..." or `...`, where \\. is an escaped character) is
	    // matched first and put back as it is, so DEFAULT '#ffffff' keeps
	    // its #.
	    $sql = preg_replace_callback(
	        '/(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|`[^`]*`)|--[^\n]*|#[^\n]*|\/\*.*?\*\//s',
	        function ($match) {
	            return isset($match[1]) ? $match[1] : '';
	        },
	        $sql
	    );

	    // Remove the lines left empty, such as those that held only a
	    // comment: dbDelta() reads an empty line between the columns as an
	    // index with no name, and sends a broken ALTER TABLE for it every
	    // time it runs over an existing table. A quoted value is matched
	    // first and put back as it is, so an empty line inside one stays.
	    $sql = preg_replace_callback(
	        '/(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|`[^`]*`)|^[ \t]*\r?\n/m',
	        function ($match) {
	            return isset($match[1]) ? $match[1] : '';
	        },
	        $sql
	    );

	    // Collapse multiple spaces & newlines
	    // $sql = preg_replace('/\s+/', ' ', $sql);

	    // Remove a comma left before a closing parenthesis, with any spaces
	    // or newlines between: '(post_id, user_id, )' -> '(post_id, user_id)'.
	    $sql = preg_replace('/,\s*\)/', ')', $sql);

	    return trim($sql);
	}

	/**
	 * Creates a new table using dbDelta function or alters the table if exists.
	 * 
	 * @param  string $table The table name without the prefix
	 * @param  string $sql   The sql to create table or an absolute path of a
	 * .sql file containing the column definations for creating the new table.
	 * 
	 * @return string message
	 */
	public static function createTable($table, $sql)
	{
		$name = $table;

		$table = static::table($table);

		$sql = static::cleanUp($sql);

		if (static::isSqlite()) {
            $sql = static::jsonToLongtext($sql);
        }

        $collate = static::db()->get_charset_collate();

        $query = "CREATE TABLE $table (
        		".PHP_EOL.trim(trim($sql), ',').PHP_EOL."
        	) $collate;";

        $result = static::callDBDelta($query);

        // dbDelta reports "Created table" even when the database refused
        // the statement; the error is read before hasTable() queries again.
        $error = static::db()->last_error;

        if (!static::hasTable($name)) {
            throw new QueryException($query, [], new Exception(
                $error ?: "The database did not create the table {$table}."
            ));
        }

        return $result;
	}

	/**
	 * Alters an existing table if exists
	 * 
	 * @param  string $table The table name without the prefix
	 * @param    string $sql   The sql to create table or an absolute path of a
	 * .sql file containing the column definations for creating the new table.
	 * 
	 * @return string message
	 */
	public static function alterTableIfExists($table, $sql)
	{
		if (static::hasTable($table)) {
			return static::alterTable($table, $sql);
		}
	}

	/**
	 * Alters an existing table
	 *
	 * @param  string $table The table name without the prefix
	 * @param  string $sql   The sql to create table or an absolute path of a
	 * .sql file containing the column definations for creating the new table.
	 *
	 * @return string message
	 */
	public static function alterTable($table, $sql)
	{
		$table = static::table($table);

		$sql = static::cleanUp($sql);

		if (static::isSqlite()) {
            $sql = static::jsonToLongtext($sql);
        }

		$parts = array_filter(array_map('trim', static::splitAlterClauses($sql)));

		if (static::isSqlite()) {
			// SQLite only supports one ALTER TABLE operation per statement;
			// false when any of them failed.
			$result = null;
			foreach ($parts as $part) {
				$partResult = static::query("ALTER TABLE $table {$part};");
				$result = ($result === false || $partResult === false) ? false : $partResult;
			}
			return $result;
		}

        $sql = "ALTER TABLE $table ".PHP_EOL.rtrim(
        	trim(implode(','.PHP_EOL, $parts)), ';'
        ).";";

        return static::query($sql);
	}

	/**
	 * Split a comma-separated ALTER TABLE clause list, respecting parentheses
	 * so that DECIMAL(10,2) and similar types are not split mid-definition,
	 * and quotes, so DEFAULT 'a, b' and a comment with a comma stay whole.
	 *
	 * @param  string $sql
	 * @return string[]
	 */
	protected static function splitAlterClauses($sql)
	{
		$parts = [];
		$depth = 0;
		$current = '';
		$quote = null;

		for ($i = 0, $len = strlen($sql); $i < $len; $i++) {
			$char = $sql[$i];
			if ($quote !== null) {
				$current .= $char;
				if ($char === '\\' && $i + 1 < $len) {
					$current .= $sql[++$i];
				} elseif ($char === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($char === "'" || $char === '"' || $char === '`') {
				$quote = $char;
			} elseif ($char === '(') {
				$depth++;
			} elseif ($char === ')') {
				$depth--;
			} elseif ($char === ',' && $depth === 0) {
				$parts[] = $current;
				$current = '';
				continue;
			}
			$current .= $char;
		}

		if ($current !== '') {
			$parts[] = $current;
		}

		return $parts;
	}

	/**
	 * Alters an existing table using dbDelta function if exists, otherwise creates
	 * it. Alters an existing table but takes the table creation column defination.
	 * This is because, the dbDelta functioin can create or update a table using 
	 * the table creation defination. In this, case, if a table exists and the
	 * columns are matched then nothing happens but if there's any difference
	 * in the new sql then the dbDelta alters the table using the new sql
	 * defination but doesn't delete any columns. So, after the dbDelta
	 * finishes it's job, any non-existing columns in the new sql
	 * defination will be deleted from the existing table. if
	 * table is not there then the table gets created.
	 * 
	 * @param  string $table The table name without the prefix
	 * @param  string $sql   The sql to create table or an absolute path of a
	 * .sql file containing the column definations for creating the new table.
	 * 
	 * @return string message
	 */
	public static function updateTable($table, $sql)
	{
	    $sql = static::cleanUp($sql);

	    if (static::isSqlite()) {
            $sql = static::jsonToLongtext($sql);
        }

	    $columnsDefinitions = array_map('trim', static::splitAlterClauses($sql));
	    $schemaSql = implode(",\n", $columnsDefinitions);

	    // Extract desired column names (skip constraint/index clauses)
	    $columns = [];
	    foreach ($columnsDefinitions as $definition) {
	        // Skip index and constraint lines, which start with a keyword
	        // such as PRIMARY KEY, UNIQUE KEY, KEY, INDEX or FOREIGN KEY, in
	        // any case: 'PRIMARY KEY (id)' is skipped, 'email varchar(191)' is
	        // not. \b means a whole word, so index_name is not taken for INDEX.
	        if (preg_match('/^(?:PRIMARY\s+KEY|UNIQUE(?:(?:\s+KEY|\s+INDEX))?|KEY|INDEX|CONSTRAINT|FOREIGN\s+KEY|FULLTEXT|SPATIAL)\b/i', ltrim($definition))) {
	            continue;
	        }

	        // Take the column name from the start of the line, with or without
	        // backticks: '`email` varchar(191) NOT NULL' gives 'email'.
	        if (preg_match('/^`?(\w+)`?\s+/i', $definition, $matches)) {
	            $columns[$matches[1]] = $definition;
	        }
	    }

	    $tbl = static::table($table);

	    // SQLite cannot drop PRIMARY KEY columns or columns referenced by indexes
	    // via native ALTER TABLE DROP COLUMN.
	    //
	    // Workarounds:
	    //   - Indexed columns: drop the index first with DROP INDEX,
	    //     then drop the column.
	    //   - PRIMARY KEY columns: use CHANGE COLUMN to rebuild the table without
	    //     the PK attribute (renaming the column to a temp name), then drop
	    //     the renamed column.
	    if (static::isSqlite()) {
	        // Collect PRIMARY KEY columns and per-column index key names.
	        $indexRows     = (array) static::db()->get_results("SHOW INDEX FROM {$tbl}");
	        $pkColumns     = [];
	        $columnIndexes = []; // column_name => [key_name, ...]
	        foreach ($indexRows as $row) {
	            if ($row->Key_name === 'PRIMARY') {
	                $pkColumns[] = $row->Column_name;
	            } else {
	                $columnIndexes[$row->Column_name][] = $row->Key_name;
	            }
	        }

	        $existingColumns = static::getColumns($table) ?: [];

	        // 1. Add any desired columns not yet in the table.
	        foreach ($columns as $colName => $definition) {
	            if (!in_array($colName, $existingColumns)) {
	                static::db()->query("ALTER TABLE {$tbl} ADD COLUMN {$definition}");
	            }
	        }

	        // 2. Drop every column that is not in the desired schema.
	        foreach ($existingColumns as $column) {
	            if (isset($columns[$column])) {
	                continue; // desired — keep it
	            }

	            // Drop non-primary indexes referencing this column first, otherwise
	            // native SQLite ALTER TABLE DROP COLUMN fails.
	            foreach ($columnIndexes[$column] ?? [] as $keyName) {
	                static::db()->query("ALTER TABLE {$tbl} DROP INDEX {$keyName}");
	            }

	            if (in_array($column, $pkColumns)) {
	                // Native SQLite ALTER TABLE DROP COLUMN rejects PRIMARY KEY columns.
	                // Use CHANGE COLUMN to trigger an internal table rebuild that strips
	                // the PK attribute, then drop the (now ordinary) renamed column.
	                $tmpCol = $column . '_wpf_drop';
	                static::db()->query("ALTER TABLE {$tbl} CHANGE COLUMN {$column} {$tmpCol} INT NULL");
	                static::db()->query("ALTER TABLE {$tbl} DROP COLUMN {$tmpCol}");
	            } else {
	                static::db()->query("ALTER TABLE {$tbl} DROP COLUMN {$column}");
	            }
	        }

	        return [$tbl => "Updated table structure"];
	    }

	    // MySQL path: use dbDelta to create/update, then drop extra columns.
	    $result = static::createTable($table, $schemaSql);
	    $existingColumns = static::getColumns($table) ?: [];

	    // Add missing columns
	    foreach ($columns as $colName => $definition) {
	        if (!in_array($colName, $existingColumns)) {
	            static::query("ALTER TABLE $tbl ADD COLUMN $definition");
	            $result[$tbl.'.'.$colName] = "Added column {$tbl}.{$colName}";
	        }
	    }

	    // Drop extra columns
	    foreach ($existingColumns as $column) {
	        if (!isset($columns[$column])) {
	            static::query("ALTER TABLE $tbl DROP COLUMN $column");
	            $result[$tbl.'.'.$column] = "Dropped column {$tbl}.{$column}";
	        }
	    }

	    return $result;
	}

	/**
	 * Drops/deletes an existing table.
	 * 
	 * @param string $table The table name without the prefix
     * @param bool $disableForeignKeyCheck Optional.
     * @return bool
	 */
	public static function dropTable($table, $disableForeignKeyCheck = true)
    {
        return static::db()->query('DROP TABLE ' . static::table($table));
    }

    /**
     * Drops/deletes an existing table if exists
     *
     * @param string $table The table name without the prefix
     * @param bool $disableForeignKeyCheck Optional.
     * @return bool
     */
    public static function dropTableIfExists($table, $disableForeignKeyCheck = true)
    {
        if (static::hasTable($table)) {
            return static::dropTable($table, $disableForeignKeyCheck);
        }
    }

	/**
	 * Truncate a table.
	 *
	 * @param  string $table
	 * @return bool
	 */
	public static function truncate($table)
	{
		$table = static::table($table);

		$result = static::db()->query("TRUNCATE TABLE $table");

		if (static::isSqlite()) {
			// SQLite translates TRUNCATE TABLE to DELETE FROM, which does NOT reset
			// the auto-increment counter. Manually clear the sqlite_sequence row so
			// that the next INSERT starts at id=1 (matching MySQL TRUNCATE behaviour).
			static::db()->query(
				static::db()->prepare('DELETE FROM sqlite_sequence WHERE name = %s', $table)
			);
		}

		return $result;
	}

	/**
	 * Truncate a table if exists.
	 *
	 * @param  string $table
	 * @return bool
	 */
	public static function truncateTableIfExists($table)
	{
		if (static::hasTable($table)) {
			return static::truncate($table);
		}
	}

	/**
	 * Adds a new  index to a column of given table.
	 * 
	 * @param string $table Table name
	 * @param string $index Columns name
	 * @return bool
	 * @see https://developer.wordpress.org/reference/functions/add_clean_index
	 */
	public static function addIndex($table, $index)
	{
		return add_clean_index(static::table($table), $index);
	}

	/**
	 * Drops an index from a column of given table.
	 * 
	 * @param  string $table Table name
	 * @param  string $index Columns name
	 * @return bool
	 * @see https://developer.wordpress.org/reference/functions/drop_index
	 */
	public static function dropIndex($table, $index)
	{
		if (static::isSqlite()) {
			// Backticks, so an index named after an SQL keyword (group)
			// drops; WordPress's drop_index() adds its own on MySQL.
			$tbl = static::table($table);
			$index = '`' . str_replace('`', '``', trim($index, '`')) . '`';

			return static::db()->query("ALTER TABLE {$tbl} DROP INDEX {$index}");
		}

		return drop_index(static::table($table), $index);
	}

	/**
	 * Add a FULLTEXT index to the given columns, required by the query
	 * builder's relevance ranking (selectRelevance/orderByRelevance and the
	 * Searchable relevanceSearch scope). No-ops on SQLite, which has no
	 * native FULLTEXT support and degrades to LIKE-based matching.
	 *
	 * @param  string  $table   Table name without prefix
	 * @param  string|array  $columns
	 * @param  string|null  $name  Optional index name
	 * @return mixed
	 */
	public static function fullText($table, $columns, $name = null)
	{
		if (static::isSqlite()) {
			return false;
		}

		$columns = is_array($columns)
			? $columns
			: array_map('trim', explode(',', $columns));

		$tbl = static::table($table);
		$name = $name ?: ('ft_' . implode('_', $columns));
		$list = implode(', ', $columns);

		return static::db()->query(
			"ALTER TABLE {$tbl} ADD FULLTEXT {$name} ({$list})"
		);
	}

	/**
	 * Drop a FULLTEXT index by name.
	 *
	 * @param  string  $table  Table name without prefix
	 * @param  string  $name   Index name
	 * @return mixed
	 */
	public static function dropFullText($table, $name)
	{
		if (static::isSqlite()) {
			return false;
		}

		$tbl = static::table($table);

		return static::db()->query("ALTER TABLE {$tbl} DROP INDEX {$name}");
	}

	/**
	 * Makes raw query and can resolve the table name from the query
	 * and can form a full table name including the table prefix if
	 * the table name is wrapped like: %table_name% in the query.
	 * 
	 * @param  string $query
	 * @return mixed
	 */
	public static function query($query)
	{
		// Replace %name% with the full, prefixed table name: %posts% becomes
		// wp_posts. The name may hold letters, digits, _ and -. A quoted
		// value ('...' or "...", where \\. is an escaped character) is
		// matched first and kept as it is, so LIKE '%news%' and
		// LIKE 'A%size%B' are left alone.
		$query = preg_replace_callback('/(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")|%([a-zA-Z0-9_-]+)%/', function ($matches) {
			return isset($matches[2]) ? static::table($matches[2]) : $matches[1];
		}, $query);

		// Queries that start with SELECT, SHOW, DESCRIBE or EXPLAIN (any case,
		// then a space) return rows; anything else is run as a statement.
		if (preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\s+/i', trim($query))) {
	        return static::db()->get_results($query, OBJECT);
	    }

		return static::db()->query($query);
	}

	/**
	 * Get a list of all columns from the given table name.
	 *
	 * @param  string $table The table name without the prefix
	 * @return array|null
	 */
	public static function getColumns($table)
	{
		if (!static::hasTable($table)) {
			return null;
		}

		return static::db()->get_col(
            'DESCRIBE ' . static::table($table), 0
        );
	}

	/**
	 * Gets a list of all columns including column information
	 * 
	 * @param  string $table The table name without the prefix
	 * @return array
	 */
	protected static function protectedGetColumnsWithTypes($table)
	{
		if (!static::hasTable($table)) return;

		$table = static::table($table);

		if (static::isSqlite()) {
			// INFORMATION_SCHEMA is not available in SQLite.
			// Use DESCRIBE which the WP SQLite plugin translates with
			// proper MySQL type mapping from its data type cache.
			$columns = (array) static::db()->get_results("DESCRIBE {$table}");
			return array_map(function ($col, $pos) {
				$extra = $col->Extra ?? '';
				// SQLite INTEGER PRIMARY KEY is auto_increment but DESCRIBE
				// doesn't report it in Extra — detect from Key + Type.
				if (empty($extra) && ($col->Key ?? '') === 'PRI') {
					$type = strtoupper($col->Type ?? '');
					if (in_array($type, ['INTEGER', 'BIGINT', 'BIGINT(20)', 'BIGINT(20) UNSIGNED', 'INT'])) {
						$extra = 'auto_increment';
					}
				}

				return [
					'column_name' => $col->Field,
					'ordinal_position' => $pos + 1,
					'column_default' => $col->Default,
					'is_nullable' => ($col->Null === 'YES' ? 'YES' : 'NO'),
					// The type without its size: everything from the first
					// '(' is cut, so 'bigint(20) unsigned' gives 'bigint'.
					'data_type' => strtolower(
						preg_replace('/\(.*/', '', $col->Type)
					),
					// The number in brackets: 'varchar(191)' gives 191.
					// Types like 'decimal(10,2)' do not match and give null.
					'character_maximum_length' => preg_match(
						'/\((\d+)\)/', $col->Type, $matches
					) ? (int)$matches[1] : null,
					'numeric_precision' => null,
					'numeric_scale' => null,
					'column_key' => $col->Key ?? '',
					'extra' => $extra,
				];
			}, $columns, array_keys($columns));
		}

		// @phpstan-ignore-next-line
		$db = static::db()->dbname;

		$fields = [
			'COLUMN_NAME',
			'ORDINAL_POSITION',
			'COLUMN_DEFAULT',
			'IS_NULLABLE',
			'DATA_TYPE',
			'CHARACTER_MAXIMUM_LENGTH',
			'NUMERIC_PRECISION',
			'NUMERIC_SCALE',
			'COLUMN_KEY',
			'EXTRA',
		];

		$sql = "SELECT " . implode(',', $fields) . " FROM INFORMATION_SCHEMA.COLUMNS";
		$sql .= static::db()->prepare(' WHERE TABLE_NAME = %s AND TABLE_SCHEMA = %s', $table, $db);

		return array_map(function($i) {
			$item = [];
			foreach ((array) $i as $key => $value) {
				$item[strtolower($key)] = $value;
			}
			return $item;
		}, (array) static::db()->get_results($sql));
	}

	public static function getColumnsWithTypes($table)
	{
	    $columns = static::protectedGetColumnsWithTypes($table);

	    if (!empty($columns)) {
	    	return $columns;
	    }

		if (static::isSqlite()) {
			return [];
		}

	    $columns = static::db()->get_results(
	    	'SHOW COLUMNS FROM `'.static::table($table).'`'
	    );

        return array_map(function ($col) {
            return [
                'column_name' => $col->Field,
                'ordinal_position' => null,
                'column_default' => $col->Default,
                'is_nullable' => ($col->Null === 'YES' ? 'YES' : 'NO'),
                // The type without its size: everything from the first
                // '(' is cut, so 'bigint(20) unsigned' gives 'bigint'.
                'data_type' => strtolower(
                	preg_replace('/\(.*/', '', $col->Type)
                ),
                // The number in brackets: 'varchar(191)' gives 191.
                // Types like 'decimal(10,2)' do not match and give null.
                'character_maximum_length' => preg_match(
                	'/\((\d+)\)/', $col->Type, $matches
                ) ? (int)$matches[1] : null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'column_key' => $col->Key,
                'extra' => $col->Extra,
            ];
        }, (array) $columns);
	}

	/**
	 * Gets a list of all columns including column information
	 * 
	 * @param  string $table The table name without the prefix
	 * @return array
	 */
	public static function describeTable($table)
	{
		return static::getColumnsWithTypes($table);
	}

	/**
	 * Gets a list of all foreign keys from the given table name.
	 * 
	 * @param  string $table
	 * @return array
	 */
	public static function getTableForeignKeys($table)
	{
	    $table = static::table($table);

	    if (static::isSqlite()) {
	        // Parse foreign keys from the CREATE TABLE statement in sqlite_master
	        // since PRAGMA queries cannot go through $wpdb. `sql` in backticks:
	        // the drop-in's MySQL engine reads SQL as MySQL, which takes a bare
	        // sql for a keyword, fails the query and so the open transaction.
	        $row = static::db()->get_row(
	            static::db()->prepare("SELECT `sql` FROM sqlite_master WHERE type='table' AND name = %s", $table)
	        );
	        if (!$row || empty($row->sql)) {
	            return [];
	        }
	        $fks = [];
	        // Read every FOREIGN KEY (column) REFERENCES table (column), in
	        // any case, with names bare or wrapped in ` or ". 'FOREIGN KEY
	        // ("user_id") REFERENCES "wp_users" ("ID")' gives user_id,
	        // wp_users and ID.
	        if (preg_match_all(
	            '/FOREIGN\s+KEY\s*\(\s*[`"]?(\w+)[`"]?\s*\)\s*REFERENCES\s+[`"]?(\w+)[`"]?\s*\(\s*[`"]?(\w+)[`"]?\s*\)/i',
	            $row->sql, $matches, PREG_SET_ORDER
	        )) {
	            foreach ($matches as $m) {
	                $fks[] = [
	                    'column_name'       => $m[1],
	                    'referenced_table'  => $m[2],
	                    'referenced_column' => $m[3],
	                ];
	            }
	        }
	        return $fks;
	    }

		// @phpstan-ignore-next-line
	    $db = static::db()->dbname;

	    $sql = static::db()->prepare(
	        "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
	            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
	            WHERE TABLE_NAME = %s
	              AND TABLE_SCHEMA = %s
	              AND REFERENCED_TABLE_NAME IS NOT NULL",
	        $table,
	        $db
	    );

	    return array_map(function ($i) {
	        return [
	            'column_name'       => $i->COLUMN_NAME,
	            'referenced_table'  => $i->REFERENCED_TABLE_NAME,
	            'referenced_column' => $i->REFERENCED_COLUMN_NAME,
	        ];
	    }, (array) static::db()->get_results($sql));
	}

	/**
	 * Retrieves the list of all available built-in tables from
	 * the database using WordPress' $wpdb->tables native method.
	 * 
	 * @param  string  $scope
	 * @param  boolean $prefix
	 * @param  integer $blogId
	 * @return string[] WP Table names. When a prefix is requested,
	 * the key is the unprefixed table name.
	 * @see https://developer.wordpress.org/reference/classes/wpdb/tables/
	 */
	public static function tables($scope = 'all', $prefix = true, $blogId = 0)
	{
		return static::db()->tables($scope, $prefix, $blogId);
	}

	/**
	 * Retrieves the list of all available tables from the database.
	 * 
	 * @param  string $dbname optional
	 * @return array
	 */
	public static function getTables($dbname = null)
	{
		return static::getTableList($dbname);
	}

	/**
	 * Retrieves the list of all available tables in the database.
	 * 
	 * @param  string $dbname optional
	 * @return array
	 */
	public static function getTableList($dbname = null)
	{
		if (static::isSqlite()) {
			return array_map(function ($i) {
				return $i->name;
			}, (array) static::db()->get_results(
				"SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
			));
		}

		// @phpstan-ignore-next-line
		$dbname = $dbname ?: static::db()->dbname;
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES";
		$sql .= static::db()->prepare(' WHERE TABLE_SCHEMA = %s', $dbname);

		return array_map(function($i) {
			return $i->TABLE_NAME;
		}, (array) static::db()->get_results($sql));
	}

	/**
	 * Retrieves the list of all available views in the database.
	 *
	 * @param  string $dbname optional
	 * @return array
	 */
	public static function getViews($dbname = null)
	{
		if (static::isSqlite()) {
			return array_map(function ($i) {
				return $i->name;
			}, (array) static::db()->get_results(
				"SELECT name FROM sqlite_master WHERE type='view'"
			));
		}

		// @phpstan-ignore-next-line
		$dbname = $dbname ?: static::db()->dbname;
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS";
		$sql .= static::db()->prepare(' WHERE TABLE_SCHEMA = %s', $dbname);

		return array_map(function ($i) {
			return $i->TABLE_NAME;
		}, (array) static::db()->get_results($sql));
	}

	/**
	 * Retrieves the SQL definition of a specific view.
	 *
	 * @param  string $name   The view name
	 * @param  string $dbname optional
	 * @return string|null
	 */
	public static function getView($name, $dbname = null)
	{
		if (static::isSqlite()) {
			$result = static::db()->get_row(
				static::db()->prepare("SELECT `sql` FROM sqlite_master WHERE type='view' AND name = %s", $name)
			);

			return $result ? $result->sql : null;
		}

		// @phpstan-ignore-next-line
		$dbname = $dbname ?: static::db()->dbname;
		$result = static::db()->get_row(
			static::db()->prepare(
				'SELECT VIEW_DEFINITION FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				$dbname,
				$name
			)
		);

		return $result ? $result->VIEW_DEFINITION : null;
	}

	/**
     * Determine if the connected database is a sqlite database.
     *
     * @return bool
     */
    public static function isSqlite()
    {
        return defined('DB_ENGINE') && DB_ENGINE === 'sqlite';
    }

    /**
     * Determine if the connected database is a mariadb database.
     *
     * @return bool
     */
    public static function isMaria()
    {
        if (static::isSqlite()) {
            return false;
        }

        return str_contains(
        	static::db()->get_var('SELECT VERSION()'), 'MariaDB'
        );
    }

	/**
	 * Retrieve the current database engine name.
	 * 
	 * @param  string $table
	 * @return string
	 */
	public static function getEngine($table)
	{
		if (static::isSqlite()) {
			return 'sqlite';
		}

		return static::db()->get_var(
            static::db()->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                static::table($table)
            )
        );
	}

	/**
	 * The wrapper for calling dbDelta function
	 * 
	 * @param  string $sql
	 * @return mixed
	 */
	public static function callDBDelta($sql)
	{
		if (!function_exists('dbDelta')) {
			require (ABSPATH . 'wp-admin/includes/upgrade.php');
		}

		return dbDelta($sql);
	}

	/**
     * Helper to get the driver-specific JSON type.
     */
    public static function jsonType()
    {
        return static::isSqlite() ? 'longtext' : 'json';
    }
}
