<?php
defined('ABSPATH') or die;

use WPSocialReviews\App\Services\Platforms\Reviews\Helper;
use WPSocialReviews\App\Models\Review;
use WPSocialReviews\Framework\Support\Arr;

/**
 * Declare functions only, never call them here. This file loads before the
 * framework, so $app isn't available.
 */


is_readable(__DIR__ . '/globals_dev.php') && include 'globals_dev.php';

//if (!function_exists('dd')) {
//    function dd()
//    {
//        foreach (func_get_args() as $arg) {
//            echo "<pre>";
//            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- This is a development debugging function
//            print_r($arg);
//            echo "</pre>";
//        }
//        die();
//    }
//}

/**
 * Reviews for the given platforms, filtered by template-style $filters.
 *
 * @param array $platforms
 * @param array $filters
 *
 * @return array
 */
if(!function_exists('wpsrGetReviews')){
    function wpsrGetReviews($platforms = [], $filters = [])
    {
        $validPlatforms = Helper::validPlatforms($platforms);
        return Review::filteredReviewsQuery($validPlatforms, $filters)->get()->toArray();
    }
}

/**
 * Business info for the given platforms, limited to $sourceIds when given.
 *
 * @param array $platforms
 * @param array $sourceIds
 *
 * @return array
 */
if(!function_exists('wpsrGetReviewsBusinessInfo')){
    function wpsrGetReviewsBusinessInfo($platforms = [], $sourceIds = [])
    {
        $validPlatforms = Helper::validPlatforms($platforms);
        return Helper::getSelectedBusinessInfoByPlatforms($validPlatforms, $sourceIds);
    }
}


if(!function_exists('wpsrGetReviewsDataFromTemplate')){
    function wpsrGetReviewsDataFromTemplate($templateId = null)
    {
        $data['template_meta'] = [];
        $data['reviews'] = [];
        $data['business_info'] = [];

        $encodedMeta = get_post_meta($templateId, '_wpsr_template_config', true);
        $template_meta = json_decode($encodedMeta, true);

        if(!empty($template_meta)) {
            $data['template_meta'] = Helper::formattedTemplateMeta($template_meta);

            $platforms = Arr::get($template_meta, 'platform', []);
            $selectedBusinesses = Arr::get($template_meta, 'selectedBusinesses', []);

            $data['business_info'] = wpsrGetReviewsBusinessInfo($platforms, $selectedBusinesses);
            $data['reviews'] = wpsrGetReviews($platforms, $template_meta);
        }

        return $data;
    }
}

/**
 * The app instance, or a service bound to $key.
 *
 * @param string $key
 *
 * @return mixed
 */
if(!function_exists('wpsrSocialReviews')) {
    function wpsrSocialReviews($key = null)
    {
        return \WPSocialReviews\App\App::make($key);
    }
}

if (! function_exists('wpsrDb')) {
    /**
     * @return \WPSocialReviews\Framework\Database\Query\Builder
     */
    function wpsrDb()
    {
        return wpsrSocialReviews('db');
    }
}

if(! function_exists('wpsr_backend_sanitizer')) {
    /**
     * Recursively sanitizes an array of inputs based on a sanitization map.
     *
     * @param array $inputs The array of data to sanitize (passed by reference).
     * @param array $sanitizeMap A map of 'key' => 'sanitization_function'. Supports dot notation for nested keys.
     * @param string $defaultMethod The default sanitization function to use if no specific rule is found.
     * @param string $keyPrefix Internal use only. The prefix for the current recursion level.
     * @return array The sanitized array, passed through a filter.
     */
    function wpsr_backend_sanitizer(&$inputs, $sanitizeMap = [], $defaultMethod = 'sanitize_text_field', $keyPrefix = '')
    {
        $originalValues = $inputs;

        foreach ($inputs as $key => &$value) {
            // Dot-notation key for the map, e.g. 'fields.author_company'.
            $fullKey = empty($keyPrefix) ? $key : $keyPrefix . '.' . $key;

            if (is_array($value)) {
                $method = Arr::get($sanitizeMap, $fullKey);

                if ($method === 'wpsr_array_map_absint') {
                    $value = array_map('absint', $value);
                } elseif ($method === 'wpsr_array_map_sanitize_text_field') {
                    $value = array_map('sanitize_text_field', $value);
                } else {
                    wpsr_backend_sanitizer($value, $sanitizeMap, $defaultMethod, $fullKey);
                }
            } else {
                $method = Arr::get($sanitizeMap, $fullKey);

                if (is_callable($method)) {
                    $value = call_user_func($method, $value);
                } elseif (!empty($defaultMethod) && is_callable($defaultMethod)) {
                    $value = call_user_func($defaultMethod, $value);
                } elseif (!empty($defaultMethod) && function_exists($defaultMethod)) {
                    $value = $defaultMethod($value);
                }
            }
        }

        return apply_filters('wpsocialreviews/backend_sanitized_values', $inputs, $originalValues);
    }
}

if (! function_exists('wpsr_sanitize_boolean')) {
    /**
     * Normalize boolean-like values using WordPress REST helper when available.
     * Pass $as_string = false to get a real boolean true/false.
     *
     * @param mixed $value
     * @param bool $as_string
     * @return string|bool
     */
    function wpsr_sanitize_boolean($value, $as_string = true)
    {
        $bool = rest_sanitize_boolean($value);

        if ($as_string) {
            return $bool ? 'true' : 'false';
        }

        return (bool) $bool;
    }
}

if (! function_exists('wpsr_sanitize_opaque_token')) {
    /**
     * Charset check for opaque OAuth-bridge tokens: payload (base64), result_id
     * (base64url) and flow_id (UUID4). A value with any other character becomes ''
     * so it fails downstream instead of being silently mangled.
     *
     * @param mixed $value
     * @return string sanitized value, or '' if it contains any disallowed character
     */
    function wpsr_sanitize_opaque_token($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        // Union of standard base64 (+ /), base64url (- _), padding (=), and UUID (-).
        return preg_match('/^[A-Za-z0-9+\/=_-]+$/', $value) ? $value : '';
    }
}

if (! function_exists('wpsr_sanitize_recipients')) {
    /**
     * Sanitize recipients (comma-separated string or array) with sanitize_email.
     * Returns a comma-separated string, or an array when $as_string is false.
     *
     * @param string|array $value
     * @param bool $as_string
     * @return string|array
     */
    function wpsr_sanitize_recipients($value, $as_string = true)
    {
        if (is_null($value) || $value === '') {
            return $as_string ? '' : [];
        }

        $items = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
        $clean = array();

        foreach ($items as $email) {
            if ($email === '') {
                continue;
            }
            $s = sanitize_email($email);
            if (!empty($s)) {
                $clean[] = $s;
            }
        }

        if ($as_string) {
            return implode(',', $clean);
        }

        return $clean;
    }
}

if(! function_exists('wpsr_sanitize_css_selector')) {
    /**
     * Sanitizes a CSS selector, allowing only safe characters.
     */
    function wpsr_sanitize_css_selector($selector) {
        // Alphanumerics, whitespace and . # : > + ~ [ ] - _ * , | = "
        return preg_replace('/[^a-zA-Z0-9\s\.#:>+\~\[\]\-\_\*\,\|\="]/', '', $selector);
    }
}

if(! function_exists('wpsr_sanitize_color')) {
    /**
     * Sanitizes a CSS color: hex, rgb(a)/hsl(a) within range, or a few named colors.
     */
    function wpsr_sanitize_color($color) {
        $color = trim($color);
        if (empty($color)) {
            return '';
        }

        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)) {
            return sanitize_hex_color($color);
        }

        if (preg_match('/^(rgba?|hsla?)\(\s*(\d{1,3}%?\s*,\s*){2}\d{1,3}%?\s*(,\s*[0-9.]+)?\s*\)$/', $color, $wpsr_match)) {
            $wpsr_fn = strtolower($wpsr_match[1]);
            $wpsr_parts = array_map('trim', explode(',', trim(substr($color, strpos($color, '(') + 1), ' )')));
            $wpsr_alpha = count($wpsr_parts) === 4 ? array_pop($wpsr_parts) : null;

            // The pattern checks shape only; rgb(999,0,0) and rgba(0,0,0,1.5) are caught here.
            $wpsr_limits = ($wpsr_fn === 'hsl' || $wpsr_fn === 'hsla') ? [360, 100, 100] : [255, 255, 255];
            foreach ($wpsr_parts as $wpsr_index => $wpsr_part) {
                $wpsr_limit = substr($wpsr_part, -1) === '%' ? 100 : $wpsr_limits[$wpsr_index];
                if ((float) rtrim($wpsr_part, '%') > $wpsr_limit) {
                    return '';
                }
            }

            if ($wpsr_alpha !== null && (float) $wpsr_alpha > 1) {
                return '';
            }

            return $color;
        }

        $named_colors = ['inherit', 'transparent', 'currentcolor', 'black', 'white', 'red', 'green', 'blue', 'yellow', 'cyan', 'magenta'];
        if (in_array(strtolower($color), $named_colors)) {
            return $color;
        }

        return '';
    }
}

if(! function_exists('wpsr_sanitize_css_unit')) {
    /**
     * Sanitizes a CSS unit value (e.g., 10px, 1.5em, 100%).
     */
    function wpsr_sanitize_css_unit($value) {
        if (preg_match('/^(\-?\d*\.?\d+)(px|em|rem|%|vw|vh|vmin|vmax|ex|ch|cm|mm|in|pt|pc|deg|rad|turn|s|ms)?$/', $value)) {
            return $value;
        }
        return '';
    }
}

if(! function_exists('wpsr_sanitize_css_keyword')) {
    /**
     * Sanitizes a CSS keyword (e.g., bold, underline, center, block).
     */
    function wpsr_sanitize_css_keyword($value) {
        return sanitize_key($value);
    }
}

if(! function_exists('wpsr_sanitize_styles_config')) {
    /**
     * Sanitizes a deeply nested styles array (like styles_config or responsive_styles).
     *
     * @param array $styles_config The raw styles array.
     * @return array The sanitized styles array.
     */
    function wpsr_sanitize_styles_config($styles_config) {
        if (!is_array($styles_config)) {
            return [];
        }

        $sanitized_config = [];
        foreach ($styles_config as $key => $value) {
            if ($key === 'styles' && is_array($value)) {
                $sanitized_config[$key] = wpsr_sanitize_style_properties($value);
            } else {
                $sanitized_config[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized_config;
    }
}

if(! function_exists('wpsr_sanitize_style_properties')) {
    /**
     * Recursively sanitizes an array of style properties for a single element.
     *
     * @param array $properties The style properties array.
     * @return array The sanitized properties.
     */
    function wpsr_sanitize_style_properties($properties) {
        $sanitized_properties = [];
        foreach ($properties as $property_name => $property_data) {
            $sanitized_property_data = [];
            foreach ($property_data as $data_key => $data_value) {
                switch ($data_key) {
                    case 'selector':
                        $sanitized_property_data[$data_key] = wpsr_sanitize_css_selector($data_value);
                        break;

                    case 'color':
                    case 'typography':
                    case 'slider':
                    case 'padding':
                    case 'border':
                    case 'border_radius':
                    case 'box_shadow':
                    case 'spacing': // often used instead of padding
                        $sanitized_property_data[$data_key] = wpsr_sanitize_style_data($data_value);
                        break;

                    default:
                        $sanitized_property_data[$data_key] = sanitize_text_field($data_value);
                }
            }
            $sanitized_properties[$property_name] = $sanitized_property_data;
        }
        return $sanitized_properties;
    }
}

if(! function_exists('wpsr_sanitize_style_data')) {

    /**
     * Sanitizes a generic style data array (e.g., a 'color' or 'typography' array).
     *
     * @param array $data The style data array.
     * @return array The sanitized data.
     */
    function wpsr_sanitize_style_data($data) {
        if (!is_array($data)) {
            return sanitize_text_field($data);
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Responsive values, e.g. 'font_size' => ['desktop' => '16px', 'mobile' => '14px'].
                $sanitized[$key] = wpsr_sanitize_style_data($value);
            } else {
                if (strpos($key, 'color') !== false) {
                    $sanitized[$key] = wpsr_sanitize_color($value);
                } elseif (in_array($key, ['font_size', 'letter_spacing', 'line_height', 'top', 'right', 'bottom', 'left', 'width', 'height', 'max_width', 'spaceBetween', 'horizontal', 'vertical', 'blur', 'spread'])) {
                    $sanitized[$key] = wpsr_sanitize_css_unit($value);
                } elseif (in_array($key, ['font_weight', 'font_style', 'text_transform', 'text_decoration', 'border_style', 'linked', 'box_shadow_style', 'inset'])) {
                    $sanitized[$key] = wpsr_sanitize_css_keyword($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }
        return $sanitized;
    }
}

if (!function_exists('wpsr_trim_url')) {
    /**
     * Trim a URL the way JavaScript's String.prototype.trim() does.
     * The character set is mirrored by WPSR_TRIM_SET in utils/videoUrl.js.
     *
     * @param mixed $value
     *
     * @return string
     */
    function wpsr_trim_url($value)
    {
        if (!is_string($value)) {
            return '';
        }

        $set = '[\s\x{00A0}\x{FEFF}\x{2000}-\x{200D}\x{2028}\x{2029}\x{3000}]+';
        $trimmed = preg_replace('/^' . $set . '|' . $set . '$/u', '', $value);

        return $trimmed === null ? trim($value) : $trimmed;
    }
}

if (!function_exists('wpsr_is_http_url')) {
    /**
     * One http(s) URL rule, shared by the form, the REST validator and every renderer.
     *
     * @param mixed $value
     *
     * @return bool
     */
    function wpsr_is_http_url($value)
    {
        return wpsr_sanitize_http_url($value) !== '';
    }
}

if (!function_exists('wpsr_sanitize_http_url')) {
    /**
     * Validate one http(s) URL, host included. Checked before esc_url_raw(), which
     * turns a bare word into a URL. IDN hosts must be punycode; non-ASCII paths are fine.
     *
     * @param mixed $value
     *
     * @return string the normalized URL, or '' when it is not a usable http(s) URL
     */
    function wpsr_sanitize_http_url($value)
    {
        $raw = wpsr_trim_url($value);

        if (!preg_match('#^https?://#i', $raw)) {
            return '';
        }

        $raw_host = wp_parse_url($raw, PHP_URL_HOST);
        if (!is_string($raw_host) || $raw_host === '') {
            return '';
        }

        if (preg_match('/[^\x21-\x7E]/', $raw_host)) {
            return '';
        }

        $url = esc_url_raw($raw);
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        return preg_match('/[\p{L}\p{N}]/u', $host) ? $url : '';
    }
}

if (!function_exists('wpsr_sanitize_media_url')) {
    /**
     * Gate for a URL the server will fetch later. wp_http_validate_url() decides
     * scheme, port, credentials and private/reserved hosts; only the shape check is ours.
     *
     * @param mixed $value
     *
     * @return string the normalized URL, or '' when it is not safe to fetch
     */
    function wpsr_sanitize_media_url($value)
    {
        $url = wpsr_sanitize_http_url($value);
        if ($url === '') {
            return '';
        }

        return wp_http_validate_url($url) ? $url : '';
    }
}

if (!function_exists('wpsr_is_media_url')) {
    /**
     * @param mixed $value
     *
     * @return bool
     */
    function wpsr_is_media_url($value)
    {
        return wpsr_sanitize_media_url($value) !== '';
    }
}
