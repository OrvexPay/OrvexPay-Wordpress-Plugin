<?php
/**
 * OrvexPay WooCommerce Payment Gateway.
 *
 * Extends WC_Payment_Gateway to:
 *  1. Display admin settings (API key, currency, etc.)
 *  2. Render the payment method on checkout
 *  3. Create an OrvexPay invoice and redirect the customer
 *  4. Handle return from the hosted checkout page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrvexPay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'orvexpay';
        $this->icon               = ORVEXPAY_PLUGIN_URL . 'assets/icon.svg';
        $this->has_fields         = false;
        $this->method_title       = __( 'OrvexPay — Crypto Payments', 'orvexpay-payment-gateway' );
        $this->method_description = __( 'Accept Bitcoin, Ethereum, USDT, USDC and 50+ cryptocurrencies via OrvexPay. Zero chargebacks. Instant settlement.', 'orvexpay-payment-gateway' );

        $this->supports = [ 'products' ];

        $this->init_form_fields();
        $this->init_settings();

        // Read settings
        $this->title            = $this->get_option( 'title' );
        $this->description      = $this->get_option( 'description' );
        $this->enabled          = $this->get_option( 'enabled' );
        $this->api_key          = $this->get_option( 'api_key' );
        $this->pay_currency     = $this->get_option( 'pay_currency', 'USDT_TRC20' );
        $this->webhook_secret   = $this->get_option( 'webhook_secret' );
        $this->order_status     = $this->get_option( 'order_status', 'wc-pending' );

        // Save admin options
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

        // Handle return from hosted checkout
        add_action( 'woocommerce_api_orvexpay_return', [ $this, 'handle_return' ] );
    }

    // ── Admin Settings Form ────────────────────────────────────────────────

    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled' => [
                'title'   => __( 'Enable / Disable', 'orvexpay-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable OrvexPay Crypto Payment Gateway', 'orvexpay-payment-gateway' ),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __( 'Title', 'orvexpay-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Payment method title displayed to customers.', 'orvexpay-payment-gateway' ),
                'default'     => __( 'Crypto Payment (OrvexPay)', 'orvexpay-payment-gateway' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Description', 'orvexpay-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Short description displayed below the payment method title.', 'orvexpay-payment-gateway' ),
                'default'     => __( 'Pay securely with Bitcoin, Ethereum, USDT, USDC and more. You will be redirected to OrvexPay\'s hosted checkout.', 'orvexpay-payment-gateway' ),
            ],
            'api_key' => [
                'title'       => __( 'API Key', 'orvexpay-payment-gateway' ),
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s dashboard URL */
                    __( 'Your OrvexPay API Key. Create one at <a href="%s" target="_blank">OrvexPay Dashboard → API Keys</a>.', 'orvexpay-payment-gateway' ),
                    'https://dashboard.orvexpay.com/dashboard/api-keys'
                ),
                'default'     => '',
            ],
            'webhook_secret' => [
                'title'       => __( 'Webhook Secret', 'orvexpay-payment-gateway' ),
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s dashboard URL */
                    __( 'Your Webhook Secret (whsec_...) from the OrvexPay Dashboard. Also set the Webhook URL to: <code>%s</code>', 'orvexpay-payment-gateway' ),
                    home_url( '/wc-api/orvexpay_webhook' )
                ),
                'default'     => '',
            ],
            'pay_currency' => [
                'title'       => __( 'Default Crypto Currency', 'orvexpay-payment-gateway' ),
                'type'        => 'select',
                'description' => __( 'The cryptocurrency customers pay with by default.', 'orvexpay-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => 'USDT_TRC20',
                'options'     => [
                    'USDT_TRC20'  => 'USDT (TRC20 — Tron)',
                    'USDT_BEP20'  => 'USDT (BEP20 — BSC)',
                    'USDT_ERC20'  => 'USDT (ERC20 — Ethereum)',
                    'USDC_ERC20'  => 'USDC (ERC20 — Ethereum)',
                    'BTC'         => 'Bitcoin (BTC)',
                    'ETH'         => 'Ethereum (ETH)',
                    'BNB'         => 'BNB (BSC)',
                    'TRX'         => 'TRON (TRX)',
                    'LTC'         => 'Litecoin (LTC)',
                    'DOGE'        => 'Dogecoin (DOGE)',
                ],
            ],
            'order_status' => [
                'title'       => __( 'New Order Status', 'orvexpay-payment-gateway' ),
                'type'        => 'select',
                'description' => __( 'Order status set when the customer is redirected to OrvexPay (before payment confirmation).', 'orvexpay-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => 'wc-pending',
                'options'     => [
                    'wc-pending'    => __( 'Pending Payment', 'orvexpay-payment-gateway' ),
                    'wc-on-hold'    => __( 'On Hold', 'orvexpay-payment-gateway' ),
                ],
            ],
        ];
    }

    // ── Checkout Page ──────────────────────────────────────────────────────

    public function payment_fields(): void {
        if ( $this->description ) {
            echo '<p>' . esc_html( $this->description ) . '</p>';
        }
        echo '<p class="orvexpay-icons" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">';
        foreach ( [ 'BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'TRX' ] as $coin ) {
            echo '<span style="font-size:12px;font-weight:600;background:#f3f4f6;border-radius:4px;padding:2px 8px;">' . esc_html( $coin ) . '</span>';
        }
        echo '</p>';
    }

    // ── Process Payment ────────────────────────────────────────────────────

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        if ( empty( $this->api_key ) ) {
            wc_add_notice( __( 'OrvexPay is not configured. Please contact the store administrator.', 'orvexpay-payment-gateway' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $api = new OrvexPay_API( $this->api_key );

        $return_url  = $this->get_return_url( $order );
        $cancel_url  = $order->get_cancel_order_url_raw();
        $webhook_url = home_url( '/wc-api/orvexpay_webhook' );

        $payload = [
            'priceAmount'      => (float) $order->get_total(),
            'priceCurrency'    => get_woocommerce_currency(),
            'payCurrency'      => $this->pay_currency,
            'orderId'          => (string) $order->get_order_number(),
            'orderDescription' => sprintf(
                /* translators: 1: order number, 2: site name */
                __( 'Order #%1$s from %2$s', 'orvexpay-payment-gateway' ),
                $order->get_order_number(),
                get_bloginfo( 'name' )
            ),
            'successUrl'  => $return_url,
            'cancelUrl'   => $cancel_url,
            'webhookUrl'  => $webhook_url,
        ];

        $result = $api->create_invoice( $payload );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
            $order->add_order_note( $result->get_error_message() );
            return [ 'result' => 'failure' ];
        }

        // Store OrvexPay invoice ID on the order
        $invoice_id  = $result['id'] ?? $result['invoiceId'] ?? '';
        $pay_url     = $result['payUrl'] ?? $result['checkoutUrl'] ?? '';

        if ( $invoice_id ) {
            $order->update_meta_data( '_orvexpay_invoice_id', $invoice_id );
        }
        $order->update_meta_data( '_orvexpay_order_id', (string) $order->get_order_number() );
        $order->update_status(
            str_replace( 'wc-', '', $this->order_status ),
            __( 'OrvexPay invoice created. Awaiting payment.', 'orvexpay-payment-gateway' )
        );
        $order->save();

        WC()->cart->empty_cart();

        // Redirect to OrvexPay hosted checkout
        // If pay_url not returned by API, use the standard OrvexPay checkout URL
        if ( empty( $pay_url ) && $invoice_id ) {
            $pay_url = 'https://orvexpay.com/pay/' . rawurlencode( $invoice_id );
        }

        return [
            'result'   => 'success',
            'redirect' => $pay_url,
        ];
    }

    // ── Customer Return Handling ───────────────────────────────────────────

    /**
     * Called when the customer returns from the hosted checkout page.
     * We verify the payment status via the API and redirect accordingly.
     */
    public function handle_return(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce return URL does not use nonces.
        $order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
        $order    = $order_id ? wc_get_order( $order_id ) : null;

        if ( ! $order ) {
            wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
            exit;
        }

        $invoice_id = $order->get_meta( '_orvexpay_invoice_id' );

        if ( $invoice_id && ! empty( $this->api_key ) ) {
            $api    = new OrvexPay_API( $this->api_key );
            $result = $api->get_invoice( $invoice_id );

            if ( ! is_wp_error( $result ) ) {
                $status = strtolower( $result['status'] ?? '' );

                if ( in_array( $status, [ 'paid', 'confirmed' ], true ) && ! $order->is_paid() ) {
                    $order->payment_complete( $invoice_id );
                    $order->add_order_note( __( 'Payment confirmed via OrvexPay return URL check.', 'orvexpay-payment-gateway' ) );
                }
            }
        }

        wp_safe_redirect( $this->get_return_url( $order ) );
        exit;
    }
}
