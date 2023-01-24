=== WooCommerce PayFast Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes, dwainm, laurendavissmith001
Tags: credit card, payfast, payment request, woocommerce, automattic
Requires at least: 5.6
Tested up to: 6.0
Requires PHP: 7.0
Stable tag: 1.5.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official WooCommerce extension to receive payments using the South African PayFast payments provider.

== Description ==

The PayFast extension for WooCommerce enables you to accept payments including Subscriptions, Deposits & Pre-Orders via one of South Africa’s most popular payment gateways.

= Why choose PayFast? =

PayFast gives your customers more flexibility including putting down deposits, ordering ahead of time or paying on a weekly, monthly or annual basis.

== Frequently Asked Questions ==

= Does this require a PayFast merchant account? =

Yes! A PayFast merchant account, merchant key and merchant ID are required for this gateway to function.

= Does this require an SSL certificate? =

An SSL certificate is recommended for additional safety and security for your customers.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.woocommerce.com/document/payfast-payment-gateway)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Changelog ==

= 1.5.0 - 2022-12-06 =
* Add – Support for High-performance Order Storage (“HPOS”) (formerly known as Custom Order Tables, “COT”).
* Dev – Update node version from 12.0.0 to 16.13.0.
* Dev – Update npm version from 6.9.0 to 8.0.0.
* Tweak – Bump minimum PHP version from 5.6 to 7.0.
* Tweak – Bump minimum WP version from 4.4 to 5.6.
* Tweak – Bump minimum WC version from 2.6 to 6.0.

= 1.4.25 - 2022-09-07 =
* Fix - Add support for Transaction ID.

= 1.4.24 - 2022-07-19 =
* Fix - Subscription renewal payment failed issue in the production environment.

= 1.4.23 - 2022-07-05 =
 * Add - Allow setup PayFast during onboarding.
 * Add - Added support for customer subscription payment method change.

= 1.4.22 - 2022-05-12 =
 * Tweak - WP tested up to 6.0

= 1.4.21 - 2022-05-03 =
 * Tweak - Bump tested up to WordPress version 5.9.

= 1.4.20 - 2022-01-18 =
 * Fix - Status toggle button not working as expected

= 1.4.19 - 2021-05-04 =
 * Add - support for the Cart and Checkout blocks included
 * Fix - Error notice from direct access to the order id property.

= 1.4.18 - 2021-02-04 =
 * Add fees to order
 * Add signature to the request to PayFast
 * Tweak - WC 4.9.2 compatibility.
 * Tweak - WP 5.6 compatibility.

= 1.4.17 - 2020-11-25 =
 * Fix   - Fix Object could not be converted to string when renewing a subscription.
 * Tweak - WC tested up to 4.7
 * Tweak - WP tested up to 5.6
 * Tweak - PHP 8.0 compatibility.

= 1.4.15 - 2020-03-30 =
 * Tweak - WC tested up to 4.0
 * Tweak - WP tested up to 5.4

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-payfast/trunk/changelog.txt).

