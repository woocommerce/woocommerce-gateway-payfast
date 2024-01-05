=== WooCommerce Payfast Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes, dwainm, laurendavissmith001
Tags: credit card, payfast, payment request, woocommerce, automattic
Requires at least: 6.2
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official WooCommerce extension to receive payments using the South African Payfast payments provider.

== Description ==

The Payfast extension for WooCommerce enables you to accept payments including Subscriptions, Deposits & Pre-Orders via one of South Africa’s most popular payment gateways.

= Why choose Payfast? =

Payfast gives your customers more flexibility including putting down deposits, ordering ahead of time or paying on a weekly, monthly or annual basis.

== Frequently Asked Questions ==

= Does this require a Payfast merchant account? =

Yes! A Payfast merchant account, merchant key and merchant ID are required for this gateway to function.

= Does this require an SSL certificate? =

An SSL certificate is recommended for additional safety and security for your customers.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.woocommerce.com/document/payfast-payment-gateway)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Changelog ==

= 1.6.1 - 2024-01-08 =
* Add - Readme.md file for e2e tests.
* Dev - Declare compatibility with WooCommerce Blocks.
* Dev - Declare compatibility with Product Editor.
* Dev - Updated the main file of the plugin to match the plugin's slug.
* Dev - Bump PHP minimum supported version to 7.4.
* Dev - Bump WooCommerce "tested up to" version 8.4.
* Dev - Bump WooCommerce minimum supported version to 8.2.
* Dev - Resolve coding standards issues.
* Tweak - Bump PHP "tested up to" version 8.3.

= 1.6.0 - 2023-11-22 =
* Dev - Add Playwright end-to-end tests.
* Dev - Update default behavior to use a block-based cart and checkout in E2E tests.
* Dev - Bump WooCommerce "tested up to" version 8.3.
* Dev - Bump WooCommerce minimum supported version to 8.1.
* Dev - Bump WordPress minimum supported version to 6.2.
* Dev - Bump WordPress "tested up to" version 6.4.
* Dev - Bump WordPress minimum supported version to 6.2.
* Dev - Update PHPCS and PHPCompatibility GitHub Actions.

= 1.5.9 - 2023-09-18 =
* Dev - Bump WordPress "tested up to" version from 6.2 to 6.3.
* Dev - Bump WooCommerce "tested up to" version 7.9.
* Dev - Bump WooCommerce minimum supported version to 7.7.
* Dev - Bump PHP minimum supported version to 7.3.

= 1.5.8 - 2023-08-29 =
* Add - Admin notice if this extension is activated without WooCommerce.

= 1.5.7 - 2023-07-25 =
* Fix - Handle WP_Error object when return from wp_remote_request.

= 1.5.6 - 2023-07-19 =
* Fix - Include build directory.

= 1.5.5 - 2023-07-04 =
* Dev - Bump WooCommerce "tested up to" version 7.8.
* Dev - Bump WooCommerce minimum supported version from 6.8 to 7.2.
* Dev - Bump WordPress minimum supported version from 5.8 to 6.1.
* Fix - Replace escaping of order total price elements on the edit order admin screen.

= 1.5.4 - 2023-06-13 =
* Fix - Escaped strings.

= 1.5.3 - 2023-05-25 =
* Dev – Bump WooCommerce “tested up to” version 7.6.
* Dev – Bump WordPress minimum supported version from 5.6 to 5.8.
* Dev – Bump WordPress “tested up to” version 6.2.

= 1.5.2 - 2023-03-16 =
* Tweak - Bump PHP minimum supported version from 7.0 to 7.2.
* Tweak - Bump WooCommerce minimum supported version from 6.0 to 6.8.
* Tweak - Bump WooCommerce "tested up to" version 7.4.

= 1.5.1 - 2023-02-28 =
* Update – Payfast logo and text references to meet their new branding guidelines.
* Tweak – Bump WooCommerce “Tested up to” to 7.3.
* Tweak – Bump WooCommerce tested up to 7.3.0.
* Dev – Bump @sideway/formula from 3.0.0 to 3.0.1.
* Dev – Resolved linting issues.
* Dev – Bump json5 from 1.0.1 to 1.0.2.
* Dev – Bump loader-utils from 1.4.0 to 1.4.2.

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
 * Add - Allow setup Payfast during onboarding.
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
 * Add signature to the request to Payfast
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

