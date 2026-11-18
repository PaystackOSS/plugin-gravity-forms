<?php

defined('ABSPATH') || die();

require_once __DIR__ . '/class-gf-paystack-keys.php';

if (!class_exists('GF_Paystack_Restricted_Key')) {
    class GF_Paystack_Restricted_Key
    {
        const BASE_URL = 'https://api.paystack.co/';
        const SCOPE_DENIED_MESSAGE = 'This restricted key is not permitted to perform this action';
        const WRITE_PRESENT_MESSAGE = 'Sorry, this feature is no longer available.';
        const MOR_MESSAGE = 'This feature is not available to merchant of record integrations.';
        const REQUEST_TIMEOUT = 4;

        // Sync with pstk-bigcommerce/utils/restrictedKey.js (minus account:read); a drifted route makes saves fail closed.
        // Order matters: offending scopes are reported in it, and the baseline must stay first. Never add a write-capable route.
        // present: '2xx' | '2xx_or_400' | '404_write'
        const PROBES = array(
            array('scope' => 'transactions:read', 'type' => 'required', 'method' => 'GET', 'path' => 'transaction?perPage=1', 'body' => null, 'present' => '2xx'),
            array('scope' => 'transactions:write', 'type' => 'required', 'method' => 'POST', 'path' => 'transaction/check_authorization', 'body' => array(), 'present' => '404_write'),
            array('scope' => 'disputes_refunds', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'refund?perPage=1', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'bulk_charges', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'bulkcharge', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'direct_debit', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'directdebit/account', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'preauthorizations', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'preauthorization', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'settlements', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'settlement?perPage=1', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'subaccounts', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'subaccount', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'transfers', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'transfer/config', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'virtual_accounts', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'pay-with-transfer/custom-accounts/status', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'webhooks', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'integration/webhooks/events', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'connect', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'connect/platforms', 'body' => null, 'present' => '2xx_or_400'),
            array('scope' => 'payouts', 'type' => 'sensitive', 'method' => 'GET', 'path' => 'settlement/instant/profile', 'body' => null, 'present' => '2xx_or_400'),
        );

        // Overridable so tests can exhaust it without sleeping.
        protected static $budget_seconds = 15.0;

        public static function validate($key, $request = null)
        {
            try {
                if (!is_callable($request)) {
                    $request = array(__CLASS__, 'default_request');
                }

                $start = microtime(true);
                $results = array();
                $baseline = null;

                foreach (self::PROBES as $index => $probe) {
                    if (microtime(true) - $start >= static::$budget_seconds) {
                        $results[] = array('probe' => $probe, 'outcome' => 'indeterminate', 'status' => 0, 'ran' => false);
                        break;
                    }

                    $response = self::send($request, $key, $probe['method'], $probe['path'], $probe['body']);
                    $outcome = self::classify($probe, $response, $baseline);
                    $results[] = array('probe' => $probe, 'outcome' => $outcome, 'status' => $response['status'], 'ran' => true);

                    if ($index === 0) {
                        $baseline = $outcome;
                    }
                    if ($outcome === 'invalid' || $outcome === 'indeterminate') {
                        break;
                    }
                }

                return self::decide($results);
            } catch (\Throwable $e) {
                return self::verdict('unverifiable', array(), array(), 'transient');
            }
        }

        public static function probe_plans($key, $request = null)
        {
            try {
                if (!is_callable($request)) {
                    $request = array(__CLASS__, 'default_request');
                }

                $response = self::send($request, $key, 'POST', 'plan', array());
                if ($response['status'] === 400) {
                    return 'ok';
                }
                if ($response['status'] === 403) {
                    if ($response['message'] === self::SCOPE_DENIED_MESSAGE) {
                        return 'plans_missing';
                    }
                    if ($response['message'] === self::MOR_MESSAGE) {
                        return 'plans_mor';
                    }
                }

                return 'unverifiable';
            } catch (\Throwable $e) {
                return 'unverifiable';
            }
        }

        public static function default_request($method, $url, $headers, $body)
        {
            $res = wp_remote_request($url, array(
                'method' => $method,
                'timeout' => self::REQUEST_TIMEOUT,
                'redirection' => 0,
                'headers' => $headers,
                'body' => $body === null ? null : json_encode($body, JSON_FORCE_OBJECT),
            ));

            if (is_wp_error($res) || !is_array($res)) {
                return array('status' => 0, 'body' => null);
            }

            $status = isset($res['response']['code']) ? (int) $res['response']['code'] : 0;
            $raw = isset($res['body']) ? $res['body'] : null;

            return array('status' => $status, 'body' => $raw);
        }

        private static function send($request, $key, $method, $path, $body)
        {
            // Authorization is forced last so a filter can't swap or drop the key under test.
            $headers = apply_filters('gf_paystack_request_headers', array('Content-Type' => 'application/json'));
            if (!is_array($headers)) {
                $headers = array();
            }
            $headers['Authorization'] = 'Bearer ' . $key;

            try {
                $response = call_user_func($request, $method, self::BASE_URL . $path, $headers, $body);
            } catch (\Throwable $e) {
                $response = null;
            }

            if (!is_array($response) || !isset($response['status'])) {
                return array('status' => 0, 'message' => null);
            }

            return array('status' => (int) $response['status'], 'message' => self::message_of(isset($response['body']) ? $response['body'] : null));
        }

        private static function message_of($body)
        {
            if (is_string($body)) {
                $decoded = json_decode($body, true);
                $body = is_array($decoded) ? $decoded : $body;
            }
            if (is_array($body)) {
                return isset($body['message']) && is_string($body['message']) ? $body['message'] : null;
            }

            return is_string($body) ? $body : null;
        }

        private static function is_present($probe, $response)
        {
            $status = $response['status'];
            if ($probe['present'] === '404_write') {
                return $status === 404 && $response['message'] === self::WRITE_PRESENT_MESSAGE;
            }
            if ($status >= 200 && $status <= 299) {
                return true;
            }

            return $probe['present'] === '2xx_or_400' && $status === 400;
        }

        // Any 403 that isn't the exact scope message may be another gate (IP, feature), so it fails closed.
        private static function classify($probe, $response, $baseline)
        {
            if ($response['status'] === 401) {
                if ($baseline === null) {
                    return 'invalid';
                }

                return $baseline === 'present' && $probe['type'] === 'sensitive' ? 'present' : 'indeterminate';
            }
            if ($response['status'] === 403 && $response['message'] === self::SCOPE_DENIED_MESSAGE) {
                return 'absent';
            }

            return self::is_present($probe, $response) ? 'present' : 'indeterminate';
        }

        private static function decide($results)
        {
            if ($results[0]['outcome'] === 'invalid') {
                return self::verdict('invalid_key', array(), array(), null, $results);
            }

            $missing = array();
            $sensitive = array();
            $indeterminate = array();
            foreach ($results as $r) {
                $type = $r['probe']['type'];
                if ($type === 'required' && $r['outcome'] === 'absent') {
                    $missing[] = $r['probe']['scope'];
                } elseif ($type === 'sensitive' && $r['outcome'] === 'present') {
                    $sensitive[] = $r['probe']['scope'];
                } elseif ($r['outcome'] === 'indeterminate') {
                    $indeterminate[] = $r;
                }
            }

            if ($missing) {
                return self::verdict('missing_scope', $missing, $sensitive, null, $results);
            }
            if ($sensitive) {
                return self::verdict('sensitive_scope', array(), $sensitive, null, $results);
            }
            if ($indeterminate) {
                $reason = 'transient';
                foreach ($indeterminate as $r) {
                    if ($r['status'] === 403) {
                        $reason = 'blocked';
                    }
                }

                return self::verdict('unverifiable', array(), array(), $reason, $results);
            }

            return self::verdict('ok', array(), array(), null, $results);
        }

        private static function verdict($code, $scopes, $sensitive_scopes, $reason, $results = array())
        {
            $parts = array();
            foreach ($results as $r) {
                if ($r['ran']) {
                    $parts[] = $r['probe']['scope'] . ':' . $r['status'];
                }
            }

            return array(
                'code' => $code,
                'scopes' => $scopes,
                'sensitive_scopes' => $sensitive_scopes,
                'reason' => $reason,
                'probes' => $parts ? implode(',', $parts) : '-',
            );
        }
    }
}
