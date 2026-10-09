<?php
/**
 * Image Provider Factory
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Image_Provider_Factory {

    /**
     * Get list of available image providers
     *
     * @return array [ slug => label ]
     */
    public static function get_providers(): array {
        return [
            'gemini'     => 'Google Gemini Vision (Direct)',
            'magnific'   => 'Magnific AI (Nano Banana Pro / imagen-nano-banana-2)',
            'higgsfield' => 'Higgsfield AI (Studio)',
        ];
    }

    /**
     * Get currently active provider slug
     */
    public static function get_active_provider_slug(): string {
        $slug = WASGO_Settings::get_image_provider();
        $available = array_keys( self::get_providers() );

        if ( in_array( $slug, $available, true ) ) {
            return $slug;
        }

        return 'gemini';
    }

    /**
     * Instantiate and return requested or active provider
     *
     * @param string|null $slug Provider slug or null for active setting
     * @return WASGO_Image_Provider_Interface
     */
    public static function get_provider( $slug = null ): WASGO_Image_Provider_Interface {
        if ( empty( $slug ) ) {
            $slug = self::get_active_provider_slug();
        }

        switch ( $slug ) {
            case 'magnific':
                return new WASGO_Provider_Magnific();

            case 'higgsfield':
                return new WASGO_Provider_Higgsfield();

            case 'gemini':
            default:
                return new WASGO_Provider_Gemini();
        }
    }
}
