/**
 * Change the currency
 *
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
		}
	}

	const waitForURLPromise = page.waitForURL(
		'**/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payfast' );
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
