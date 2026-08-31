# WooCommerce Gateway Payfast - AI Agents Documentation

WooCommerce Gateway Payfast is a WooCommerce payment gateway for Payfast, a South African payment processor. The gateway class lives in `includes/class-wc-gateway-payfast.php`; checkout block support lives in `includes/class-wc-gateway-payfast-blocks-support.php`.

## Backward Compatibility

Any change to a public class, method signature, hook, endpoint, or persisted data shape is high-risk and must state its backward-compatibility impact in the PR description. Assume unseen consumers: merchant sites carry snippets and extensions built on these surfaces, and Payfast's servers call into this plugin.

**Deprecate, don't rename.** Never rename or remove a public symbol (class, method, hook, script handle) in place. Mark the old symbol deprecated, introduce the replacement alongside it, and keep both working through a deprecation window.

**The gateway class is a public contract.** `WC_Gateway_PayFast` extends `WC_Payment_Gateway` and is not final. WooCommerce calls its public methods, and external code subclasses it and overrides individual methods, including the `handle_itn_*` payment handlers. Keep method signatures stable and keep overridable methods invoked on every code path - a refactor that stops calling one silently disables an override even though no signature changed.

**The ITN callback is an external contract with Payfast's servers.** Payment notifications arrive at the WooCommerce API endpoint registered as `woocommerce_api_wc_gateway_payfast` and are processed by `check_itn_response()` and `handle_itn_request()`. The endpoint name and the validation sequence (signature verification, source IP check, amount check, order state transition) are load-bearing for payments in flight; never rename the endpoint or remove a validation step. ITN payloads are external input - validate every field before acting on it.

**Hooks and filters are public contracts.** The `woocommerce_gateway_payfast_*` filters (`setup_constants`, `payment_data_to_send`, `is_valid_ip`, `available_currencies`), the `woocommerce_payfast_handle_itn_payment_complete` action, and `wc_payfast_privacy_eraser_subs_statuses` are interfaces that third-party callbacks depend on. Removing a hook, renaming it, or removing or reordering its arguments breaks attached callbacks. Append new arguments at the end; retire hooks through `apply_filters_deprecated()` / `do_action_deprecated()`.

**Never trust data that flows through hooks.** Validate the final return value of every filter before using it - any callback in the chain can return the wrong thing. The `woocommerce_gateway_payfast_payment_data_to_send` return feeds the signed payload sent to Payfast, and the `woocommerce_gateway_payfast_is_valid_ip` return decides whether an ITN request is accepted; validate both before use.

**Registered script handles are public contracts.** `WC_PayFast_Blocks_Support` registers the `wc-payfast-blocks-integration` script for the checkout block, and third-party code can list it as a dependency. To rename a handle, register the legacy handle as an alias that depends on the new one.

**Persisted data shapes survive updates.** Gateway settings live in the `woocommerce_payfast_settings` option, including the merchant credentials (`merchant_id`, `merchant_key`, `pass_phrase`). Orders and subscriptions carry the meta keys `_payfast_subscription_token`, `_payfast_pre_order_token`, `_payfast_renewal_flag`, `payfast_amount_fee`, and `payfast_amount_net`. The gateway id `payfast` is stored on every order as its payment method. All of these must keep their names and value formats; data already written by old versions does not migrate itself.

**Keep the legacy loader.** `gateway-payfast.php` rewrites the old plugin basename inside the `active_plugins` option so sites that activated the plugin under its old file name stay active. Do not remove or rename it.

**Do not assume global state.** ITN handling, subscription renewals, and pre-order completion run outside a normal front-end request: no cart, no session, no `$post`. The Subscriptions and Pre-orders integrations are optional; guard every use with `function_exists` (`wcs_*` functions) or `class_exists` (`WC_Pre_Orders_Order`, `WC_Pre_Orders_Cart`) as the existing code does.

**Do not assume single-site or a standard install layout.** A change that reads or writes site state must state in its PR whether it behaves correctly under multisite. Build return and notify URLs from WooCommerce and WordPress URL helpers; never concatenate them from the domain root.

### Before changing any public or externally exposed surface (agent checklist)

1. Identify the contract you are touching: signature, hook, ITN endpoint, script handle, persisted data, scope expectation, or install layout.
2. Assume unseen consumers; if the surface is reachable from outside this plugin, someone consumes it - including Payfast's servers.
3. Prefer the additive path: new optional method, appended hook argument, new symbol plus deprecation.
4. State the impact in the PR description: what changed, who could consume it, and why it is safe or what the deprecation path is.
5. If you cannot establish the impact, stop and flag it for review.
