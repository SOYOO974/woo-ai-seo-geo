<?php
/**
 * Image Generator Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Image_Generator {

    /**
     * Core AI processing function
     */
    public static function process_product( $product_id, $force = false ) {
        
        $api_key = WASGO_Settings::get_api_key();
        if ( empty( $api_key ) ) {
            WASGO_Logs::log_error( $product_id, "API Key is missing in settings." );
            return "API Key missing.";
        }

        $prompt_template = WASGO_Settings::get_prompt();
        if ( empty( $prompt_template ) ) {
            WASGO_Logs::log_error( $product_id, "Prompt is empty in settings." );
            return "Prompt settings empty.";
        }

        $is_disabled = get_post_meta( $product_id, '_wasgo_disable_ai_gen', true );
        $is_generated = get_post_meta( $product_id, 'prevent_ebp_image_sync', true );

        if ( ! $force ) {
            if ( $is_disabled ) return "AI Disabled for this product.";
            if ( $is_generated ) return "Image already AI generated.";
        }

        $current_image_id = get_post_thumbnail_id( $product_id );
        if ( ! $current_image_id ) {
            WASGO_Logs::log_error( $product_id, "No main image found to process." );
            return "No main image found.";
        }

        // --- Backups & Source Definition ---
        $existing_backup_id = get_post_meta( $product_id, 'original_wasgo_image_id', true );
        $legacy_backup_url = get_post_meta( $product_id, 'original_ebp_image_url', true );

        $source_image_id = 0;
        $source_image_url = '';

        if ( $existing_backup_id ) {
            // Backup exists as an ID
            $source_image_id = $existing_backup_id;
            $source_image_url = wp_get_attachment_url( $source_image_id );
        } elseif ( $legacy_backup_url ) {
            // Legacy URL backup exists
            $source_image_id = attachment_url_to_postid( $legacy_backup_url ); // could be 0
            $source_image_url = $legacy_backup_url;
        } else {
            // No backup exists. Current image is the source.
            $source_image_id = $current_image_id;
            $source_image_url = wp_get_attachment_url( $current_image_id );
            
            // Should we save it as a backup?
            if ( ! WASGO_Settings::should_delete_original() ) {
                update_post_meta( $product_id, 'original_wasgo_image_id', $current_image_id );
            }
        }

        if ( $source_image_id ) {
            $image_path = get_attached_file( $source_image_id );
            if ( $image_path && file_exists( $image_path ) ) {
                $image_data_raw = @file_get_contents( $image_path );
            } else {
                $image_data_raw = @file_get_contents( $source_image_url );
            }
            $mime_type = get_post_mime_type( $source_image_id ) ?: 'image/jpeg';
        } else {
            // Fallback for legacy URL that couldn't map to an ID
            $image_data_raw = @file_get_contents( $source_image_url );
            $mime_type = 'image/jpeg';
        }

        if ( ! $image_data_raw ) {
            WASGO_Logs::log_error( $product_id, "Could not read source image data." );
            return "Could not read source image.";
        }

        $base64_image = base64_encode( $image_data_raw );

        $product_title = get_the_title( $product_id );
        $final_prompt = str_replace( '[PRODUCT_TITLE]', $product_title, $prompt_template );
        
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-image-preview:generateContent';

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $final_prompt ],
                        [
                            'inline_data' => [
                                'mime_type' => $mime_type,
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
        $curl_error = curl_error( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $response_body === false ) {
            WASGO_Logs::log_error( $product_id, "cURL Error: " . $curl_error );
            return "Network Error: " . $curl_error;
        }

        if ( $http_code !== 200 ) {
            $err_json = json_decode( $response_body, true );
            $err_msg = isset( $err_json['error']['message'] ) ? $err_json['error']['message'] : "HTTP Code $http_code";
            WASGO_Logs::log_error( $product_id, "API Error ($http_code): " . $err_msg );
            return "API Error: " . $err_msg;
        }

        $data = json_decode( $response_body, true );
        $generated_base64 = '';
        $generated_mime = 'image/png'; 

        if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
            foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
                if ( ! empty( $part['inlineData']['data'] ) ) {
                    $generated_base64 = $part['inlineData']['data'];
                    if( isset( $part['inlineData']['mimeType'] ) ) $generated_mime = $part['inlineData']['mimeType'];
                    break;
                }
                if ( ! empty( $part['inline_data']['data'] ) ) {
                    $generated_base64 = $part['inline_data']['data'];
                    if( isset( $part['inline_data']['mime_type'] ) ) $generated_mime = $part['inline_data']['mime_type'];
                    break;
                }
            }
        }

        if ( empty( $generated_base64 ) ) {
            WASGO_Logs::log_error( $product_id, "API Success but no image data. Raw: " . substr( $response_body, 0, 300 ) );
            return "API did not return an image.";
        }

        $decoded_image = base64_decode( $generated_base64 );
        
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $tmp_file = wp_tempnam( 'ai_gen_' );
        file_put_contents( $tmp_file, $decoded_image );
        
        $editor = wp_get_image_editor( $tmp_file );

        $safe_title = sanitize_title_with_dashes( remove_accents( $product_title ) );
        $safe_title = preg_replace( '/[^a-z0-9\-]/', '', $safe_title ); 
        $safe_title = trim( $safe_title, '-' );
        if ( empty( $safe_title ) ) { $safe_title = 'product'; }
        
        $auto_compress = WASGO_Settings::should_auto_compress();

        if ( ! is_wp_error( $editor ) && $auto_compress ) {
            $editor->set_quality( 85 );
            $filename = 'ai-gen-' . $safe_title . '-' . time() . '.webp';
            $upload_dir = wp_upload_dir();
            $dest_path = $upload_dir['path'] . '/' . $filename;

            $saved = $editor->save( $dest_path, 'image/webp' );
            unlink( $tmp_file );

            if ( is_wp_error( $saved ) ) {
                WASGO_Logs::log_error( $product_id, "WebP Conversion Error: " . $saved->get_error_message() );
                return "Conversion error.";
            }

            $file_path = $saved['path'];
            $generated_mime = 'image/webp';
        } else {
            $ext = ( strpos( $generated_mime, 'jpeg' ) !== false ) ? 'jpg' : 'png';
            $filename = 'ai-gen-' . $safe_title . '-' . time() . '.' . $ext;
            $upload = wp_upload_bits( $filename, null, $decoded_image );
            $file_path = $upload['file'];
            unlink( $tmp_file );
            
            if ( $upload['error'] ) {
                WASGO_Logs::log_error( $product_id, "WP Upload Error: " . $upload['error'] );
                return "File save error.";
            }
        }

        $attachment = [
            'post_mime_type' => $generated_mime,
            'post_title'     => $product_title . ' (AI Enhanced)',
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attach_id = wp_insert_attachment( $attachment, $file_path, $product_id );
        
        if ( is_wp_error( $attach_id ) ) {
            WASGO_Logs::log_error( $product_id, "DB Error: " . $attach_id->get_error_message() );
            return "Attachment DB error.";
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        // Replace thumbnail
        set_post_thumbnail( $product_id, $attach_id );
        update_post_meta( $product_id, 'prevent_ebp_image_sync', '1' );

        // Clean up previous images safely
        if ( $backup_id || $legacy_backup_url ) {
            // This is a re-run! The old thumbnail was a previously generated AI outcome.
            // We MUST delete it natively so it doesn't leave an orphaned image filling up the server.
            if ( $current_image_id && $current_image_id != $backup_id ) {
                wp_delete_attachment( $current_image_id, true );
            }
        } elseif ( WASGO_Settings::should_delete_original() ) {
            // Fresh run where user strictly demanded original deletion
            wp_delete_attachment( $current_image_id, true );
        }

        return true;
    }
}
