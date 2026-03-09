<?php
/**
 * OrvexPay Webhook Handler.
 *
 * Listens on: /wc-api/orvexpay_webhook
 * Verifies the X-OrvexPay-Signature HMAC-SHA256 and updates order status.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrvexPay_Webhook_Handler {

    public function handle(): void {
        $raw_body  = file_get_contents( 'php://input' );
        $signature = isset( $_SERVER['HTTP_X_ORVEXPAY_SIGNATURE'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ORVEXPAY_SIGNATURE'] ) )
            : '';

        // Load gateway settings to retrieve webhook secret
        $gateway_settings = get_option( 'woocommerce_orvexpay_settings', [] );
        $webhook_secret    = $gateway_settings['webhook_secret'] ?? '';

        // ── Signature verification ─────────────────────────────────────────
        if ( ! empty( $webhook_secret ) ) {
            $expected = hash_hmac( 'sha256', $raw_body, $webhook_secret );
            if ( ! hash_equals( $expected, $signature ) ) {
                $this->respond( 401, [ 'error' => 'Invalid signature' ] );
                return;
            }
        }

        $payload = json_decode( $raw_body, true );
        if ( ! $payload ) {
            $this->respond( 400, [ 'error' => 'Invalid JSON' ] );
            return;
        }

        $event      = $payload['event']     ?? $payload['type']   ?? '';
        $invoice_id = $payload['invoiceId'] ?? $payload['invoice_id'] ?? '';
        $order_id   = $payload['orderId']   ?? $payload['order_id']   ?? '';
        $status     = $payload['status']    ?? '';

        // Find WC order by meta field or order ID
        $wc_order = null;
        if ( $order_id ) {
            $orders = wc_get_orders( [
                'meta_key'   => '_orvexpay_order_id',
                'meta_value' => sanitize_text_field( $order_id ),
                'limit'      => 1,
            ] );
            $wc_order = $orders[0] ?? null;
        }

        if ( ! $wc_order && $invoice_id ) {
            $orders = wc_get_orders( [
                'meta_key'   => '_orvexpay_invoice_id',
                'meta_value' => sanitize_text_field( $invoice_id ),
                'limit'      => 1,
            ] );
            $wc_order = $orders[0] ?? null;
        }

        if ( ! $wc_order ) {
            $this->respond( 200, [ 'status' => 'order_not_found', 'invoice_id' => $invoice_id ] );
            return;
        }

        // ── Map OrvexPay status → WooCommerce order status ─────────────────
        switch ( strtolower( $status ) ) {

            case 'paid':
            case 'confirmed':
                if ( ! $wc_order->is_paid() ) {
                    $wc_order->payment_complete( $invoice_id );
                    $wc_order->add_order_note(
                        sprintf(
                            /* translators: %s OrvexPay invoice ID */
                            __( 'Payment confirmed via OrvexPay. Invoice ID: %s', 'orvexpay-payment-gateway' ),
                            $invoice_id
                        )
                    );
                }
                break;

            case 'partiallypaid':
            case 'partially_paid':
                $wc_order->update_status(
                    'on-hold',
                    __( 'OrvexPay: Partial payment received. Awaiting remaining amount.', 'orvexpay-payment-gateway' )
                );
                break;

            case 'expired':
                if ( $wc_order->get_status() === 'pending' ) {
                    $wc_order->update_status(
                        'cancelled',
                        __( 'OrvexPay: Invoice expired, no payment received.', 'orvexpay-payment-gateway' )
                    );
                }
                break;

            default:
                $wc_order->add_order_note(
                    sprintf(
                        /* translators: %s OrvexPay event name */
                        __( 'OrvexPay webhook received: %s', 'orvexpay-payment-gateway' ),
                        sanitize_text_field( $event ?: $status )
                    )
                );
        }

        $this->respond( 200, [ 'received' => true ] );
    }

    private function respond( int $code, array $body ): void {
        status_header( $code );
        header( 'Content-Type: application/json' );
        echo wp_json_encode( $body );
        exit;
    }
}
