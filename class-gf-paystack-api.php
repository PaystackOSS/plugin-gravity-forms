<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

class GFPaystackApi
{
    public $plugin_name;
    
    protected $public_key;
    
    protected $secret_key;

    public function __construct($config)
    {
        $this->plugin_name = 'Paystack Addon for Gravity Forms';
        $this->secret_key = $config->secret_key ?? '';
        $this->public_key = $config->public_key ?? '';
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

        $this->send_request(
            'log/charge_success',
            $params,
            'post',
            'https://plugin-tracker.paystackintegrations.com/'
        );
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

        if ($body !== null) {
            error_log(__METHOD__ . '(): ' . strtoupper($method) . ' ' . $uri . ' status: ' . (isset($body['status']) ? var_export($body['status'], true) : 'unknown'));

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
     * @param $input
     * @param string|null $signature Defaults to the request's signature header.
     * @return boolean
     */
    public function validate_webhook($input, $signature = null)
    {
        if ($signature === null) {
            $signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';
        }

        if (!is_string($signature) || $signature === '' || !is_string($this->secret_key) || trim($this->secret_key) === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', (string) $input, $this->secret_key), $signature);
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
