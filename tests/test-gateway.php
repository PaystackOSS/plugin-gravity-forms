<?php

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET = array();

$redirect_api = new GFPaystackRecordingApi();
$redirect_api->response = array('status' => true, 'data' => array('reference' => 'gf-9-ref', 'access_code' => 'ac_1', 'authorization_url' => 'https://checkout.example.test/ac_1'));
$redirect = new GFPaystackGatewayFixture();
$redirect->fixture_api = $redirect_api;
$GLOBALS['gf_paystack_test_meta'] = array();
$redirect_feed = array('id' => 5, 'meta' => array('mode' => 'test', 'transactionType' => 'product', 'feedName' => 'Feed'));
$redirect_url = $redirect->redirect_url($redirect_feed, array('payment_amount' => 50), array('id' => 1), array('id' => 9, 'currency' => 'NGN'));
$redirect_meta = $GLOBALS['gf_paystack_test_meta']['9'];
gf_paystack_test_check($redirect_url === 'https://checkout.example.test/ac_1', 'redirect_url case 1');
gf_paystack_test_check($redirect_meta['paystack_mode'] === 'test', 'redirect_url case 2');
gf_paystack_test_check($redirect_meta['paystack_tx_amount'] === 5000, 'redirect_url case 3');
gf_paystack_test_check($redirect_meta['paystack_tx_currency'] === 'NGN', 'redirect_url case 4');
gf_paystack_test_check($redirect_meta['paystack_tx_reference'] === 'gf-9-ref' && $redirect->modes_requested === array('test'), 'redirect_url case 5');

$redirect_api->response = array('status' => false, 'data' => array());
$GLOBALS['gf_paystack_test_meta'] = array();
$redirect_feed['meta']['mode'] = 'live';
$redirect_failed = $redirect->redirect_url($redirect_feed, array('payment_amount' => 50), array('id' => 1), array('id' => 9, 'currency' => 'NGN'));
gf_paystack_test_check($redirect_failed === false && $GLOBALS['gf_paystack_test_meta'] === array(), 'redirect_url case 6');

$log_file = tempnam(sys_get_temp_dir(), 'gfpstk');
$previous_log = ini_get('error_log');
ini_set('error_log', $log_file);
$log_api = new GFPaystackApi((object) array('secret_key' => 'sk_live_topsecret_value', 'public_key' => 'pk_live_public'));
$GLOBALS['gf_paystack_test_remote_calls'] = array();
$GLOBALS['gf_paystack_test_remote_response'] = array('body' => json_encode(array('status' => true, 'message' => 'ok', 'data' => array('authorization_url' => 'https://checkout.example.test/x', 'customer' => array('email' => 'pii@example.test')))));
$log_result = $log_api->send_request('transaction/initialize/', array('email' => 'pii@example.test', 'amount' => 100));
$log_contents = (string) file_get_contents($log_file);
$sent_headers = $GLOBALS['gf_paystack_test_remote_calls'][0][1]['headers'];
gf_paystack_test_check($sent_headers['Authorization'] === 'Bearer sk_live_topsecret_value' && $log_result['status'] === true, 'send_request logging case 1');
gf_paystack_test_check($log_contents !== '' && strpos($log_contents, 'sk_live_topsecret_value') === false && stripos($log_contents, 'Authorization') === false && stripos($log_contents, 'Bearer') === false, 'send_request logging case 2');
gf_paystack_test_check(strpos($log_contents, 'pii@example.test') === false && strpos($log_contents, 'transaction/initialize/') !== false, 'send_request logging case 3');

file_put_contents($log_file, '');
$GLOBALS['gf_paystack_test_remote_response'] = array('body' => json_encode(array('status' => false, 'message' => 'Invalid key')));
$log_thrown = gf_paystack_exception_message(function () use ($log_api) {
    $log_api->send_request('transaction/verify/abc', array(), 'get');
});
$log_contents = (string) file_get_contents($log_file);
gf_paystack_test_check($log_thrown === 'Invalid key' && strpos($log_contents, 'sk_live_topsecret_value') === false && strpos($log_contents, 'GET') !== false, 'send_request logging case 4');

$GLOBALS['gf_paystack_test_remote_response'] = new WP_Error('http', 'down');
$log_thrown = gf_paystack_exception_message(function () use ($log_api) {
    $log_api->send_request('transaction/verify/abc');
});
gf_paystack_test_check(strpos($log_thrown, 'Paystack Addon for Gravity Forms') !== false, 'send_request logging case 5');

ini_set('error_log', $previous_log === false ? '' : $previous_log);
unlink($log_file);

$event_settings = array('api_mode' => 'test', 'live_secret_key' => 'sk_live_a', 'test_secret_key' => 'sk_test_b');
$event_addon = new GFPaystackEventFixture();
$event_addon->settings = $event_settings;

$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', '', 'sk_test_b');
$event_result = $event_addon->get_webhook_event();
gf_paystack_test_check($event_addon->webhook_mode_value() === 'test' && $event_result !== false && !is_wp_error($event_result), 'get_webhook_event case 1');

$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', '', 'sk_live_a');
$event_result = $event_addon->get_webhook_event();
gf_paystack_test_check($event_addon->webhook_mode_value() === 'test' && $event_result === false, 'get_webhook_event case 2');

$event_addon->settings['api_mode'] = 'live';
$event_result = $event_addon->get_webhook_event();
gf_paystack_test_check($event_addon->webhook_mode_value() === 'live' && $event_result !== false, 'get_webhook_event case 3');


foreach (array('foo', array('live')) as $domain) {
    $seam_body = json_encode(array('event' => 'charge.success', 'data' => array('domain' => $domain)));
    $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = hash_hmac('sha512', $seam_body, 'sk_test_b');
    $event_addon->settings['api_mode'] = 'test';
    $event_result = $event_addon->get_webhook_event($seam_body);
    $seam_case = is_array($domain) ? 5 : 4;
    gf_paystack_test_check($event_addon->webhook_mode_value() === 'test' && is_array($event_result) && $event_result['event'] === 'charge.success', "get_webhook_event case $seam_case");
}

unset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']);

$mode_api = new GFPaystackRecordingApi();
$mode_api->response = array('status' => false, 'message' => 'declined', 'data' => array('status' => 'failed'));
$mode_feed = array('is_active' => '1', 'meta' => array('mode' => 'live'));

$mode_addon = gf_paystack_thankyou_addon($mode_api, $mode_feed);
gf_paystack_thankyou_run($mode_addon, gf_paystack_return_query('gf-7-abc'), array('paystack_tx_reference' => 'gf-7-abc', 'paystack_mode' => 'test'));
gf_paystack_test_check($mode_addon->modes_requested === array('test'), 'thank-you case 3');

$mode_addon = gf_paystack_thankyou_addon($mode_api, $mode_feed);
gf_paystack_thankyou_run($mode_addon, gf_paystack_return_query('gf-7-abc'), array('paystack_tx_reference' => 'gf-7-abc'));
gf_paystack_test_check($mode_addon->modes_requested === array('live'), 'thank-you case 4');

$mode_calls = count($mode_api->calls);
$mode_addon = gf_paystack_thankyou_addon($mode_api, $mode_feed);
gf_paystack_thankyou_run($mode_addon, gf_paystack_return_query('gf-7-abc', array('ids' => '7|5|1', 'hash' => 'tampered')));
gf_paystack_test_check($mode_addon->modes_requested === array() && count($mode_api->calls) === $mode_calls, 'thank-you case 5');

$mode_addon = gf_paystack_thankyou_addon($mode_api);
gf_paystack_thankyou_run($mode_addon, gf_paystack_return_query('gf-7-abc', array('ids' => '7|5|1')));
gf_paystack_test_check($mode_addon->modes_requested === array() && count($mode_api->calls) === $mode_calls, 'thank-you case 6');

gf_paystack_thankyou_run($mode_addon, gf_paystack_return_query('gf-7-abc', array('ids' => array('x'), 'hash' => array('y'))));
gf_paystack_test_check($mode_addon->modes_requested === array() && count($mode_api->calls) === $mode_calls, 'thank-you case 7');
$_GET = array();

$amount_api = new GFPaystackRecordingApi();
$amount_api->response = array('status' => true, 'data' => array('status' => 'success', 'amount' => 100, 'currency' => 'NGN'));
$amount_addon = gf_paystack_thankyou_addon($amount_api, $mode_feed);
gf_paystack_thankyou_run($amount_addon, gf_paystack_return_query('gf-7-abc'), array('paystack_tx_reference' => 'gf-7-abc', 'paystack_tx_amount' => 5000, 'paystack_tx_currency' => 'NGN'));
gf_paystack_test_check($amount_api->logged === array() && count($amount_api->calls) === 1, 'thank-you case 8');
$_GET = array();

$GLOBALS['gf_paystack_test_remote_calls'] = array();
$GLOBALS['gf_paystack_test_remote_response'] = array('body' => json_encode(array('status' => true)));
$log_api->log_transaction_success('gf-7-abc');
$tracker_call = $GLOBALS['gf_paystack_test_remote_calls'][0];
gf_paystack_test_check(strpos($tracker_call[0], 'plugin-tracker') !== false && !isset($tracker_call[1]['headers']['Authorization']), 'send_request headers case 1');
