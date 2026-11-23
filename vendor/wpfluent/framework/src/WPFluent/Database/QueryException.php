<?php

namespace WPSocialReviews\Framework\Database;

use Throwable;
use PDOException;

class QueryException extends PDOException
{

	/**
	 * The SQL for the query.
	 *
	 * @var string
	 */
	protected $sql;

	/**
	 * The bindings for the query.
	 *
	 * @var array
	 */
	protected $bindings;

	/**
     * Create a new query exception instance.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @param  \Throwable  $previous
     * @return void
     */
    public function __construct($sql, array $bindings, Throwable $previous)
    {
        parent::__construct('', 0, $previous);

        $this->sql = $sql;
        $this->bindings = $bindings;
        $this->code = $previous->getCode();
        $this->message = $this->formatMessage($sql, $bindings, $previous);

        if ($previous instanceof PDOException) {
            $this->errorInfo = $previous->errorInfo;
        }
    }

	/**
	 * Format the SQL error message.
	 *
	 * @param  string  $sql
	 * @param  array  $bindings
	 * @param  \Exception $previous
	 * @return string
	 */
	protected function formatMessage($sql, $bindings, $previous)
	{
		$message = $this->substituteBindings((string) $sql, $bindings);

		$reason = static::readableError($previous->getMessage());

		return $reason . ' (SQL: ' . $message . ')';
	}

	/**
	 * The database's own reason in a wpdb error.
	 *
	 * The WordPress SQLite drop-in reports an error as HTML, with the query
	 * log and a backtrace around it; its reason is the "error_message"
	 * block. The old engine (2.x) writes it after "Error message was:", the
	 * MySQL engine (3.x) alone. Any other error, such as MySQL's, is kept as
	 * written.
	 *
	 * @param  string $error
	 * @return string
	 */
	public static function readableError($error)
	{
		$error = (string) $error;

		$block = '/<div[^>]*class="error_message"[^>]*>(.*?)<\/div>/s';

		if (strpos($error, '<div') !== 0 || !preg_match($block, $error, $match)) {
			return $error;
		}

		// A backtrace may follow the reason inside the block.
		$text = preg_replace('/\s*Backtrace:.*$/', '', static::plainText($match[1]));

		// The old engine's reason runs up to its closing period.
		if (preg_match('/Error message was:\s*(.+?)\.?$/', $text, $reason)) {
			return $reason[1];
		}

		return $text;
	}

	/**
	 * HTML as one line of plain text.
	 *
	 * @param  string $html
	 * @return string
	 */
	protected static function plainText($html)
	{
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES);

		return trim(preg_replace('/\s+/', ' ', $text));
	}

	/**
	 * Get the SQL for the query.
	 *
	 * @return string
	 */
	public function getSql()
	{
		return $this->sql;
	}

	/**
	 * Get the bindings for the query.
	 *
	 * @return array
	 */
	public function getBindings()
	{
		return $this->bindings;
	}

	/**
	 * Put each binding, as written, in place of the next ? that is not
	 * inside a quoted string; a ? left without a binding stays.
	 *
	 * @param  string $sql
	 * @param  array  $bindings
	 * @return string
	 */
	protected function substituteBindings($sql, array $bindings)
	{
		$query = '';
		$quote = null;

		for ($i = 0, $length = strlen($sql); $i < $length; $i++) {
			$char = $sql[$i];

			if ($quote !== null) {
				// \' and '' (or \" and "") don't end the string.
				if ($char === '\\' || ($char === $quote && ($sql[$i + 1] ?? null) === $quote)) {
					$query .= $char . ($sql[$i + 1] ?? '');
					$i++;
				} else {
					$query .= $char;
					$quote = $char === $quote ? null : $quote;
				}
			} elseif ($char === "'" || $char === '"') {
				$query .= $char;
				$quote = $char;
			} elseif ($char === '?' && $bindings) {
				$query .= (string) array_shift($bindings);
			} else {
				$query .= $char;
			}
		}

		return $query;
	}

	/**
	 * Replace placeholders with bindings
	 * 
	 * @param  string $search
	 * @param  array  $replace
	 * @param  string $subject
	 * @return string $subject
	 */
	protected function strReplaceArray($search, array $replace, $subject)
    {
        // Swap each match for the next value, one at a time. A callback puts
        // the value in as plain text, so '$1' or '\1' in it stays as it is.
        foreach ($replace as $value) {
            $subject = preg_replace_callback('/' . $search . '/', function () use ($value) {
                return (string) $value;
            }, $subject, 1);
        }

        return $subject;
    }
}
