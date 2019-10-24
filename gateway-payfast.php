<?php
/**
 * Plugin Name: WooCommerce PayFast Gateway
 * Plugin URI: https://woocommerce.com/products/payfast-payment-gateway/
 * Description: Receive payments using the South African PayFast payments provider.
 * Author: WooCommerce
 * Author URI: http://woocommerce.com/
 * Version: 1.4.14
 * Requires at least: 4.4
 * Tested up to: 5.3
 * WC tested up to: 3.8
 * WC requires at least: 2.6
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Initialize the gateway.
 * @since 1.0.0
 */
function woocommerce_payfast_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	define( 'WC_GATEWAY_PAYFAST_VERSION', '1.4.14' );

	require_once( plugin_basename( 'includes/class-wc-gateway-payfast.php' ) );
	require_once( plugin_basename( 'includes/class-wc-gateway-payfast-privacy.php' ) );
	load_plugin_textdomain( 'woocommerce-gateway-payfast', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_payfast_add_gateway' );
}
add_action( 'plugins_loaded', 'woocommerce_payfast_init', 0 );

function woocommerce_payfast_plugin_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'page' => 'wc-settings',
			'tab' => 'checkout',
			'section' => 'wc_gateway_payfast',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-gateway-payfast' ) . '</a>',
		'<a href="https://www.woocommerce.com/my-account/tickets/">' . __( 'Support', 'woocommerce-gateway-payfast' ) . '</a>',
		'<a href="https://docs.woocommerce.com/document/payfast-payment-gateway/">' . __( 'Docs', 'woocommerce-gateway-payfast' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_payfast_plugin_links' );


/**
 * Add the gateway to WooCommerce
 * @since 1.0.0
 */
function woocommerce_payfast_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_PayFast';
	return $methods;
}
