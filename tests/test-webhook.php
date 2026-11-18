<?php

$webhook_body = '{"event":"charge.success"}';
$webhook_api = new GFPaystackApi((object) array('secret_key' => 'sk_test_secret', 'public_key' => 'pk', 'stored_secret_key' => 'sk_test_secret'));
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

$charge_api = function ($overrides = array()) {
    $api = new GFPaystackRecordingApi();
    $api->response = array('status' => true, 'message' => 'ok', 'data' => array_merge(array('id' => 99, 'reference' => 'gf-7-abc', 'status' => 'success', 'amount' => 5000, 'currency' => 'NGN'), $overrides));
    return $api;
};

$verified_api = $charge_api();
$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $verified_api);
gf_paystack_test_check(is_array($result) && $result['type'] === 'complete_payment' && $result['entry_id'] === '7' && $result['amount'] == 50 && $verified_api->calls === array('transaction/verify/gf-7-abc'), 'callback case 1');

$result = gf_paystack_callback_run(gf_paystack_charge_event(array('metadata' => array('entry_id' => '8'))), 'live', $stored);
gf_paystack_test_check($result === false, 'callback case 2');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'test', $stored);
gf_paystack_test_check($result === false, 'callback case 3');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', array('paystack_tx_amount' => '5000'), null, null, $charge_api());
gf_paystack_test_check(is_array($result) && $result['type'] === 'complete_payment', 'callback case 4');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'test', array('paystack_tx_amount' => '5000'));
gf_paystack_test_check($result === false, 'callback case 5');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $charge_api(array('amount' => 100)));
gf_paystack_test_check($result === false, 'callback case 6');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $charge_api(array('currency' => 'USD')));
gf_paystack_test_check($result === false, 'callback case 7');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', array(), null, null, $charge_api());
gf_paystack_test_check(is_array($result) && $result['type'] === 'complete_payment', 'callback case 8');

$result = gf_paystack_callback_run(gf_paystack_charge_event(array('amount' => 100, 'currency' => 'USD')), 'live', $stored, null, null, $charge_api());
gf_paystack_test_check(is_array($result) && $result['type'] === 'complete_payment' && $result['amount'] == 50, 'callback case 29');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $charge_api(array('status' => 'failed')));
gf_paystack_test_check($result === false, 'callback case 30');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $charge_api(array('id' => 100)));
gf_paystack_test_check($result === false, 'callback case 31');

$throwing_charge_api = $charge_api();
$throwing_charge_api->throw = true;
$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $throwing_charge_api);
gf_paystack_test_check(is_wp_error($result) && $result->code === 'transaction_verification' && rgar($result->get_error_data(), 'status_header') === 500, 'callback case 32: verify throws asks Paystack to retry');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $charge_api(array('id' => null)));
gf_paystack_test_check($result === false, 'callback case 33: verified charge without an id is not completed');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $charge_api(array('reference' => 'gf-7-other')));
gf_paystack_test_check($result === false, 'callback case 34: verified reference mismatch is not completed');

$result = gf_paystack_callback_run(gf_paystack_charge_event(), 'live', $stored, null, null, $charge_api(array('reference' => null)));
gf_paystack_test_check($result === false, 'callback case 35: verified charge without a reference is not completed');

$sub_feed = array('is_active' => '1', 'meta' => array('mode' => 'live', 'transactionType' => 'subscription'));
$sub_charge = $charge_api(array('authorization' => array('authorization_code' => 'AUTH_verified')));
$sub_event = gf_paystack_charge_event();
$sub_event['data']['authorization'] = array('authorization_code' => 'AUTH_forged');
$result = gf_paystack_callback_run($sub_event, 'live', $stored, $sub_feed, null, $sub_charge);
gf_paystack_test_check($sub_charge->calls === array('transaction/verify/gf-7-abc') && gform_get_meta('7', 'paystack_tx_auth_code') === 'AUTH_verified', 'callback case 36: subscription branch stores the verified authorization code');

$sub_forged = $charge_api(array('id' => 100, 'authorization' => array('authorization_code' => 'AUTH_verified')));
gf_paystack_callback_run($sub_event, 'live', $stored, $sub_feed, null, $sub_forged);
gf_paystack_test_check(gform_get_meta('7', 'paystack_tx_auth_code') === false, 'callback case 37: subscription branch stores nothing when the verified id differs');

$sub_throwing = $charge_api();
$sub_throwing->throw = true;
$result = gf_paystack_callback_run($sub_event, 'live', $stored, $sub_feed, null, $sub_throwing);
gf_paystack_test_check(is_wp_error($result) && rgar($result->get_error_data(), 'status_header') === 500 && gform_get_meta('7', 'paystack_tx_auth_code') === false, 'callback case 38: subscription verify throws asks Paystack to retry');

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

// # Signing-secret (Paystack-Signature) verification
$whsec = 'whsec_' . str_repeat('ab12', 8);
$whsec_other = 'whsec_' . str_repeat('cd34', 8);
$sk_live = 'sk_live_abc123';
$rk_live = 'rk_live_' . str_repeat('x', 20);
$reset_sig_headers = function () {
    unset($_SERVER['HTTP_PAYSTACK_SIGNATURE'], $_SERVER['HTTP_X_PAYSTACK_SIGNATURE']);
};
$reset_sig_headers();
$sign = function ($t, $body, $secret, $kid = 'k1') {
    return "v1={$kid}:" . hash_hmac('sha512', $t . '.' . $body, $secret);
};
$whsec_api = function ($secret, $stored = '') {
    return new GFPaystackApi((object) array('secret_key' => $stored, 'public_key' => 'pk', 'webhook_secret' => $secret, 'stored_secret_key' => $stored));
};
$now = 1800000000;
$bad_v1 = 'v1=k0:' . str_repeat('0', 128);

$api = $whsec_api($whsec);
$t = (string) $now;
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t," . $sign($t, $webhook_body, $whsec), $now) === 'valid', 'whsec valid header');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, " t = $t , " . $sign($t, $webhook_body, $whsec), $now) === 'valid', 'whsec header whitespace trimmed');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t,$bad_v1," . $sign($t, $webhook_body, $whsec, 'k2'), $now) === 'valid', 'whsec rotated multi-v1, right one second');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t," . $sign($t, $webhook_body, $whsec) . ",$bad_v1", $now) === 'valid', 'whsec rotated multi-v1, right one first');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t,$bad_v1,$bad_v1", $now) === 'invalid', 'whsec all v1 wrong');

$t_old = (string) ($now - 301);
$t_edge = (string) ($now - 300);
$t_future = (string) ($now + 301);
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t_old," . $sign($t_old, $webhook_body, $whsec), $now) === 'stale', 'whsec stale t-301');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t_future," . $sign($t_future, $webhook_body, $whsec), $now) === 'stale', 'whsec future t+301');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t_edge," . $sign($t_edge, $webhook_body, $whsec), $now) === 'valid', 'whsec t-300 accepted');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t_old," . $bad_v1, $now) === 'invalid', 'whsec HMAC checked before window');

$good_v1 = $sign($t, $webhook_body, $whsec);
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t,t=$t,$good_v1", $now) === 'malformed', 'whsec two t values');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, $good_v1, $now) === 'malformed', 'whsec no t');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=12ab,$good_v1", $now) === 'malformed', 'whsec non-digit t');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=-5,$good_v1", $now) === 'malformed', 'whsec negative t');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t", $now) === 'malformed', 'whsec no v1');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t,v1=" . hash_hmac('sha512', $t . '.' . $webhook_body, $whsec), $now) === 'malformed', 'whsec v1 without kid');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, "t=$t,v1=k1:abc", $now) === 'malformed', 'whsec v1 short hex');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, '', $now) === 'malformed', 'whsec empty header');
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, array($good_v1), $now) === 'malformed', 'whsec non-string header');

$many = "t=$t," . str_repeat("$bad_v1,", 10) . $good_v1;
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, $many, $now) === 'invalid', 'whsec v1 entries beyond 10 ignored');
$nine = "t=$t," . str_repeat("$bad_v1,", 9) . $good_v1;
gf_paystack_test_check($api->check_signing_secret_header($webhook_body, $nine, $now) === 'valid', 'whsec 10th v1 entry counted');

gf_paystack_test_check($api->check_signing_secret_header('{"event":"charge.success","x":1}', "t=$t,$good_v1", $now) === 'invalid', 'whsec tampered body');
gf_paystack_test_check($whsec_api($whsec_other)->check_signing_secret_header($webhook_body, "t=$t,$good_v1", $now) === 'invalid', 'whsec wrong secret');
gf_paystack_test_check($whsec_api('')->check_signing_secret_header($webhook_body, "t=$t,$good_v1", $now) === 'malformed', 'whsec blank secret never verifies');

// validate_webhook(): header from $_SERVER, HMAC over the injected $input
$live_t = (string) time();
$live_header = "t=$live_t," . $sign($live_t, $webhook_body, $whsec);
$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = $live_header;
gf_paystack_test_check($whsec_api($whsec)->validate_webhook($webhook_body) === true, 'validate_webhook whsec via server header');
$checked = $whsec_api($whsec);
$checked->validate_webhook($webhook_body);
gf_paystack_test_check($checked->last_webhook_check === array('sig' => 'whsec', 'result' => 'valid'), 'validate_webhook records whsec/valid');
gf_paystack_test_check($whsec_api($whsec)->validate_webhook('{"event":"charge.success","amount":1}') === false, 'validate_webhook HMAC uses injected input, not signed body');
$reset_sig_headers();
gf_paystack_test_check($whsec_api($whsec)->validate_webhook($webhook_body) === false, 'validate_webhook whsec set, no header');

// whsec set: legacy header must not rescue a bad or missing Paystack-Signature
$legacy = hash_hmac('sha512', $webhook_body, $sk_live);
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = $legacy;
gf_paystack_test_check($whsec_api($whsec, $sk_live)->validate_webhook($webhook_body) === false, 'whsec set + only valid legacy header rejected');
$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = "t=$live_t,$bad_v1";
gf_paystack_test_check($whsec_api($whsec, $sk_live)->validate_webhook($webhook_body) === false, 'whsec set + bad header + valid legacy rejected');
gf_paystack_test_check($whsec_api($whsec, $sk_live)->validate_webhook($webhook_body, $legacy) === false, 'whsec set + legacy passed explicitly rejected');
$reset_sig_headers();

// rk stored without whsec is never an HMAC key
$rk_sig = hash_hmac('sha512', $webhook_body, $rk_live);
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = $rk_sig;
gf_paystack_test_check($whsec_api('', $rk_live)->validate_webhook($webhook_body) === false, 'rk without whsec rejected despite rk-signed header');
$rk_api = new GFPaystackApi((object) array('secret_key' => $rk_live, 'public_key' => 'pk', 'webhook_secret' => '', 'stored_secret_key' => $rk_live));
gf_paystack_test_check($rk_api->validate_webhook($webhook_body, $rk_sig) === false, 'rk without whsec rejected with explicit signature');
$reset_sig_headers();

// sk legacy path unchanged
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = $legacy;
$sk_api = $whsec_api('', $sk_live);
gf_paystack_test_check($sk_api->validate_webhook($webhook_body) === true && $sk_api->last_webhook_check === array('sig' => 'legacy', 'result' => 'valid'), 'sk legacy path valid');
gf_paystack_test_check($sk_api->validate_webhook('{"tampered":1}') === false, 'sk legacy path tampered body');
$reset_sig_headers();

// get_webhook_event(): mode selection
$event_addon = function ($settings) {
    $addon = new GFPaystackEventFixture();
    $addon->settings = $settings;
    return $addon;
};
$header_for = function ($body, $secret) use ($sign) {
    $ts = (string) time();
    return "t=$ts," . $sign($ts, $body, $secret);
};
$live_whsec = $whsec;
$test_whsec = $whsec_other;
$both = array('api_mode' => 'live', 'live_secret_key' => $rk_live, 'live_webhook_secret' => $live_whsec, 'test_secret_key' => 'rk_test_' . str_repeat('y', 20), 'test_webhook_secret' => $test_whsec);

$event_body = json_encode(array('event' => 'charge.success', 'data' => array('reference' => 'gf-7-abc')));
$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = $header_for($event_body, $test_whsec);
$addon = $event_addon($both);
$event = $addon->get_webhook_event($event_body);
gf_paystack_test_check(is_array($event) && $addon->webhook_mode_value() === 'test', 'domain missing: verifies under the mode whose whsec matches (test)');

$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = $header_for($event_body, $live_whsec);
$addon = $event_addon($both);
$event = $addon->get_webhook_event($event_body);
gf_paystack_test_check(is_array($event) && $addon->webhook_mode_value() === 'live', 'domain missing: verifies under the mode whose whsec matches (live)');

$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = $header_for($event_body, 'whsec_' . str_repeat('ef56', 8));
$addon = $event_addon($both);
gf_paystack_test_check($addon->get_webhook_event($event_body) === false, 'domain missing: no mode verifies');
gf_paystack_test_check(count($addon->errors) === 1 && strpos($addon->errors[0], 'checks=live:whsec:invalid,test:whsec:invalid') !== false && strpos($addon->errors[0], $live_whsec) === false, 'failure logged at error level listing every attempt, no secret');

$domain_body = json_encode(array('event' => 'charge.success', 'data' => array('domain' => 'live', 'reference' => 'gf-7-abc')));
$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = $header_for($domain_body, $test_whsec);
$addon = $event_addon($both);
gf_paystack_test_check($addon->get_webhook_event($domain_body) === false, 'domain=live with a test-secret signature rejected (no cross-mode retry)');

$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = $header_for($domain_body, $live_whsec);
$addon = $event_addon($both);
gf_paystack_test_check(is_array($addon->get_webhook_event($domain_body)) && $addon->webhook_mode_value() === 'live', 'domain=live verifies with live whsec');

$test_event_body = json_encode(array('event' => 'charge.success', 'data' => array('reference' => 'gf-7-abc')));
$_SERVER['HTTP_PAYSTACK_SIGNATURE'] = $header_for($test_event_body, $test_whsec);
$addon = $event_addon($both);
$addon->get_webhook_event($test_event_body);
$GLOBALS['gf_paystack_test_entries'] = array('7' => array('id' => '7', 'form_id' => '1'));
$GLOBALS['gf_paystack_test_meta'] = array('7' => array('paystack_mode' => 'live'));
$matcher = new class extends GFPaystackEventFixture {
    public function matches($entry)
    {
        return $this->webhook_mode_matches_entry($entry, array('meta' => array('mode' => 'live')));
    }
    public function set_mode($mode)
    {
        $this->webhook_mode = $mode;
    }
};
$matcher->set_mode($addon->webhook_mode_value());
gf_paystack_test_check($matcher->matches(array('id' => '7')) === false, 'test-signed event cannot match a live entry');

// sk merchant without whsec: legacy path through get_webhook_event, mode from domain or sk presence
$sk_settings = array('api_mode' => 'live', 'live_secret_key' => $sk_live);
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $event_body, $sk_live);
$addon = $event_addon($sk_settings);
gf_paystack_test_check(is_array($addon->get_webhook_event($event_body)) && $addon->webhook_mode_value() === 'live', 'sk legacy via get_webhook_event, domain missing');
$reset_sig_headers();

// domain missing, no whsec: the configured api mode decides, as before
$both_sk = array('api_mode' => 'test', 'live_secret_key' => $sk_live, 'test_secret_key' => 'sk_test_abc123');
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $event_body, $sk_live);
$addon = $event_addon($both_sk);
gf_paystack_test_check($addon->get_webhook_event($event_body) === false && $addon->webhook_mode_value() === 'test' && strpos($addon->errors[0], 'checks=test:legacy:invalid') !== false, 'domain missing, no whsec: api mode only, no sk try-both');
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $event_body, 'sk_test_abc123');
gf_paystack_test_check(is_array($event_addon($both_sk)->get_webhook_event($event_body)), 'domain missing, no whsec: api mode key verifies');

$one_whsec = array('api_mode' => 'live', 'live_secret_key' => $sk_live, 'test_secret_key' => 'sk_test_abc123', 'test_webhook_secret' => $test_whsec);
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $event_body, $sk_live);
$addon = $event_addon($one_whsec);
gf_paystack_test_check($addon->get_webhook_event($event_body) === false && $addon->webhook_mode_value() === 'test' && strpos($addon->errors[0], 'checks=test:whsec:malformed') !== false, 'domain missing: only modes with a whsec are candidates');
$reset_sig_headers();

// ?secret= must not change the verifying key
$_GET['secret'] = 'sk_live_attacker';
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $event_body, 'sk_live_attacker');
$addon = $event_addon($sk_settings);
gf_paystack_test_check($addon->get_webhook_event($event_body) === false, '?secret= does not reach webhook verification');
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $event_body, $sk_live);
gf_paystack_test_check(is_array($event_addon($sk_settings)->get_webhook_event($event_body)), '?secret= present, stored sk still verifies');
unset($_GET['secret']);

// no whsec, no sk: rejected
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $event_body, $rk_live);
gf_paystack_test_check($event_addon(array('api_mode' => 'live', 'live_secret_key' => $rk_live))->get_webhook_event($event_body) === false, 'rk only, no whsec: get_webhook_event rejects');
$reset_sig_headers();

// ?secret= must not reach Paystack calls
$_GET['secret'] = 'sk_live_attacker';
$_GET['public'] = 'pk_live_attacker';
$GLOBALS['gf_paystack_test_caps'] = array('update_core');
$read_prop = function ($api, $prop) {
    return (function () use ($prop) {
        return $this->$prop;
    })->call($api);
};
$qs_addon = $event_addon(array('api_mode' => 'live', 'live_secret_key' => $sk_live, 'live_public_key' => 'pk_live_stored'));
$qs_api = $qs_addon->paystack_api();
gf_paystack_test_check($read_prop($qs_api, 'secret_key') === $sk_live && $read_prop($qs_api, 'public_key') === 'pk_live_stored', '?secret= and ?public= ignored when building the API client');
unset($_GET['secret'], $_GET['public'], $GLOBALS['gf_paystack_test_caps']);
