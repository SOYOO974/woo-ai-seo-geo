<?php
/**
 * Plugin Settings Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Settings {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        // General API Settings
        register_setting( 'wasgo_api_group', 'wasgo_gemini_api_key', [ 'sanitize_callback' => 'sanitize_text_field' ] );

        // Enhance Image Prompts
        register_setting( 'wasgo_prompt_group', 'wasgo_ai_prompt', [ 'sanitize_callback' => 'sanitize_textarea_field' ] );

        // Enhance Settings
        register_setting( 'wasgo_settings_group', 'wasgo_delete_original', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_auto_compress', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_auto_clear_logs', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_exclude_outofstock', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_auto_process_new', [ 'sanitize_callback' => 'absint' ] );
    }

    public static function get_api_key() {
        return get_option( 'wasgo_gemini_api_key', '' );
    }

    public static function get_prompt() {
        return get_option( 'wasgo_ai_prompt', '' );
    }

    public static function should_delete_original() {
        return (bool) get_option( 'wasgo_delete_original', 0 );
    }

    public static function should_auto_compress() {
        return (bool) get_option( 'wasgo_auto_compress', 0 ); // Default is not set, meaning they have to opt-in
    }

    public static function should_auto_clear_logs() {
        return (bool) get_option( 'wasgo_auto_clear_logs', 0 );
    }

    public static function should_exclude_outofstock() {
        return (bool) get_option( 'wasgo_exclude_outofstock', 0 );
    }

    public static function should_auto_process_new() {
        return (bool) get_option( 'wasgo_auto_process_new', 0 );
    }
}
