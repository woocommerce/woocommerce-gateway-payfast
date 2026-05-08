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
												'name'      => $item->get_name(),
												'productId' => $item->get_product_id(),
												'quantity'  => $item->get_quantity(),
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
	}
);
