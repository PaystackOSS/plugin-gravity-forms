<?php
$gf_paystack_root = dirname(__DIR__) . '/';
$gf_paystack_main = file_get_contents($gf_paystack_root . 'paystack.php');
$gf_paystack_readme = file_get_contents($gf_paystack_root . 'readme.txt');

preg_match('/^Version:[ \t]*(\S+)[ \t]*\r?$/m', $gf_paystack_main, $header);
preg_match("/^define\\('GF_PAYSTACK_VERSION',\\s*'([^']+)'\\);/m", $gf_paystack_main, $constant);
preg_match('/^Stable tag:[ \t]*(\S+)[ \t]*\r?$/m', $gf_paystack_readme, $stable);
preg_match('/^Requires PHP:[ \t]*(\S+)[ \t]*\r?$/m', $gf_paystack_readme, $requires_php);

$gf_paystack_header = isset($header[1]) ? $header[1] : null;
$gf_paystack_constant = isset($constant[1]) ? $constant[1] : null;
$gf_paystack_stable = isset($stable[1]) ? $stable[1] : null;
$gf_paystack_requires = isset($requires_php[1]) ? $requires_php[1] : '';

gf_paystack_test_check(
    $gf_paystack_header !== null
        && $gf_paystack_header === $gf_paystack_constant
        && $gf_paystack_header === $gf_paystack_stable,
    'version case 1: plugin header, GF_PAYSTACK_VERSION and readme Stable tag match'
);

gf_paystack_test_check(
    preg_match('/^\d+\.\d+(\.\d+)?$/', $gf_paystack_requires) === 1,
    'version case 2: readme Requires PHP is a bare version'
);
