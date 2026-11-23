<?php

namespace WPSocialReviews\Framework\Validator;

use WPSocialReviews\Framework\Support\Arr;
use WPSocialReviews\Framework\Support\Str;

trait MessageBag
{
    /**
     * The default message bag.
     *
     * @var array
     */
    protected $bag = [
        'accepted'    => 'The :attribute field must be accepted.',
        'after'       => 'The :attribute must be a date after :date.',
        'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
        'array'       => 'The :attribute must be an array.',
        'before'      => 'The :attribute must be a date before :date.',
        'before_or_equal' => 'The :attribute must be a date before or equal to :date.',
        'boolean'     => 'The :attribute field must be true or false.',
        'alpha'       => 'The :attribute must contain only alphabetic characters.',
        'alphanum'    => 'The :attribute must contain only alphanumeric characters.',
        'alphadash'   => 'The :attribute must contain only alphanumeric and _- characters.',
        'email'       => 'The :attribute must be a valid email address.',
        'date'        => 'The :attribute is not a valid date.',
        'date_equals' => 'The :attribute must be a date equal to :date.',
        'date_format' => 'The :attribute must match the format :format.',
        'decimal'     => 'The :attribute must have :decimal decimal places.',
        'exists'      => 'The selected :attribute is invalid.',
        'gt'          => 'The :attribute must be greater than :value.',
        'lt'          => 'The :attribute must be less than :value.',
        'gte'          => 'The :attribute must be greater than or equal to :value.',
        'lte'          => 'The :attribute must be less than or equal to :value.',
        'in'          => 'The selected :attribute is invalid.',
        'not_in'          => 'The selected :attribute is invalid.',
        'max'         => [
            'numeric' => 'The :attribute may not be greater than :max.',
            'file'    => 'The :attribute may not be greater than :max kilobytes.',
            'string'  => 'The :attribute may not be greater than :max characters.',
            'array'   => 'The :attribute may not have more than :max items.',
        ],
        'mimes'       => 'The :attribute must be a file of type: :values.',
        'mimetypes'   => 'The :attribute must be a file of type: :values.',
        'file'        => 'The :attribute must be a file.',
        'filled'      => 'The :attribute field must have a value.',
        'min'         => [
            'numeric' => 'The :attribute must be at least :min.',
            'file'    => 'The :attribute must be at least :min kilobytes.',
            'string'  => 'The :attribute must be at least :min characters.',
            'array'   => 'The :attribute must have at least :min items.',
        ],
        'string'      => 'The :attribute must be a string.',
        'integer'     => 'The :attribute must be an integer.',
        'numeric'     => 'The :attribute must be a number.',
        'present'     => 'The :attribute field must be present.',
        'regex'       => 'The :attribute format is invalid.',
        'required'    => 'The :attribute field is required.',
        'required_array_keys'    => 'The :attribute must be an array and should contain :keys.',
        'required_if' => 'The :attribute field is required when :other is :value.',
        'required_unless' => 'The :attribute field is required unless :other is in :values.',
        'required_with' => 'The :attribute field is required when the :other field is present.',
        'required_with_all' => 'The :attribute field is required when :other field(s) are present.',
        'required_without' => 'The :attribute field is required when :other field(s) are not present.',
        'required_without_all' => 'The :attribute field is required when :other field(s) are all missing.',
        'same'        => 'The :attribute and :other must match.',
        'size'        => [
            'numeric' => 'The :attribute must be :size.',
            'file'    => 'The :attribute must be :size kilobytes.',
            'string'  => 'The :attribute must be :size characters.',
            'array'   => 'The :attribute must contain :size items.',
        ],
        'url'         => 'The :attribute format is invalid.',
        'unique'      => 'The :attribute attribute is already taken and must be unique.',
        'dimensions'  => 'The :attribute has invalid image dimensions.',
        'digits'      => 'The :attribute must be :digits characters.'
    ];

    /**
     * Generate a validation error message.
     *
     * @param $attribute
     * @param $rule
     * @param $parameters
     * @param $originalRuleKey
     *
     * @return mixed
     */
    protected function generate($attribute, $rule, $parameters, $originalRuleKey = null)
    {
        $method = 'replace'.str_replace(
                ' ', '', ucwords(str_replace(['-', '_'], ' ', $rule))
            );

        $originalKey = '';

        if (!empty($originalRuleKey)){
            $originalKey = $originalRuleKey.'.'.$rule;
        }

        if ($this->hasMethod($method)) {
            return $this->$method($attribute, $parameters, $originalKey);
        }

        $text = $this->getReplacementText(
            $attribute.'.'.$rule, $rule, $originalKey
        );

        if (!is_string($text) || $text === '') {
            return $this->generateDefaultMessage($attribute, $parameters);
        }

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Fallback message generator for the failed validation.
     * @param  string $attribute
     * @param  array $parameters
     * @return string
     */
    protected function generateDefaultMessage($attribute, $parameters)
    {
        $msg = "The {$attribute} field has been failed the validation";

        if ($parameters) {
            $msg .= " with parameter \"{$parameters[0]}\"";
        }

        return ($msg . '.');
    }


    /**
     * Get the replacement text of the error message.
     *
     * A custom message is looked up by the field and rule (hours.start.min),
     * then by the rule's key as written (slots.*.start.min), then by any
     * wildcard key that matches the field (hours.*.min), as in Laravel.
     *
     * @param $customKey
     * @param $bagAccessor
     * @param $originalKey
     *
     * @return string
     */
    protected function getReplacementText($customKey, $bagAccessor, $originalKey = null)
    {
        if (isset($this->customMessages[$customKey])) {
            return $this->customMessages[$customKey];
        } elseif (isset($this->customMessages[$originalKey])) {
            return $this->customMessages[$originalKey];
        }

        foreach ($this->customMessages as $key => $message) {
            if ($this->matchesWildcardKey($key, $customKey)) {
                return $message;
            }
        }

        return Arr::get($this->bag, $bagAccessor, '');
    }

    /**
     * Get the name a message gives a field.
     *
     * A name from the validator's attributes wins, on the exact field or on
     * a wildcard key (items.*.name); otherwise the field reads as words, as
     * in Laravel: support_email and supportEmail become "support email".
     *
     * @param  string  $attribute
     * @return string
     */
    protected function displayableAttribute($attribute)
    {
        if (isset($this->customAttributes[$attribute])) {
            return $this->customAttributes[$attribute];
        }

        foreach ($this->customAttributes as $key => $name) {
            if ($this->matchesWildcardKey($key, $attribute)) {
                return $name;
            }
        }

        return str_replace('_', ' ', Str::snake($attribute));
    }

    /**
     * Determine whether a wildcard key matches a dotted key.
     *
     * Each * stands for one key at its place: items.*.name matches
     * items.1.name, but not items.1.meta.name.
     *
     * @param  string  $wildcard
     * @param  string  $key
     * @return bool
     */
    protected function matchesWildcardKey($wildcard, $key)
    {
        if (strpos($wildcard, '*') === false) {
            return false;
        }

        $pattern = str_replace('\\*', '[^.]+', preg_quote($wildcard, '/'));

        return (bool) preg_match('/^' . $pattern . '$/', $key);
    }

    /**
     * Make bag accessor key.
     *
     * @param $attribute
     * @param $rule
     *
     * @return string
     */
    protected function makeBagKey($attribute, $rule)
    {
        $type = $this->deduceType(
            $this->getValue($attribute), $attribute
        );

        return $rule.'.'.$type;
    }

    /**
     * Replace all place-holders for the string rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     * @return string
     */
    protected function replaceString($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText(
            $attribute.'.string', 'string', $originalKey
        );

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the int|integer rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     * @return string
     */
    protected function replaceInt($attribute, $parameters, $originalKey)
    {
        return $this->replaceInteger($attribute, $parameters, $originalKey);
    }

    /**
     * Replace all place-holders for the int|integer rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceInteger($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText(
            $attribute.'.integer', 'integer', $originalKey
        );

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the alpha rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceAlpha($attribute, $parameters,  $originalKey)
    {
        $text = $this->getReplacementText(
            $attribute.'.alpha', 'alpha',  $originalKey
        );

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the alphanum rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceAlphanum($attribute, $parameters,  $originalKey)
    {
        $text = $this->getReplacementText(
            $attribute.'.alphanum', 'alphanum', $originalKey
        );

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the alphadash rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceAlphadash($attribute, $parameters,  $originalKey)
    {
        $text = $this->getReplacementText(
            $attribute.'.alphadash', 'alphadash', $originalKey
        );

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the required rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceRequired($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText(
            $attribute.'.required', 'required', $originalKey
        );

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the required if rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceRequiredIf($attribute, $parameters,  $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.required_if', 'required_if', $originalKey);

        $value = end($parameters);

        return str_replace([
            ':attribute', ':other', ':value'],
            [$this->displayableAttribute($attribute), $this->displayableAttribute($parameters[0]), $value],
            $text
        );
    }

    /**
     * Replace all place-holders for the required unless rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceRequiredUnless($attribute, $parameters, $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.required_unless', 'required_unless', $originalKey);

        return str_replace(
            [':attribute', ':other', ':values'],
            [
                $this->displayableAttribute($attribute),
                $this->displayableAttribute($parameters[0]),
                implode(', ', array_slice($parameters, 1)),
            ],
            $text
        );
    }

    /**
     * Replace all place-holders for the required with rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceRequiredWith($attribute, $parameters,  $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.required_with', 'required_with', $originalKey);

        $parameters = array_map([$this, 'displayableAttribute'], $parameters);

        $other = '';

        if (count($parameters) === 1) {
            $other = $parameters[0];
        } elseif (count($parameters) === 2) {
            $other = implode(' or ', $parameters);
        } else {
            $last = array_pop($parameters);
            $other = implode(', ', $parameters) . ' or ' . $last;
        }

        return str_replace([
            ':attribute', ':other'],
            [$this->displayableAttribute($attribute), $other],
            $text
        );
    }

    /**
     * Replace all place-holders for the required with all rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceRequiredWithAll($attribute, $parameters,  $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.required_with_all', 'required_with_all', $originalKey);

        $parameters = array_map([$this, 'displayableAttribute'], $parameters);

        $other = '';

        if (count($parameters) === 1) {
            $other = $parameters[0];
        } elseif (count($parameters) === 2) {
            $other = $parameters[0] . ' and ' . $parameters[1];
        } else {
            $last = array_pop($parameters);
            $other = 'all ' . implode(', ', $parameters) . ' and ' . $last;
        }

        return str_replace(
            [':attribute', ':other'],
            [$this->displayableAttribute($attribute), $other],
            $text
        );
    }

        /**
     * Replace all place-holders for the required without rule.
     *
     * @param string $attribute
     * @param array $parameters
     * @param string $originalKey
     * @return string
     */
    protected function replaceRequiredWithout($attribute, $parameters, $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute . '.required_without', 'required_without', $originalKey);

        $parameters = array_map([$this, 'displayableAttribute'], $parameters);

        $other = '';

        if (count($parameters) === 1) {
            $other = $parameters[0];
        } elseif (count($parameters) === 2) {
            $other = implode(' or ', $parameters);
        } else {
            $last = array_pop($parameters);
            $other = implode(', ', $parameters) . ' or ' . $last;
        }

        // Default message
        return str_replace(
            [':attribute', ':other'],
            [$this->displayableAttribute($attribute), $other],
            $text
        );
    }

    /**
     * Replace all place-holders for the required without all rule.
     *
     * @param string $attribute
     * @param array $parameters
     * @param string $originalKey
     * @return string
     */
    protected function replaceRequiredWithoutAll($attribute, $parameters, $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute . '.required_without_all', 'required_without_all', $originalKey);

        $parameters = array_map([$this, 'displayableAttribute'], $parameters);

        $other = '';

        if (count($parameters) === 1) {
            $other = $parameters[0];
        } elseif (count($parameters) === 2) {
            $other = $parameters[0] . ' and ' . $parameters[1];
        } else {
            $last = array_pop($parameters);
            $other = 'all ' . implode(', ', $parameters) . ' and ' . $last;
        }

        // Default message
        return str_replace(
            [':attribute', ':other'],
            [$this->displayableAttribute($attribute), $other],
            $text
        );
    }

    /**
     * Replace placeholders in a validation message for required array keys.
     *
     * @param string $attribute
     * @param array  $parameters
     * @param string $originalKey
     *
     * @return string
     */
    protected function replaceRequiredArrayKeys(
        $attribute,
        $parameters, 
        $originalKey
    )
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.required_array_keys', 'required_array_keys', $originalKey);

        $other = '';

        if (count($parameters) === 1) {
            $other = $parameters[0] . ' key';
        } elseif (count($parameters) === 2) {
            $other = $parameters[0] . ' and ' . $parameters[1] . ' keys';
        } else {
            $last = array_pop($parameters);
            $other = 'all ' . implode(', ', $parameters) . ' and ' . $last . ' keys';
        }

        return str_replace(
            [':attribute', ':keys'],
            [$this->displayableAttribute($attribute), $other],
            $text
        );
    }

    /**
     * Replace all place-holders for the gt rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceGt($attribute, $parameters,  $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.gt', 'gt', $originalKey);

        $value = $this->comparedSize($attribute, $this->getValue($attribute), $parameters[0]) ?? $parameters[0];

        return str_replace([
            ':attribute', ':value'],
            [$this->displayableAttribute($attribute), $value],
            $text
        );
    }

    /**
     * Replace all place-holders for the lt rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceLt($attribute, $parameters,  $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.lt', 'lt', $originalKey);

        $value = $this->comparedSize($attribute, $this->getValue($attribute), $parameters[0]) ?? $parameters[0];

        return str_replace([
            ':attribute', ':value'],
            [$this->displayableAttribute($attribute), $value],
            $text
        );
    }

    /**
     * Replace all place-holders for the gte rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceGte($attribute, $parameters,  $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.gte', 'gte', $originalKey);

        $value = $this->comparedSize($attribute, $this->getValue($attribute), $parameters[0]) ?? $parameters[0];

        return str_replace([
            ':attribute', ':value'],
            [$this->displayableAttribute($attribute), $value],
            $text
        );
    }

    /**
     * Replace all place-holders for the lte rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceLte($attribute, $parameters,  $originalKey)
    {
        $parameters[0] = $this->resolveWildcardIndexes($attribute, $parameters[0]);

        $text = $this->getReplacementText($attribute.'.lte', 'lte', $originalKey);

        $value = $this->comparedSize($attribute, $this->getValue($attribute), $parameters[0]) ?? $parameters[0];

        return str_replace([
            ':attribute', ':value'],
            [$this->displayableAttribute($attribute), $value],
            $text
        );
    }

    /**
     * Replace all place-holders for the email rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceEmail($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText(
            $attribute.'.email', 'email', $originalKey
        );

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the after rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceAfter($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.after', 'after', $originalKey);

        return str_replace(
            [':attribute', ':date'],
            [$this->displayableAttribute($attribute), $this->displayableDate($attribute, $parameters[0])],
            $text
        );
    }

    /**
     * Replace all place-holders for the after_or_equal rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceAfterOrEqual($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.after_or_equal', 'after_or_equal', $originalKey);

        return str_replace(
            [':attribute', ':date'],
            [$this->displayableAttribute($attribute), $this->displayableDate($attribute, $parameters[0])],
            $text
        );
    }

    /**
     * Replace all place-holders for the before rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceBefore($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.before', 'before', $originalKey);

        return str_replace(
            [':attribute', ':date'],
            [$this->displayableAttribute($attribute), $this->displayableDate($attribute, $parameters[0])],
            $text
        );
    }

    /**
     * Replace all place-holders for the before_or_equal rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceBeforeOrEqual($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.before_or_equal', 'before_or_equal', $originalKey);

        return str_replace(
            [':attribute', ':date'],
            [$this->displayableAttribute($attribute), $this->displayableDate($attribute, $parameters[0])],
            $text
        );
    }

    /**
     * Replace all place-holders for the date_equals rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceDateEquals($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.date_equals', 'date_equals', $originalKey);

        return str_replace(
            [':attribute', ':date'],
            [$this->displayableAttribute($attribute), $this->displayableDate($attribute, $parameters[0])],
            $text
        );
    }

    /**
     * Get what a date rule's message shows for its parameter: the other
     * field's name when it names a field, otherwise the date as written.
     *
     * @param  string  $attribute
     * @param  string  $parameter
     * @return string
     */
    protected function displayableDate($attribute, $parameter)
    {
        $field = $this->resolveWildcardIndexes($attribute, $parameter);

        return Arr::has($this->data, $field)
            ? $this->displayableAttribute($parameter)
            : $parameter;
    }

    /**
     * Replace all place-holders for the decimal rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceDecimal($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.decimal', 'decimal', $originalKey);

        return str_replace(
            [':attribute', ':decimal'], [$this->displayableAttribute($attribute), implode('-', $parameters)], $text
        );
    }

    /**
     * Replace all place-holders for the date_format rule.
     *
     * The format fills both :format, as in Laravel, and :value, the name
     * older messages use.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceDateformat($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.date_format', 'date_format', $originalKey);

        return str_replace(
            [':attribute', ':format', ':value'],
            [$this->displayableAttribute($attribute), $parameters[0], $parameters[0]],
            $text
        );
    }

    /**
     * Replace all place-holders for the size rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceSize($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.size', $this->makeBagKey($attribute, 'size'),  $originalKey);

        return str_replace(
            [':attribute', ':size'], [$this->displayableAttribute($attribute), $parameters[0]], $text
        );
    }

    /**
     * Replace all place-holders for the min rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceMin($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.min', $this->makeBagKey($attribute, 'min'),  $originalKey);

        return str_replace(
            [':attribute', ':min'], [$this->displayableAttribute($attribute), $parameters[0]], $text
        );
    }

    /**
     * Replace all place-holders for the max rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceMax($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.max', $this->makeBagKey($attribute, 'max'), $originalKey);

        return str_replace(
            [':attribute', ':max'], [$this->displayableAttribute($attribute), $parameters[0]], $text
        );
    }

    /**
     * Replace all place-holders for the min rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceSame($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.same', 'same', $originalKey);

        return str_replace(
            [':attribute', ':other'], [$this->displayableAttribute($attribute), $this->displayableAttribute($parameters[0])], $text
        );
    }

    /**
     * Replace all place-holders for the file rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceFile($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.file', 'file', $originalKey);

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the url rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceUrl($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.url', 'url', $originalKey);

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the numeric rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceNumeric($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.numeric', 'numeric', $originalKey);

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the mimes rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceMimes($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.mimes', 'mimes', $originalKey);

        return str_replace([':attribute', ':values'], [$this->displayableAttribute($attribute), implode(', ', $parameters)], $text);
    }

    /**
     * Replace all place-holders for the mimetypes rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceMimetypes($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.mimetypes', 'mimetypes', $originalKey);

        return str_replace([':attribute', ':values'], [$this->displayableAttribute($attribute), implode(', ', $parameters)], $text);
    }

    /**
     * Replace all place-holders for the unique rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceUnique($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.unique', 'unique', $originalKey);

        return str_replace(':attribute', $this->displayableAttribute($attribute), $text);
    }

    /**
     * Replace all place-holders for the digits rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceDigits($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.digits', 'digits', $originalKey);

        return str_replace([':attribute', ':digits'], [$this->displayableAttribute($attribute), $parameters[0]], $text);
    }

    /**
     * Replace all place-holders for the array rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceArray($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.array', 'array', $originalKey);

        return str_replace([':attribute', ':array'], [$this->displayableAttribute($attribute)], $text);
    }

    /**
     * Replace all place-holders for the in rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceIn($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.in', 'in', $originalKey);

        return str_replace([':attribute', ':in'], [$this->displayableAttribute($attribute)], $text);
    }

    /**
     * Replace all place-holders for the not_in rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceNotIn($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.not_in', 'not_in', $originalKey);

        return str_replace([':attribute', ':not_in'], [$this->displayableAttribute($attribute)], $text);
    }

    /**
     * Replace all place-holders for the exista rule.
     *
     * @param $attribute
     * @param $parameters
     * @param $originalKey
     *
     * @return string
     */
    protected function replaceExists($attribute, $parameters, $originalKey)
    {
        $text = $this->getReplacementText($attribute.'.exists', 'exists', $originalKey);

        return str_replace([':attribute', ':exists'], [$this->displayableAttribute($attribute)], $text);
    }
}
