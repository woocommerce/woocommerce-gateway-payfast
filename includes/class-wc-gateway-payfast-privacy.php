<?php
/**
 * Payfast Payment Gateway
 *
 * @package WooCommerce Gateway Payfast
 */

if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

/**
 * Privacy/GDPR related functionality which ties into WordPress functionality.
 */
class WC_Gateway_PayFast_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( __( 'Payfast', 'woocommerce-gateway-payfast' ) );

		$this->add_exporter( 'woocommerce-gateway-payfast-order-data', __( 'WooCommerce Payfast Order Data', 'woocommerce-gateway-payfast' ), array( $this, 'order_data_exporter' ) );

		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$this->add_exporter( 'woocommerce-gateway-payfast-subscriptions-data', __( 'WooCommerce Payfast Subscriptions Data', 'woocommerce-gateway-payfast' ), array( $this, 'subscriptions_data_exporter' ) );
		}

		$this->add_eraser( 'woocommerce-gateway-payfast-order-data', __( 'WooCommerce Payfast Data', 'woocommerce-gateway-payfast' ), array( $this, 'order_data_eraser' ) );
	}

	/**
	 * Returns a list of orders that are using one of Payfast's payment methods.
	 *
	 * The list of orders is paginated to 10 orders per page.
	 *
	 * @param string $email_address The user email address.
	 * @param int    $page          Page number to query.
	 * @return WC_Order[]|stdClass Number of pages and an array of order objects.
	 */
	protected function get_payfast_orders( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$order_query = array(
			'payment_method' => 'payfast',
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 */
	public function get_privacy_message() {
		return wpautop(
			sprintf(
				/* translators: 1: anchor tag 2: closing anchor tag */
				esc_html__( 'By using this extension, you may be storing personal data or sharing data with an external service. %1$sLearn more about how this works, including what you may want to include in your privacy policy.%2$s', 'woocommerce-gateway-payfast' ),
				'<a href="https://docs.woocommerce.com/document/privacy-payments/#woocommerce-gateway-payfast" target="_blank" rel="noopener noreferrer">',
				'</a>'
			)
		);
	}

	/**
	 * Handle exporting data for Orders.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = array();

		$orders = $this->get_payfast_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => esc_attr__( 'Orders', 'woocommerce-gateway-payfast' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => esc_attr__( 'Payfast token', 'woocommerce-gateway-payfast' ),
							'value' => $order->get_meta( '_payfast_pre_order_token', true ),
						),
					),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Handle exporting data for Subscriptions.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function subscriptions_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$page           = (int) $page;
		$data_to_export = array();

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_payment_method',
				'value'   => 'payfast',
				'compare' => '=',
			),
			array(
				'key'     => '_billing_email',
				'value'   => $email_address,
				'compare' => '=',
			),
		);

		$subscription_query = array(
			'posts_per_page' => 10,
			'page'           => $page,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => $meta_query,
		);

		$subscriptions = wcs_get_subscriptions( $subscription_query );

		$done = true;

		if ( 0 < count( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_subscriptions',
					'group_label' => esc_attr__( 'Subscriptions', 'woocommerce-gateway-payfast' ),
					'item_id'     => 'subscription-' . $subscription->get_id(),
					'data'        => array(
						array(
							'name'  => esc_attr__( 'Payfast subscription token', 'woocommerce-gateway-payfast' ),
							'value' => $subscription->get_meta( '_payfast_subscription_token', true ),
						),
					),
				);
			}

			$done = 10 > count( $subscriptions );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Finds and erases order data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function order_data_eraser( $email_address, $page ) {
		$orders = $this->get_payfast_orders( $email_address, (int) $page );

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		foreach ( (array) $orders as $order ) {
			$order = wc_get_order( $order->get_id() );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $order );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_subscription( $order );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );
		}

		// Tell core if we have more orders to work on still.
		$done = count( $orders ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Handle eraser of data tied to Subscriptions
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	protected function maybe_handle_subscription( $order ) {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return array( false, false, array() );
		}

		if ( ! wcs_order_contains_subscription( $order ) ) {
			return array( false, false, array() );
		}

		$subscription = current( wcs_get_subscriptions_for_order( $order->get_id() ) );

		$payfast_source_id = $subscription->get_meta( '_payfast_subscription_token', true );

		if ( empty( $payfast_source_id ) ) {
			return array( false, false, array() );
		}

		/**
		 * Filter privacy eraser subscription statuses.
		 *
		 * Modify the subscription statuses that are considered active and should be retained.
		 *
		 * @since 1.4.13
		 *
		 * @param string[] $statuses Array of subscription statuses considered active.
		 */
		if ( $subscription->has_status( apply_filters( 'wc_payfast_privacy_eraser_subs_statuses', array( 'on-hold', 'active' ) ) ) ) {
			return array(
				false,
				true,
				array(
					sprintf(
						/* translators: %d: Order ID */
						esc_html__( 'Order ID %d contains an active Subscription' ),
						$order->get_id()
					),
				),
			);
		}

		$renewal_orders = WC_Subscriptions_Renewal_Order::get_renewal_orders( $order->get_id(), 'WC_Order' );

		foreach ( $renewal_orders as $renewal_order ) {
			$renewal_order->delete_meta_data( '_payfast_subscription_token' );
			$renewal_order->save_meta_data();
		}

		$subscription->delete_meta_data( '_payfast_subscription_token' );
		$subscription->save_meta_data();

		return array( true, false, array( esc_html__( 'Payfast Subscriptions Data Erased.', 'woocommerce-gateway-payfast' ) ) );
	}

	/**
	 * Handle eraser of data tied to Orders
	 *
	 * @since 1.4.13
	 *
	 * @param WC_Order $order The order object.
	 * @return array
	 */
	protected function maybe_handle_order( $order ) {
		$payfast_token = $order->get_meta( '_payfast_pre_order_token', true );

		if ( empty( $payfast_token ) ) {
			return array( false, false, array() );
		}

		$order->delete_meta_data( '_payfast_pre_order_token' );
		$order->save_meta_data();

		return array( true, false, array( esc_html__( 'Payfast Order Data Erased.', 'woocommerce-gateway-payfast' ) ) );
	}
}

new WC_Gateway_PayFast_Privacy();
