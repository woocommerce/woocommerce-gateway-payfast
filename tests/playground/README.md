# WordPress Playground previews

Every pull request gets a "Test the build" comment with [WordPress Playground](https://wordpress.org/playground/) links. Playground runs WordPress in the visitor's browser, so the links need no site of your own.

- **Open with a demo checkout** starts a store where Payfast is switched on with placeholder credentials, on the shop page.
- **Open unconfigured** starts a store on the Payfast settings screen, with no credentials.

Both stores use South African rand, have one product ("Playground test product") and a "Shortcode Checkout" page next to the Blocks "Checkout" page.

The links are built in `.github/workflows/pr-build-live-branch.yml`. Each one is a Blueprint that installs WooCommerce, downloads one workflow artifact holding the build of the pull request and the two preview files, and runs `setup.php` from this directory.

## What the previews cannot do

- **Take a payment.** A mu-plugin replaces the page that would send the customer to Payfast with a notice. Its source is `wordpress_org_assets/blueprint-payfast-takeover.php.txt`, the same file the WordPress.org live preview uses.
- **Receive payment notifications (ITN).** Nothing on the internet can reach a site that runs in a browser, so orders stay "Pending payment" and nothing that happens after a payment can be checked.
- **Subscriptions and Pre-Orders.** Those plugins are not installed.

To test a payment, use a site Payfast can reach, or the E2E suite, which fakes the notification.

## Why there are no credentials

Playground downloads the build through its artifact proxy, which picks the build by branch name. A pull request from a fork whose branch has the same name can therefore have its build served by a link. Whatever code runs in a preview can read what is typed into it.

So no Blueprint holds Payfast credentials, the comment never asks for them, and a preview is not the place to enter real ones.

The placeholder settings of the demo checkout are saved before the plugin is installed. Saving them later would make the plugin check them with Payfast and mark them invalid, which hides Payfast from checkout.

## Changing the previews

`setup.php` and the receipt page file are copied from the pull request's own checkout into the artifact, so a pull request that changes them changes its own preview.

Nothing in this directory ships in the plugin zip: `tests` is excluded in `.gitattributes`.
