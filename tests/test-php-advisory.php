<?php

require_once dirname(__DIR__) . '/includes/php-advisory.php';

$gf_paystack_vulnerable_versions = array(
    '5.6.40', '7.2.0', '8.1.30', '8.2.34', '8.3.35', '8.4.26', '8.5.11',
    '8.3.0RC1', '8.4.0RC1', '8.2.35-dev',
);
$gf_paystack_safe_versions = array(
    '8.2.35', '8.3.36', '8.4.27', '8.5.12', '8.6.0', '9.0.0',
    '8.3.36-1+ubuntu22.04.1+deb.sury.org+1',
);

foreach ($gf_paystack_vulnerable_versions as $version) {
    gf_paystack_test_check(gf_paystack_php_is_vulnerable($version) === true, "php advisory: $version is vulnerable");
}
foreach ($gf_paystack_safe_versions as $version) {
    gf_paystack_test_check(gf_paystack_php_is_vulnerable($version) === false, "php advisory: $version is not vulnerable");
}
gf_paystack_test_check(gf_paystack_php_is_vulnerable('abc') === false, 'php advisory: unparseable version is not flagged');

function gf_paystack_test_notice_output($caps, $multisite, $screen, $version = '8.1.30')
{
    global $gf_paystack_test_caps, $gf_paystack_test_multisite, $gf_paystack_test_screen;
    $gf_paystack_test_caps = $caps;
    $gf_paystack_test_multisite = $multisite;
    $gf_paystack_test_screen = $screen;

    ob_start();
    gf_paystack_php_advisory_notice($version);
    $output = ob_get_clean();

    $gf_paystack_test_caps = null;
    $gf_paystack_test_multisite = false;
    $gf_paystack_test_screen = null;

    return $output;
}

gf_paystack_test_check(gf_paystack_test_notice_output(array(), false, 'dashboard') === '', 'php advisory notice: hidden without capability');
gf_paystack_test_check(gf_paystack_test_notice_output(array('manage_options'), false, 'edit-post') === '', 'php advisory notice: hidden on other screens');
gf_paystack_test_check(gf_paystack_test_notice_output(array('manage_options'), false, null) === '', 'php advisory notice: hidden without a screen');
gf_paystack_test_check(gf_paystack_test_notice_output(array('manage_options'), true, 'dashboard-network') === '', 'php advisory notice: multisite requires manage_network_options');

$gf_paystack_cases = array(
    'single site on dashboard' => array(array('manage_options'), false, 'dashboard'),
    'single site on plugins' => array(array('manage_options'), false, 'plugins'),
    'multisite on network plugins' => array(array('manage_network_options'), true, 'plugins-network'),
);
foreach ($gf_paystack_cases as $label => $case) {
    $output = gf_paystack_test_notice_output($case[0], $case[1], $case[2], '8.1.30');
    gf_paystack_test_check(strpos($output, 'notice-warning') !== false && strpos($output, '8.1.30') !== false, "php advisory notice: shown for $label on vulnerable PHP");
    $output = gf_paystack_test_notice_output($case[0], $case[1], $case[2], '8.5.12');
    gf_paystack_test_check($output === '', "php advisory notice: hidden for $label on patched PHP");
}

$output = gf_paystack_test_notice_output(array('manage_options'), false, 'dashboard', '5.6<b>');
gf_paystack_test_check(strpos($output, '<b>') === false && strpos($output, '5.6&lt;b&gt;') !== false, 'php advisory notice: version is escaped');
