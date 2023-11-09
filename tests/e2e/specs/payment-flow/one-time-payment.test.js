/**
 * Internal dependencies
 */
import {addProductToCart, changeCurrency, clearEmailLogs, editPayfastSetting, fillBillingDetails, goToOrderEditPage} from '../../utils';
import {customer, payfastSandboxCredentials} from "../../config";

/**
 * WordPress dependencies
 */
const {test, expect} = require( '@playwright/test' );

test.describe( 'Verify Payfast One-Time Payment Process - @foundational', async () => {
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

	test( 'Checkout Block: Verify one-time payment', async () => {
		test.slow();

		let waitForURL;

		await clearEmailLogs( {page: adminPage} );

		await addProductToCart( {page: checkoutBlock, productUrl:'/product/simple-product/'} );
		await checkoutBlock.goto('/checkout/');
		await fillBillingDetails(page, customer.billing, true);

		// Check if Payfast payment method is visible & place order
		waitForURL = checkoutBlock.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
		const payfastPaymentMethod = await checkoutBlock.locator( 'label[for="radio-control-wc-payment-method-options-payfast"]' );
		await payfastPaymentMethod.click();
		await checkoutBlock.getByRole( 'button', {name: 'Place order'} ).click();
		await waitForURL;

		// Pay on Payfast checkout page.
		waitForURL = checkoutBlock.waitForURL( /\/order-received\// );
		const payfastCompletePaymentButton = await checkoutBlock.locator( 'button#pay-with-wallet' );
		await payfastCompletePaymentButton.click();
		await waitForURL;

		// Validate order status.
		// Order should be in processing state.
		const orderId = await checkoutBlock.url().split( 'order-received/' )[1].split( '/' )[0];
		await goToOrderEditPage({page: adminPage, orderId});

		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect(await orderStatus.evaluate( el => el.value )).toBe('wc-processing');

		// Verify: email sent.
		await adminPage.goto( '/wp-admin/admin.php?page=email-log' );
		const emailLogRows = await adminPage.locator( "#the-list tr", {
			hasText: 'Your woocommerce-gateway-payfast order has been received!',
		} );
		await expect( await emailLogRows.count() ).not.toBe( 0 );
	} );

	test( 'Checkout Page: Verify one-time payment', async () => {
		test.slow();

		let waitForURL;

		await addProductToCart( {page: checkoutPage, productUrl: '/product/simple-product/'} );
		await checkoutPage.goto( '/shortcode-checkout/' );
		await fillBillingDetails(page, customer.billing);

		// Check if Payfast payment method is visible & place order
		waitForURL = checkoutPage.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
		const payfastPaymentMethod = await checkoutPage.locator( '.wc_payment_method.payment_method_payfast' );
		await payfastPaymentMethod.click();
		await checkoutPage.getByRole( 'button', {name: 'Place order'} ).click();
		await waitForURL;

		// Pay on Payfast checkout page.
		waitForURL = checkoutPage.waitForURL( /\/order-received\// );
		const payfastCompletePaymentButton = await checkoutPage.locator( 'button#pay-with-wallet' );
		await payfastCompletePaymentButton.click();
		await waitForURL;

		// Validate order status.
		// Order should be in processing state.
		const orderId = await checkoutPage.url().split( 'order-received/' )[1].split( '/' )[0];
		await goToOrderEditPage({page: adminPage, orderId});

		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect(await orderStatus.evaluate( el => el.value )).toBe('wc-processing');
	} );
} );
