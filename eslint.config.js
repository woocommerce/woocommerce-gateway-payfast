const woocommerce = require( '@woocommerce/eslint-plugin' );

module.exports = [
	{
		ignores: [
			'build/**',
			'assets/**',
			'includes/**',
			'languages/**',
			'vendor/**',
			'tests/**',
		],
	},
	...woocommerce.configs.recommended,
	{
		settings: {
			jsdoc: { mode: 'typescript' },
			// Modules that webpack.config.js maps to scripts loaded by
			// WordPress and WooCommerce, so they are not installed here.
			'import/core-modules': [
				'@woocommerce/blocks-registry',
				'@woocommerce/settings',
				'@wordpress/html-entities',
				'@wordpress/i18n',
			],
		},
		rules: {
			'@wordpress/i18n-text-domain': [
				'error',
				{ allowedTextDomain: 'woocommerce-gateway-payfast' },
			],
		},
	},
	{
		// Node loads these config files as CommonJS.
		files: [ '*.config.js', '.prettierrc.js' ],
		rules: {
			'@typescript-eslint/no-require-imports': 'off',
		},
	},
];
