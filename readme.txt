=== Paystack Add-On for Gravity Forms ===
Contributors: webong, paystack, kaneahabagale, 
Donate link: https://paystack.com/demo
Tags: payments, subscriptions, gravityforms
Requires at least: 5.1
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 2.0.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
The Paystack Addon for Gravity Forms allows you to quickly and easily implement credit card payments with WordPress. With the Paystack Add-On you can capture one time credit card payments or setup recurring payment subscriptions.


== Installation ==
 
= Minimum Requirements =
* Gravity Forms v2.4+
* WordPress v5.1+
* SSL Certificate Installed and - Configured
* Download and install the add-on
* A Paystack account

= Automatic installation =
 
Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of Paystack Payment Add-On for Gravity Forms, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.
 
In the search field type “Paystack Payment Add-On for Gravity Forms” and click Search Plugins. Once you’ve found our payment plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.
 
= Manual installation =
 
The manual installation method involves downloading our payment plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =
 
Automatic updates should work like a charm; as always though, ensure you backup your site just in case.


== Frequently Asked Questions ==
 
= Where can I find help and documentation to understand Paystack? =
 
You can find help and information on Paystack on our [Help Desk](https://paystack.com/help)
 
= Where can I get support or talk to other users? =
 
If you get stuck, you can ask for help in the [Paystack Payment Add-On for Gravity Forms](https://wordpress.org/support/plugin/paystack-add-on-for-gravity-forms/). You can also directly email support@paystack.com for assistance.
 
= Paystack Payment for Give is awesome! Can I contribute? =
 
Yes you can! Join in on our [GitHub repository](https://github.com/PaystackHQ/plugin-gravity-forms) :)


== Changelog ==

= 2.0.9 - October 28, 2026 =
* Fixed a fatal error on the confirmation page after a successful payment when the usage tracking service is unreachable.

= 2.0.8 - October 27, 2026 =
* Security: hardened webhook and payment verification. Props k2sobot.
* Security: API credentials and response bodies are no longer written to the PHP error log, and the API secret key is only sent to the Paystack API.
* Added an admin notice when the server's PHP version is affected by CVE-2026-109618 / CVE-2026-109619.
* Tested with WordPress 7.1 and PHP 8.2.35, 8.3.36, 8.4.27 and 8.5.12.
* Version 2.1.0 will require PHP 8.2 or later.

= 2.0.7 - July 21, 2025 =
* Compatibility with WordPress v6.8.1 and PHP 8.2.23

= 2.0.6 - AUGUST 8, 2024 =
* Compatibility with WordPress v6.6 and PHP 8.2

= 2.0.3 - SEPTMBER 26, 2023 =
* Added XOF and EGP to the currency list

= 2.0.2 - SEPTMBER 26, 2023 =
* Compatibility with WordPress v6.2 and PHP 8

= 2.0.1 - APRIL 14, 2022 =
* Test plugin up to WordPress 6.2
* Fix PHP warnings about missing currency code

= 2.0.0 - NOVEMBER 1, 2021 =
* Compatibility with WordPress v5.9 and PHP 8

= 1.0.2 - NOVEMBER 1, 2021 =
* Added support for KES currency

= 1.0.1 - JANUARY 22, 2021 =
* Added support for ZAR currency

= 1.0.0 - JANUARY 21, 2021 =
* Initial Release


== Upgrade Notice ==

= 2.0.9 =
* Fixes a fatal error customers could see after paying. Please update.

= 2.0.8 =
* Security update — please update now. After updating, rotate your live and test secret keys in the Paystack Dashboard and delete old PHP and WordPress debug log files, as earlier versions logged API request details. Ask your host to run PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later.

= 2.0.7 - July 21, 2025 =
* Compatibility with WordPress v6.8.1 and PHP 8.2.23

= 2.0.6 =
* Compatibility with WordPress v6.6 and PHP 8.2

= 2.0.2 =
* Compatibility with WordPress v6.2 and PHP 8

= 1.0.2 =
* Test plugin up to WordPress 6.2
* Fix PHP warnings about missing currency code


= 1.0.2 =
* You should update to this version if you are a Kenyan merchant on Paystack

= 1.0.1 =
* You should update to this version if you are a South African merchant on Paystack

= 1.0.0 =
* Initial Release


== Screenshots ==
1. Setup screen