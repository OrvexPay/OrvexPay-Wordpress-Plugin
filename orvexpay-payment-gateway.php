<?php
/**
 * Plugin Name:       OrvexPay Payment Gateway
 * Plugin URI:        https://orvexpay.com/developers
 * Description:       Accept Bitcoin, Ethereum, USDT, USDC and 50+ cryptocurrencies in your WooCommerce store via OrvexPay — zero chargebacks, instant settlement.
 * Version:           1.0.0
 * Author:            OrvexPay
 * Author URI:        https://orvexpay.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       orvexpay-payment-gateway
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   8.9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ORVEXPAY_VERSION', '1.0.0' );
define( 'ORVEXPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORVEXPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ORVEXPAY_API_BASE', 'https://api.orvexpay.com' );

// ── Declare WooCommerce HPOS compatibility ─────────────────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

// ── Bootstrap ──────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'orvexpay_init_gateway', 11 );

function orvexpay_init_gateway() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'OrvexPay requires WooCommerce to be installed and active.', 'orvexpay-payment-gateway' )
                . '</p></div>';
        } );
        return;
    }

    require_once ORVEXPAY_PLUGIN_DIR . 'includes/class-orvexpay-api.php';
    require_once ORVEXPAY_PLUGIN_DIR . 'includes/class-orvexpay-gateway.php';
    require_once ORVEXPAY_PLUGIN_DIR . 'includes/class-orvexpay-webhook-handler.php';

    // Register gateway
    add_filter( 'woocommerce_payment_gateways', 'orvexpay_register_gateway' );

    // Register webhook endpoint: /orvexpay-webhook
    $handler = new OrvexPay_Webhook_Handler();
    add_action( 'woocommerce_api_orvexpay_webhook', [ $handler, 'handle' ] );
}

function orvexpay_register_gateway( $gateways ) {
    $gateways[] = 'OrvexPay_Gateway';
    return $gateways;
}

// ── Plugin activation: flush rewrite rules ────────────────────────────────
register_activation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
