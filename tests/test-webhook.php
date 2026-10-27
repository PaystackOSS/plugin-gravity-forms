<?php

$webhook_body = '{"event":"charge.success"}';
$webhook_api = new GFPaystackApi((object) array('secret_key' => 'sk_test_secret', 'public_key' => 'pk'));
$webhook_sig = hash_hmac('sha512', $webhook_body, 'sk_test_secret');

unset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']);

gf_paystack_test_check($webhook_api->validate_webhook($webhook_body, $webhook_sig) === true, 'validate_webhook case 1');
gf_paystack_test_check($webhook_api->validate_webhook($webhook_body, 'deadbeef') === false, 'validate_webhook case 2');
gf_paystack_test_check($webhook_api->validate_webhook($webhook_body) === false, 'validate_webhook case 3');
gf_paystack_test_check($webhook_api->validate_webhook($webhook_body, '') === false, 'validate_webhook case 4');
gf_paystack_test_check($webhook_api->validate_webhook($webhook_body, array($webhook_sig)) === false, 'validate_webhook case 5');

$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = $webhook_sig;
gf_paystack_test_check($webhook_api->validate_webhook($webhook_body) === true, 'validate_webhook case 6');
unset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']);

$empty_secret_api = new GFPaystackApi((object) array('secret_key' => '', 'public_key' => 'pk'));
gf_paystack_test_check($empty_secret_api->validate_webhook($webhook_body, hash_hmac('sha512', $webhook_body, '')) === false, 'validate_webhook case 7');

$blank_secret_api = new GFPaystackApi((object) array('secret_key' => '  ', 'public_key' => 'pk'));
gf_paystack_test_check($blank_secret_api->validate_webhook($webhook_body, hash_hmac('sha512', $webhook_body, '  ')) === false, 'validate_webhook case 8');

gf_paystack_test_check(GFPaystack::resolve_webhook_mode('live') === 'live', 'resolve_webhook_mode case 1');
gf_paystack_test_check(GFPaystack::resolve_webhook_mode('test') === 'test', 'resolve_webhook_mode case 2');
gf_paystack_test_check(GFPaystack::resolve_webhook_mode('LIVE') === null, 'resolve_webhook_mode case 3');
gf_paystack_test_check(GFPaystack::resolve_webhook_mode('foo') === null, 'resolve_webhook_mode case 4');
gf_paystack_test_check(GFPaystack::resolve_webhook_mode('') === null, 'resolve_webhook_mode case 5');
gf_paystack_test_check(GFPaystack::resolve_webhook_mode(null) === null, 'resolve_webhook_mode case 6');
gf_paystack_test_check(GFPaystack::resolve_webhook_mode(array('live')) === null, 'resolve_webhook_mode case 7');

gf_paystack_test_check(GFPaystack::modes_match('live', 'live') === true, 'modes_match case 1');
gf_paystack_test_check(GFPaystack::modes_match('test', 'live') === false, 'modes_match case 2');
gf_paystack_test_check(GFPaystack::modes_match('', '') === false, 'modes_match case 3');
gf_paystack_test_check(GFPaystack::modes_match(null, 'live') === false, 'modes_match case 4');

gf_paystack_test_check(GFPaystack::reference_matches_entry('ref_1', 'ref_1') === true, 'reference_matches_entry case 1');
gf_paystack_test_check(GFPaystack::reference_matches_entry('ref_1', 'ref_2') === false, 'reference_matches_entry case 2');
gf_paystack_test_check(GFPaystack::reference_matches_entry('', '') === false, 'reference_matches_entry case 3');
gf_paystack_test_check(GFPaystack::reference_matches_entry((string) false, 'ref_1') === false, 'reference_matches_entry case 4');
gf_paystack_test_check(GFPaystack::reference_matches_entry((string) null, '') === false, 'reference_matches_entry case 5');
gf_paystack_test_check(GFPaystack::reference_matches_entry('ref_1', array('ref_1')) === false, 'reference_matches_entry case 6');

$stored = array('paystack_mode' => 'live', 'paystack_tx_amount' => '5000', 'paystack_tx_currency' => 'NGN');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored);
gf_paystack_test_check(is_array($result) && $result['type'] === 'complete_payment' && $result['entry_id'] === '7', 'callback case 1');

$result = gf_paystack_callback_run(gf_paystack_charge_event(array('metadata' => array('entry_id' => '8'))), 'live', $stored);
gf_paystack_test_check($result === false, 'callback case 2');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'test', $stored);
gf_paystack_test_check($result === false, 'callback case 3');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', array('paystack_tx_amount' => '5000'));
gf_paystack_test_check(is_array($result) && $result['type'] === 'complete_payment', 'callback case 4');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'test', array('paystack_tx_amount' => '5000'));
gf_paystack_test_check($result === false, 'callback case 5');

$result = gf_paystack_callback_run(gf_paystack_charge_event(array('amount' => 100)), 'live', $stored);
gf_paystack_test_check($result === false, 'callback case 6');

$result = gf_paystack_callback_run(gf_paystack_charge_event(array('currency' => 'USD')), 'live', $stored);
gf_paystack_test_check($result === false, 'callback case 7');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', array());
gf_paystack_test_check(is_array($result) && $result['type'] === 'complete_payment', 'callback case 8');

$result = gf_paystack_callback_run(gf_paystack_charge_event(array('reference' => 'gf-7-unknown')), 'live', $stored);
gf_paystack_test_check(is_wp_error($result) && $result->code === 'entry_not_found', 'callback case 9');

$result = gf_paystack_callback_run(gf_paystack_charge_event(array('reference' => 'GF-7-ABC')), 'live', $stored);
gf_paystack_test_check(is_wp_error($result), 'callback case 10');

$disable_event = array('event' => 'subscription.disable', 'data' => array('subscription_code' => 'SUB_1'));
$subscription = array('data' => array('invoices' => array(), 'invoice_limit' => 0, 'customer' => array('customer_code' => 'CUS_1')));

$result = gf_paystack_callback_run($disable_event, 'live', $stored, false, $subscription);
gf_paystack_test_check(is_array($result) && $result['type'] === 'cancel_subscription', 'callback case 11');

$result = gf_paystack_callback_run($disable_event, 'test', $stored, false, $subscription);
gf_paystack_test_check($result === false, 'callback case 12');

$sub_invoice = array('id' => 55, 'reference' => 'inv-ref-1', 'status' => 'success', 'paid' => true, 'amount' => 7500);
$sub_data = array('data' => array('subscription_code' => 'SUB_1', 'invoices' => array($sub_invoice), 'invoice_limit' => 0, 'customer' => array('customer_code' => 'CUS_1')));
$sub_create_event = array('event' => 'subscription.create', 'data' => array('subscription_code' => 'SUB_1', 'email_token' => 'tok', 'amount' => 7500));
$sub_create_data = array('data' => array('subscription_code' => 'SUB_1', 'invoices' => array(array('id' => 55, 'reference' => 'gf-7-abc', 'status' => 'success', 'amount' => 7500))));
$invoice_event = function ($reference, $type = 'invoice.update') {
    return array('event' => $type, 'data' => array('subscription' => array('subscription_code' => 'SUB_1'), 'transaction' => array('reference' => $reference)));
};
$failed_event = array('event' => 'invoice.payment_failed', 'data' => array('subscription' => array('subscription_code' => 'SUB_1'), 'invoice_code' => 'INV_1', 'amount' => 7500));

$result = gf_paystack_callback_run($sub_create_event, 'live', $stored, false, $sub_create_data);
gf_paystack_test_check(is_array($result) && $result['type'] === 'create_subscription' && $result['add_subscription_payment']['transaction_id'] === 55, 'callback case 13');

$result = gf_paystack_callback_run($sub_create_event, 'test', $stored, false, $sub_create_data);
gf_paystack_test_check($result === false, 'callback case 14');

$result = gf_paystack_callback_run($invoice_event('inv-ref-1'), 'test', $stored, false, $sub_data);
gf_paystack_test_check($result === false, 'callback case 15');

$result = gf_paystack_callback_run($failed_event, 'test', $stored, false, $sub_data);
gf_paystack_test_check($result === false, 'callback case 16');

$result = gf_paystack_callback_run($failed_event, 'live', $stored, false, $sub_data);
gf_paystack_test_check(is_array($result) && $result['type'] === 'fail_subscription_payment', 'callback case 17');

$result = gf_paystack_callback_run($invoice_event(array('inv-ref-1')), 'live', $stored, false, $sub_data);
gf_paystack_test_check($result === false, 'callback case 18');

$result = gf_paystack_callback_run($invoice_event('inv-ref-1'), 'live', $stored, false, $sub_data);
gf_paystack_test_check(is_array($result) && $result['type'] === 'add_subscription_payment' && $result['id'] === '55_invoice.update' && $result['transaction_id'] === 55, 'callback case 19');

$verify_api = new GFPaystackRecordingApi();
$verify_api->response = array('status' => true, 'message' => 'ok', 'data' => array('id' => 77, 'status' => 'success', 'amount' => 9000, 'customer' => array('customer_code' => 'CUS_1')));
$result = gf_paystack_callback_run($invoice_event('inv-ref-2'), 'live', $stored, false, $sub_data, $verify_api);
gf_paystack_test_check(is_array($result) && $result['type'] === 'add_subscription_payment' && $result['id'] === '77_invoice.update' && $result['transaction_id'] === 77 && $verify_api->calls === array('transaction/verify/inv-ref-2'), 'callback case 20');

$verify_api->response = array('status' => true, 'data' => array('id' => 78, 'status' => 'failed', 'amount' => 9000));
$result = gf_paystack_callback_run($invoice_event('inv-ref-3'), 'live', $stored, false, $sub_data, $verify_api);
gf_paystack_test_check($result === false, 'callback case 21');

$verify_api->response = array('status' => true, 'message' => 'ok', 'data' => array('id' => 79, 'status' => 'success', 'amount' => 9000, 'customer' => array('customer_code' => 'CUS_2')));
$result = gf_paystack_callback_run($invoice_event('inv-ref-2'), 'live', $stored, false, $sub_data, $verify_api);
gf_paystack_test_check($result === false, 'callback case 27');

$ordered_data = $sub_data;
array_unshift($ordered_data['data']['invoices'], array('id' => 60, 'status' => 'success', 'amount' => 1));
$result = gf_paystack_callback_run($invoice_event('inv-ref-1'), 'live', $stored, false, $ordered_data);
gf_paystack_test_check(is_array($result) && $result['id'] === '55_invoice.update', 'callback case 28');

$pending_data = array('data' => array('subscription_code' => 'SUB_1', 'invoices' => array(array('id' => 56, 'reference' => 'inv-ref-4', 'status' => 'pending', 'paid' => false, 'amount' => 7500))));
$result = gf_paystack_callback_run($invoice_event('inv-ref-4'), 'live', $stored, false, $pending_data);
gf_paystack_test_check($result === false, 'callback case 22');

$throwing_api = new GFPaystackRecordingApi();
$throwing_api->throw = true;
$result = gf_paystack_callback_run($invoice_event('inv-ref-5'), 'live', $stored, false, $sub_data, $throwing_api);
gf_paystack_test_check(is_wp_error($result) && $result->code === 'transaction_verification', 'callback case 23');

$result = gf_paystack_callback_run($disable_event, 'test', array(), false, $subscription);
gf_paystack_test_check($result === false, 'callback case 24');

$result = gf_paystack_callback_run($disable_event, 'live', array(), false, $subscription);
gf_paystack_test_check(is_array($result) && $result['type'] === 'cancel_subscription', 'callback case 25');

$return_api = new GFPaystackRecordingApi();
$return_api->response = array('status' => true, 'data' => array('status' => 'success'));
$thankyou = gf_paystack_thankyou_addon($return_api, array('is_active' => '1', 'meta' => array('mode' => 'live')));
$thankyou_meta = $stored + array('paystack_tx_reference' => 'gf-7-abc');
gf_paystack_test_check(gf_paystack_thankyou_run($thankyou, gf_paystack_return_query('gf-7-other'), $thankyou_meta) === false && $return_api->calls === array(), 'thank-you case 1');

$return_api->response = array('status' => false, 'message' => 'declined', 'data' => array('status' => 'failed'));
$thankyou_result = gf_paystack_thankyou_run($thankyou, gf_paystack_return_query('gf-7-abc'));
gf_paystack_test_check($thankyou_result === false && $return_api->calls === array('transaction/verify/gf-7-abc'), 'thank-you case 2');
$_GET = array();

$lookup = new GFPaystackCallbackFixture();
$GLOBALS['gf_paystack_test_refs'] = array('gf-7-abc' => '7');
$GLOBALS['gf_paystack_test_meta'] = array('7' => array('paystack_tx_reference' => 'gf-7-abc'));
gf_paystack_test_check($lookup->get_entry_id_by_reference('gf-7-abc') === '7', 'get_entry_id_by_reference case 1');
gf_paystack_test_check($lookup->get_entry_id_by_reference('') === false, 'get_entry_id_by_reference case 2');
gf_paystack_test_check($lookup->get_entry_id_by_reference(array('gf-7-abc')) === false, 'get_entry_id_by_reference case 3');
$GLOBALS['gf_paystack_test_refs']['GF-7-ABC'] = '7';
gf_paystack_test_check($lookup->get_entry_id_by_reference('GF-7-ABC') === false, 'get_entry_id_by_reference case 4');

gf_paystack_test_check(GFPaystack::charge_matches_stored(5000, 'NGN', '5000', 'NGN') === true, 'charge_matches_stored case 1');
gf_paystack_test_check(GFPaystack::charge_matches_stored(5075, 'NGN', '5000', 'NGN') === true, 'charge_matches_stored case 2');
gf_paystack_test_check(GFPaystack::charge_matches_stored('abc', 'NGN', '5000', 'NGN') === false, 'charge_matches_stored case 3');
gf_paystack_test_check(GFPaystack::charge_matches_stored(5000, 'ngn', '5000', 'NGN') === true, 'charge_matches_stored case 4');
gf_paystack_test_check(GFPaystack::charge_matches_stored(100, 'NGN', '5000', 'NGN') === false, 'charge_matches_stored case 5');
gf_paystack_test_check(GFPaystack::charge_matches_stored(5000, 'USD', '5000', 'NGN') === false, 'charge_matches_stored case 6');
gf_paystack_test_check(GFPaystack::charge_matches_stored(5000, 'USD', '5000', false) === true, 'charge_matches_stored case 7');
gf_paystack_test_check(GFPaystack::charge_matches_stored(5000, 'NGN', false, false) === true, 'charge_matches_stored case 8');
gf_paystack_test_check(GFPaystack::charge_matches_stored(5000, 'NGN', '', '') === true, 'charge_matches_stored case 9');

class GFPaystackTestModeFixture extends GFPaystackCallbackFixture
{
    public function get_plugin_settings()
    {
        return array('api_mode' => 'test');
    }
}

gf_paystack_callback_run($disable_event, 'test', array(), false, $subscription);
$test_mode_addon = new GFPaystackTestModeFixture();
$test_mode_addon->fixture_event = $disable_event;
$test_mode_addon->fixture_mode = 'test';
$test_mode_addon->fixture_feed = false;
$test_mode_addon->fixture_subscription = $subscription;
gf_paystack_test_check($test_mode_addon->callback() === false, 'callback case 26');

class GFPaystackPathRecordingApi extends GFPaystackApi
{
    public $paths = array();

    public function send_request($endpoint, $args = array(), $method = 'post', $domain = 'https://api.paystack.co/')
    {
        $this->paths[] = $endpoint;
        return array();
    }
}

$path_api = new GFPaystackPathRecordingApi((object) array('secret_key' => 'sk', 'public_key' => 'pk'));
$path_api->verify_transaction('a/b?c#d');
gf_paystack_test_check($path_api->paths === array('transaction/verify/a%2Fb%3Fc%23d'), 'verify_transaction case 1');
