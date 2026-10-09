<?php
/**
 * Image Provider Interface
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface WASGO_Image_Provider_Interface {

    /**
     * Get unique slug identifier for the provider (e.g. gemini, magnific, higgsfield)
     *
     * @return string
     */
    public function get_slug(): string;

    /**
     * Get human-readable provider name
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Generate or optimize an image via the AI provider
     *
     * @param string $source_image_data Binary data of the source image
     * @param string $mime_type         Source MIME type (e.g. image/jpeg, image/png)
     * @param string $prompt            Prompt with [PRODUCT_TITLE] substituted
     * @param array  $context           Additional product context (product_id, title, etc.)
     * @return array|WP_Error           [ 'base64' => string, 'mime' => string, 'raw' => string ] or WP_Error
     */
    public function generate( $source_image_data, $mime_type, $prompt, $context = [] );
}
