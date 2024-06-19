/**
 * WordPress dependencies
 */
const { test, expect } = require('@playwright/test');

test.describe(
	'Store admin can login and make sure add-on is activated - @foundational',
	async (selector, options) => {
		// Set admin as logged-in user.
		test.use({ storageState: process.env.ADMINSTATE });

		test('Plugin Activation Without Errors', async ({ page }) => {
			await page.goto('/wp-admin/plugins.php');

			// Addon is active by default in the test environment, so we need to validate that it is activated.
			await expect(
				page.getByRole('link', {
					name: 'Deactivate WooCommerce Payfast Gateway',
					exact: true,
				})
			).toBeVisible();
		});
	}
);
