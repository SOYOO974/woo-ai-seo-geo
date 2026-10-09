<?php
/**
 * Magnific AI Image Provider (Nano Banana Pro / imagen-nano-banana-2)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Provider_Magnific implements WASGO_Image_Provider_Interface {

    public function get_slug(): string {
        return 'magnific';
    }

    public function get_name(): string {
        return 'Magnific AI (Nano Banana Pro)';
    }

    public function generate( $source_image_data, $mime_type, $prompt, $context = [] ) {
        $api_key = WASGO_Settings::get_magnific_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_api_key', 'Magnific API Key is missing in settings.' );
        }

        $model = WASGO_Settings::get_magnific_model();
        if ( empty( $model ) ) {
            $model = 'imagen-nano-banana-2';
        }

        $headers = [
            'Authorization' => 'Bearer ' . trim( $api_key ),
            'Content-Type'  => 'application/json',
        ];

        $effective_mime = ! empty( $mime_type ) ? $mime_type : 'image/jpeg';

        // 1. Demande d'upload presigné (creations_request_upload)
        $upload_req_payload = [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'creations_request_upload',
                'arguments' => [ 'mimeType' => $effective_mime ]
            ],
            'id'      => 101
        ];

        $response = wp_remote_post( 'https://mcp.magnific.com', [
            'headers' => $headers,
            'body'    => wp_json_encode( $upload_req_payload ),
            'timeout' => 30
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'magnific_req_upload_error', 'Magnific Request Upload failed: ' . $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $res_json = json_decode( $body, true );

        $raw_upload_text = isset( $res_json['result']['content'][0]['text'] ) ? $res_json['result']['content'][0]['text'] : '';
        $data_up = json_decode( $raw_upload_text, true );

        if ( empty( $data_up['proxyUploadUrl'] ) || empty( $data_up['path'] ) ) {
            return new WP_Error( 'magnific_upload_url_error', 'Could not obtain Magnific presigned upload URL: ' . substr( $body, 0, 300 ) );
        }

        $put_url = $data_up['proxyUploadUrl'];
        $server_path = $data_up['path'];

        // 2. Upload binaire direct par PUT
        $put_response = wp_remote_request( $put_url, [
            'method'  => 'PUT',
            'headers' => [
                'Content-Type'   => $effective_mime,
                'Content-Length' => strlen( $source_image_data )
            ],
            'body'    => $source_image_data,
            'timeout' => 45
        ] );

        if ( is_wp_error( $put_response ) ) {
            return new WP_Error( 'magnific_put_error', 'Magnific PUT binary upload failed: ' . $put_response->get_error_message() );
        }

        $put_code = wp_remote_retrieve_response_code( $put_response );
        if ( $put_code !== 200 && $put_code !== 204 ) {
            return new WP_Error( 'magnific_put_error', "Magnific PUT upload returned HTTP $put_code." );
        }

        // 3. Finalisation de l'upload (creations_finalize_upload)
        $fin_payload = [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'creations_finalize_upload',
                'arguments' => [
                    'path'    => $server_path,
                    'visible' => false
                ]
            ],
            'id'      => 102
        ];

        $fin_response = wp_remote_post( 'https://mcp.magnific.com', [
            'headers' => $headers,
            'body'    => wp_json_encode( $fin_payload ),
            'timeout' => 30
        ] );

        if ( is_wp_error( $fin_response ) ) {
            return new WP_Error( 'magnific_finalize_error', 'Magnific finalize upload failed: ' . $fin_response->get_error_message() );
        }

        $fin_body = wp_remote_retrieve_body( $fin_response );
        $fin_json = json_decode( $fin_body, true );
        $fin_text = isset( $fin_json['result']['content'][0]['text'] ) ? $fin_json['result']['content'][0]['text'] : '';
        $fin_data = json_decode( $fin_text, true );

        $asset_id = isset( $fin_data['identifier'] ) ? $fin_data['identifier'] : '';
        if ( empty( $asset_id ) ) {
            return new WP_Error( 'magnific_asset_error', 'Magnific could not extract uploaded asset identifier: ' . substr( $fin_body, 0, 300 ) );
        }

        // 4. Lancement de la génération (images_generate)
        $gen_payload = [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'images_generate',
                'arguments' => [
                    'mode'        => $model,
                    'aspectRatio' => '1:1',
                    'count'       => 1,
                    'prompt'      => $prompt,
                    'references'  => [
                        [ 'type' => 'image', 'identifier' => $asset_id ]
                    ]
                ]
            ],
            'id'      => 103
        ];

        $gen_response = wp_remote_post( 'https://mcp.magnific.com', [
            'headers' => $headers,
            'body'    => wp_json_encode( $gen_payload ),
            'timeout' => 45
        ] );

        if ( is_wp_error( $gen_response ) ) {
            return new WP_Error( 'magnific_gen_error', 'Magnific images_generate failed: ' . $gen_response->get_error_message() );
        }

        $gen_body = wp_remote_retrieve_body( $gen_response );
        $gen_json = json_decode( $gen_body, true );

        $creation_id = null;
        if ( ! empty( $gen_json['result']['structuredContent']['results'][0]['identifier'] ) ) {
            $creation_id = $gen_json['result']['structuredContent']['results'][0]['identifier'];
        }

        if ( ! $creation_id && ! empty( $gen_json['result']['content'] ) ) {
            foreach ( $gen_json['result']['content'] as $c ) {
                $raw_txt = isset( $c['text'] ) ? $c['text'] : '';
                if ( preg_match( '/`([A-Za-z0-9_-]{8,})`/', $raw_txt, $m ) ) {
                    $creation_id = $m[1];
                    break;
                }
            }
        }

        if ( ! $creation_id ) {
            return new WP_Error( 'magnific_creation_id_error', 'Could not locate creation identifier from Magnific: ' . substr( $gen_body, 0, 300 ) );
        }

        // 5. Attente de la génération (creations_wait, timeout 90s)
        $wait_payload = [
            'jsonrpc' => '2.0',
            'method'  => 'tools/call',
            'params'  => [
                'name'      => 'creations_wait',
                'arguments' => [
                    'identifiers' => [ $creation_id ],
                    'timeout'     => 90
                ]
            ],
            'id'      => 104
        ];

        $wait_response = wp_remote_post( 'https://mcp.magnific.com', [
            'headers' => $headers,
            'body'    => wp_json_encode( $wait_payload ),
            'timeout' => 110
        ] );

        if ( is_wp_error( $wait_response ) ) {
            return new WP_Error( 'magnific_wait_error', 'Magnific creations_wait failed: ' . $wait_response->get_error_message() );
        }

        $wait_body = wp_remote_retrieve_body( $wait_response );
        $wait_json = json_decode( $wait_body, true );

        $out_url = null;
        if ( ! empty( $wait_json['result']['structuredContent']['results'][0]['results']['url'] ) ) {
            $out_url = $wait_json['result']['structuredContent']['results'][0]['results']['url'];
        }

        if ( ! $out_url && ! empty( $wait_json['result']['content'] ) ) {
            foreach ( $wait_json['result']['content'] as $c ) {
                $wtext = isset( $c['text'] ) ? $c['text'] : '';
                $wdata = json_decode( $wtext, true );
                if ( ! empty( $wdata['results'][0]['results']['url'] ) ) {
                    $out_url = $wdata['results'][0]['results']['url'];
                    break;
                }
            }
        }

        if ( ! $out_url ) {
            return new WP_Error( 'magnific_output_url_error', 'Magnific completed task but no output media URL found: ' . substr( $wait_body, 0, 300 ) );
        }

        // 6. Téléchargement de l'image finale
        $download_response = wp_remote_get( $out_url, [
            'timeout' => 45,
            'headers' => [ 'User-Agent' => 'WASGO-WordPress-Plugin/4.4' ]
        ] );

        if ( is_wp_error( $download_response ) ) {
            return new WP_Error( 'magnific_download_error', 'Could not download final image from Magnific: ' . $download_response->get_error_message() );
        }

        $image_binary = wp_remote_retrieve_body( $download_response );
        if ( empty( $image_binary ) ) {
            return new WP_Error( 'magnific_empty_image', 'Downloaded image from Magnific is empty.' );
        }

        $content_type = wp_remote_retrieve_header( $download_response, 'content-type' );
        $out_mime = ! empty( $content_type ) ? strtok( $content_type, ';' ) : 'image/jpeg';

        return [
            'base64' => base64_encode( $image_binary ),
            'mime'   => $out_mime,
            'raw'    => $out_url
        ];
    }
}
