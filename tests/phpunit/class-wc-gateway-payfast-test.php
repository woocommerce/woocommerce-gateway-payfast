<?php
/**
 * Tests for WC_Gateway_PayFast.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * WC_Gateway_PayFast tests.
 */
class WC_Gateway_PayFast_Test extends WC_Payfast_Unit_Test_Case {

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
		$order = $this->create_order( array( 'currency' => 'USD' ) );

		set_query_var( 'order-pay', $order->get_id() );

		$this->assertFalse( $this->gateway->is_available() );
	}
}
