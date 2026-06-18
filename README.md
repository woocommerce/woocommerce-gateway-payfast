# WooCommerce Payfast Gateway

Give customers more flexibility and increase your bottom line with Payfast — one of South Africa's most popular payment gateways.

- [WordPress.org](https://wordpress.org/plugins/woocommerce-payfast-gateway/)
- [Product page](https://woocommerce.com/products/payfast-payment-gateway/)
- [Documentation](https://woocommerce.com/document/payfast-payment-gateway/)

## Dependencies

- WooCommerce

## Getting started

This extension requires a Payfast merchant account. [Sign up for free](https://payfast.io/gateway-aggregator-selector/).

### Features

* Fast, **super-secure** payments from almost anywhere in the world.
* Compatible with **subscriptions**, **deposits**, and **pre-orders**.

### Prerequisites

- [Node.js 24](https://nodejs.org) (managed via [NVM](https://github.com/nvm-sh/nvm#installing-and-updating)): we recommend NVM to keep your Node version aligned with the development team. The repository contains an [`.nvmrc` file](.nvmrc) that pins the supported version.
- [PHP 7.4+](https://www.php.net/manual/en/install.php): required by the plugin and to run Composer / build scripts.
- [Composer](https://getcomposer.org/doc/00-intro.md): manages PHP dependencies and dev tooling.

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

## How does it work?

At checkout, customers are automatically taken to Payfast to pay for their orders. Once payment is complete, Payfast redirects them to your store to continue shopping.

## Fast, super-secure, and scalable

Pay it safe, every time. Payfast is trusted by **more than 100,000** South African businesses, with security that exceeds industry standards. It's PCI DSS-compliant and also supports 18+ popular payment methods. In a nutshell, WooCommerce Payfast Gateway is the fastest, smartest, and safest way to accept payments online.

## Boost your bottom line

Payfast is compatible with [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) and [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/), giving your shoppers the ultimate flexibility. **Convert more customers** by letting them make deposits, order in advance, or subscribe to your products and services. You win each way!

## Frequently Asked Questions

#### Where can I find documentation?

You've come to the right place. [Our documentation](https://woocommerce.com/document/payfast-payment-gateway/) for WooCommerce Payfast Gateway includes detailed setup instructions.

#### Where can I get support?

Get in touch via the [official support forum](https://wordpress.org/support/plugin/woocommerce-payfast-gateway/).

#### Does this extension support subscriptions?

Yes! WooCommerce PayFast Gateway is compatible with [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).

#### Do I need an SSL certificate?

We recommend using a [Secure Sockets Layer (SSL) certificate](https://woocommerce.com/document/ssl-and-https/) for additional customer security and trust.

#### Can I accept international payments?

Yes! You can receive Visa and Mastercard payments from anywhere in the world.

#### Do I need a Payfast account?

Yes; a [Payfast merchant account](https://payfast.io/gateway-aggregator-selector/) is required.

#### Do I need to be a South African resident to open a Payfast account?

No, but to open a Payfast account, you'll need a South African bank account to pay your funds into.

## Compatibility

This extension is compatible with:
- [WooCommerce Blocks](https://woo.com/products/woocommerce-gutenberg-products-block/)
- [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
- [WooCommerce Deposits](https://woocommerce.com/products/woocommerce-deposits/)
- [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/)

## License

[GPLv3](https://www.gnu.org/licenses/gpl-3.0.html)
