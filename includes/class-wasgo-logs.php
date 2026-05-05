<?php
/**
 * Logs Class for storing errors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Logs {

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'wasgo_daily_log_cleanup', [ $this, 'cleanup_old_logs' ] );

        if ( ! wp_next_scheduled( 'wasgo_daily_log_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'wasgo_daily_log_cleanup' );
        }
    }

    public function register_cpt() {
        register_post_type( 'wasgo_log', [
            'public'   => false,
            'show_ui'  => false,
            'supports' => [ 'title' ]
        ] );
    }

    /**
     * Logs an error for a specific product
     */
    public static function log_error( $product_id, $message, $type = 'image' ) {
        $product_title = get_the_title( $product_id );
        
        $post_id = wp_insert_post( [
            'post_title'  => $product_title,
            'post_status' => 'publish',
            'post_type'   => 'wasgo_log'
        ] );
 
        if ( $post_id && ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, 'failed_product_id', $product_id );
            update_post_meta( $post_id, 'error_message', $message );
            update_post_meta( $post_id, '_wasgo_log_type', $type ); // Module
            update_post_meta( $post_id, '_wasgo_log_nature', 'error' );
        }
    }

    /**
     * Logs a success for a specific product
     */
    public static function log_success( $product_id, $message, $type = 'content' ) {
        $product_title = get_the_title( $product_id );
        
        $post_id = wp_insert_post( [
            'post_title'  => $product_title,
            'post_status' => 'publish',
            'post_type'   => 'wasgo_log'
        ] );
 
        if ( $post_id && ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, 'success_product_id', $product_id );
            update_post_meta( $post_id, 'success_message', $message );
            update_post_meta( $post_id, '_wasgo_log_type', $type ); // Module
            update_post_meta( $post_id, '_wasgo_log_nature', 'success' );
        }
    }

    /**
     * Deletes logs older than 30 days if the setting is checked.
     * Hooks into the daily cron wasgo_daily_log_cleanup.
     */
    public function cleanup_old_logs() {
        if ( ! class_exists( 'WASGO_Settings' ) || ! WASGO_Settings::should_auto_clear_logs() ) {
            return;
        }

        global $wpdb;
        $thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        // Fetch up to 1000 old logs at a time to prevent timeout memory limits
        $old_logs = $wpdb->get_col( $wpdb->prepare( "
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'wasgo_log' 
            AND post_date < %s
            LIMIT 1000
        ", $thirty_days_ago ) );

        if ( ! empty( $old_logs ) ) {
            foreach ( $old_logs as $log_id ) {
                wp_delete_post( $log_id, true );
            }
        }
    }
}
