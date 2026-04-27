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
        add_action( 'save_post_product', [ $this, 'enqueue_new_product' ], 10, 3 );
        add_action( 'wasgo_process_single_product', [ $this, 'process_single_product' ], 10, 1 );
        add_action( 'wasgo_process_gallery_item', [ $this, 'process_gallery_item' ], 10, 2 );
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
            update_option( 'wasgo_bulk_status', 'running' );
            as_unschedule_all_actions( 'wasgo_process_image_batch' );
            as_enqueue_async_action( 'wasgo_process_image_batch', [ $force_all, 1 ], 'wasgo-batch' );
        } else {
            update_option( 'wasgo_bulk_status', 'finished' );
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
            $meta_query[] = [
                'key'     => 'prevent_ebp_image_sync',
                'compare' => 'NOT EXISTS',
            ];
            $meta_query[] = [
                'key'     => 'wasgo_api_failed',
                'compare' => 'NOT EXISTS',
            ];
            // Sync with processor logic: skip products in 15-min cooloff
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => 'wasgo_batch_attempt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'     => 'wasgo_batch_attempt',
                    'value'   => time() - 900,
                    'compare' => '<',
                    'type'    => 'NUMERIC'
                ]
            ];
        }

        if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_exclude_outofstock() ) {
            $meta_query[] = [
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '!=',
            ];
        }

        // Must actually HAVE an image source to be count/processed
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS'
            ],
            [
                'key'     => 'original_ebp_image_url',
                'compare' => 'EXISTS'
            ]
        ];

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
        @set_time_limit( 300 ); // High limit for Gemini processing
        
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
            'fields'         => 'ids',
        ];

        $meta_query = [ 'relation' => 'AND' ];

        if ( ! $force_all ) {
            $meta_query[] = [
                'key'     => 'prevent_ebp_image_sync',
                'compare' => 'NOT EXISTS',
            ];
            // Skip products that fail repeatedly in the same batch session
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => 'wasgo_batch_attempt',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'     => 'wasgo_batch_attempt',
                    'value'   => time() - 900, // 15 mins cooloff
                    'compare' => '<',
                    'type'    => 'NUMERIC'
                ]
            ];
        }

        if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_exclude_outofstock() ) {
            $meta_query[] = [
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '!=',
            ];
        }

        // Must actually HAVE an image source to be processed
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS'
            ],
            [
                'key'     => 'original_ebp_image_url',
                'compare' => 'EXISTS'
            ]
        ];

        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        if ( $force_all ) {
            $args['paged'] = $batch_number;
        }

        $products = get_posts( $args );

        if ( empty( $products ) ) {
            update_option( 'wasgo_bulk_status', 'finished' );
            return;
        }

        $processed_count = get_option( 'wasgo_processed_count', 0 );

        foreach ( $products as $product_id ) {
            // Pre-emptively mark attempt to prevent "Loop of Death" if a fatal error occurs
            update_post_meta( $product_id, 'wasgo_batch_attempt', time() );

            // Run processing (Main Image)
            $result = WASGO_Image_Generator::process_product( $product_id, $force_all );
            
            if ( $result !== true && ! $force_all ) {
                update_post_meta( $product_id, 'wasgo_api_failed', current_time( 'mysql' ) );
                WASGO_Logs::log_error( $product_id, "Bulk Process Warning: " . (is_string($result) ? $result : 'Unspecified error') );
            }

            // Spawn Gallery Tasks if enabled (respect force_all)
            if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_enhance_gallery() ) {
                self::enqueue_gallery_tasks( $product_id, $force_all );
            }

            $processed_count++;
        }

        update_option( 'wasgo_processed_count', $processed_count );

        // RESILIENCE: Enqueue the next batch even if something happened.
        // If we found a product, there might be more.
        if ( count( $products ) >= 1 ) {
            if ( function_exists( 'as_enqueue_async_action' ) ) {
                as_enqueue_async_action( 'wasgo_process_image_batch', [ $force_all, $batch_number + 1 ], 'wasgo-batch' );
            }
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

    /**
     * Enqueue a single new product when it is published and auto-process setting is strictly active
     */
    public function enqueue_new_product( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || ! class_exists( 'WASGO_Settings' ) ) {
            return;
        }

        if ( ! WASGO_Settings::should_auto_process_new() ) {
            return;
        }

        // We strictly only process published products
        if ( $post->post_status !== 'publish' ) {
            return;
        }

        // Only explicitly process if this product hasn't successfully generated an image before
        $already_processed = get_post_meta( $post_id, 'prevent_ebp_image_sync', true );
        if ( ! $already_processed ) {
            if ( function_exists( 'as_enqueue_async_action' ) ) {
                as_enqueue_async_action( 'wasgo_process_single_product', [ $post_id ], 'wasgo' );
                
                // Also trigger gallery tasks if enabled (Auto-mode, force = false)
                if ( WASGO_Settings::should_enhance_gallery() ) {
                    self::enqueue_gallery_tasks( $post_id, false );
                }
            }
        }
    }

    /**
     * Action Scheduler designated hook to process a single standalone product
     */
    public function process_single_product( $product_id ) {
        $post_status = get_post_status( $product_id );
        if ( $post_status !== 'publish' ) {
            return; // Fallback abort if unpublished between queue and execution
        }

        if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_exclude_outofstock() ) {
            $stock_status = get_post_meta( $product_id, '_stock_status', true );
            if ( $stock_status === 'outofstock' ) {
                return; // Fallback abort if product became out of stock 
            }
        }

        // Process seamlessly native
        WASGO_Image_Generator::process_product( $product_id, false );

        // If gallery enhancement is enabled, enqueue gallery tasks (Auto-mode, force = false)
        if ( class_exists( 'WASGO_Settings' ) && WASGO_Settings::should_enhance_gallery() ) {
            self::enqueue_gallery_tasks( $product_id, false );
        }
    }

    /**
     * Identify gallery images and spawn individual background tasks for each
     * 
     * @param int  $product_id The product ID.
     * @param bool $force      Whether to re-process images that are already AI-generated.
     */
    public static function enqueue_gallery_tasks( $product_id, $force = false ) {
        $gallery = get_post_meta( $product_id, '_product_image_gallery', true );
        if ( empty( $gallery ) ) {
            return;
        }

        $ids = explode( ',', $gallery );
        $ids = array_filter( array_map( 'absint', $ids ) );

        if ( empty( $ids ) ) {
            return;
        }

        foreach ( $ids as $attachment_id ) {
            // LOOP PROTECTION: Check if this attachment is already AI-generated
            if ( ! $force ) {
                $is_ai = get_post_meta( $attachment_id, '_wasgo_gallery_original_id', true );
                if ( $is_ai ) {
                    continue; // Skip already enhanced images to prevent infinite loops
                }
            }

            if ( function_exists( 'as_enqueue_async_action' ) ) {
                as_enqueue_async_action( 'wasgo_process_gallery_item', [ $product_id, $attachment_id ], 'wasgo-gallery' );
            }
        }
    }

    /**
     * Worker specifically for one single gallery attachment
     */
    public function process_gallery_item( $product_id, $attachment_id ) {
        if ( ! class_exists( 'WASGO_Image_Generator' ) ) {
            return;
        }
        
        WASGO_Image_Generator::process_gallery_image( $product_id, $attachment_id );
    }
}
