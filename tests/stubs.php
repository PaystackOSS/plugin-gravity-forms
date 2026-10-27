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
        public static function get_entry_meta_table_name()
        {
            return 'wp_gf_entry_meta';
        }

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

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $code;
        public $message;

        public function __construct($code = '', $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

if (!function_exists('rgempty')) {
    function rgempty($name, $array = null)
    {
        $value = rgar($array === null ? $_POST : $array, $name);
        return empty($value);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('gform_update_meta')) {
    function gform_update_meta($entry_id, $key, $value)
    {
        $GLOBALS['gf_paystack_test_meta'][$entry_id][$key] = $value;
    }
}

if (!class_exists('GFAPI')) {
    class GFAPI
    {
        public static function get_entry($id)
        {
            $entries = $GLOBALS['gf_paystack_test_entries'];
            return isset($entries[$id]) ? $entries[$id] : new WP_Error('not_found', 'not found');
        }

        public static function get_form($id)
        {
            return array('id' => $id);
        }

        public static function update_entry_property($id, $property, $value)
        {
            return true;
        }
    }
}

$GLOBALS['gf_paystack_test_refs'] = array();

$GLOBALS['wpdb'] = new class {
    public function prepare($sql, $reference)
    {
        return $reference;
    }

    public function get_row($reference)
    {
        $map = $GLOBALS['gf_paystack_test_refs'];
        $match = isset($map[$reference]) ? $reference : null;

        // The meta_value column is matched case-insensitively.
        foreach ($map as $key => $id) {
            if ($match === null && strcasecmp($key, $reference) === 0) {
                $match = $key;
            }
        }

        if ($match === null) {
            return null;
        }

        $row = new stdClass();
        $row->entry_id = $map[$match];
        $row->meta_value = gform_get_meta($map[$match], 'paystack_tx_reference');
        return $row;
    }
};

class GFPaystackRecordingApi
{
    public $calls = array();
    public $response;
    public $throw = false;
    public $logged = array();

    public function log_transaction_success($reference)
    {
        $this->logged[] = $reference;
    }

    public function send_request($path, $args = array(), $method = 'post')
    {
        $this->calls[] = $path;
        if ($this->throw) {
            throw new \Exception('request failed');
        }
        return $this->response;
    }

    public function verify_transaction($reference)
    {
        return $this->send_request('transaction/verify/' . rawurlencode($reference), array(), 'get');
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '')
    {
        return 'https://example.test';
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($key, $value, $url)
    {
        return $url . '?' . $key . '=' . rawurlencode($value);
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url()
    {
        return 'https://example.test';
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return $url;
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($uri, $args)
    {
        $GLOBALS['gf_paystack_test_remote_calls'][] = array($uri, $args);
        return $GLOBALS['gf_paystack_test_remote_response'];
    }
}
