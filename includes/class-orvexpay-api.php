<?php
/**
 * OrvexPay REST API client.
 * Wraps wp_remote_post / wp_remote_get with proper auth headers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrvexPay_API {

    private string $api_key;
    private string $base_url;

    public function __construct( string $api_key, string $base_url = ORVEXPAY_API_BASE ) {
        $this->api_key  = $api_key;
        $this->base_url = rtrim( $base_url, '/' );
    }

    /**
     * Create a new invoice (public endpoint — uses API Key header).
     *
     * @param array $payload {
     *   float  $priceAmount
     *   string $priceCurrency   USD|EUR|...
     *   string $payCurrency     USDT_TRC20|BTC|...
     *   string $orderId
     *   string $orderDescription
     *   string $successUrl
     *   string $cancelUrl
     *   string $webhookUrl
     * }
     * @return array|WP_Error
     */
    public function create_invoice( array $payload ) {
        $response = wp_remote_post(
            $this->base_url . '/api/invoice',
            [
                'timeout'     => 30,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'X-API-Key'    => $this->api_key,
                ],
                'body'        => wp_json_encode( $payload ),
                'data_format' => 'body',
            ]
        );

        return $this->parse_response( $response );
    }

    /**
     * Fetch invoice details by ID.
     *
     * @param string $invoice_id
     * @return array|WP_Error
     */
    public function get_invoice( string $invoice_id ) {
        $response = wp_remote_get(
            $this->base_url . '/api/invoice/' . rawurlencode( $invoice_id ),
            [
                'timeout' => 20,
                'headers' => [
                    'X-API-Key' => $this->api_key,
                ],
            ]
        );

        return $this->parse_response( $response );
    }

    /**
     * Parse a WP HTTP response and return the decoded body or WP_Error.
     */
    private function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 ) {
            $message = $data['message'] ?? $body;
            return new WP_Error(
                'orvexpay_api_error',
                sprintf(
                    /* translators: 1: HTTP status code, 2: error message */
                    __( 'OrvexPay API error %1$d: %2$s', 'orvexpay-payment-gateway' ),
                    $code,
                    $message
                )
            );
        }

        return $data;
    }
}
