import {customer} from "../config";
import {expect} from "@playwright/test";

/**
 * Change the currency
 *
 * @param {Page} page
 * @param {string} currency
 * @return {Promise<void>}
 */
export async function changeCurrency( {page, currency} ) {
	const waitForURLPromise = page.waitForURL( '**/wp-admin/admin.php?page=wc-settings&tab=general' );
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=general' );
	const currencySelectorLocator = await page.getByLabel( 'Currency ', {exact: true} );
	const selectedCurrency = await currencySelectorLocator.getAttribute( 'value' );

	// Change currency if not already set.
	if ( selectedCurrency !== currency ) {
		await currencySelectorLocator.selectOption( currency );
		const submitButtonLocator = await page.locator( 'text=Save changes' );
		await submitButtonLocator.click();
		await waitForURLPromise;
	}
}

/**
 * Go to the payfast settings page
 * @param {Page} page
 * @return {Promise<void>}
 */
export async function gotoPayfastSettingPage( {page} ) {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payfast' );
}

/**
 * Edit the payfast setting
 * @param {Page} page
 * @param {Object} setting
 * @return {Promise<void>}
 *
 * TODO: Save setting only if value is different from the current value
 */
export async function editPayfastSetting( {page, settings} ) {
	await gotoPayfastSettingPage( {page} );

	for ( const settingId in settings ) {
		switch ( settingId ) {
			case 'toggle_payment_gateway':
				const togglePaymentGatewaySettingLocator = await page.getByLabel( 'Enable/Disable' );

				if ( settings.toggle_payment_gateway ) {
					await togglePaymentGatewaySettingLocator.check();
				} else {
					await togglePaymentGatewaySettingLocator.uncheck();
				}
				break;

			case 'title':
				const titleSettingLocator = await page.getByLabel( 'Title', {exact: true} );
				await titleSettingLocator.fill( settings.title );
				break;

			case 'description':
				const descriptionSettingLocator = await page.getByLabel( 'Description', {exact: true} );
				await descriptionSettingLocator.fill( settings.description );
				break;

			case 'merchant_id':
				const merchantIdSettingLocator = await page.getByLabel( 'Merchant ID', {exact: true} );
				await merchantIdSettingLocator.fill( settings.merchant_id );
				break;

			case 'merchant_key':
				const merchantKeySettingLocator = await page.getByLabel( 'Merchant Key', {exact: true} );
				await merchantKeySettingLocator.fill( settings.merchant_key );
				break;

			case 'passphrase':
				const passphraseSettingLocator = await page.getByLabel( 'Passphrase', {exact: true} );
				await passphraseSettingLocator.fill( settings.passphrase );
				break;

			case 'send_debug_emails':
				const sendDebugEmailsSettingLocator = await page.getByLabel( 'Send Debug Emails' );
				if ( settings.send_debug_emails ) {
					await sendDebugEmailsSettingLocator.check();
				} else {
					await sendDebugEmailsSettingLocator.uncheck();
				}
				break;

			case 'enable_logging':
				const enableLoggingSettingLocator = await page.getByLabel( 'Enable Logging' );
				if ( settings.enable_logging ) {
					await enableLoggingSettingLocator.check();
				} else {
					await enableLoggingSettingLocator.uncheck();
				}
				break;
		}
	}

	const waitForURLPromise = page.waitForURL(
		'**/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payfast' );
	const submitButtonLocator = await page.locator( 'text=Save changes' );
	await submitButtonLocator.click();
	await waitForURLPromise;
}

/**
 * Add a product to cart
 *
 * @param {Page} page
 * @param {string} productUrl
 * @return {Promise<void>}
 */
export async function addProductToCart( {page, productUrl} ) {
	await page.goto( productUrl );
	await page.click( 'text=Add to cart' );
}

/**
 * Clear email Logs
 *
 * @param {Page} page
 */
export async function clearEmailLogs( {page} ) {
	const result = await page.evaluate( async() => {
		const response = await fetch( `${window.location.origin}/wp-json/e2e-wc/v1/flush-all-emails`, {method: 'DELETE'} );
		return await response.json();
	});

	await expect( result ).toBeTruthy();
}

/**
 * Clear WooCommerce Logs
 *
 * @param {Page} page
 */
export async function clearWooCommerceLogs( {page} ) {
	const result = await page.evaluate( async() => {
		const response = await fetch( `${window.location.origin}/wp-json/e2e-wc/v1/flush-all-logs`, {method: 'DELETE'} );
		return await response.json();
	});

	await expect( result ).toBeTruthy();
}

/**
 * Process one-time order with block checkout page.
 *
 * @param {Page} page
 * @param {string} productUrl
 *
 * @return {Promise<string>} Order ID
 */
export async function processOneTimeOrderWithBlockCheckout( {page, productUrl} ) {
	let waitForURL;

	await addProductToCart( {page, productUrl} );
	await page.goto( '/checkout/' );
	await fillBillingDetails(page, customer.billing, true);

	// Check if Payfast payment method is visible & place order
	waitForURL = page.waitForURL( /\/sandbox.payfast.co.za\/eng\/process\/payment/ );
	const payfastPaymentMethod = await page.locator( 'label[for="radio-control-wc-payment-method-options-payfast"]' );
	await payfastPaymentMethod.click();
	await page.getByRole( 'button', {name: 'Place order'} ).click();
	await waitForURL;

	// Pay on Payfast checkout page.
	waitForURL = page.waitForURL( /\/order-received\// );
	const payfastCompletePaymentButton = await page.locator( 'button#pay-with-wallet' );
	await payfastCompletePaymentButton.click();
	await waitForURL;

	return page.url().split( 'order-received/' )[1].split( '/' )[0];
}

/**
 * Verify order status is processing.
 *
 * @param {Page} page
 * @param {string} orderId
 * @return {Promise<void>}
 */
export async function verifyOrderStatusIsProcessing( {page, orderId} ) {
	let waitForURL;

	// Validate order status.
	// Order should be in processing state.
	await goToOrderEditPage( {page, orderId} );
	const orderStatus = await page.locator( 'select[name="order_status"]' );
	await expect( await orderStatus.evaluate( el => el.value ) ).toBe( 'wc-processing' );
}

/**
 * Goto order edit page.
 *
 * @param {Page} page
 * @param {string} orderId
 * @return {Promise<void>}
 */
export async function goToOrderEditPage( {page, orderId} ){
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}` );
}


/**
 * Fill Billing details on block checkout page
 *
 * @param {Page}   page            Playwright page object
 * @param {Object} customerDetails Customer billing details
 */
export async function blockFillBillingDetails(page, customerDetails) {
	const card = await page.locator('.wc-block-components-address-card');
	if (await card.isVisible()) {
		await card.locator('a.wc-block-components-address-card__edit').click();
	}

	await page.getByLabel( 'First name' ).fill( customerDetails.firstname );
	await page.getByLabel( 'Last name' ).fill( customerDetails.lastname );
	await page.getByLabel( 'Address', {exact: true} ).fill( customerDetails.addressfirstline );
	await page.getByLabel( 'City' ).fill( customerDetails.city );
	await page.getByLabel( 'Zip Code' ).fill( customerDetails.postcode );
	await page.getByLabel( 'Phone (optional)' ).fill( customerDetails.phone );
}

/**
 * Fill billing details on checkout page
 *
 * @param {Page}    page                   Playwright page object
 * @param {Object}  customerBillingDetails Customer billing details
 * @param {boolean} isBlock                Is block checkout
 */
export async function fillBillingDetails(
	page,
	customerBillingDetails,
	isBlock = false
) {
	if (isBlock) {
		return blockFillBillingDetails(page, customerBillingDetails);
	}
	await page.getByLabel( 'First name' ).fill( customerDetails.firstname );
	await page.getByLabel( 'Last name' ).fill( customerDetails.lastname );
	await page.getByLabel( 'Street address' ).fill( customerDetails.addressfirstline );
	await page.getByLabel( 'Town / City' ).fill( customerDetails.city );
	await page.getByLabel( 'Zip Code' ).fill( customerDetails.postcode );
	await page.getByLabel( 'Phone' ).fill( customerDetails.phone );
}
