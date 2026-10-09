<?php
/**
 * Google Gemini Image Provider
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Provider_Gemini implements WASGO_Image_Provider_Interface {

    public function get_slug(): string {
        return 'gemini';
    }

    public function get_name(): string {
        return 'Google Gemini Vision';
    }

    public function generate( $source_image_data, $mime_type, $prompt, $context = [] ) {
        $api_key = WASGO_Settings::get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_api_key', 'Google Gemini API Key is missing in settings.' );
        }

        $model = WASGO_Settings::get_gemini_model();
        if ( empty( $model ) ) {
            $model = 'gemini-3.1-flash-image-preview';
        }

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode( $model ) . ':generateContent';
        $base64_image = base64_encode( $source_image_data );

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ],
                        [
                            'inline_data' => [
                                'mime_type' => $mime_type ?: 'image/jpeg',
                                'data'      => $base64_image
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init( $endpoint );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $api_key
            ],
            CURLOPT_POSTFIELDS     => wp_json_encode( $payload ),
            CURLOPT_TIMEOUT        => 60,
        ] );

        $response_body = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        if ( $response_body === false ) {
            return new WP_Error( 'curl_error', 'Gemini cURL Error: ' . $curl_error );
        }

        if ( $http_code !== 200 ) {
            $err_json = json_decode( $response_body, true );
            $err_msg = isset( $err_json['error']['message'] ) ? $err_json['error']['message'] : "HTTP Code $http_code";
            return new WP_Error( 'api_error', "Gemini API Error ($http_code): " . $err_msg );
        }

        $data = json_decode( $response_body, true );
        $generated_base64 = '';
        $generated_mime = 'image/png';

        if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
            foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
                if ( ! empty( $part['inlineData']['data'] ) ) {
                    $generated_base64 = $part['inlineData']['data'];
                    if ( isset( $part['inlineData']['mimeType'] ) ) {
                        $generated_mime = $part['inlineData']['mimeType'];
                    }
                    break;
                }
                if ( ! empty( $part['inline_data']['data'] ) ) {
                    $generated_base64 = $part['inline_data']['data'];
                    if ( isset( $part['inline_data']['mime_type'] ) ) {
                        $generated_mime = $part['inline_data']['mime_type'];
                    }
                    break;
                }
            }
        }

        if ( empty( $generated_base64 ) ) {
            return new WP_Error( 'no_image_data', 'Gemini API Success but no image data returned. Raw: ' . substr( $response_body, 0, 300 ) );
        }

        return [
            'base64' => $generated_base64,
            'mime'   => $generated_mime,
            'raw'    => $response_body
        ];
    }
}
