<?php
/**
 * Plugin Name: WooCommerce PayFast Gateway
 * Plugin URI: https://woocommerce.com/products/payfast-payment-gateway/
 * Description: Receive payments using the South African PayFast payments provider.
 * Author: WooCommerce
 * Author URI: http://woocommerce.com/
 * Version: 1.5.0
 * Requires at least: 5.6
 * Tested up to: 6.1
 * WC tested up to: 7.1
 * WC requires at least: 6.0
 * Requires PHP: 7.0
 */
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

defined( 'ABSPATH' ) || exit;

define( 'WC_GATEWAY_PAYFAST_VERSION', '1.5.0' ); // WRCS: DEFINED_VERSION.
define( 'WC_GATEWAY_PAYFAST_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_GATEWAY_PAYFAST_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Initialize the gateway.
 * @since 1.0.0
 */
function woocommerce_payfast_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

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

add_action( 'woocommerce_blocks_loaded', 'woocommerce_payfast_woocommerce_blocks_support' );

function woocommerce_payfast_woocommerce_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-payfast-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_PayFast_Blocks_Support );
			}
		);
	}
}

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_payfast_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'woocommerce_payfast_declare_hpos_compatibility' );
