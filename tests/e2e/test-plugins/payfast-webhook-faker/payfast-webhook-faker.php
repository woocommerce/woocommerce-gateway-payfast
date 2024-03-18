<?php
/**
 * Plugin name: PayFast Webhook Faker
 * Description: A plugin to fake PayFast webhooks for E2E testing purposes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bootstrap the plugin.
require_once __DIR__ . '/WebhookDataProvider.php';

// Fake response for the PayFast webhooks.
add_filter( 'pre_http_request', function ( $result, $args, $url ) {
	// Check if the request is for the PayFast webhook data validation.
	if ( strpos( $url, 'sandbox.payfast.co.za/eng/query/validate' ) !== false ) {
		return ['body' => 'VALID'];
	}

	return $result;
}, 10, 3 );

// Fake the PayFast webhook.
add_action( 'woocommerce_thankyou_payfast', function ( $order_id ) {
	wp_remote_post(
		esc_url_raw(home_url('/?wc-api=wc_gateway_payfast')),
		[
			'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
			'body'    => ( new WebhookDataProvider( $order_id ) )->getData(),
			'format'  => 'body',
			'sslverify' => false,
		]
	);
}, 999 );


