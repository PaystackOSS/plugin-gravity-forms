<?php

require_once dirname(__DIR__) . '/includes/class-gf-paystack-restricted-key.php';

if (!class_exists('GF_Paystack_Restricted_Key_Spent')) {
    class GF_Paystack_Restricted_Key_Spent extends GF_Paystack_Restricted_Key
    {
        protected static $budget_seconds = 0.05;
    }
}

if (!class_exists('GF_Paystack_Restricted_Key_Zero')) {
    class GF_Paystack_Restricted_Key_Zero extends GF_Paystack_Restricted_Key
    {
        protected static $budget_seconds = 0.0;
    }
}

GF_Paystack_Restricted_Key::reset_budget();
$rk_key = 'rk_live_' . str_repeat('Zq9', 10);
$scope_msg = 'This restricted key is not permitted to perform this action';
$write_msg = 'Sorry, this feature is no longer available.';
$mor_msg = 'This feature is not available to merchant of record integrations.';

// $map: scope => status | array(status, message); unlisted scopes get $default.
$rk_make = function ($map, $default = array(403, 'This restricted key is not permitted to perform this action'), &$log = null) {
    $log = array();

    return function ($method, $url, $headers, $body) use ($map, $default, &$log) {
        $path = substr($url, strlen('https://api.paystack.co/'));
        $scope = null;
        foreach (GF_Paystack_Restricted_Key::PROBES as $p) {
            if ($p['path'] === $path && $p['method'] === $method) {
                $scope = $p['scope'];
            }
        }
        $log[] = $scope;
        $r = array_key_exists($scope, $map) ? $map[$scope] : $default;
        if ($r instanceof Exception) {
            throw $r;
        }
        $r = (array) $r;

        return array('status' => $r[0], 'body' => isset($r[1]) ? array('status' => false, 'message' => $r[1]) : null);
    };
};

$rk_ok_map = array('transactions:read' => 200, 'transactions:write' => array(404, $write_msg));
$rk_expected_scopes = array('transactions:read', 'transactions:write', 'disputes_refunds', 'bulk_charges', 'direct_debit', 'preauthorizations', 'settlements', 'subaccounts', 'transfers', 'virtual_accounts', 'webhooks', 'connect', 'payouts');

$scope_list = array();
foreach (GF_Paystack_Restricted_Key::PROBES as $p) {
    $scope_list[] = $p['scope'];
}
gf_paystack_test_check($scope_list === $rk_expected_scopes, 'rk probe table scope list and order pinned');
$labels_ok = true;
foreach (GF_Paystack_Restricted_Key::PROBES as $p) {
    $labels_ok = $labels_ok && isset($p['label']) && is_string($p['label']) && $p['label'] !== '' && GF_Paystack_Restricted_Key::scope_label($p['scope']) === $p['label'];
}
gf_paystack_test_check($labels_ok && GF_Paystack_Restricted_Key::scope_label('payouts') === 'Payouts' && GF_Paystack_Restricted_Key::scope_label('unknown') === 'unknown', 'rk every probe has a label and scope_label falls back to the raw scope');
gf_paystack_test_check(GF_Paystack_Restricted_Key::PROBES[1]['path'] === 'transaction/check_authorization' && GF_Paystack_Restricted_Key::PROBES[1]['method'] === 'POST', 'rk write probe route pinned');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map, array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'ok' && $v['reason'] === null && $v['scopes'] === array() && $v['sensitive_scopes'] === array() && count($log) === 13, 'rk ok when baseline and write present and sensitive absent');
gf_paystack_test_check(strpos($v['probes'], 'transactions:read:200,transactions:write:404,disputes_refunds:403') === 0 && strpos($v['probes'], $rk_key) === false, 'rk probes string has scope:status and no key');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => array(401, 'Invalid key')), array(401), $log));
gf_paystack_test_check($v['code'] === 'invalid_key' && $log === array('transactions:read'), 'rk baseline 401 is invalid_key and stops');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array(), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'missing_scope' && $v['scopes'] === array('transactions:read', 'transactions:write') && count($log) === 13, 'rk missing read and write when all absent');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:write' => array(404, $write_msg)) + array('transactions:read' => array(403, $scope_msg)), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'missing_scope' && $v['scopes'] === array('transactions:read'), 'rk missing read only');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => 200, 'transactions:write' => 404), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && $log === array('transactions:read', 'transactions:write'), 'rk write 404 without exact message is unverifiable and stops');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => 200, 'transactions:write' => array(404, 'Not found')), array(403, $scope_msg)));
gf_paystack_test_check($v['code'] === 'unverifiable', 'rk write 404 with other message is unverifiable');

foreach (array(200, 400) as $status) {
    $v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('settlements' => $status, 'payouts' => $status), array(403, $scope_msg), $log));
    gf_paystack_test_check($v['code'] === 'sensitive_scope' && $v['sensitive_scopes'] === array('settlements', 'payouts') && $v['scopes'] === array(), "rk sensitive present on $status");
}

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('transfers' => 401), array(403, $scope_msg)));
gf_paystack_test_check($v['code'] === 'sensitive_scope' && $v['sensitive_scopes'] === array('transfers'), 'rk 401 on sensitive after 2xx baseline is present');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => array(403, $scope_msg), 'transactions:write' => array(404, $write_msg), 'disputes_refunds' => 401), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'missing_scope' && end($log) === 'disputes_refunds', 'rk 401 on sensitive after absent baseline is not present and stops');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('direct_debit' => array(403, 'IP not allowed')), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'blocked' && end($log) === 'direct_debit', 'rk other 403 is blocked and probing stops');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('bulk_charges' => 429), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && end($log) === 'bulk_charges', 'rk 429 is transient');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('subaccounts' => 503), array(403, $scope_msg)));
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient', 'rk 5xx is transient');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('connect' => 302), array(403, $scope_msg)));
gf_paystack_test_check($v['code'] === 'unverifiable', 'rk 3xx is unverifiable');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('webhooks' => 0), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && end($log) === 'webhooks', 'rk status 0 on sensitive probe is unverifiable');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('settlements' => 200, 'transfers' => 429), array(403, $scope_msg)));
gf_paystack_test_check($v['code'] === 'sensitive_scope', 'rk proven sensitive scope wins over a later indeterminate');

$v = GF_Paystack_Restricted_Key::validate($rk_key, function () {
    throw new RuntimeException('boom');
});
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient', 'rk throwing request is unverifiable transient');

$v = GF_Paystack_Restricted_Key::validate($rk_key, function () {
    return 'garbage';
});
gf_paystack_test_check($v['code'] === 'unverifiable', 'rk malformed response is unverifiable');

GF_Paystack_Restricted_Key::reset_budget();
$calls = 0;
$v = GF_Paystack_Restricted_Key_Zero::validate($rk_key, function () use (&$calls) {
    $calls++;

    return array('status' => 200, 'body' => null);
});
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && $calls === 0 && $v['probes'] === '-', 'rk exhausted budget stops before any probe');

GF_Paystack_Restricted_Key::reset_budget();
gf_paystack_test_check(GF_Paystack_Restricted_Key_Zero::probe_plans($rk_key, function () use (&$calls) {
    $calls++;

    return array('status' => 400, 'body' => null);
}) === 'unverifiable' && $calls === 0, 'rk probe_plans after exhausted budget is unverifiable without a request');

GF_Paystack_Restricted_Key::reset_budget();
$slow = $rk_make($rk_ok_map, array(403, $scope_msg), $log);
$v = GF_Paystack_Restricted_Key_Spent::validate($rk_key, function ($m, $u, $h, $b) use ($slow) {
    usleep(30000);

    return $slow($m, $u, $h, $b);
});
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && strpos($v['probes'], 'transactions:read:200') === 0, 'rk budget exhausted mid-run is unverifiable transient');

// A new window starts once the old one has been over for more than 60s.
if (!class_exists('GF_Paystack_Restricted_Key_Clocked')) {
    class GF_Paystack_Restricted_Key_Clocked extends GF_Paystack_Restricted_Key
    {
        public static $time = 1000.0;
        protected static $budget_seconds = 5.0;

        protected static function now()
        {
            return static::$time;
        }
    }
}
GF_Paystack_Restricted_Key::reset_budget();
$clock_calls = 0;
$clock_request = function () use (&$clock_calls) {
    $clock_calls++;

    return array('status' => 200, 'body' => null);
};
GF_Paystack_Restricted_Key_Clocked::$time = 1000.0;
GF_Paystack_Restricted_Key_Clocked::validate($rk_key, $clock_request);
$first_calls = $clock_calls;
GF_Paystack_Restricted_Key_Clocked::$time = 1010.0;
$clock_calls = 0;
$v = GF_Paystack_Restricted_Key_Clocked::validate($rk_key, $clock_request);
gf_paystack_test_check($first_calls > 0 && $clock_calls === 0 && $v['code'] === 'unverifiable', 'rk budget stays spent shortly after the window ends');
GF_Paystack_Restricted_Key_Clocked::$time = 1066.0;
$v = GF_Paystack_Restricted_Key_Clocked::validate($rk_key, $clock_request);
gf_paystack_test_check($clock_calls > 0, 'rk budget opens a new window once the old one is over by more than 60s');
GF_Paystack_Restricted_Key::reset_budget();

// Budget is shared across calls: a second validate() in the same request gets what the first left.
GF_Paystack_Restricted_Key::reset_budget();
$slow_ok = function ($m, $u, $h, $b) use ($rk_make, $rk_ok_map, $scope_msg) {
    usleep(30000);
    $f = $rk_make($rk_ok_map, array(403, $scope_msg));

    return $f($m, $u, $h, $b);
};
GF_Paystack_Restricted_Key_Spent::validate($rk_key, $slow_ok);
$calls = 0;
$v = GF_Paystack_Restricted_Key_Spent::validate($rk_key, function () use (&$calls) {
    $calls++;

    return array('status' => 200, 'body' => null);
});
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && $calls === 0, 'rk second validate shares the exhausted budget');
GF_Paystack_Restricted_Key::reset_budget();

// probe_plans
$plan = function ($status, $message = null) {
    return function ($method, $url, $headers, $body) use ($status, $message) {
        return array('status' => $status, 'body' => $message === null ? null : json_encode(array('message' => $message)));
    };
};
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(400, 'Plan name is required')) === 'ok', 'rk plans 400 ok');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(403, $scope_msg)) === 'plans_missing', 'rk plans scope 403 missing');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(403, $mor_msg)) === 'plans_mor', 'rk plans MoR 403');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(403, 'other')) === 'unverifiable', 'rk plans other 403 unverifiable');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(500)) === 'unverifiable', 'rk plans 500 unverifiable');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, function () {
    throw new Exception('x');
}) === 'unverifiable', 'rk plans throwing request unverifiable');
$seen = array();
GF_Paystack_Restricted_Key::probe_plans($rk_key, function ($m, $u, $h, $b) use (&$seen) {
    $seen = array($m, $u, $b);

    return array('status' => 400, 'body' => null);
});
gf_paystack_test_check($seen === array('POST', 'https://api.paystack.co/plan', array()), 'rk plans probe is POST plan with empty body');

// default transport
$GLOBALS['gf_paystack_test_remote_calls'] = array();
$GLOBALS['gf_paystack_test_remote_response'] = array('response' => array('code' => 403), 'body' => json_encode(array('message' => $scope_msg)));
$v = GF_Paystack_Restricted_Key::validate($rk_key);
$call = $GLOBALS['gf_paystack_test_remote_calls'][0];
gf_paystack_test_check($call[0] === 'https://api.paystack.co/transaction?perPage=1' && $call[1]['method'] === 'GET' && $call[1]['timeout'] === 4 && $call[1]['redirection'] === 0 && $call[1]['body'] === null, 'rk default transport request args');
gf_paystack_test_check($call[1]['headers']['Authorization'] === 'Bearer ' . $rk_key && $call[1]['headers']['Content-Type'] === 'application/json', 'rk default transport headers');
gf_paystack_test_check($v['code'] === 'missing_scope' && count($GLOBALS['gf_paystack_test_remote_calls']) === 13, 'rk default transport parses json message');

$GLOBALS['gf_paystack_test_remote_calls'] = array();
$GLOBALS['gf_paystack_test_remote_response'] = array('response' => array('code' => 200), 'body' => '{}');
GF_Paystack_Restricted_Key::validate($rk_key);
$write_call = $GLOBALS['gf_paystack_test_remote_calls'][1];
gf_paystack_test_check($write_call[1]['method'] === 'POST' && $write_call[1]['body'] === '{}', 'rk default transport encodes empty write body');

$GLOBALS['gf_paystack_test_remote_calls'] = array();
$GLOBALS['gf_paystack_test_remote_response'] = new WP_Error('http', 'down');
$v = GF_Paystack_Restricted_Key::validate($rk_key);
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && count($GLOBALS['gf_paystack_test_remote_calls']) === 1 && strpos($v['probes'], $rk_key) === false, 'rk wp_error maps to status 0');

$GLOBALS['gf_paystack_test_remote_calls'] = array();
$GLOBALS['gf_paystack_test_remote_response'] = array('response' => array('code' => 403), 'body' => json_encode(array('message' => $scope_msg)));
$GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers'] = array('Authorization' => 'Bearer other', 'authorization' => 'Bearer other', 'X-Extra' => '1');
GF_Paystack_Restricted_Key::validate($rk_key);
$sent_headers = $GLOBALS['gf_paystack_test_remote_calls'][0][1]['headers'];
unset($GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers']);
$auth_keys = array_filter(array_keys($sent_headers), function ($k) {
    return strtolower($k) === 'authorization';
});
gf_paystack_test_check(count($auth_keys) === 1 && $sent_headers['Authorization'] === 'Bearer ' . $rk_key && $sent_headers['X-Extra'] === '1', 'rk probe headers keep a single Authorization with the saved key');

foreach (array(' Authorization', 'authorization ') as $odd_name) {
    $GLOBALS['gf_paystack_test_remote_calls'] = array();
    $GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers'] = array($odd_name => 'Bearer other', 'X-Extra' => '1');
    GF_Paystack_Restricted_Key::validate($rk_key);
    $sent_headers = $GLOBALS['gf_paystack_test_remote_calls'][0][1]['headers'];
    unset($GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers']);
    gf_paystack_test_check(array_keys($sent_headers) === array('X-Extra', 'Authorization') && $sent_headers['Authorization'] === 'Bearer ' . $rk_key, 'rk probe strips a padded Authorization header name: ' . json_encode($odd_name));
}

// # Coverage additions (dedicated test pass)

$rk_pinned = array(
    'transactions:read|required|GET|transaction?perPage=1|2xx',
    'transactions:write|required|POST|transaction/check_authorization|404_write',
    'disputes_refunds|sensitive|GET|refund?perPage=1|2xx_or_400',
    'bulk_charges|sensitive|GET|bulkcharge|2xx_or_400',
    'direct_debit|sensitive|GET|directdebit/account|2xx_or_400',
    'preauthorizations|sensitive|GET|preauthorization|2xx_or_400',
    'settlements|sensitive|GET|settlement?perPage=1|2xx_or_400',
    'subaccounts|sensitive|GET|subaccount|2xx_or_400',
    'transfers|sensitive|GET|transfer/config|2xx_or_400',
    'virtual_accounts|sensitive|GET|pay-with-transfer/custom-accounts/status|2xx_or_400',
    'webhooks|sensitive|GET|integration/webhooks/events|2xx_or_400',
    'connect|sensitive|GET|connect/platforms|2xx_or_400',
    'payouts|sensitive|GET|settlement/instant/profile|2xx_or_400',
);
$rk_actual = array();
$rk_only_safe = true;
foreach (GF_Paystack_Restricted_Key::PROBES as $p) {
    $rk_actual[] = $p['scope'] . '|' . $p['type'] . '|' . $p['method'] . '|' . $p['path'] . '|' . $p['present'];
    $rk_only_safe = $rk_only_safe && ($p['method'] === 'GET' || $p['scope'] === 'transactions:write');
}
gf_paystack_test_check($rk_actual === $rk_pinned, 'rk full probe table (scope, type, method, path, presence rule) pinned');
gf_paystack_test_check($rk_only_safe, 'rk only the transactions:write probe is a non-GET');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => 201, 'transactions:write' => array(404, $write_msg)), array(403, $scope_msg)));
gf_paystack_test_check($v['code'] === 'ok', 'rk baseline 201 counts as present');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map + array('settlements' => 204), array(403, $scope_msg)));
gf_paystack_test_check($v['code'] === 'sensitive_scope' && $v['sensitive_scopes'] === array('settlements'), 'rk sensitive probe 204 is present');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => 400), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'unverifiable' && $log === array('transactions:read'), 'rk 400 on the required baseline is not presence');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => 200, 'transactions:write' => 401), array(403, $scope_msg), $log));
gf_paystack_test_check($v['code'] === 'unverifiable' && end($log) === 'transactions:write', 'rk 401 on the required write probe after a 2xx baseline is not proof');

$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make(array('transactions:read' => array(403, $scope_msg), 'transactions:write' => array(404, $write_msg), 'disputes_refunds' => 401), array(403, $scope_msg)));
gf_paystack_test_check($v['sensitive_scopes'] === array(), 'rk 401 on sensitive after absent baseline reports no sensitive scope');

// A failing transport still records the probe that ran.
$notices = array();
set_error_handler(function ($no, $str) use (&$notices) {
    $notices[] = $str;

    return true;
});
$v_throw = GF_Paystack_Restricted_Key::validate($rk_key, function () {
    throw new RuntimeException('boom');
});
$v_nostatus = GF_Paystack_Restricted_Key::validate($rk_key, function () {
    return array('body' => null);
});
restore_error_handler();
gf_paystack_test_check($v_throw['probes'] === 'transactions:read:0' && $v_nostatus['probes'] === 'transactions:read:0' && $v_nostatus['code'] === 'unverifiable' && $notices === array(), 'rk throwing or status-less transport maps to status 0 without notices');

// Anything thrown outside the transport is still contained.
$GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers'] = function () {
    throw new RuntimeException('filter exploded');
};
$v = GF_Paystack_Restricted_Key::validate($rk_key, $rk_make($rk_ok_map));
gf_paystack_test_check($v['code'] === 'unverifiable' && $v['reason'] === 'transient' && $v['probes'] === '-', 'rk validate contains a Throwable raised outside the transport');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(400)) === 'unverifiable', 'rk probe_plans contains a Throwable raised outside the transport');
unset($GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers']);

// A filter returning a non-array must not break the probe.
$GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers'] = 'junk';
$seen_headers = null;
$v = GF_Paystack_Restricted_Key::validate($rk_key, function ($m, $u, $h, $b) use (&$seen_headers, $rk_make, $rk_ok_map, $scope_msg) {
    $seen_headers = $h;
    $f = $rk_make($rk_ok_map, array(403, $scope_msg));

    return $f($m, $u, $h, $b);
});
unset($GLOBALS['gf_paystack_test_filters']['gf_paystack_request_headers']);
gf_paystack_test_check($v['code'] === 'ok' && $seen_headers === array('Authorization' => 'Bearer ' . $rk_key), 'rk non-array header filter falls back to the forced Authorization header');

// probe_plans: only 400 is a pass
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(422, 'x')) === 'unverifiable', 'rk plans 422 unverifiable');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(200)) === 'unverifiable', 'rk plans 200 unverifiable');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(401, $scope_msg)) === 'unverifiable', 'rk plans 401 unverifiable');
gf_paystack_test_check(GF_Paystack_Restricted_Key::probe_plans($rk_key, $plan(403)) === 'unverifiable', 'rk plans 403 without a message unverifiable');
$plan_headers = null;
GF_Paystack_Restricted_Key::probe_plans($rk_key, function ($m, $u, $h, $b) use (&$plan_headers) {
    $plan_headers = $h;

    return array('status' => 400, 'body' => null);
});
gf_paystack_test_check($plan_headers['Authorization'] === 'Bearer ' . $rk_key, 'rk plans probe sends the key as Bearer');
