<?php
/**
 * Plugin name: PayFast Webhook Faker
 * Description: A plugin to fake PayFast webhooks for E2E testing purposes.
 */

// Bootstrap the plugin.
require_once __DIR__ . '/WebhookDataProvider.php';

// Fake response for the PayFast webhooks.
add_filter( 'pre_http_request', function ( $result, $args, $url ) {
	// Check if the request is for the PayFast webhook data validation.
	if ( strpos( $url, 'sandbox.payfast.co.za/eng/query/validate' ) !== false ) {
		return 'VALID';
	}

	return $result;
}, 10, 3 );

// Fake the PayFast webhook.
add_action( 'woocommerce_receipt_payfast', function ( $order_id ) {
	wp_remote_post(
		home_url() . '/?wc-api=wc_gateway_payfast',
		[
			'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded', ],
			'body' => ( new WebhookDataProvider( $order_id ) )->getData(),
			'format' => 'body',
			'sslverify' => false,
			'blocking' => false,
		]
	);
}, 999 );


