# WooCommerce Payfast Gateway

Give customers more flexibility and increase your bottom line with Payfast - one of South Africa's most popular payment gateways.

- [Product page](https://woocommerce.com/products/payfast-payment-gateway/)
- [Documentation](https://woocommerce.com/document/payfast-payment-gateway/)

## Dependencies

- WooCommerce

## Development

### Prerequisites

- [Node.js 24](https://nodejs.org) (see `.nvmrc`; managed via [nvm](https://github.com/nvm-sh/nvm) or [fnm](https://github.com/Schniz/fnm))
- [Composer](https://getcomposer.org/doc/00-intro.md)

Docker is required to run the end-to-end test suite via `@wordpress/env`.

### Quick start

```bash
nvm use
npm install
composer install
npm run build:webpack
```

## npm scripts

```bash
# Development build
npm run build:webpack  # Build JS/CSS assets

# Watch mode
npm run start:webpack  # Rebuild JS/CSS on file changes

# Production build
npm run build          # Generate language files + build assets + create plugin ZIP

# Tests
npm run env:start              # Start the wp-env local test environment
npm run test:e2e               # Run all E2E tests with Playwright
npm run test:e2e-foundational  # Run only @foundational tagged tests
npm run test:e2e-debug         # Run E2E tests in debug mode

# Quality
npm run phpcompat      # PHP compatibility check
npm run lint:js        # ESLint on JS source
```

## Known caveats

- Moderate-severity advisories remain in transitive dependencies of `@wordpress/scripts` (`webpack-dev-server` via `sockjs`). These packages are only used during local development and are never shipped in the plugin.

## Compatibility

This extension is compatible with:
- [WooCommerce Blocks](https://woo.com/products/woocommerce-gutenberg-products-block/)
- [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
- [WooCommerce Deposits](https://woocommerce.com/products/woocommerce-deposits/)
- [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/)

## License

[GPLv3](https://www.gnu.org/licenses/gpl-3.0.html)
