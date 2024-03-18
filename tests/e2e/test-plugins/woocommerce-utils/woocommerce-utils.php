<?php
/**
 * Plugin name: WooCommerce Utilities
 * Description: A plugin to provide tools for WooCommerce for testing purposes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register rest api endpoints.
add_action( 'rest_api_init', static function () {
	// Flush all logs.
	register_rest_route( 'e2e-wc/v1', '/flush-all-logs', array(
		'methods'             => 'DELETE',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			try {
				WC_Log_Handler_File::delete_logs_before_timestamp( strtotime( '+2 day' ) );
			} catch ( Exception $e ) {
				return new WP_REST_Response( false, 500 );
			}

			return new WP_REST_Response( true, 200 );
		},
	) );

	// Flush all emails.
	register_rest_route( 'e2e-wc/v1', '/flush-all-emails', array(
		'methods'  => 'DELETE',
		'callback' => function () {
			global $wpdb;

			try {
				$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}email_log" );
			} catch ( Exception $e ) {
				return new WP_REST_Response( false, 500 );
			}

			return new WP_REST_Response( true, 200 );
		},
	) );
} );

