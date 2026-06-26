<?php
/**
 * Provides fake PayFast webhook data for e2e tests.
 *
 * @package WooCommerce_Gateway_Payfast\Tests
 */

/**
 * This class is used to provide the data for the payfast webhook.
 */
class WebhookDataProvider {
	/**
	 * Order.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Payfast payment gateway.
	 *
	 * @var WC_Gateway_PayFast
	 */
	protected $payment_gateway;

	/**
	 * BaseClass constructor.
	 *
	 * @param int $order_id Order ID.
	 */
	public function __construct( int $order_id ) {
		$this->order           = wc_get_order( $order_id );
		$this->payment_gateway = WC()->payment_gateways()->payment_gateways()['payfast'];
	}

	/**
	 * This function should return the data for the transaction webhook.
	 *
	 * @param array $overrides                  Data overrides.
	 * @param bool  $include_subscription_token Whether to include subscription token data.
	 * @return array
	 */
	public function get_data( array $overrides = array(), bool $include_subscription_token = true ): array {
		$this->generate_payment_data();

		// Make $data_to_send property and _generate_parameter_string function accessible.
		$reflection_object = new ReflectionObject( $this->payment_gateway );

		$data_property = $reflection_object->getProperty( 'data_to_send' );
		$data_property->setAccessible( true );
		$data_for_payfast = $data_property->getValue( $this->payment_gateway );

		$result = array(
			'm_payment_id'     => $data_for_payfast['m_payment_id'],
			'pf_payment_id'    => random_int( 1000000, 9999999 ),
			'payment_status'   => 'COMPLETE',
			'item_name'        => $data_for_payfast['item_name'],
			'item_description' => $data_for_payfast['item_description'],
			'custom_str1'      => $data_for_payfast['custom_str1'],
			'custom_str2'      => $data_for_payfast['custom_str2'],
			'custom_str3'      => $data_for_payfast['custom_str3'],
			'custom_str4'      => '',
			'custom_str5'      => '',
			'custom_int1'      => '',
			'custom_int2'      => '',
			'custom_int3'      => '',
			'custom_int4'      => '',
			'custom_int5'      => '',
			'name_first'       => $data_for_payfast['name_first'],
			'name_last'        => $data_for_payfast['name_last'],
			'email_address'    => $data_for_payfast['email_address'],
			'merchant_id'      => $data_for_payfast['merchant_id'],
		);

		// Add the amounts.
		$result = array_merge( $result, $this->get_amounts() );

		// Add the token and billing date for subscriptions.
		if (
			$include_subscription_token &&
			(
				$this->payment_gateway->is_subscription( $this->order )
				|| $this->payment_gateway->order_contains_subscription( $this->order )
			)
		) {
			$result['token']        = wp_generate_password( 8, false );
			$result['billing_date'] = gmdate( 'Y-m-d' );
		}

		$result = array_merge( $result, $overrides );

		if ( ! $include_subscription_token ) {
			unset( $result['token'], $result['billing_date'] );
		}

		$method = $reflection_object->getMethod( '_generate_parameter_string' );
		$method->setAccessible( true );
		$signature = md5( $method->invoke( $this->payment_gateway, $result, false, false ) );

		// Add the signature.
		$result['signature'] = $signature;

		return $result;
	}

	/**
	 * Generate and cache the data Payfast would receive from the checkout form.
	 */
	private function generate_payment_data(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test helper mirrors Woo pay-for-order context.
		$had_order_key = array_key_exists( 'key', $_GET );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is restored only.
		$previous_order_key  = $had_order_key ? $_GET['key'] : null;
		$order_id            = $this->order->get_id();
		$grant_pay_for_order = static function ( $allcaps, $caps, $args ) use ( $order_id ) {
			if ( isset( $args[0], $args[2] ) && 'pay_for_order' === $args[0] && absint( $args[2] ) === $order_id ) {
				foreach ( (array) $caps as $cap ) {
					$allcaps[ $cap ] = true;
				}
			}

			return $allcaps;
		};

		$_GET['key'] = $this->order->get_order_key();
		add_filter( 'user_has_cap', $grant_pay_for_order, 10, 3 );

		ob_start();
		try {
			$this->payment_gateway->generate_payfast_form( $this->order->get_id() );
		} finally {
			ob_end_clean();
			remove_filter( 'user_has_cap', $grant_pay_for_order, 10 );

			if ( $had_order_key ) {
				$_GET['key'] = $previous_order_key;
			} else {
				unset( $_GET['key'] );
			}
		}
	}

	/**
	 * This function should return the signature for the transaction webhook.
	 */
	private function get_amounts(): array {
		$gross_amount = $this->order->get_total();
		$amount_fee   = $gross_amount * 0.023; // 2.3% fee for PayFast.
		$amount_net   = $gross_amount - $amount_fee;

		return array(
			'amount_gross' => $gross_amount,
			'amount_fee'   => - $amount_fee,
			'amount_net'   => $amount_net,
		);
	}
}
