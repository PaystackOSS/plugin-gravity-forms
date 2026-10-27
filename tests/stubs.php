<?php

if (!function_exists('add_action')) {
    function add_action()
    {
    }
}

if (!function_exists('add_filter')) {
    function add_filter()
    {
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '')
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = '')
    {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return $text;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability)
    {
        global $gf_paystack_test_caps;
        return is_array($gf_paystack_test_caps) && in_array($capability, $gf_paystack_test_caps, true);
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite()
    {
        global $gf_paystack_test_multisite;
        return !empty($gf_paystack_test_multisite);
    }
}

if (!function_exists('get_current_screen')) {
    function get_current_screen()
    {
        global $gf_paystack_test_screen;
        if ($gf_paystack_test_screen === null) {
            return null;
        }
        $screen = new stdClass();
        $screen->id = $gf_paystack_test_screen;
        return $screen;
    }
}

if (!function_exists('rgar')) {
    function rgar($array, $name, $default = '')
    {
        if (is_array($array) && isset($array[$name])) {
            return $array[$name];
        }
        return $default;
    }
}

if (!function_exists('rgars')) {
    function rgars($array, $name, $default = '')
    {
        $current = $array;
        foreach (explode('/', $name) as $part) {
            if (!is_array($current) || !isset($current[$part])) {
                return $default;
            }
            $current = $current[$part];
        }
        return $current;
    }
}

if (!function_exists('rgget')) {
    function rgget($name, $array = null, $default = '')
    {
        if ($array === null) {
            $array = $_GET;
        }
        return rgar($array, $name, $default);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text)
    {
        return trim(strip_tags($text));
    }
}

if (!function_exists('gform_get_meta')) {
    function gform_get_meta($entry_id, $key)
    {
        global $gf_paystack_test_meta;
        return isset($gf_paystack_test_meta[$entry_id][$key]) ? $gf_paystack_test_meta[$entry_id][$key] : false;
    }
}

if (!function_exists('wp_hash')) {
    function wp_hash($data)
    {
        return hash_hmac('md5', $data, 'gf-paystack-test-salt');
    }
}

if (!class_exists('GFForms')) {
    class GFForms
    {
        public static function include_payment_addon_framework()
        {
        }
    }
}

if (!class_exists('GFAddOn')) {
    class GFAddOn
    {
    }
}

if (!class_exists('GFPaymentAddOn')) {
    class GFPaymentAddOn extends GFAddOn
    {
        public function log_debug($message)
        {
        }

        public function log_error($message)
        {
        }

        public function get_plugin_settings()
        {
            return array();
        }

        public function get_setting($setting_name, $default_value = '', $settings = null)
        {
            return rgar($settings, $setting_name, $default_value);
        }
    }
}
