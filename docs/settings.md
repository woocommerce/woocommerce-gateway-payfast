# Payfast gateway settings

Settings are under **WooCommerce > Settings > Payments > Payfast** and are stored in the `woocommerce_payfast_settings` option.

| Setting | Key | Explanation |
| --- | --- | --- |
| Enable/Disable | `enabled` | Shows Payfast as a payment method at checkout; off by default until the merchant details are entered. |
| Title | `title` | The payment method name customers see at checkout (default: "Payfast"). |
| Description | `description` | The text shown under the payment method at checkout (default: "Pay securely with Payfast."). |
| Payfast Sandbox | `testmode` | Sends payments to the Payfast sandbox instead of the live site, using sandbox credentials. |
| Merchant ID | `merchant_id` | The merchant ID from your Payfast account. |
| Merchant Key | `merchant_key` | The merchant key from your Payfast account. |
| Passphrase | `pass_phrase` | The passphrase set in your Payfast account, used to sign the data sent to and received from Payfast. |
| Send Debug Emails | `send_debug_email` | Emails a report for each Payfast transaction, successful or not; only applies in sandbox mode. |
| Who Receives Debug E-mails? | `debug_email` | The address that receives debug emails (default: the site admin email). |
| Enable Logging | `enable_logging` | Writes transaction details to the WooCommerce log (source `payfast`) in live mode; sandbox mode always logs. |
