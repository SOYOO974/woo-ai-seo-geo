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
        add_action( 'wp_ajax_wasgo_content_search_products', [ $this, 'search_preview_products' ] );
        add_action( 'wp_ajax_wasgo_content_get_preview_data', [ $this, 'get_preview_product_data' ] );
    }

    public function search_preview_products() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 10,
            's'              => $term,
            'post_status'    => 'publish'
        ];

        $query = new WP_Query( $args );
        $results = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $results[] = [
                    'id'    => get_the_ID(),
                    'title' => get_the_title()
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success( $results );
    }

    public function get_preview_product_data() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $pid = isset( $_POST['pid'] ) ? intval( $_POST['pid'] ) : 0;

        if ( ! $pid ) {
            wp_send_json_error( 'Invalid ID' );
        }

        $data = [
            'title' => get_the_title( $pid ),
            'cats'  => WASGO_Content_Utility::get_product_categories_string( $pid ),
            'attrs' => WASGO_Content_Utility::get_product_attributes_string( $pid ),
            'image' => '',
            'lang'  => WASGO_Settings::get_content_language(),
            'req_img' => WASGO_Settings::is_image_required()
        ];

        $img_id = get_post_thumbnail_id( $pid );
        if ( $img_id ) {
            $data['image'] = wp_get_attachment_thumb_url( $img_id );
        }

        wp_send_json_success( $data );
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
            $content = $review_data[$type]['content'];
            WASGO_Content_Orchestrator::save_product_content( $pid, $type, $content );
            $type_label = ucwords( str_replace( ['short', 'long', 'title', 'desc'], ['Short Description', 'Long Description', 'Meta Title', 'Meta Description'], $type ) );
            WASGO_Logs::log_success( $pid, "Manually approved $type_label.", 'content', [ $type_label => $content ] );
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
