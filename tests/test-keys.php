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

$keys_cases = array(
    'blank key' => array(array('live', '', $sk_live, '', ''), false, null),
    'blank key, stored rk, no whsec' => array(array('live', '', $rk_live, '', ''), false, null),
    'unchanged odd shape' => array(array('live', 'weird-key', 'weird-key', '', ''), false, null),
    'unchanged wrong field' => array(array('live', $sk_test, $sk_test, '', ''), false, null),
    'unchanged with whitespace' => array(array('live', "  $sk_live ", $sk_live, '', ''), false, null),
    'new sk refused' => array(array('live', $sk_live, '', '', ''), false, 'secret_key'),
    'new sk other mode refused' => array(array('live', $sk_test, '', '', ''), false, 'secret_key'),
    'changed sk refused' => array(array('live', 'sk_live_rotated999', $sk_live, '', ''), false, 'secret_key'),
    'stored sk replaced by rk' => array(array('live', $rk_live, $sk_live, $whsec_a, ''), true, null),
    'new rk other mode' => array(array('test', $rk_live, '', $whsec_a, ''), false, 'wrong_field'),
    'new key bad shape' => array(array('live', 'rk_live_', '', $whsec_a, ''), false, 'invalid_format'),
    'new key unknown prefix' => array(array('live', 'pk_live_abc', '', '', ''), false, 'invalid_format'),
    'new rk too short' => array(array('live', 'rk_live_short', '', $whsec_a, ''), false, 'invalid_format'),
    'new rk with whsec' => array(array('live', $rk_live, '', $whsec_a, $whsec_b), true, null),
    'new rk without whsec' => array(array('live', $rk_live, '', '', ''), false, 'whsec_required'),
    'kept rk without whsec' => array(array('live', $rk_live, $rk_live, '', ''), false, 'whsec_required'),
    'kept sk without whsec' => array(array('live', $sk_live, $sk_live, '', ''), false, null),
    'sk with whsec' => array(array('live', $sk_live, $sk_live, $whsec_a, ''), false, null),
    'whsec same as other mode' => array(array('live', $sk_live, $sk_live, $whsec_a, $whsec_a), false, 'whsec_same'),
    'whsec bad shape' => array(array('live', $sk_live, $sk_live, 'whsec_x', ''), false, 'whsec_format'),
    'whsec bad shape with blank key' => array(array('live', '', '', 'nope', ''), false, 'whsec_format'),
    'key error before whsec error' => array(array('live', $rk_test, '', 'nope', ''), false, 'wrong_field'),
);
foreach ($keys_cases as $label => $case) {
    $result = call_user_func_array(array('GF_Paystack_Keys', 'resolve_save'), $case[0]);
    gf_paystack_test_check($result === array('probe' => $case[1], 'error' => $case[2]), 'keys resolve_save ' . $label);
}

$keys_redact_cases = array(
    'punctuation rk' => array('key rk_live_ab-c.d~e!f_g tail', 'key rk_live_[redacted] tail'),
    'base64 whsec' => array('whsec_ab+/cd==ef rest', 'whsec_[redacted] rest'),
    'query string' => array('x=1&secret=sk_live_abc&y=2', 'x=1&secret=sk_live_[redacted]'),
    'rk with &,; chars' => array('k=rk_live_ab&cd,ef;gh_ijklmnop tail', 'k=rk_live_[redacted] tail'),
    'json escaped' => array('{"key":"rk_live_abc123"}', '{"key":"rk_live_[redacted]"}'),
    'json escaped slash tail' => array('{"k":"rk_live_abcdefghij\\/klmnopTAIL"}', '{"k":"rk_live_[redacted]"}'),
    'url encoded' => array('secret%3Dsk_live_abc%26y', 'secret%3Dsk_live_[redacted]'),
    'pk' => array('pk_test_abc123', 'pk_test_[redacted]'),
    'no secrets' => array('Payment verified for ref gf-9 task_live_x', 'Payment verified for ref gf-9 task_live_x'),
);
foreach ($keys_redact_cases as $label => $case) {
    gf_paystack_test_check(GF_Paystack_Keys::redact($case[0]) === $case[1], 'keys redact ' . $label);
}
gf_paystack_test_check(GF_Paystack_Keys::redact(array('sk_live_abc')) === array('sk_live_abc') && GF_Paystack_Keys::redact(null) === null, 'keys redact non-strings');

// # Coverage additions (dedicated test pass)
gf_paystack_test_check(GF_Paystack_Keys::mode('sk_livefoo') === null && GF_Paystack_Keys::type('sk_livefoo') === null, 'keys prefix needs the trailing underscore');
$keys_extra = array(
    'prefix without trailing underscore' => array(array('test', 'sk_livefoo', '', '', ''), false, 'invalid_format'),
    'rk body at 256 chars' => array(array('live', 'rk_live_' . str_repeat('a', 256), '', $whsec_a, ''), true, null),
    'rk body over 256 chars' => array(array('live', 'rk_live_' . str_repeat('a', 257), '', $whsec_a, ''), false, 'invalid_format'),
    'rk at 16 chars' => array(array('live', 'rk_live_' . str_repeat('a', 16), '', $whsec_a, ''), true, null),
    'rk at 15 chars' => array(array('live', 'rk_live_' . str_repeat('a', 15), '', $whsec_a, ''), false, 'invalid_format'),
    'blank whsec on both sides is not a duplicate' => array(array('live', $sk_live, $sk_live, '', ''), false, null),
    'whsec at 16 chars' => array(array('live', $sk_live, $sk_live, 'whsec_' . str_repeat('a', 16), ''), false, null),
    'whsec at 15 chars' => array(array('live', $sk_live, $sk_live, 'whsec_' . str_repeat('a', 15), ''), false, 'whsec_format'),
    'whitespace-only other whsec is blank' => array(array('live', $sk_live, $sk_live, $whsec_a, '   '), false, null),
    'rk containing a double quote' => array(array('live', 'rk_live_abcdefgh"ijklmnopqr', '', $whsec_a, ''), false, 'invalid_format'),
    'rk containing a backslash' => array(array('live', 'rk_live_abcdefgh\\ijklmnopqr', '', $whsec_a, ''), false, 'invalid_format'),
    'rk containing a less-than' => array(array('live', 'rk_live_abcdefgh<ijklmnopqr', '', $whsec_a, ''), false, 'invalid_format'),
    'rk containing a single quote' => array(array('live', "rk_live_abcdefgh'ijklmnopqr", '', $whsec_a, ''), false, 'invalid_format'),
    'rk error reports no probe' => array(array('live', $rk_live, '', '', ''), false, 'whsec_required'),
);
foreach ($keys_extra as $label => $case) {
    $result = call_user_func_array(array('GF_Paystack_Keys', 'resolve_save'), $case[0]);
    gf_paystack_test_check($result === array('probe' => $case[1], 'error' => $case[2]), 'keys resolve_save ' . $label);
}

gf_paystack_test_check(GF_Paystack_Keys::redact('a sk_live_abc and rk_test_def and whsec_ghi') === 'a sk_live_[redacted] and rk_test_[redacted] and whsec_[redacted]', 'keys redact several secrets in one string');
gf_paystack_test_check(GF_Paystack_Keys::redact('sk_live_abc"') === 'sk_live_[redacted]"', 'keys redact stops at a closing quote');
