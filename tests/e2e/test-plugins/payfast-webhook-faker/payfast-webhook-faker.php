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

	// Fake PayFast subscription API requests for scheduled renewals.
	if ( strpos( $url, 'api.payfast.co.za/subscriptions/' ) !== false ) {
		$path      = wp_parse_url( $url, PHP_URL_PATH );
		$parts     = array_values( array_filter( explode( '/', (string) $path ) ) );
		$token     = $parts[1] ?? '';
		$command   = $parts[2] ?? '';
		$body      = $args['body'] ?? array();
		$method    = $args['method'] ?? 'POST';
		$response  = array(
			'data' => array(
				'response' => true,
			),
		);

		update_option(
			'payfast_e2e_last_api_request',
			array(
				'url'     => $url,
				'token'   => $token,
				'command' => $command,
				'body'    => $body,
				'method'  => $method,
			)
		);

		return array(
			'headers'  => array(
				'content-type' => 'application/json',
			),
			'body'     => wp_json_encode( $response ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
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

