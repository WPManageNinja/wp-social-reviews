<?php

namespace WPSocialReviews\Framework\Support;

use RuntimeException;
use InvalidArgumentException;

class Serializer
{
	/**
     * Serialize data into the specified format.
     *
     * @param mixed  $data   Data to serialize.
     * @param string $format Serialization format ('json' or 'php').
     * @param int|null $jsonOptions Options for JSON encoding
     * (default: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).
     * @return string|false Serialized data; false when the data can't be
     * written as JSON, and the debug log says why.
     * @throws InvalidArgumentException If the format is unsupported.
     */
    public static function serialize($data, $format = 'json', $jsonOptions = null)
    {
    	$jsonOptions ??= JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	    
	    if ($format === 'json') {
	        $json = json_encode($data, $jsonOptions);

	        if ($json === false) {
	            DebugLog::warn(
	                "Serializer::serialize() couldn't write the data as JSON and returned false: "
	                . json_last_error_msg()
	            );
	        }

	        return $json;
	    } elseif ($format === 'php') {
	    	if (static::isSerialized($data)) {
	    		return $data;
	    	}
	        return serialize($data);
	    }

	    throw new InvalidArgumentException("Unsupported format: {$format}");
	}

    /**
     * Deserialize data from JSON or PHP serialized format.
     *
     * @param mixed $data   The serialized data. Anything but text, such as
     *                      the array get_option() gives, comes back as it is.
     * @param mixed $option The option:
     *
     * - true: JSON objects come back as arrays.
     *
     * - An array of class names: the only classes PHP serialized data may
     * rebuild. Any other class, and every class without such a list, comes
     * back as __PHP_Incomplete_Class, so none of its code runs.
     *
     * @return mixed The deserialized data.
     * @throws RuntimeException If the data cannot be deserialized.
     */
    public static function deserialize($data, $option = false)
    {
        if (!is_string($data)) {
            return $data;
        }

        if (static::isJson($data)) {
            $decoded = json_decode($data, !is_array($option) && (bool) $option);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                	'Invalid JSON: ' . json_last_error_msg()
                );
            }

            return $decoded;
        }

        if (static::isSerialized($data)) {
	        set_error_handler(function ($errno, $errstr) {
	            if (
	            	$errno === E_WARNING && strpos(
	            		$errstr, 'unserialize(): Error at offset'
	            	) !== false
	            ) {
	                throw new RuntimeException(
	                	'Invalid serialized data or potential security risk.'
	                );
	            }
	        });

	        try {
	            // Only the classes named in a list are rebuilt: a true meant for
	            // JSON's arrays must not let every class on the site run
	            $result = unserialize($data, [
	                'allowed_classes' => is_array($option) ? $option : false
	            ]);

	            if ($result === false && $data !== serialize(false)) {
	                throw new RuntimeException(
	                    'Invalid serialized data or potential security risk.'
	                );
	            }

	            return $result;
	        } catch (RuntimeException $e) {
	            throw $e;
	        } finally {
	            restore_error_handler();
	        }
	    }

        throw new RuntimeException(
        	'Invalid data format: Neither JSON nor serialized PHP data.'
        );
    }

    /**
     * Check if the data is a valid JSON string.
     *
     * @param string $data Data to check.
     * @return bool True if the data is valid JSON.
     */
    public static function isJson($data)
    {
        if (!is_string($data)) {
            return false;
        }

        json_decode($data);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Check if the data is a serialized PHP string.
     *
     * @param string $data Data to check.
     * @return bool True if the data is serialized.
     */
    public static function isSerialized($data)
    {
        return is_serialized($data);
    }
}
