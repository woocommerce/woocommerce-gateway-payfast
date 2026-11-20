<?php
/**
 * Tests for Payfast API requests in WC_Gateway_PayFast.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * WC_Gateway_PayFast::api_request() tests.
 */
class WC_Gateway_PayFast_API_Test extends WC_Payfast_Unit_Test_Case {

	const API_URL = 'https://api.payfast.co.za/';

	/**
	 * Mock the Payfast API.
	 *
	 * @param string $body Response body.
	 * @param int    $code HTTP status code.
	 * @return void
	 */
	private function mock_api( string $body, int $code = 200 ): void {
		$this->mock_http_response( self::API_URL, $code, $body );
	}

	/**
	 * Compute the signature Payfast expects for a request.
	 *
	 * @param array $args Request arguments as sent.
	 * @return string
	 */
	private function expected_signature( array $args ): string {
		return WC_Payfast_Test_Signature::api( array_merge( $args['headers'], $args['body'] ?? array() ), self::PASS_PHRASE );
	}

	/**
	 * An ad hoc charge is posted in cents to the subscription token, signed, in sandbox mode.
	 *
	 * @return void
	 */
	public function test_ad_hoc_payment_request(): void {
		$this->mock_api(
			$this->payfast_api_response(
				array(
					'response' => true,
					'message'  => 'Transaction was successful',
				)
			)
		);

		$result = $this->gateway->submit_ad_hoc_payment( 'test-token', 99.5, 'Monthly box', '{"renewal_order_id":12}' );

		$this->assertTrue( $result );
		$this->assertCount( 1, $this->http_requests );

		$request = $this->http_requests[0];
		$this->assertSame( self::API_URL . 'subscriptions/test-token/adhoc?testing=true', $request['url'] );
		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertEquals( 9950, $request['args']['body']['amount'] );
		$this->assertSame( 'Monthly box', $request['args']['body']['item_name'] );
		$this->assertSame( self::MERCHANT_ID, $request['args']['headers']['merchant-id'] );
		$this->assertSame( 'v1', $request['args']['headers']['version'] );
		$this->assertSame( $this->expected_signature( $request['args'] ), $request['args']['headers']['signature'] );
	}

	/**
	 * In live mode, requests go to the live API.
	 *
	 * @return void
	 */
	public function test_live_mode_request_omits_testing_flag(): void {
		$this->update_gateway_settings( array( 'testmode' => 'no' ) );
		$this->mock_api( $this->payfast_api_response( array( 'response' => true ) ) );

		$this->gateway->submit_ad_hoc_payment( 'test-token', 10, 'Item', 'Description' );

		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( self::API_URL . 'subscriptions/test-token/adhoc', $this->http_requests[0]['url'] );
	}

	/**
	 * The sandbox answers ad hoc charges with a boolean and the live API with a string.
	 *
	 * @return void
	 */
	public function test_ad_hoc_payment_accepts_string_true(): void {
		$this->mock_api( $this->payfast_api_response( array( 'response' => 'true' ) ) );

		$this->assertTrue( $this->gateway->submit_ad_hoc_payment( 'test-token', 10, 'Item', 'Description' ) );
	}

	/**
	 * A declined ad hoc charge returns the reason, trimmed for use in an order note.
	 *
	 * @return void
	 */
	public function test_declined_ad_hoc_payment_returns_error(): void {
		$this->mock_api(
			$this->payfast_api_response(
				array(
					'response' => array(
						'code'   => 'P001',
						'reason' => "Insufficient funds\r\n",
					),
				)
			)
		);

		$result = $this->gateway->submit_ad_hoc_payment( 'test-token', 10, 'Item', 'Description' );

		$this->assertWPError( $result );
		$this->assertSame( 'P001', $result->get_error_code() );
		$this->assertSame( 'Insufficient funds', $result->get_error_message() );
	}

	/**
	 * A non-200 response returns an error carrying Payfast's message.
	 *
	 * @return void
	 */
	public function test_http_error_returns_error(): void {
		$this->mock_api(
			$this->payfast_api_response(
				array(
					'response' => 'Invalid token',
					'message'  => false,
				),
				'failed',
				400
			),
			400
		);

		$result = $this->gateway->submit_ad_hoc_payment( 'test-token', 10, 'Item', 'Description' );

		$this->assertWPError( $result );
		$this->assertSame( 400, $result->get_error_code() );
		$this->assertSame( 'Invalid token', $result->get_error_message() );
	}

	/**
	 * A failed request is returned as is.
	 *
	 * @return void
	 */
	public function test_transport_error_is_returned(): void {
		// No mock: the test bootstrap fails every unmocked request.
		$result = $this->gateway->submit_ad_hoc_payment( 'test-token', 10, 'Item', 'Description' );

		$this->assertWPError( $result );
		$this->assertSame( 'wc_payfast_tests_http_blocked', $result->get_error_code() );
	}

	/**
	 * Cancelling a token sends a signed PUT with a zero content length, which Payfast requires.
	 *
	 * @return void
	 */
	public function test_cancel_request(): void {
		$this->mock_api( $this->payfast_api_response( array( 'response' => true ) ) );

		$this->assertTrue( $this->gateway->cancel_pre_order_subscription( 'test-token' ) );

		$request = $this->http_requests[0];
		$this->assertSame( self::API_URL . 'subscriptions/test-token/cancel?testing=true', $request['url'] );
		$this->assertSame( 'PUT', $request['args']['method'] );
		$this->assertSame( 0, $request['args']['headers']['content-length'] );
		$this->assertSame( $this->expected_signature( $request['args'] ), $request['args']['headers']['signature'] );
	}

	/**
	 * A 200 response with a failed status is still an error.
	 *
	 * @return void
	 */
	public function test_failed_status_returns_error(): void {
		$this->mock_api(
			$this->payfast_api_response(
				array(
					'response' => false,
					'message'  => "Subscription already cancelled\n",
				),
				'failed',
				400
			)
		);

		$result = $this->gateway->cancel_pre_order_subscription( 'test-token' );

		$this->assertWPError( $result );
		$this->assertSame( 'Subscription already cancelled', $result->get_error_message() );
	}
}
