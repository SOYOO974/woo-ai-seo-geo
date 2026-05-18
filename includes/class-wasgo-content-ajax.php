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
        add_action( 'wp_ajax_wasgo_generate_single_category', [ $this, 'generate_single_category' ] );
        add_action( 'wp_ajax_wasgo_content_search_products', [ $this, 'search_preview_products' ] );
        add_action( 'wp_ajax_wasgo_content_get_preview_data', [ $this, 'get_preview_product_data' ] );
        add_action( 'wp_ajax_wasgo_content_save_review_edit', [ $this, 'save_review_edit' ] );
        add_action( 'wp_ajax_wasgo_content_regenerate_review', [ $this, 'regenerate_review' ] );
    }

    public function search_preview_products() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';
        $search_type = isset( $_POST['search_type'] ) ? sanitize_text_field( $_POST['search_type'] ) : 'product';

        $results = [];

        if ( $search_type === 'category' ) {
            $terms = get_terms( [
                'taxonomy'   => 'product_cat',
                'name__like' => $term,
                'hide_empty' => false,
                'number'     => 10
            ] );

            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                foreach ( $terms as $t ) {
                    $results[] = [
                        'id'    => $t->term_id,
                        'title' => $t->name
                    ];
                }
            }
        } else {
            $args = [
                'post_type'      => 'product',
                'posts_per_page' => 10,
                's'              => $term,
                'post_status'    => 'publish'
            ];

            $query = new WP_Query( $args );

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
        }

        wp_send_json_success( $results );
    }

    public function get_preview_product_data() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $pid = isset( $_POST['pid'] ) ? intval( $_POST['pid'] ) : 0;
        $search_type = isset( $_POST['search_type'] ) ? sanitize_text_field( $_POST['search_type'] ) : 'product';

        if ( ! $pid ) {
            wp_send_json_error( 'Invalid ID' );
        }

        if ( $search_type === 'category' ) {
            $term = get_term( $pid, 'product_cat' );
            if ( ! $term || is_wp_error( $term ) ) {
                wp_send_json_error( 'Invalid Category' );
            }

            // Get Category Image
            $image_url = '';
            $thumbnail_id = get_term_meta( $pid, 'thumbnail_id', true );
            if ( $thumbnail_id ) {
                $image_url = wp_get_attachment_thumb_url( $thumbnail_id );
            }

            // Get Parent Category
            $parent_name = 'None';
            if ( $term->parent ) {
                $parent_term = get_term( $term->parent, 'product_cat' );
                if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                    $parent_name = $parent_term->name;
                }
            }

            $products = get_posts( [
                'post_type'      => 'product',
                'posts_per_page' => 3,
                'post_status'    => 'publish',
                'tax_query'      => [
                    [
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $pid
                    ]
                ]
            ] );

            $product_titles = [];
            foreach ( $products as $p ) {
                $product_titles[] = $p->post_title;
            }

            $data = [
                'title'           => $term->name,
                'desc'            => $term->description ?: 'No organic description defined.',
                'parent'          => $parent_name,
                'count'           => $term->count,
                'image'           => $image_url,
                'sample_products' => ! empty( $product_titles ) ? implode( ', ', $product_titles ) : 'No products inside this category.',
                'lang'            => WASGO_Settings::get_content_language(),
                'req_img'         => false
            ];
        } else {
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
        }

        wp_send_json_success( $data );
    }

    public function handle_review_action() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );

        $pid       = isset( $_POST['pid'] ) ? intval( $_POST['pid'] ) : 0;
        $type      = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $action    = isset( $_POST['review_action'] ) ? sanitize_text_field( $_POST['review_action'] ) : '';
        $item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : 'product';

        if ( ! $pid || ! $type || ! $action ) {
            wp_send_json_error( 'Missing parameters.' );
        }

        if ( $item_type === 'category' ) {
            $review_data = get_term_meta( $pid, '_wasgo_content_review', true );
            if ( ! is_array( $review_data ) || ! isset( $review_data[$type] ) ) {
                wp_send_json_error( 'Content not found in review queue.' );
            }

            if ( $action === 'approve' ) {
                $content = $review_data[$type]['content'];
                WASGO_Content_Orchestrator::save_category_content( $pid, $type, $content );
                $type_label = ucwords( str_replace( ['cat_title', 'cat_desc'], ['Category Meta Title', 'Category Meta Description'], $type ) );
                WASGO_Logs::log_success( $pid, "Manually approved $type_label.", 'content', [ $type_label => $content ] );
            }

            unset( $review_data[$type] );

            if ( empty( $review_data ) ) {
                delete_term_meta( $pid, '_wasgo_content_review' );
                delete_term_meta( $pid, '_wasgo_needs_review' );
            } else {
                update_term_meta( $pid, '_wasgo_content_review', $review_data );
            }
        } else {
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

            unset( $review_data[$type] );

            if ( empty( $review_data ) ) {
                delete_post_meta( $pid, '_wasgo_content_review' );
                delete_post_meta( $pid, '_wasgo_needs_review' );
            } else {
                update_post_meta( $pid, '_wasgo_content_review', $review_data );
            }
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

        if ( isset( $result['status'] ) && $result['status'] === 'error' ) {
            wp_send_json_error( $result['message'] );
        }
        wp_send_json_success( $result );
    }

    public function save_review_edit() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $pid       = isset( $_POST['pid'] ) ? intval( $_POST['pid'] ) : 0;
        $type      = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $content   = isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';
        $item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : 'product';

        if ( ! $pid || ! $type ) {
            wp_send_json_error( 'Missing parameters.' );
        }

        if ( $item_type === 'category' ) {
            $review_data = get_term_meta( $pid, '_wasgo_content_review', true );
            if ( is_array( $review_data ) && isset( $review_data[$type] ) ) {
                $review_data[$type]['content'] = $content;
                update_term_meta( $pid, '_wasgo_content_review', $review_data );
                wp_send_json_success();
            }
        } else {
            $review_data = get_post_meta( $pid, '_wasgo_content_review', true );
            if ( is_array( $review_data ) && isset( $review_data[$type] ) ) {
                $review_data[$type]['content'] = $content;
                update_post_meta( $pid, '_wasgo_content_review', $review_data );
                wp_send_json_success();
            }
        }

        wp_send_json_error( 'Review data not found.' );
    }

    public function regenerate_review() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );
        $pid       = isset( $_POST['pid'] ) ? intval( $_POST['pid'] ) : 0;
        $type      = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $item_type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : 'product';

        if ( ! $pid || ! $type ) {
            wp_send_json_error( 'Missing parameters.' );
        }

        if ( $item_type === 'category' ) {
            $gen_result = WASGO_Content_Generator::generate_category_content( $pid, [$type] );
            if ( is_wp_error( $gen_result ) ) {
                wp_send_json_error( $gen_result->get_error_message() );
            }

            $content = isset( $gen_result[$type] ) ? $gen_result[$type] : '';
            if ( empty( $content ) || $content === 'UNKNOWN' ) {
                wp_send_json_error( 'AI could not generate new content.' );
            }

            // Fetch prompt
            $prompt = get_option( "wasgo_content_{$type}_prompt", '' );
            if ( empty( $prompt ) ) {
                if ( $type === 'cat_title' ) {
                    $prompt = "Write a high-converting, professional, and SEO-optimized meta title for this product category.\nThe meta title should be compelling, incorporate the category name naturally, and stay within 50-60 characters for maximum search engine click-through rates.";
                } elseif ( $type === 'cat_desc' ) {
                    $prompt = "Write an engaging, SEO-optimized meta description for this product category.\nIt should entice searchers to click, describe what products they will find in this category, and contain a clear call to action while strictly staying within 150-160 characters.";
                }
            }

            $context = [
                'type'   => $type,
                'prompt' => $prompt,
                'specs'  => WASGO_Content_Orchestrator::get_category_specs_for_type( $pid, $type )
            ];
            $val_result = WASGO_Content_Validator::validate_category_content( $pid, [ $type => $content ], $context );

            $score = 0.5;
            $issues = [];
            if ( ! is_wp_error( $val_result ) ) {
                $score = isset( $val_result['confidence_score'] ) ? floatval( $val_result['confidence_score'] ) : 0.5;
                $issues = isset( $val_result['issues'] ) ? $val_result['issues'] : [];
            }

            $review_data = get_term_meta( $pid, '_wasgo_content_review', true );
            $review_data[$type] = [
                'content' => $content,
                'score'   => $score,
                'issues'  => $issues
            ];
            update_term_meta( $pid, '_wasgo_content_review', $review_data );
        } else {
            $gen_result = WASGO_Content_Generator::generate_content( $pid, [$type] );
            if ( is_wp_error( $gen_result ) ) {
                wp_send_json_error( $gen_result->get_error_message() );
            }

            $content = isset( $gen_result[$type] ) ? $gen_result[$type] : '';
            if ( empty( $content ) || $content === 'UNKNOWN' ) {
                wp_send_json_error( 'AI could not generate new content.' );
            }

            $context = [
                'type'   => $type,
                'prompt' => get_option( "wasgo_content_{$type}_prompt", '' ),
                'specs'  => []
            ];
            $val_result = WASGO_Content_Validator::validate_content( $pid, [ $type => $content ], $context );

            $score = 0.5;
            $issues = [];
            if ( ! is_wp_error( $val_result ) ) {
                $score = isset( $val_result['confidence_score'] ) ? floatval( $val_result['confidence_score'] ) : 0.5;
                $issues = isset( $val_result['issues'] ) ? $val_result['issues'] : [];
            }

            $review_data = get_post_meta( $pid, '_wasgo_content_review', true );
            $review_data[$type] = [
                'content' => $content,
                'score'   => $score,
                'issues'  => $issues
            ];
            update_post_meta( $pid, '_wasgo_content_review', $review_data );
        }

        // Determine score color for UI update
        $score_pct = round( $score * 100 );
        $score_color = '#ef4444'; 
        if ( $score >= 0.8 ) $score_color = '#22c55e';
        elseif ( $score >= 0.5 ) $score_color = '#f59e0b';

        wp_send_json_success( [
            'content'     => $content,
            'score_pct'   => $score_pct,
            'score_color' => $score_color,
            'issues'      => $issues
        ] );
    }

    public function generate_single_category() {
        check_ajax_referer( 'wasgo_ajax_nonce', 'nonce' );

        $term_id = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
        $types   = isset( $_POST['types'] ) ? array_map( 'sanitize_text_field', $_POST['types'] ) : [];

        if ( ! $term_id || empty( $types ) ) {
            wp_send_json_error( 'Invalid request.' );
        }

        update_term_meta( $term_id, '_wasgo_processing_content', time() );
        $result = WASGO_Content_Orchestrator::process_category( $term_id, $types, true );
        delete_term_meta( $term_id, '_wasgo_processing_content' );

        if ( isset( $result['status'] ) && $result['status'] === 'error' ) {
            wp_send_json_error( $result['message'] );
        }
        wp_send_json_success( $result );
    }
}
