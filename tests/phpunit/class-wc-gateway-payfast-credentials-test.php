<?php
/**
 * Tests for credential handling in WC_Gateway_PayFast.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * Credential protection and validation tests.
 */
class WC_Gateway_PayFast_Credentials_Test extends WC_Payfast_Unit_Test_Case {

	private const PING_URL = 'https://api.payfast.co.za/ping';

	/**
	 * Log in a user with the given role.
	 *
	 * @param string $role Role.
	 * @return void
	 */
	private function log_in_as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Mock Payfast's ping endpoint, which checks the credentials.
	 *
	 * @param int $code HTTP status code.
	 * @return void
	 */
	private function mock_ping( int $code ): void {
		$this->mock_http_response(
			self::PING_URL,
			$code,
			$this->payfast_api_response( array(), 200 === $code ? 'success' : 'failed', $code )
		);
	}

	/**
	 * Settings with every credential changed, plus a changed title.
	 *
	 * @return array
	 */
	private function get_changed_settings(): array {
		return array_merge(
			get_option( 'woocommerce_payfast_settings' ),
			array(
				'title'        => 'Changed title',
				'testmode'     => 'no',
				'merchant_id'  => 'other-merchant-id',
				'merchant_key' => 'other-merchant-key',
				'pass_phrase'  => 'other-passphrase',
			)
		);
	}

	/**
	 * A shop manager can save the other settings but not the credentials or sandbox mode.
	 *
	 * @return void
	 */
	public function test_shop_manager_cannot_change_credentials(): void {
		$this->log_in_as( 'shop_manager' );

		update_option( 'woocommerce_payfast_settings', $this->get_changed_settings() );

		$saved = get_option( 'woocommerce_payfast_settings' );
		$this->assertSame( 'Changed title', $saved['title'] );
		$this->assertSame( 'yes', $saved['testmode'] );
		$this->assertSame( self::MERCHANT_ID, $saved['merchant_id'] );
		$this->assertSame( self::MERCHANT_KEY, $saved['merchant_key'] );
		$this->assertSame( self::PASS_PHRASE, $saved['pass_phrase'] );
	}

	/**
	 * An administrator can change the credentials.
	 *
	 * @return void
	 */
	public function test_administrator_can_change_credentials(): void {
		$this->log_in_as( 'administrator' );

		update_option( 'woocommerce_payfast_settings', $this->get_changed_settings() );

		$saved = get_option( 'woocommerce_payfast_settings' );
		$this->assertSame( 'other-merchant-id', $saved['merchant_id'] );
		$this->assertSame( 'other-passphrase', $saved['pass_phrase'] );
	}

	/**
	 * A non-array settings value from a user without access keeps the saved settings.
	 *
	 * @return void
	 */
	public function test_shop_manager_cannot_replace_settings_with_non_array(): void {
		$this->log_in_as( 'shop_manager' );
		$old = get_option( 'woocommerce_payfast_settings' );

		$this->assertSame( $old, $this->gateway->protect_credential_settings( 'not-an-array', $old ) );
	}

	/**
	 * Credentials are limited to manage_options by default.
	 *
	 * @return void
	 */
	public function test_shop_manager_cannot_manage_credentials_by_default(): void {
		$this->log_in_as( 'shop_manager' );

		$this->assertFalse( $this->gateway->can_manage_credentials() );
	}

	/**
	 * Credentials capability filter results and whether a shop manager can then manage the credentials.
	 *
	 * @return array[]
	 */
	public static function data_capability_filter_results(): array {
		return array(
			'capability shop managers have' => array( 'manage_woocommerce', true ),
			'empty string'                  => array( '', false ),
			'not a string'                  => array( array( 'manage_woocommerce' ), false ),
		);
	}

	/**
	 * The credentials capability filter can grant access, and an invalid return falls back to manage_options.
	 *
	 * @dataProvider data_capability_filter_results
	 *
	 * @param mixed $capability Filter return value.
	 * @param bool  $expected   Whether a shop manager can manage the credentials.
	 * @return void
	 */
	public function test_credentials_capability_filter( $capability, bool $expected ): void {
		$this->log_in_as( 'shop_manager' );
		$this->set_filter_result( 'woocommerce_gateway_payfast_credentials_capability', $capability );

		$this->assertSame( $expected, $this->gateway->can_manage_credentials() );
	}

	/**
	 * Submitting the settings form with a blank passphrase keeps the saved one, since the field is never pre-filled.
	 *
	 * @return void
	 */
	public function test_blank_passphrase_keeps_saved_passphrase(): void {
		$this->log_in_as( 'administrator' );

		$this->assertSame( self::PASS_PHRASE, $this->gateway->validate_pass_phrase_field( 'pass_phrase', '' ) );
		$this->assertSame( self::PASS_PHRASE, $this->gateway->validate_pass_phrase_field( 'pass_phrase', '   ' ) );
		$this->assertSame( 'new-passphrase', $this->gateway->validate_pass_phrase_field( 'pass_phrase', 'new-passphrase' ) );
	}

	/**
	 * Submitted credential fields from a user without access are replaced with the saved values.
	 *
	 * @return void
	 */
	public function test_credential_fields_ignore_values_from_shop_manager(): void {
		$this->log_in_as( 'shop_manager' );

		$this->assertSame( self::MERCHANT_ID, $this->gateway->validate_merchant_id_field( 'merchant_id', 'other' ) );
		$this->assertSame( self::MERCHANT_KEY, $this->gateway->validate_merchant_key_field( 'merchant_key', 'other' ) );
		$this->assertSame( self::PASS_PHRASE, $this->gateway->validate_pass_phrase_field( 'pass_phrase', 'other' ) );
		$this->assertSame( 'yes', $this->gateway->validate_testmode_field( 'testmode', null ) );
	}

	/**
	 * The saved passphrase is never printed in the settings form.
	 *
	 * @return void
	 */
	public function test_passphrase_field_does_not_print_saved_value(): void {
		$this->log_in_as( 'administrator' );

		$html = $this->gateway->generate_password_html( 'pass_phrase', $this->gateway->get_form_fields()['pass_phrase'] );

		$this->assertStringNotContainsString( self::PASS_PHRASE, $html );
		$this->assertStringContainsString( 'A passphrase is saved', $html );
		$this->assertSame( self::PASS_PHRASE, $this->gateway->get_option( 'pass_phrase' ) );
	}

	/**
	 * Build a REST response for the gateway settings.
	 *
	 * @return WP_REST_Response
	 */
	private function get_rest_response(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'id'       => 'payfast',
				'settings' => array(
					'pass_phrase' => array( 'value' => self::PASS_PHRASE ),
				),
			)
		);
	}

	/**
	 * The REST API hides the passphrase from users who cannot manage the credentials.
	 *
	 * @return void
	 */
	public function test_rest_response_redacts_passphrase_for_shop_manager(): void {
		$this->log_in_as( 'shop_manager' );

		$response = $this->gateway->redact_rest_credential_settings( $this->get_rest_response(), $this->gateway );

		$this->assertSame( '', $response->get_data()['settings']['pass_phrase']['value'] );
	}

	/**
	 * The REST API shows the passphrase to administrators.
	 *
	 * @return void
	 */
	public function test_rest_response_keeps_passphrase_for_administrator(): void {
		$this->log_in_as( 'administrator' );

		$response = $this->gateway->redact_rest_credential_settings( $this->get_rest_response(), $this->gateway );

		$this->assertSame( self::PASS_PHRASE, $response->get_data()['settings']['pass_phrase']['value'] );
	}

	/**
	 * Credentials Payfast rejects are flagged, which makes the gateway unavailable.
	 *
	 * @return void
	 */
	public function test_rejected_credentials_are_flagged(): void {
		$this->mock_ping( 401 );

		$this->gateway->validate_payfast_credentials( array(), $this->get_changed_settings() );

		$this->assertSame( 'yes', get_option( 'woocommerce_payfast_invalid_credentials' ) );
		$this->assertContains( 'wc-gateway-payfast-error-invalid-credentials', $this->gateway->check_requirements() );
		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( self::PING_URL, $this->http_requests[0]['url'] );
		$this->assertSame( 'other-merchant-id', $this->http_requests[0]['args']['headers']['merchant-id'] );
	}

	/**
	 * Accepted credentials clear an earlier invalid flag.
	 *
	 * @return void
	 */
	public function test_accepted_credentials_clear_flag(): void {
		update_option( 'woocommerce_payfast_invalid_credentials', 'yes' );
		$this->mock_ping( 200 );

		$this->gateway->validate_payfast_credentials( array(), get_option( 'woocommerce_payfast_settings' ) );

		$this->assertFalse( get_option( 'woocommerce_payfast_invalid_credentials' ) );
		$this->assertSame( self::PING_URL . '?testing=true', $this->http_requests[0]['url'] );
	}

	/**
	 * Saving without changing the credentials does not call Payfast again.
	 *
	 * @return void
	 */
	public function test_unchanged_credentials_are_not_checked_again(): void {
		$this->mock_ping( 401 );
		$settings = get_option( 'woocommerce_payfast_settings' );

		$this->gateway->validate_payfast_credentials( $settings, array_merge( $settings, array( 'title' => 'Changed title' ) ) );

		$this->assertCount( 0, $this->http_requests );
		$this->assertFalse( get_option( 'woocommerce_payfast_invalid_credentials' ) );
	}
}
