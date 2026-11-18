<?php

require_once dirname(__DIR__) . '/includes/class-gf-paystack-keys.php';

$rk_live = 'rk_live_' . str_repeat('a1B2', 8) . '-_.~!';
$rk_test = 'rk_test_' . str_repeat('a1B2', 8);
$sk_live = 'sk_live_abc123XYZ';
$sk_test = 'sk_test_abc123XYZ';
$whsec_a = 'whsec_' . str_repeat('a', 32);
$whsec_b = 'whsec_' . str_repeat('b', 32);

gf_paystack_test_check(GF_Paystack_Keys::type($sk_live) === 'sk' && GF_Paystack_Keys::type($rk_test) === 'rk', 'keys type case 1');
gf_paystack_test_check(GF_Paystack_Keys::type('pk_live_x') === null && GF_Paystack_Keys::type(null) === null, 'keys type case 2');
gf_paystack_test_check(GF_Paystack_Keys::mode($sk_test) === 'test' && GF_Paystack_Keys::mode($rk_live) === 'live' && GF_Paystack_Keys::mode('nope') === null, 'keys mode case 1');
gf_paystack_test_check(GF_Paystack_Keys::is_whsec_shaped($whsec_a) && !GF_Paystack_Keys::is_whsec_shaped('whsec_short') && !GF_Paystack_Keys::is_whsec_shaped('whsec_' . str_repeat('a', 20) . '+'), 'keys whsec shape');
gf_paystack_test_check(GF_Paystack_Keys::restricted_keys_enabled() === true, 'keys gate default on');

$keys_cases = array(
    'blank key' => array(array('live', '', $sk_live, '', '', true), false, null),
    'blank key, stored rk, no whsec' => array(array('live', '', $rk_live, '', '', true), false, null),
    'unchanged odd shape' => array(array('live', 'weird-key', 'weird-key', '', '', true), false, null),
    'unchanged wrong field' => array(array('live', $sk_test, $sk_test, '', '', true), false, null),
    'unchanged with whitespace' => array(array('live', "  $sk_live ", $sk_live, '', '', true), false, null),
    'new sk' => array(array('live', $sk_live, '', '', '', true), false, null),
    'new sk other mode' => array(array('live', $sk_test, '', '', '', true), false, 'wrong_field'),
    'new rk other mode' => array(array('test', $rk_live, '', $whsec_a, '', true), false, 'wrong_field'),
    'new key bad shape' => array(array('live', 'sk_live_', '', '', '', true), false, 'invalid_format'),
    'new key unknown prefix' => array(array('live', 'pk_live_abc', '', '', '', true), false, 'invalid_format'),
    'new rk too short' => array(array('live', 'rk_live_short', '', $whsec_a, '', true), false, 'invalid_format'),
    'new rk with whsec' => array(array('live', $rk_live, '', $whsec_a, $whsec_b, true), true, null),
    'new rk gate off' => array(array('live', $rk_live, '', $whsec_a, '', false), false, 'rk_disabled'),
    'kept rk gate off' => array(array('live', $rk_live, $rk_live, $whsec_a, '', false), false, null),
    'new rk without whsec' => array(array('live', $rk_live, '', '', '', true), false, 'whsec_required'),
    'kept rk without whsec' => array(array('live', $rk_live, $rk_live, '', '', true), false, 'whsec_required'),
    'new sk without whsec' => array(array('live', $sk_live, '', '', '', true), false, null),
    'sk with whsec' => array(array('live', $sk_live, $sk_live, $whsec_a, '', true), false, null),
    'whsec same as other mode' => array(array('live', $sk_live, $sk_live, $whsec_a, $whsec_a, true), false, 'whsec_same'),
    'whsec bad shape' => array(array('live', $sk_live, $sk_live, 'whsec_x', '', true), false, 'whsec_format'),
    'whsec bad shape with blank key' => array(array('live', '', '', 'nope', '', true), false, 'whsec_format'),
    'key error before whsec error' => array(array('live', $sk_test, '', 'nope', '', true), false, 'wrong_field'),
);
foreach ($keys_cases as $label => $case) {
    $result = call_user_func_array(array('GF_Paystack_Keys', 'resolve_save'), $case[0]);
    gf_paystack_test_check($result === array('probe' => $case[1], 'error' => $case[2]), 'keys resolve_save ' . $label);
}

$keys_redact_cases = array(
    'punctuation rk' => array('key rk_live_ab-c.d~e!f_g tail', 'key rk_live_[redacted] tail'),
    'base64 whsec' => array('whsec_ab+/cd==ef rest', 'whsec_[redacted] rest'),
    'query string' => array('x=1&secret=sk_live_abc&y=2', 'x=1&secret=sk_live_[redacted]&y=2'),
    'json escaped' => array('{"key":"rk_live_abc123"}', '{"key":"rk_live_[redacted]"}'),
    'url encoded' => array('secret%3Dsk_live_abc%26y', 'secret%3Dsk_live_[redacted]'),
    'pk' => array('pk_test_abc123', 'pk_test_[redacted]'),
    'no secrets' => array('Payment verified for ref gf-9 task_live_x', 'Payment verified for ref gf-9 task_live_x'),
);
foreach ($keys_redact_cases as $label => $case) {
    gf_paystack_test_check(GF_Paystack_Keys::redact($case[0]) === $case[1], 'keys redact ' . $label);
}
gf_paystack_test_check(GF_Paystack_Keys::redact(array('sk_live_abc')) === array('sk_live_abc') && GF_Paystack_Keys::redact(null) === null, 'keys redact non-strings');
