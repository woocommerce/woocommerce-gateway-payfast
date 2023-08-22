/**
 * Change the currency
 *
 * @param {string} currency
 * @return {Promise<void>}
 */
export async function changeCurreny( {page, currency} ) {
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
 * Go to the snapscan settings page
 * @param {Page} page
 * @return {Promise<void>}
 */
export async function gotoSnapscanSettingPage( {page} ) {
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=snapscan' );
}

/**
 * Edit the snapscan setting
 * @param {Page} page
 * @param {Object} setting
 * @return {Promise<void>}
 *
 * TODO: Save setting only if value is different from the current value
 */
export async function editSnapscanSetting( {page, settings} ) {
	await gotoSnapscanSettingPage( {page} );

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
				const titleSettingLocator = await page.getByLabel( 'Title' );
				await titleSettingLocator.fill( settings.title );
				break;

			case 'description':
				const descriptionSettingLocator = await page.getByLabel( 'Description' );
				await descriptionSettingLocator.fill( settings.description );
				break;

			case 'snapcode':
				const snapcodeSettingLocator = await page.getByLabel( 'SnapCode' );
				await snapcodeSettingLocator.fill( settings.snapcode );
				break;

			case 'merchant_api_key':
				const merchantApiKeySettingLocator = await page.getByLabel( 'Merchant API Key' );
				await merchantApiKeySettingLocator.fill( settings.merchant_api_key );
				break;

			case 'merchant_callback_token':
				const merchantCallbackTokenSettingLocator = await page.getByLabel( 'Merchant Callback Token' );
				await merchantCallbackTokenSettingLocator.fill( settings.merchant_callback_token );
				break;

		}
	}

	const waitForURLPromise = page.waitForURL(
		'**/wp-admin/admin.php?page=wc-settings&tab=checkout&section=snapscan' );
	const submitButtonLocator = await page.locator( 'text=Save changes' );
	await submitButtonLocator.click();
	await waitForURLPromise;
};

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
