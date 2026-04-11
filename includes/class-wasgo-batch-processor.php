<?php
/**
 * Batch Processor using Action Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Batch_Processor {

    public function __construct() {
        add_action( 'wasgo_process_image_batch', [ $this, 'process_batch' ], 10, 2 );
        add_action( 'wasgo_process_delete_batch', [ $this, 'process_delete_batch' ], 10, 2 );
    }

    /**
     * Start the bulk action
     */
    public static function start_bulk( $force_all, $resume = false ) {
        if ( $resume ) {
            $total = get_option( 'wasgo_total_to_process', 0 );
            $processed = get_option( 'wasgo_processed_count', 0 );
            if ( $total == 0 || $processed >= $total ) {
                $resume = false; // Fallback to fresh start if nothing to resume
            }
        }

        update_option( 'wasgo_bulk_status', 'running' );
        update_option( 'wasgo_force_all', $force_all );
        
        if ( ! $resume ) {
            global $wpdb;
            // Wipe all failed stamps natively so previously failed items get a fresh retry attempt in this new bulk run
            $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'wasgo_api_failed'" );

            update_option( 'wasgo_processed_count', 0 );
            $total = self::get_total_remaining( $force_all );
            update_option( 'wasgo_total_to_process', $total );
        } else {
            // Resume from where we left off
            $total = get_option( 'wasgo_total_to_process', 0 );
        }

        if ( $total > 0 ) {
            as_unschedule_all_actions( 'wasgo_process_image_batch' );
            as_enqueue_async_action( 'wasgo_process_image_batch', [ $force_all, 1 ] );
        }
        
        return $total;
    }

    public static function stop_bulk() {
        update_option( 'wasgo_bulk_status', 'stopped' );
        as_unschedule_all_actions( 'wasgo_process_image_batch' );
    }

    /**
     * Start the bulk deletion
     */
    public static function start_bulk_delete( $resume = false ) {
        if ( $resume ) {
            $total = get_option( 'wasgo_delete_total_to_process', 0 );
            $processed = get_option( 'wasgo_delete_processed_count', 0 );
            if ( $total == 0 || $processed >= $total ) {
                $resume = false; // Fallback to fresh start if nothing to resume
            }
        }

        update_option( 'wasgo_bulk_delete_status', 'running' );
        
        if ( ! $resume ) {
            update_option( 'wasgo_delete_processed_count', 0 );
            $total = self::get_total_backups();
            update_option( 'wasgo_delete_total_to_process', $total );
        } else {
            $total = get_option( 'wasgo_delete_total_to_process', 0 );
        }

        if ( $total > 0 ) {
            as_unschedule_all_actions( 'wasgo_process_delete_batch' );
            as_enqueue_async_action( 'wasgo_process_delete_batch', [ 1 ] );
        }
        
        return $total;
    }

    public static function stop_bulk_delete() {
        update_option( 'wasgo_bulk_delete_status', 'stopped' );
        as_unschedule_all_actions( 'wasgo_process_delete_batch' );
    }

    /**
     * Get count of products needing processing
     */
    public static function get_total_remaining( $force_all = false ) {
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ];

        $meta_query = [ 'relation' => 'AND' ];

        if ( ! $force_all ) {
            // Only products that NOT have 'prevent_ebp_image_sync' AND NOT have 'wasgo_api_failed'
            $meta_query[] = [
                'key'     => 'prevent_ebp_image_sync',
                'compare' => 'NOT EXISTS',
            ];
            $meta_query[] = [
                'key'     => 'wasgo_api_failed',
                'compare' => 'NOT EXISTS',
            ];
        }

        if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_exclude_outofstock() ) {
            $meta_query[] = [
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '!=',
            ];
        }

        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        $products = get_posts( $args );
        return count( $products );
    }

    public static function get_total_backups() {
        global $wpdb;

        // Directly query postmeta to avoid WP_Query complex EXISTS OR relation bugs
        $count = $wpdb->get_var( "
            SELECT COUNT(DISTINCT pm.post_id) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
              AND p.post_status = 'publish'
              AND pm.meta_key IN ('original_wasgo_image_id', 'original_ebp_image_url')
        " );

        return (int) $count;
    }

    /**
     * The hook called by Action Scheduler
     */
    public function process_batch( $force_all, $batch_number ) {
        // Check if we were stopped
        $status = get_option( 'wasgo_bulk_status', 'stopped' );
        if ( $status === 'stopped' ) {
            return;
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ];

        $meta_query = [ 'relation' => 'AND' ];

        if ( ! $force_all ) {
            $meta_query[] = [
                'key'     => 'prevent_ebp_image_sync',
                'compare' => 'NOT EXISTS',
            ];
            $meta_query[] = [
                'key'     => 'wasgo_api_failed',
                'compare' => 'NOT EXISTS',
            ];
        }

        if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_exclude_outofstock() ) {
            $meta_query[] = [
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '!=',
            ];
        }

        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        // Wait, if it's force_all = true, we need to paginate through them using the batch number.
        // If force_all = false, those that are processed will get 'prevent_ebp_image_sync' set 
        // and automatically fall out of the query for the next batch, so we can just grab the first 5 over and over.
        
        if ( $force_all ) {
            $args['paged'] = $batch_number;
        }

        $products = get_posts( $args );

        if ( empty( $products ) ) {
            // Finished!
            update_option( 'wasgo_bulk_status', 'finished' );
            return;
        }

        $processed_count = get_option( 'wasgo_processed_count', 0 );

        foreach ( $products as $product ) {
            // Run processing
            $result = WASGO_Image_Generator::process_product( $product->ID, $force_all );
            if ( $result !== true && ! $force_all ) {
                update_post_meta( $product->ID, 'wasgo_api_failed', current_time( 'mysql' ) );
            }
            $processed_count++;
        }

        update_option( 'wasgo_processed_count', $processed_count );

        if ( count( $products ) == 1 ) {
            as_enqueue_async_action( 'wasgo_process_image_batch', [ $force_all, $batch_number + 1 ] );
        } else {
            update_option( 'wasgo_bulk_status', 'finished' );
        }
    }

    public function process_delete_batch( $batch_number ) {
        $status = get_option( 'wasgo_bulk_delete_status', 'stopped' );
        if ( $status === 'stopped' ) {
            return;
        }

        global $wpdb;
        $product_ids = $wpdb->get_col( "
            SELECT DISTINCT pm.post_id 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
              AND p.post_status = 'publish'
              AND pm.meta_key IN ('original_wasgo_image_id', 'original_ebp_image_url')
            ORDER BY pm.post_id ASC
            LIMIT 5
        " );

        if ( empty( $product_ids ) ) {
            update_option( 'wasgo_bulk_delete_status', 'finished' );
            return;
        }

        $processed_count = get_option( 'wasgo_delete_processed_count', 0 );

        foreach ( $product_ids as $product_id ) {
            $backup_id = get_post_meta( $product_id, 'original_wasgo_image_id', true );
            $legacy_backup_url = get_post_meta( $product_id, 'original_ebp_image_url', true );

            if ( $backup_id ) {
                wp_delete_attachment( $backup_id, true );
            } elseif ( $legacy_backup_url ) {
                $attach_id = attachment_url_to_postid( $legacy_backup_url );
                if ( $attach_id ) {
                    wp_delete_attachment( $attach_id, true );
                }
            }

            delete_post_meta( $product_id, 'original_wasgo_image_id' );
            delete_post_meta( $product_id, 'original_ebp_image_url' );

            $processed_count++;
        }

        update_option( 'wasgo_delete_processed_count', $processed_count );

        if ( count( $product_ids ) == 5 ) {
            as_enqueue_async_action( 'wasgo_process_delete_batch', [ $batch_number + 1 ] );
        } else {
            update_option( 'wasgo_bulk_delete_status', 'finished' );
        }
    }
}
