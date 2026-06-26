<?php
/**
 * Plugin name: WooCommerce Utilities
 * Description: A plugin to provide tools for WooCommerce for testing purposes.
 *
 * @package WooCommerce_Gateway_Payfast\Tests
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register rest api endpoints.
add_action(
	'rest_api_init',
	static function () {
		// Flush all logs.
		register_rest_route(
			'e2e-wc/v1',
			'/flush-all-logs',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					try {
						WC_Log_Handler_File::delete_logs_before_timestamp( strtotime( '+2 day' ) );
					} catch ( Exception $e ) {
						return new WP_REST_Response( false, 500 );
					}

					return new WP_REST_Response( true, 200 );
				},
			)
		);

		// Flush all emails.
		register_rest_route(
			'e2e-wc/v1',
			'/flush-all-emails',
			array(
				'methods'  => 'DELETE',
				'callback' => function () {
					global $wpdb;

					try {
						$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}email_log" );
					} catch ( Exception $e ) {
						return new WP_REST_Response( false, 500 );
					}

					return new WP_REST_Response( true, 200 );
				},
			)
		);

		// Get subscriptions related to an order.
		register_rest_route(
			'e2e-wc/v1',
			'/orders/(?P<id>\d+)/subscriptions',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$order_id = absint( $request['id'] );

					if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
						return new WP_REST_Response( array(), 200 );
					}

					$subscriptions = wcs_get_subscriptions_for_order( $order_id );

					if ( function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
						$subscriptions = array_merge(
							$subscriptions,
							wcs_get_subscriptions_for_renewal_order( $order_id )
						);
					}

					$subscriptions = array_map(
						static function ( WC_Subscription $subscription ) {
							return array(
								'id'      => $subscription->get_id(),
								'status'  => $subscription->get_status(),
								'token'   => $subscription->get_meta( '_payfast_subscription_token', true ),
								'viewUrl' => $subscription->get_view_order_url(),
								'items'   => array_values(
									array_map(
										static function ( WC_Order_Item_Product $item ) {
											return array(
												'name'     => $item->get_name(),
												'productId' => $item->get_product_id(),
												'quantity' => $item->get_quantity(),
											);
										},
										$subscription->get_items()
									)
								),
							);
						},
						$subscriptions
					);

					return new WP_REST_Response( array_values( $subscriptions ), 200 );
				},
			)
		);

		// Process a subscription renewal through the admin scheduled-payment flow.
		register_rest_route(
			'e2e-wc/v1',
			'/subscriptions/(?P<id>\d+)/admin-renewal',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $request ) {
					$subscription_id = absint( $request['id'] );

					if ( ! function_exists( 'wcs_get_subscription' ) ) {
						return new WP_REST_Response(
							array( 'message' => 'WooCommerce Subscriptions is not available.' ),
							500
						);
					}

					$subscription = wcs_get_subscription( $subscription_id );

					if ( ! $subscription instanceof WC_Subscription ) {
						return new WP_REST_Response(
							array( 'message' => 'Subscription was not found.' ),
							404
						);
					}

					$token_before = $subscription->get_meta( '_payfast_subscription_token', true );

					if ( empty( $token_before ) ) {
						return new WP_REST_Response(
							array( 'message' => 'Subscription does not have a Payfast token.' ),
							400
						);
					}

					delete_option( 'payfast_e2e_last_api_request' );

					try {
						/**
						 * Processes a scheduled subscription payment for e2e admin renewal tests.
						 *
						 * @since 1.7.6
						 *
						 * @param int $subscription_id Subscription ID.
						 */
						do_action( 'woocommerce_scheduled_subscription_payment', $subscription_id );
					} catch ( Exception $e ) {
						return new WP_REST_Response(
							array( 'message' => $e->getMessage() ),
							500
						);
					}

					$subscription  = wcs_get_subscription( $subscription_id );
					$renewal_order = $subscription->get_last_order( 'all', 'renewal' );

					if ( ! $renewal_order instanceof WC_Order ) {
						return new WP_REST_Response(
							array( 'message' => 'Renewal order was not created.' ),
							500
						);
					}

					$payment_gateways = WC()->payment_gateways()->payment_gateways();
					$gateway          = isset( $payment_gateways['payfast'] ) ? $payment_gateways['payfast'] : null;

					if ( ! $gateway instanceof WC_Gateway_PayFast ) {
						return new WP_REST_Response(
							array( 'message' => 'Payfast gateway is not available.' ),
							500
						);
					}

					if ( ! class_exists( 'WebhookDataProvider' ) ) {
						return new WP_REST_Response(
							array( 'message' => 'Payfast webhook faker is not available.' ),
							500
						);
					}

					$gateway->handle_itn_request(
						( new WebhookDataProvider( $renewal_order->get_id() ) )->get_data(
							array(
								'item_description' => wp_json_encode(
									array(
										'renewal_order_id' => $renewal_order->get_id(),
									)
								),
							),
							false
						)
					);

					$renewal_order = wc_get_order( $renewal_order->get_id() );
					$subscription  = wcs_get_subscription( $subscription_id );
					$api_request   = get_option( 'payfast_e2e_last_api_request', array() );

					return new WP_REST_Response(
						array(
							'subscriptionId'     => $subscription_id,
							'subscriptionStatus' => $subscription->get_status(),
							'tokenBefore'        => $token_before,
							'tokenAfter'         => $subscription->get_meta( '_payfast_subscription_token', true ),
							'renewalOrderId'     => $renewal_order->get_id(),
							'renewalOrderStatus' => $renewal_order->get_status(),
							'apiCommand'         => isset( $api_request['command'] ) ? $api_request['command'] : null,
							'apiToken'           => isset( $api_request['token'] ) ? $api_request['token'] : null,
							'apiBody'            => isset( $api_request['body'] ) ? $api_request['body'] : array(),
						),
						200
					);
				},
			)
		);
	}
);
