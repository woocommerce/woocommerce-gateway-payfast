<?php
/**
 * Prepares the store of a WordPress Playground preview of a pull request build.
 *
 * The Blueprints in .github/workflows/pr-build-live-branch.yml run this file once, after
 * WooCommerce and the Payfast build are active. It is not loaded by the plugin.
 *
 * @package WooCommerce Gateway Payfast
 */

require_once '/wordpress/wp-load.php';

// WooCommerce would otherwise replace the Blueprint's landing page with its setup wizard.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );

// Payfast is only offered when the store currency is ZAR.
update_option( 'woocommerce_currency', 'ZAR' );
update_option( 'woocommerce_default_country', 'ZA:WC' );
update_option( 'woocommerce_store_address', '90 Plein Street' );
update_option( 'woocommerce_store_city', 'Cape Town' );
update_option( 'woocommerce_store_postcode', '8000' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_allow_tracking', 'no' );

// A WooCommerce installed by a Blueprint starts on legacy order storage, which is not what new stores use.
update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );

WC_Install::create_pages();

$product = new WC_Product_Simple();
$product->set_name( 'Playground test product' );
$product->set_slug( 'playground-test-product' );
$product->set_regular_price( '100' );
// Virtual, because the preview store has no shipping zones for checkout to offer.
$product->set_virtual( true );
$product->set_status( 'publish' );
$product->save();

// The Checkout page WooCommerce creates uses the Checkout block, so the shortcode checkout gets its own page.
wp_insert_post(
	array(
		'post_title'   => 'Shortcode Checkout',
		'post_name'    => 'shortcode-checkout',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_author'  => 1,
		'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
	)
);
