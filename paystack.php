<?php
/*
Plugin Name: Paystack Add-On for Gravity Forms 
Plugin URI: https://paystack.com/docs/libraries-and-plugins/plugins#wordpress
0
Description: Integrates Gravity Forms with Paystack, enabling customers to pay for goods and services through Gravity Forms.
Version: 2.0.6
Author: Paystack
Author URI: https://developers.paystack.com
License: GPL-2.0+
Text Domain: gravityformspaystack
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2020 Paystack

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

defined('ABSPATH') || die();

define('GF_PAYSTACK_VERSION', '2.0.6');
define('GF_PAYSTACK_MIN_GF_VERSION', '2.0');

// Register the addon - only runs if Gravity Forms is loaded
add_action('gform_loaded', array('GF_Paystack_Bootstrap', 'load'), 5);

// Show admin notice if Gravity Forms is not active
add_action('admin_notices', array('GF_Paystack_Bootstrap', 'admin_notice'));

class GF_Paystack_Bootstrap
{
	/**
	 * Load the add-on if Gravity Forms is available.
	 *
	 * @return void
	 */
	public static function load()
	{
		if (!method_exists('GFForms', 'include_payment_addon_framework')) {
			return;
		}

		require_once('class-gf-paystack.php');
		require_once('class-gf-paystack-api.php');

		GFAddOn::register('GFPaystack');
	}

	/**
	 * Display admin notice if Gravity Forms is not active.
	 *
	 * @return void
	 */
	public static function admin_notice()
	{
		// Only show on plugins page or dashboard
		$screen = get_current_screen();
		if (!$screen || !in_array($screen->id, array('plugins', 'plugins-network', 'dashboard'))) {
			return;
		}

		// Check if Gravity Forms is active
		if (!class_exists('GFForms')) {
			printf(
				'<div class="notice notice-error"><p><strong>Paystack for Gravity Forms</strong> requires Gravity Forms to be installed and active. <a href="%s" target="_blank" rel="noopener noreferrer">Get Gravity Forms</a></p></div>',
				esc_url('https://www.gravityforms.com/')
			);
			return;
		}

		// Check if minimum version requirement is met
		if (method_exists('GFForms', 'version') && version_compare(GFForms::version(), GF_PAYSTACK_MIN_GF_VERSION, '<')) {
			printf(
				'<div class="notice notice-error"><p><strong>Paystack for Gravity Forms</strong> requires Gravity Forms %s or higher. Please update Gravity Forms.</p></div>',
				esc_html(GF_PAYSTACK_MIN_GF_VERSION)
			);
			return;
		}
	}
}

/**
 * Returns an instance of the GFPaystack class.
 *
 * @return GFPaystack|false
 */
function gf_paystack()
{
	if (!class_exists('GFPaystack')) {
		return false;
	}
	return GFPaystack::get_instance();
}
