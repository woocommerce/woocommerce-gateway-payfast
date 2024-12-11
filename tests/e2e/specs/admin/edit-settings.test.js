/**
 * Internal dependencies
 */
import {
	addProductToCart,
	changeCurrency,
	clearEmailLogs,
	clearWooCommerceLogs,
	editPayfastSetting,
	gotoPayfastSettingPage,
	processOneTimeOrderWithBlockCheckout,
	verifyOrderStatusIsProcessing
} from '../../utils';
import {payfastSandboxCredentials} from "../../config";

/**
 * WordPress dependencies
 */
const {test, expect} = require( '@playwright/test' );

test.describe( 'Verify payfast setting - @foundational', async () => {
	let adminPage, checkoutPage, checkoutBlockPage;

	test.beforeAll( async ( {browser} ) => {
		const adminContext = await browser
			.newContext( {storageState: process.env.ADMINSTATE} );
		const customerContext = await browser
			.newContext( {storageState: process.env.CUSTOMERSTATE} );
		adminPage = await adminContext.newPage();
		checkoutPage = await customerContext.newPage();
		checkoutBlockPage = await customerContext.newPage();
	} );

	test( 'Setup - Edit setting: Disable payfast', async () => {
		await changeCurrency( {page: adminPage, currency: 'ZAR'} );

		await editPayfastSetting( {
			page: adminPage,
			settings: {
				toggle_payment_gateway: false,
			}
		} );
	} );

	test( 'Checkout Block: Payment method should not available when disabled', async () => {
		await addProductToCart( {page: checkoutBlockPage, productUrl: '/product/simple-product/'} );
		await checkoutBlockPage.goto( '/checkout/' , { waitUntil: 'networkidle' });

		const paymentMethodLocator = await checkoutBlockPage.locator( 'label.wc-block-components-radio-control__option', {
			has: checkoutBlockPage.locator( 'input[value="payfast"]' )
		} );

		await expect( await paymentMethodLocator.count() ).toBe( 0 );
	} );

	test( 'Checkout Page: Payment method should not available when disabled', async () => {
		await addProductToCart( {page: checkoutPage, productUrl: '/product/simple-product/'} );
		await checkoutPage.goto( '/shortcode-checkout/' );

		const paymentMethodLocator = await checkoutPage.locator( '.wc_payment_method', {
			has: checkoutPage.locator( 'input[value="payfast"]' )
		} );

		await expect( await paymentMethodLocator.count() ).toBe( 0 );
	} );


	test( 'Setup: Edit setting', async () => {
		await editPayfastSetting( {
			page: adminPage,
			settings: {
				toggle_payment_gateway: true,
				title: 'Payfast',
				description: 'Pay with payfast',
			}
		} );
	} );

	test( 'Edit Setting: Verify required notice for the credentials', async () => {
		await gotoPayfastSettingPage( {page: adminPage} );
		await editPayfastSetting( {
			page: adminPage,
			settings: {
				merchant_id: '',
				merchant_key: '',
				passphrase: '',
			}
		} );

		await gotoPayfastSettingPage( {page: adminPage} );
		await expect( await adminPage.locator( '.notice.notice-error' ).last() ).toHaveText( /You forgot to fill your merchant ID/ );
		await expect( await adminPage.locator( '.notice.notice-error' ).last() ).toHaveText( /You forgot to fill your merchant key/ );
		await expect( await adminPage.locator( '.notice.notice-error' ).last() ).toHaveText( /Payfast requires a passphrase to work/ );
	} );


	test( 'Edit Setting: Verify credentials and show notice for invalid credentials', async () => {
		await gotoPayfastSettingPage( {page: adminPage} );
		await editPayfastSetting( {
			page: adminPage,
			settings: {
				merchant_id: '1',
				merchant_key: '1',
				passphrase: '1',
			}
		} );

		await expect( await adminPage.locator( '.notice.notice-error' ).last() ).toHaveText( /Invalid Payfast credentials/ );
	} );

	test( 'Edit Setting: Verify Merchant ID, Merchant Key, and Passphrase', async () => {
		await gotoPayfastSettingPage( {page: adminPage} );
		await editPayfastSetting( {
			page: adminPage,
			settings: {
				merchant_id: payfastSandboxCredentials.merchantId,
				merchant_key: payfastSandboxCredentials.merchantKey,
				passphrase: payfastSandboxCredentials.passPharse,
			}
		} );

		const merchantIdSettingLocator = await adminPage.getByLabel( 'Merchant ID', {exact: true} );
		await expect( await merchantIdSettingLocator.inputValue() ).toEqual( payfastSandboxCredentials.merchantId );

		const merchantKeySettingLocator = await adminPage.getByLabel( 'Merchant Key', {exact: true} );
		await expect( await merchantKeySettingLocator.inputValue() ).toEqual( payfastSandboxCredentials.merchantKey );

		const passphraseSettingLocator = await adminPage.getByLabel( 'Passphrase', {exact: true} );
		await expect( await passphraseSettingLocator.inputValue() ).toEqual( payfastSandboxCredentials.passPharse );
	} );

	test( 'Checkout Block: Verify method title & description', async () => {
		await addProductToCart( {page: checkoutBlockPage, productUrl: '/product/simple-product/'} );
		await checkoutBlockPage.goto( '/checkout/' , { waitUntil: 'networkidle' });

		const paymentMethodLocator = await checkoutBlockPage.locator(
			'label[for="radio-control-wc-payment-method-options-payfast"]' );
		await expect( await checkoutBlockPage.locator(
			'label[for="radio-control-wc-payment-method-options-payfast"] img' ).getAttribute( 'alt' ) )
			.toEqual( 'Payfast' );
		await paymentMethodLocator.click();
		await expect( await checkoutBlockPage.locator( '.wc-block-components-radio-control-accordion-content' ) )
			.toHaveText( /Pay with payfast/ );
	} );

	test( 'Checkout Page: Verify method title & description', async () => {
		await addProductToCart( {page: checkoutPage, productUrl: '/product/simple-product/'} );
		await checkoutPage.goto( '/shortcode-checkout/' );

		const paymentMethodLocator = await checkoutPage.locator( '.wc_payment_method.payment_method_payfast' );
		await expect( paymentMethodLocator ).toHaveText( /Payfast/ );
		await paymentMethodLocator.click();
		await expect( await checkoutPage.locator( '.payment_box.payment_method_payfast' ) )
			.toHaveText( /Pay with payfast/ );
	} );

	test( 'Edit Setting - Send Debug Emails - Verify when setting disabled', async () => {
		test.slow();

		let orderID, emailLogRows, logPageNotices;

		await clearEmailLogs( {page: adminPage} );
		await clearWooCommerceLogs( {page: adminPage} );

		// Disable "Send Debug Emails" & "Enable Logging".
		await editPayfastSetting( {
			page: adminPage,
			settings: {
				send_debug_emails: false,
				enable_logging: false,
			}
		} );

		// Process one time order.
		orderID = await processOneTimeOrderWithBlockCheckout(
			{
				page: checkoutBlockPage,
				productUrl: '/product/simple-product/'
			}
		);
		await verifyOrderStatusIsProcessing( {page: adminPage, orderId: orderID} );

		// Verify: zero email sent.
		await adminPage.goto( '/wp-admin/admin.php?page=email-log' );
		emailLogRows = await adminPage.locator( "#the-list tr", {
			hasText: 'Payfast ITN on your site',
		} );
		await expect( await emailLogRows.count() ).toBe( 0 );
	} );

	test( 'Edit Setting - Send Debug Emails - Verify when setting enabled', async () => {
		test.slow();

		let orderID, emailLogRows, logPageNotices;

		await clearEmailLogs( {page: adminPage} );
		await clearWooCommerceLogs( {page: adminPage} );

		// Disable "Send Debug Emails" & "Enable Logging".
		await editPayfastSetting( {
			page: adminPage,
			settings: {
				send_debug_emails: true,
				enable_logging: true,
			}
		} );

		// Process one time order.
		orderID = await processOneTimeOrderWithBlockCheckout(
			{
				page: checkoutBlockPage,
				productUrl: '/product/simple-product/'
			}
		);
		await verifyOrderStatusIsProcessing( {page: adminPage, orderId: orderID} );

		// Verify: email sent.
		await adminPage.goto( '/wp-admin/admin.php?page=email-log' );
		emailLogRows = await adminPage.locator( "#the-list tr", {
			hasText: 'Payfast ITN on your site',
		} );
		await expect( await emailLogRows.count() ).not.toBe( 0 );

		// Verify: log create.
		await adminPage.goto( '/wp-admin/admin.php?page=wc-status&tab=logs' );
		const woocommerceLogFiles = await adminPage.locator( 'table.log-files a.row-title', {
			hasText: 'payfast',
		} );
		await expect( await woocommerceLogFiles.count() ).not.toBe( 0 );
	} );
} );
