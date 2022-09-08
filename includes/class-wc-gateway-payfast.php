<?php
/**
 * PayFast Payment Gateway
 *
 * Provides a PayFast Payment Gateway.
 *
 * @class  woocommerce_payfast
 * @package WooCommerce
 * @category Payment Gateways
 * @author WooCommerce
 */


class WC_Gateway_PayFast extends WC_Payment_Gateway {

	/**
	 * Version
	 *
	 * @var string
	 */
	public $version;

	/**
	 * @access protected
	 * @var array $data_to_send
	 */
	protected $data_to_send = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version = WC_GATEWAY_PAYFAST_VERSION;
		$this->id = 'payfast';
		$this->method_title       = __( 'PayFast', 'woocommerce-gateway-payfast' );
		/* translators: 1: a href link 2: closing href */
		$this->method_description = sprintf( __( 'PayFast works by sending the user to %1$sPayFast%2$s to enter their payment information.', 'woocommerce-gateway-payfast' ), '<a href="http://payfast.co.za/">', '</a>' );
		$this->icon               = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/assets/images/icon.png';
		$this->debug_email        = get_option( 'admin_email' );
		$this->available_countries  = array( 'ZA' );
		$this->available_currencies = (array)apply_filters('woocommerce_gateway_payfast_available_currencies', array( 'ZAR' ) );

		// Supported functionality
		$this->supports = array(
			'products',
			'pre-orders',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change', // Subs 1.x support
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
		$this->response_url	    = add_query_arg( 'wc-api', 'WC_Gateway_PayFast', home_url( '/' ) );
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
		add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_payments' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Add fees to order.
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_order_fee') );
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'display_order_net'), 20 );

		// Change Payment Method actions.
		add_action( 'woocommerce_subscription_payment_method_updated_from_' . $this->id, array( $this, 'maybe_cancel_subscription_token' ), 10, 2 );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-gateway-payfast' ),
				'label'       => __( 'Enable PayFast', 'woocommerce-gateway-payfast' ),
				'type'        => 'checkbox',
				'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-payfast' ),
				'default'     => 'no',		// User should enter the required information before enabling the gateway.
				'desc_tip'    => true,
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payfast' ),
				'default'     => __( 'PayFast', 'woocommerce-gateway-payfast' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'testmode' => array(
				'title'       => __( 'PayFast Sandbox', 'woocommerce-gateway-payfast' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in development mode.', 'woocommerce-gateway-payfast' ),
				'default'     => 'yes',
			),
			'merchant_id' => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This is the merchant ID, received from PayFast.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
			),
			'merchant_key' => array(
				'title'       => __( 'Merchant Key', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'This is the merchant key, received from PayFast.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
			),
			'pass_phrase' => array(
				'title'       => __( 'Passphrase', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( '* Required. Needed to ensure the data passed through is secure.', 'woocommerce-gateway-payfast' ),
				'default'     => '',
			),
			'send_debug_email' => array(
				'title'   => __( 'Send Debug Emails', 'woocommerce-gateway-payfast' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send debug e-mails for transactions through the PayFast gateway (sends on successful transaction as well).', 'woocommerce-gateway-payfast' ),
				'default' => 'yes',
			),
			'debug_email' => array(
				'title'       => __( 'Who Receives Debug E-mails?', 'woocommerce-gateway-payfast' ),
				'type'        => 'text',
				'description' => __( 'The e-mail address to which debugging error e-mails are sent when in test mode.', 'woocommerce-gateway-payfast' ),
				'default'     => get_option( 'admin_email' ),
			),
			'enable_logging' => array(
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
			'pass_phrase'
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
	 * add_testmode_admin_settings_notice()
	 * Add a notice to the merchant_key and merchant_id fields when in test mode.
	 *
	 * @since 1.0.0
	 */
	public function add_testmode_admin_settings_notice() {
		$this->form_fields['merchant_id']['description']  .= ' <strong>' . __( 'Sandbox Merchant ID currently in use', 'woocommerce-gateway-payfast' ) . ' ( ' . esc_html( $this->merchant_id ) . ' ).</strong>';
		$this->form_fields['merchant_key']['description'] .= ' <strong>' . __( 'Sandbox Merchant Key currently in use', 'woocommerce-gateway-payfast' ) . ' ( ' . esc_html( $this->merchant_key ) . ' ).</strong>';
	}

	/**
	 * check_requirements()
	 *
	 * Check if this gateway is enabled and available in the base currency being traded with.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function check_requirements() {

		$errors = [
			// Check if the store currency is supported by PayFast
			! in_array( get_woocommerce_currency(), $this->available_currencies ) ? 'wc-gateway-payfast-error-invalid-currency' : null,
			// Check if user entered the merchant ID
			'yes' !== $this->get_option( 'testmode' ) && empty( $this->get_option( 'merchant_id' ) )  ? 'wc-gateway-payfast-error-missing-merchant-id' : null,
			// Check if user entered the merchant key
			'yes' !== $this->get_option( 'testmode' ) && empty( $this->get_option( 'merchant_key' ) ) ? 'wc-gateway-payfast-error-missing-merchant-key' : null,
			// Check if user entered a pass phrase
			'yes' !== $this->get_option( 'testmode' ) && empty( $this->get_option( 'pass_phrase' ) )  ? 'wc-gateway-payfast-error-missing-pass-phrase' : null
		];

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
		if ( in_array( get_woocommerce_currency(), $this->available_currencies ) ) {
			parent::admin_options();
		} else {
		?>
			<h3><?php _e( 'PayFast', 'woocommerce-gateway-payfast' ); ?></h3>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce-gateway-payfast' ); ?></strong> <?php /* translators: 1: a href link 2: closing href */ echo sprintf( __( 'Choose South African Rands as your store currency in %1$sGeneral Settings%2$s to enable the PayFast Gateway.', 'woocommerce-gateway-payfast' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">', '</a>' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Generate the PayFast button link.
	 *
	 * @since 1.0.0
	 */
	public function generate_payfast_form( $order_id ) {
		$order         = wc_get_order( $order_id );
		// Construct variables for post
		$this->data_to_send = array(
			// Merchant details
			'merchant_id'      => $this->merchant_id,
			'merchant_key'     => $this->merchant_key,
			'return_url'       => esc_url_raw( add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) ) ),
			'cancel_url'       => $order->get_cancel_order_url(),
			'notify_url'       => $this->response_url,

			// Billing details
			'name_first'       => self::get_order_prop( $order, 'billing_first_name' ),
			'name_last'        => self::get_order_prop( $order, 'billing_last_name' ),
			'email_address'    => self::get_order_prop( $order, 'billing_email' ),

			// Item details
			'm_payment_id'     => ltrim( $order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce-gateway-payfast' ) ),
			'amount'           => $order->get_total(),
			'item_name'        => get_bloginfo( 'name' ) . ' - ' . $order->get_order_number(),
			/* translators: 1: blog info name */
			'item_description' => sprintf( __( 'New order from %s', 'woocommerce-gateway-payfast' ), get_bloginfo( 'name' ) ),

			// Custom strings
			'custom_str1'      => self::get_order_prop( $order, 'order_key' ),
			'custom_str2'      => 'WooCommerce/' . WC_VERSION . '; ' . get_site_url(),
			'custom_str3'      => self::get_order_prop( $order, 'id' ),
			'source'           => 'WooCommerce-Free-Plugin',
		);

		/**
		 * Check If changing payment method.
		 * We have to generate Tokenization (ad-hoc) token to charge future payments.
		 */
		if ( $this->is_subscription( $order_id ) ) {
			// 2 == ad-hoc subscription type see PayFast API docs
			$this->data_to_send['subscription_type'] = '2';
		}

		// add subscription parameters
		if ( $this->order_contains_subscription( $order_id ) ) {
			// 2 == ad-hoc subscription type see PayFast API docs
			$this->data_to_send['subscription_type'] = '2';
		}

		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			$current       = reset( $subscriptions );
			// For renewal orders that have subscriptions with renewal flag OR
			// For renew orders which are failed to pay by other payment gateway buy now payinh using Payfast.
			// we will create a new subscription in PayFast and link it to the existing ones in WC.
			// The old subscriptions in PayFast will be cancelled once we handle the itn request.
			if ( count( $subscriptions ) > 0 && ( $this->_has_renewal_flag( $current ) || $this->id !== $current->get_payment_method() ) ) {
				// 2 == ad-hoc subscription type see PayFast API docs
				$this->data_to_send['subscription_type'] = '2';
			}
		}

		// pre-order: add the subscription type for pre order that require tokenization
		// at this point we assume that the order pre order fee and that
		// we should only charge that on the order. The rest will be charged later.
		if ( $this->order_contains_pre_order( $order_id )
			 && $this->order_requires_payment_tokenization( $order_id ) ) {
			$this->data_to_send['amount']            = $this->get_pre_order_fee( $order_id );
			$this->data_to_send['subscription_type'] = '2';
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
		$sign_strings = [];
		foreach ( $this->data_to_send as $key => $value ) {
			if ($key !== 'source') {
				$sign_strings[] = esc_attr( $key ) . '=' . urlencode(str_replace('&amp;', '&', trim( $value )));
			}
			$payfast_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}

		if (!empty($this->pass_phrase)) {
			$payfast_args_array[] = '<input type="hidden" name="signature" value="' . md5(implode('&', $sign_strings) . '&passphrase=' . urlencode($this->pass_phrase)) . '" />';
		} else {
			$payfast_args_array[] = '<input type="hidden" name="signature" value="' . md5(implode('&', $sign_strings)) . '" />';
		}

		return '<form action="' . esc_url( $this->url ) . '" method="post" id="payfast_payment_form">
				' . implode( '', $payfast_args_array ) . '
				<input type="submit" class="button-alt" id="submit_payfast_payment_form" value="' . __( 'Pay via PayFast', 'woocommerce-gateway-payfast' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce-gateway-payfast' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "' . __( 'Thank you for your order. We are now redirecting you to PayFast to make payment.', 'woocommerce-gateway-payfast' ) . '",
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
	 */
	public function process_payment( $order_id ) {

		if ( $this->order_contains_pre_order( $order_id )
			&& $this->order_requires_payment_tokenization( $order_id )
			&& ! $this->cart_contains_pre_order_fee() ) {
				throw new Exception( 'PayFast does not support transactions without any upfront costs or fees. Please select another gateway' );
		}

		$order = wc_get_order( $order_id );
		return array(
			'result' 	 => 'success',
			'redirect'	 => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to PayFast.
	 *
	 * @since 1.0.0
	 */
	public function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with PayFast.', 'woocommerce-gateway-payfast' ) . '</p>';
		echo $this->generate_payfast_form( $order );
	}

	/**
	 * Check PayFast ITN response.
	 *
	 * @since 1.0.0
	 */
	public function check_itn_response() {
		$this->handle_itn_request( stripslashes_deep( $_POST ) );

		// Notify PayFast that information has been received
		header( 'HTTP/1.0 200 OK' );
		flush();
	}

	/**
	 * Check PayFast ITN validity.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	public function handle_itn_request( $data ) {
		$this->log( PHP_EOL
			. '----------'
			. PHP_EOL . 'PayFast ITN call received'
			. PHP_EOL . '----------'
		);
		$this->log( 'Get posted data' );
		$this->log( 'PayFast Data: ' . print_r( $data, true ) );

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
			$payfast_error  = true;
			$payfast_error_message = PF_ERR_BAD_ACCESS;
		}

		// Verify security signature
		if ( ! $payfast_error && ! $payfast_done ) {
			$this->log( 'Verify security signature' );
			$signature = md5( $this->_generate_parameter_string( $data, false, false ) ); // false not to sort data
			// If signature different, log for debugging
			if ( ! $this->validate_signature( $data, $signature ) ) {
				$payfast_error         = true;
				$payfast_error_message = PF_ERR_INVALID_SIGNATURE;
			}
		}

		// Verify source IP (If not in debug mode)
		if ( ! $payfast_error && ! $payfast_done
			&& $this->get_option( 'testmode' ) != 'yes' ) {
			$this->log( 'Verify source IP' );

			if ( ! $this->is_valid_ip( $_SERVER['REMOTE_ADDR'] ) ) {
				$payfast_error  = true;
				$payfast_error_message = PF_ERR_BAD_SOURCE_IP;
			}
		}

		// Verify data received
		if ( ! $payfast_error ) {
			$this->log( 'Verify data received' );
			$validation_data = $data;
			unset( $validation_data['signature'] );
			$has_valid_response_data = $this->validate_response_data( $validation_data );

			if ( ! $has_valid_response_data ) {
				$payfast_error = true;
				$payfast_error_message = PF_ERR_BAD_ACCESS;
			}
		}

		// Check data against internal order
		if ( ! $payfast_error && ! $payfast_done ) {
			$this->log( 'Check data against internal order' );

			// Check order amount
			if ( ! $this->amounts_equal( $data['amount_gross'], self::get_order_prop( $order, 'order_total' ) )
				 && ! $this->order_contains_pre_order( $order_id )
				 && ! $this->order_contains_subscription( $order_id )
				 && ! $this->is_subscription( $order_id ) ) { // if changing payment method.
				$payfast_error  = true;
				$payfast_error_message = PF_ERR_AMOUNT_MISMATCH;
			} elseif ( strcasecmp( $data['custom_str1'], self::get_order_prop( $order, 'order_key' ) ) != 0 ) {
				// Check session ID
				$payfast_error  = true;
				$payfast_error_message = PF_ERR_SESSIONID_MISMATCH;
			}
		}

		// alter order object to be the renewal order if
		// the ITN request comes as a result of a renewal submission request
		$description = json_decode( $data['item_description'] );

		if ( ! empty( $description->renewal_order_id ) ) {
			$order = wc_get_order( $description->renewal_order_id );
		}

		// Get internal order and verify it hasn't already been processed
		if ( ! $payfast_error && ! $payfast_done ) {
			$this->log_order_details( $order );

			// Check if order has already been processed
			if ( 'completed' === self::get_order_prop( $order, 'status' ) ) {
				$this->log( 'Order has already been processed' );
				$payfast_done = true;
			}
		}

		// If an error occurred
		if ( $payfast_error ) {
			$this->log( 'Error occurred: ' . $payfast_error_message );

			if ( $this->send_debug_email ) {
				$this->log( 'Sending email notification' );

				 // Send an email
				$subject = 'PayFast ITN error: ' . $payfast_error_message;
				$body =
					"Hi,\n\n" .
					"An invalid PayFast transaction on your website requires attention\n" .
					"------------------------------------------------------------\n" .
					'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n" .
					'Remote IP Address: ' . $_SERVER['REMOTE_ADDR'] . "\n" .
					'Remote host name: ' . gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) . "\n" .
					'Purchase ID: ' . self::get_order_prop( $order, 'id' ) . "\n" .
					'User ID: ' . self::get_order_prop( $order, 'user_id' ) . "\n";
				if ( isset( $data['pf_payment_id'] ) ) {
					$body .= 'PayFast Transaction ID: ' . esc_html( $data['pf_payment_id'] ) . "\n";
				}
				if ( isset( $data['payment_status'] ) ) {
					$body .= 'PayFast Payment Status: ' . esc_html( $data['payment_status'] ) . "\n";
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

					// For all other errors there is no need to add additional information
					default:
						break;
				}

				wp_mail( $debug_email, $subject, $body );
			} // End if().
		} elseif ( ! $payfast_done ) {

			$this->log( 'Check status and update order' );

			if ( self::get_order_prop( $original_order, 'order_key' ) !== $order_key ) {
				$this->log( 'Order key does not match' );
				exit;
			}

			$status = strtolower( $data['payment_status'] );

			/**
			 * Handle Changing Payment Method.
			 *   - Save payfast subscription token to handle future payment
			 *   - (for Payfast to Payfast payment method change) Cancel old token, as future payment will be handle with new token
			 */
			if ( $this->is_subscription( $order_id ) && floatval( 0 ) === floatval( $data['amount_gross'] ) ) {
				$this->log( '- Change Payment Method' );
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
		} // End if().

		$this->log( PHP_EOL
			. '----------'
			. PHP_EOL . 'End ITN call'
			. PHP_EOL . '----------'
		);

	}

	/**
	 * Handle logging the order details.
	 *
	 * @since 1.4.5
	 */
	public function log_order_details( $order ) {
		$customer_id = $order->get_user_id();

		$details = "Order Details:"
		. PHP_EOL . 'customer id:' . $customer_id
		. PHP_EOL . 'order id:   ' . $order->get_id()
		. PHP_EOL . 'parent id:  ' . $order->get_parent_id()
		. PHP_EOL . 'status:     ' . $order->get_status()
		. PHP_EOL . 'total:      ' . $order->get_total()
		. PHP_EOL . 'currency:   ' . $order->get_currency()
		. PHP_EOL . 'key:        ' . $order->get_order_key()
		. "";

		$this->log( $details );
	}

	/**
	 * This function mainly responds to ITN cancel requests initiated on PayFast, but also acts
	 * just in case they are not cancelled.
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array $data should be from the Gatewy ITN callback.
	 * @param WC_Order $order
	 */
	public function handle_itn_payment_cancelled( $data, $order, $subscriptions ) {

		remove_action( 'woocommerce_subscription_status_cancelled', array( $this, 'cancel_subscription_listener' ) );
		foreach ( $subscriptions as $subscription ) {
			if ( 'cancelled' !== $subscription->get_status() ) {
				$subscription->update_status( 'cancelled', __( 'Merchant cancelled subscription on PayFast.' , 'woocommerce-gateway-payfast' ) );
				$this->_delete_subscription_token( $subscription );
			}
		}
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'cancel_subscription_listener' ) );
	}

	/**
	 * This function handles payment complete request by PayFast.
	 * @version 1.4.3 Subscriptions flag
	 *
	 * @param array $data should be from the Gatewy ITN callback.
	 * @param WC_Order $order
	 */
	public function handle_itn_payment_complete( $data, $order, $subscriptions ) {
		$this->log( '- Complete' );
		$order->add_order_note( __( 'ITN payment completed', 'woocommerce-gateway-payfast' ) );
		$order->update_meta_data( 'payfast_amount_fee', $data['amount_fee'] );
		$order->update_meta_data( 'payfast_amount_net', $data['amount_net'] );
		$order_id = self::get_order_prop( $order, 'id' );

		// Store token for future subscription deductions.
		if ( count( $subscriptions ) > 0 && isset( $data['token'] ) ) {
			if ( $this->_has_renewal_flag( reset( $subscriptions ) ) ) {
				// renewal flag is set to true, so we need to cancel previous token since we will create a new one
				$this->log( 'Cancel previous subscriptions with token ' . $this->_get_subscription_token( reset( $subscriptions ) ) );

				// only request API cancel token for the first subscription since all of them are using the same token
				$this->cancel_subscription_listener( reset( $subscriptions ) );
			}

			$token = sanitize_text_field( $data['token'] );
			foreach ( $subscriptions as $subscription ) {
				$this->_delete_renewal_flag( $subscription );
				$this->_set_subscription_token( $token, $subscription );
			}
		}

		// the same mechanism (adhoc token) is used to capture payment later
		if ( $this->order_contains_pre_order( $order_id )
			&& $this->order_requires_payment_tokenization( $order_id ) ) {

			$token = sanitize_text_field( $data['token'] );
			$is_pre_order_fee_paid = $order->get_meta( '_pre_order_fee_paid', true ) === 'yes';

			if ( ! $is_pre_order_fee_paid ) {
				/* translators: 1: gross amount 2: payment id */
				$order->add_order_note( sprintf( __( 'PayFast pre-order fee paid: R %1$s (%2$s)', 'woocommerce-gateway-payfast' ), $data['amount_gross'], $data['pf_payment_id'] ) );
				$this->_set_pre_order_token( $token, $order );
				// set order to pre-ordered
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
				$order->update_meta_data( '_pre_order_fee_paid', 'yes' );
				$order->save_meta_data();
				WC()->cart->empty_cart();
			} else {
				/* translators: 1: gross amount 2: payment id */
				$order->add_order_note( sprintf( __( 'PayFast pre-order product line total paid: R %1$s (%2$s)', 'woocommerce-gateway-payfast' ), $data['amount_gross'], $data['pf_payment_id'] ) );
				$order->payment_complete( $data['pf_payment_id'] );
				$this->cancel_pre_order_subscription( $token );
			}
		} else {
			$order->payment_complete( $data['pf_payment_id'] );
		}

		$debug_email   = $this->get_option( 'debug_email', get_option( 'admin_email' ) );
		$vendor_name    = get_bloginfo( 'name', 'display' );
		$vendor_url     = home_url( '/' );
		if ( $this->send_debug_email ) {
			$subject = 'PayFast ITN on your site';
			$body =
				"Hi,\n\n"
				. "A PayFast transaction has been completed on your website\n"
				. "------------------------------------------------------------\n"
				. 'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n"
				. 'Purchase ID: ' . esc_html( $data['m_payment_id'] ) . "\n"
				. 'PayFast Transaction ID: ' . esc_html( $data['pf_payment_id'] ) . "\n"
				. 'PayFast Payment Status: ' . esc_html( $data['payment_status'] ) . "\n"
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
	 * @param $data
	 * @param $order
	 */
	public function handle_itn_payment_failed( $data, $order ) {
		$this->log( '- Failed' );
		/* translators: 1: payment status */
		$order->update_status( 'failed', sprintf( __( 'Payment %s via ITN.', 'woocommerce-gateway-payfast' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );
		$debug_email   = $this->get_option( 'debug_email', get_option( 'admin_email' ) );
		$vendor_name    = get_bloginfo( 'name', 'display' );
		$vendor_url     = home_url( '/' );

		if ( $this->send_debug_email ) {
			$subject = 'PayFast ITN Transaction on your site';
			$body =
				"Hi,\n\n" .
				"A failed PayFast transaction on your website requires attention\n" .
				"------------------------------------------------------------\n" .
				'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n" .
				'Purchase ID: ' . self::get_order_prop( $order, 'id' ) . "\n" .
				'User ID: ' . self::get_order_prop( $order, 'user_id' ) . "\n" .
				'PayFast Transaction ID: ' . esc_html( $data['pf_payment_id'] ) . "\n" .
				'PayFast Payment Status: ' . esc_html( $data['payment_status'] );
			wp_mail( $debug_email, $subject, $body );
		}
	}

	/**
	 * @since 1.4.0 introduced
	 * @param $data
	 * @param $order
	 */
	public function handle_itn_payment_pending( $data, $order ) {
		$this->log( '- Pending' );
		// Need to wait for "Completed" before processing
		/* translators: 1: payment status */
		$order->update_status( 'on-hold', sprintf( __( 'Payment %s via ITN.', 'woocommerce-gateway-payfast' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );
	}

	/**
	 * @param string $order_id
	 * @return double
	 */
	public function get_pre_order_fee( $order_id ) {
		foreach ( wc_get_order( $order_id )->get_fees() as $fee ) {
			if ( is_array( $fee ) && 'Pre-Order Fee' == $fee['name'] ) {
				return doubleval( $fee['line_total'] ) + doubleval( $fee['line_tax'] );
			}
		}
	}
	/**
	 * @param string $order_id
	 * @return bool
	 */
	public function order_contains_pre_order( $order_id ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			return WC_Pre_Orders_Order::order_contains_pre_order( $order_id );
		}
		return false;
	}

	/**
	 * @param string $order_id
	 *
	 * @return bool
	 */
	public function order_requires_payment_tokenization( $order_id ) {
		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			return WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id );
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public function cart_contains_pre_order_fee() {
		if ( class_exists( 'WC_Pre_Orders_Cart' ) ) {
			return WC_Pre_Orders_Cart::cart_contains_pre_order_fee();
		}
		return false;
	}
	/**
	 * Store the PayFast subscription token
	 *
	 * @param string $token
	 * @param WC_Subscription $subscription
	 */
	protected function _set_subscription_token( $token, $subscription ) {
		$subscription->update_meta_data( '_payfast_subscription_token', $token );
		$subscription->save_meta_data();
	}

	/**
	 * Retrieve the PayFast subscription token for a given order id.
	 *
	 * @param WC_Subscription $subscription
	 * @return mixed
	 */
	protected function _get_subscription_token( $subscription ) {
		return $subscription->get_meta( '_payfast_subscription_token', true );
	}

	/**
	 * Retrieve the PayFast subscription token for a given order id.
	 *
	 * @param WC_Subscription $subscription
	 * @return mixed
	 */
	protected function _delete_subscription_token( $subscription ) {
		return $subscription->delete_meta_data( '_payfast_subscription_token' ); 
	}

	/**
	 * Store the PayFast renewal flag
	 * @since 1.4.3
	 *
	 * @param string $token
	 * @param WC_Subscription $subscription
	 */
	protected function _set_renewal_flag( $subscription ) {
		$subscription->update_meta_data( '_payfast_renewal_flag', 'true' );
		$subscription->save_meta_data();
	}

	/**
	 * Retrieve the PayFast renewal flag for a given order id.
	 * @since 1.4.3
	 *
	 * @param WC_Subscription $subscription
	 * @return bool
	 */
	protected function _has_renewal_flag( $subscription ) {
		return 'true' === $subscription->get_meta( '_payfast_renewal_flag', true );
	}

	/**
	 * Retrieve the PayFast renewal flag for a given order id.
	 * @since 1.4.3
	 *
	 * @param WC_Subscription $subscription
	 * @return mixed
	 */
	protected function _delete_renewal_flag( $subscription ) {
		$subscription->delete_meta_data( '_payfast_renewal_flag' );
		$subscription->save_meta_data();
	}

	/**
	 * Store the PayFast pre_order_token token
	 *
	 * @param string   $token
	 * @param WC_Order $order
	 */
	protected function _set_pre_order_token( $token, $order ) {
		$order->update_meta_data( '_payfast_pre_order_token', $token );
		$order->save_meta_data();
	}

	/**
	 * Retrieve the PayFast pre-order token for a given order id.
	 *
	 * @param WC_Order $order
	 * @return mixed
	 */
	protected function _get_pre_order_token( $order ) {
		return $order->get_meta( '_payfast_pre_order_token', true );
	}

	/**
	 * Wrapper for WooCommerce subscription function wc_is_subscription.
	 *
	 * @param WC_Order $order The order.
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
	 * Wrapper function for wcs_order_contains_subscription
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	public function order_contains_subscription( $order ) {
		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}
		return wcs_order_contains_subscription( $order );
	}

	/**
	 * @param $amount_to_charge
	 * @param WC_Order $renewal_order
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
			$renewal_order->update_status( 'failed', sprintf( __( 'PayFast Subscription renewal transaction failed (%1$s:%2$s)', 'woocommerce-gateway-payfast' ), $response->get_error_code() ,$response->get_error_message() ) );
		}
		// Payment will be completion will be capture only when the ITN callback is sent to $this->handle_itn_request().
		$renewal_order->add_order_note( __( 'PayFast Subscription renewal transaction submitted.', 'woocommerce-gateway-payfast' ) );

	}

	/**
	 * @param WC_Subscription $subscription
	 * @param $amount_to_charge
	 * @return mixed WP_Error on failure, bool true on success
	 */
	public function submit_subscription_payment( $subscription, $amount_to_charge ) {
		$token = $this->_get_subscription_token( $subscription );
		$item_name = $this->get_subscription_name( $subscription );

		foreach ( $subscription->get_related_orders( 'all', 'renewal' ) as $order ) {
			$statuses_to_charge = array( 'on-hold', 'failed', 'pending' );
			if ( in_array( $order->get_status(), $statuses_to_charge ) ) {
				$latest_order_to_renew = $order;
				break;
			}
		}
		$item_description = json_encode( array( 'renewal_order_id' => self::get_order_prop( $latest_order_to_renew, 'id' ) ) );

		return $this->submit_ad_hoc_payment( $token, $amount_to_charge, $item_name, $item_description );
	}

	/**
	 * Get a name for the subscription item. For multiple
	 * item only Subscription $date will be returned.
	 *
	 * For subscriptions with no items Site/Blog name will be returned.
	 *
	 * @param WC_Subscription $subscription
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
	 * @param string $token
	 * @param double $amount_to_charge
	 * @param string $item_name
	 * @param string $item_description
	 *
	 * @return bool|WP_Error
	 */
	public function submit_ad_hoc_payment( $token, $amount_to_charge, $item_name, $item_description ) {
		$args = array(
			'body' => array(
				'amount'           => $amount_to_charge * 100, // convert to cents
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
	 * @param $command
	 * @param $token
	 * @param $api_args
	 * @param string $method GET | PUT | POST | DELETE.
	 *
	 * @return bool|WP_Error
	 */
	public function api_request( $command, $token, $api_args, $method = 'POST' ) {
		if ( empty( $token ) ) {
			$this->log( "Error posting API request: No token supplied", true );
			return new WP_Error( '404', __( 'Can not submit PayFast request with an empty token', 'woocommerce-gateway-payfast' ), $results );
		}

		$api_endpoint  = "https://api.payfast.co.za/subscriptions/$token/$command";
		$api_endpoint .= 'yes' === $this->get_option( 'testmode' ) ? '?testing=true' : '';

		$timestamp = current_time( rtrim( DateTime::ATOM, 'P' ) ) . '+02:00';
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

		// generate signature
		$all_api_variables                = array_merge( $api_args['headers'], (array) $api_args['body'] );
		$api_args['headers']['signature'] = md5( $this->_generate_parameter_string( $all_api_variables ) );
		$api_args['method']               = strtoupper( $method );

		$results = wp_remote_request( $api_endpoint, $api_args );

		// Check PayFast server response
		if ( 200 !== $results['response']['code'] ) {
			$this->log( "Error posting API request:\n" . print_r( $results['response'], true ) );
			return new WP_Error( $results['response']['code'], json_decode( $results['body'] )->data->response, $results );
		}

		// Check adhoc bank charge response
		$results_data = json_decode( $results['body'], true )['data'];

		// Sandbox ENV returns true(boolean) in response, while Production ENV "true"(string) in response.
		if ( $command == 'adhoc' && ! ( 'true' === $results_data['response'] || true === $results_data['response'] ) ) {
			$this->log( "Error posting API request:\n" . print_r( $results_data , true ) );

			$code         = is_array( $results_data['response'] ) ? $results_data['response']['code'] : $results_data['response'];
			$message      = is_array( $results_data['response'] ) ? $results_data['response']['reason'] : $results_data['message'];
			// Use trim here to display it properly e.g. on an order note, since PayFast can include CRLF in a message.
			return new WP_Error( $code, trim( $message ), $results );
		}

		$maybe_json = json_decode( $results['body'], true );

		if ( ! is_null( $maybe_json ) && isset( $maybe_json['status'] ) && 'failed' === $maybe_json['status'] ) {
			$this->log( "Error posting API request:\n" . print_r( $results['body'], true ) );

			// Use trim here to display it properly e.g. on an order note, since PayFast can include CRLF in a message.
			return new WP_Error( $maybe_json['code'], trim( $maybe_json['data']['message'] ), $results['body'] );
		}

		return true;
	}

	/**
	 * Responds to Subscriptions extension cancellation event.
	 *
	 * @since 1.4.0 introduced.
	 * @param WC_Subscription $subscription
	 */
	public function cancel_subscription_listener( $subscription ) {
		$token = $this->_get_subscription_token( $subscription );
		if ( empty( $token ) ) {
			return;
		}
		$this->api_request( 'cancel', $token, array(), 'PUT' );
	}

	/**
	 * @since 1.4.0
	 * @param string $token
	 *
	 * @return bool|WP_Error
	 */
	public function cancel_pre_order_subscription( $token ) {
		return $this->api_request( 'cancel', $token, array(), 'PUT' );
	}

	/**
	 * @since 1.4.0 introduced.
	 * @param      $api_data
	 * @param bool $sort_data_before_merge? default true.
	 * @param bool $skip_empty_values Should key value pairs be ignored when generating signature?  Default true.
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
				$val = urlencode( $val );
				$parameter_string .= "$key=$val&";
			}
		}
		// when not sorting passphrase should be added to the end before md5
		if ( $sort_data_before_merge ) {
			$parameter_string = rtrim( $parameter_string, '&' );
		} elseif ( ! empty( $this->pass_phrase ) ) {
			$parameter_string .= 'passphrase=' . urlencode( $this->pass_phrase );
		} else {
			$parameter_string = rtrim( $parameter_string, '&' );
		}

		return $parameter_string;
	}

	/**
	 * @since 1.4.0 introduced.
	 * @param WC_Order $order
	 */
	public function process_pre_order_payments( $order ) {

		// The total amount to charge is the the order's total.
		$total = $order->get_total() - $this->get_pre_order_fee( self::get_order_prop( $order, 'id' ) );
		$token = $this->_get_pre_order_token( $order );

		if ( ! $token ) {
			return;
		}
		// get the payment token and attempt to charge the transaction
		$item_name = 'pre-order';
		$results = $this->submit_ad_hoc_payment( $token, $total, $item_name, '' );

		if ( is_wp_error( $results ) ) {
			/* translators: 1: error code 2: error message */
			$order->update_status( 'failed', sprintf( __( 'PayFast Pre-Order payment transaction failed (%1$s:%2$s)', 'woocommerce-gateway-payfast' ), $results->get_error_code() ,$results->get_error_message() ) );
			return;
		}

		// Payment completion will be handled by ITN callback
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the PayFast gateway.
	 *
	 * @since 1.0.0
	 */
	public function setup_constants() {
		// Create user agent string.
		define( 'PF_SOFTWARE_NAME', 'WooCommerce' );
		define( 'PF_SOFTWARE_VER', WC_VERSION );
		define( 'PF_MODULE_NAME', 'WooCommerce-PayFast-Free' );
		define( 'PF_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pf_features = 'PHP ' . phpversion() . ';';

		// - cURL
		if ( in_array( 'curl', get_loaded_extensions() ) ) {
			define( 'PF_CURL', '' );
			$pf_version = curl_version();
			$pf_features .= ' curl ' . $pf_version['version'] . ';';
		} else {
			$pf_features .= ' nocurl;';
		}

		// Create user agrent
		define( 'PF_USER_AGENT', PF_SOFTWARE_NAME . '/' . PF_SOFTWARE_VER . ' (' . trim( $pf_features ) . ') ' . PF_MODULE_NAME . '/' . PF_MODULE_VER );

		// General Defines
		define( 'PF_TIMEOUT', 15 );
		define( 'PF_EPSILON', 0.01 );

		// Messages
		// Error
		define( 'PF_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_BAD_ACCESS', __( 'Bad access of page', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_CONNECT_FAILED', __( 'Failed to connect to PayFast', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_MERCHANT_ID_MISMATCH', __( 'Merchant ID mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_PDT_FAIL', __( 'PDT query failed', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woocommerce-gateway-payfast' ) );
		define( 'PF_ERR_UNKNOWN', __( 'Unkown error occurred', 'woocommerce-gateway-payfast' ) );

		// General
		define( 'PF_MSG_OK', __( 'Payment was successful', 'woocommerce-gateway-payfast' ) );
		define( 'PF_MSG_FAILED', __( 'Payment has failed', 'woocommerce-gateway-payfast' ) );
		define( 'PF_MSG_PENDING', __( 'The payment is pending. Please note, you will receive another Instant Transaction Notification when the payment status changes to "Completed", or "Failed"', 'woocommerce-gateway-payfast' ) );

		do_action( 'woocommerce_gateway_payfast_setup_constants' );
	}

	/**
	 * Log system processes.
	 * @since 1.0.0
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
	 * validate_signature()
	 *
	 * Validate the signature against the returned data.
	 *
	 * @param array $data
	 * @param string $signature
	 * @since 1.0.0
	 * @return string
	 */
	public function validate_signature( $data, $signature ) {
	    $result = $data['signature'] === $signature;
	    $this->log( 'Signature = ' . ( $result ? 'valid' : 'invalid' ) );
	    return $result;
	}

	/**
	 * Validate the IP address to make sure it's coming from PayFast.
	 *
	 * @param array $source_ip
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_valid_ip( $source_ip ) {
		// Variable initialization
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

		// Remove duplicates
		$valid_ips = array_unique( $valid_ips );

		// Adds support for X_Forwarded_For
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$source_ip = (string) rest_is_ip_address( trim( current( preg_split( '/[,:]/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) ) ) ?: $source_ip;
		}

		$this->log( "Valid IPs:\n" . print_r( $valid_ips, true ) );
		$is_valid_ip = in_array( $source_ip, $valid_ips );
		return apply_filters( 'woocommerce_gateway_payfast_is_valid_ip', $is_valid_ip, $source_ip );
	}

	/**
	 * validate_response_data()
	 *
	 * @param array $post_data
	 * @param string $proxy Address of proxy to use or NULL if no proxy.
	 * @since 1.0.0
	 * @return bool
	 */
	public function validate_response_data( $post_data, $proxy = null ) {
		$this->log( 'Host = ' . $this->validate_url );
		$this->log( 'Params = ' . print_r( $post_data, true ) );

		if ( ! is_array( $post_data ) ) {
			return false;
		}

		$response = wp_remote_post( $this->validate_url, array(
			'body'       => $post_data,
			'timeout'    => 70,
			'user-agent' => PF_USER_AGENT,
		));

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			$this->log( "Response error:\n" . print_r( $response, true ) );
			return false;
		}

		parse_str( $response['body'], $parsed_response );

		$response = $parsed_response;

		$this->log( "Response:\n" . print_r( $response, true ) );

		// Interpret Response
		if ( is_array( $response ) && in_array( 'VALID', array_keys( $response ) ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * amounts_equal()
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @author Jonathan Smit
	 * @param $amount1 Float 1st amount for comparison
	 * @param $amount2 Float 2nd amount for comparison
	 * @since 1.0.0
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
	 * @param   string  $key  The key representing an error
	 *
	 * @return  string        The user-friendly error message for display
	 */
	public function get_error_message( $key ) {
		switch ( $key ) {
			case 'wc-gateway-payfast-error-invalid-currency':
				return __( 'Your store uses a currency that PayFast doesnt support yet.', 'woocommerce-gateway-payfast' );
			case 'wc-gateway-payfast-error-missing-merchant-id':
				return __( 'You forgot to fill your merchant ID.', 'woocommerce-gateway-payfast' );
			case 'wc-gateway-payfast-error-missing-merchant-key':
				return __( 'You forgot to fill your merchant key.', 'woocommerce-gateway-payfast' );
			case 'wc-gateway-payfast-error-missing-pass-phrase':
				return __( 'PayFast requires a passphrase to work.', 'woocommerce-gateway-payfast' );
			default:
				return '';
		}
	}

	/**
	*  Show possible admin notices
	*/
	public function admin_notices() {

		// Get requirement errors.
		$errors_to_show = $this->check_requirements();

		// If everything is in place, don't display it.
		if ( ! count( $errors_to_show ) ) {
			return;
		}

		// If the gateway isn't enabled, don't show it.
		if ( "no" ===  $this->enabled ) {
			return;
		}

		// Use transients to display the admin notice once after saving values.
		if ( ! get_transient( 'wc-gateway-payfast-admin-notice-transient' ) ) {
			set_transient( 'wc-gateway-payfast-admin-notice-transient', 1, 1);

			echo '<div class="notice notice-error is-dismissible"><p>'
				. __( 'To use PayFast as a payment provider, you need to fix the problems below:', 'woocommerce-gateway-payfast' ) . '</p>'
				. '<ul style="list-style-type: disc; list-style-position: inside; padding-left: 2em;">'
				. array_reduce( $errors_to_show, function( $errors_list, $error_item ) {
					$errors_list = $errors_list . PHP_EOL . ( '<li>' . $this->get_error_message($error_item) . '</li>' );
					return $errors_list;
				}, '' )
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

		if (! $fee ) {
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
				<?php echo wc_price( $fee, array( 'decimals' => 2 ));  ?>
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

		if (! $net ) {
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
				<?php echo wc_price( $net, array( 'decimals' => 2 ) ); ?>
			</td>
		</tr>

		<?php
	}
}
