<?php

/**
 * This class is used to provide the data for the payfast webhook.
 */
class WebhookDataProvider {
	/**
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * @var WC_Gateway_PayFast
	 */
	protected $paymentGateway;

	/**
	 * BaseClass constructor.
	 */
	public function __construct( int $orderId ) {
		$this->order          = wc_get_order( $orderId );
		$this->paymentGateway = WC()->payment_gateways()->payment_gateways()['payfast'];
	}

	/**
	 * This function should return the data for the transaction webhook.
	 */
	public function getData(): array {
		ob_start();
		$this->paymentGateway->generate_payfast_form( $this->order->get_id() );
		$form = ob_get_clean();

		// Make $data_to_send property and _generate_parameter_string function accessible.
		$reflectionObject = new ReflectionObject( $this->paymentGateway );

		$dataProperty = $reflectionObject->getProperty( 'data_to_send' );
		$dataProperty->setAccessible( true );
		$dataForPayFast = $dataProperty->getValue( $this->paymentGateway );

		$result = [
			'm_payment_id'     => $dataForPayFast['m_payment_id'],
			'pf_payment_id'    => random_int( 1000000, 9999999 ),
			'payment_status'   => 'COMPLETE',
			'item_name'        => $dataForPayFast['item_name'],
			'item_description' => $dataForPayFast['item_description'],
			'custom_str1'      => $dataForPayFast['custom_str1'],
			'custom_str2'      => $dataForPayFast['custom_str2'],
			'custom_str3'      => $dataForPayFast['custom_str3'],
			'custom_str4'      => '',
			'custom_str5'      => '',
			'custom_int1'      => '',
			'custom_int2'      => '',
			'custom_int3'      => '',
			'custom_int4'      => '',
			'custom_int5'      => '',
			'name_first'       => $dataForPayFast['name_first'],
			'name_last'        => $dataForPayFast['name_last'],
			'email_address'    => $dataForPayFast['email_address'],
			'merchant_id'      => $dataForPayFast['merchant_id'],
		];

		// Add the amounts.
		$result = array_merge( $result, $this->getAmounts() );

		// Add the token and billing date for subscriptions.
		if($this->paymentGateway->is_subscription($this->order)){
			$result['token'] = sprintf(
				'%1$s-%2$s-%2$s-%2$s-%1$s',
				random_bytes(8),
				random_bytes(4)
			);
			$result['billing_date'] = date('Y-m-d', strtotime());
		}

		$method = $reflectionObject->getMethod( '_generate_parameter_string' );
		$method->setAccessible( true );
		$signature = md5($method->invoke( $this->paymentGateway, $result, false, false ));

		// Add the signature.
		$result['signature'] = $signature;

		return $result;
	}

	/**
	 * This function should return the signature for the transaction webhook.
	 */
	private function getAmounts(): array {
		$grossAmount = $this->order->get_total();
		$amountFee   = $grossAmount * 0.023; // 2.3% fee for PayFast.
		$amountNet   = $grossAmount - $amountFee;

		return [
			'amount_gross' => $grossAmount,
			'amount_fee'   => - $amountFee,
			'amount_net'   => $amountNet,
		];
	}
}
