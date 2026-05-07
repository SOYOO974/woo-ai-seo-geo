<?php
/**
 * Content Batch Processor using Action Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Content_Batch_Processor {

    public function __construct() {
        add_action( 'wasgo_process_content_batch', [ $this, 'process_batch' ], 10, 3 );
        add_action( 'wasgo_process_single_content', [ $this, 'process_single_content' ], 10, 2 );
        add_action( 'save_post_product', [ $this, 'enqueue_auto_process' ], 20, 3 );
    }

    /**
     * Start bulk content generation
     */
    /**
     * Start the bulk process
     */
    public static function start_bulk( $types, $mode, $resume = false ) {
        $old_types = get_option( 'wasgo_content_bulk_types', [] );
        $old_mode  = get_option( 'wasgo_content_bulk_mode', '' );

        // If types or mode changed, we MUST NOT resume. We must start fresh.
        if ( serialize( $types ) !== serialize( $old_types ) || $mode !== $old_mode ) {
            $resume = false;
        }

        update_option( 'wasgo_content_bulk_types', $types );
        update_option( 'wasgo_content_bulk_mode', $mode );

        if ( ! $resume ) {
            update_option( 'wasgo_content_processed', 0 );
            $queue = self::get_eligible_product_ids( $types, $mode );
            update_option( 'wasgo_content_queue', $queue );
            $total = count( $queue );
            update_option( 'wasgo_content_total', $total );
        } else {
            $total = get_option( 'wasgo_content_total', 0 );
            $queue = get_option( 'wasgo_content_queue', [] );
            
            // If the queue is empty but total > 0, it means we actually finished
            if ( empty( $queue ) && $total > 0 ) {
                update_option( 'wasgo_content_bulk_status', 'finished' );
                return $total;
            }
        }

        if ( $total > 0 && ! empty( $queue ) ) {
            update_option( 'wasgo_content_bulk_status', 'running' );
            as_unschedule_all_actions( 'wasgo_process_content_batch' );
            as_enqueue_async_action( 'wasgo_process_content_batch', [ $types, $mode ], 'wasgo-content' );
        } else {
            update_option( 'wasgo_content_bulk_status', 'finished' );
        }

        return $total;
    }

    /**
     * Get list of product IDs needing processing
     */
    public static function get_eligible_product_ids( $types, $mode ) {
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ];

        $meta_query = [ 'relation' => 'AND' ];

        if ( get_option( 'wasgo_content_out_of_stock', 0 ) ) {
            $meta_query[] = [
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '!=',
            ];
        }

        if ( get_option( 'wasgo_content_image_required', 0 ) ) {
            $meta_query[] = [
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS',
            ];
        }

        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        $products = get_posts( $args );
        
        if ( $mode === 'smart' ) {
            $filtered = [];
            foreach ( $products as $pid ) {
                if ( get_post_meta( $pid, '_wasgo_needs_review', true ) ) continue;

                $needs_work = false;
                foreach ( $types as $type ) {
                    $val = '';
                    if ( $type === 'short' ) $val = get_post_field( 'post_excerpt', $pid );
                    elseif ( $type === 'long' ) $val = get_post_field( 'post_content', $pid );
                    elseif ( $type === 'title' ) {
                        $val = get_post_meta( $pid, '_yoast_wpseo_title', true ) ?: 
                               get_post_meta( $pid, 'rank_math_title', true ) ?: 
                               get_post_meta( $pid, '_genesis_title', true );
                    } elseif ( $type === 'desc' ) {
                        $val = get_post_meta( $pid, '_yoast_wpseo_metadesc', true ) ?: 
                               get_post_meta( $pid, 'rank_math_description', true ) ?: 
                               get_post_meta( $pid, '_genesis_description', true );
                    }

                    if ( empty( $val ) ) {
                        $needs_work = true;
                        break;
                    }
                }
                if ( $needs_work ) $filtered[] = $pid;
            }
            return $filtered;
        }

        return $products;
    }

    /**
     * Legacy helper for UI
     */
    public static function get_total_remaining( $types, $mode ) {
        $ids = self::get_eligible_product_ids( $types, $mode );
        return count( $ids );
    }

    public static function stop_bulk() {
        update_option( 'wasgo_content_bulk_status', 'stopped' );
        as_unschedule_all_actions( 'wasgo_process_content_batch' );
    }

    /**
     * Process a batch of products from the queue
     */
    public function process_batch( $types, $mode ) {
        if ( get_option( 'wasgo_content_bulk_status' ) !== 'running' ) {
            return;
        }

        $queue = get_option( 'wasgo_content_queue', [] );

        if ( empty( $queue ) ) {
            update_option( 'wasgo_content_bulk_status', 'finished' );
            return;
        }

        // Get next product from queue
        $target_product = array_shift( $queue );
        update_option( 'wasgo_content_queue', $queue );

        // Process the product (Ignore global disabled toggles for bulk)
        update_post_meta( $target_product, '_wasgo_processing_content', time() );
        WASGO_Content_Orchestrator::process_product( $target_product, $types, true );
        delete_post_meta( $target_product, '_wasgo_processing_content' );

        $processed_count = get_option( 'wasgo_content_processed', 0 );
        update_option( 'wasgo_content_processed', $processed_count + 1 );

        // Schedule next immediately
        as_enqueue_async_action( 'wasgo_process_content_batch', [ $types, $mode ], 'wasgo-content' );
    }

    /**
     * Auto-process hook
     */
    public function enqueue_auto_process( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! get_option( 'wasgo_content_auto_process', 0 ) ) return;
        if ( $post->post_status !== 'publish' ) return;

        // 1. Prevent loop if we are currently mid-process for this product
        if ( get_post_meta( $post_id, '_wasgo_processing_content', true ) ) {
            return;
        }

        // 2. Prevent duplicate scheduling if an action is already pending
        $args = [ $post_id, ['short', 'long', 'title', 'desc'] ];
        if ( as_has_scheduled_action( 'wasgo_process_single_content', $args, 'wasgo-content' ) ) {
            return;
        }

        as_enqueue_async_action( 'wasgo_process_single_content', $args, 'wasgo-content' );
    }

    public function process_single_content( $product_id, $types ) {
        // Set lock
        update_post_meta( $product_id, '_wasgo_processing_content', time() );
        
        WASGO_Content_Orchestrator::process_product( $product_id, $types );
        
        // Release lock
        delete_post_meta( $product_id, '_wasgo_processing_content' );
    }
}
