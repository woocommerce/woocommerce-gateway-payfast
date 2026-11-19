<?php
/**
 * Tests for WC_Gateway_PayFast.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * WC_Gateway_PayFast tests.
 */
class WC_Gateway_PayFast_Test extends WP_UnitTestCase {

	/**
	 * Gateway under test.
	 *
	 * @var WC_Gateway_PayFast
	 */
	private $gateway;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'woocommerce_currency', 'ZAR' );
		update_option(
			'woocommerce_payfast_settings',
			array(
				'enabled'      => 'yes',
				'testmode'     => 'yes',
				'merchant_id'  => 'test-merchant-id',
				'merchant_key' => 'test-merchant-key',
				'pass_phrase'  => 'test-passphrase',
			)
		);

		// The gateway defines constants when constructed, so a second instance would raise warnings.
		$this->gateway = WC()->payment_gateways()->payment_gateways()['payfast'];
		$this->gateway->init_settings();
	}

	/**
	 * The gateway is registered with WooCommerce under the `payfast` id stored on orders.
	 *
	 * @return void
	 */
	public function test_gateway_is_registered_with_woocommerce() {
		$gateways = WC()->payment_gateways()->payment_gateways();

		$this->assertArrayHasKey( 'payfast', $gateways );
		$this->assertInstanceOf( WC_Gateway_PayFast::class, $gateways['payfast'] );
	}

	/**
	 * The gateway is available with ZAR and credentials.
	 *
	 * @return void
	 */
	public function test_is_available_with_zar_and_credentials() {
		$this->assertSame( array(), $this->gateway->check_requirements() );
		$this->assertTrue( $this->gateway->is_available() );
	}

	/**
	 * The gateway is unavailable when the store currency is not ZAR.
	 *
	 * @return void
	 */
	public function test_is_not_available_with_unsupported_currency() {
		update_option( 'woocommerce_currency', 'USD' );

		$this->assertContains( 'wc-gateway-payfast-error-invalid-currency', $this->gateway->check_requirements() );
		$this->assertFalse( $this->gateway->is_available() );
	}

	/**
	 * On the order-pay page, the order's currency decides availability, not the store currency.
	 *
	 * @return void
	 */
	public function test_is_not_available_on_order_pay_for_non_zar_order() {
		$order = wc_create_order();
		$order->set_currency( 'USD' );
		$order->save();

		set_query_var( 'order-pay', $order->get_id() );

		$this->assertFalse( $this->gateway->is_available() );
	}

	/**
	 * ITN data is valid when Payfast's validate endpoint answers VALID.
	 *
	 * @return void
	 */
	public function test_validate_response_data_accepts_valid_response() {
		$requested_url = null;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$requested_url ) {
				$requested_url = $url;
				return array(
					'headers'  => array(),
					'body'     => 'VALID',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$this->assertTrue( $this->gateway->validate_response_data( array( 'pf_payment_id' => '1' ) ) );
		$this->assertStringContainsString( 'payfast.co.za/eng/query/validate', $requested_url );
	}

	/**
	 * ITN data is rejected when the validation request fails, which is what an unmocked request does in tests.
	 *
	 * @return void
	 */
	public function test_validate_response_data_rejects_failed_request() {
		$this->assertFalse( $this->gateway->validate_response_data( array( 'pf_payment_id' => '1' ) ) );
	}
}
