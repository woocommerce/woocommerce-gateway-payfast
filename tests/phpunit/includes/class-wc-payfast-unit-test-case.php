<?php
/**
 * Base test case for the Payfast gateway tests.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * Shared setup and helpers for tests of WC_Gateway_PayFast.
 *
 * Every test starts with:
 * - ZAR as the store currency and sandbox settings with test credentials.
 * - Outbound HTTP blocked unless mocked with mock_http_response().
 * - The ITN sender DNS fallback switched off, so no test makes a real DNS lookup by accident.
 *   The bootstrap's HTTP block does not cover gethostbynamel(). A test of that fallback must
 *   filter woocommerce_gateway_payfast_valid_ip_hostnames itself and pre-fill the lookup cache.
 */
abstract class WC_Payfast_Unit_Test_Case extends WP_UnitTestCase {

	const MERCHANT_ID  = 'test-merchant-id';
	const MERCHANT_KEY = 'test-merchant-key';
	const PASS_PHRASE  = 'test-passphrase';

	/**
	 * An address inside Payfast's documented ITN range 197.97.145.144/28.
	 */
	const PAYFAST_ITN_IP = '197.97.145.150';

	/**
	 * An address from the TEST-NET-3 documentation block, which Payfast can never send from.
	 */
	const UNKNOWN_IP = '203.0.113.10';

	/**
	 * Gateway under test.
	 *
	 * @var WC_Gateway_PayFast
	 */
	protected $gateway;

	/**
	 * Outbound HTTP requests answered by a mock, each with `url` and `args`.
	 *
	 * @var array[]
	 */
	protected $http_requests = array();

	/**
	 * Mocked responses, each with `url_part`, `code`, and `body`.
	 *
	 * @var array[]
	 */
	private $http_responses = array();

	/**
	 * `$_SERVER` values that tests change, saved so they can be put back.
	 *
	 * WP_UnitTestCase resets `$_SERVER` only when running WordPress core's own tests, so this is not redundant.
	 *
	 * @var array
	 */
	private $server_backup = array();

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->http_requests  = array();
		$this->http_responses = array();
		$this->server_backup  = array_intersect_key( $_SERVER, array_flip( array( 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR' ) ) );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		// WP_UnitTestCase removes hooks added during a test, so these need no clean up.
		add_filter( 'pre_http_request', array( $this, 'answer_http_request' ), 10, 3 );
		add_filter( 'woocommerce_gateway_payfast_valid_ip_hostnames', '__return_empty_array' );

		update_option( 'woocommerce_currency', 'ZAR' );
		$this->update_gateway_settings();
	}

	/**
	 * Tear down each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$_SERVER = array_merge( $_SERVER, $this->server_backup ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		parent::tear_down();
	}

	/**
	 * Save gateway settings and load them into the gateway.
	 *
	 * The gateway's Payfast process and validate URLs are not updated. They are set from the sandbox
	 * setting when WooCommerce constructs the gateway, before any test settings exist, and sandbox mode
	 * defaults to on, so they always point at the sandbox. The API and credential checks read the
	 * setting on each request, so they do follow `testmode`.
	 *
	 * @param array $overrides Settings that differ from the defaults.
	 * @return void
	 */
	protected function update_gateway_settings( array $overrides = array() ): void {
		$settings = array_merge(
			array(
				'enabled'      => 'yes',
				'testmode'     => 'yes',
				'merchant_id'  => self::MERCHANT_ID,
				'merchant_key' => self::MERCHANT_KEY,
				'pass_phrase'  => self::PASS_PHRASE,
			),
			$overrides
		);
		update_option( 'woocommerce_payfast_settings', $settings );

		// The gateway defines constants when constructed, so a second instance would raise warnings.
		$this->gateway = WC()->payment_gateways()->payment_gateways()['payfast'];
		$this->gateway->init_settings();

		// The shared instance copies its credentials into properties when it is constructed, before any
		// test settings exist, and validate_payfast_credentials() overwrites one of them.
		$this->set_gateway_property( 'merchant_id', $settings['merchant_id'] );
		$this->set_gateway_property( 'merchant_key', $settings['merchant_key'] );
		$this->set_gateway_property( 'pass_phrase', $settings['pass_phrase'] );
		$this->gateway->enabled = 'yes' === $settings['enabled'] ? 'yes' : 'no';
	}

	/**
	 * Set a non-public property on the gateway.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Value.
	 * @return void
	 */
	protected function set_gateway_property( string $name, $value ): void {
		$property = new ReflectionProperty( WC_Gateway_PayFast::class, $name );

		// Needed before PHP 8.1 only, and deprecated from PHP 8.5.
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( $this->gateway, $value );
	}

	/**
	 * Make a filter return a fixed value, whatever earlier callbacks returned.
	 *
	 * @param string $hook_name Filter name.
	 * @param mixed  $value     Value to return.
	 * @return void
	 */
	protected function set_filter_result( string $hook_name, $value ): void {
		add_filter(
			$hook_name,
			function () use ( $value ) {
				return $value;
			},
			1000
		);
	}

	/**
	 * Answer outbound HTTP requests whose URL contains $url_part.
	 *
	 * When several mocks match a request, the one with the longest $url_part answers it, so a specific
	 * mock can be added alongside a broader one in any order. Only answered requests are recorded in
	 * $http_requests. Requests that no mock matches fail, which also keeps requests WooCommerce makes on
	 * its own out of $http_requests.
	 *
	 * @param string $url_part Part of the URL to match, for example a host and path.
	 * @param int    $code     HTTP status code.
	 * @param string $body     Response body.
	 * @return void
	 */
	protected function mock_http_response( string $url_part, int $code, string $body ): void {
		$this->http_responses[] = array(
			'url_part' => $url_part,
			'code'     => $code,
			'body'     => $body,
		);
	}

	/**
	 * Answer a request with the matching mocked response. Hooked to `pre_http_request`.
	 *
	 * @param false|array|WP_Error $preempt A preempted response, or false to continue.
	 * @param array                $args    HTTP request arguments.
	 * @param string               $url     The request URL.
	 * @return false|array|WP_Error
	 */
	public function answer_http_request( $preempt, $args, $url ) {
		if ( false !== $preempt ) {
			return $preempt;
		}

		$match = null;
		foreach ( $this->http_responses as $response ) {
			if ( false !== strpos( $url, $response['url_part'] ) && ( null === $match || strlen( $response['url_part'] ) > strlen( $match['url_part'] ) ) ) {
				$match = $response;
			}
		}

		if ( null === $match ) {
			return $preempt;
		}

		$this->http_requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		return array(
			'headers'  => array(),
			'body'     => $match['body'],
			'response' => array(
				'code'    => $match['code'],
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Build a Payfast API response body.
	 *
	 * @param mixed  $data   The `data` member.
	 * @param string $status `success` or `failed`.
	 * @param int    $code   The `code` member, which Payfast sets to the HTTP status.
	 * @return string JSON.
	 */
	protected function payfast_api_response( $data, string $status = 'success', int $code = 200 ): string {
		return wp_json_encode(
			array(
				'code'   => $code,
				'status' => $status,
				'data'   => $data,
			)
		);
	}

	/**
	 * Create a saved order paid with Payfast.
	 *
	 * @param array $props Order properties that differ from the defaults.
	 * @return WC_Order
	 */
	protected function create_order( array $props = array() ): WC_Order {
		$order = wc_create_order();
		$order->set_props(
			array_merge(
				array(
					'currency'           => 'ZAR',
					'payment_method'     => 'payfast',
					'total'              => '100.00',
					'billing_first_name' => 'Thandi',
					'billing_last_name'  => 'Nkosi',
					'billing_email'      => 'thandi@example.com',
				),
				$props
			)
		);
		$order->save();

		return $order;
	}
}
