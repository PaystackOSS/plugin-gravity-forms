<?php

$st_rk_test = 'rk_test_' . str_repeat('Ab3', 10);
$st_rk_live = 'rk_live_' . str_repeat('Cd4', 10);
$st_sk_test = 'sk_test_abc123def456';
$st_sk_live = 'sk_live_abc123def456';
$st_ws_test = 'whsec_' . str_repeat('a1', 16);
$st_ws_live = 'whsec_' . str_repeat('b2', 16);
$st_suffix = ' No settings were saved.';

$st_run = function ($mode, $posted, $stored = array(), $configure = null) {
    $GLOBALS['gf_paystack_test_gf_logs'] = array();
    $addon = new GFPaystackSettingsFixture();
    $addon->posted = $posted;
    $addon->stored = $stored;
    if ($configure) {
        $configure($addon);
    }
    if ($mode === 'test') {
        $addon->validate_test_secret_key(array());
    } else {
        $addon->validate_live_secret_key(array());
    }

    return $addon;
};

$a = $st_run('test', array('test_secret_key' => $st_sk_test), array('test_secret_key' => $st_sk_test));
gf_paystack_test_check($a->field_errors === array() && $a->validate_calls === array(), 'settings unchanged sk saves');

$a = $st_run('live', array('live_secret_key' => $st_sk_live), array());
gf_paystack_test_check($a->field_errors === array() && $a->validate_calls === array(), 'settings new sk saves without probe');

$a = $st_run('test', array('test_secret_key' => '  ' . $st_rk_test . ' ', 'test_webhook_secret' => $st_ws_test));
gf_paystack_test_check($a->field_errors === array() && $a->validate_calls === array($st_rk_test), 'settings new rk with whsec probes trimmed key and saves');
$logged = json_encode($GLOBALS['gf_paystack_test_gf_logs']);
gf_paystack_test_check(strpos($logged, 'rk validate mode=test code=ok probes=transactions:read:200') !== false && strpos($logged, $st_rk_test) === false, 'settings probe log line never contains the key');

$rk_posted = array('live_secret_key' => $st_rk_live, 'live_webhook_secret' => $st_ws_live);
$verdict = function ($v) {
    return function ($addon) use ($v) {
        $addon->verdict = array_merge($addon->verdict, $v);
    };
};

$a = $st_run('live', $rk_posted, array(), $verdict(array('code' => 'invalid_key')));
gf_paystack_test_check(isset($a->field_errors['live_secret_key']) && strpos($a->field_errors['live_secret_key'], 'Paystack didn\'t accept this key.') === 0 && substr($a->field_errors['live_secret_key'], -strlen($st_suffix)) === $st_suffix, 'settings probe invalid_key error');

$a = $st_run('live', $rk_posted, array(), $verdict(array('code' => 'missing_scope', 'scopes' => array('transactions:write'))));
gf_paystack_test_check($a->field_errors['live_secret_key'] === 'This restricted key can\'t be used: enable Transactions: write. It needs Transactions: read and write. Permissions that can move money aren\'t allowed.' . $st_suffix, 'settings probe missing_scope copy');

$a = $st_run('live', $rk_posted, array(), $verdict(array('code' => 'sensitive_scope', 'sensitive_scopes' => array('transfers', 'disputes_refunds'))));
gf_paystack_test_check(strpos($a->field_errors['live_secret_key'], 'remove Transfers, Disputes and refunds.') !== false && strpos($a->field_errors['live_secret_key'], 'enable') === false, 'settings probe sensitive_scope copy');

$a = $st_run('live', $rk_posted, array(), $verdict(array('code' => 'missing_scope', 'scopes' => array('transactions:read'), 'sensitive_scopes' => array('payouts'))));
gf_paystack_test_check(strpos($a->field_errors['live_secret_key'], 'enable Transactions: read; remove Payouts.') !== false, 'settings probe missing and sensitive scope copy');

$a = $st_run('live', $rk_posted, array(), $verdict(array('code' => 'unverifiable', 'reason' => 'blocked')));
gf_paystack_test_check(strpos($a->field_errors['live_secret_key'], 'Paystack refused our check of this key.') === 0 && substr($a->field_errors['live_secret_key'], -strlen($st_suffix)) === $st_suffix, 'settings probe unverifiable blocked copy');

$a = $st_run('live', $rk_posted, array(), $verdict(array('code' => 'unverifiable', 'reason' => 'transient')));
gf_paystack_test_check(strpos($a->field_errors['live_secret_key'], 'We couldn\'t reach Paystack to check this key.') === 0 && substr($a->field_errors['live_secret_key'], -strlen($st_suffix)) === $st_suffix, 'settings probe unverifiable transient copy');

$a = $st_run('test', array('test_secret_key' => $st_rk_test, 'test_webhook_secret' => ''));
gf_paystack_test_check(isset($a->field_errors['test_webhook_secret']) && !isset($a->field_errors['test_secret_key']) && strpos($a->field_errors['test_webhook_secret'], 'Add your test webhook signing secret') !== false && substr($a->field_errors['test_webhook_secret'], -strlen($st_suffix)) === $st_suffix && $a->validate_calls === array(), 'settings rk without whsec errors on whsec field without probing');

$a = $st_run('test', array('test_secret_key' => $st_rk_test, 'test_webhook_secret' => ''), array('test_secret_key' => $st_rk_test));
gf_paystack_test_check(isset($a->field_errors['test_webhook_secret']), 'settings whsec cleared with unchanged rk errors');

$a = $st_run('test', array('test_secret_key' => $st_rk_test, 'test_webhook_secret' => 'nope'), array('test_secret_key' => $st_rk_test));
gf_paystack_test_check(strpos($a->field_errors['test_webhook_secret'], 'Webhook signing secrets start with whsec_.') === 0, 'settings bad whsec shape errors');

$a = $st_run('live', array('live_secret_key' => $st_sk_live, 'live_webhook_secret' => $st_ws_live, 'test_webhook_secret' => $st_ws_live));
gf_paystack_test_check(isset($a->field_errors['live_webhook_secret']) && strpos($a->field_errors['live_webhook_secret'], 'are the same') !== false, 'settings test==live whsec errors');

$a = $st_run('live', array('live_secret_key' => $st_sk_test));
gf_paystack_test_check($a->field_errors['live_secret_key'] === 'This is a test key. Paste it in the test secret key field.' . $st_suffix, 'settings wrong_field copy');

$a = $st_run('test', array('test_secret_key' => 'garbage'));
gf_paystack_test_check($a->field_errors['test_secret_key'] === 'Secret keys start with sk_test_ and restricted keys with rk_test_. Check you copied the whole key.' . $st_suffix, 'settings invalid_format copy');

$GLOBALS['gf_paystack_test_filters'] = array('gform_paystack_restricted_keys_enabled' => false);
$a = $st_run('live', $rk_posted);
gf_paystack_test_check(isset($a->field_errors['live_secret_key']) && strpos($a->field_errors['live_secret_key'], 'Restricted keys are turned off on this site.') === 0 && $a->validate_calls === array(), 'settings gate off new rk rejected');

$a = $st_run('live', $rk_posted, array('live_secret_key' => $st_rk_live));
gf_paystack_test_check($a->field_errors === array() && $a->validate_calls === array(), 'settings gate off kept rk ok');
unset($GLOBALS['gf_paystack_test_filters']);

// Fields.
$fa = new GFPaystackSettingsFixture();
$by_name = array();
foreach ($fa->api_settings_fields() as $f) {
    $by_name[$f['name']] = $f;
}
$pw_ok = true;
foreach (array('test_secret_key', 'live_secret_key', 'test_webhook_secret', 'live_webhook_secret') as $n) {
    $pw_ok = $pw_ok && isset($by_name[$n]) && $by_name[$n]['input_type'] === 'password';
}
gf_paystack_test_check($pw_ok, 'settings secret and whsec fields are password inputs');
$order = array_keys($by_name);
gf_paystack_test_check(array_search('test_webhook_secret', $order) === array_search('test_secret_key', $order) + 1 && array_search('live_webhook_secret', $order) === array_search('live_secret_key', $order) + 1, 'settings whsec fields follow their secret key');
gf_paystack_test_check($by_name['test_secret_key']['label'] === 'Test Secret or Restricted Key' && $by_name['live_webhook_secret']['label'] === 'Live Webhook Signing Secret' && strpos($by_name['live_secret_key']['tooltip'], 'A restricted key lets this plugin take payments and nothing else.') !== false, 'settings labels and help text');

// Feed.
$feed = function ($posted, $stored, $plans = 'ok') {
    $addon = new GFPaystackSettingsFixture();
    $addon->posted = $posted;
    $addon->stored = $stored;
    $addon->plans_result = $plans;
    $addon->validate_subscription_plans(array());

    return $addon;
};

$sub_test = array('transactionType' => 'subscription', 'mode' => 'test');
$a = $feed($sub_test, array('test_secret_key' => $st_rk_test));
gf_paystack_test_check($a->field_errors === array() && $a->plans_calls === array($st_rk_test), 'feed subscription rk plans ok');

$a = $feed($sub_test, array('test_secret_key' => $st_rk_test), 'plans_missing');
gf_paystack_test_check($a->field_errors['transactionType'] === 'Subscriptions need Plans: write on your test restricted key. Add it in your Paystack Dashboard, then save this feed again.', 'feed plans_missing copy');

$a = $feed(array('transactionType' => 'subscription', 'mode' => 'live'), array('live_secret_key' => $st_rk_live), 'plans_mor');
gf_paystack_test_check($a->field_errors['transactionType'] === 'Paystack subscriptions aren\'t available for merchant-of-record accounts.', 'feed plans_mor copy');

$a = $feed($sub_test, array('test_secret_key' => $st_rk_test), 'unverifiable');
gf_paystack_test_check(strpos($a->field_errors['transactionType'], 'We couldn\'t reach Paystack') === 0, 'feed plans unverifiable copy');

$a = $feed($sub_test, array('test_secret_key' => $st_sk_test, 'live_secret_key' => $st_rk_live), 'plans_missing');
gf_paystack_test_check($a->field_errors === array() && $a->plans_calls === array(), 'feed subscription sk no probe, other mode rk ignored');

$a = $feed(array('transactionType' => 'product', 'mode' => 'test'), array('test_secret_key' => $st_rk_test), 'plans_missing');
gf_paystack_test_check($a->field_errors === array() && $a->plans_calls === array(), 'feed product rk no probe');

$GLOBALS['gf_paystack_test_saved_settings'] = null;
$st_saver = new GFPaystackSettingsFixture();
$st_saver->update_plugin_settings(array('test_secret_key' => "  $st_rk_test\n", 'live_webhook_secret' => " $st_ws_live ", 'api_mode' => ' test '));
$st_saved = $GLOBALS['gf_paystack_test_saved_settings'];
gf_paystack_test_check($st_saved['test_secret_key'] === $st_rk_test && $st_saved['live_webhook_secret'] === $st_ws_live && $st_saved['api_mode'] === ' test ', 'settings save trims only credential fields');
