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
		return ['body' => 'VALID'];
	}

	return $result;
}, 10, 3 );

// Fake the PayFast webhook.
add_action( 'woocommerce_thankyou_payfast', function ( $order_id ) {
	/* @var WC_Gateway_PayFast $paymentGateway */
	$paymentGateway = WC()->payment_gateways()->payment_gateways()['payfast'];
	$paymentGateway->handle_itn_request( ( new WebhookDataProvider( $order_id ) )->getData() );
}, 999 );


