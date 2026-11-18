<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

require_once __DIR__ . '/includes/class-gf-paystack-keys.php';
require_once __DIR__ . '/includes/class-gf-paystack-restricted-key.php';

class GFPaystackApi
{
    public $plugin_name;
    
    protected $public_key;
    
    protected $secret_key;

    protected $webhook_secret;

    protected $stored_secret_key;

    /** @var array|null Path (`whsec`|`legacy`|`none`) and outcome of the last validate_webhook() call. */
    public $last_webhook_check = null;

    public function __construct($config)
    {
        $this->plugin_name = 'Paystack Addon for Gravity Forms';
        $this->secret_key = $config->secret_key ?? '';
        $this->public_key = $config->public_key ?? '';
        $this->webhook_secret = $config->webhook_secret ?? '';
        $this->stored_secret_key = $config->stored_secret_key ?? '';
    }

    /**
     * Track Payment Transactions from this Plugin
     *
     * @param string $trx_ref
     * @return void
     */
    public function log_transaction_success($reference)
    {
        // Send reference to logger along with plugin name and public key
        // $params = [
        //     'plugin_name'  => $this->plugin_name,
        //     'public_key' => $this->public_key,
        //     'transaction_reference' => $reference
        // ];

        $params = [
            'plugin_name'  => 'pstk-gravityforms',
            'public_key' => $this->public_key,
            'transaction_reference' => $reference
        ];

        // Usage tracking must never block a paid customer from reaching the confirmation page.
        try {
            $this->send_request(
                'log/charge_success',
                $params,
                'post',
                'https://plugin-tracker.paystackintegrations.com/'
            );
        } catch (\Throwable $e) {
            error_log(GF_Paystack_Keys::redact(__METHOD__ . '(): plugin tracker request failed: ' . $e->getMessage()));
        }
    }

    /**
     * Send request to the Paystack Api
     * 
     * @param string $endpoint API request path
     * @param array $args API request arguments
     * @param string $method API request method
     * @param string $domain API request uri
     * 
     * @return object|null JSON decoded transaction object. NULL on API error.
     */
    public function send_request(
        $endpoint,
        $args = array(),
        $method = 'post',
        $domain = 'https://api.paystack.co/'
    ) {
        $uri = "{$domain}{$endpoint}";

        $arg_array = array(
            'method'    => strtoupper($method),
            'body'      => $args,
            'timeout'   => 15,
            'headers'   => $domain === 'https://api.paystack.co/' ? $this->get_headers() : array()
        );

        $res = wp_remote_request($uri, $arg_array);

        if (is_wp_error($res)) {
            throw new Exception(sprintf(__('You had an HTTP error connecting to %s', 'gravityformspaystack'), $this->plugin_name));
        }

        $body = json_decode($res['body'], true);

        if (is_array($body)) {
            error_log(GF_Paystack_Keys::redact(__METHOD__ . '(): ' . strtoupper($method) . ' ' . $uri . ' status: ' . (isset($body['status']) ? var_export($body['status'], true) : 'unknown')));

            $code = isset($res['response']['code']) ? (int) $res['response']['code'] : 0;
            if ($code === 403 && isset($body['message']) && $body['message'] === GF_Paystack_Restricted_Key::SCOPE_DENIED_MESSAGE) {
                error_log(GF_Paystack_Keys::redact('rk scope denied method=' . strtoupper($method) . ' path=' . parse_url($uri, PHP_URL_PATH)));
            }

            if (isset($body['error']) || $body['status'] == false) {
                throw new Exception("{$body['message']}");
            } else {
                return $body;
            }
        } else { // Un-decipherable message
            throw new Exception(sprintf(__('There was an issue connecting with the payment processor. Try again later.', 'gravityformspaystack'), $this->plugin_name));
        }

        return false;
    }

    /**
     * @param string $reference
     *
     * @return object|null
     */
    public function verify_transaction($reference)
    {
        return $this->send_request('transaction/verify/' . rawurlencode($reference), array(), 'get');
    }

    /**
     * Validate Webhook Signature
     *
     * A configured signing secret decides alone; the legacy sk HMAC is only used when none is set.
     *
     * @param string      $input          The raw body that will be decoded.
     * @param string|null $signature      Legacy X-Paystack-Signature; defaults to the request's header.
     * @param string|null $signing_header Paystack-Signature; defaults to the request's header.
     * @return boolean
     */
    public function validate_webhook($input, $signature = null, $signing_header = null)
    {
        if (is_string($this->webhook_secret) && trim($this->webhook_secret) !== '') {
            if ($signing_header === null) {
                $signing_header = isset($_SERVER['HTTP_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_PAYSTACK_SIGNATURE'] : '';
            }

            $outcome = $this->check_signing_secret_header($input, $signing_header);
            $this->last_webhook_check = array('sig' => 'whsec', 'result' => $outcome);

            return $outcome === 'valid';
        }

        if (GF_Paystack_Keys::type($this->stored_secret_key) === 'sk') {
            if ($signature === null) {
                $signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';
            }

            $is_valid = is_string($signature) && $signature !== ''
                && hash_equals(hash_hmac('sha512', (string) $input, $this->stored_secret_key), $signature);
            $this->last_webhook_check = array('sig' => 'legacy', 'result' => $is_valid ? 'valid' : 'invalid');

            return $is_valid;
        }

        $this->last_webhook_check = array('sig' => 'none', 'result' => 'invalid');

        return false;
    }

    /**
     * Verify a `Paystack-Signature: t=<unix>,v1=<kid>:<hex>[,v1=...]` header.
     *
     * @param string   $input  The raw body.
     * @param mixed    $header The header value.
     * @param int|null $now    Current unix time; defaults to time().
     * @return string valid|stale|invalid|malformed
     */
    public function check_signing_secret_header($input, $header, $now = null)
    {
        if (!is_string($this->webhook_secret) || trim($this->webhook_secret) === '' || !is_string($header)) {
            return 'malformed';
        }

        $timestamps = array();
        $signatures = array();
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2) {
                continue;
            }

            $name = trim($pair[0]);
            $value = trim($pair[1]);
            if ($name === 't') {
                $timestamps[] = $value;
            } elseif ($name === 'v1' && count($signatures) < 10) {
                $colon = strrpos($value, ':');
                $hex = $colon === false ? '' : substr($value, $colon + 1);
                if ($colon > 0 && preg_match('/^[0-9a-fA-F]{128}$/', $hex)) {
                    $signatures[] = strtolower($hex);
                }
            }
        }

        if (count($timestamps) !== 1 || !ctype_digit($timestamps[0]) || empty($signatures)) {
            return 'malformed';
        }

        $timestamp = $timestamps[0];
        $expected = hash_hmac('sha512', $timestamp . '.' . (string) $input, $this->webhook_secret);

        $matched = false;
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                $matched = true;
            }
        }

        if (!$matched) {
            return 'invalid';
        }

        $now = $now === null ? time() : (int) $now;

        return abs($now - (int) $timestamp) <= 300 ? 'valid' : 'stale';
    }

    /**
     * Generates the headers to pass to API request.
     */
    public function get_headers()
    {
        return apply_filters('gf_paystack_request_headers', [
            'Authorization' => "Bearer {$this->secret_key}"
        ]);
    }
}
