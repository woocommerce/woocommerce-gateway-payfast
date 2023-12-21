## E2E Tests

This directory contains end-to-end tests for the project, utilizing [Playwright](https://playwright.dev) to run tests in Chromium browser.

### Pre-requisites
- Create [sandbox PayFast](https://sandbox.payfast.co.za/) account which used for test payments.

### Dependencies for local testing
- Add `.env` file with PayFast credentials in `./tests/e2e/config` directory.
```
PAYFAST_MERCHANT_ID=********
PAYFAST_MERCHANT_KEY=********
PAYFAST_PASSPHRASE=********
```

**Note**: Use `npm run test:e2e-local` to run tests locally.

### Dependencies for Github testing
- Add bot token to Github,
```
gh secret set BOT_GITHUB_TOKEN --app actions --repo=woocommerce/woocommerce-gateway-payfast
```
- Add PayFast credentials to Github.
```
gh secret set PAYFAST_MERCHANT_ID --app actions --repo=woocommerce/woocommerce-gateway-payfast
gh secret set PAYFAST_MERCHANT_KEY --app actions --repo=woocommerce/woocommerce-gateway-payfast
gh secret set PAYFAST_PASSPHRASE --app actions --repo=woocommerce/woocommerce-gateway-payfast
```

**Note**: Add `needs: e2e testing` to pull request to run e2e tests. 
