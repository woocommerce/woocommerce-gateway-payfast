<?php
/**
 * Payfast Payment Gateway
 *
 * @package WooCommerce Gateway Payfast
 */

/**
 * Payfast Payment Gateway
 *
 * Provides a Payfast Payment Gateway.
 *
 * @class  woocommerce_payfast
 */
class WC_Gateway_PayFast extends WC_Payment_Gateway {

	/**
	 * Version
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Data to send to Payfast.
	 *
	 * @var array $data_to_send
	 */
	protected $data_to_send = array();

	/**
	 * Merchant ID.
	 *
	 * @var string $merchant_id
	 */
	protected $merchant_id;

	/**
	 * Merchant Key.
	 *
	 * @var string $merchant_key
	 */
	protected $merchant_key;

	/**
	 * Pass Phrase.
	 *
	 * @var string $pass_phrase
	 */
	protected $pass_phrase;

	/**
	 * Payfast URL.
	 *
	 * @var string $url
	 */
	protected $url;

	/**
	 * Payfast Validate URL.
	 *
	 * @var string $validate_url
	 */
	protected $validate_url;

	/**
	 * Response URL.
	 *
	 * @var string $response_url
	 */
	protected $response_url;

	/**
	 * Send debug email.
	 *
	 * @var bool $send_debug_email
	 */
	protected $send_debug_email;

	/**
	 * Debug email.
	 *
	 * @var string $debug_email
	 */
	protected $debug_email;

	/**
	 * Enable logging.
	 *
	 * @var bool $enable_logging
	 */
	protected $enable_logging;

	/**
	 * Available countries.
	 *
	 * @var array $available_countries
	 */
	protected $available_countries;

	/**
	 * Available currencies.
	 *
	 * @var array $available_currencies
	 */
	protected $available_currencies;

	/**
	 * Logger instance.
	 *
	 * @var WC_Logger $logger
	 */
	protected $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version      = WC_GATEWAY_PAYFAST_VERSION;
		$this->id           = 'payfast';
		$this->method_title = __( 'Payfast', 'woocommerce-gateway-payfast' );
		/* translators: 1: a href link 2: closing href */
		$this->method_description  = sprintf( __( 'Payfast works by sending the user to %1$sPayfast%2$s to enter their payment information.', 'woocommerce-gateway-payfast' ), '<a href="https://payfast.io/">', '</a>' );
		$this->icon                = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __DIR__ ) ) . '/assets/images/icon.png';
		$this->debug_email         = get_option( 'admin_email' );
		$this->available_countries = array( 'ZA' );

		/**
		 * Filter available countries for Payfast Gateway.
		 *
		 * @since 1.4.13
		 *
		 * @param string[] $available_countries Array of available countries.
		 */
		$this->available_currencies = (array) apply_filters( 'woocommerce_gateway_payfast_available_currencies', array( 'ZAR' ) );

		// Supported functionality.
		$this->supports = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change', // Subs 1.x support.
			'subscription_payment_method_change_customer', // Enabled for https://github.com/woocommerce/woocommerce-gateway-payfast/issues/32.
		);

		$this->init_form_fields();
		$this->init_settings();

		if ( ! is_admin() ) {
			$this->setup_constants();
		}

		// Setup default merchant data.
		$this->merchant_id      = $this->get_option( 'merchant_id' );
		$this->merchant_key     = $this->get_option( 'merchant_key' );
		$this->pass_phrase      = $this->get_option( 'pass_phrase' );
		$this->url              = 'https://www.payfast.co.za/eng/process?aff=woo-free';
		$this->validate_url     = 'https://www.payfast.co.za/eng/query/validate';
		$this->title            = $this->get_option( 'title' );
		$this->response_url     = add_query_arg( 'wc-api', 'WC_Gateway_PayFast', home_url( '/' ) );
		$this->send_debug_email = 'yes' === $this->get_option( 'send_debug_email' );
		$this->description      = $this->get_option( 'description' );
		$this->enabled          = 'yes' === $this->get_option( 'enabled' ) ? 'yes' : 'no';
		$this->enable_logging   = 'yes' === $this->get_option( 'enable_logging' );

		// Setup the test data, if in test mode.
		if ( 'yes' === $this->get_option( 'testmode' ) ) {
			$this->url          = 'https://sandbox.payfast.co.za/eng/process?aff=woo-free';
			$this->validate_url = 'https://sandbox.payfast.co.za/eng/query/validate';
			$this->add_testmode_admin_settings_notice();
		} else {
			$this->send_debug_email = false;
		}

		add_action( 'woocommerce_api_wc_gateway_payfast', array( $this, 'check_itn_response' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_payfast', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'cancel_subscription_listener' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Add fees to order.
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_order_fee' ) );
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_order_net' ), 20 );

		// Change Payment Method actions.
		add_action( 'woocommerce_subscription_payment_method_updated_from_' . $this->id, array( $this, 'maybe_cancel_subscription_token' ), 10, 2 );

		// Add support for WooPayments multi-currency.
		add_filter( 'woocommerce_currency', array( $this, 'filter_currency' ) );

		add_filter( 'nocache_headers', array( $this, 'no_store_cache_headers' ) );
	}

	/**
	 * Use the no-store, private cache directive on the order-pay endpoint.
	 *
	 * This prevents the browser caching the page even when the visitor has clicked
	 * the back button. This is required to determine if a user has pressed back while
	 * in the payfast gateway.
	 *
	 * @since 1.6.2
	 *
	 * @param string[] $headers Array of caching headers.
	 * @return string[] Modified caching headers.
	 */
	public function no_store_cache_headers( $headers ) {
		if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
			return $headers;
		}

		$headers['Cache-Control'] = 'no-cache, must-revalidate, max-age=0, no-store, private';
		return $headers;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'          => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-gateway-payfast' ),
				'label'       => __( 'Enable Payfast', 'woocommerce-gateway-payfast' ),
				'type'        => 'checkbox',
				'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-payfast' ),
				'default'     => 'no', // User should enter the required information before enabling the gateway.
				'desc_tip'    => true,
			),
			'title'            => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payfast' ),
				'default'     => __( 'Payfast', 'woocommerce-gateway-payfast' ),
				'desc_tip'    => true,
			),
			'description'      => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode'         => array(
				'title'       => __( 'Payfast Sandbox', 'woocommerce-gateway-payfast' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in development mode.', 'woocommerce-gateway-payfast' ),
				'default'     => 'yes',
			),
			'merchant_id'      => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This is the merchant ID, received from Payfast.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
			),
			'merchant_key'     => array(
				'title'       => __( 'Merchant Key', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This is the merchant key, received from Payfast.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
			),
			'pass_phrase'      => array(
				'title'       => __( 'Passphrase', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( '* Required. Needed to ensure the data passed through is secure.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
			),
			'send_debug_email' => array(
				'title'   => __( 'Send Debug Emails', 'woocommerce-gateway-payfast' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send debug e-mails for transactions through the Payfast gateway (sends on successful transaction as well).', 'woocommerce-gateway-payfast' ),
				'default' => 'yes',
			),
			'debug_email'      => array(
				'title'       => __( 'Who Receives Debug E-mails?', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'The e-mail address to which debugging error e-mails are sent when in test mode.', 'woocommerce-gateway-payfast' ),
				'default'     => get_option( 'admin_email' ),
			),
			'enable_logging'   => array(
				'title'   => __( 'Enable Logging', 'woocommerce-gateway-payfast' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable transaction logging for gateway.', 'woocommerce-gateway-payfast' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Get the required form field keys for setup.
	 *
	 * @return array
	 */
	public function get_required_settings_keys() {
		return array(
			'merchant_id',
			'merchant_key',
			'pass_phrase',
		);
	}

	/**
	 * Determine if the gateway still requires setup.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		return ! $this->get_option( 'merchant_id' ) || ! $this->get_option( 'merchant_key' ) || ! $this->get_option( 'pass_phrase' );
	}

	/**
	 * Add a notice to the merchant_key and merchant_id fields when in test mode.
	 *
	 * @since 1.0.0
	 */
	public function add_testmode_admin_settings_notice() {
		$this->form_fields['merchant_id']['description']  .= ' <strong>' . esc_html__( 'Sandbox Merchant ID currently in use', 'woocommerce-gateway-payfast' ) . ' ( ' . esc_html( $this->merchant_id ) . ' ).</strong>';
		$this->form_fields['merchant_key']['description'] .= ' <strong>' . esc_html__( 'Sandbox Merchant Key currently in use', 'woocommerce-gateway-payfast' ) . ' ( ' . esc_html( $this->merchant_key ) . ' ).</strong>';
	}

	/**
	 * Check if this gateway is enabled and available in the base currency being traded with.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function check_requirements() {

		$errors = array(
			// Check if the store currency is supported by Payfast.
			! in_array( get_woocommerce_currency(), $this->available_currencies, true ) ? 'wc-gateway-payfast-error-invalid-currency' : null,
			// Check if user entered the merchant ID.
			'yes' !== $this->get_option( 'testmode' ) && empty( $this->get_option( 'merchant_id' ) ) ? 'wc-gateway-payfast-error-missing-merchant-id' : null,
			// Check if user entered the merchant key.
			'yes' !== $this->get_option( 'testmode' ) && empty( $this->get_option( 'merchant_key' ) ) ? 'wc-gateway-payfast-error-missing-merchant-key' : null,
			// Check if user entered a pass phrase.
			'yes' !== $this->get_option( 'testmode' ) && empty( $this->get_option( 'pass_phrase' ) ) ? 'wc-gateway-payfast-error-missing-pass-phrase' : null,
		);

		return array_filter( $errors );
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			$errors = $this->check_requirements();
			// Prevent using this gateway on frontend if there are any configuration errors.
			return 0 === count( $errors );
		}

		return parent::is_available();
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( in_array( get_woocommerce_currency(), $this->available_currencies, true ) ) {
			parent::admin_options();
		} else {
			?>
			<h3><?php esc_html_e( 'Payfast', 'woocommerce-gateway-payfast' ); ?></h3>
			<div class="inline error">
				<p>
					<strong><?php esc_html_e( 'Gateway Disabled', 'woocommerce-gateway-payfast' ); ?></strong>
					<?php
					/* translators: 1: a href link 2: closing href */
					echo wp_kses_post( sprintf( __( 'Choose South African Rands as your store currency in %1$sGeneral Settings%2$s to enable the Payfast Gateway.', 'woocommerce-gateway-payfast' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">', '</a>' ) );
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Generate the Payfast button link.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function generate_payfast_form( $order_id ) {
		$order     = wc_get_order( $order_id );
		$site_name = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		// Construct variables for post.
		$this->data_to_send = array(
			// Merchant details.
			'merchant_id'      => $this->merchant_id,
			'merchant_key'     => $this->merchant_key,
			'return_url'       => esc_url_raw( add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) ) ),
			'cancel_url'       => $order->get_cancel_order_url(),
			'notify_url'       => $this->response_url,

			// Billing details.
			'name_first'       => self::get_order_prop( $order, 'billing_first_name' ),
			'name_last'        => self::get_order_prop( $order, 'billing_last_name' ),
			'email_address'    => self::get_order_prop( $order, 'billing_email' ),

			// Item details.
			'm_payment_id'     => ltrim( $order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce-gateway-payfast' ) ),
			'amount'           => $order->get_total(),
			'item_name'        => $site_name . ' - ' . $order->get_order_number(),
			/* translators: 1: blog info name */
			'item_description' => sprintf( esc_html__( 'New order from %s', 'woocommerce-gateway-payfast' ), $site_name ),

			// Custom strings.
			'custom_str1'      => self::get_order_prop( $order, 'order_key' ),
			'custom_str2'      => 'WooCommerce/' . WC_VERSION . '; ' . rawurlencode( get_site_url() ),
			'custom_str3'      => self::get_order_prop( $order, 'id' ),
			'source'           => 'WooCommerce-Free-Plugin',
		);

		// Add Change subscription payment method parameters.
		if ( isset( $_GET['change_pay_method'] ) ) {
			$subscription_id = absint( wp_unslash( $_GET['change_pay_method'] ) );
			if ( $this->is_subscription( $subscription_id ) && $order_id === $subscription_id && floatval( 0 ) === floatval( $order->get_total() ) ) {
				$this->data_to_send['custom_str4'] = 'change_pay_method';
			}
		}

		/*
		 * Check If changing payment method.
		 * We have to generate Tokenization (ad-hoc) token to charge future payments.
		 */
		if ( $this->is_subscription( $order_id ) ) {
			// 2 == ad-hoc subscription type see Payfast API docs
			$this->data_to_send['subscription_type'] = '2';
		}

		// Add subscription parameters.
		if ( $this->order_contains_subscription( $order_id ) ) {
			// 2 == ad-hoc subscription type see Payfast API docs
			$this->data_to_send['subscription_type'] = '2';
		}

		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			$current       = reset( $subscriptions );
			// For renewal orders that have subscriptions with renewal flag OR
			// For renew orders which are failed to pay by other payment gateway buy now paying using Payfast.
			// we will create a new subscription in Payfast and link it to the existing ones in WC.
			// The old subscriptions in Payfast will be cancelled once we handle the itn request.
			if ( count( $subscriptions ) > 0 && ( $this->_has_renewal_flag( $current ) || $this->id !== $current->get_payment_method() ) ) {
				// 2 == ad-hoc subscription type see Payfast API docs
				$this->data_to_send['subscription_type'] = '2';
			}
		}

		/**
		 * Allow others to modify payment data before that is sent to Payfast.
		 *
		 * @since 1.4.21
		 *
		 * @param array $this->data_to_send Payment data.
		 * @param int   $order_id           Order id.
		 */
		$this->data_to_send = apply_filters( 'woocommerce_gateway_payfast_payment_data_to_send', $this->data_to_send, $order_id );

		$payfast_args_array = array();
		$sign_strings       = array();
		foreach ( $this->data_to_send as $key => $value ) {
			if ( 'source' !== $key ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- legacy code, validation required prior to switching to rawurlencode.
				$sign_strings[] = esc_attr( $key ) . '=' . urlencode( str_replace( '&amp;', '&', trim( $value ) ) );
			}
			$payfast_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}

		if ( ! empty( $this->pass_phrase ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- legacy code, validation required prior to switching to rawurlencode.
			$payfast_args_array[] = '<input type="hidden" name="signature" value="' . md5( implode( '&', $sign_strings ) . '&passphrase=' . urlencode( $this->pass_phrase ) ) . '" />';
		} else {
			$payfast_args_array[] = '<input type="hidden" name="signature" value="' . md5( implode( '&', $sign_strings ) ) . '" />';
		}

		echo '<form action="' . esc_url( $this->url ) . '" method="post" id="payfast_payment_form">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in foreach loop above.
		echo implode( '', $payfast_args_array );
		echo '
				<input
					type="submit"
					class="button-alt"
					id="submit_payfast_payment_form"
					value="' . esc_attr__( 'Pay via Payfast', 'woocommerce-gateway-payfast' ) . '"
				/>
				<a
					class="button cancel"
					href="' . esc_url( $order->get_cancel_order_url() ) . '"
				>' .
					esc_html__( 'Cancel order &amp; restore cart', 'woocommerce-gateway-payfast' ) .
				'</a>
				<script type="text/javascript">
					jQuery(function(){
						// Feature detect.
						if (
							typeof PerformanceNavigationTiming !== "undefined" &&
							typeof window.performance !== "undefined" &&
							typeof performance.getEntriesByType === "function"
						) {
							var isBackForward = false;
							var entries = performance.getEntriesByType("navigation");
							entries.forEach((entry) => {
								if (entry.type === "back_forward") {
									isBackForward = true;
								}
							});
							if (isBackForward) {
								/*
								 * Do not submit form on back or forward.
								 * Ensure that the body is unblocked/not showing the redirect message.
								 */
								jQuery("body").unblock();
								return;
							}
						}

						jQuery("body").block(
							{
								message: "' . esc_html__( 'Thank you for your order. We are now redirecting you to Payfast to make payment.', 'woocommerce-gateway-payfast' ) . '",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
									padding:        20,
									textAlign:      "center",
									color:          "#555",
									border:         "3px solid #aaa",
									backgroundColor:"#fff",
									cursor:         "wait"
								}
							});
						jQuery( "#submit_payfast_payment_form" ).click();
					});
				</script>
			</form>';
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 *
	 * @throws Exception When there is an error processing the payment.
	 *
	 * @param int $order_id Order ID.
	 * @return string[] Payment result {
	 *    @type string $result   Result of payment.
	 *    @type string $redirect Redirect URL.
	 * }
	 */
	public function process_payment( $order_id ) {
		$order    = wc_get_order( $order_id );
		$redirect = $order->get_checkout_payment_url( true );

		// Check if the payment is for changing payment method.
		if ( isset( $_GET['change_payment_method'] ) ) {
			$sub_id = absint( wp_unslash( $_GET['change_payment_method'] ) );
			if ( $this->is_subscription( $sub_id ) && floatval( 0 ) === floatval( $order->get_total() ) ) {
				$redirect = add_query_arg( 'change_pay_method', $sub_id, $redirect );
			}
		}

		return array(
			'result'   => 'success',
			'redirect' => $redirect,
		);
	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to Payfast.
	 *
	 * @param WC_Order $order Order object.
	 * @since 1.0.0
	 */
	public function receipt_page( $order ) {
		echo '<p>' . esc_html__( 'Thank you for your order, please click the button below to pay with Payfast.', 'woocommerce-gateway-payfast' ) . '</p>';
		$this->generate_payfast_form( $order );
	}

	/**
	 * Check Payfast ITN response.
	 *
	 * @since 1.0.0
	 */
	public function check_itn_response() {
		// phpcs:ignore.WordPress.Security.NonceVerification.Missing
		$this->handle_itn_request( stripslashes_deep( $_POST ) );

		// Notify Payfast that information has been received.
		header( 'HTTP/1.0 200 OK' );
		flush();
	}

	/**
	 * Check Payfast ITN validity.
	 *
	 * @param array $data Data.
	 * @since 1.0.0
	 */
	public function handle_itn_request( $data ) {
		$this->log(
			PHP_EOL
			. '----------'
			. PHP_EOL . 'Payfast ITN call received'
			. PHP_EOL . '----------'
		);
		$this->log( 'Get posted data' );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- debug info for logging.
		$this->log( 'Payfast Data: ' . print_r( $data, true ) );

		$payfast_error  = false;
		$payfast_done   = false;
		$debug_email    = $this->get_option( 'debug_email', get_option( 'admin_email' ) );
		$session_id     = $data['custom_str1'];
		$vendor_name    = get_bloginfo( 'name', 'display' );
		$vendor_url     = home_url( '/' );
		$order_id       = absint( $data['custom_str3'] );
		$order_key      = wc_clean( $session_id );
		$order          = wc_get_order( $order_id );
		$original_order = $order;

		if ( false === $data ) {
			$payfast_error         = true;
			$payfast_error_message = PF_ERR_BAD_ACCESS;
		}

		// Verify security signature.
		if ( ! $payfast_error && ! $payfast_done ) {
			$this->log( 'Verify security signature' );
			$signature = md5( $this->_generate_parameter_string( $data, false, false ) ); // false not to sort data.
			// If signature different, log for debugging.
			if ( ! $this->validate_signature( $data, $signature ) ) {
				$payfast_error         = true;
				$payfast_error_message = PF_ERR_INVALID_SIGNATURE;
			}
		}

		// Verify source IP (If not in debug mode).
		if ( ! $payfast_error && ! $payfast_done
			&& $this->get_option( 'testmode' ) !== 'yes' ) {
			$this->log( 'Verify source IP' );

			if ( isset( $_SERVER['REMOTE_ADDR'] ) && ! $this->is_valid_ip( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) ) {
				$payfast_error         = true;
				$payfast_error_message = PF_ERR_BAD_SOURCE_IP;
			}
		}

		// Verify data received.
		if ( ! $payfast_error ) {
			$this->log( 'Verify data received' );
			$validation_data = $data;
			unset( $validation_data['signature'] );
			$has_valid_response_data = $this->validate_response_data( $validation_data );

			if ( ! $has_valid_response_data ) {
				$payfast_error         = true;
				$payfast_error_message = PF_ERR_BAD_ACCESS;
			}
		}

		/**
		 * Handle Changing Payment Method.
		 *   - Save payfast subscription token to handle future payment
		 *   - (for Payfast to Payfast payment method change) Cancel old token, as future payment will be handle with new token
		 *
		 * Note: The change payment method is handled before the amount mismatch check, as it doesn't involve an actual payment (0.00) and only token updates are handled here.
		 */
		if (
			! $payfast_error &&
			isset( $data['custom_str4'] ) &&
			'change_pay_method' === wc_clean( $data['custom_str4'] ) &&
			$this->is_subscription( $order_id ) &&
			floatval( 0 ) === floatval( $data['amount_gross'] )
		) {
			if ( self::get_order_prop( $order, 'order_key' ) !== $order_key ) {
				$this->log( 'Order key does not match' );
				exit;
			}

			$this->log( '- Change Payment Method' );
			$status = strtolower( $data['payment_status'] );
			if ( 'complete' === $status && isset( $data['token'] ) ) {
				$token        = sanitize_text_field( $data['token'] );
				$subscription = wcs_get_subscription( $order_id );
				if ( ! empty( $subscription ) && ! empty( $token ) ) {
					$old_token = $this->_get_subscription_token( $subscription );
					// Cancel old subscription token of subscription if we have it.
					if ( ! empty( $old_token ) ) {
						$this->cancel_subscription_listener( $subscription );
					}

					// Set new subscription token on subscription.
					$this->_set_subscription_token( $token, $subscription );
					$this->log( 'Payfast token updated on Subcription: ' . $order_id );
				}
			}
			return;
		}

		// Check data against internal order.
		if ( ! $payfast_error && ! $payfast_done ) {
			$this->log( 'Check data against internal order' );

			// alter order object to be the renewal order if
			// the ITN request comes as a result of a renewal submission request.
			$description = json_decode( $data['item_description'] );

			if ( ! empty( $description->renewal_order_id ) ) {
				$renewal_order = wc_get_order( $description->renewal_order_id );
				if ( ! empty( $renewal_order ) && function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $renewal_order ) ) {
					$order = $renewal_order;
				}
			}

			// Check order amount.
			if ( ! $this->amounts_equal( $data['amount_gross'], self::get_order_prop( $order, 'order_total' ) ) ) {
				$payfast_error         = true;
				$payfast_error_message = PF_ERR_AMOUNT_MISMATCH;
			} elseif ( strcasecmp( $data['custom_str1'], self::get_order_prop( $original_order, 'order_key' ) ) !== 0 ) {
				// Check session ID.
				$payfast_error         = true;
				$payfast_error_message = PF_ERR_SESSIONID_MISMATCH;
			}
		}

		// Get internal order and verify it hasn't already been processed.
		if ( ! $payfast_error && ! $payfast_done ) {
			$this->log_order_details( $order );

			// Check if order has already been processed.
			if ( 'completed' === self::get_order_prop( $order, 'status' ) ) {
				$this->log( 'Order has already been processed' );
				$payfast_done = true;
			}
		}

		// If an error occurred.
		if ( $payfast_error ) {
			$this->log( 'Error occurred: ' . $payfast_error_message );

			if ( $this->send_debug_email ) {
				$this->log( 'Sending email notification' );

				// Send an email.
				$subject = 'Payfast ITN error: ' . $payfast_error_message;
				$body    =
					"Hi,\n\n" .
					"An invalid Payfast transaction on your website requires attention\n" .
					"------------------------------------------------------------\n" .
					'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n" .
					'Remote IP Address: ' . sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) . "\n" .
					'Remote host name: ' . gethostbyaddr( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) . "\n" .
					'Purchase ID: ' . self::get_order_prop( $order, 'id' ) . "\n" .
					'User ID: ' . self::get_order_prop( $order, 'user_id' ) . "\n";
				if ( isset( $data['pf_payment_id'] ) ) {
					$body .= 'Payfast Transaction ID: ' . esc_html( $data['pf_payment_id'] ) . "\n";
				}
				if ( isset( $data['payment_status'] ) ) {
					$body .= 'Payfast Payment Status: ' . esc_html( $data['payment_status'] ) . "\n";
				}

				$body .= "\nError: " . $payfast_error_message . "\n";

				switch ( $payfast_error_message ) {
					case PF_ERR_AMOUNT_MISMATCH:
						$body .=
							'Value received : ' . esc_html( $data['amount_gross'] ) . "\n"
							. 'Value should be: ' . self::get_order_prop( $order, 'order_total' );
						break;

					case PF_ERR_ORDER_ID_MISMATCH:
						$body .=
							'Value received : ' . esc_html( $data['custom_str3'] ) . "\n"
							. 'Value should be: ' . self::get_order_prop( $order, 'id' );
						break;

					case PF_ERR_SESSIONID_MISMATCH:
						$body .=
							'Value received : ' . esc_html( $data['custom_str1'] ) . "\n"
							. 'Value should be: ' . self::get_order_prop( $order, 'id' );
						break;

					// For all other errors there is no need to add additional information.
					default:
						break;
				}

				wp_mail( $debug_email, $subject, $body );
			} // End if.
		} elseif ( ! $payfast_done ) {

			$this->log( 'Check status and update order' );

			if ( self::get_order_prop( $original_order, 'order_key' ) !== $order_key ) {
				$this->log( 'Order key does not match' );
				exit;
			}

			$status = strtolower( $data['payment_status'] );

			$subscriptions = array();
			if ( function_exists( 'wcs_get_subscriptions_for_renewal_order' ) && function_exists( 'wcs_get_subscriptions_for_order' ) ) {
				$subscriptions = array_merge(
					wcs_get_subscriptions_for_renewal_order( $order_id ),
					wcs_get_subscriptions_for_order( $order_id )
				);
			}

			if ( 'complete' !== $status && 'cancelled' !== $status ) {
				foreach ( $subscriptions as $subscription ) {
					$this->_set_renewal_flag( $subscription );
				}
			}

			if ( 'complete' === $status ) {
				$this->handle_itn_payment_complete( $data, $order, $subscriptions );
			} elseif ( 'failed' === $status ) {
				$this->handle_itn_payment_failed( $data, $order );
			} elseif ( 'pending' === $status ) {
				$this->handle_itn_payment_pending( $data, $order );
			} elseif ( 'cancelled' === $status ) {
				$this->handle_itn_payment_cancelled( $data, $order, $subscriptions );
			}
		} // End if.

		$this->log(
			PHP_EOL
			. '----------'
			. PHP_EOL . 'End ITN call'
			. PHP_EOL . '----------'
		);
	}

	/**
	 * Handle logging the order details.
	 *
	 * @since 1.4.5
	 *
	 * @param WC_Order $order Order object.
	 */
	public function log_order_details( $order ) {
		$customer_id = $order->get_user_id();

		$details = 'Order Details:'
		. PHP_EOL . 'customer id:' . $customer_id
		. PHP_EOL . 'order id:   ' . $order->get_id()
		. PHP_EOL . 'parent id:  ' . $order->get_parent_id()
		. PHP_EOL . 'status:     ' . $order->get_status()
		. PHP_EOL . 'total:      ' . $order->get_total()
		. PHP_EOL . 'currency:   ' . $order->get_currency()
		. PHP_EOL . 'key:        ' . $order->get_order_key()
		. '';

		$this->log( $details );
	}

	/**
	 * This function mainly responds to ITN cancel requests initiated on Payfast, but also acts
	 * just in case they are not cancelled.
	 *
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array             $data          Should be from the Gateway ITN callback.
	 * @param WC_Order          $order         Order object.
	 * @param WC_Subscription[] $subscriptions Array of subscriptions.
	 */
	public function handle_itn_payment_cancelled( $data, $order, $subscriptions ) {

		remove_action( 'woocommerce_subscription_status_cancelled', array( $this, 'cancel_subscription_listener' ) );
		foreach ( $subscriptions as $subscription ) {
			if ( 'cancelled' !== $subscription->get_status() ) {
				$subscription->update_status( 'cancelled', esc_html__( 'Merchant cancelled subscription on Payfast.', 'woocommerce-gateway-payfast' ) );
				$this->_delete_subscription_token( $subscription );
			}
		}
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'cancel_subscription_listener' ) );
	}

	/**
	 * This function handles payment complete request by Payfast.
	 *
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array             $data          Should be from the Gateway ITN callback.
	 * @param WC_Order          $order         Order object.
	 * @param WC_Subscription[] $subscriptions Array of subscriptions.
	 */
	public function handle_itn_payment_complete( $data, $order, $subscriptions ) {
		$this->log( '- Complete' );
		$order->add_order_note( esc_html__( 'ITN payment completed', 'woocommerce-gateway-payfast' ) );
		$order->update_meta_data( 'payfast_amount_fee', $data['amount_fee'] );
		$order->update_meta_data( 'payfast_amount_net', $data['amount_net'] );
		$order_id = self::get_order_prop( $order, 'id' );

		// Store token for future subscription deductions.
		if ( count( $subscriptions ) > 0 && isset( $data['token'] ) ) {
			if ( $this->_has_renewal_flag( reset( $subscriptions ) ) ) {
				// Renewal flag is set to true, so we need to cancel previous token since we will create a new one.
				$this->log( 'Cancel previous subscriptions with token ' . $this->_get_subscription_token( reset( $subscriptions ) ) );

				// Only request API cancel token for the first subscription since all of them are using the same token.
				$this->cancel_subscription_listener( reset( $subscriptions ) );
			}

			$token = sanitize_text_field( $data['token'] );
			foreach ( $subscriptions as $subscription ) {
				$this->_delete_renewal_flag( $subscription );
				$this->_set_subscription_token( $token, $subscription );
			}
		}

		// Mark payment as complete.
		$order->payment_complete( $data['pf_payment_id'] );

		$debug_email = $this->get_option( 'debug_email', get_option( 'admin_email' ) );
		$vendor_name = get_bloginfo( 'name', 'display' );
		$vendor_url  = home_url( '/' );
		if ( $this->send_debug_email ) {
			$subject = 'Payfast ITN on your site';
			$body    =
				"Hi,\n\n"
				. "A Payfast transaction has been completed on your website\n"
				. "------------------------------------------------------------\n"
				. 'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n"
				. 'Purchase ID: ' . esc_html( $data['m_payment_id'] ) . "\n"
				. 'Payfast Transaction ID: ' . esc_html( $data['pf_payment_id'] ) . "\n"
				. 'Payfast Payment Status: ' . esc_html( $data['payment_status'] ) . "\n"
				. 'Order Status Code: ' . self::get_order_prop( $order, 'status' );
			wp_mail( $debug_email, $subject, $body );
		}

		/**
		 * Fires after handling the Payment Complete ITN from Payfast.
		 *
		 * @since 1.4.22
		 *
		 * @param array             $data          ITN Payload.
		 * @param WC_Order          $order         Order Object.
		 * @param WC_Subscription[] $subscriptions Subscription array.
		 */
		do_action( 'woocommerce_payfast_handle_itn_payment_complete', $data, $order, $subscriptions );
	}

	/**
	 * Handle payment failed request by Payfast.
	 *
	 * @param array    $data  Should be from the Gateway ITN callback.
	 * @param WC_Order $order Order object.
	 */
	public function handle_itn_payment_failed( $data, $order ) {
		$this->log( '- Failed' );
		/* translators: 1: payment status */
		$order->update_status( 'failed', sprintf( __( 'Payment %s via ITN.', 'woocommerce-gateway-payfast' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );
		$debug_email = $this->get_option( 'debug_email', get_option( 'admin_email' ) );
		$vendor_name = get_bloginfo( 'name', 'display' );
		$vendor_url  = home_url( '/' );

		if ( $this->send_debug_email ) {
			$subject = 'Payfast ITN Transaction on your site';
			$body    =
				"Hi,\n\n" .
				"A failed Payfast transaction on your website requires attention\n" .
				"------------------------------------------------------------\n" .
				'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n" .
				'Purchase ID: ' . self::get_order_prop( $order, 'id' ) . "\n" .
				'User ID: ' . self::get_order_prop( $order, 'user_id' ) . "\n" .
				'Payfast Transaction ID: ' . esc_html( $data['pf_payment_id'] ) . "\n" .
				'Payfast Payment Status: ' . esc_html( $data['payment_status'] );
			wp_mail( $debug_email, $subject, $body );
		}
	}

	/**
	 * Handle payment pending request by Payfast.
	 *
	 * @since 1.4.0
	 *
	 * @param array    $data  Should be from the Gateway ITN callback.
	 * @param WC_Order $order Order object.
	 */
	public function handle_itn_payment_pending( $data, $order ) {
		$this->log( '- Pending' );
		// Need to wait for "Completed" before processing.
		/* translators: 1: payment status */
		$order->update_status( 'on-hold', sprintf( esc_html__( 'Payment %s via ITN.', 'woocommerce-gateway-payfast' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );
	}

	/**
	 * Get the pre-order fee.
	 *
	 * @param string $order_id Order ID.
	 * @return double
	 */
	public function get_pre_order_fee( $order_id ) {
		foreach ( wc_get_order( $order_id )->get_fees() as $fee ) {
			if ( is_array( $fee ) && 'Pre-Order Fee' === $fee['name'] ) {
				return doubleval( $fee['line_total'] ) + doubleval( $fee['line_tax'] );
			}
		}
	}

	/**
	 * Whether order contains a pre-order.
	 *
	 * @param string $order_id Order ID.
	 * @return bool Whether order contains a pre-order.
	 */
	public function order_contains_pre_order( $order_id ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			return WC_Pre_Orders_Order::order_contains_pre_order( $order_id );
		}
		return false;
	}

	/**
	 * Whether the order requires payment tokenization.
	 *
	 * @param string $order_id Order ID.
	 * @return bool Whether the order requires payment tokenization.
	 */
	public function order_requires_payment_tokenization( $order_id ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			return WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id );
		}
		return false;
	}

	/**
	 * Whether order contains a pre-order fee.
	 *
	 * @return bool Whether order contains a pre-order fee.
	 */
	public function cart_contains_pre_order_fee() {
		if ( class_exists( 'WC_Pre_Orders_Cart' ) ) {
			return WC_Pre_Orders_Cart::cart_contains_pre_order_fee();
		}
		return false;
	}
	/**
	 * Store the Payfast subscription token
	 *
	 * @param string          $token        Payfast subscription token.
	 * @param WC_Subscription $subscription The subscription object.
	 */
	protected function _set_subscription_token( $token, $subscription ) {
		$subscription->update_meta_data( '_payfast_subscription_token', $token );
		$subscription->save_meta_data();
	}

	/**
	 * Retrieve the Payfast subscription token for a given order id.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @return mixed Payfast subscription token.
	 */
	protected function _get_subscription_token( $subscription ) {
		return $subscription->get_meta( '_payfast_subscription_token', true );
	}

	/**
	 * Retrieve the Payfast subscription token for a given order id.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @return mixed
	 */
	protected function _delete_subscription_token( $subscription ) {
		return $subscription->delete_meta_data( '_payfast_subscription_token' );
	}

	/**
	 * Store the Payfast renewal flag
	 *
	 * @since 1.4.3
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 */
	protected function _set_renewal_flag( $subscription ) {
		$subscription->update_meta_data( '_payfast_renewal_flag', 'true' );
		$subscription->save_meta_data();
	}

	/**
	 * Retrieve the Payfast renewal flag for a given order id.
	 *
	 * @since 1.4.3
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @return bool
	 */
	protected function _has_renewal_flag( $subscription ) {
		return 'true' === $subscription->get_meta( '_payfast_renewal_flag', true );
	}

	/**
	 * Retrieve the Payfast renewal flag for a given order id.
	 *
	 * @since 1.4.3
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 */
	protected function _delete_renewal_flag( $subscription ) {
		$subscription->delete_meta_data( '_payfast_renewal_flag' );
		$subscription->save_meta_data();
	}

	/**
	 * Wrapper for WooCommerce subscription function wc_is_subscription.
	 *
	 * @param WC_Order|int $order The order.
	 * @return bool
	 */
	public function is_subscription( $order ) {
		if ( ! function_exists( 'wcs_is_subscription' ) ) {
			return false;
		}
		return wcs_is_subscription( $order );
	}

	/**
	 * Cancel Payfast Tokenization(ad-hoc) token if subscription changed to other payment method.
	 *
	 * @param WC_Subscription $subscription       The subscription for which the payment method changed.
	 * @param string          $new_payment_method New payment method name.
	 */
	public function maybe_cancel_subscription_token( $subscription, $new_payment_method ) {
		$token = $this->_get_subscription_token( $subscription );
		if ( empty( $token ) || $this->id === $new_payment_method ) {
			return;
		}
		$this->cancel_subscription_listener( $subscription );
		$this->_delete_subscription_token( $subscription );

		$this->log( 'Payfast subscription token Cancelled.' );
	}

	/**
	 * Whether order contains a subscription.
	 *
	 * Wrapper function for wcs_order_contains_subscription
	 *
	 * @param WC_Order $order Order object.
	 * @return bool Whether order contains a subscription.
	 */
	public function order_contains_subscription( $order ) {
		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}
		return wcs_order_contains_subscription( $order );
	}

	/**
	 * Process scheduled subscription payment and update the subscription status accordingly.
	 *
	 * @param float    $amount_to_charge Subscription cost.
	 * @param WC_Order $renewal_order    Renewal order object.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		$subscription = wcs_get_subscription( $renewal_order->get_meta( '_subscription_renewal', true ) );
		$this->log( 'Attempting to renew subscription from renewal order ' . self::get_order_prop( $renewal_order, 'id' ) );

		if ( empty( $subscription ) ) {
			$this->log( 'Subscription from renewal order was not found.' );
			return;
		}

		$response = $this->submit_subscription_payment( $subscription, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			/* translators: 1: error code 2: error message */
			$renewal_order->update_status( 'failed', sprintf( esc_html__( 'Payfast Subscription renewal transaction failed (%1$s:%2$s)', 'woocommerce-gateway-payfast' ), $response->get_error_code(), $response->get_error_message() ) );
		}
		// Payment will be completion will be capture only when the ITN callback is sent to $this->handle_itn_request().
		$renewal_order->add_order_note( esc_html__( 'Payfast Subscription renewal transaction submitted.', 'woocommerce-gateway-payfast' ) );
	}

	/**
	 * Attempt to process a subscription payment on the Payfast gateway.
	 *
	 * @param WC_Subscription $subscription     The subscription object.
	 * @param float           $amount_to_charge The amount to charge.
	 * @return mixed WP_Error on failure, bool true on success
	 */
	public function submit_subscription_payment( $subscription, $amount_to_charge ) {
		$token     = $this->_get_subscription_token( $subscription );
		$item_name = $this->get_subscription_name( $subscription );

		foreach ( $subscription->get_related_orders( 'all', 'renewal' ) as $order ) {
			$statuses_to_charge = array( 'on-hold', 'failed', 'pending' );
			if ( in_array( $order->get_status(), $statuses_to_charge, true ) ) {
				$latest_order_to_renew = $order;
				break;
			}
		}
		$item_description = wp_json_encode( array( 'renewal_order_id' => self::get_order_prop( $latest_order_to_renew, 'id' ) ) );

		return $this->submit_ad_hoc_payment( $token, $amount_to_charge, $item_name, $item_description );
	}

	/**
	 * Get a name for the subscription item. For multiple
	 * item only Subscription $date will be returned.
	 *
	 * For subscriptions with no items Site/Blog name will be returned.
	 *
	 * @param WC_Subscription $subscription The subscription object.
	 * @return string
	 */
	public function get_subscription_name( $subscription ) {

		if ( $subscription->get_item_count() > 1 ) {
			return $subscription->get_date_to_display( 'start' );
		} else {
			$items = $subscription->get_items();

			if ( empty( $items ) ) {
				return get_bloginfo( 'name' );
			}

			$item = array_shift( $items );
			return $item['name'];
		}
	}

	/**
	 * Setup api data for the the adhoc payment.
	 *
	 * @since 1.4.0 introduced.
	 *
	 * @param string $token            Payfast subscription token.
	 * @param float  $amount_to_charge Amount to charge.
	 * @param string $item_name        Item name.
	 * @param string $item_description Item description.
	 *
	 * @return bool|WP_Error WP_Error on failure, bool true on success
	 */
	public function submit_ad_hoc_payment( $token, $amount_to_charge, $item_name, $item_description ) {
		$args = array(
			'body' => array(
				'amount'           => $amount_to_charge * 100, // Convert to cents.
				'item_name'        => $item_name,
				'item_description' => $item_description,
			),
		);
		return $this->api_request( 'adhoc', $token, $args );
	}

	/**
	 * Send off API request.
	 *
	 * @since 1.4.0 introduced.
	 *
	 * @param string $command  API command.
	 * @param string $token    Payfast subscription token.
	 * @param array  $api_args Arguments for the API request. See WP documentation for wp_remote_request.
	 * @param string $method   GET | PUT | POST | DELETE.
	 *
	 * @return bool|WP_Error WP_Error on failure, bool true on success
	 */
	public function api_request( $command, $token, $api_args, $method = 'POST' ) {
		if ( empty( $token ) ) {
			$this->log( 'Error posting API request: No token supplied', true );
			return new WP_Error( '404', esc_html__( 'Can not submit Payfast request with an empty token', 'woocommerce-gateway-payfast' ), $results );
		}

		$api_endpoint  = "https://api.payfast.co.za/subscriptions/$token/$command";
		$api_endpoint .= 'yes' === $this->get_option( 'testmode' ) ? '?testing=true' : '';

		$timestamp           = current_time( rtrim( DateTime::ATOM, 'P' ) ) . '+02:00';
		$api_args['timeout'] = 45;
		$api_args['headers'] = array(
			'merchant-id' => $this->merchant_id,
			'timestamp'   => $timestamp,
			'version'     => 'v1',
		);

		// Set content length to fix "411: requests require a Content-length header" error.
		if ( 'cancel' === $command && ! isset( $api_args['body'] ) ) {
			$api_args['headers']['content-length'] = 0;
		}

		// Generate signature.
		$all_api_variables                = array_merge( $api_args['headers'], (array) $api_args['body'] );
		$api_args['headers']['signature'] = md5( $this->_generate_parameter_string( $all_api_variables ) );
		$api_args['method']               = strtoupper( $method );

		$results = wp_remote_request( $api_endpoint, $api_args );

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		// Check Payfast server response.
		if ( 200 !== $results['response']['code'] ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- used for logging.
			$this->log( "Error posting API request:\n" . print_r( $results['response'], true ) );
			return new WP_Error( $results['response']['code'], json_decode( $results['body'] )->data->response, $results );
		}

		// Check adhoc bank charge response.
		$results_data = json_decode( $results['body'], true )['data'];

		// Sandbox ENV returns true(boolean) in response, while Production ENV "true"(string) in response.
		if ( 'adhoc' === $command && ! ( 'true' === $results_data['response'] || true === $results_data['response'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- used for logging.
			$this->log( "Error posting API request:\n" . print_r( $results_data, true ) );

			$code    = is_array( $results_data['response'] ) ? $results_data['response']['code'] : $results_data['response'];
			$message = is_array( $results_data['response'] ) ? $results_data['response']['reason'] : $results_data['message'];
			// Use trim here to display it properly e.g. on an order note, since Payfast can include CRLF in a message.
			return new WP_Error( $code, trim( $message ), $results );
		}

		$maybe_json = json_decode( $results['body'], true );

		if ( ! is_null( $maybe_json ) && isset( $maybe_json['status'] ) && 'failed' === $maybe_json['status'] ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- used for logging.
			$this->log( "Error posting API request:\n" . print_r( $results['body'], true ) );

			// Use trim here to display it properly e.g. on an order note, since Payfast can include CRLF in a message.
			return new WP_Error( $maybe_json['code'], trim( $maybe_json['data']['message'] ), $results['body'] );
		}

		return true;
	}

	/**
	 * Responds to Subscriptions extension cancellation event.
	 *
	 * @since 1.4.0 introduced.
	 * @param WC_Subscription $subscription The subscription object.
	 */
	public function cancel_subscription_listener( $subscription ) {
		$token = $this->_get_subscription_token( $subscription );
		if ( empty( $token ) ) {
			return;
		}
		$this->api_request( 'cancel', $token, array(), 'PUT' );
	}

	/**
	 * Cancel a pre-order subscription.
	 *
	 * @since 1.4.0
	 *
	 * @param string $token Payfast subscription token.
	 *
	 * @return bool|WP_Error WP_Error on failure, bool true on success.
	 */
	public function cancel_pre_order_subscription( $token ) {
		return $this->api_request( 'cancel', $token, array(), 'PUT' );
	}

	/**
	 * Generate the parameter string to send to Payfast.
	 *
	 * @since 1.4.0 introduced.
	 *
	 * @param array $api_data               Data to send to the Payfast API.
	 * @param bool  $sort_data_before_merge Whether to sort before merge. Default true.
	 * @param bool  $skip_empty_values      Should key value pairs be ignored when generating signature? Default true.
	 *
	 * @return string
	 */
	protected function _generate_parameter_string( $api_data, $sort_data_before_merge = true, $skip_empty_values = true ) {

		// if sorting is required the passphrase should be added in before sort.
		if ( ! empty( $this->pass_phrase ) && $sort_data_before_merge ) {
			$api_data['passphrase'] = $this->pass_phrase;
		}

		if ( $sort_data_before_merge ) {
			ksort( $api_data );
		}

		// concatenate the array key value pairs.
		$parameter_string = '';
		foreach ( $api_data as $key => $val ) {

			if ( $skip_empty_values && empty( $val ) ) {
				continue;
			}

			if ( 'signature' !== $key ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- legacy code, validation required prior to switching to rawurlencode.
				$val               = urlencode( $val );
				$parameter_string .= "$key=$val&";
			}
		}
		// When not sorting passphrase should be added to the end before md5.
		if ( $sort_data_before_merge ) {
			$parameter_string = rtrim( $parameter_string, '&' );
		} elseif ( ! empty( $this->pass_phrase ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- legacy code, validation required prior to switching to rawurlencode.
			$parameter_string .= 'passphrase=' . urlencode( $this->pass_phrase );
		} else {
			$parameter_string = rtrim( $parameter_string, '&' );
		}

		return $parameter_string;
	}

	/**
	 * Process pre-order payment.
	 *
	 * @since 1.4.0 introduced.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function process_pre_order_payments( $order ) {
		wc_deprecated_function( 'process_pre_order_payments', '1.6.3' );
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the Payfast gateway.
	 *
	 * @since 1.0.0
	 */
	public function setup_constants() {
		// Create user agent string.
		define( 'PF_SOFTWARE_NAME', 'WooCommerce' );
		define( 'PF_SOFTWARE_VER', WC_VERSION );
		define( 'PF_MODULE_NAME', 'WooCommerce-Payfast-Free' );
		define( 'PF_MODULE_VER', $this->version );

		// Features
		// - PHP.
		$pf_features = 'PHP ' . phpversion() . ';';

		// - cURL.
		if ( in_array( 'curl', get_loaded_extensions(), true ) ) {
			define( 'PF_CURL', '' );
			$pf_version   = curl_version();
			$pf_features .= ' curl ' . $pf_version['version'] . ';';
		} else {
			$pf_features .= ' nocurl;';
		}

		// Create user agent.
		define( 'PF_USER_AGENT', PF_SOFTWARE_NAME . '/' . PF_SOFTWARE_VER . ' (' . trim( $pf_features ) . ') ' . PF_MODULE_NAME . '/' . PF_MODULE_VER );

		// General Defines.
		define( 'PF_TIMEOUT', 15 );
		define( 'PF_EPSILON', 0.01 );

		// Error messages.
		define( 'PF_ERR_AMOUNT_MISMATCH', esc_html__( 'Amount mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_BAD_ACCESS', esc_html__( 'Bad access of page', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_BAD_SOURCE_IP', esc_html__( 'Bad source IP address', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_CONNECT_FAILED', esc_html__( 'Failed to connect to Payfast', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_INVALID_SIGNATURE', esc_html__( 'Security signature mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_MERCHANT_ID_MISMATCH', esc_html__( 'Merchant ID mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_NO_SESSION', esc_html__( 'No saved session found for ITN transaction', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_ID_MISSING_URL', esc_html__( 'Order ID not present in URL', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_ID_MISMATCH', esc_html__( 'Order ID mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_INVALID', esc_html__( 'This order ID is invalid', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_NUMBER_MISMATCH', esc_html__( 'Order Number mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_PROCESSED', esc_html__( 'This order has already been processed', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_PDT_FAIL', esc_html__( 'PDT query failed', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_PDT_TOKEN_MISSING', esc_html__( 'PDT token not present in URL', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_SESSIONID_MISMATCH', esc_html__( 'Session ID mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_UNKNOWN', esc_html__( 'Unkown error occurred', 'woocommerce-gateway-payfast' ) );

		// General messages.
		define( 'PF_MSG_OK', esc_html__( 'Payment was successful', 'woocommerce-gateway-payfast' ) );
		define( 'PF_MSG_FAILED', esc_html__( 'Payment has failed', 'woocommerce-gateway-payfast' ) );
		define( 'PF_MSG_PENDING', esc_html__( 'The payment is pending. Please note, you will receive another Instant Transaction Notification when the payment status changes to "Completed", or "Failed"', 'woocommerce-gateway-payfast' ) );

		/**
		 * Fires after Payfast constants are setup.
		 *
		 * @since 1.4.13
		 */
		do_action( 'woocommerce_gateway_payfast_setup_constants' );
	}

	/**
	 * Log system processes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Log message.
	 */
	public function log( $message ) {
		if ( 'yes' === $this->get_option( 'testmode' ) || $this->enable_logging ) {
			if ( empty( $this->logger ) ) {
				$this->logger = new WC_Logger();
			}
			$this->logger->add( 'payfast', $message );
		}
	}

	/**
	 * Validate the signature against the returned data.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data      Returned data.
	 * @param string $signature Signature to check.
	 * @return bool Whether the signature is valid.
	 */
	public function validate_signature( $data, $signature ) {
		$result = $data['signature'] === $signature;
		$this->log( 'Signature = ' . ( $result ? 'valid' : 'invalid' ) );
		return $result;
	}

	/**
	 * Validate the IP address to make sure it's coming from Payfast.
	 *
	 * @param string $source_ip Source IP.
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_valid_ip( $source_ip ) {
		// Variable initialization.
		$valid_hosts = array(
			'www.payfast.co.za',
			'sandbox.payfast.co.za',
			'w1w.payfast.co.za',
			'w2w.payfast.co.za',
		);

		$valid_ips = array();

		foreach ( $valid_hosts as $pf_hostname ) {
			$ips = gethostbynamel( $pf_hostname );

			if ( false !== $ips ) {
				$valid_ips = array_merge( $valid_ips, $ips );
			}
		}

		// Remove duplicates.
		$valid_ips = array_unique( $valid_ips );

		// Adds support for X_Forwarded_For.
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$x_forwarded_http_header = trim( current( preg_split( '/[,:]/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );
			$source_ip               = rest_is_ip_address( $x_forwarded_http_header ) ? rest_is_ip_address( $x_forwarded_http_header ) : $source_ip;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- used for logging.
		$this->log( "Valid IPs:\n" . print_r( $valid_ips, true ) );
		$is_valid_ip = in_array( $source_ip, $valid_ips, true );

		/**
		 * Filter whether Payfast Gateway IP address is valid.
		 *
		 * @since 1.4.13
		 *
		 * @param bool $is_valid_ip Whether IP address is valid.
		 * @param bool $source_ip   Source IP.
		 */
		return apply_filters( 'woocommerce_gateway_payfast_is_valid_ip', $is_valid_ip, $source_ip );
	}

	/**
	 * Validate response data.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $post_data POST data for original request.
	 * @param string $proxy     Address of proxy to use or NULL if no proxy.
	 * @return bool
	 */
	public function validate_response_data( $post_data, $proxy = null ) {
		$this->log( 'Host = ' . $this->validate_url );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- used for logging.
		$this->log( 'Params = ' . print_r( $post_data, true ) );

		if ( ! is_array( $post_data ) ) {
			return false;
		}

		$response = wp_remote_post(
			$this->validate_url,
			array(
				'body'       => $post_data,
				'timeout'    => 70,
				'user-agent' => PF_USER_AGENT,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- used for logging.
			$this->log( "Response error:\n" . print_r( $response, true ) );
			return false;
		}

		parse_str( $response['body'], $parsed_response );

		$response = $parsed_response;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- used for logging.
		$this->log( "Response:\n" . print_r( $response, true ) );

		// Interpret Response.
		if ( is_array( $response ) && in_array( 'VALID', array_keys( $response ), true ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check the given amounts are equal.
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @since 1.0.0
	 *
	 * @param float $amount1 1st amount for comparison.
	 * @param float $amount2 2nd amount for comparison.
	 *
	 * @return bool
	 */
	public function amounts_equal( $amount1, $amount2 ) {
		return ! ( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PF_EPSILON );
	}

	/**
	 * Get order property with compatibility check on order getter introduced
	 * in WC 3.0.
	 *
	 * @since 1.4.1
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $prop  Property name.
	 *
	 * @return mixed Property value
	 */
	public static function get_order_prop( $order, $prop ) {
		switch ( $prop ) {
			case 'order_total':
				$getter = array( $order, 'get_total' );
				break;
			default:
				$getter = array( $order, 'get_' . $prop );
				break;
		}

		return is_callable( $getter ) ? call_user_func( $getter ) : $order->{ $prop };
	}

	/**
	 * Gets user-friendly error message strings from keys
	 *
	 * @param   string $key  The key representing an error.
	 *
	 * @return  string        The user-friendly error message for display
	 */
	public function get_error_message( $key ) {
		switch ( $key ) {
			case 'wc-gateway-payfast-error-invalid-currency':
				return esc_html__( 'Your store uses a currency that Payfast doesn\'t support yet.', 'woocommerce-gateway-payfast' );
			case 'wc-gateway-payfast-error-missing-merchant-id':
				return esc_html__( 'You forgot to fill your merchant ID.', 'woocommerce-gateway-payfast' );
			case 'wc-gateway-payfast-error-missing-merchant-key':
				return esc_html__( 'You forgot to fill your merchant key.', 'woocommerce-gateway-payfast' );
			case 'wc-gateway-payfast-error-missing-pass-phrase':
				return esc_html__( 'Payfast requires a passphrase to work.', 'woocommerce-gateway-payfast' );
			default:
				return '';
		}
	}

	/**
	 * Show possible admin notices
	 */
	public function admin_notices() {

		// Get requirement errors.
		$errors_to_show = $this->check_requirements();

		// If everything is in place, don't display it.
		if ( ! count( $errors_to_show ) ) {
			return;
		}

		// If the gateway isn't enabled, don't show it.
		if ( 'no' === $this->enabled ) {
			return;
		}

		// Use transients to display the admin notice once after saving values.
		if ( ! get_transient( 'wc-gateway-payfast-admin-notice-transient' ) ) {
			set_transient( 'wc-gateway-payfast-admin-notice-transient', 1, 1 );

			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html__( 'To use Payfast as a payment provider, you need to fix the problems below:', 'woocommerce-gateway-payfast' ) . '</p>'
				. '<ul style="list-style-type: disc; list-style-position: inside; padding-left: 2em;">'
				. wp_kses_post(
					array_reduce(
						$errors_to_show,
						function ( $errors_list, $error_item ) {
							$errors_list = $errors_list . PHP_EOL . ( '<li>' . $this->get_error_message( $error_item ) . '</li>' );
							return $errors_list;
						},
						''
					)
				)
				. '</ul></p></div>';
		}
	}

	/**
	 * Displays the amount_fee as returned by payfast.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function display_order_fee( $order_id ) {

		$order = wc_get_order( $order_id );
		$fee   = $order->get_meta( 'payfast_amount_fee', true );

		if ( ! $fee ) {
			return;
		}
		?>

		<tr>
			<td class="label payfast-fee">
				<?php echo wc_help_tip( __( 'This represents the fee Payfast collects for the transaction.', 'woocommerce-gateway-payfast' ) ); ?>
				<?php esc_html_e( 'Payfast Fee:', 'woocommerce-gateway-payfast' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wp_kses_post( wc_price( $fee, array( 'decimals' => 2 ) ) ); ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Displays the amount_net as returned by payfast.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function display_order_net( $order_id ) {

		$order = wc_get_order( $order_id );
		$net   = $order->get_meta( 'payfast_amount_net', true );

		if ( ! $net ) {
			return;
		}

		?>

		<tr>
			<td class="label payfast-net">
				<?php echo wc_help_tip( __( 'This represents the net total that was credited to your Payfast account.', 'woocommerce-gateway-payfast' ) ); ?>
				<?php esc_html_e( 'Amount Net:', 'woocommerce-gateway-payfast' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wp_kses_post( wc_price( $net, array( 'decimals' => 2 ) ) ); ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Filters the currency to 'ZAR' if set via WooPayments multi-currency feature.
	 *
	 * @param string $currency The currency code.
	 * @return string
	 */
	public function filter_currency( $currency ) {
		// Do nothing if WooPayments is not activated.
		if ( ! class_exists( '\WCPay\MultiCurrency\MultiCurrency' ) ) {
			return $currency;
		}

		// Do nothing if the page is admin screen.
		if ( is_admin() ) {
			return $currency;
		}

		$user_id = get_current_user_id();

		// Check if the currency is set in the URL.
		if ( isset( $_GET['currency'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$currency_code = sanitize_text_field(
				wp_unslash( $_GET['currency'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
			// Check if the currency is set in the session (for logged-out users).
		} elseif ( 0 === $user_id && WC()->session ) {
			$currency_code = WC()->session->get( \WCPay\MultiCurrency\MultiCurrency::CURRENCY_SESSION_KEY );
			// Check if the currency is set in the user meta (for logged-in users).
		} elseif ( $user_id ) {
			$currency_code = get_user_meta( $user_id, \WCPay\MultiCurrency\MultiCurrency::CURRENCY_META_KEY, true );
		}

		if ( is_string( $currency_code ) && 'ZAR' === $currency_code ) {
			return 'ZAR';
		}

		return $currency;
	}
}
