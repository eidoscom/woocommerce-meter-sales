<?php
/**
 * Plugin Name: WooCommerce Meter Sales
 * Description: Sell products by linear meter with DTF nesting calculator. Configure film width, fixed cost, and tiered pricing per meter.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: woocommerce-meter-sales
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 * WC tested up to: 9.5
 */

defined( 'ABSPATH' ) || exit;

define( 'WCMS_VERSION', '1.1.0' );
define( 'WCMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'wcms_init' );

function wcms_init() {
    load_plugin_textdomain( 'woocommerce-meter-sales', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e( 'WooCommerce Meter Sales requires WooCommerce to be installed and active.', 'woocommerce-meter-sales' );
            echo '</p></div>';
        } );
        return;
    }

    require_once WCMS_PLUGIN_DIR . 'includes/class-admin.php';
    require_once WCMS_PLUGIN_DIR . 'includes/class-settings.php';
    require_once WCMS_PLUGIN_DIR . 'includes/class-pricing.php';
    require_once WCMS_PLUGIN_DIR . 'includes/class-nesting.php';
    require_once WCMS_PLUGIN_DIR . 'includes/class-frontend.php';
    require_once WCMS_PLUGIN_DIR . 'includes/class-cart.php';
    require_once WCMS_PLUGIN_DIR . 'includes/class-imposition-pdf.php';

    new WCMS_Admin();
    new WCMS_Settings();
    new WCMS_Pricing();
    new WCMS_Nesting();
    new WCMS_Frontend();
    new WCMS_Cart();

    require_once WCMS_PLUGIN_DIR . 'includes/class-divi-integration.php';
    new WCMS_Divi_Integration();
}

add_action( 'wp_enqueue_scripts', 'wcms_register_module_styles' );
function wcms_register_module_styles() {
    wp_register_style(
        'wcms-divi-module-style',
        WCMS_PLUGIN_URL . 'assets/css/divi.css',
        [],
        WCMS_VERSION
    );
}

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );
