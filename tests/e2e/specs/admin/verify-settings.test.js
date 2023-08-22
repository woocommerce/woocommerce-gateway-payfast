/**
 * WordPress dependencies
 */
const {test, expect} = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import {
	changeCurreny,
	gotoSnapscanSettingPage,
	editSnapscanSetting
} from '../../utils';

test.describe( 'Store admin can edit plugin setting - @foundational', async () => {
	// Set admin as logged-in user.
	test.use( {storageState: process.env.ADMINSTATE} );

	test( 'Verify Payfast gateway disabled with non-ZAR currency', async ( {page} ) => {
		await changeCurreny( { page, currency: 'USD'} );
		await gotoSnapscanSettingPage( { page } );

		const errorContainerLocator = await page.locator( '.inline.error' );
		await expect( errorContainerLocator )
			.toHaveText( /Choose South African Rands as your store currency in/ );
	} );

	test( 'Verify Payfast plugin compatibility with ZAR store currency', async ( {page} ) => {
		await changeCurreny( { page, currency: 'ZAR'} );
		await gotoSnapscanSettingPage( { page } );

		// Setting field: Enable/Disable
		const togglePaymentGatewaySettingLocator = await page.getByLabel( 'Enable/Disable' );
		await expect( togglePaymentGatewaySettingLocator ).toBeVisible();

		// Setting field: Title
		const titleSettingLocator = await page.getByLabel( 'Title' , { exact: true });
		await expect( titleSettingLocator ).toBeVisible();

		// Setting field: Description
		const descriptionSettingLocator = await page.getByLabel( 'Description' , { exact: true });
		await expect( descriptionSettingLocator ).toBeVisible();

		// Setting field: SnapCode
		const payfastSandboxSettingLocator = await page.getByLabel( 'Payfast Sandbox' );
		await expect( payfastSandboxSettingLocator ).toBeVisible();

		// Setting field: Merchant API Key
		const merchantIDSettingFieldLocator = await page.getByLabel( 'Merchant ID' );
		await expect( merchantIDSettingFieldLocator ).toBeVisible();

		// Setting field: Merchant Callback Token
		const merchantKeySettingFieldLocator = await page.getByLabel( 'Merchant Key' );
		await expect( merchantKeySettingFieldLocator ).toBeVisible();

		// Setting field: Merchant Callback Token
		const passPhraseSettingFieldLocator = await page.getByLabel( 'Passphrase' );
		await expect( passPhraseSettingFieldLocator ).toBeVisible();

		// Setting field: Merchant Callback Token
		const sendDebugEmailSettingFieldLocator = await page.getByLabel( 'Send Debug Emails' );
		await expect( sendDebugEmailSettingFieldLocator ).toBeVisible();

		// Setting field: Merchant Callback Token
		const recipeintSettingFieldLocator = await page.getByLabel( 'Who Receives Debug E-mails?' );
		await expect( recipeintSettingFieldLocator ).toBeVisible();

		// Setting field: Debug Log
		const debugLogSettingFieldLocator = await page.getByLabel( 'Enable Logging' );
		await expect( debugLogSettingFieldLocator ).toBeVisible();
	} );
} );
