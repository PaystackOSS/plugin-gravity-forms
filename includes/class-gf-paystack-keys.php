<?php

defined('ABSPATH') || die();

if (!class_exists('GF_Paystack_Keys')) {
    final class GF_Paystack_Keys
    {
        const SK_SHAPE = '/^sk_(?:test|live)_[A-Za-z0-9]+$/';
        const RK_SHAPE = '/^rk_(?:test|live)_[\x21\x23-\x26\x28-\x3b\x3d\x3f-\x5b\x5d-\x7e]{16,256}$/';
        const WHSEC_SHAPE = '/^whsec_[A-Za-z0-9_]{16,}$/';
        // A prefix glued to a word (`task_live_`) isn't a key, but one after a %XX escape (`secret%3Dsk_live_`) is.
        const REDACT_PATTERN = '/(?<!(?<!%[0-9A-Fa-f])[A-Za-z0-9])((?:sk|rk|pk)_(?:live|test)_|whsec_)[^\s"\'<>]+/';

        public static function type($key)
        {
            return self::parse($key)[0];
        }

        public static function mode($key)
        {
            return self::parse($key)[1];
        }

        private static function parse($key)
        {
            if (is_string($key) && preg_match('/^(sk|rk)_(live|test)_/', $key, $m)) {
                return array($m[1], $m[2]);
            }

            return array(null, null);
        }

        public static function is_whsec_shaped($value)
        {
            return is_string($value) && preg_match(self::WHSEC_SHAPE, $value) === 1;
        }

        public static function restricted_keys_enabled()
        {
            return (bool) apply_filters('gform_paystack_restricted_keys_enabled', true);
        }

        public static function resolve_save($mode, $posted_key, $stored_key, $posted_whsec, $other_mode_whsec, $rk_enabled)
        {
            $key = trim((string) $posted_key);
            $stored = trim((string) $stored_key);
            $whsec = trim((string) $posted_whsec);
            $other_whsec = trim((string) $other_mode_whsec);

            $probe = false;
            if ($key !== '' && $key !== $stored) {
                $key_mode = self::mode($key);
                if ($key_mode !== null && $key_mode !== $mode) {
                    return self::result(false, 'wrong_field');
                }

                $type = self::type($key);
                $shaped = $type === 'sk' ? preg_match(self::SK_SHAPE, $key) : ($type === 'rk' ? preg_match(self::RK_SHAPE, $key) : 0);
                if (!$shaped) {
                    return self::result(false, 'invalid_format');
                }

                if ($type === 'rk') {
                    if (!$rk_enabled) {
                        return self::result(false, 'rk_disabled');
                    }
                    $probe = true;
                }
            }

            // A blank key is a cleared field, so it never needs a signing secret.
            if ($whsec !== '' && !self::is_whsec_shaped($whsec)) {
                return self::result($probe, 'whsec_format');
            }
            if ($key !== '' && self::type($key) === 'rk' && $whsec === '') {
                return self::result($probe, 'whsec_required');
            }
            if ($whsec !== '' && $whsec === $other_whsec) {
                return self::result($probe, 'whsec_same');
            }

            return self::result($probe, null);
        }

        public static function redact($string)
        {
            if (!is_string($string)) {
                return $string;
            }

            if (strpos($string, '_live_') === false && strpos($string, '_test_') === false && strpos($string, 'whsec_') === false) {
                return $string;
            }

            return preg_replace_callback(self::REDACT_PATTERN, array(__CLASS__, 'redact_match'), $string);
        }

        private static function redact_match($match)
        {
            return $match[1] . '[redacted]';
        }

        private static function result($probe, $error)
        {
            return array('probe' => $error === null ? $probe : false, 'error' => $error);
        }
    }
}
