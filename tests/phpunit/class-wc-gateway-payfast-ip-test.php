<?php
/**
 * Tests for the ITN source IP checks in WC_Gateway_PayFast.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * ITN source IP tests.
 */
class WC_Gateway_PayFast_IP_Test extends WC_Payfast_Unit_Test_Case {

	/**
	 * Addresses, ranges, and whether the address is in the range.
	 *
	 * @return array[]
	 */
	public static function data_ip_in_range(): array {
		return array(
			'address before range'      => array( '197.97.145.143', '197.97.145.144/28', false ),
			'network address'           => array( '197.97.145.144', '197.97.145.144/28', true ),
			'last address in range'     => array( '197.97.145.159', '197.97.145.144/28', true ),
			'first address after range' => array( '197.97.145.160', '197.97.145.144/28', false ),
			'bare address matches'      => array( '13.245.74.88', '13.245.74.88', true ),
			'bare address is one host'  => array( '13.245.74.89', '13.245.74.88', false ),
			'address before bare host'  => array( '13.245.74.87', '13.245.74.88', false ),
			'/0 covers every address'   => array( '8.8.8.8', '0.0.0.0/0', true ),
			'zero padded prefix'        => array( '10.1.2.3', '10.0.0.0/08', false ),
			'prefix above 32'           => array( '10.0.0.1', '10.0.0.0/33', false ),
			'empty prefix'              => array( '10.0.0.1', '10.0.0.0/', false ),
			'malformed range'           => array( '10.0.0.1', 'not-a-range', false ),
			'IPv6 address'              => array( '::ffff:a00:1', '10.0.0.0/8', false ),
			'malformed address'         => array( 'not-an-ip', '10.0.0.0/8', false ),
			'non-string address'        => array( null, '10.0.0.0/8', false ),
		);
	}

	/**
	 * Addresses are matched against CIDR ranges, and malformed input never matches.
	 *
	 * @dataProvider data_ip_in_range
	 *
	 * @param mixed  $ip       Address.
	 * @param string $range    Range.
	 * @param bool   $expected Whether the address is in the range.
	 * @return void
	 */
	public function test_is_ip_in_range( $ip, string $range, bool $expected ): void {
		$this->assertSame( $expected, $this->gateway->is_ip_in_range( $ip, $range ) );
	}

	/**
	 * Malformed entries returned by the ranges filter are dropped and the rest are kept.
	 *
	 * @return void
	 */
	public function test_valid_ip_ranges_filter_drops_malformed_entries(): void {
		$this->set_filter_result( 'woocommerce_gateway_payfast_valid_ip_ranges', array( '10.0.0.0/8', 123, 'bogus', '10.0.0.0/08', ' 192.168.1.1 ' ) );

		$this->assertSame( array( '10.0.0.0/8', '192.168.1.1' ), $this->gateway->get_valid_ip_ranges() );
	}

	/**
	 * Filter results that would leave no usable range.
	 *
	 * @return array[]
	 */
	public static function data_unusable_ranges(): array {
		return array(
			'not an array'          => array( '10.0.0.0/8' ),
			'empty array'           => array( array() ),
			'only malformed values' => array( array( 'bogus', false ) ),
		);
	}

	/**
	 * A filter result with no usable range falls back to the shipped ranges instead of rejecting every ITN.
	 *
	 * @dataProvider data_unusable_ranges
	 *
	 * @param mixed $filtered Filter return value.
	 * @return void
	 */
	public function test_valid_ip_ranges_falls_back_to_defaults( $filtered ): void {
		$this->set_filter_result( 'woocommerce_gateway_payfast_valid_ip_ranges', $filtered );

		$this->assertContains( '197.97.145.144/28', $this->gateway->get_valid_ip_ranges() );
	}

	/**
	 * Hostname filter results that are used, and the hostnames they leave.
	 *
	 * @return array[]
	 */
	public static function data_usable_hostnames(): array {
		return array(
			'empty array switches the lookup off' => array( array(), array() ),
			'malformed entries are dropped'       => array( array( 'itn.example.com', 'bad host', 42, '-bad.example.com' ), array( 'itn.example.com' ) ),
		);
	}

	/**
	 * The hostnames filter can switch the DNS fallback off or replace the hostnames, dropping malformed ones.
	 *
	 * @dataProvider data_usable_hostnames
	 *
	 * @param array    $filtered Filter return value.
	 * @param string[] $expected Hostnames used.
	 * @return void
	 */
	public function test_valid_ip_hostnames_filter( array $filtered, array $expected ): void {
		$this->set_filter_result( 'woocommerce_gateway_payfast_valid_ip_hostnames', $filtered );

		$this->assertSame( $expected, $this->gateway->get_valid_ip_hostnames() );
	}

	/**
	 * Hostname filter results that leave no usable hostname.
	 *
	 * @return array[]
	 */
	public static function data_unusable_hostnames(): array {
		return array(
			'not an array'          => array( false ),
			'only malformed values' => array( array( 'bad host', 42 ) ),
		);
	}

	/**
	 * A malformed hostnames filter result falls back to the shipped hostnames instead of switching the lookup off.
	 *
	 * @dataProvider data_unusable_hostnames
	 *
	 * @param mixed $filtered Filter return value.
	 * @return void
	 */
	public function test_valid_ip_hostnames_falls_back_to_defaults( $filtered ): void {
		$this->set_filter_result( 'woocommerce_gateway_payfast_valid_ip_hostnames', $filtered );

		$this->assertContains( 'www.payfast.co.za', $this->gateway->get_valid_ip_hostnames() );
	}

	/**
	 * Addresses and whether Payfast's documented ranges accept them.
	 *
	 * @return array[]
	 */
	public static function data_documented_ranges(): array {
		return array(
			'documented ITN range'    => array( self::PAYFAST_ITN_IP, true ),
			'second documented range' => array( '41.74.179.200', true ),
			'CloudFront pool address' => array( '3.163.240.237', true ),
			'unknown address'         => array( self::UNKNOWN_IP, false ),
		);
	}

	/**
	 * Addresses in Payfast's documented ranges are accepted and others are refused.
	 *
	 * @dataProvider data_documented_ranges
	 *
	 * @param string $ip       Address.
	 * @param bool   $expected Whether it is accepted.
	 * @return void
	 */
	public function test_is_valid_ip_checks_documented_ranges( string $ip, bool $expected ): void {
		$this->assertSame( $expected, $this->gateway->is_valid_ip( $ip ) );
	}

	/**
	 * An address outside the ranges is accepted when a Payfast hostname resolves to it.
	 *
	 * @return void
	 */
	public function test_is_valid_ip_falls_back_to_resolved_hostnames(): void {
		$this->set_filter_result( 'woocommerce_gateway_payfast_valid_ip_hostnames', array( 'itn.example.com' ) );
		// Pre-filled cache, so the test does not depend on DNS.
		set_transient( 'wc_payfast_itn_sender_ips_' . md5( 'itn.example.com' ), array( self::UNKNOWN_IP ) );

		$this->assertTrue( $this->gateway->is_valid_ip( self::UNKNOWN_IP ) );
		$this->assertFalse( $this->gateway->is_valid_ip( '203.0.113.11' ) );
	}

	/**
	 * Forwarded-for headers, the connecting address, and whether the request is accepted.
	 *
	 * @return array[]
	 */
	public static function data_forwarded_for(): array {
		return array(
			'Payfast address first' => array( self::PAYFAST_ITN_IP . ', 10.0.0.1', '10.0.0.1', true ),
			'unknown address first' => array( self::UNKNOWN_IP . ', ' . self::PAYFAST_ITN_IP, self::PAYFAST_ITN_IP, false ),
		);
	}

	/**
	 * Behind a proxy, the first X-Forwarded-For address is checked instead of the connecting address.
	 *
	 * @dataProvider data_forwarded_for
	 *
	 * @param string $forwarded_for X-Forwarded-For header.
	 * @param string $remote_addr   Connecting address.
	 * @param bool   $expected      Whether the request is accepted.
	 * @return void
	 */
	public function test_is_valid_ip_uses_forwarded_for_header( string $forwarded_for, string $remote_addr, bool $expected ): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded_for;

		$this->assertSame( $expected, $this->gateway->is_valid_ip( $remote_addr ) );
	}

	/**
	 * Non-boolean is_valid_ip filter results, the address checked, and the expected boolean.
	 *
	 * @return array[]
	 */
	public static function data_non_boolean_ip_filter_results(): array {
		return array(
			'truthy string accepts unknown address'  => array( 'yes', self::UNKNOWN_IP, true ),
			'truthy integer accepts unknown address' => array( 1, self::UNKNOWN_IP, true ),
			'empty string refuses Payfast address'   => array( '', self::PAYFAST_ITN_IP, false ),
			'zero refuses Payfast address'           => array( 0, self::PAYFAST_ITN_IP, false ),
		);
	}

	/**
	 * The is_valid_ip filter decides whether an ITN is accepted, so a non-boolean return is normalised.
	 *
	 * @dataProvider data_non_boolean_ip_filter_results
	 *
	 * @param mixed  $filtered Filter return value.
	 * @param string $ip       Address.
	 * @param bool   $expected Expected result.
	 * @return void
	 */
	public function test_is_valid_ip_filter_result_is_boolean( $filtered, string $ip, bool $expected ): void {
		$this->set_filter_result( 'woocommerce_gateway_payfast_is_valid_ip', $filtered );

		$this->assertSame( $expected, $this->gateway->is_valid_ip( $ip ) );
	}
}
