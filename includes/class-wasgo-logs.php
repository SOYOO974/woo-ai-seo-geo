<?php
/**
 * Logs Management via WooCommerce Logger (WC_Logger)
 * High-performance file-based logging preventing wp_posts and MySQL bloat.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Logs {

    public function __construct() {
        // Daily cleanup for log rotation & legacy DB posts purge
        add_action( 'wasgo_daily_log_cleanup', [ $this, 'cleanup_old_logs' ] );

        if ( ! wp_next_scheduled( 'wasgo_daily_log_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'wasgo_daily_log_cleanup' );
        }
    }

    /**
     * Resolve item title safely distinguishing products and categories
     */
    public static function get_item_title( $item_id ) {
        $post = get_post( $item_id );
        if ( $post && $post->post_type === 'product' ) {
            return ! empty( $post->post_title ) ? $post->post_title : 'Product #' . $item_id;
        }

        $term = get_term( $item_id, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
            return 'Category: ' . $term->name;
        }

        if ( $post && ! empty( $post->post_title ) ) {
            return $post->post_title;
        }

        return 'Item #' . $item_id;
    }

    /**
     * Logs an error for an item via WC_Logger
     */
    public static function log_error( $item_id, $message, $type = 'image' ) {
        $item_title = self::get_item_title( $item_id );
        $source = 'wasgo-' . ( $type === 'content' ? 'content' : 'image' );

        $context = [
            'source'     => $source,
            'item_id'    => $item_id,
            'item_title' => $item_title,
            'nature'     => 'error',
            'type'       => $type,
            'time'       => current_time( 'mysql' ),
        ];

        $log_line = sprintf( '#%d [%s] - %s', $item_id, $item_title, $message );

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->error( $log_line, $context );
        } else {
            error_log( sprintf( '[WASGO ERROR][%s] %s', $source, $log_line ) );
        }
    }

    /**
     * Logs a success for an item via WC_Logger
     */
    public static function log_success( $item_id, $message, $type = 'content', $data = [] ) {
        $item_title = self::get_item_title( $item_id );
        $source = 'wasgo-' . ( $type === 'image' ? 'image' : 'content' );

        $context = [
            'source'     => $source,
            'item_id'    => $item_id,
            'item_title' => $item_title,
            'nature'     => 'success',
            'type'       => $type,
            'time'       => current_time( 'mysql' ),
            'data'       => $data,
        ];

        $log_line = sprintf( '#%d [%s] - %s', $item_id, $item_title, $message );

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info( $log_line, $context );
        } else {
            error_log( sprintf( '[WASGO SUCCESS][%s] %s', $source, $log_line ) );
        }
    }

    /**
     * Retrieve directory where WooCommerce stores log files
     */
    public static function get_log_dir() {
        if ( defined( 'WC_LOG_DIR' ) ) {
            return trailingslashit( WC_LOG_DIR );
        }

        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] ) . 'wc-logs/';
    }

    /**
     * Find all WC log files matching the source handle
     */
    public static function get_log_files( $type = 'image' ) {
        $dir = self::get_log_dir();
        if ( ! is_dir( $dir ) ) {
            return [];
        }

        $source = 'wasgo-' . ( $type === 'content' ? 'content' : 'image' );
        $pattern = $dir . $source . '-*.log';
        $files = glob( $pattern );

        if ( empty( $files ) ) {
            return [];
        }

        // Sort newest first based on file modification time
        usort( $files, function( $a, $b ) {
            return filemtime( $b ) - filemtime( $a );
        } );

        return $files;
    }

    /**
     * Read the last N lines from a file without loading entire large files into memory
     */
    public static function read_last_lines( $file_path, $line_count = 100 ) {
        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            return [];
        }

        $file_size = filesize( $file_path );
        if ( $file_size === 0 ) {
            return [];
        }

        // For small files (< 256 KB), file() is fast and simple
        if ( $file_size < 262144 ) {
            $lines = file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
            return $lines ? array_slice( $lines, -$line_count ) : [];
        }

        // For larger files, seek backward to protect PHP memory limit
        $fp = fopen( $file_path, 'r' );
        if ( ! $fp ) {
            return [];
        }

        $buffer_size = 4096;
        $output = '';
        $pos = $file_size;
        $lines_found = 0;

        while ( $pos > 0 && $lines_found <= $line_count ) {
            $read_size = min( $buffer_size, $pos );
            $pos -= $read_size;
            fseek( $fp, $pos );
            $chunk = fread( $fp, $read_size );
            $output = $chunk . $output;
            $lines_found = substr_count( $output, "\n" );
        }

        fclose( $fp );

        $lines = explode( "\n", trim( $output ) );
        return array_slice( $lines, -$line_count );
    }

    /**
     * Parse recent entries from WC logs for admin UI display
     * 
     * @param string $type   'image' or 'content'
     * @param string $nature 'all', 'error', or 'success'
     * @param int    $limit  Maximum number of entries to return
     * @return array List of structured log items
     */
    public static function get_logs( $type = 'image', $nature = 'all', $limit = 50 ) {
        $files = self::get_log_files( $type );
        if ( empty( $files ) ) {
            return [];
        }

        $parsed_entries = [];

        // Read through newest log files until limit is satisfied
        foreach ( $files as $file ) {
            $lines = self::read_last_lines( $file, max( 150, $limit * 2 ) );
            if ( empty( $lines ) ) {
                continue;
            }

            // Reverse lines so newest entries are parsed first
            $lines = array_reverse( $lines );

            foreach ( $lines as $line ) {
                $entry = self::parse_log_line( $line );
                if ( ! $entry ) {
                    continue;
                }

                if ( $nature !== 'all' && $entry['nature'] !== $nature ) {
                    continue;
                }

                $parsed_entries[] = $entry;

                if ( count( $parsed_entries ) >= $limit ) {
                    break 2;
                }
            }
        }

        return $parsed_entries;
    }

    /**
     * Parse a single WC log entry line into structured data
     */
    private static function parse_log_line( $line ) {
        $line = trim( $line );
        if ( empty( $line ) ) {
            return null;
        }

        // Pattern: YYYY-MM-DDTHH:MM:SS+00:00 LEVEL Message {JSON context}
        // Note: in WC logs, timestamp format can be ISO8601
        $pattern = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})\s+([A-Z]+)\s+(.*?)(?:\s+(\{.*\}))?$/s';
        if ( ! preg_match( $pattern, $line, $matches ) ) {
            return null;
        }

        $raw_time  = $matches[1];
        $level     = strtolower( $matches[2] );
        $raw_msg   = trim( $matches[3] );
        $json_str  = ! empty( $matches[4] ) ? $matches[4] : '';

        $context = [];
        if ( ! empty( $json_str ) ) {
            $decoded = json_decode( $json_str, true );
            if ( is_array( $decoded ) ) {
                $context = $decoded;
            }
        }

        $item_id = isset( $context['item_id'] ) ? intval( $context['item_id'] ) : 0;
        $title   = isset( $context['item_title'] ) ? $context['item_title'] : '';
        $nature  = isset( $context['nature'] ) ? $context['nature'] : ( $level === 'info' ? 'success' : 'error' );
        $data    = isset( $context['data'] ) && is_array( $context['data'] ) ? $context['data'] : [];
        $message = $raw_msg;

        // If product ID / title weren't in context JSON, parse from "#123 [Title] - Message"
        if ( preg_match( '/^#(\d+)\s+\[(.*?)\]\s+-\s+(.*)$/s', $raw_msg, $msg_matches ) ) {
            if ( ! $item_id ) {
                $item_id = intval( $msg_matches[1] );
            }
            if ( empty( $title ) ) {
                $title = $msg_matches[2];
            }
            $message = $msg_matches[3];
        }

        // Format user-friendly date
        $timestamp = strtotime( $raw_time );
        $formatted_date = $timestamp ? date_i18n( 'Y-m-d H:i:s', $timestamp ) : $raw_time;

        return [
            'raw_time'  => $raw_time,
            'date'      => $formatted_date,
            'level'     => $level,
            'nature'    => $nature,
            'item_id'   => $item_id,
            'title'     => $title ?: ( $item_id ? 'Item #' . $item_id : 'General' ),
            'message'   => $message,
            'data'      => $data,
        ];
    }

    /**
     * Clear WC log files for a specific type
     */
    public static function clear_logs( $type = 'image' ) {
        $files = self::get_log_files( $type );
        foreach ( $files as $file ) {
            if ( is_file( $file ) && is_writable( $file ) ) {
                @unlink( $file );
            }
        }
    }

    /**
     * Count legacy wasgo_log CPT posts still present in wp_posts
     */
    public static function get_legacy_posts_count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'wasgo_log'" );
    }

    /**
     * Fast bulk purge of legacy wasgo_log CPT posts and their postmeta
     */
    public static function purge_legacy_posts( $limit = 1000 ) {
        global $wpdb;
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wasgo_log' LIMIT %d", $limit ) );
        if ( ! empty( $ids ) ) {
            $id_list = implode( ',', array_map( 'absint', $ids ) );
            $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$id_list})" );
            $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$id_list})" );
            return count( $ids );
        }
        return 0;
    }

    /**
     * URL linking to WooCommerce native Status > Logs tab
     */
    public static function get_wc_logs_url( $type = 'image' ) {
        $source = 'wasgo-' . ( $type === 'content' ? 'content' : 'image' );
        $files = self::get_log_files( $type );
        $file_key = '';

        if ( ! empty( $files ) ) {
            $file_key = basename( $files[0] );
        }

        $url = admin_url( 'admin.php?page=wc-status&tab=logs' );
        if ( ! empty( $file_key ) ) {
            $url = add_query_arg( 'log_file', $file_key, $url );
        }

        return $url;
    }

    /**
     * Daily cleanup: cleans up legacy DB posts if enabled
     */
    public function cleanup_old_logs() {
        // Purge legacy DB posts in batches
        if ( self::get_legacy_posts_count() > 0 ) {
            self::purge_legacy_posts( 1000 );
        }
    }
}
