const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

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
			// Load the JSX runtime from WordPress (`react-jsx-runtime`, WP 6.6+)
			// so it always matches the React version WordPress ships.
			requestToExternal: ( request ) =>
				request === 'react/jsx-runtime' ? 'ReactJSXRuntime' : undefined,
			requestToHandle: ( request ) =>
				request === 'react/jsx-runtime' ? 'react-jsx-runtime' : undefined,
		} ),
	],
};
