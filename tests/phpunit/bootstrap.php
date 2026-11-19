<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package WooCommerce Gateway Payfast
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// wp-env sets WP_TESTS_DIR in its containers; the fallback is where the usual install-wp-tests.sh puts the library.
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Run the tests with `npm run test:php`." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// The WordPress test library requires the PHPUnit Polyfills and looks for them here before its own fallbacks.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WooCommerce and the gateway.
 *
 * The test database has no active plugins, so both are loaded by hand, before `plugins_loaded`
 * fires so that the gateway's own `plugins_loaded` callbacks still run.
 *
 * @return void
 */
function wc_payfast_tests_load_plugins() {
	require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
	require_once dirname( __DIR__, 2 ) . '/woocommerce-gateway-payfast.php';
}
tests_add_filter( 'muplugins_loaded', 'wc_payfast_tests_load_plugins' );

/**
 * Create the WooCommerce tables, options, and roles in the test database.
 *
 * The WordPress test installer drops every table on each run, so WooCommerce must be installed again.
 *
 * @return void
 */
function wc_payfast_tests_install_woocommerce() {
	WC_Install::install();

	// Orders use posts storage by default here. Tests that switch to HPOS need its tables, and creating
	// them inside a test would implicitly commit the transaction that isolates the test.
	$data_synchronizer = wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
	if ( ! $data_synchronizer->check_orders_table_exists() ) {
		$data_synchronizer->create_database_tables();
	}

	// WC_Install adds capabilities to the roles in the database; reload them so the current request sees them.
	$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	wp_roles();
}
tests_add_filter( 'setup_theme', 'wc_payfast_tests_install_woocommerce' );

require $_tests_dir . '/includes/bootstrap.php';

/**
 * Block outbound HTTP requests that a test has not mocked.
 *
 * Without this, ITN validation and subscription API calls would reach the real Payfast servers.
 * Tests mock a response by adding their own `pre_http_request` filter, which runs first.
 *
 * @param false|array|WP_Error $preempt     A preempted response, or false to continue.
 * @param array                $parsed_args HTTP request arguments.
 * @param string               $url         The request URL.
 * @return false|array|WP_Error The preempted response.
 */
function wc_payfast_tests_block_http_requests( $preempt, $parsed_args, $url ) {
	if ( false !== $preempt ) {
		return $preempt;
	}

	return new WP_Error(
		'wc_payfast_tests_http_blocked',
		sprintf( 'Unmocked outbound HTTP request to %s. Mock it with a `pre_http_request` filter in the test.', $url )
	);
}
add_filter( 'pre_http_request', 'wc_payfast_tests_block_http_requests', PHP_INT_MAX, 3 );
