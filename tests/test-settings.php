<?php

$st_rk_test = 'rk_test_' . str_repeat('Ab3', 10);
$st_rk_live = 'rk_live_' . str_repeat('Cd4', 10);
$st_sk_test = 'sk_test_abc123def456';
$st_sk_live = 'sk_live_abc123def456';
$st_ws_test = 'whsec_' . str_repeat('a1', 16);
$st_ws_live = 'whsec_' . str_repeat('b2', 16);
$st_suffix = ' No settings were saved.';

$st_run = function ($mode, $posted, $stored = array(), $configure = null, $which = 'both') {
    $GLOBALS['gf_paystack_test_gf_logs'] = array();
    $addon = new GFPaystackSettingsFixture();
    $addon->posted = $posted;
    $addon->stored = $stored;
    if ($configure) {
        $configure($addon);
    }
    $call = function ($method) use ($addon, $mode) {
        return (function () use ($method, $mode) {
            return $this->$method($mode);
        })->call($addon);
    };
    if ($which !== 'whsec') {
        $call('validate_secret_key_for_mode');
    }
    if ($which !== 'key') {
        $call('validate_webhook_secret_for_mode');
    }

    return $addon;
};

// The Mode field's callback checks both modes, so a key hidden by the mode switch can't skip validation.
$cred = function ($posted, $stored = array(), $configure = null) {
    $GLOBALS['gf_paystack_test_gf_logs'] = array();
    $addon = new GFPaystackSettingsFixture();
    $addon->posted = $posted;
    $addon->stored = $stored;
    if ($configure) {
        $configure($addon);
    }
    $addon->validate_credentials(array());

    return $addon;
};
$a = $cred(array('api_mode' => 'live', 'test_secret_key' => $st_sk_test));
gf_paystack_test_check(isset($a->field_errors['test_secret_key']) && strpos($a->field_errors['api_mode'] ?? '', 'Your test settings have a problem. Select Test to see it.') === 0, 'credentials: new sk in the hidden test fields is refused and flagged on Mode');
$a = $cred(array('api_mode' => 'live', 'test_secret_key' => $st_rk_test, 'test_webhook_secret' => ''));
gf_paystack_test_check(isset($a->field_errors['test_webhook_secret']) && isset($a->field_errors['api_mode']), 'credentials: hidden rk without whsec is refused');
$a = $cred(array('api_mode' => 'test', 'live_secret_key' => $st_rk_live, 'live_webhook_secret' => 'nope'));
gf_paystack_test_check(isset($a->field_errors['live_webhook_secret']) && strpos($a->field_errors['api_mode'] ?? '', 'Your live settings') === 0, 'credentials: hidden bad whsec is refused');
$a = $cred(array('api_mode' => 'test', 'live_secret_key' => $st_rk_live, 'live_webhook_secret' => $st_ws_live));
gf_paystack_test_check($a->validate_calls === array($st_rk_live), 'credentials: hidden new rk is still probed');
$a = $cred(array('api_mode' => 'test', 'test_secret_key' => $st_sk_test));
gf_paystack_test_check(isset($a->field_errors['test_secret_key']) && !isset($a->field_errors['api_mode']), 'credentials: an error in the selected mode is not repeated on Mode');
$a = $cred(array('api_mode' => 'test', 'test_secret_key' => $st_sk_test, 'live_secret_key' => $st_sk_live), array('test_secret_key' => $st_sk_test, 'live_secret_key' => $st_sk_live));
gf_paystack_test_check($a->field_errors === array() && $a->validate_calls === array(), 'credentials: grandfathered sk keys in both modes save');
$a = $cred(array('api_mode' => ''));
gf_paystack_test_check(($a->field_errors['api_mode'] ?? '') === 'Choose Test or Live.', 'credentials: a missing mode is refused');

$a = $st_run('test', array('test_secret_key' => $st_sk_test), array('test_secret_key' => $st_sk_test));
gf_paystack_test_check($a->field_errors === array() && $a->validate_calls === array(), 'settings unchanged sk saves');

$a = $st_run('live', array('live_secret_key' => $st_sk_live), array());
gf_paystack_test_check(strpos($a->field_errors['live_secret_key'] ?? '', 'Use a live restricted key instead.') === 0 && $a->validate_calls === array(), 'settings new sk refused without probe');

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
gf_paystack_test_check($a->field_errors['live_secret_key'] === 'This restricted key can\'t be used: enable Transactions: write. It needs Transactions: read and write. Permissions such as transfers, refunds, settlements and payouts aren\'t allowed.' . $st_suffix, 'settings probe missing_scope copy');

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

$a = $st_run('live', array('live_secret_key' => $st_sk_live, 'live_webhook_secret' => $st_ws_live, 'test_webhook_secret' => $st_ws_live), array('live_secret_key' => $st_sk_live));
gf_paystack_test_check(isset($a->field_errors['live_webhook_secret']) && strpos($a->field_errors['live_webhook_secret'], 'are the same') !== false, 'settings test==live whsec errors');

$a = $st_run('live', array('live_secret_key' => $st_rk_test, 'live_webhook_secret' => $st_ws_live));
gf_paystack_test_check($a->field_errors['live_secret_key'] === 'This is a test key. Paste it in the test restricted key field.' . $st_suffix, 'settings wrong_field copy');

$a = $st_run('live', array('live_secret_key' => 'sk_live_rotated123'), array('live_secret_key' => $st_sk_live));
gf_paystack_test_check($a->field_errors['live_secret_key'] === 'Use a live restricted key instead. Create one in your Paystack Dashboard with Transactions: read and write.' . $st_suffix, 'settings changed sk refused copy');

$a = $st_run('test', array('test_secret_key' => 'garbage'));
gf_paystack_test_check($a->field_errors['test_secret_key'] === 'Restricted keys start with rk_test_. Check you copied the whole key.' . $st_suffix, 'settings invalid_format copy');

// Each callback reports only its own codes.
$a = $st_run('test', array('test_secret_key' => $st_rk_test, 'test_webhook_secret' => ''), array(), null, 'key');
gf_paystack_test_check($a->error_log === array() && $a->validate_calls === array(), 'settings key callback ignores whsec codes');
$a = $st_run('test', array('test_secret_key' => $st_rk_test, 'test_webhook_secret' => ''), array(), null, 'whsec');
gf_paystack_test_check($a->error_log === array('test_webhook_secret'), 'settings whsec callback reports whsec codes once');
$a = $st_run('live', array('live_secret_key' => $st_sk_test), array(), null, 'whsec');
gf_paystack_test_check($a->error_log === array(), 'settings whsec callback ignores key codes');
$a = $st_run('live', array('live_secret_key' => $st_sk_test));
gf_paystack_test_check($a->error_log === array('live_secret_key'), 'settings key code is reported once across both callbacks');
$a = $st_run('live', $rk_posted, array(), $verdict(array('code' => 'invalid_key')));
gf_paystack_test_check($a->error_log === array('live_secret_key'), 'settings probe result is reported once across both callbacks');
$a = $st_run('live', array('live_secret_key' => $st_sk_live, 'live_webhook_secret' => 'nope'), array('live_secret_key' => $st_sk_live), null, 'key');
gf_paystack_test_check($a->error_log === array(), 'settings key callback silent on bad whsec shape');

// Plans check on save.
$sub_feed = function ($mode, $active = '1', $type = 'subscription') {
    return array('is_active' => $active, 'meta' => array('transactionType' => $type) + ($mode === null ? array() : array('mode' => $mode)));
};
$with_feeds = function ($feeds, $plans = 'ok') use ($verdict) {
    return function ($addon) use ($feeds, $plans) {
        $addon->feeds = $feeds;
        $addon->plans_result = $plans;
    };
};

$a = $st_run('live', $rk_posted, array(), $with_feeds(array($sub_feed('live'))), 'key');
gf_paystack_test_check($a->field_errors === array() && $a->plans_calls === array($st_rk_live), 'settings save probes plans for an active subscription feed in that mode');

$a = $st_run('live', $rk_posted, array(), $with_feeds(array($sub_feed(null))), 'key');
gf_paystack_test_check($a->plans_calls === array($st_rk_live), 'settings save treats a feed without a mode as live');

$a = $st_run('live', $rk_posted, array(), $with_feeds(array($sub_feed('live')), 'plans_missing'), 'key');
gf_paystack_test_check($a->field_errors['live_secret_key'] === 'Subscriptions need Plans: write on your live restricted key. Add it in your Paystack Dashboard, then save these settings again.' . $st_suffix, 'settings save plans_missing error on the key field');

$a = $st_run('live', $rk_posted, array(), $with_feeds(array($sub_feed('live')), 'plans_mor'), 'key');
gf_paystack_test_check($a->field_errors['live_secret_key'] === 'Paystack subscriptions aren\'t available for merchant-of-record accounts.' . $st_suffix, 'settings save plans_mor error on the key field');

$a = $st_run('live', $rk_posted, array(), $with_feeds(array($sub_feed('live')), 'unverifiable'), 'key');
gf_paystack_test_check(strpos($a->field_errors['live_secret_key'], 'We couldn\'t reach Paystack') === 0, 'settings save plans unverifiable error on the key field');

$a = $st_run('live', $rk_posted, array(), $with_feeds(array($sub_feed('test'), $sub_feed('live', '1', 'product')), 'plans_missing'), 'key');
gf_paystack_test_check($a->field_errors === array() && $a->plans_calls === array(), 'settings save skips plans when no subscription feed uses that mode');

$a = $st_run('live', $rk_posted, array(), $with_feeds(array($sub_feed('live', '0'))), 'key');
gf_paystack_test_check($a->plans_calls === array($st_rk_live), 'settings save probes plans for an inactive subscription feed too');

$a = $st_run('test', array('test_secret_key' => $st_rk_test, 'test_webhook_secret' => $st_ws_test), array('api_mode' => 'test'), $with_feeds(array($sub_feed(null))), 'key');
gf_paystack_test_check($a->plans_calls === array($st_rk_test), 'settings save treats a feed without a mode as the global api mode');

$a2 = $st_run('live', $rk_posted, array(), function ($addon) use ($sub_feed, $verdict) {
    $addon->feeds = array($sub_feed('live'));
    $addon->verdict = array_merge($addon->verdict, array('code' => 'invalid_key'));
    $addon->plans_result = 'plans_missing';
}, 'key');
gf_paystack_test_check($a2->plans_calls === array() && strpos($a2->field_errors['live_secret_key'], 'Paystack didn\'t accept this key.') === 0, 'settings save skips plans when the settings probe fails');

$a = $st_run('live', array('live_secret_key' => $st_rk_live, 'live_webhook_secret' => $st_ws_live), array('live_secret_key' => $st_rk_live), $with_feeds(array($sub_feed('live')), 'plans_missing'), 'key');
gf_paystack_test_check($a->plans_calls === array() && $a->field_errors === array(), 'settings save skips plans for an unchanged key');

$a = $st_run('live', array('live_secret_key' => $st_rk_live, 'live_webhook_secret' => $st_ws_live), array('live_secret_key' => $st_sk_live));
gf_paystack_test_check($a->field_errors === array() && $a->validate_calls === array($st_rk_live), 'settings stored sk replaced by rk is probed');

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
gf_paystack_test_check($by_name['test_secret_key']['label'] === 'Test Restricted Key' && $by_name['live_webhook_secret']['label'] === 'Live Webhook Signing Secret' && strpos($by_name['live_secret_key']['tooltip'], 'Create a restricted key in your Paystack Dashboard') !== false, 'settings labels and help text');
gf_paystack_test_check(!isset($by_name['test_public_key']) && !isset($by_name['live_public_key']), 'settings have no public key fields');
$labels = '';
foreach ($by_name as $f) {
    $labels .= ' ' . rgar($f, 'label') . ' ' . rgar($f, 'tooltip');
}
gf_paystack_test_check(stripos($labels, 'secret key') === false, 'settings never mention secret keys');
$dep_ok = true;
foreach (array('test' => array('test_secret_key', 'test_webhook_secret'), 'live' => array('live_secret_key', 'live_webhook_secret')) as $dep_mode => $names) {
    foreach ($names as $n) {
        $dep_ok = $dep_ok && rgar($by_name[$n], 'dependency') === array('live' => true, 'fields' => array(array('field' => 'api_mode', 'values' => array($dep_mode))));
    }
}
gf_paystack_test_check($dep_ok, 'settings key fields show only for their own mode');
gf_paystack_test_check($by_name['api_mode']['default_value'] === 'test' && !isset($by_name['webhooks_enabled']['dependency']['live']), 'settings mode defaults to test and webhook checkbox shows in both modes');

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


// Feed: own validity of the transactionType field.
$feed_field = array('name' => 'transactionType', 'choices' => array(array('label' => 'Products', 'value' => 'product'), array('label' => 'Subscription', 'value' => 'subscription')));
$feed_run = function ($posted, $stored, $plans = 'ok', $configure = null, $field = null) use ($feed_field) {
    $addon = new GFPaystackSettingsFixture();
    $addon->posted = $posted;
    $addon->stored = $stored;
    $addon->plans_result = $plans;
    if ($configure) {
        $configure($addon);
    }
    $addon->validate_subscription_plans($field === null ? $feed_field : $field);

    return $addon;
};

foreach (array('empty' => '', 'missing' => null, 'unknown' => 'donation', 'array' => array('subscription')) as $label => $bad) {
    $posted = $bad === null ? array('mode' => 'test') : array('transactionType' => $bad, 'mode' => 'test');
    $a = $feed_run($posted, array('test_secret_key' => $st_rk_test));
    gf_paystack_test_check($a->field_errors === array('transactionType' => 'This field is required.') && $a->plans_calls === array(), "feed $label transactionType is a required error");
}

$a = $feed_run(array('transactionType' => 'donation'), array(), 'ok', null, array('name' => 'transactionType'));
gf_paystack_test_check($a->field_errors === array('transactionType' => 'This field is required.'), 'feed without choices accepts only product or subscription');
$a = $feed_run(array('transactionType' => 'product'), array(), 'ok', null, array('name' => 'transactionType'));
gf_paystack_test_check($a->field_errors === array(), 'feed without choices accepts product');

// Feed: probe only when something changed.
$stored_feed = function ($type, $mode) {
    return array('id' => 4, 'meta' => array('transactionType' => $type, 'mode' => $mode));
};
$with_current = function ($current) {
    return function ($addon) use ($current) {
        $addon->current_feed = $current;
    };
};

$a = $feed_run($sub_test, array('test_secret_key' => $st_rk_test), 'plans_missing', $with_current($stored_feed('subscription', 'test')));
gf_paystack_test_check($a->field_errors === array() && $a->plans_calls === array(), 'feed unchanged subscription feed is not probed');

$a = $feed_run($sub_test, array('test_secret_key' => $st_rk_test, 'live_secret_key' => $st_rk_live), 'plans_missing', $with_current($stored_feed('subscription', 'live')));
gf_paystack_test_check(isset($a->field_errors['transactionType']) && $a->plans_calls === array($st_rk_test), 'feed changed mode is probed');

$a = $feed_run($sub_test, array('test_secret_key' => $st_rk_test), 'ok', $with_current($stored_feed('product', 'test')));
gf_paystack_test_check($a->plans_calls === array($st_rk_test), 'feed changed to subscription is probed');

$a = $feed_run(array('transactionType' => 'subscription'), array('live_secret_key' => $st_rk_live), 'ok', $with_current($stored_feed('subscription', 'test')));
gf_paystack_test_check($a->plans_calls === array($st_rk_live), 'feed posted without mode compares as live');

$a = $feed_run(array('transactionType' => 'subscription'), array('live_secret_key' => $st_rk_live), 'plans_missing', $with_current($stored_feed('subscription', 'live')));
gf_paystack_test_check($a->plans_calls === array(), 'feed unchanged live default is not probed');

// Feed: wiring and chaining.
class GFPaystackFeedWiringFixture extends GFPaystackSettingsFixture
{
    public $parent_calls = 0;

    public function parent_callback($field)
    {
        $this->parent_calls++;
    }

    public function feed_settings_fields()
    {
        return parent::feed_settings_fields();
    }
}

$wired = new GFPaystackFeedWiringFixture();
$wired_fields = $wired->feed_settings_fields();
$wired_type = $wired->get_field('transactionType', $wired_fields);
gf_paystack_test_check(is_array($wired_type) && $wired_type['validation_callback'] === array($wired, 'validate_subscription_plans'), 'feed_settings_fields wires validate_subscription_plans onto transactionType');

class GFPaystackFeedChainFixture extends GFPaystackFeedWiringFixture
{
    public function get_field($name, $fields)
    {
        $field = parent::get_field($name, $fields);
        if ($name === 'transactionType' && is_array($field) && !isset($field['validation_callback'])) {
            $field['validation_callback'] = array($this, 'parent_callback');
        }

        return $field;
    }
}

$chained = new GFPaystackFeedChainFixture();
$chained->posted = array('transactionType' => 'product');
$chained_fields = $chained->feed_settings_fields();
$chained_type = $chained->get_field('transactionType', $chained_fields);
$chained->validate_subscription_plans($chained_type);
gf_paystack_test_check($chained->parent_calls === 1 && $chained->field_errors === array(), 'feed existing transactionType callback is still called');

$GLOBALS['gf_paystack_test_saved_settings'] = null;
$st_saver = new GFPaystackSettingsFixture();
$st_saver->update_plugin_settings(array('test_secret_key' => "  $st_rk_test\n", 'live_webhook_secret' => " $st_ws_live ", 'api_mode' => ' test '));
$st_saved = $GLOBALS['gf_paystack_test_saved_settings'];
gf_paystack_test_check($st_saved['test_secret_key'] === $st_rk_test && $st_saved['live_webhook_secret'] === $st_ws_live && $st_saved['api_mode'] === ' test ', 'settings save trims only credential fields');

// # Coverage additions (dedicated test pass)

// Credentials are validated from the always-visible Mode field, never from fields a dependency can hide.
$wiring_ok = $by_name['api_mode']['validation_callback'] === array($fa, 'validate_credentials');
foreach (array('test_secret_key', 'live_secret_key', 'test_webhook_secret', 'live_webhook_secret') as $field_name) {
    $wiring_ok = $wiring_ok && !isset($by_name[$field_name]['validation_callback']);
}
gf_paystack_test_check($wiring_ok, 'settings credentials are validated from the Mode field');

// Each callback validates its own mode.
$a = $st_run('test', array('test_secret_key' => $st_sk_live, 'live_secret_key' => $st_sk_test));
gf_paystack_test_check($a->error_log === array('test_secret_key'), 'settings test key callback only reports the test field');
$a = $st_run('live', array('test_secret_key' => $st_sk_live, 'live_secret_key' => $st_sk_test));
gf_paystack_test_check($a->error_log === array('live_secret_key'), 'settings live key callback only reports the live field');
$a = $st_run('test', array('test_webhook_secret' => 'nope', 'live_webhook_secret' => 'nope'), array(), null, 'whsec');
gf_paystack_test_check($a->error_log === array('test_webhook_secret'), 'settings test whsec callback only reports the test field');
$a = $st_run('live', array('test_webhook_secret' => 'nope', 'live_webhook_secret' => 'nope'), array(), null, 'whsec');
gf_paystack_test_check($a->error_log === array('live_webhook_secret'), 'settings live whsec callback only reports the live field');

// The mirror image: each mode's rk probe uses its own mode's whsec and stored key.
$a = $st_run('live', array('live_secret_key' => $st_rk_live, 'live_webhook_secret' => $st_ws_live, 'test_webhook_secret' => $st_ws_live));
gf_paystack_test_check(isset($a->field_errors['live_webhook_secret']) && $a->validate_calls === array(), 'settings whsec_same is judged against the other mode and blocks the probe');
$a = $st_run('live', array('live_secret_key' => $st_rk_live, 'live_webhook_secret' => $st_ws_live, 'test_webhook_secret' => $st_ws_test), array('live_secret_key' => $st_rk_test));
gf_paystack_test_check($a->validate_calls === array($st_rk_live), 'settings a changed key is compared against this mode stored key');
$a = $st_run('live', array('live_secret_key' => $st_rk_live, 'live_webhook_secret' => $st_ws_live), array('test_secret_key' => $st_rk_live));
gf_paystack_test_check($a->validate_calls === array($st_rk_live), 'settings stored key of the other mode does not make this key unchanged');

// Feed choices drive the allowed transaction types.
$custom_field = array('name' => 'transactionType', 'choices' => array(array('label' => 'Donation', 'value' => 'donation'), array('label' => 'Sub', 'value' => 'subscription')));
$a = $feed_run(array('transactionType' => 'donation'), array(), 'ok', null, $custom_field);
gf_paystack_test_check($a->field_errors === array(), 'feed accepts a type that is only in the field choices');
$a = $feed_run(array('transactionType' => 'product'), array(), 'ok', null, $custom_field);
gf_paystack_test_check($a->field_errors === array('transactionType' => 'This field is required.'), 'feed rejects product when the field choices do not offer it');
$name_field = array('name' => 'transactionType', 'choices' => array(array('label' => 'Donation', 'name' => 'donation')));
$a = $feed_run(array('transactionType' => 'donation'), array(), 'ok', null, $name_field);
gf_paystack_test_check($a->field_errors === array(), 'feed choice without a value falls back to its name');

// Non-array settings pass through untouched.
$GLOBALS['gf_paystack_test_saved_settings'] = 'unset';
$st_saver->update_plugin_settings(null);
gf_paystack_test_check($GLOBALS['gf_paystack_test_saved_settings'] === null, 'settings save passes non-array settings through');

// get_webhook_secret / get_stored_secret_key read per-mode settings and trim.
$st_reader = new GFPaystackSettingsFixture();
$st_reader->stored = array('test_webhook_secret' => " $st_ws_test ", 'live_webhook_secret' => $st_ws_live, 'test_secret_key' => " $st_sk_test\n", 'live_secret_key' => $st_rk_live);
gf_paystack_test_check($st_reader->get_webhook_secret('test') === $st_ws_test && $st_reader->get_webhook_secret('live') === $st_ws_live && $st_reader->get_webhook_secret('live', array()) === $st_ws_live, 'settings get_webhook_secret is per mode, trimmed, and falls back to plugin settings');
gf_paystack_test_check($st_reader->get_stored_secret_key('test') === $st_sk_test && $st_reader->get_stored_secret_key('live', array('live_secret_key' => 'x')) === 'x', 'settings get_stored_secret_key is per mode, trimmed, and prefers passed settings');
gf_paystack_test_check($st_reader->get_webhook_secret('test', array('test_webhook_secret' => $st_ws_live)) === $st_ws_live && (new GFPaystackSettingsFixture())->get_webhook_secret('test') === '', 'settings get_webhook_secret prefers passed settings and defaults to blank');

// Feed: callback arguments, object fields and the global-mode default.
class GFPaystackFeedArgsFixture extends GFPaystackFeedWiringFixture
{
    public $parent_args = array();

    public function set_transaction_type_callback($callback)
    {
        $this->transaction_type_callback = $callback;
    }

    public function parent_callback($field, $value = null)
    {
        $this->parent_args = func_get_args();
    }
}

$args_addon = new GFPaystackFeedArgsFixture();
$args_addon->posted = array('transactionType' => 'product');
$args_fields = $args_addon->feed_settings_fields();
$args_addon->set_transaction_type_callback(array($args_addon, 'parent_callback'));
$args_addon->validate_subscription_plans($feed_field, 'product');
gf_paystack_test_check(count($args_addon->parent_args) === 2 && $args_addon->parent_args[1] === 'product', 'feed existing transactionType callback receives both arguments');

$obj_field = (object) array('name' => 'transactionType', 'choices' => array(array('label' => 'Subscription', 'value' => 'subscription')));
$a = $feed_run(array('transactionType' => 'product'), array(), 'ok', null, $obj_field);
gf_paystack_test_check($a->field_errors === array('transactionType' => 'This field is required.'), 'feed object field choices are enforced');
$a = $feed_run(array('transactionType' => 'subscription', 'mode' => 'test'), array('test_secret_key' => $st_rk_test), 'ok', null, $obj_field);
gf_paystack_test_check($a->field_errors === array() && $a->plans_calls === array($st_rk_test), 'feed object field accepts a listed choice');

$a = $feed_run(array('transactionType' => 'subscription'), array('api_mode' => 'test', 'test_secret_key' => $st_rk_test, 'live_secret_key' => $st_rk_live));
gf_paystack_test_check($a->plans_calls === array($st_rk_test), 'feed without a mode follows the global api mode');
