<?php
/**
 * AJAX Handlers Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_AJAX {

    public function __construct() {
        // Bulk actions
        add_action( 'wp_ajax_wasgo_start_bulk', [ $this, 'ajax_start_bulk' ] );
        add_action( 'wp_ajax_wasgo_stop_bulk', [ $this, 'ajax_stop_bulk' ] );
        add_action( 'wp_ajax_wasgo_get_progress', [ $this, 'ajax_get_progress' ] );
        
        // Bulk Deletions
        add_action( 'wp_ajax_wasgo_start_bulk_delete', [ $this, 'ajax_start_bulk_delete' ] );
        add_action( 'wp_ajax_wasgo_stop_bulk_delete', [ $this, 'ajax_stop_bulk_delete' ] );
        add_action( 'wp_ajax_wasgo_get_delete_progress', [ $this, 'ajax_get_delete_progress' ] );
        
        // Single regen/delete
        add_action( 'wp_ajax_wasgo_force_regen', [ $this, 'ajax_force_regen' ] );
        add_action( 'wp_ajax_wasgo_delete_single_backup', [ $this, 'ajax_delete_single_backup' ] );
    }

    public function ajax_start_bulk() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        
        $force_all = isset( $_POST['force_all'] ) && $_POST['force_all'] === '1' ? true : false;
        $resume = isset( $_POST['resume'] ) && $_POST['resume'] === '1' ? true : false;
        
        $total = WASGO_Batch_Processor::start_bulk( $force_all, $resume );

        if ( $total > 0 ) {
            wp_send_json_success( [ 'message' => "Started processing $total products.", 'total' => $total ] );
        } else {
            wp_send_json_error( [ 'message' => "No products found to process." ] );
        }
    }

    public function ajax_stop_bulk() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        WASGO_Batch_Processor::stop_bulk();
        wp_send_json_success();
    }

    public function ajax_get_progress() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        
        $status = get_option( 'wasgo_bulk_status', 'stopped' );
        $total = (int) get_option( 'wasgo_total_to_process', 0 );
        $processed = (int) get_option( 'wasgo_processed_count', 0 );
        
        // Self-healing: if running but counts are done, mark finished
        if ( $status === 'running' ) {
            if ( $total == 0 || ( $total > 0 && $processed >= $total ) ) {
                $status = 'finished';
                update_option( 'wasgo_bulk_status', 'finished' );
            }
        }

        wp_send_json_success( [
            'status'    => $status,
            'processed' => $processed,
            'total'     => $total
        ] );
    }

    public function ajax_start_bulk_delete() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $resume = isset( $_POST['resume'] ) && $_POST['resume'] === '1' ? true : false;
        
        $total = WASGO_Batch_Processor::start_bulk_delete( $resume );

        if ( $total > 0 ) {
            wp_send_json_success( [ 'message' => "Started deleting backups for $total products.", 'total' => $total ] );
        } else {
            wp_send_json_error( [ 'message' => "No backup images found to delete." ] );
        }
    }

    public function ajax_stop_bulk_delete() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        WASGO_Batch_Processor::stop_bulk_delete();
        wp_send_json_success();
    }

    public function ajax_get_delete_progress() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        
        $status = get_option( 'wasgo_bulk_delete_status', 'stopped' );
        $processed = (int) get_option( 'wasgo_delete_processed_count', 0 );
        $total = (int) get_option( 'wasgo_delete_total_to_process', 0 );
        
        // Self-healing zombie tasks & overflow safeguards
        if ( $status === 'running' ) {
            if ( $total == 0 || ( $total > 0 && $processed >= $total ) ) {
                $status = 'finished';
                update_option( 'wasgo_bulk_delete_status', 'finished' );
            }
        }

        if ( $processed > $total ) {
            $processed = $total; // Clamp UI to 100%
        }

        wp_send_json_success( [
            'status'    => $status,
            'processed' => $processed,
            'total'     => $total
        ] );
    }

    public function ajax_force_regen() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid ID' );
        }

        $result = WASGO_Image_Generator::process_product( $post_id, true );

        if ( $result === true ) {
            // If gallery enhancement is enabled, enqueue gallery tasks (Force-mode, force = true)
            if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_enhance_gallery() ) {
                if ( class_exists( 'WASGO_Batch_Processor' ) ) {
                    WASGO_Batch_Processor::enqueue_gallery_tasks( $post_id, true );
                }
            }
            wp_send_json_success( 'Image updated.' );
        } else {
            wp_send_json_error( $result );
        }
    }

    public function ajax_delete_single_backup() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid ID' );
        }

        $backup_id = get_post_meta( $post_id, 'original_wasgo_image_id', true );
        $legacy_backup_url = get_post_meta( $post_id, 'original_ebp_image_url', true );

        if ( $backup_id ) {
            wp_delete_attachment( $backup_id, true );
        } elseif ( $legacy_backup_url ) {
            $attach_id = attachment_url_to_postid( $legacy_backup_url );
            if ( $attach_id ) {
                wp_delete_attachment( $attach_id, true );
            }
        }
        
        delete_post_meta( $post_id, 'original_wasgo_image_id' );
        delete_post_meta( $post_id, 'original_ebp_image_url' );

        wp_send_json_success();
    }
}
