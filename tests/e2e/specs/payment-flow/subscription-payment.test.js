/**
 * Internal dependencies
 */
import {changeCurrency, editPayfastSetting, fillBillingDetails, goToOrderEditPage} from '../../utils';
import {customer, payfastSandboxCredentials} from "../../config";

/**
 * WordPress dependencies
 */
const {test, expect} = require( '@playwright/test' );

test.describe( 'Verify Payfast Subscription Payment Process - @foundational', async () => {
	let adminPage, checkoutPage, checkoutBlock;

	test.beforeAll( async ( {browser} ) => {
		const adminContext = await browser
			.newContext( {storageState: process.env.ADMINSTATE} );
		const customerContext = await browser
			.newContext( {storageState: process.env.CUSTOMERSTATE} );
		adminPage = await adminContext.newPage();
		checkoutPage = await customerContext.newPage();
		checkoutBlock = await customerContext.newPage();
	} );

	test( 'Setup: Edit setting', async () => {
		await changeCurrency( {page: adminPage, currency: 'ZAR'} );

		await editPayfastSetting( {
			page: adminPage,
			settings: {
				title: 'Payfast',
				toggle_payment_gateway: true,
				merchant_id: payfastSandboxCredentials.merchantId,
				merchant_key: payfastSandboxCredentials.merchantKey,
				passphrase: payfastSandboxCredentials.passPharse,
			}
		} );
	} );

	test( 'Checkout Block: Verify subscription payment', async () => {
		test.slow();

		let waitForURL;

		await checkoutBlock.goto( '/product/simple-subscription-product/' );
		await checkoutBlock.click( 'text=Sign up now' );
		await checkoutBlock.goto( '/checkout/' );
		await fillBillingDetails(checkoutBlock, customer.billing, true);

		// Check if Payfast payment method is visible & place order
		waitForURL = checkoutBlock.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
		const payfastPaymentMethod = await checkoutBlock.locator(
			'label[for="radio-control-wc-payment-method-options-payfast"]' );
		await payfastPaymentMethod.click();
		await checkoutBlock.getByRole( 'button', {name: 'Place Order'} ).click();
		await waitForURL;

		// Pay on Payfast checkout page.
		// Confirm on payfast checkout page whether current transaction is for subscription.
		waitForURL = checkoutBlock.waitForURL( /\/order-received\// );
		const payfastCompletePaymentButton = await checkoutBlock.locator( 'button#pay-with-wallet' );
		const recurringPaymentText = await checkoutBlock.locator( '.tablewrapper-body__completing_process_text' );
		await expect( recurringPaymentText )
			.toHaveText( 'Completing this process will allow to automatically process your future payments.' );
		await payfastCompletePaymentButton.click();
		await waitForURL;

		// Validate order status.
		// Order should be in processing state.
		const orderId = await checkoutBlock.url().split( 'order-received/' )[1].split( '/' )[0];
		await goToOrderEditPage({page: adminPage, orderId})

		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect( await orderStatus.evaluate( el => el.value ) ).toBe( 'wc-processing' );

		const relatedOrders = await adminPage.locator( '.woocommerce_subscriptions_related_orders' );
		await expect( relatedOrders ).toContainText( 'Subscription' );
		await expect( relatedOrders ).toContainText( 'Active' );
	} );

	test( 'Checkout Page: Verify subscription payment', async () => {
		test.slow();

		let waitForURL;

		await checkoutPage.goto( '/product/simple-subscription-product/' );
		await checkoutPage.click( 'text=Sign up now' );
		await checkoutPage.goto( '/shortcode-checkout/' );
		await fillBillingDetails(checkoutPage, customer.billing);

		// Check if Payfast payment method is visible & place order
		waitForURL = checkoutPage.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
		const payfastPaymentMethod = await checkoutPage.locator( '.wc_payment_method.payment_method_payfast' );
		await payfastPaymentMethod.click();
		await checkoutPage.getByRole( 'button', {name: 'Sign up now'} ).click();
		await waitForURL;

		// Pay on Payfast checkout page.
		// Confirm on payfast checkout page whether current transaction is for subscription.
		waitForURL = checkoutPage.waitForURL( /\/order-received\// );
		const payfastCompletePaymentButton = await checkoutPage.locator( 'button#pay-with-wallet' );
		const recurringPaymentText = await checkoutPage.locator( '.tablewrapper-body__completing_process_text' );
		await expect( recurringPaymentText )
			.toHaveText( 'Completing this process will allow to automatically process your future payments.' );
		await payfastCompletePaymentButton.click();
		await waitForURL;

		// Validate order status.
		// Order should be in processing state.
		// Subscription should be active
		// Receipt page should have informaiton about subscription.
		const relatedSubscriotionOnReceiptPage = await checkoutPage.getByRole( 'heading',
			{name: 'Related subscriptions', exact: true} );
		await expect( relatedSubscriotionOnReceiptPage ).toBeVisible();

		// Open order page
		const orderId = await checkoutPage.url().split( 'order-received/' )[1].split( '/' )[0];
		await goToOrderEditPage({page: adminPage, orderId});

		// Verify details on order page.
		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect( await orderStatus.evaluate( el => el.value ) ).toBe( 'wc-processing' );
		const relatedOrders = await adminPage.locator( '.woocommerce_subscriptions_related_orders' );
		await expect( relatedOrders ).toContainText( 'Subscription' );
		await expect( relatedOrders ).toContainText( 'Active' );
	} );

	test( 'Verify renew subscription payment by customer', async () => {
		test.slow();

		let waitForURL;

		await checkoutPage.goto( '/my-account/subscriptions/' );

		// Edit first active subscription.
		const editSubscription = await checkoutPage
			.locator( '.order.woocommerce-orders-table__row--status-active .subscription-id a' )
			.first();
		await editSubscription.click();

		await checkoutPage.getByRole( 'link', {name: 'Renew now'} ).click();

		// Check if Payfast payment method is visible & place order
		await checkoutPage.goto( '/shortcode-checkout/' );
		waitForURL = checkoutPage.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
		const payfastPaymentMethod = await checkoutPage.locator( '.wc_payment_method.payment_method_payfast' );
		await payfastPaymentMethod.click();
		await checkoutPage.getByRole( 'button', {name: 'Renew Subscription'} ).click();
		await waitForURL;

		// Pay on Payfast checkout page.
		// Confirm on payfast checkout page whether current transaction is for subscription.
		waitForURL = checkoutPage.waitForURL( /\/order-received\// );
		const payfastCompletePaymentButton = await checkoutPage.locator( 'button#pay-with-wallet' );
		await payfastCompletePaymentButton.click();
		await waitForURL;

		// Validate order status.
		// Order should be in processing state.
		// Subscription should be active
		// Receipt page should have informaiton about subscription.
		const relatedSubscriotionOnReceiptPage = await checkoutPage.getByRole( 'heading',
			{name: 'Related subscriptions', exact: true} );
		await expect( relatedSubscriotionOnReceiptPage ).toBeVisible();

		// Open order page
		const orderId = await checkoutPage.url().split( 'order-received/' )[1].split( '/' )[0];
		await goToOrderEditPage({page: adminPage, orderId});

		// Verify details on order page.
		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect( await orderStatus.evaluate( el => el.value ) ).toBe( 'wc-processing' );
		const relatedOrders = await adminPage.locator( '.woocommerce_subscriptions_related_orders' );
		await expect( relatedOrders ).toContainText( 'Subscription' );
		await expect( relatedOrders ).toContainText( 'Active' );
	} );
} );
