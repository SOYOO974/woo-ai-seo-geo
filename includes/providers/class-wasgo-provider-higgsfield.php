<?php
/**
 * Higgsfield AI Image Provider
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Provider_Higgsfield implements WASGO_Image_Provider_Interface {

    public function get_slug(): string {
        return 'higgsfield';
    }

    public function get_name(): string {
        return 'Higgsfield AI';
    }

    public function generate( $source_image_data, $mime_type, $prompt, $context = [] ) {
        $api_key = WASGO_Settings::get_higgsfield_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_api_key', 'Higgsfield API Key is missing in settings.' );
        }

        $model = WASGO_Settings::get_higgsfield_model();
        if ( empty( $model ) ) {
            $model = 'higgsfield-ai/soul';
        }

        $trimmed_key = trim( $api_key );
        $auth_header = ( strpos( $trimmed_key, ':' ) !== false ) ? 'Key ' . $trimmed_key : 'Bearer ' . $trimmed_key;

        $headers = [
            'Authorization' => $auth_header,
            'Content-Type'  => 'application/json',
        ];

        $effective_mime = ! empty( $mime_type ) ? $mime_type : 'image/jpeg';
        $base64_img = 'data:' . $effective_mime . ';base64,' . base64_encode( $source_image_data );

        // 1. Submit Job
        $endpoint = 'https://api.higgsfield.ai/v2/' . ltrim( $model, '/' );
        $payload = [
            'input' => [
                'prompt'       => $prompt,
                'image'        => $base64_img,
                'aspect_ratio' => '1:1',
            ]
        ];

        $post_res = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
            'timeout' => 45
        ] );

        if ( is_wp_error( $post_res ) ) {
            return new WP_Error( 'higgsfield_post_error', 'Higgsfield request error: ' . $post_res->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $post_res );
        $body = wp_remote_retrieve_body( $post_res );
        $data = json_decode( $body, true );

        if ( $code >= 400 || empty( $data ) ) {
            $err_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : ( isset( $data['detail'] ) ? $data['detail'] : "HTTP $code" );
            return new WP_Error( 'higgsfield_api_error', "Higgsfield API Error: $err_msg" );
        }

        // Check if output is returned synchronously
        $out_url = null;
        if ( ! empty( $data['output']['url'] ) ) {
            $out_url = $data['output']['url'];
        } elseif ( ! empty( $data['images'][0]['url'] ) ) {
            $out_url = $data['images'][0]['url'];
        }

        // If asynchronous, poll status endpoint
        if ( ! $out_url ) {
            $request_id = ! empty( $data['id'] ) ? $data['id'] : ( ! empty( $data['request_id'] ) ? $data['request_id'] : '' );
            if ( empty( $request_id ) ) {
                return new WP_Error( 'higgsfield_id_error', 'No request ID returned by Higgsfield: ' . substr( $body, 0, 300 ) );
            }

            $status_url = 'https://api.higgsfield.ai/requests/' . urlencode( $request_id ) . '/status';
            $max_attempts = 20; // 20 x 3s = 60s
            $attempt = 0;

            while ( $attempt < $max_attempts ) {
                sleep( 3 );
                $attempt++;

                $poll_res = wp_remote_get( $status_url, [
                    'headers' => $headers,
                    'timeout' => 20
                ] );

                if ( is_wp_error( $poll_res ) ) {
                    continue;
                }

                $poll_body = wp_remote_retrieve_body( $poll_res );
                $poll_data = json_decode( $poll_body, true );

                $status = isset( $poll_data['status'] ) ? strtolower( $poll_data['status'] ) : '';

                if ( in_array( $status, [ 'completed', 'succeeded', 'done' ], true ) ) {
                    if ( ! empty( $poll_data['output']['url'] ) ) {
                        $out_url = $poll_data['output']['url'];
                    } elseif ( ! empty( $poll_data['result']['images'][0]['url'] ) ) {
                        $out_url = $poll_data['result']['images'][0]['url'];
                    } elseif ( ! empty( $poll_data['images'][0]['url'] ) ) {
                        $out_url = $poll_data['images'][0]['url'];
                    }
                    break;
                } elseif ( in_array( $status, [ 'failed', 'error', 'canceled' ], true ) ) {
                    $err_detail = isset( $poll_data['error'] ) ? ( is_string( $poll_data['error'] ) ? $poll_data['error'] : wp_json_encode( $poll_data['error'] ) ) : 'Task failed.';
                    return new WP_Error( 'higgsfield_task_failed', 'Higgsfield task failed: ' . $err_detail );
                }
            }
        }

        if ( ! $out_url ) {
            return new WP_Error( 'higgsfield_timeout', 'Higgsfield generation timed out or returned no media URL.' );
        }

        // Download result
        $download_res = wp_remote_get( $out_url, [
            'timeout' => 40,
            'headers' => [ 'User-Agent' => 'WASGO-WordPress-Plugin/4.4' ]
        ] );

        if ( is_wp_error( $download_res ) ) {
            return new WP_Error( 'higgsfield_dl_error', 'Could not download media from Higgsfield: ' . $download_res->get_error_message() );
        }

        $binary = wp_remote_retrieve_body( $download_res );
        if ( empty( $binary ) ) {
            return new WP_Error( 'higgsfield_empty_image', 'Downloaded image from Higgsfield is empty.' );
        }

        $ctype = wp_remote_retrieve_header( $download_res, 'content-type' );
        $out_mime = ! empty( $ctype ) ? strtok( $ctype, ';' ) : 'image/jpeg';

        return [
            'base64' => base64_encode( $binary ),
            'mime'   => $out_mime,
            'raw'    => $out_url
        ];
    }
}
