/**
 * Internal dependencies
 */
import {
	changeCurrency,
	editPayfastSetting,
	fillBillingDetails,
	getOrderSubscriptions,
	goToOrderEditPage,
	renewSubscriptionByAdmin,
} from '../../utils';
import {customer, payfastSandboxCredentials} from "../../config";

/**
 * WordPress dependencies
 */
const {test, expect} = require( '@playwright/test' );

test.describe( 'Verify Payfast Multiple Subscriptions Payment Process - @foundational', async () => {
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
				passphrase: payfastSandboxCredentials.passPhrase,
			}
		} );
	} );

	test( 'Checkout Block: Verify multiple subscriptions payment', async () => {
		test.slow();

		let waitForURL;

		// Add two subscription products to cart.
		await checkoutBlock.goto( '/product/simple-subscription-product/' );
		await checkoutBlock.locator( '.single_add_to_cart_button' ).click();
		await checkoutBlock.goto( '/product/second-subscription-product/' );
		await checkoutBlock.locator( '.single_add_to_cart_button' ).click();
		await checkoutBlock.goto( '/checkout/', { waitUntil: 'networkidle' } );
		await fillBillingDetails( checkoutBlock, customer.billing, true );

		// Check if Payfast payment method is visible & place order.
		waitForURL = checkoutBlock.waitForURL( /\/sandbox.payfast.co.za\/eng/ );
		const payfastPaymentMethod = await checkoutBlock.locator(
			'label[for="radio-control-wc-payment-method-options-payfast"]' );
		await payfastPaymentMethod.click();
		await checkoutBlock.locator( 'button.wc-block-components-checkout-place-order-button' ).click();
		await waitForURL;

		// Pay on Payfast checkout page.
		// Confirm on Payfast checkout page whether current transaction is for subscriptions.
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
		await goToOrderEditPage( {page: adminPage, orderId} );

		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect( await orderStatus.evaluate( el => el.value ) ).toBe( 'wc-processing' );

		await assertOrderHasActivePayfastSubscriptions( {
			page: adminPage,
			orderId,
			expectedSubscriptionCount: 1,
			expectedProductCount: 2,
		} );
	} );

	test( 'Checkout Page: Verify multiple subscription records payment', async () => {
		test.slow();

		let waitForURL;

		// Add subscription products with different schedules so WooCommerce creates separate subscriptions.
		await checkoutPage.goto( '/product/simple-subscription-product/' );
		await checkoutPage.locator( '.single_add_to_cart_button' ).click();
		await checkoutPage.goto( '/product/yearly-subscription-product/' );
		await checkoutPage.locator( '.single_add_to_cart_button' ).click();
		await checkoutPage.goto( '/shortcode-checkout/' );
		await fillBillingDetails( checkoutPage, customer.billing );

		// Check if Payfast payment method is visible & place order.
		waitForURL = checkoutPage.waitForURL( /\/sandbox.payfast.co.za\/eng/ );
		const payfastPaymentMethod = await checkoutPage.locator( '.wc_payment_method.payment_method_payfast' );
		await payfastPaymentMethod.click();
		await checkoutPage.locator( '#place_order' ).click();
		await waitForURL;

		// Pay on Payfast checkout page.
		// Confirm on Payfast checkout page whether current transaction is for subscriptions.
		waitForURL = checkoutPage.waitForURL( /\/order-received\// );
		const payfastCompletePaymentButton = await checkoutPage.locator( 'button#pay-with-wallet' );
		const recurringPaymentText = await checkoutPage.locator( '.tablewrapper-body__completing_process_text' );
		await expect( recurringPaymentText )
			.toHaveText( 'Completing this process will allow to automatically process your future payments.' );
		await payfastCompletePaymentButton.click();
		await waitForURL;

		// Validate order status.
		// Order should be in processing state.
		// The split subscriptions should be active.
		// Receipt page should have information about the subscription.
		const relatedSubscriptionsOnReceiptPage = await checkoutPage.getByRole( 'heading',
			{name: 'Related subscriptions', exact: true} );
		await expect( relatedSubscriptionsOnReceiptPage ).toBeVisible();

		// Open order page.
		const orderId = await checkoutPage.url().split( 'order-received/' )[1].split( '/' )[0];
		await goToOrderEditPage( {page: adminPage, orderId} );

		// Verify details on order page.
		const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
		await expect( await orderStatus.evaluate( el => el.value ) ).toBe( 'wc-processing' );

		await assertOrderHasActivePayfastSubscriptions( {
			page: adminPage,
			orderId,
			expectedSubscriptionCount: 2,
			expectedProductCount: 1,
			expectedSharedToken: true,
		} );
	} );

	test( 'Checkout Page: Verify multiple subscription records renewal payment', async () => {
		test.slow();

		const subscriptionIdsForRenewal = await getActiveSubscriptionIdsForRenewal( {page: checkoutPage, count: 2} );
		expect( subscriptionIdsForRenewal ).toHaveLength( 2 );

		for ( const subscriptionId of subscriptionIdsForRenewal ) {
			const renewal = await renewSubscriptionByAdmin( {page: adminPage, subscriptionId} );
			const orderId = renewal.renewalOrderId;

			expect( renewal.subscriptionId ).toBe( subscriptionId );
			expect( renewal.subscriptionStatus ).toBe( 'active' );
			expect( renewal.renewalOrderStatus ).toBe( 'processing' );
			expect( renewal.tokenBefore ).toBeTruthy();
			expect( renewal.tokenAfter ).toBe( renewal.tokenBefore );
			expect( renewal.apiCommand ).toBe( 'adhoc' );
			expect( renewal.apiToken ).toBe( renewal.tokenBefore );
			expect( JSON.parse( renewal.apiBody.item_description ).renewal_order_id ).toBe( renewal.renewalOrderId );

			await goToOrderEditPage( {page: adminPage, orderId} );

			const orderStatus = await adminPage.locator( 'select[name="order_status"]' );
			await expect( await orderStatus.evaluate( el => el.value ) ).toBe( 'wc-processing' );

			const subscriptions = await getOrderSubscriptions( {page: adminPage, orderId} );
			expect( subscriptions ).toHaveLength( 1 );
			expect( subscriptions[0].id ).toBe( subscriptionId );
			expect( subscriptions[0].status ).toBe( 'active' );
			expect( subscriptions[0].token ).toBeTruthy();
			expect( subscriptions[0].items ).toHaveLength( 1 );
		}
	} );
} );

async function assertOrderHasActivePayfastSubscriptions( {
	page,
	orderId,
	expectedSubscriptionCount,
	expectedProductCount,
	expectedSharedToken = false,
} ) {
	const relatedOrders = await page.locator( '.woocommerce_subscriptions_related_orders' );
	await expect( relatedOrders ).toContainText( 'Subscription' );
	const activeSubscriptions = await relatedOrders.getByText( 'Active' );
	await expect( activeSubscriptions ).toHaveCount( expectedSubscriptionCount );

	const subscriptions = await getOrderSubscriptions( {page, orderId} );
	expect( subscriptions ).toHaveLength( expectedSubscriptionCount );

	for ( const subscription of subscriptions ) {
		expect( subscription.status ).toBe( 'active' );
		expect( subscription.token ).toBeTruthy();
		expect( subscription.items ).toHaveLength( expectedProductCount );
	}

	if ( expectedSharedToken ) {
		expect( new Set( subscriptions.map( subscription => subscription.token ) ).size ).toBe( 1 );
	}

	return subscriptions;
}

async function getActiveSubscriptionIdsForRenewal( {page, count} ) {
	await page.goto( '/my-account/subscriptions/' );

	const activeSubscriptions = await page
		.locator( '.order.woocommerce-orders-table__row--status-active .subscription-id a' );
	expect( await activeSubscriptions.count() ).toBeGreaterThanOrEqual( count );

	const subscriptionIds = [];

	for ( let index = 0; index < count; index++ ) {
		const subscriptionUrl = await activeSubscriptions.nth( index ).getAttribute( 'href' );
		const subscriptionIdMatch = subscriptionUrl.match( /\/view-subscription\/(\d+)/ );

		expect( subscriptionIdMatch ).not.toBeNull();
		subscriptionIds.push( Number( subscriptionIdMatch[1] ) );
	}

	return subscriptionIds;
}
