<?php
/**
 * Tests for the Payfast redirect form in WC_Gateway_PayFast.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * WC_Gateway_PayFast::generate_payfast_form() tests.
 */
class WC_Gateway_PayFast_Form_Test extends WC_Payfast_Unit_Test_Case {

	/**
	 * Render the form for an order, as the order-pay page does.
	 *
	 * @param WC_Order    $order     Order.
	 * @param string|null $order_key Key in the URL; the order's own key when null.
	 * @return string Form HTML.
	 */
	private function render_form( WC_Order $order, ?string $order_key = null ): string {
		if ( null !== $order_key ) {
			$_GET['key'] = $order_key;
		} else {
			$_GET['key'] = $order->get_order_key();
		}

		ob_start();
		$this->gateway->generate_payfast_form( $order->get_id() );
		return ob_get_clean();
	}

	/**
	 * Read the hidden fields from the form, in the order they are posted.
	 *
	 * @param string $html Form HTML.
	 * @return array Field values by name.
	 */
	private function get_fields( string $html ): array {
		preg_match_all( '/<input type="hidden" name="([^"]+)" value="([^"]*)" \/>/', $html, $matches, PREG_SET_ORDER );

		$fields = array();
		foreach ( $matches as $match ) {
			$fields[ $match[1] ] = html_entity_decode( $match[2], ENT_QUOTES );
		}

		return $fields;
	}

	/**
	 * Compute the signature Payfast expects for the posted fields.
	 *
	 * @param array $fields Posted fields.
	 * @return string
	 */
	private function expected_signature( array $fields ): string {
		// The gateway posts `source` but leaves it out of the signature.
		unset( $fields['source'] );

		return WC_Payfast_Test_Signature::sign_posted_data( $fields, self::PASS_PHRASE );
	}

	/**
	 * The form posts the order to the Payfast sandbox with a valid signature.
	 *
	 * @return void
	 */
	public function test_form_posts_signed_order_data_to_payfast(): void {
		$order = $this->create_order();

		$html   = $this->render_form( $order );
		$fields = $this->get_fields( $html );

		$this->assertStringContainsString( 'action="https://sandbox.payfast.co.za/eng/process?aff=woo-free"', $html );
		$this->assertSame( self::MERCHANT_ID, $fields['merchant_id'] );
		$this->assertSame( self::MERCHANT_KEY, $fields['merchant_key'] );
		$this->assertSame( '100.00', $fields['amount'] );
		$this->assertSame( $order->get_order_key(), $fields['custom_str1'] );
		$this->assertSame( (string) $order->get_id(), $fields['custom_str3'] );
		$this->assertSame( $order->get_billing_email(), $fields['email_address'] );
		$this->assertStringContainsString( 'wc-api=WC_Gateway_PayFast', $fields['notify_url'] );
		$this->assertSame( $this->expected_signature( $fields ), $fields['signature'] );
	}

	/**
	 * Fields added through the payment data filter are posted and signed.
	 *
	 * @return void
	 */
	public function test_payment_data_filter_changes_are_signed(): void {
		add_filter(
			'woocommerce_gateway_payfast_payment_data_to_send',
			function ( $data ) {
				$data['custom_int1'] = '42';
				return $data;
			}
		);
		$order = $this->create_order();

		$fields = $this->get_fields( $this->render_form( $order ) );

		$this->assertSame( '42', $fields['custom_int1'] );
		$this->assertSame( $this->expected_signature( $fields ), $fields['signature'] );
	}

	/**
	 * Payfast rejects relative return URLs, so one filtered to a relative path is rebuilt as absolute.
	 *
	 * @return void
	 */
	public function test_relative_return_url_is_made_absolute(): void {
		$this->set_filter_result( 'woocommerce_get_return_url', '/order-received/123/' );
		$order = $this->create_order();

		$fields = $this->get_fields( $this->render_form( $order ) );

		$this->assertMatchesRegularExpression( '#^https?://#', $fields['return_url'] );
		$this->assertStringContainsString( 'key=' . $order->get_order_key(), $fields['return_url'] );
		$this->assertStringContainsString( (string) $order->get_id(), $fields['return_url'] );
	}

	/**
	 * Without the order key in the URL, the order details are not printed.
	 *
	 * @return void
	 */
	public function test_form_not_rendered_with_wrong_order_key(): void {
		$order = $this->create_order();

		$html = $this->render_form( $order, 'wc_order_wrongkey' );

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( 'not allowed to pay for this order', $html );
	}

	/**
	 * A logged-in customer cannot pay another customer's order, even with its key.
	 *
	 * @return void
	 */
	public function test_form_not_rendered_for_another_customers_order(): void {
		$owner = self::factory()->user->create( array( 'role' => 'customer' ) );
		$other = self::factory()->user->create( array( 'role' => 'customer' ) );
		$order = $this->create_order( array( 'customer_id' => $owner ) );
		wp_set_current_user( $other );

		$html = $this->render_form( $order );

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( 'not allowed to pay for this order', $html );
	}

	/**
	 * Amounts are sent without a currency, so an order in another currency must not reach Payfast.
	 *
	 * @return void
	 */
	public function test_form_not_rendered_for_non_zar_order(): void {
		$order = $this->create_order( array( 'currency' => 'USD' ) );

		$html = $this->render_form( $order );

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( 'South African Rand', $html );
	}
}
