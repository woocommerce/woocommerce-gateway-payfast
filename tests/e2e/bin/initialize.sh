#!/bin/bash

echo "Initializing WooCommerce Gateway Payfast E2E"

# Enable pretty permalinks.
wp-env run tests-wordpress chmod -c ugo+w /var/www/html
wp-env run tests-cli wp rewrite structure '/%postname%/' --hard

# Use storefront theme.
wp-env run tests-cli wp theme activate storefront
wp-env run tests-cli wp option update storefront_nux_dismissed 1

# Activate and setup WooCommerce.
wp-env run tests-cli wp wc tool run install_pages --user=1
wp-env run tests-cli wp wc payment_gateway update cod --enabled=true --user=1

wp-env run tests-cli wp option update woocommerce_currency "USD"
wp-env run tests-cli wp option update woocommerce_default_country "US:CA"

wp-env run tests-cli wp user create customer customer@euvatnumbertestsuite.com --user_pass=password --role=customer

wp-env run tests-cli wp wc tax create -- --country="*" --state="*" --postcode="*" --city="*" --rate=20 --name="General Tax" --user=1
wp-env run tests-cli wp wc product create -- --name="Simple Product" --slug="simple-product" --user=1 --regular_price=10
wp-env run tests-cli wp post create ./wp-content/plugins/woocommerce-gateway-payfast/tests/e2e/config/checkout-block-page.txt --post_title='Checkout Block' --post_type=page --post_status=publish --post_author=1
wp-env run tests-cli wp wc product create -- --name="Simple Subscription Product" --slug="simple-subscription-product" --user=1 --regular_price=10 --type=subscription --meta_data='[{"key":"_subscription_price","value":"10"},{"key":"_subscription_period","value":"month"},{"key":"_subscription_period_interval","value":"1"}]'
