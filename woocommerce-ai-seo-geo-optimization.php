<?php
/**
 * Plugin Name: WooCommerce AI SEO & GEO Optimization
 * Plugin URI:  https://github.com/SOYOO974/woo-ai-seo-geo.git
 * Description: Multi-provider AI image generation (Magnific Nano Banana Pro, Higgsfield, Gemini Vision) with auto-fallback and bulk SEO content optimizations.
 * Version: 4.5
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

// Optional: Private repo access token defined via wp-config.php constant if needed
if ( defined( 'WASGO_GITHUB_ACCESS_TOKEN' ) && WASGO_GITHUB_ACCESS_TOKEN ) {
    $myUpdateChecker->setAuthentication( WASGO_GITHUB_ACCESS_TOKEN );
}

define( 'WASGO_VERSION', '4.5' );
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
    
    // Include Image AI Providers
    require_once WASGO_PLUGIN_DIR . 'includes/providers/interface-wasgo-image-provider.php';
    require_once WASGO_PLUGIN_DIR . 'includes/providers/class-wasgo-provider-gemini.php';
    require_once WASGO_PLUGIN_DIR . 'includes/providers/class-wasgo-provider-magnific.php';
    require_once WASGO_PLUGIN_DIR . 'includes/providers/class-wasgo-provider-higgsfield.php';
    require_once WASGO_PLUGIN_DIR . 'includes/providers/class-wasgo-image-provider-factory.php';

    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-image-generator.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-batch-processor.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-ajax.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-admin-menu.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-meta-boxes.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-content-utility.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-content-generator.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-content-validator.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-content-orchestrator.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-content-batch-processor.php';
    require_once WASGO_PLUGIN_DIR . 'includes/class-wasgo-content-ajax.php';
    
    // Initialize subclasses
    new WASGO_Settings();
    new WASGO_Logs();
    new WASGO_Image_Generator();
    new WASGO_Batch_Processor();
    new WASGO_AJAX();
    new WASGO_Admin_Menu();
    new WASGO_Meta_Boxes();
    new WASGO_Content_Batch_Processor();
    new WASGO_Content_AJAX();
}

function wasgo_woocommerce_missing_notice() {
    echo '<div class="error"><p>' . esc_html__( 'WooCommerce AI SEO & GEO Optimization requires WooCommerce to be installed and active.', 'wasgo' ) . '</p></div>';
}
