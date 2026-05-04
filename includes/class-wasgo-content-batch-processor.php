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
    public static function start_bulk( $types, $mode, $resume = false ) {
        if ( $resume ) {
            $total = get_option( 'wasgo_content_total', 0 );
            $processed = get_option( 'wasgo_content_processed', 0 );
            if ( $total == 0 || $processed >= $total ) {
                $resume = false;
            }
        }

        update_option( 'wasgo_content_bulk_types', $types );
        update_option( 'wasgo_content_bulk_mode', $mode );

        if ( ! $resume ) {
            update_option( 'wasgo_content_processed', 0 );
            $total = self::get_total_remaining( $types, $mode );
            update_option( 'wasgo_content_total', $total );
        } else {
            $total = get_option( 'wasgo_content_total', 0 );
        }

        if ( $total > 0 ) {
            update_option( 'wasgo_content_bulk_status', 'running' );
            as_unschedule_all_actions( 'wasgo_process_content_batch' );
            as_enqueue_async_action( 'wasgo_process_content_batch', [ $types, $mode, 1 ], 'wasgo-content' );
        } else {
            update_option( 'wasgo_content_bulk_status', 'finished' );
        }

        return $total;
    }

    public static function stop_bulk() {
        update_option( 'wasgo_content_bulk_status', 'stopped' );
        as_unschedule_all_actions( 'wasgo_process_content_batch' );
    }

    /**
     * Get count of products needing processing
     */
    public static function get_total_remaining( $types, $mode ) {
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ];

        $meta_query = [ 'relation' => 'AND' ];

        if ( $mode === 'smart' ) {
            // In smart mode, we skip if ALL requested types are already filled
            // This is complex for a meta query, so we'll fetch all and filter in PHP 
            // for absolute accuracy, or just return total products to be safe.
            // For now, let's just filter by out-of-stock and image requirement.
        }

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
        
        // If Smart Mode, we filter products that already have all types filled
        if ( $mode === 'smart' ) {
            $filtered = [];
            foreach ( $products as $pid ) {
                // Skip if product is already in the review queue
                if ( get_post_meta( $pid, '_wasgo_needs_review', true ) ) {
                    continue;
                }

                $needs_work = false;
                foreach ( $types as $type ) {
                    $val = '';
                    if ( $type === 'short' ) $val = get_post_field( 'post_excerpt', $pid );
                    elseif ( $type === 'long' ) $val = get_post_field( 'post_content', $pid );
                    elseif ( $type === 'title' ) {
                        // Check SEO fields
                        $val = get_post_meta( $pid, '_yoast_wpseo_title', true ) ?: get_post_meta( $pid, 'rank_math_title', true );
                    } elseif ( $type === 'desc' ) {
                        $val = get_post_meta( $pid, '_yoast_wpseo_metadesc', true ) ?: get_post_meta( $pid, 'rank_math_description', true );
                    }

                    if ( empty( $val ) ) {
                        $needs_work = true;
                        break;
                    }
                }
                if ( $needs_work ) $filtered[] = $pid;
            }
            return count( $filtered );
        }

        return count( $products );
    }

    /**
     * The hook called by Action Scheduler
     */
    public function process_batch( $types, $mode, $batch_number ) {
        @set_time_limit( 300 );
        
        $status = get_option( 'wasgo_content_bulk_status', 'stopped' );
        if ( $status === 'stopped' ) return;

        // Logic: Fetch the NEXT product that needs work
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ];

        // Same filtering as get_total_remaining
        if ( get_option( 'wasgo_content_out_of_stock', 0 ) ) {
            $args['meta_query'][] = [ 'key' => '_stock_status', 'value' => 'outofstock', 'compare' => '!=' ];
        }
        if ( get_option( 'wasgo_content_image_required', 0 ) ) {
            $args['meta_query'][] = [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ];
        }

        if ( $mode === 'full' ) {
            $args['paged'] = $batch_number;
        } else {
            // Smart mode: we need to find the first product that is missing AT LEAST one type
            // This is harder to do in WP_Query. We'll fetch a small batch and filter.
            $args['posts_per_page'] = 50; 
            $args['paged'] = $batch_number;
        }

        $products = get_posts( $args );

        if ( empty( $products ) ) {
            update_option( 'wasgo_content_bulk_status', 'finished' );
            return;
        }

        $target_product = 0;
        if ( $mode === 'full' ) {
            $target_product = $products[0];
        } else {
            foreach ( $products as $pid ) {
                // Skip if product is already in the review queue
                if ( get_post_meta( $pid, '_wasgo_needs_review', true ) ) {
                    continue;
                }

                foreach ( $types as $type ) {
                    $val = '';
                    if ( $type === 'short' ) $val = get_post_field( 'post_excerpt', $pid );
                    elseif ( $type === 'long' ) $val = get_post_field( 'post_content', $pid );
                    elseif ( $type === 'title' ) $val = get_post_meta( $pid, '_yoast_wpseo_title', true ) ?: get_post_meta( $pid, 'rank_math_title', true );
                    elseif ( $type === 'desc' ) $val = get_post_meta( $pid, '_yoast_wpseo_metadesc', true ) ?: get_post_meta( $pid, 'rank_math_description', true );
                    
                    if ( empty( $val ) ) {
                        $target_product = $pid;
                        break 2;
                    }
                }
            }
        }

        if ( ! $target_product ) {
            // If no product in this page needs work, skip to next page
            as_enqueue_async_action( 'wasgo_process_content_batch', [ $types, $mode, $batch_number + 1 ], 'wasgo-content' );
            return;
        }

        // Process the product (Ignore global disabled toggles for bulk)
        update_post_meta( $target_product, '_wasgo_processing_content', time() );
        $result = WASGO_Content_Orchestrator::process_product( $target_product, $types, true );
        delete_post_meta( $target_product, '_wasgo_processing_content' );

        $processed_count = get_option( 'wasgo_content_processed', 0 );
        update_option( 'wasgo_content_processed', $processed_count + 1 );

        // Continue
        as_enqueue_async_action( 'wasgo_process_content_batch', [ $types, $mode, $batch_number + 1 ], 'wasgo-content' );
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
