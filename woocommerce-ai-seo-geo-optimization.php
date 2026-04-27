<?php
/**
 * Plugin Name: WooCommerce AI SEO & GEO Optimization
 * Plugin URI:  https://github.com/SOYOO974/woo-ai-seo-geo.git
 * Description: Integrates Gemini 3.1 Flash Image API to regenerate product images and perform bulk optimizations.
 * Version: 2.0
 * Author:      Soyoo.re
 * Author URI:  https://www.soyoo.re/
 * Text Domain: wasgo
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/SOYOO974/woo-ai-seo-geo',
	__FILE__,
	'woocommerce-ai-seo-geo-optimization'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
$myUpdateChecker->setAuthentication('WASGO_GITHUB_TOKEN_REDACTED');

define( 'WASGO_VERSION', '2.0' );
define( 'WASGO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WASGO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Initialize Plugin
add_action( 'plugins_loaded', 'wasgo_init_plugin' );

function wasgo_init_plugin() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wasgo_woocommerce_missing_notice' );
        return;
    }

    // Include core classes
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-settings.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-logs.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-image-generator.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-batch-processor.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-ajax.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-admin-menu.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-meta-boxes.php';
    
    // Initialize subclasses
    new WASGO_Settings();
    new WASGO_Logs();
    new WASGO_Image_Generator();
    new WASGO_Batch_Processor();
    new WASGO_AJAX();
    new WASGO_Admin_Menu();
    new WASGO_Meta_Boxes();
}

function wasgo_woocommerce_missing_notice() {
    echo '<div class="error"><p>' . esc_html__( 'WooCommerce AI SEO & GEO Optimization requires WooCommerce to be installed and active.', 'wasgo' ) . '</p></div>';
}
