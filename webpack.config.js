const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

// WooCommerce Blocks packages are loaded by WooCommerce as scripts, so they
// must stay out of the bundle. The @woocommerce extraction plugin did this,
// but it wraps an old version of the WordPress plugin that bundles React's
// JSX runtime instead of using the `react-jsx-runtime` script from WordPress.
const wooPackages = {
	'@woocommerce/blocks-registry': {
		external: [ 'wc', 'wcBlocksRegistry' ],
		handle: 'wc-blocks-registry',
	},
	'@woocommerce/settings': {
		external: [ 'wc', 'wcSettings' ],
		handle: 'wc-settings',
	},
};

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry,
		'payment-method': './src/blocks/payment-method/index.js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal: ( request ) => wooPackages[ request ]?.external,
			requestToHandle: ( request ) => wooPackages[ request ]?.handle,
		} ),
	],
};
