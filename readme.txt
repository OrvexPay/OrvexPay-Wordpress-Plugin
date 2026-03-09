=== OrvexPay Payment Gateway ===
Contributors:            orvexpay
Tags:                    crypto, bitcoin, usdt, woocommerce, payment gateway
Requires at least:       5.8
Tested up to:            6.9
Requires PHP:            7.4
Stable tag:              1.0.0
WC requires at least:    6.0
WC tested up to:         9.4
License:                 GPL-2.0-or-later
License URI:             https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin, Ethereum, USDT, USDC and 50+ cryptocurrencies in your WooCommerce store via OrvexPay — zero chargebacks, instant settlement.

== Description ==

**OrvexPay** is a next-generation crypto payment gateway. This plugin integrates OrvexPay's powerful payment infrastructure directly into your WooCommerce store.

= Key Features =

* ✅ Accept **50+ cryptocurrencies** — Bitcoin, Ethereum, USDT (TRC20/ERC20/BEP20), USDC, BNB, TRX, LTC, DOGE and more.
* ✅ **Zero chargebacks** — crypto payments are irreversible, eliminating payment disputes.
* ✅ **Instant settlement** — funds land in your OrvexPay wallet after blockchain confirmation.
* ✅ **Hosted checkout** — customers are redirected to a beautiful, secure payment page hosted by OrvexPay.
* ✅ **Webhook verification** — HMAC-SHA256 signed webhooks keep your order statuses always in sync.
* ✅ **WooCommerce HPOS compatible** — fully supports High-Performance Order Storage.

= How It Works =

1. Customer places an order and selects **Crypto Payment (OrvexPay)** at checkout.
2. They are redirected to OrvexPay's hosted checkout page with a QR code and countdown timer.
3. Customer sends the crypto payment.
4. OrvexPay confirms the blockchain transaction and notifies your store via webhook.
5. The WooCommerce order is automatically marked as **Processing/Completed**.

== Installation ==

1. Upload the `orvexpay-payment-gateway` folder to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → Payments** and enable **OrvexPay**.
4. Enter your **API Key** and **Webhook Secret** from [OrvexPay Dashboard → API Keys](https://orvexpay.com/dashboard/api-keys).
5. Set your Webhook URL in OrvexPay Dashboard to: `https://yourstore.com/wc-api/orvexpay_webhook`
6. Save settings. Done!

== Frequently Asked Questions ==

= Where do I get my API Key? =
Log in to [OrvexPay Dashboard](https://orvexpay.com/dashboard), go to **API Keys**, and create a new key.

= What is the Webhook Secret? =
When creating or editing an API Key in the OrvexPay Dashboard, you'll see a `whsec_...` Webhook Secret. Copy this to the plugin settings so webhook signatures can be verified.

= Which cryptocurrencies are supported? =
Bitcoin (BTC), Ethereum (ETH), USDT (TRC20, ERC20, BEP20), USDC (ERC20), BNB, TRX, LTC, DOGE, and more. Additional currencies can be added in future updates.

= Is this plugin compatible with HPOS? =
Yes — full High-Performance Order Storage (custom order tables) support is declared.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrades needed.
