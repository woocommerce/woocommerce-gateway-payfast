<?php
/**
 * Backwards compat.
 *
 * @since 1.6.1
 * @package WooCommerce Gateway Payfast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_plugins = get_option( 'active_plugins', array() );

foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/gateway-payfast.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/gateway-payfast.php', '/woocommerce-gateway-payfast.php', $active_plugin );
	}
}

update_option( 'active_plugins', $active_plugins );
