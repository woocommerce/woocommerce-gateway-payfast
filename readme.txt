=== WooCommerce Payfast Gateway ===
Contributors: woocommerce, automattic
Tags: credit card, payfast, payment request, woocommerce, automattic
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Give customers more flexibility and increase your bottom line with Payfast — one of South Africa’s most popular payment gateways.

== Description ==

Give customers more flexibility and increase your bottom line with Payfast — one of South Africa’s most popular payment gateways.

= Features =

* Fast, **super-secure** payments from almost anywhere in the world.
* Compatible with **subscriptions**, **deposits**, and **pre-orders**.

= Get started =

This extension requires a Payfast merchant account. [Sign up for free](https://payfast.io/gateway-aggregator-selector/).

= How does it work? =

At checkout, customers are automatically taken to Payfast to pay for their orders. Once payment is complete, Payfast redirects them to your store to continue shopping.

= Fast, super-secure, and scalable =

Pay it safe, every time. Payfast is trusted by **more than 100,000** South African businesses, with security that exceeds industry standards. It’s PCI DSS-compliant and also supports 18+ popular payment methods. In a nutshell, WooCommerce Payfast Gateway is the fastest, smartest, and safest way to accept payments online.

= Boost your bottom line =

Payfast is compatible with [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) and [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/), giving your shoppers the ultimate flexibility. **Convert more customers** by letting them make deposits, order in advance, or subscribe to your products and services. You win each way!

== Frequently Asked Questions ==

= Where can I find documentation? =

You’ve come to the right place. [Our documentation](https://woocommerce.com/document/payfast-payment-gateway/) for WooCommerce Payfast Gateway includes detailed setup instructions.

= Where can I get support? =

Get in touch via the [official support forum](https://wordpress.org/support/plugin/woocommerce-payfast-gateway/).

= Does this extension support subscriptions? =

Yes! WooCommerce PayFast Gateway is compatible with [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).

= Do I need an SSL certificate? =

We recommend using a [Secure Sockets Layer (SSL) certificate](https://woocommerce.com/document/ssl-and-https/) for additional customer security and trust.

= Can I accept international payments? =

Yes! You can receive Visa and Mastercard payments from anywhere in the world.

= Do I need a Payfast account? =

Yes; a [Payfast merchant account](https://payfast.io/gateway-aggregator-selector/) is required.

= Do I need to be a South African resident to open a Payfast account? =

No, but to open a Payfast account, you’ll need a South African bank account to pay your funds into.

== Changelog ==

= 1.7.4 - 2025-11-03 =
* Dev - Bump WooCommerce "tested up to" version 10.3.
* Dev - Bump WooCommerce minimum supported version to 10.1.
* Dev - Run PHP Compatibility sniffs against production build in GitHub action.
* Dev - Updates to our QIT GitHub Action workflow.
* Dev - Add new deploy workflow.

= 1.7.3 - 2025-08-11 =
* Dev - Bump WooCommerce "tested up to" version 10.1.
* Dev - Bump WooCommerce minimum supported version to 9.9.
* Dev - Bump WordPress minimum supported version to 6.7.
* Dev - Update NPM packages to fix security issues.

= 1.7.2 - 2025-07-14 =
* Add - WordPress Playground instance.
* Dev - Bump WooCommerce "tested up to" version 10.0.
* Dev - Bump WooCommerce minimum supported version to 9.8.

= 1.7.1 - 2025-05-05 =
* Fix - PHP warning for undefined variable when running alongside WooPayments.
* Dev - Bump WooCommerce "tested up to" version 9.8.
* Dev - Bump WooCommerce minimum supported version to 9.6.
* Dev - Bump WordPress "tested up to" version 6.8.
* Dev - Bump WordPress minimum supported version to 6.6.
* Dev - Update all third-party actions our workflows rely on to use versions based on specific commit hashes.

= 1.7.0 - 2025-03-17 =
* Update - Refresh copy and brand assets.
* Dev - Bump WooCommerce "tested up to" version 9.7.
* Dev - Bump WooCommerce minimum supported version to 9.5.
* Dev - Bump WordPress minimum supported version to 6.6.
* Dev - Add the WordPress Plugin Check GitHub Action and fix all issues it found.

= 1.6.10 - 2025-01-13 =
* Dev - Bump WooCommerce "tested up to" version 9.6.
* Dev - Bump WooCommerce minimum supported version to 9.4.
* Dev - Use the `@woocommerce/e2e-utils-playwright` NPM package for E2E tests.

= 1.6.9 - 2024-11-18 =
* Dev - Bump WordPress "tested up to" version 6.7.

= 1.6.8 - 2024-11-04 =
* Add - Credentials validation and required field notice for PayFast in the sandbox environment.
* Dev - Bump WooCommerce "tested up to" version 9.4.
* Dev - Bump WooCommerce minimum supported version to 9.2.
* Dev - Bump WordPress minimum supported version to 6.5.

= 1.6.7 - 2024-09-09 =
* Dev - Bump WooCommerce "tested up to" version 9.3.
* Dev - Bump WooCommerce minimum supported version to 9.1.
* Dev - Update E2E tests to accommodate changes in WooCommerce.

= 1.6.6 - 2024-07-29 =
* Dev - Bump WooCommerce "tested up to" version 9.1.
* Dev - Bump WooCommerce minimum supported version to 8.9.
* Dev - Bump WordPress "tested up to" version 6.6.
* Dev - Bump WordPress minimum supported version to 6.4.
* Dev - Update NPM packages and node version to v20 to modernize developer experience.
* Dev - Exclude the Woo Comment Hook `@since` sniff.
* Dev - Fix QIT E2E tests and add support for a few new test types.
* Tweak - Update WordPress.org plugin assets.

= 1.6.5 - 2024-05-14 =
* Fix - Use `rawurlencode` around the call to `get_site_url` to ensure things are encoded properly.

= 1.6.4 - 2024-05-07 =
* Fix - Resolved signature mismatch error caused by HTML entity encoding in site/blog name.
* Dev - Bump WooCommerce "tested up to" version 8.8.
* Dev - Bump WooCommerce minimum supported version to 8.6.

= 1.6.3 - 2024-05-02 =
* Fix - Enforce amount match check for all payments in the Payfast ITN handler.
* Dev - Bump WooCommerce "tested up to" version 8.7.
* Dev - Bump WooCommerce minimum supported version to 8.5.
* Dev - Bump WordPress "tested up to" version 6.5.
* Dev - Bump WordPress minimum supported version to 6.3.

= 1.6.2 - 2024-03-25 =
* Dev - Bump WooCommerce "tested up to" version 8.6.
* Dev - Bump WooCommerce minimum supported version to 8.4.
* Dev - Bump WordPress minimum supported version to 6.3.
* Fix - Payfast gateway not visible on Checkout when ZAR currency is set via WooPayments multi-currency feature.
* Fix - Allow navigation back from PayFast gateway payment page.

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

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-payfast/trunk/changelog.txt).
