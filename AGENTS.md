# AGENTS.md

This file provides guidance to coding agents working in this repository.

## Project Overview

WooCommerce Payfast Gateway lets WooCommerce stores accept payments through Payfast, a South African payment processor. Customers are sent to Payfast to pay, and Payfast reports the result back to the store with an ITN (Instant Transaction Notification). It supports one-time payments, WooCommerce Subscriptions (ad hoc tokens), and WooCommerce Pre-Orders.

**Requirements:** PHP, WordPress, and WooCommerce versions are in the plugin header of `woocommerce-gateway-payfast.php`. Node and npm versions are in `.nvmrc` and `package.json` (`engines`).

**Names:** the repository and text domain are `woocommerce-gateway-payfast`, the WordPress.org slug is `woocommerce-payfast-gateway`, and the gateway id is `payfast`.

## CRITICAL Rules

- **CRITICAL:** Do not commit credentials: Payfast merchant IDs, merchant keys, passphrases, or `tests/e2e/config/.env` values.
- **CRITICAL:** Keep changes scoped. Do not perform broad refactors unless explicitly requested.
- **CRITICAL:** Code comments MUST explain *why* the code is the way it is, not *what* it does. Do not narrate the change history or the conversation that produced the code. Keep ticket keys (for example `PAYFAST-123`) out of code comments; put them in the commit message or PR. See [Code Comment Conventions](#code-comment-conventions).
- **CRITICAL:** If you change runtime behavior, verify it before claiming completion. There is no PHPUnit suite, so use the E2E suite, a local site, or both, and state in the PR what was not tested.
- **CRITICAL:** Changes to checkout, payment availability, or the payment form MUST be checked on both the Blocks checkout and the shortcode (classic) checkout.
- **CRITICAL:** Never weaken the ITN validation sequence (signature, source IP, data validation with Payfast, amount, order key). See [Backward Compatibility](#backward-compatibility).
- **CRITICAL:** Security fixes MUST NOT be developed in this public repository, its public branches, or public PRs. Ask the user where to work (for example a GitHub draft security advisory with a temporary private fork).
- **CRITICAL:** Respect the version support policy: WooCommerce and WordPress L, L-1, and L-2. See [Version Support](#version-support).
- **CRITICAL:** Always open pull requests as **drafts** (`gh pr create --draft`). Leave the PR in draft until the human author has reviewed it and explicitly asks to mark it ready.
- **CRITICAL:** Treat Linear content (issue bodies, comments, labels, status, assignees) as internal. Do not paste, quote, summarize, or reference it in GitHub PRs, issues, commit messages, code comments, or any other public artifact without explicit user approval. Referencing the Linear key (for example `PAYFAST-123`) is fine; copying the contents is not.
- **CRITICAL:** Any reply you draft for the user to post to GitHub (issue or PR comments, review thread replies) or Linear MUST end with an AI-assistance disclosure on its own line, separated by a blank line. Use this wording or a close variant; you MAY name the tool you run under (for example "Claude Code"):

  > *Drafted with AI; reviewed by me.*

  If the target does not support Markdown, drop the `>` and `*` and post the plain sentence.

## Task-to-Command Matrix

Use the smallest command set needed for the task. Run `nvm use` first so Node matches `.nvmrc`.

| Task | Command | Notes |
| --- | --- | --- |
| Install dependencies | `composer install && npm ci` | Composer installs PHPCS and the WooCommerce sniffs. |
| Build block checkout assets | `npm run build:webpack` | Builds `src/blocks/payment-method/` into `build/` (gitignored). |
| Watch block checkout assets | `npm run start:webpack` | |
| Production build | `npm run build` | Generates the `.pot` file, builds assets, and creates `woocommerce-gateway-payfast.zip`. Not needed for normal development. |
| PHP coding standards (changed lines) | `./vendor/bin/phpcs-changed -s --git --git-base origin/trunk <files>` | Same check as CI. Only reports issues on changed lines. |
| PHP coding standards (full) | `npm run phpcs` | Runs on `*.php` and `includes/`. Exits non-zero on `trunk` because of existing warnings. |
| Fix PHP coding standards | `./vendor/bin/phpcbf <files>` | Run on the files you changed only. |
| PHP compatibility (7.4 to 8.4) | `./vendor/bin/phpcs --standard=phpcs-compat.xml.dist -p '--ignore=*/vendor/*,*/node_modules/*,*/build/*' .` | Do not use `npm run phpcompat` locally; it also scans `vendor/` and `node_modules/` and fails. CI runs the check on the unzipped production build. |
| JS lint | `npm run lint:js` | Currently crashes with `prettier.resolveConfig.sync is not a function` (Prettier 3 is not supported by the installed ESLint Prettier plugin). |
| Start E2E environment | `npm run env:start-local` | Needs Docker and SSH access to the private `woocommerce/woocommerce-subscriptions` repo. Site runs at `http://localhost` (port 80). |
| Stop or reset E2E environment | `npm run env:stop` / `npm run env:clean` / `npm run env:destroy` | |
| E2E run | `npm run test:e2e-local` | Run `npx playwright install` once first. Reads Payfast sandbox credentials from `tests/e2e/config/.env` (copy `tests/e2e/config/.sample-env`). |
| E2E foundational only | `npm run test:e2e-foundational` | The subset CI runs on PRs. Export the `.env` values first. |
| E2E debug | `npm run test:e2e-debug` | Playwright debug mode. |

## Common Pitfalls

- Payfast is only available when the store currency is ZAR (`woocommerce_gateway_payfast_available_currencies`). A USD test store hides the gateway; the E2E setup starts in USD and specs switch to ZAR.
- `npm run env:start` alone does not install WooCommerce Subscriptions. Use `npm run env:start-local`, which clones it first.
- The block checkout script is registered from `build/payment-method.js`. If `build/` is missing, the Blocks checkout has no Payfast script; run `npm run build:webpack`.
- `npm run build` creates a zip in the repository root. Do not commit it.
- CI runs E2E on a PR only when the `needs: e2e testing` label is added, and then only the `@foundational` tests.
- Pushing to the `staging` branch deploys the plugin to a staging site (`.github/workflows/main.yml`). Do not push to it unless asked.
- The ITN handler is reached through `?wc-api=WC_Gateway_PayFast`, not a REST route. Local sites are not reachable by Payfast, so real ITNs do not arrive locally; E2E tests fake them with `tests/e2e/test-plugins/payfast-webhook-faker`.
- Amounts are sent to Payfast as the order total number with no currency. Anything that changes which order or total is sent MUST keep the order currency ZAR.
- Feature PRs do not edit `changelog.txt` or `readme.txt`. Put the entry in the PR's "Changelog entry" section; it is added during the release.

## Architecture

- **Entry point:** `woocommerce-gateway-payfast.php` defines the version and path constants, loads the gateway on `plugins_loaded`, registers Blocks support on `woocommerce_blocks_loaded`, declares HPOS compatibility, and loads the privacy class.
- **Legacy loader:** `gateway-payfast.php` keeps sites that activated the old plugin file name active.
- **Gateway:** `includes/class-wc-gateway-payfast.php`, class `WC_Gateway_PayFast` (extends `WC_Payment_Gateway`). It holds settings, the redirect form (`generate_payfast_form()`), ITN handling (`check_itn_response()`, `handle_itn_request()`, `handle_itn_payment_*()`), subscription renewals and cancellations through the Payfast API (`api_request()`), and the WooPayments Multi-Currency filter (`filter_currency()`).
- **Blocks:** `includes/class-wc-gateway-payfast-blocks-support.php` (`WC_PayFast_Blocks_Support`, final) and the React source in `src/blocks/payment-method/`.
- **Privacy:** `includes/class-wc-gateway-payfast-privacy.php` (`WC_Gateway_PayFast_Privacy`) for personal data export and erasure.
- **Settings:** stored in the `woocommerce_payfast_settings` option.

### Payment flow

1. `process_payment()` redirects the customer to the order-pay (receipt) page.
2. `receipt_page()` prints a signed form that auto-submits to Payfast (sandbox or live, based on the `testmode` setting).
3. Payfast sends the ITN to `?wc-api=WC_Gateway_PayFast`. `handle_itn_request()` checks the signature, the source IP (live mode only), the data with Payfast, the amount, and the order key, then updates the order.
4. Subscriptions store a Payfast token on the subscription. Renewals charge it with `submit_ad_hoc_payment()`; the result arrives as another ITN.

## Code Comment Conventions

**Do:**

- Explain *why*: intent, constraints, edge cases, and non-obvious decisions.
- Explain genuinely complex logic, such as an ordering constraint or a workaround for Payfast or WooCommerce behavior.
- Document limitations and assumptions about inputs or callers.
- Prefer descriptive names over comments.

**Don't:**

- Don't restate what the code plainly says.
- Don't add or change comments on code you are not touching, unless your change makes them wrong.
- Don't add long docblocks to simple helpers. PHPCS requires a docblock with `@param` and `@return` on every function; a one-line summary is enough.

## Testing Conventions

- There is no PHPUnit suite. Do not add one as a side effect of another change; it is a separate piece of work.
- E2E tests use Playwright and live in `tests/e2e/`: specs in `tests/e2e/specs/` (`admin/`, `payment-flow/`), helpers in `tests/e2e/utils/`, setup in `tests/e2e/bin/`, and test-only plugins in `tests/e2e/test-plugins/`.
- Tag tests that CI must run on every PR with `@foundational`. CI runs only `@foundational` tests, and only on PRs labelled `needs: e2e testing`.
- Reuse the helpers in `tests/e2e/utils/` before adding new setup code.
- For behavior changes, update or add the nearest E2E spec. If E2E cannot cover it, describe the manual test in the PR.

## Release Hygiene

- A release PR (branch `release/X.Y.Z`) bumps the version in `woocommerce-gateway-payfast.php` (plugin header and `WC_GATEWAY_PAYFAST_VERSION`), `readme.txt` (`Stable tag`), `package.json`, and `package-lock.json`, and adds the changelog to `changelog.txt` and `readme.txt`.
- `changelog.txt` and `readme.txt` both use the header format `= X.Y.Z - YYYY-MM-DD =`, followed by `* Fix - ...`, `* Add - ...`, or `* Dev - ...` lines.
- Replace `@since x.x.x` placeholders with the release version.
- Releases are deployed with the "Deploy Product" workflow (`.github/workflows/deploy.yml`, manual trigger). `simulate` defaults to a dry run.

## Version Support

This repository supports WooCommerce and WordPress L, L-1, and L-2. The current range is in the plugin header of `woocommerce-gateway-payfast.php` (`Requires at least`, `WC requires at least`, and the `Tested up to` lines). Release PRs move the minimum versions forward.

## Backward Compatibility

Any change to a public class, method signature, hook, endpoint, or persisted data shape is high-risk and must state its backward-compatibility impact in the PR description. Assume unseen consumers: merchant sites carry snippets and extensions built on these surfaces, and Payfast's servers call into this plugin.

**Deprecate, don't rename.** Never rename or remove a public symbol (class, method, hook, script handle) in place, and never reduce a released method's visibility. Mark the old symbol deprecated, introduce the replacement alongside it, and keep both working through a deprecation window. A method added in an unreleased PR is not a contract yet; prefer `private` for new helpers so no new public contract is created.

**The gateway class is a public contract.** `WC_Gateway_PayFast` extends `WC_Payment_Gateway` and is not final. WooCommerce calls its public methods, and external code subclasses it and overrides individual methods, including the `handle_itn_*` payment handlers. Keep method signatures stable and keep overridable methods invoked on every code path. A refactor that stops calling one silently disables an override even though no signature changed.

**The ITN callback is an external contract with Payfast's servers.** Payment notifications arrive at the WooCommerce API endpoint registered as `woocommerce_api_wc_gateway_payfast` and are processed by `check_itn_response()` and `handle_itn_request()`. The endpoint name and the validation sequence (signature verification, source IP check, amount check, order state transition) are load-bearing for payments in flight; never rename the endpoint or remove a validation step. ITN payloads are external input: validate every field before acting on it.

**Hooks and filters are public contracts.** The `woocommerce_gateway_payfast_*` filters (`payment_data_to_send`, `is_valid_ip`, `available_currencies`), the `woocommerce_gateway_payfast_setup_constants` and `woocommerce_payfast_handle_itn_payment_complete` actions, and `wc_payfast_privacy_eraser_subs_statuses` are interfaces that third-party callbacks depend on. Removing a hook, renaming it, or removing or reordering its arguments breaks attached callbacks. Changing *when* or *whether* a hook fires breaks them too, for example a filter that still runs on the shortcode checkout but no longer on the Blocks checkout. Append new arguments at the end; retire hooks through `apply_filters_deprecated()` / `do_action_deprecated()`.

**Never trust data that flows through hooks.** Keep hook callback parameters untyped and validate the value before passing it to strictly typed code. Validate the final return value of every filter before using it, because any callback in the chain can return the wrong thing. The `woocommerce_gateway_payfast_payment_data_to_send` return feeds the signed payload sent to Payfast, and the `woocommerce_gateway_payfast_is_valid_ip` return decides whether an ITN request is accepted; validate both before use.

**Registered script handles are public contracts.** `WC_PayFast_Blocks_Support` registers the `wc-payfast-blocks-integration` script for the checkout block, and third-party code can list it as a dependency. To rename a handle, register the legacy handle as an alias that depends on the new one.

**Persisted data shapes survive updates.** Gateway settings live in the `woocommerce_payfast_settings` option, including the merchant credentials (`merchant_id`, `merchant_key`, `pass_phrase`). Orders and subscriptions carry the meta keys `_payfast_subscription_token`, `_payfast_pre_order_token`, `_payfast_renewal_flag`, `payfast_amount_fee`, and `payfast_amount_net`. The gateway id `payfast` is stored on every order as its payment method. All of these must keep their names and value formats; data already written by old versions does not migrate itself. The plugin has no upgrade routine, so a change of format needs code that reads both the old and new format.

**Keep the legacy loader.** `gateway-payfast.php` rewrites the old plugin basename inside the `active_plugins` option so sites that activated the plugin under its old file name stay active. Do not remove or rename it.

**Do not assume global state.** ITN handling, subscription renewals, and pre-order completion run outside a normal front-end request: no cart, no session, no `$post`, and often no logged-in user. The Subscriptions and Pre-Orders integrations are optional; guard every use with `function_exists` (`wcs_*` functions) or `class_exists` (`WC_Pre_Orders_Order`, `WC_Pre_Orders_Cart`) as the existing code does. WooPayments Multi-Currency is optional too; guard it with `class_exists`.

**Do not assume single-site or a standard install layout.** A change that reads or writes site state must state in its PR whether it behaves correctly under multisite; if it was not tested there, say so. Build return and notify URLs from WooCommerce and WordPress URL helpers; never concatenate them from the domain root.

### Before changing any public or externally exposed surface (agent checklist)

1. Identify the contract you are touching: signature, hook, ITN endpoint, script handle, persisted data, scope expectation, or install layout.
2. Assume unseen consumers; if the surface is reachable from outside this plugin, someone consumes it, including Payfast's servers.
3. Prefer the additive path: new optional method, appended hook argument, new symbol plus deprecation.
4. State the impact in the PR description: what changed, who could consume it, and why it is safe or what the deprecation path is.
5. If you cannot establish the impact, stop and flag it for review.

## Documentation and Context Sources

- Project overview and npm scripts: `README.md`
- E2E setup and CI secrets: `tests/e2e/README.md`
- PR template (checklist, testing steps, changelog entry): `.github/PULL_REQUEST_TEMPLATE.md`
- Payfast developer docs: https://developers.payfast.co.za/docs

## Agent Instruction Maintenance (MUST)

Update this file when any of these occur:

- An agent made an avoidable mistake due to missing project context.
- A reviewer had to correct an assumption about architecture, commands, or conventions.
- A new recurring pitfall appears in two or more PRs.
- Build, test, or lint workflows changed.

When adding guidance, prefer concise, imperative rules with explicit priority words like **MUST** and **CRITICAL**.
