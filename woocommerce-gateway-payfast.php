<?php
/**
 * Plugin Name: WooCommerce Payfast Gateway
 * Plugin URI: https://woocommerce.com/products/payfast-payment-gateway/
 * Description: Receive payments using the South African Payfast payments provider.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Version: 1.6.1
 * Requires at least: 6.2
 * Tested up to: 6.4
 * WC requires at least: 8.2
 * WC tested up to: 8.4
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 *
 * @package WooCommerce Gateway Payfast
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_GATEWAY_PAYFAST_VERSION', '1.6.1' ); // WRCS: DEFINED_VERSION.
define( 'WC_GATEWAY_PAYFAST_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_GATEWAY_PAYFAST_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_payfast_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once plugin_basename( 'includes/class-wc-gateway-payfast.php' );
	require_once plugin_basename( 'includes/class-wc-gateway-payfast-privacy.php' );
	load_plugin_textdomain( 'woocommerce-gateway-payfast', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_payfast_add_gateway' );
}
add_action( 'plugins_loaded', 'woocommerce_payfast_init', 0 );

/**
 * Add links to the plugin action links.
 *
 * @since 1.4.13
 *
 * @param array $links Plugin action links.
 * @return array Modified plugin action links.
 */
function woocommerce_payfast_plugin_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'wc_gateway_payfast',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'woocommerce-gateway-payfast' ) . '</a>',
		'<a href="https://www.woocommerce.com/my-account/tickets/">' . esc_html__( 'Support', 'woocommerce-gateway-payfast' ) . '</a>',
		'<a href="https://docs.woocommerce.com/document/payfast-payment-gateway/">' . esc_html__( 'Docs', 'woocommerce-gateway-payfast' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_payfast_plugin_links' );

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 *
 * @param string[] $methods WooCommerce payment methods.
 * @return string[] Modified payment methods to include Payfast.
 */
function woocommerce_payfast_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_PayFast';
	return $methods;
}

add_action( 'woocommerce_blocks_loaded', 'woocommerce_payfast_woocommerce_blocks_support' );

/**
 * Add the gateway to WooCommerce Blocks.
 *
 * @since 1.4.19
 */
function woocommerce_payfast_woocommerce_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once WC_GATEWAY_PAYFAST_PATH . '/includes/class-wc-gateway-payfast-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_PayFast_Blocks_Support() );
			}
		);
	}
}

/**
 * Declares compatibility with Woocommerce features.
 *
 * List of features:
 * - custom_order_tables
 * - product_block_editor
 *
 * @since 1.6.1 Rename function
 * @return void
 */
function woocommerce_payfast_declare_feature_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__
		);

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'product_block_editor',
			__FILE__
		);
	}
}
add_action( 'before_woocommerce_init', 'woocommerce_payfast_declare_feature_compatibility' );

/**
 * Display notice if WooCommerce is not installed.
 *
 * @since 1.5.8
 */
function woocommerce_payfast_missing_wc_notice() {
	if ( class_exists( 'WooCommerce' ) ) {
		// Display nothing if WooCommerce is installed and activated.
		return;
	}

	echo '<div class="error"><p><strong>';
	printf(
		/* translators: %s WooCommerce download URL link. */
		esc_html__( 'WooCommerce Payfast Gateway requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-payfast' ),
		'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
	);
	echo '</strong></p></div>';
}
add_action( 'admin_notices', 'woocommerce_payfast_missing_wc_notice' );
