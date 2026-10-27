<?php

require_once dirname(__DIR__) . '/class-gf-paystack-api.php';
require_once dirname(__DIR__) . '/class-gf-paystack.php';

class GFPaystackCallbackFixture extends GFPaystack
{
    public $fixture_api;
    public $fixture_event;
    public $fixture_mode;
    public $fixture_feed;
    public $fixture_subscription;

    public function is_gravityforms_supported()
    {
        return true;
    }

    public function get_webhook_event($body = null)
    {
        $this->webhook_mode = $this->fixture_mode;
        $this->paystack_api = $this->fixture_api;
        return $this->fixture_event;
    }

    public function get_payment_feed($entry, $form = false)
    {
        return $this->fixture_feed;
    }

    public function paystack_api($mode = null, $settings = null)
    {
        return $this->paystack_api = $this->fixture_api;
    }

    public function get_feed($feed_id)
    {
        return $this->fixture_feed;
    }

    public function get_subscription($code)
    {
        return $this->fixture_subscription;
    }

    public function get_entry_by_transaction_id($transaction_id)
    {
        $map = isset($GLOBALS['gf_paystack_test_tx']) ? $GLOBALS['gf_paystack_test_tx'] : array();
        return isset($map[$transaction_id]) ? $map[$transaction_id] : false;
    }

    public function get_amount_import($amount, $currency)
    {
        return $amount / 100;
    }

    public function get_plugin_settings()
    {
        return array('api_mode' => 'live');
    }
}

function gf_paystack_callback_run($event, $mode, $meta = array(), $feed = null, $subscription = null, $api = null)
{
    $GLOBALS['gf_paystack_test_entries'] = array(
        '7' => array('id' => '7', 'form_id' => '1', 'is_fulfilled' => false, 'currency' => 'NGN', 'payment_status' => 'Processing'),
    );
    $GLOBALS['gf_paystack_test_refs'] = array('gf-7-abc' => '7');
    $GLOBALS['gf_paystack_test_tx'] = array('SUB_1' => '7');
    $GLOBALS['gf_paystack_test_meta'] = array('7' => array_merge(array('paystack_tx_reference' => 'gf-7-abc'), $meta));

    $addon = new GFPaystackCallbackFixture();
    $addon->fixture_event = $event;
    $addon->fixture_mode = $mode;
    $addon->fixture_feed = $feed === null ? array('is_active' => '1', 'meta' => array('mode' => 'live', 'transactionType' => 'product')) : $feed;
    $addon->fixture_subscription = $subscription;
    $addon->fixture_api = $api;

    return $addon->callback();
}

function gf_paystack_charge_event($overrides = array())
{
    $data = array_merge(array('id' => 99, 'reference' => 'gf-7-abc', 'amount' => 5000, 'currency' => 'NGN', 'domain' => 'live', 'metadata' => array('entry_id' => '7')), $overrides);
    return array('event' => 'charge.success', 'data' => $data);
}

class GFPaystackGatewayFixture extends GFPaystackCallbackFixture
{
    public $modes_requested = array();
    public $settings = array('api_mode' => 'live');

    public function paystack_api($mode = null, $settings = null)
    {
        $this->modes_requested[] = $mode;
        return $this->paystack_api = $this->fixture_api;
    }

    public function get_plugin_settings()
    {
        return $this->settings;
    }

    public function get_customer_fields()
    {
        return array();
    }

    public function get_fields_meta_data($feed, $entry, $fields)
    {
        return array('email' => 'buyer@example.test');
    }

    public function get_paystack_meta_data($feed, $entry, $form)
    {
        return array();
    }

    public function get_amount_export($amount, $currency)
    {
        return (int) round($amount * 100);
    }
}

class GFPaystackEventFixture extends GFPaystack
{
    public $settings = array();

    public function get_plugin_settings()
    {
        return $this->settings;
    }

    public function webhook_mode_value()
    {
        return $this->webhook_mode;
    }
}

function gf_paystack_return_query($reference, $query = null)
{
    $ids = '7|5|1';
    if ($query === null) {
        $query = array('ids' => $ids, 'hash' => wp_hash('ids=' . $ids));
    }

    return array('gf_paystack_return' => base64_encode(http_build_query($query)), 'reference' => $reference);
}

function gf_paystack_thankyou_addon($api, $feed = null)
{
    $addon = new GFPaystackGatewayFixture();
    $addon->fixture_api = $api;
    $addon->fixture_feed = $feed;

    return $addon;
}

function gf_paystack_thankyou_run($addon, $get, $meta = null)
{
    $_GET = $get;
    $GLOBALS['gf_paystack_test_entries'] = array('7' => array('id' => '7', 'form_id' => '1', 'status' => 'active'));
    if ($meta !== null) {
        $GLOBALS['gf_paystack_test_meta'] = array('7' => $meta);
    }

    return $addon->maybe_thankyou_page();
}

function gf_paystack_exception_message($callback)
{
    try {
        $callback();
    } catch (\Exception $e) {
        return $e->getMessage();
    }

    return '';
}
