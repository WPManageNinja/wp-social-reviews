<?php

namespace WPSocialReviews\Framework\Validator;

use Countable;
use Exception;
use Throwable;
use DateTimeInterface;
use InvalidArgumentException;
use WPSocialReviews\Framework\Support\Str;
use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\Framework\Support\Helper;
use WPSocialReviews\Framework\Support\DateTime;
use WPSocialReviews\Framework\Validator\Contracts\File;

trait ValidatesAttributes
{
    use ValidateDatabaseRulesTrait;

    /**
     * Require a certain number of parameters to be present.
     *
     * @param int $count
     * @param array $parameters
     * @param string $rule
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function requireParameterCount($count, $parameters, $rule)
    {
        if (count($parameters) < $count) {
            throw new InvalidArgumentException(
                "Validation rule $rule requires at least $count parameters."
            );
        }
    }

    /**
     * Put the list indexes from an attribute's name into the '*' parts of
     * another field's name, in order: for the attribute
     * 'items.10.tags.2.name', 'items.*.tags.*.type' becomes
     * 'items.10.tags.2.type'.
     *
     * @param  string  $attribute
     * @param  string  $other
     * @return string
     */
    protected function resolveWildcardIndexes($attribute, $other)
    {
        // Each whole number that sits between two dots: 'items.10.tags.2.name'
        // gives 10 and 2. (?<=\.) and (?=\.) check for the dots without
        // taking them, so neighbouring indexes such as '.1.2.' both count.
        preg_match_all('/(?<=\.)\d+(?=\.|$)/', $attribute, $indexes);

        // Each '*' segment of the other field, in order, takes the next
        // index: items.*.tags.* for items.1.tags.2.name is items.1.tags.2.
        $segments = explode('.', $other);
        $indexes = $indexes[0];

        foreach ($segments as $i => $segment) {
            if ($segment === '*' && $indexes) {
                $segments[$i] = array_shift($indexes);
            }
        }

        return implode('.', $segments);
    }

    /**
     * Get the size of an attribute.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return mixed
     */
    protected function getSize($attribute, $value)
    {
        // This method will determine if the attribute is a number, string, or file and
        // return the proper size accordingly. If it is a number, then number itself
        // is the size. If it is a file, we take kilobytes, and for a string the
        // entire length of the string will be considered the attribute size.
        $type = $this->deduceType($value, $attribute);

        switch ($type) {
            case 'numeric':
                return $value;
            case 'array':
                return count($value);
            case 'file':
                return $value->getSize() / 1024;
            default:
                return mb_strlen($value);
        }
    }

    /**
     * Deduce the value type of an attribute.
     *
     * @param $value
     *
     * @return string
     */
    protected function deduceType($value, $attribute = null)
    {
        if (is_numeric($value)) {
            return $this->guessType($value, $attribute);
        } elseif (is_array($value)) {
            return 'array';
        } elseif ($value instanceof File) {
            return 'file';
        }

        return 'string';
    }

    /**
     * Guess the real type by examining rules.
     * 
     * @param  mixed $value
     * @param  string|null $attribute
     * @return string
     */
    protected function guessType($value, $attribute)
    {
        if ($attribute && in_array('string', $this->rules[$attribute] ?? [], true)) {
            return 'string';
        }

        return 'numeric';
    }

    /**
     * Check if parameter should be converted to boolean.
     *
     * @param  string  $parameter
     * @return bool
     */
    protected function shouldConvertToBoolean($parameter)
    {
        return in_array('boolean', Arr::get($this->rules, $parameter, []), true);
    }

    /**
     * Determine whether another field's value is one of the given values.
     *
     * "true" and "false" stand for booleans when the other field holds a
     * boolean or carries the boolean rule, so required_if:flag,false
     * matches a false sent as JSON and required_if:flag,true a form's "1".
     *
     * @param  string  $field
     * @param  mixed  $other
     * @param  array  $values
     * @return bool
     */
    protected function matchesAnyValue($field, $other, array $values)
    {
        if (is_bool($other) || $this->shouldConvertToBoolean($field)) {
            $values = $this->convertValuesToBoolean($values);
        }

        return in_array($other, $values);
    }

    /**
     * Convert the given values to boolean if they are string "true" / "false".
     *
     * @param array $values
     *
     * @return array
     */
    protected function convertValuesToBoolean($values)
    {
        return array_map(function ($value) {
            if ($value === 'true') {
                return true;
            } elseif ($value === 'false') {
                return false;
            }

            return $value;
        }, $values);
    }

    /**
     * Convert the given values to null if they are string "null".
     *
     * @param  array  $values
     * @return array
     */
    protected function convertValuesToNull($values)
    {
        return array_map(function ($value) {
            return Str::lower($value) === 'null' ? null : $value;
        }, $values);
    }

    /**
     * Validate that a required attribute exists.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateRequired($attribute, $value)
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif ((is_array($value) || $value instanceof Countable) && count($value) < 1) {
            return false;
        } elseif ($value instanceof File) {
            return (string) $value->getPath() != '';
        }

        return true;
    }

    /**
     * Validate that an attribute exists when another attribute has a given value.
     *
     * @param string $attribute
     * @param mixed $value
     * @param mixed $parameters
     *
     * @return bool
     */
    protected function validateRequiredIf($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'required_if');
        
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $other = Arr::get($this->data, $parameters[0]);

        $values = array_slice($parameters, 1);

        if ($this->matchesAnyValue($parameters[0], $other, $values)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute exists unless another attribute has one
     * of the given values: required_unless:status,closed,resolved. "true",
     * "false" and "null" stand for those values, as in Laravel.
     *
     * @param string $attribute
     * @param mixed $value
     * @param mixed $parameters
     *
     * @return bool
     */
    protected function validateRequiredUnless($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'required_unless');

        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $other = Arr::get($this->data, $parameters[0]);

        $values = array_slice($parameters, 1);

        if (is_null($other)) {
            $values = $this->convertValuesToNull($values);
        }

        if (!$this->matchesAnyValue($parameters[0], $other, $values)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute, when it is present, is not empty.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateFilled($attribute, $value)
    {
        if ($this->validatePresent($attribute, $value)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute was accepted: yes, on, 1, true or "true".
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateAccepted($attribute, $value)
    {
        $acceptable = ['yes', 'on', '1', 1, true, 'true'];

        return $this->validateRequired($attribute, $value)
            && in_array($value, $acceptable, true);
    }

    /**
     * Validate that an attribute is required only if **any** of the
     * other specified fields are present.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateRequiredWith($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'required_with');

        if (! $this->allFailingRequired($attribute, $parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute is required only if **all** of the
     * other specified fields are present.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateRequiredWithAll($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'required_with_all');

        if (! $this->anyFailingRequired($attribute, $parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute is required only if **any** of the
     * other specified fields are **not present**.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateRequiredWithout($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'required_without');

        if ($this->anyFailingRequired($attribute, $parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validate that an attribute is required only if **all** of the
     * other specified fields are **not present**.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateRequiredWithoutAll($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'required_without_all');

        if ($this->allFailingRequired($attribute, $parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Whether none of the other fields has a value. On a wildcard field the
     * others are the same item's: items.0.qty reads items.0.price.
     *
     * @param  string  $attribute
     * @param  array  $fields
     * @return bool
     */
    protected function allFailingRequired($attribute, array $fields)
    {
        foreach ($fields as $field) {
            $field = $this->resolveWildcardIndexes($attribute, $field);

            if ($this->validateRequired($field, $this->getValue($field))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether any of the other fields has no value.
     *
     * @param  string  $attribute
     * @param  array  $fields
     * @return bool
     */
    protected function anyFailingRequired($attribute, array $fields)
    {
        foreach ($fields as $field) {
            $field = $this->resolveWildcardIndexes($attribute, $field);

            if (! $this->validateRequired($field, $this->getValue($field))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the given attribute is present.
     * 
     * @param  string  $attribute
     * @return boolean
     */
    protected function isPresent($attribute)
    {
        $attribute = str_replace(['[', ']'], ['.', ''], $attribute);

        if (!Arr::has($this->data, $attribute)) {
            return false;
        }

        $value = Arr::get($this->data, $attribute);

        return !is_null($value) && $value !== '';
    }

    /**
     * Get the size an attribute is compared with by gt, gte, lt and lte.
     *
     * The parameter names another field when the data has it: that field's
     * size, which must be of the same kind (number, string, array or file).
     * Otherwise it is a number. Null means the comparison cannot be made.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  string  $parameter
     * @return int|float|null
     */
    protected function comparedSize($attribute, $value, $parameter)
    {
        $field = $this->resolveWildcardIndexes($attribute, $parameter);

        if (Arr::has($this->data, $field)) {
            $other = Arr::get($this->data, $field);

            if ($this->deduceType($value, $attribute) !== $this->deduceType($other, $field)) {
                return null;
            }

            return $this->getSize($field, $other);
        }

        return is_numeric($parameter) ? $parameter + 0 : null;
    }

    /**
     * Validate that an attribute is greater than value.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateGt($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'gt');

        $compared = $this->comparedSize($attribute, $value, $parameters[0]);

        return !is_null($compared) && $this->getSize($attribute, $value) > $compared;
    }

    /**
     * Validate that an attribute is greater than or equal to value.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateGte($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'gte');

        $compared = $this->comparedSize($attribute, $value, $parameters[0]);

        return !is_null($compared) && $this->getSize($attribute, $value) >= $compared;
    }

    /**
     * Validate that an attribute is less than value.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateLt($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'lt');

        $compared = $this->comparedSize($attribute, $value, $parameters[0]);

        return !is_null($compared) && $this->getSize($attribute, $value) < $compared;
    }

    /**
     * Validate that an attribute is less than or equal to value.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @return bool
     */
    protected function validateLte($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'lte');

        $compared = $this->comparedSize($attribute, $value, $parameters[0]);

        return !is_null($compared) && $this->getSize($attribute, $value) <= $compared;
    }

    /**
     * Validate that an attribute is a valid e-mail address.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateEmail($attribute, $value)
    {
        return ! ! is_email($value);
    }

    /**
     * Validate the size of an attribute.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    protected function validateSize($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'size');

        return $this->getSize($attribute, $value) == $parameters[0];
    }

    /**
     * Validate the size of an attribute is greater than a minimum value.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    protected function validateMin($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'min');

        return $this->getSize($attribute, $value) >= $parameters[0];
    }

    /**
     * Validate the size of an attribute is less than a maximum value.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    protected function validateMax($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'max');

        return $this->getSize($attribute, $value) <= $parameters[0];
    }

    /**
     * Validate that two attributes match.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    protected function validateSame($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'same');

        $other = Arr::get($this->data, $this->resolveWildcardIndexes($attribute, $parameters[0]));

        return $value === $other;
    }

    /**
     * Validate that an attribute is a valid URL.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateUrl($attribute, $value)
    {
        return (bool) Str::isUrl($value);
    }

    /**
     * Validate that an attribute is a string.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    protected function validateString($attribute, $value)
    {
        return is_string($value);
    }

    /**
     * Validate that an attribute is a integer.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    protected function validateInt($attribute, $value)
    {
        return $this->validateInteger($attribute, $value);
    }

    /**
     * Validate that an attribute is a integer.
     *
     * Checks the value, not the PHP type: query strings and form posts always
     * arrive as strings, so "5" must pass. Booleans are excluded because
     * filter_var() reads true as 1.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    protected function validateInteger($attribute, $value)
    {
        return ! is_bool($value) && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate that an attribute has a given number of decimal places.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array<int, int|string>  $parameters
     * @return bool
     */
    protected function validateDecimal($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'decimal');

        if (!$this->validateNumeric($attribute, $value)) {
            return false;
        }

        $matches = [];

        // An optional sign, digits, an optional '.', then the decimals,
        // which are captured: '1.25' -> '25', '-3' -> '', '1e5' fails. \z is
        // the very end, so a trailing newline fails too (unlike $).
        if (preg_match('/^[+-]?\d*\.?(\d*)\z/', $value, $matches) !== 1) {
            return false;
        }

        $decimals = strlen(end($matches));

        if (!isset($parameters[1])) {
            return $decimals == $parameters[0];
        }

        return $decimals >= $parameters[0] && $decimals <= $parameters[1];
    }

    /**
     * Validate that an attribute is numeric.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateNumeric($attribute, $value)
    {
        return is_numeric($value);
    }

    /**
     * Validate that an attribute is a valid date.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    protected function validateDate($attribute, $value)
    {
        if ($value instanceof DateTimeInterface) {
            return true;
        }

        try {
            if ((!is_string($value) && !is_numeric($value)) || strtotime($value) === false) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        $date = date_parse($value);

        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /**
     * Validate that an attribute is a date after the given date or field.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateAfter($attribute, $value, $parameters)
    {
        return $this->compareDates($attribute, $value, $parameters, 'after', '>');
    }

    /**
     * Validate that an attribute is a date after or equal to the given date
     * or field.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateAfterOrEqual($attribute, $value, $parameters)
    {
        return $this->compareDates($attribute, $value, $parameters, 'after_or_equal', '>=');
    }

    /**
     * Validate that an attribute is a date before the given date or field.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateBefore($attribute, $value, $parameters)
    {
        return $this->compareDates($attribute, $value, $parameters, 'before', '<');
    }

    /**
     * Validate that an attribute is a date before or equal to the given date
     * or field.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateBeforeOrEqual($attribute, $value, $parameters)
    {
        return $this->compareDates($attribute, $value, $parameters, 'before_or_equal', '<=');
    }

    /**
     * Validate that an attribute is the same date as the given date or field.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateDateEquals($attribute, $value, $parameters)
    {
        return $this->compareDates($attribute, $value, $parameters, 'date_equals', '==');
    }

    /**
     * Compare an attribute's date with another field's or a given date.
     *
     * The parameter names another field when the data has it; otherwise it
     * is a date, such as 2026-01-01 or today. Either side that is not a date
     * fails the rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @param  string  $rule
     * @param  string  $operator
     * @return bool
     */
    protected function compareDates($attribute, $value, $parameters, $rule, $operator)
    {
        $this->requireParameterCount(1, $parameters, $rule);

        $date = $this->toDate($value, $this->dateFormatsOf($attribute));

        $field = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $compared = Arr::has($this->data, $field)
            ? $this->toDate(Arr::get($this->data, $field), $this->dateFormatsOf($field))
            : $this->toDate($parameters[0], $this->dateFormatsOf($attribute), true);

        if (!$date || !$compared) {
            return false;
        }

        $difference = $date->getTimestamp() - $compared->getTimestamp();

        switch ($operator) {
            case '>':
                return $difference > 0;
            case '>=':
                return $difference >= 0;
            case '<':
                return $difference < 0;
            case '<=':
                return $difference <= 0;
            default:
                return $difference === 0;
        }
    }

    /**
     * Read a value as a date in the plugin's timezone.
     *
     * With date_format rules the value is read with those formats, so
     * 01/02/2026 in d/m/Y is the 1st of February. A given date (the rule's
     * parameter) may also be anything PHP understands, such as tomorrow.
     *
     * @param  mixed  $value
     * @param  array  $formats
     * @param  bool  $isParameter
     * @return \WPSocialReviews\Framework\Support\DateTime|null
     */
    protected function toDate($value, array $formats = [], $isParameter = false)
    {
        if ($value instanceof DateTimeInterface) {
            return DateTime::create($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        foreach ($formats as $format) {
            try {
                $date = DateTime::createFromFormat('!' . $format, $value);
            } catch (Throwable $e) {
                continue;
            }

            if ($date->format($format) === $value) {
                return $date;
            }
        }

        if ($formats && !$isParameter) {
            return null;
        }

        try {
            return DateTime::create($value);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get the formats an attribute's date_format rule names.
     *
     * @param  string  $attribute
     * @return array
     */
    protected function dateFormatsOf($attribute)
    {
        foreach ($this->rules[$attribute] ?? [] as $rule) {
            if (is_string($rule) && strpos($rule, 'date_format:') === 0) {
                return explode(',', substr($rule, strlen('date_format:')));
            }
        }

        return [];
    }

    /**
     * Validate that an attribute matches a date format.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array<int, int|string>  $parameters
     * @return bool
     */
    protected function validateDateFormat($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'date_format');

        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        foreach ($parameters as $format) {
            try {
                $date = DateTime::createFromFormat('!'.$format, (string) $value);
            } catch (InvalidArgumentException | \ValueError $e) {
                // Not in this format; a NUL byte: not a date at all.
                continue;
            }

            if ($date->format($format) == $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate that an attribute is a boolean: true, false, 1, 0, "1" or "0".
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateBoolean($attribute, $value)
    {
        return in_array($value, [true, false, 1, 0, '1', '0'], true);
    }

    /**
     * Validate that an attribute is alphabetic.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateAlpha($attribute, $value)
    {
        // Only letters in any language (\pL) and the accent marks that
        // attach to them (\pM), at least one: 'naïve' passes, 'abc1' fails.
        // \z is the very end, so "abc\n" fails too (unlike $).
        return is_string($value) && preg_match('/^[\pL\pM]+\z/u', $value);
    }

    /**
     * Validate that an attribute is alphanum.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateAlphanum($attribute, $value)
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        // Letters in any language (\pL), accent marks (\pM) and numbers in
        // any script (\pN), at least one: 'abc1' passes, 'a_b-c' fails.
        // \z is the very end, so "abc\n" fails too (unlike $).
        return preg_match('/^[\pL\pM\pN]+\z/u', $value) > 0;
    }

    /**
     * Validate that an attribute is alphanum and -_.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validateAlphadash($attribute, $value)
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        // Letters in any language (\pL), accent marks (\pM), numbers in any
        // script (\pN), '_' and '-', at least one: 'a_b-c' passes. \z is
        // the very end, so "a_b\n" fails too (unlike $).
        return preg_match('/^[\pL\pM\pN_-]+\z/u', $value) > 0;
    }

    /**
     * Validate the guessed extension of a file upload is in a set of file extensions.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    protected function validateMimes($attribute, $value, $parameters)
    {
        if (! $this->isValidFileInstance($value)) {
            return false;
        }

        if ($this->shouldBlockPhpUpload($value, $parameters)) {
            return false;
        }

        /**
         * @var $value \WPSocialReviews\Framework\Validator\Contracts\File
         */
        // wp_check_filetype() matches case-insensitively but returns the
        // extension in the filename's own case ("clip.MOV" => "MOV"), so a
        // case-sensitive compare rejects valid uploads. Fold both sides.
        // Casts guard the false (no match) and [null] (bare "mimes:" via
        // str_getcsv) cases, and avoid strtolower(null) on PHP 8.1+.
        $extension = strtolower((string) $value->guessExtension());

        $allowed = array_map(function ($parameter) {
            return strtolower((string) $parameter);
        }, $parameters);

        return $value->getPath() != '' && '' !== $extension && in_array(
            $extension, $allowed, true
        );
    }

    /**
     * Validate the MIME type of a file upload attribute is in a set of MIME types.
     *
     * @param  string $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateMimetypes($attribute, $value, $parameters)
    {
        if (! $this->isValidFileInstance($value)) {
            return false;
        }

        if ($this->shouldBlockPhpUpload($value, $parameters)) {
            return false;
        }

        return $value->getPath() != '' && (
            in_array($value->getMimeType(), $parameters) ||
            in_array(explode('/', $value->getMimeType())[0].'/*', $parameters)
        );
    }

    /**
     * Validate that an attribute is a successfully uploaded file.
     *
     * @param  string $attribute
     * @param  mixed  $value
     * @return bool
     */
    protected function validateFile($attribute, $value)
    {
        return $this->isValidFileInstance($value);
    }

    /**
     * Validate the size of an uploaded image, as in Laravel: width, height,
     * min_width, max_width, min_height, max_height and ratio (1.5 or 3/2).
     * An SVG passes; a file that is not an image fails.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    protected function validateDimensions($attribute, $value, $parameters)
    {
        if (!$this->isValidFileInstance($value) || !$value instanceof \SplFileInfo) {
            return false;
        }

        $this->requireParameterCount(1, $parameters, 'dimensions');

        // An SVG passes before it is measured: it scales, and PHP 8.5's
        // getimagesize() reads its width and height where older ones can't.
        if ($this->isSvg($value->getPathname())) {
            return true;
        }

        $size = @getimagesize($value->getPathname());

        if (!$size) {
            return false;
        }

        [$width, $height] = $size;

        $constraints = [];

        foreach ($parameters as $parameter) {
            [$name, $limit] = array_pad(explode('=', $parameter, 2), 2, null);
            $constraints[trim($name)] = trim((string) $limit);
        }

        return !$this->failsBasicDimensionChecks($constraints, $width, $height)
            && !$this->failsRatioCheck($constraints, $width, $height);
    }

    /**
     * Whether a file's contents are an SVG image, which has no fixed size.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function isSvg($path)
    {
        if (!function_exists('finfo_open')) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = $finfo ? finfo_file($finfo, $path) : false;

        return in_array($type, ['image/svg+xml', 'image/svg'], true);
    }

    /**
     * Whether the image fails the width and height constraints.
     *
     * @param array $constraints
     * @param int $width
     * @param int $height
     *
     * @return bool
     */
    protected function failsBasicDimensionChecks($constraints, $width, $height)
    {
        return (isset($constraints['width']) && $constraints['width'] != $width)
            || (isset($constraints['min_width']) && $constraints['min_width'] > $width)
            || (isset($constraints['max_width']) && $constraints['max_width'] < $width)
            || (isset($constraints['height']) && $constraints['height'] != $height)
            || (isset($constraints['min_height']) && $constraints['min_height'] > $height)
            || (isset($constraints['max_height']) && $constraints['max_height'] < $height);
    }

    /**
     * Whether the image fails the ratio constraint, given as 1.5 or 3/2, to
     * within a pixel.
     *
     * @param array $constraints
     * @param int $width
     * @param int $height
     *
     * @return bool
     */
    protected function failsRatioCheck($constraints, $width, $height)
    {
        if (!isset($constraints['ratio'])) {
            return false;
        }

        [$numerator, $denominator] = array_replace(
            [1, 1], array_filter(sscanf($constraints['ratio'], '%f/%d'))
        );

        $precision = 1 / (max($width, $height) + 1);

        return abs($numerator / $denominator - $width / $height) > $precision;
    }

    /**
     * Check that the given value is a valid file instance.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isValidFileInstance($value)
    {
        return $value instanceof File && $value->isValid();
    }

    /**
     * Check if PHP uploads are explicitly allowed.
     *
     * @param mixed $value
     * @param array $parameters
     *
     * @return bool
     */
    protected function shouldBlockPhpUpload($value, $parameters)
    {
        if (in_array('php', $parameters)) {
            return false;
        }

        /**
         * @var $value \WPSocialReviews\Framework\Validator\Contracts\File
         */
        return strtolower($value->getClientOriginalExtension()) === 'php';
    }

    /**
     * Validate that an attribute exists even if not filled.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    protected function validatePresent($attribute, $value)
    {
        $attribute = str_replace(['[', ']'], ['.', ''], $attribute);

        return Arr::has($this->data, $attribute);
    }

    /**
     * Validate that an attribute has a given number of digits.
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * 
     * @return bool
     */
    protected function validateDigits($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'digits');

        // Digits only: '1.5', '-12' and '1e2' are numbers but not digits.
        return (is_int($value) || is_string($value))
                    && preg_match('/[^0-9]/', (string) $value) !== 1
                    && strlen((string) $value) == $parameters[0];
    }

    /**
     * Validate that an attribute is an array.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateArray($attribute, $value, $parameters = [])
    {
        if (! is_array($value)) {
            return false;
        }

        if (empty($parameters)) {
            return true;
        }

        return empty(array_diff_key($value, array_fill_keys($parameters, '')));
    }

    /**
     * Validate that an array has all of the given keys.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array<int, int|string>  $parameters
     * @return bool
     */
    protected function validateRequiredArrayKeys($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'required_array_keys');

        if (!is_array($value)) {
            return false;
        }

        foreach ($parameters as $key) {
            if (!Arr::exists($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that an attribute passes a regular expression check.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array<int, int|string>  $parameters
     * @return bool
     */
    protected function validateRegex($attribute, $value, $parameters)
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $this->requireParameterCount(1, $parameters, 'regex');

        return preg_match($parameters[0], $value) > 0;
    }

    /**
     * Validate an attribute is contained within a list of values.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateIn($attribute, $value, $parameters)
    {
        if (is_array($value) && $this->hasRule($attribute, 'Array')) {
            foreach ($value as $element) {
                if (is_array($element)) {
                    return false;
                }
            }

            return count(array_diff($value, $parameters)) === 0;
        }

        // Compared as strings, strictly: '01' is not 1.
        return !is_array($value) && in_array((string) $value, $parameters, true);
    }

    /**
     * Validate an attribute is not contained within a list of values.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array  $parameters
     * @return bool
     */
    protected function validateNotIn($attribute, $value, $parameters)
    {
        return !$this->validateIn($attribute, $value, $parameters);
    }
}
