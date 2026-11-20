<?php
/**
 * Tests for ITN handling in WC_Gateway_PayFast.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * WC_Gateway_PayFast::handle_itn_request() and validate_response_data() tests.
 */
class WC_Gateway_PayFast_ITN_Test extends WC_Payfast_Unit_Test_Case {

	/**
	 * Matched by path, because the gateway's validate URL is always the sandbox one in tests.
	 */
	private const VALIDATE_PATH = '/eng/query/validate';

	private const DEFAULT_PAYMENT_ID = '1234567';
	private const DEFAULT_AMOUNT_FEE = '-2.30';
	private const DEFAULT_AMOUNT_NET = '97.70';

	/**
	 * Build signed ITN data for an order, as Payfast would post it.
	 *
	 * @param WC_Order $order     Order the ITN is for.
	 * @param array    $overrides Fields that differ from a complete payment of the full total.
	 * @return array
	 */
	private function build_itn_data( WC_Order $order, array $overrides = array() ): array {
		$data = array_merge(
			array(
				'm_payment_id'     => (string) $order->get_id(),
				'pf_payment_id'    => self::DEFAULT_PAYMENT_ID,
				'payment_status'   => 'COMPLETE',
				'item_name'        => 'Test Store - ' . $order->get_order_number(),
				'item_description' => 'New order from Test Store',
				'amount_gross'     => $order->get_total(),
				'amount_fee'       => self::DEFAULT_AMOUNT_FEE,
				'amount_net'       => self::DEFAULT_AMOUNT_NET,
				'custom_str1'      => $order->get_order_key(),
				'custom_str2'      => '',
				'custom_str3'      => (string) $order->get_id(),
				'name_first'       => $order->get_billing_first_name(),
				'name_last'        => $order->get_billing_last_name(),
				'email_address'    => $order->get_billing_email(),
				'merchant_id'      => self::MERCHANT_ID,
			),
			$overrides
		);

		$data['signature'] = WC_Payfast_Test_Signature::sign_posted_data( $data, self::PASS_PHRASE );
		return $data;
	}

	/**
	 * Mock Payfast's validate endpoint.
	 *
	 * @param string $answer Response body, VALID or INVALID.
	 * @return void
	 */
	private function mock_payfast_validation( string $answer = 'VALID' ): void {
		$this->mock_http_response( self::VALIDATE_PATH, 200, $answer );
	}

	/**
	 * A valid COMPLETE ITN pays the order and stores the Payfast fee and net amount.
	 *
	 * @return void
	 */
	public function test_complete_itn_pays_order(): void {
		$this->mock_payfast_validation();
		$order       = $this->create_order();
		$actions_ran = did_action( 'woocommerce_payfast_handle_itn_payment_complete' );

		$this->gateway->handle_itn_request( $this->build_itn_data( $order ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->is_paid() );
		$this->assertSame( self::DEFAULT_PAYMENT_ID, $order->get_transaction_id() );
		$this->assertSame( self::DEFAULT_AMOUNT_FEE, $order->get_meta( 'payfast_amount_fee' ) );
		$this->assertSame( self::DEFAULT_AMOUNT_NET, $order->get_meta( 'payfast_amount_net' ) );
		$this->assertSame( $actions_ran + 1, did_action( 'woocommerce_payfast_handle_itn_payment_complete' ) );
	}

	/**
	 * The data is confirmed with Payfast's sandbox validate endpoint, without the signature.
	 *
	 * @return void
	 */
	public function test_itn_data_is_validated_with_payfast(): void {
		$this->mock_payfast_validation();
		$order = $this->create_order();

		$this->gateway->handle_itn_request( $this->build_itn_data( $order ) );

		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'https://sandbox.payfast.co.za/eng/query/validate', $this->http_requests[0]['url'] );
		$this->assertArrayNotHasKey( 'signature', $this->http_requests[0]['args']['body'] );
		$this->assertSame( '1234567', $this->http_requests[0]['args']['body']['pf_payment_id'] );
	}

	/**
	 * Data changed after signing is rejected before Payfast is asked to validate it.
	 *
	 * @return void
	 */
	public function test_itn_with_invalid_signature_is_rejected(): void {
		$this->mock_payfast_validation();
		$order = $this->create_order();

		$data                 = $this->build_itn_data( $order );
		$data['amount_gross'] = '1.00';
		$this->gateway->handle_itn_request( $data );

		$this->assertFalse( wc_get_order( $order->get_id() )->is_paid() );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * An ITN that Payfast does not confirm is rejected.
	 *
	 * @return void
	 */
	public function test_itn_not_confirmed_by_payfast_is_rejected(): void {
		$this->mock_payfast_validation( 'INVALID' );
		$order = $this->create_order();

		$this->gateway->handle_itn_request( $this->build_itn_data( $order ) );

		$this->assertFalse( wc_get_order( $order->get_id() )->is_paid() );
	}

	/**
	 * Invalid ITNs that are signed correctly and confirmed by Payfast.
	 *
	 * @return array[]
	 */
	public static function data_rejected_itn_overrides(): array {
		return array(
			'amount below the order total' => array( array( 'amount_gross' => '50.00' ) ),
			'amount above the order total' => array( array( 'amount_gross' => '100.02' ) ),
			'wrong order key'              => array( array( 'custom_str1' => 'wc_order_wrongkey' ) ),
		);
	}

	/**
	 * A signed and confirmed ITN is still rejected when it does not match the order.
	 *
	 * @dataProvider data_rejected_itn_overrides
	 *
	 * @param array $overrides ITN fields.
	 * @return void
	 */
	public function test_itn_not_matching_order_is_rejected( array $overrides ): void {
		$this->mock_payfast_validation();
		$order = $this->create_order();

		$this->gateway->handle_itn_request( $this->build_itn_data( $order, $overrides ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertFalse( $order->is_paid() );
		$this->assertSame( 'pending', $order->get_status() );
	}

	/**
	 * Amounts within a cent of the order total are accepted, so float rounding cannot fail a payment.
	 *
	 * @return void
	 */
	public function test_itn_amount_within_a_cent_is_accepted(): void {
		$this->mock_payfast_validation();
		$order = $this->create_order();

		$this->gateway->handle_itn_request( $this->build_itn_data( $order, array( 'amount_gross' => '100.001' ) ) );

		$this->assertTrue( wc_get_order( $order->get_id() )->is_paid() );
	}

	/**
	 * Payfast only charges ZAR, so an ITN for an order in another currency is rejected.
	 *
	 * @return void
	 */
	public function test_itn_for_non_zar_order_is_rejected(): void {
		$this->mock_payfast_validation();
		$order = $this->create_order( array( 'currency' => 'USD' ) );

		$this->gateway->handle_itn_request( $this->build_itn_data( $order ) );

		$this->assertFalse( wc_get_order( $order->get_id() )->is_paid() );
	}

	/**
	 * Non-complete payment statuses and the order status each one leads to.
	 *
	 * @return array[]
	 */
	public static function data_payment_statuses(): array {
		return array(
			'failed'  => array( 'FAILED', 'failed' ),
			'pending' => array( 'PENDING', 'on-hold' ),
		);
	}

	/**
	 * Failed and pending ITNs update the order status without paying it.
	 *
	 * @dataProvider data_payment_statuses
	 *
	 * @param string $payment_status  Payfast payment status.
	 * @param string $expected_status Expected order status.
	 * @return void
	 */
	public function test_itn_payment_status_updates_order( string $payment_status, string $expected_status ): void {
		$this->mock_payfast_validation();
		$order = $this->create_order();

		$this->gateway->handle_itn_request( $this->build_itn_data( $order, array( 'payment_status' => $payment_status ) ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( $expected_status, $order->get_status() );
		$this->assertFalse( $order->is_paid() );
	}

	/**
	 * A completed order is not changed by a later ITN, for example a replayed or delayed one.
	 *
	 * @return void
	 */
	public function test_itn_for_completed_order_is_ignored(): void {
		$this->mock_payfast_validation();
		$order = $this->create_order( array( 'status' => 'completed' ) );

		$this->gateway->handle_itn_request( $this->build_itn_data( $order, array( 'payment_status' => 'FAILED' ) ) );

		$this->assertSame( 'completed', wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * In live mode, an ITN from an address outside Payfast's ranges is rejected before validation.
	 *
	 * @return void
	 */
	public function test_live_mode_rejects_itn_from_unknown_ip(): void {
		$this->update_gateway_settings( array( 'testmode' => 'no' ) );
		$this->mock_payfast_validation();
		$order                  = $this->create_order();
		$_SERVER['REMOTE_ADDR'] = self::UNKNOWN_IP;

		$this->gateway->handle_itn_request( $this->build_itn_data( $order ) );

		$this->assertFalse( wc_get_order( $order->get_id() )->is_paid() );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * In live mode, an ITN from a documented Payfast address is processed.
	 *
	 * @return void
	 */
	public function test_live_mode_accepts_itn_from_payfast_ip(): void {
		$this->update_gateway_settings( array( 'testmode' => 'no' ) );
		$this->mock_payfast_validation();
		$order                  = $this->create_order();
		$_SERVER['REMOTE_ADDR'] = self::PAYFAST_ITN_IP;

		$this->gateway->handle_itn_request( $this->build_itn_data( $order ) );

		$this->assertTrue( wc_get_order( $order->get_id() )->is_paid() );
	}

	/**
	 * ITN data is valid when Payfast's validate endpoint answers VALID.
	 *
	 * @return void
	 */
	public function test_validate_response_data_accepts_valid_response(): void {
		$this->mock_payfast_validation();

		$this->assertTrue( $this->gateway->validate_response_data( array( 'pf_payment_id' => '1' ) ) );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * ITN data is rejected when the validation request fails, which is what an unmocked request does in tests.
	 *
	 * @return void
	 */
	public function test_validate_response_data_rejects_failed_request(): void {
		$this->assertFalse( $this->gateway->validate_response_data( array( 'pf_payment_id' => '1' ) ) );
	}
}
