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

add_action('gform_loaded', array('GF_Paystack_Bootstrap', 'load'), 5);

class GF_Paystack_Bootstrap
{
	public static function load()
	{
		if (!method_exists('GFForms', 'include_payment_addon_framework')) {
			return;
		}

		require_once('class-gf-paystack.php');

		require_once('class-gf-paystack-api.php');

		GFAddOn::register('GFPaystack');
	}
}

function gf_paystack()
{
	return GFPaystack::get_instance();
}
