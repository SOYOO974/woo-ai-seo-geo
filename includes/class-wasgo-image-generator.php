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
        $this_is_rerun     = ( $existing_backup_id || $legacy_backup_url );

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

        $product_title = get_the_title( $product_id );
        $final_prompt = str_replace( '[PRODUCT_TITLE]', $product_title, $prompt_template );
        
        $data = self::generate_image_with_provider_and_fallback(
            $image_data_raw,
            $mime_type,
            $final_prompt,
            [ 'product_id' => $product_id, 'title' => $product_title ]
        );

        if ( is_wp_error( $data ) ) {
            WASGO_Logs::log_error( $product_id, $data->get_error_message() );
            return $data->get_error_message();
        }

        $generated_base64 = $data['base64'];
        $generated_mime = $data['mime'];
        $response_body = $data['raw']; 

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
            // Check for height constraints
            $max_height = WASGO_Settings::get_max_height();
            $size = $editor->get_size();
            if ( $size && isset( $size['height'] ) && $size['height'] > $max_height ) {
                $editor->resize( 99999, $max_height, false );
            }

            $editor->set_quality( WASGO_Settings::get_image_quality() );
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
        if ( $this_is_rerun ) {
            if ( $current_image_id && $current_image_id != $source_image_id ) {
                wp_delete_attachment( $current_image_id, true );
            }
        } elseif ( WASGO_Settings::should_delete_original() ) {
            if ( $source_image_id ) {
                wp_delete_attachment( $source_image_id, true );
            }
        }

        $provider_label = ! empty( $data['provider_name'] ) ? $data['provider_name'] : 'AI';
        WASGO_Logs::log_success( $product_id, "Image successfully generated and optimized via {$provider_label} (Attachment #$attach_id).", 'image', [ 'attachment_id' => $attach_id, 'file' => basename( $file_path ), 'provider' => $provider_label ] );

        return true;
    }

    /**
     * Process a single gallery attachment in its own background task
     */
    public static function process_gallery_image( $product_id, $attachment_id ) {
        // Validate product existence first
        if ( ! get_post( $product_id ) ) {
            return;
        }

        $prompt_template = WASGO_Settings::get_prompt();
        if ( empty( $prompt_template ) ) {
            WASGO_Logs::log_error( $product_id, "Gallery Process Aborted: Prompt missing." );
            return;
        }

        // --- Backup Detection ---
        $original_source_id = get_post_meta( $attachment_id, '_wasgo_gallery_original_id', true );
        
        $source_id = $attachment_id;
        $is_rerun = false;

        if ( $original_source_id ) {
            $source_id = (int) $original_source_id;
            $is_rerun = true;
        }

        $image_path = get_attached_file( $source_id );
        if ( ! $image_path || ! file_exists( $image_path ) ) {
            // Check if it's a URL fallback (less common for gallery but possible)
            $source_url = wp_get_attachment_url( $source_id );
            $image_data_raw = @file_get_contents( $source_url );
            if ( ! $image_data_raw ) {
                WASGO_Logs::log_error( $product_id, "Gallery Error: Source image file not found for ID $source_id." );
                return;
            }
        } else {
            $image_data_raw = @file_get_contents( $image_path );
        }

        if ( ! $image_data_raw ) {
            WASGO_Logs::log_error( $product_id, "Gallery Error: Could not read data from attachment $source_id." );
            return;
        }

        $mime_type = get_post_mime_type( $source_id ) ?: 'image/jpeg';

        $product_title = get_the_title( $product_id );
        $final_prompt = str_replace( '[PRODUCT_TITLE]', $product_title, $prompt_template );

        $data = self::generate_image_with_provider_and_fallback(
            $image_data_raw,
            $mime_type,
            $final_prompt,
            [ 'product_id' => $product_id, 'attachment_id' => $attachment_id, 'title' => $product_title ]
        );

        if ( is_wp_error( $data ) ) {
            WASGO_Logs::log_error( $product_id, "Gallery API Error: " . $data->get_error_message() );
            return;
        }

        $decoded_image = base64_decode( $data['base64'] );
        $generated_mime = $data['mime'];

        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $tmp_file = wp_tempnam( 'ai_gal_' );
        file_put_contents( $tmp_file, $decoded_image );

        $editor = wp_get_image_editor( $tmp_file );
        
        // Sanitize for file name
        $safe_title = sanitize_title_with_dashes( remove_accents( $product_title ) );
        $safe_title = preg_replace( '/[^a-z0-9\-]/', '', $safe_title ); 
        $safe_title = trim( $safe_title, '-' );
        if ( empty( $safe_title ) ) { $safe_title = 'gallery'; }
        $filename_base = 'ai-gen-' . $safe_title . '-gal-' . time();

        if ( ! is_wp_error( $editor ) && WASGO_Settings::should_auto_compress() ) {
            $max_height = WASGO_Settings::get_max_height();
            $size = $editor->get_size();
            if ( $size && isset( $size['height'] ) && $size['height'] > $max_height ) {
                $editor->resize( 99999, $max_height, false );
            }
            $editor->set_quality( WASGO_Settings::get_image_quality() );
            $filename = $filename_base . '.webp';
            $upload_dir = wp_upload_dir();
            $dest_path = $upload_dir['path'] . '/' . $filename;
            $saved = $editor->save( $dest_path, 'image/webp' );
            unlink( $tmp_file );
            if ( is_wp_error( $saved ) ) {
                WASGO_Logs::log_error( $product_id, "Gallery Conversion Error: " . $saved->get_error_message() );
                return;
            }
            $file_path = $saved['path'];
            $generated_mime = 'image/webp';
        } else {
            $ext = ( strpos( $generated_mime, 'jpeg' ) !== false ) ? 'jpg' : 'png';
            $filename = $filename_base . '.' . $ext;
            $upload = wp_upload_bits( $filename, null, $decoded_image );
            $file_path = $upload['file'];
            unlink( $tmp_file );
            if ( $upload['error'] ) {
                WASGO_Logs::log_error( $product_id, "Gallery Upload Error: " . $upload['error'] );
                return;
            }
        }

        $attachment = [
            'post_mime_type' => $generated_mime,
            'post_title'     => $product_title . ' Gallery (AI Enhanced)',
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $new_attach_id = wp_insert_attachment( $attachment, $file_path, $product_id );
        if ( is_wp_error( $new_attach_id ) ) {
            WASGO_Logs::log_error( $product_id, "Gallery DB Error: " . $new_attach_id->get_error_message() );
            return;
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $new_attach_id, $file_path );
        wp_update_attachment_metadata( $new_attach_id, $attach_data );

        // Track the original source so we can re-run safely
        update_post_meta( $new_attach_id, '_wasgo_gallery_original_id', $source_id );

        // ATOMIC SWAP: Update the product gallery list
        // We fetch the LATEST meta value here to reduce race condition risks
        $gallery = get_post_meta( $product_id, '_product_image_gallery', true );
        if ( $gallery ) {
            $ids = explode( ',', $gallery );
            $found = false;
            foreach ( $ids as $key => $val ) {
                if ( (int)$val === (int)$attachment_id ) {
                    $ids[$key] = $new_attach_id;
                    $found = true;
                    break;
                }
            }
            
            if ( $found ) {
                update_post_meta( $product_id, '_product_image_gallery', implode( ',', $ids ) );
            } else {
                // If the specific ID was lost/moved, we still add the new one to the gallery to be safe
                $ids[] = $new_attach_id;
                update_post_meta( $product_id, '_product_image_gallery', implode( ',', $ids ) );
            }
        } else {
            // Case where gallery was emptied during processing
            update_post_meta( $product_id, '_product_image_gallery', $new_attach_id );
        }

        // CLEANUP
        if ( $is_rerun ) {
            // Old attachment was AI gen. Delete it natively to save space.
            if ( (int)$attachment_id !== (int)$source_id ) {
                wp_delete_attachment( $attachment_id, true );
            }
        } elseif ( WASGO_Settings::should_delete_original() ) {
            // New run, user strictly hates originals. Delete it.
            wp_delete_attachment( $source_id, true );
        }

        $provider_label = ! empty( $data['provider_name'] ) ? $data['provider_name'] : 'AI';
        WASGO_Logs::log_success( $product_id, "Gallery image successfully optimized via {$provider_label} (Attachment #$new_attach_id).", 'image', [ 'attachment_id' => $new_attach_id, 'file' => basename( $file_path ), 'provider' => $provider_label ] );
    }

    /**
     * Dispatch image generation to configured provider with automatic Gemini fallback
     *
     * @param string $image_data_raw Raw binary source image
     * @param string $mime_type      MIME type (image/jpeg, etc.)
     * @param string $final_prompt   Substituted prompt string
     * @param array  $context        Context array (product_id, title, etc.)
     * @return array|WP_Error        [ 'base64' => string, 'mime' => string, 'raw' => string, 'provider_name' => string ] or WP_Error
     */
    public static function generate_image_with_provider_and_fallback( $image_data_raw, $mime_type, $final_prompt, $context = [] ) {
        $active_slug = WASGO_Image_Provider_Factory::get_active_provider_slug();
        $primary_provider = WASGO_Image_Provider_Factory::get_provider( $active_slug );

        $result = $primary_provider->generate( $image_data_raw, $mime_type, $final_prompt, $context );

        if ( ! is_wp_error( $result ) ) {
            $result['provider_name'] = $primary_provider->get_name();
            return $result;
        }

        // Check if fallback to Gemini is eligible
        $can_fallback = ( $active_slug !== 'gemini' ) && WASGO_Settings::should_fallback_to_gemini();

        if ( $can_fallback ) {
            $gemini_key = WASGO_Settings::get_api_key();
            if ( ! empty( $gemini_key ) ) {
                $pid = ! empty( $context['product_id'] ) ? $context['product_id'] : 0;
                WASGO_Logs::log(
                    sprintf(
                        "Primary image provider '%s' failed: %s. Initiating automatic fallback to Google Gemini Vision...",
                        $primary_provider->get_name(),
                        $result->get_error_message()
                    ),
                    'warning',
                    [ 'product_id' => $pid, 'primary_error' => $result->get_error_message() ]
                );

                $gemini_provider = WASGO_Image_Provider_Factory::get_provider( 'gemini' );
                $fallback_res = $gemini_provider->generate( $image_data_raw, $mime_type, $final_prompt, $context );

                if ( ! is_wp_error( $fallback_res ) ) {
                    $fallback_res['provider_name'] = 'Google Gemini Vision (Fallback)';
                    $fallback_res['fallback_used'] = true;
                    return $fallback_res;
                }

                return new WP_Error(
                    'all_providers_failed',
                    sprintf(
                        "[%s Failed]: %s | [Gemini Fallback Failed]: %s",
                        $primary_provider->get_name(),
                        $result->get_error_message(),
                        $fallback_res->get_error_message()
                    )
                );
            }
        }

        return $result;
    }

    /**
     * Backward compatible helper to negotiate with Gemini API (delegates to WASGO_Provider_Gemini)
     */
    private static function get_ai_image_from_gemini( $api_key, $mime_type, $base64_image, $final_prompt ) {
        $gemini = new WASGO_Provider_Gemini();
        return $gemini->generate( base64_decode( $base64_image ), $mime_type, $final_prompt );
    }
}
