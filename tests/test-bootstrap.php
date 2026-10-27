<?php

require_once dirname(__DIR__) . '/paystack.php';

gf_paystack_test_check(defined('GF_PAYSTACK_VERSION'), 'bootstrap: GF_PAYSTACK_VERSION is defined');
gf_paystack_test_check(function_exists('gf_paystack_php_advisory_notice'), 'bootstrap: advisory notice is loaded');
gf_paystack_test_check(class_exists('GF_Paystack_Bootstrap'), 'bootstrap: GF_Paystack_Bootstrap is declared');
