/**
 * Internal dependencies
 */
import {addProductToCart, changeCurrency, editPayfastSetting} from '../../utils';
import {customer, payfastSandboxCredentials} from "../../config";

/**
 * WordPress dependencies
 */
const {test, expect} = require( '@playwright/test' );

test.describe.configure( {mode: 'serial'} );

test.describe( 'Verify Payfast Cancelled One-Time Payment Process - @foundational', async () => {
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

	test( 'Setup: Edit setting & Add product', async () => {
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

	test( 'Checkout Block: Verify cancelled one-time payment', async () => {
		const page = checkoutBlock;
		await addProductToCart( {page, productUrl:'/product/simple-product/'} );
		await page.goto('/checkout-block/');

		await page.getByLabel('First name').fill( customer.billing.firstname );
		await page.getByLabel('Last name').fill( customer.billing.lastname );
		await page.getByLabel('Address', {exact: true}).fill( customer.billing.addressfirstline );
		await page.getByLabel('City').fill( customer.billing.city );
		await page.getByLabel('Zip Code').fill( customer.billing.postcode );
		await page.getByLabel('Phone (optional)').fill( customer.billing.phone );

		// Check if Payfast payment method is visible & place order
		const payfastCheckoutPage = page.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
		const payfastPaymentMethod = await page.locator( 'label[for="radio-control-wc-payment-method-options-payfast"]' );
		await payfastPaymentMethod.click();
		await page.getByRole( 'button', {name: 'Place order'} ).click();
		await payfastCheckoutPage;

		// Pay on Payfast checkout page.
		const cartPage = page.waitForURL( /\/cart\// );
		const payfastCancelledTransactionButton = await page.getByRole( 'button', {name: 'Cancel transaction', exact: true} );
		await payfastCancelledTransactionButton.click();
		const payfastCancelledPaymentButton = await page.locator( 'button#cancel-payment' );
		await payfastCancelledPaymentButton.click();
		await cartPage;

		// Validate order status.
		// Order should be in cancelled state.
		const orderEditPage = adminPage.waitForURL( /\/wp-admin\/post.php\?post/ );
		const orderId = (new URLSearchParams(page.url())).get('order_id');
		await adminPage.goto( `/wp-admin/post.php?post=${orderId}&action=edit` );
		await orderEditPage;

		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect(await orderStatus.evaluate( el => el.value )).toBe('wc-cancelled');
	} );

	test( 'Checkout Page: Verify cancelled one-time payment', async () => {
		const page = checkoutPage;

		await addProductToCart( {page, productUrl: '/product/simple-product/'} );
		await page.goto( '/checkout/' );

		await page.getByLabel( 'First name' ).fill( customer.billing.firstname );
		await page.getByLabel( 'Last name' ).fill( customer.billing.lastname );
		await page.getByLabel( 'Street address' ).fill( customer.billing.addressfirstline );
		await page.getByLabel( 'Town / City' ).fill( customer.billing.city );
		await page.getByLabel( 'Zip Code' ).fill( customer.billing.postcode );
		await page.getByLabel( 'Phone' ).fill( customer.billing.phone );

		// Check if Payfast payment method is visible & place order
		const payfastCheckoutPage = page.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
		const payfastPaymentMethod = await page.locator( '.wc_payment_method.payment_method_payfast' );
		await payfastPaymentMethod.click();
		await page.getByRole( 'button', {name: 'Place order'} ).click();
		await payfastCheckoutPage;

		// Pay on Payfast checkout page.
		const cartPage = page.waitForURL( /\/cart\// );
		const payfastCancelledTransactionButton = await page.getByRole( 'button', {name: 'Cancel transaction', exact: true} );
		await payfastCancelledTransactionButton.click();
		const payfastCancelledPaymentButton = await page.getByRole( 'button', {name: 'Cancel payment', exact: true} );
		await payfastCancelledPaymentButton.click();
		await cartPage;

		// Validate order status.
		// Order should be in processing state.
		const orderEditPage = adminPage.waitForURL( /\/wp-admin\/post.php\?post/ );
		const orderId = (new URLSearchParams(page.url())).get('order_id');
		await adminPage.goto( `/wp-admin/post.php?post=${orderId}&action=edit` );
		await orderEditPage;

		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect(await orderStatus.evaluate( el => el.value )).toBe('wc-cancelled');
	} );
} );
