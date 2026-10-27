<?php

defined('ABSPATH') || die();

function gf_paystack_php_fixed_versions()
{
    return array(
        '8.2' => '8.2.35',
        '8.3' => '8.3.36',
        '8.4' => '8.4.27',
        '8.5' => '8.5.12',
    );
}

function gf_paystack_php_is_vulnerable($version)
{
    if (sscanf($version, '%d.%d', $major, $minor) !== 2) {
        return false;
    }

    $branch = $major . '.' . $minor;
    if (version_compare($branch, '8.2', '<')) {
        return true;
    }

    // Compare within the release branch; each branch has its own fixed version.
    $fixed = gf_paystack_php_fixed_versions();

    return isset($fixed[$branch]) ? version_compare($version, $fixed[$branch], '<') : false;
}

function gf_paystack_php_advisory_notice($version = '')
{
    $version = $version ?: PHP_VERSION;

    if (!gf_paystack_php_is_vulnerable($version)) {
        return;
    }

    $capability = is_multisite() ? 'manage_network_options' : 'manage_options';
    if (!current_user_can($capability)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->id, array('dashboard', 'plugins', 'dashboard-network', 'plugins-network'), true)) {
        return;
    }

    $message = sprintf(
        esc_html__('Paystack Add-On for Gravity Forms: this site runs PHP %1$s, which may be affected by critical PHP security vulnerabilities (CVE-2026-109618, CVE-2026-109619). Ask your host to upgrade to PHP %2$s or later.', 'gravityformspaystack'),
        esc_html($version),
        esc_html(implode(', ', gf_paystack_php_fixed_versions()))
    );

    echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';
}
