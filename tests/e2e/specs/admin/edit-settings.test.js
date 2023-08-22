import {chromium} from "@playwright/test";

/**
 * WordPress dependencies
 */
const {test, expect} = require( '@playwright/test' );

/**
 * Internal dependencies
 */
import {
	changeCurrency,
	editPayfastSetting,
	gotoPayfastSettingPage,
	addProductToCart
} from '../../utils';

test.describe.configure({ mode: 'serial' });

test.describe( 'Verify SnapCode setting - @foundational', async () => {
	let adminPage, checkoutPage, checkoutBlock;

	test.beforeAll(async ({ browser }) => {
		const adminContext = await browser
			.newContext(  {storageState: process.env.ADMINSTATE});
		const customerContext = await browser
			.newContext( {storageState: process.env.CUSTOMERSTATE} );
		adminPage = await adminContext.newPage();
		checkoutPage = await customerContext.newPage();
		checkoutBlock = await customerContext.newPage();
	});


	test( 'Setup: Edit setting & Add product', async (  ) => {
		await changeCurrency( {page: adminPage, currency: 'ZAR'} );

		await editPayfastSetting( {
			page: adminPage,
			settings: {
				toggle_payment_gateway: true,
				title: 'Payfast',
				description: 'Pay with payfast',
			}
		} );
	} );

	test( 'Checkout Block: Verify method title & description', async () => {
		await addProductToCart( {page: checkoutBlock, productUrl:'/product/simple-product/'} );
		await checkoutBlock.goto('/checkout-block/');

		const paymentMethodLocator = await checkoutBlock.locator( 'label[for="radio-control-wc-payment-method-options-payfast"]' );
		await expect( await checkoutBlock.locator( 'label[for="radio-control-wc-payment-method-options-payfast"] img' ). getAttribute('alt'))
			.toEqual('Payfast');
		await paymentMethodLocator.click();
		await expect(await checkoutBlock.locator( '.wc-block-components-radio-control-accordion-content' ) )
			.toHaveText(/Pay with payfast/);
	});

	test( 'Checkout Page: Verify method title & description', async () => {
		await addProductToCart( {page: checkoutPage, productUrl:'/product/simple-product/'} );
		await checkoutPage.goto('/checkout/');

		const paymentMethodLocator = await checkoutPage.locator( '.wc_payment_method.payment_method_payfast' );
		await expect( paymentMethodLocator ).toHaveText(/Payfast/);
		await paymentMethodLocator.click();
					await expect(await checkoutPage.locator( '.payment_box.payment_method_payfast' ) )
						.toHaveText(/Pay with payfast/);
	});
} );
