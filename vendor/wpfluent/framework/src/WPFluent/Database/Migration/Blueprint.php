<?php

namespace WPSocialReviews\Framework\Database\Migration;

use WPSocialReviews\Framework\Support\DebugLog;
use WPSocialReviews\Framework\Database\Schema;

class Blueprint
{
    protected $table;
    protected $mode;

    /**
     * @var ColumnDefinition[]
     */
    protected $columns = [];

    /**
     * Index definitions for CREATE mode.
     * Each: ['type' => 'KEY|UNIQUE KEY', 'columns' => [...], 'name' => '...']
     */
    protected $indexes = [];

    /**
     * Alter operations (executed in order).
     * Each: ['type' => string, 'data' => mixed]
     */
    protected $operations = [];

    public function __construct($table, $mode = 'create')
    {
        $this->table = $table;
        $this->mode = $mode;
    }

    // ══════════════════════════════════════════════════════════
    //  CREATE MODE — Column Definitions
    // ══════════════════════════════════════════════════════════

    public function id($name = 'id')
    {
        $col = new ColumnDefinition($name);
        $col->id();
        $this->columns[] = $col;
        return $col;
    }

    public function string($name, $length = 255)
    {
        $col = new ColumnDefinition($name);
        $col->string($length);
        $this->columns[] = $col;
        return $col;
    }

    public function char($name, $length = 1)
    {
        $col = new ColumnDefinition($name);
        $col->char($length);
        $this->columns[] = $col;
        return $col;
    }

    public function text($name)
    {
        $col = new ColumnDefinition($name);
        $col->text();
        $this->columns[] = $col;
        return $col;
    }

    public function mediumText($name)
    {
        $col = new ColumnDefinition($name);
        $col->mediumText();
        $this->columns[] = $col;
        return $col;
    }

    public function longText($name)
    {
        $col = new ColumnDefinition($name);
        $col->longText();
        $this->columns[] = $col;
        return $col;
    }

    public function integer($name)
    {
        $col = new ColumnDefinition($name);
        $col->integer();
        $this->columns[] = $col;
        return $col;
    }

    public function bigInteger($name)
    {
        $col = new ColumnDefinition($name);
        $col->bigInteger();
        $this->columns[] = $col;
        return $col;
    }

    public function tinyInteger($name)
    {
        $col = new ColumnDefinition($name);
        $col->tinyInteger();
        $this->columns[] = $col;
        return $col;
    }

    public function smallInteger($name)
    {
        $col = new ColumnDefinition($name);
        $col->smallInteger();
        $this->columns[] = $col;
        return $col;
    }

    public function mediumInteger($name)
    {
        $col = new ColumnDefinition($name);
        $col->mediumInteger();
        $this->columns[] = $col;
        return $col;
    }

    public function decimal($name, $precision = 8, $scale = 2)
    {
        $col = new ColumnDefinition($name);
        $col->decimal($precision, $scale);
        $this->columns[] = $col;
        return $col;
    }

    public function float($name)
    {
        $col = new ColumnDefinition($name);
        $col->float();
        $this->columns[] = $col;
        return $col;
    }

    public function double($name)
    {
        $col = new ColumnDefinition($name);
        $col->double();
        $this->columns[] = $col;
        return $col;
    }

    public function boolean($name)
    {
        $col = new ColumnDefinition($name);
        $col->boolean();
        $this->columns[] = $col;
        return $col;
    }

    public function json($name)
    {
        $col = new ColumnDefinition($name);
        $col->json();
        $this->columns[] = $col;
        return $col;
    }

    public function binary($name, $length = 255)
    {
        $col = new ColumnDefinition($name);
        $col->binary($length);
        $this->columns[] = $col;
        return $col;
    }

    public function blob($name)
    {
        $col = new ColumnDefinition($name);
        $col->blob();
        $this->columns[] = $col;
        return $col;
    }

    public function mediumBlob($name)
    {
        $col = new ColumnDefinition($name);
        $col->mediumBlob();
        $this->columns[] = $col;
        return $col;
    }

    public function longBlob($name)
    {
        $col = new ColumnDefinition($name);
        $col->longBlob();
        $this->columns[] = $col;
        return $col;
    }

    public function enum($name, array $values)
    {
        $col = new ColumnDefinition($name);
        $col->enum($values);
        $this->columns[] = $col;
        return $col;
    }

    public function set($name, array $values)
    {
        $col = new ColumnDefinition($name);
        $col->set($values);
        $this->columns[] = $col;
        return $col;
    }

    public function timestamp($name)
    {
        $col = new ColumnDefinition($name);
        $col->timestamp();
        $this->columns[] = $col;
        return $col;
    }

    public function date($name)
    {
        $col = new ColumnDefinition($name);
        $col->date();
        $this->columns[] = $col;
        return $col;
    }

    public function datetime($name)
    {
        $col = new ColumnDefinition($name);
        $col->datetime();
        $this->columns[] = $col;
        return $col;
    }

    public function time($name)
    {
        $col = new ColumnDefinition($name);
        $col->time();
        $this->columns[] = $col;
        return $col;
    }

    public function year($name)
    {
        $col = new ColumnDefinition($name);
        $col->year();
        $this->columns[] = $col;
        return $col;
    }

    // ── Compound Shortcuts ───────────────────────────────────

    public function timestamps()
    {
        $this->timestamp('created_at')->default('CURRENT_TIMESTAMP');
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes()
    {
        $this->timestamp('deleted_at')->nullable();
    }

    /**
     * Add the id and type columns of a polymorphic relation.
     *
     * The id is a BIGINT UNSIGNED, like the id() it points to, and is
     * indexed alone: an index that also covered the VARCHAR(255) type
     * would pass the 767-byte key limit of older MySQL setups.
     */
    public function morphs($name)
    {
        $this->bigInteger($name . '_id')->unsigned();
        $this->string($name . '_type');
        $this->index($name . '_id');
    }

    /**
     * Add the nullable id and type columns of a polymorphic relation.
     */
    public function nullableMorphs($name)
    {
        $this->bigInteger($name . '_id')->unsigned()->nullable();
        $this->string($name . '_type')->nullable();
        $this->index($name . '_id');
    }

    // ── Index Definitions ────────────────────────────────────

    /**
     * Add a regular index.
     */
    public function index($columns, $name = null)
    {
        $columns = (array) $columns;
        $name = $name ?: implode('_', $columns);

        if ($this->mode === 'create') {
            $this->indexes[] = [
                'type' => 'KEY',
                'columns' => $columns,
                'name' => $name,
            ];
        } else {
            $this->operations[] = [
                'type' => 'addIndex',
                'data' => ['columns' => $columns, 'name' => $name],
            ];
        }

        return $this;
    }

    /**
     * Add a unique index.
     */
    public function unique($columns, $name = null)
    {
        $columns = (array) $columns;
        $name = $name ?: 'unique_' . implode('_', $columns);

        if ($this->mode === 'create') {
            $this->indexes[] = [
                'type' => 'UNIQUE KEY',
                'columns' => $columns,
                'name' => $name,
            ];
        } else {
            $this->operations[] = [
                'type' => 'addUniqueIndex',
                'data' => ['columns' => $columns, 'name' => $name],
            ];
        }

        return $this;
    }

    // ══════════════════════════════════════════════════════════
    //  ALTER MODE — Guarded Operations
    // ══════════════════════════════════════════════════════════

    /**
     * Add a column (only if it doesn't exist).
     */
    public function addColumn($name)
    {
        $col = new ColumnDefinition($name);
        $this->operations[] = [
            'type' => 'addColumn',
            'data' => $col,
        ];
        return $col;
    }

    /**
     * Drop a column (only if it exists).
     */
    public function dropColumn($name)
    {
        $this->operations[] = [
            'type' => 'dropColumn',
            'data' => $name,
        ];
        return $this;
    }

    /**
     * Modify a column type/size (only if it exists).
     */
    public function modifyColumn($name)
    {
        $col = new ColumnDefinition($name);
        $this->operations[] = [
            'type' => 'modifyColumn',
            'data' => $col,
        ];
        return $col;
    }

    /**
     * Rename a column (only if old exists and new doesn't).
     */
    public function renameColumn($from, $to)
    {
        ColumnDefinition::assertUsableName($to);

        $this->operations[] = [
            'type' => 'renameColumn',
            'data' => ['from' => $from, 'to' => $to],
        ];
        return $this;
    }

    /**
     * Drop an index (only if it exists).
     */
    public function dropIndex($name)
    {
        $this->operations[] = [
            'type' => 'dropIndex',
            'data' => $name,
        ];
        return $this;
    }

    // ══════════════════════════════════════════════════════════
    //  EXECUTION
    // ══════════════════════════════════════════════════════════

    /**
     * Execute CREATE TABLE via dbDelta (idempotent).
     */
    public function executeCreate()
    {
        $sql = $this->buildCreateSql();
        return Schema::createTable($this->table, $sql);
    }

    /**
     * Execute ALTER operations with guards.
     *
     * Each result line says what was done. A step is checked against the
     * table afterwards; one the database refused, or that left the table
     * unchanged, says "Failed to …", and the debug log says so too.
     */
    public function executeAlter()
    {
        $results = [];
        $tbl = Schema::table($this->table);

        if (!Schema::hasTable($this->table)) {
            return $results;
        }

        if ($this->columns) {
            DebugLog::warn(sprintf(
                'In alter() of %s, column types written as in create() are ignored (%s): '
                . "add a column with \$table->addColumn('name')->string().",
                $tbl,
                implode(', ', array_map(function ($col) {
                    return $col->getName();
                }, $this->columns))
            ));
        }

        foreach ($this->operations as $op) {
            switch ($op['type']) {
                case 'addColumn':
                    /** @var ColumnDefinition $col */
                    $col = $op['data'];
                    $name = $col->getName();
                    if (!Schema::hasColumn($name, $this->table)) {
                        $done = $this->run(
                            Schema::alterTable($this->table, $col->toAlterAddSql()),
                            function () use ($name) {
                                return Schema::hasColumn($name, $this->table);
                            },
                            "Added column {$name} to {$tbl}",
                            "add column {$name} to {$tbl}",
                            $results
                        );
                        if ($done && ($col->isIndex() || $col->isUnique())) {
                            $this->addColumnIndex($col, $results);
                        }
                    }
                    break;

                case 'dropColumn':
                    $name = $op['data'];
                    if (Schema::hasColumn($name, $this->table)) {
                        $this->run(
                            Schema::query("ALTER TABLE {$tbl} DROP COLUMN " . ColumnDefinition::wrap($name)),
                            function () use ($name) {
                                return !Schema::hasColumn($name, $this->table);
                            },
                            "Dropped column {$name} from {$tbl}",
                            "drop column {$name} from {$tbl}",
                            $results
                        );
                    }
                    break;

                case 'modifyColumn':
                    /** @var ColumnDefinition $col */
                    $col = $op['data'];
                    if (Schema::hasColumn($col->getName(), $this->table)) {
                        $done = $this->run(
                            Schema::alterTable($this->table, $col->toAlterModifySql()),
                            null,
                            "Modified column {$col->getName()} in {$tbl}",
                            "modify column {$col->getName()} in {$tbl}",
                            $results
                        );
                        if ($done && ($col->isIndex() || $col->isUnique())) {
                            $this->addColumnIndex($col, $results);
                        }
                    }
                    break;

                case 'renameColumn':
                    $from = $op['data']['from'];
                    $to = $op['data']['to'];
                    if (Schema::hasColumn($from, $this->table) && !Schema::hasColumn($to, $this->table)) {
                        $currentDef = $this->getCurrentColumnDef($from);
                        if ($currentDef) {
                            $this->run(
                                Schema::query(
                                    "ALTER TABLE {$tbl} CHANGE COLUMN " . ColumnDefinition::wrap($from)
                                    . ' ' . ColumnDefinition::wrap($to) . " {$currentDef}"
                                ),
                                function () use ($from, $to) {
                                    return Schema::hasColumn($to, $this->table) && !Schema::hasColumn($from, $this->table);
                                },
                                "Renamed column {$from} to {$to} in {$tbl}",
                                "rename column {$from} to {$to} in {$tbl}",
                                $results
                            );
                        }
                    }
                    break;

                case 'addIndex':
                    $name = $op['data']['name'];
                    $columns = $op['data']['columns'];
                    if (!$this->hasIndex($name)) {
                        $colList = implode(', ', array_map([ColumnDefinition::class, 'wrap'], $columns));
                        $this->run(
                            Schema::query("ALTER TABLE {$tbl} ADD INDEX " . ColumnDefinition::wrap($name) . " ({$colList})"),
                            function () use ($name) {
                                return $this->hasIndex($name);
                            },
                            "Added index {$name} on {$tbl}",
                            "add index {$name} on {$tbl}",
                            $results
                        );
                    }
                    break;

                case 'addUniqueIndex':
                    $name = $op['data']['name'];
                    $columns = $op['data']['columns'];
                    if (!$this->hasIndex($name)) {
                        $colList = implode(', ', array_map([ColumnDefinition::class, 'wrap'], $columns));
                        $this->run(
                            Schema::query("ALTER TABLE {$tbl} ADD UNIQUE INDEX " . ColumnDefinition::wrap($name) . " ({$colList})"),
                            function () use ($name) {
                                return $this->hasIndex($name);
                            },
                            "Added unique index {$name} on {$tbl}",
                            "add unique index {$name} on {$tbl}",
                            $results
                        );
                    }
                    break;

                case 'dropIndex':
                    $name = $op['data'];
                    if ($this->hasIndex($name)) {
                        Schema::dropIndex($this->table, $name);
                        $results[] = "Dropped index {$name} from {$tbl}";
                    }
                    break;
            }
        }

        return $results;
    }

    /**
     * Record what an ALTER statement did. It failed when the database
     * returned false, or when the table doesn't show the change: say so in
     * the results and the debug log.
     *
     * @param  mixed  $result  What the query returned.
     * @param  \Closure|null  $applied  Whether the table shows the change.
     * @param  string  $done  The result line when it worked.
     * @param  string  $what  What was tried, for the failure line.
     * @param  array  $results
     * @return bool  Whether it worked.
     */
    protected function run($result, $applied, $done, $what, array &$results)
    {
        if ($result !== false && (!$applied || $applied())) {
            $results[] = $done;

            return true;
        }

        $error = $result === false
            ? (Schema::db()->last_error ?: 'the database refused it')
            : 'the table did not change';

        $results[] = "Failed to {$what}: {$error}";

        DebugLog::warn("Failed to {$what}: {$error}");

        return false;
    }

    /**
     * Add the index a column's index() or unique() asks for, named after
     * the column, unless the table has it.
     *
     * @param  ColumnDefinition  $col
     * @param  array  $results
     * @return void
     */
    protected function addColumnIndex(ColumnDefinition $col, array &$results)
    {
        $name = $col->getName();

        if ($this->hasIndex($name)) {
            return;
        }

        $tbl = Schema::table($this->table);
        $kind = $col->isUnique() ? 'UNIQUE INDEX' : 'INDEX';
        $label = $col->isUnique() ? 'unique index' : 'index';

        $this->run(
            Schema::query("ALTER TABLE {$tbl} ADD {$kind} " . ColumnDefinition::wrap($name) . ' (' . ColumnDefinition::wrap($name) . ')'),
            function () use ($name) {
                return $this->hasIndex($name);
            },
            "Added {$label} {$name} on {$tbl}",
            "add {$label} {$name} on {$tbl}",
            $results
        );
    }

    // ══════════════════════════════════════════════════════════
    //  INTERNAL HELPERS
    // ══════════════════════════════════════════════════════════

    /**
     * Build the column+index SQL block for CREATE TABLE.
     * Uses KEY (not INDEX) for dbDelta compatibility.
     */
    protected function buildCreateSql()
    {
        $lines = [];

        $hasPrimaryKey = false;

        foreach ($this->columns as $col) {
            $sql = $col->toSql();

            // dbDelta needs PRIMARY KEY on its own line
            if (str_contains($sql, 'AUTO_INCREMENT')) {
                $hasPrimaryKey = true;
            }

            $lines[] = $col->toSql();
        }

        // Add PRIMARY KEY line for auto-increment columns (dbDelta requirement)
        if ($hasPrimaryKey) {
            foreach ($this->columns as $col) {
                if (str_contains($col->toSql(), 'AUTO_INCREMENT')) {
                    $lines[] = 'PRIMARY KEY  (' . ColumnDefinition::wrap($col->getName()) . ')';
                    break;
                }
            }
        }

        // Add index lines from column-level modifiers
        // A unique key is an index too, so unique() wins over index(). A
        // table made before this kept a plain KEY for both, under the same
        // name; dbDelta can't turn it into a unique one and would try, and
        // fail, on every run, so that table keeps it.
        $plainIndexes = $this->existingPlainIndexes();

        foreach ($this->columns as $col) {
            if ($col->isUnique() && $col->isIndex() && in_array($col->getName(), $plainIndexes, true)) {
                DebugLog::warn(sprintf(
                    '%s.%s is marked unique() and index(), and this site has its plain index from before: it stays '
                    . 'as it is, so values are not unique here; new sites get a unique key. Changing this site '
                    . 'takes a one-off step that replaces the index.',
                    Schema::table($this->table),
                    $col->getName()
                ));
                $lines[] = 'KEY ' . ColumnDefinition::wrap($col->getName()) . ' (' . ColumnDefinition::wrap($col->getName()) . ')';
            } elseif ($col->isUnique()) {
                $lines[] = 'UNIQUE KEY ' . ColumnDefinition::wrap($col->getName()) . ' (' . ColumnDefinition::wrap($col->getName()) . ')';
            } elseif ($col->isIndex()) {
                $lines[] = 'KEY ' . ColumnDefinition::wrap($col->getName()) . ' (' . ColumnDefinition::wrap($col->getName()) . ')';
            }
        }

        // Add index lines from explicit index() / unique() calls
        foreach ($this->indexes as $idx) {
            $colList = implode(', ', array_map([ColumnDefinition::class, 'wrap'], $idx['columns']));
            $lines[] = $idx['type'] . ' ' . ColumnDefinition::wrap($idx['name']) . ' (' . $colList . ')';
        }

        return implode(",\n", $lines);
    }

    /**
     * The names of the table's indexes that aren't unique, or none when the
     * table doesn't exist yet.
     *
     * @return string[]
     */
    protected function existingPlainIndexes()
    {
        if (!Schema::hasTable($this->table)) {
            return [];
        }

        $names = [];

        foreach ((array) Schema::db()->get_results('SHOW INDEX FROM ' . Schema::table($this->table)) as $row) {
            if (isset($row->Non_unique) && (string) $row->Non_unique === '1') {
                $names[] = $row->Key_name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Check if an index exists on this table.
     */
    protected function hasIndex($indexName)
    {
        $tbl = Schema::table($this->table);

        $rows = (array) Schema::db()->get_results("SHOW INDEX FROM {$tbl}");

        foreach ($rows as $row) {
            if ($row->Key_name === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current column definition for CHANGE COLUMN.
     */
    protected function getCurrentColumnDef($column)
    {
        $tbl = Schema::table($this->table);

        // Fetch all columns and filter in PHP — the WP SQLite plugin
        // ignores the WHERE clause on SHOW COLUMNS FROM.
        $rows = Schema::db()->get_results("DESCRIBE {$tbl}");

        $row = null;
        foreach ((array) $rows as $r) {
            if ($r->Field === $column) {
                $row = $r;
                break;
            }
        }

        if (!$row) {
            return null;
        }

        $def = $row->Type;

        if ($row->Null === 'NO') {
            $def .= ' NOT NULL';
        } else {
            $def .= ' NULL';
        }

        if ($row->Default !== null) {
            $default = $row->Default;
            // SQLite returns defaults with surrounding quotes — strip them
            // before re-quoting to avoid double-quoting.
            if (Schema::isSqlite() && preg_match("/^'(.*)'$/s", $default, $m)) {
                $default = $m[1];
            }
            // CURRENT_TIMESTAMP is an expression, not a string.
            $def .= preg_match('/^current_timestamp(\(\d*\))?$/i', $default)
                ? ' DEFAULT CURRENT_TIMESTAMP'
                : " DEFAULT '" . addslashes($default) . "'";
        }

        $extra = strtolower((string) ($row->Extra ?? ''));

        if (str_contains($extra, 'on update current_timestamp')) {
            $def .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        if (str_contains($extra, 'auto_increment')) {
            $def .= ' AUTO_INCREMENT';
        }

        return $def;
    }

    public function getTable()
    {
        return $this->table;
    }
}
