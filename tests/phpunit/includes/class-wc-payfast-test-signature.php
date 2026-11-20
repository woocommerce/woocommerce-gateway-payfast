<?php
/**
 * Payfast signatures for tests.
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * Computes Payfast signatures the way Payfast documents them (https://developers.payfast.co.za/docs).
 *
 * Written independently of the gateway on purpose: if the tests reused the gateway's own signature
 * code, a regression in it would change the expected value too and every test would still pass.
 */
final class WC_Payfast_Test_Signature {

	/**
	 * Signature for posted data: the checkout form and ITN callbacks.
	 *
	 * Fields are signed in the order they are posted, and the passphrase is appended last.
	 *
	 * @param array  $fields     Posted fields. A `signature` field is ignored.
	 * @param string $passphrase Merchant passphrase.
	 * @return string
	 */
	public static function sign_posted_data( array $fields, string $passphrase ): string {
		unset( $fields['signature'] );

		$pairs = array();
		foreach ( $fields as $key => $value ) {
			$pairs[] = $key . '=' . urlencode( trim( $value ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Payfast signs with urlencode().
		}
		$pairs[] = 'passphrase=' . urlencode( $passphrase ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode

		return md5( implode( '&', $pairs ) );
	}

	/**
	 * Signature for API requests: the request headers and body with the passphrase, sorted by key,
	 * leaving out empty values.
	 *
	 * @param array  $variables  Headers and body fields. A `signature` field is ignored.
	 * @param string $passphrase Merchant passphrase.
	 * @return string
	 */
	public static function sign_api( array $variables, string $passphrase ): string {
		unset( $variables['signature'] );
		$variables['passphrase'] = $passphrase;
		ksort( $variables );

		$pairs = array();
		foreach ( $variables as $key => $value ) {
			if ( ! empty( $value ) ) {
				$pairs[] = $key . '=' . urlencode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Payfast signs with urlencode().
			}
		}

		return md5( implode( '&', $pairs ) );
	}
}
