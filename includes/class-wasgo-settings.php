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
        register_setting( 'wasgo_api_group', 'wasgo_openai_api_key', [ 'sanitize_callback' => 'sanitize_text_field' ] );

        // Enhance Image Prompts
        register_setting( 'wasgo_prompt_group', 'wasgo_ai_prompt', [ 'sanitize_callback' => 'sanitize_textarea_field' ] );

        // Enhance Settings
        register_setting( 'wasgo_settings_group', 'wasgo_delete_original', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_auto_compress', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_auto_clear_logs', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_exclude_outofstock', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_auto_process_new', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_image_quality', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_max_height', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_settings_group', 'wasgo_enhance_gallery', [ 'sanitize_callback' => 'absint' ] );

        // Content Generation Settings - Atomic Groups
        register_setting( 'wasgo_content_short_group', 'wasgo_content_short_prompt', [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
        register_setting( 'wasgo_content_short_group', 'wasgo_content_short_specs' );

        register_setting( 'wasgo_content_long_group', 'wasgo_content_long_prompt', [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
        register_setting( 'wasgo_content_long_group', 'wasgo_content_long_specs' );

        register_setting( 'wasgo_content_title_group', 'wasgo_content_title_prompt', [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
        register_setting( 'wasgo_content_title_group', 'wasgo_content_title_specs' );

        register_setting( 'wasgo_content_desc_group', 'wasgo_content_desc_prompt', [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
        register_setting( 'wasgo_content_desc_group', 'wasgo_content_desc_specs' );

        // Content Local Settings
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_disable_short', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_disable_long', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_disable_title', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_disable_desc', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_out_of_stock', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_auto_process', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_image_required', [ 'sanitize_callback' => 'absint' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_language', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'wasgo_content_settings_group', 'wasgo_content_include_categories', [ 'sanitize_callback' => 'absint' ] );
    }

    public static function get_api_key() {
        return get_option( 'wasgo_gemini_api_key', '' );
    }

    public static function get_openai_api_key() {
        return get_option( 'wasgo_openai_api_key', '' );
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

    public static function get_image_quality() {
        return (int) get_option( 'wasgo_image_quality', 85 );
    }

    public static function get_max_height() {
        return (int) get_option( 'wasgo_max_height', 1000 );
    }

    public static function should_enhance_gallery() {
        return (bool) get_option( 'wasgo_enhance_gallery', 0 );
    }

    public static function get_content_language() {
        return get_option( 'wasgo_content_language', 'English' );
    }

    public static function should_include_categories() {
        return (bool) get_option( 'wasgo_content_include_categories', 0 );
    }

    public static function is_image_required() {
        return (bool) get_option( 'wasgo_content_image_required', 0 );
    }
}
