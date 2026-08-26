#!/bin/bash

echo "Initializing WooCommerce Gateway Payfast E2E"

# Create a subscribe-only product using the WooCommerce Subscriptions 9.0 purchase-options
# model (a simple product carrying a single subscription plan in `_wcsatt_schemes`), rather
# than the legacy `subscription` product type.
# Args: slug, name, regular price, billing period (day|week|month|year).
create_subscription_product() {
	local slug="$1" name="$2" price="$3" period="$4"
	local scheme_key="1_${period}"
	local product_id schemes_json

	# Skip if a product with this slug already exists (idempotent re-runs).
	if wp-env run tests-cli wp wc product list --field=slug --user=1 | grep -qx "${slug}"; then
		return
	fi

	wp-env run tests-cli wp wc product create -- \
		--name="${name}" --slug="${slug}" --user=1 --regular_price="${price}" --virtual=true

	# Resolve the new product's ID by slug so the plan meta can be attached to it.
	product_id=$(wp-env run tests-cli wp post list --post_type=product --name="${slug}" --format=ids | grep -oE '[0-9]+' | tail -n1)

	if [[ -z "${product_id}" ]]; then
		echo "Failed to resolve product ID for slug=${slug}" >&2
		exit 1
	fi

	# `_wcsatt_schemes` holds a nested array; store it via --format=json so WP-CLI serializes it.
	# Pricing method "override" sets the plan's own recurring price to the product-level price.
	schemes_json=$(printf '{"%s":{"id":"%s","key":"%s","subscription_period":"%s","subscription_period_interval":1,"subscription_length":0,"subscription_trial_period":"day","subscription_trial_length":0,"subscription_pricing_method":"override","subscription_regular_price":"%s","subscription_sale_price":"","subscription_discount":""}}' "${scheme_key}" "${scheme_key}" "${scheme_key}" "${period}" "${price}")

	wp-env run tests-cli wp post meta update "${product_id}" _wcsatt_schemes "${schemes_json}" --format=json
	wp-env run tests-cli wp post meta update "${product_id}" _wcsatt_force_subscription "yes"
}

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
wp-env run tests-cli wp option update woocommerce_allow_tracking "no"
wp-env run tests-cli wp option update woocommerce_coming_soon "no"

# Add customer user if it doesn't exist.
if ! wp-env run tests-cli wp user get customer --field=ID &>/dev/null; then
    wp-env run tests-cli wp user create customer customer@payfasttestsuite.com --user_pass=password --role=customer
fi

if ! wp-env run tests-cli wp wc tax list --field=name --user=1 | grep -q "General Tax"; then
    wp-env run tests-cli wp wc tax create -- --country="*" --state="*" --postcode="*" --city="*" --rate=20 --name="General Tax" --user=1
fi

if ! wp-env run tests-cli wp wc product list --field=slug --user=1 | grep -qx "simple-product"; then
	wp-env run tests-cli wp wc product create -- --name="Simple Product" --slug="simple-product" --user=1 --regular_price=10 --virtual=true
fi
create_subscription_product "simple-subscription-product" "Simple Subscription Product" "10" "month"
create_subscription_product "second-subscription-product" "Second Subscription Product" "15" "month"
create_subscription_product "yearly-subscription-product" "Yearly Subscription Product" "50" "year"

# Add Shortcode checkout page.
if ! wp-env run tests-cli wp post list --post_type=page --field=post_name | grep -q "shortcode-checkout"; then
	wp-env run tests-cli wp post create --post_title='Shortcode Checkout' --post_type=page --post_status=publish --post_author=1 --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
fi
