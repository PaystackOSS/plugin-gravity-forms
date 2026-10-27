<?php
if (PHP_SAPI !== 'cli') {
    exit;
}

define('ABSPATH', dirname(__DIR__) . '/');

require __DIR__ . '/stubs.php';

$GLOBALS['gf_paystack_test_passed'] = 0;
$GLOBALS['gf_paystack_test_failed'] = 0;

function gf_paystack_test_check($condition, $label)
{
    if ($condition) {
        $GLOBALS['gf_paystack_test_passed']++;
        echo "ok   - $label\n";
    } else {
        $GLOBALS['gf_paystack_test_failed']++;
        echo "FAIL - $label\n";
    }
}

$test_files = glob(__DIR__ . '/test-*.php');
sort($test_files);
foreach ($test_files as $test_file) {
    require $test_file;
}

$passed = $GLOBALS['gf_paystack_test_passed'];
$failed = $GLOBALS['gf_paystack_test_failed'];
echo "\n$passed passed, $failed failed\n";

exit(($failed > 0 || $passed + $failed === 0) ? 1 : 0);
