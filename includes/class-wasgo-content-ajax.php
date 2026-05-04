<?php
/**
 * AJAX Handler for Content Generation
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Content_AJAX {

    public function __construct() {
        add_action( 'wp_ajax_wasgo_content_start', [ $this, 'start_bulk' ] );
        add_action( 'wp_ajax_wasgo_content_stop', [ $this, 'stop_bulk' ] );
        add_action( 'wp_ajax_wasgo_content_progress', [ $this, 'get_progress' ] );
        add_action( 'wp_ajax_wasgo_content_review_action', [ $this, 'handle_review_action' ] );
        add_action( 'wp_ajax_wasgo_generate_single_content', [ $this, 'generate_single' ] );
    }

    public function handle_review_action() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );

        $pid    = isset( $_POST['pid'] ) ? intval( $_POST['pid'] ) : 0;
        $type   = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $action = isset( $_POST['review_action'] ) ? sanitize_text_field( $_POST['review_action'] ) : '';

        if ( ! $pid || ! $type || ! $action ) {
            wp_send_json_error( 'Missing parameters.' );
        }

        $review_data = get_post_meta( $pid, '_wasgo_content_review', true );
        if ( ! is_array( $review_data ) || ! isset( $review_data[$type] ) ) {
            wp_send_json_error( 'Content not found in review queue.' );
        }

        if ( $action === 'approve' ) {
            WASGO_Content_Orchestrator::save_product_content( $pid, $type, $review_data[$type]['content'] );
        }

        // Remove from queue
        unset( $review_data[$type] );

        if ( empty( $review_data ) ) {
            delete_post_meta( $pid, '_wasgo_content_review' );
            delete_post_meta( $pid, '_wasgo_needs_review' );
        } else {
            update_post_meta( $pid, '_wasgo_content_review', $review_data );
        }

        wp_send_json_success();
    }

    public function start_bulk() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );

        $types  = isset( $_POST['types'] ) ? array_map( 'sanitize_text_field', $_POST['types'] ) : [];
        $mode   = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'smart';
        $resume = isset( $_POST['resume'] ) && $_POST['resume'] === '1';

        $total = WASGO_Content_Batch_Processor::start_bulk( $types, $mode, $resume );

        wp_send_json_success( [ 'total' => $total ] );
    }

    public function stop_bulk() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        WASGO_Content_Batch_Processor::stop_bulk();
        wp_send_json_success();
    }

    public function get_progress() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );

        $status    = get_option( 'wasgo_content_bulk_status', 'stopped' );
        $processed = get_option( 'wasgo_content_processed', 0 );
        $total     = get_option( 'wasgo_content_total', 0 );

        wp_send_json_success( [
            'status'    => $status,
            'processed' => $processed,
            'total'     => $total
        ] );
    }
    public function generate_single() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );

        $pid   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $types = isset( $_POST['types'] ) ? array_map( 'sanitize_text_field', $_POST['types'] ) : [];

        if ( ! $pid || empty( $types ) ) {
            wp_send_json_error( 'Invalid request.' );
        }

        // Process with $ignore_disabled = true to bypass global settings
        update_post_meta( $pid, '_wasgo_processing_content', time() );
        $result = WASGO_Content_Orchestrator::process_product( $pid, $types, true );
        delete_post_meta( $pid, '_wasgo_processing_content' );

        wp_send_json_success( $result );
    }
}
