<?php
/**
 * Content Orchestrator Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Content_Orchestrator {

    /**
     * Process content for a product
     * 
     * @param int   $product_id      The product ID.
     * @param array $types           List of types to generate.
     * @param bool  $ignore_disabled Whether to ignore global disable toggles (for bulk).
     * @return array                 Summary of results.
     */
    public static function process_product( $product_id, $types = [], $ignore_disabled = false ) {
        if ( empty( $types ) ) {
            $types = ['short', 'long', 'title', 'desc'];
        }

        // Filter out types that are globally disabled (unless it's a bulk/override context)
        $final_types = [];
        foreach ( $types as $type ) {
            if ( $ignore_disabled || ! get_option( "wasgo_content_disable_{$type}", 0 ) ) {
                $final_types[] = $type;
            }
        }

        if ( empty( $final_types ) ) {
            return [ 'status' => 'skipped', 'message' => 'All requested types are disabled.' ];
        }

        // 1. Generation Phase (GPT-4o)
        $generation_result = WASGO_Content_Generator::generate_content( $product_id, $final_types );

        if ( is_wp_error( $generation_result ) ) {
            WASGO_Logs::log_error( $product_id, "Generation failed: " . $generation_result->get_error_message(), 'content' );
            return [ 'status' => 'error', 'message' => $generation_result->get_error_message() ];
        }

        $results_summary = [];

        // 2. Validation & Commit Phase (Per Type)
        foreach ( $final_types as $type ) {
            if ( ! isset( $generation_result[$type] ) || $generation_result[$type] === 'UNKNOWN' ) {
                $results_summary[$type] = 'skipped_no_content';
                continue;
            }

            $content_to_verify = $generation_result[$type];
            $attempt = 1;
            $passed = false;

            while ( $attempt <= 2 && ! $passed ) {
                // Prepare context for Claude
                $context = [
                    'type'   => $type,
                    'prompt' => get_option( "wasgo_content_{$type}_prompt", '' ),
                    'specs'  => self::get_product_specs_for_type( $product_id, $type )
                ];

                $validation = WASGO_Content_Validator::validate_content( $product_id, [ $type => $content_to_verify ], $context );

                if ( is_wp_error( $validation ) ) {
                    WASGO_Logs::log_error( $product_id, "Validation failed ($type): " . $validation->get_error_message(), 'content' );
                    $results_summary[$type] = 'validation_error: ' . $validation->get_error_message();
                    break;
                }

                if ( $validation['status'] === 'pass' && $validation['confidence_score'] >= 0.85 ) {
                    self::save_product_content( $product_id, $type, $content_to_verify );
                    $results_summary[$type] = 'success';
                    $passed = true;
                } elseif ( $validation['status'] === 'retry' && $attempt < 2 ) {
                    // Try to regenerate specific field with feedback
                    $feedback_text = implode( ", ", $validation['issues'] );
                    if ( ! empty( $validation['recommended_action'] ) ) {
                        $feedback_text .= ". Advice: " . $validation['recommended_action'];
                    }

                    $retry_gen = WASGO_Content_Generator::generate_content( $product_id, [ $type ], [ $type => $feedback_text ] );
                    
                    if ( ! is_wp_error( $retry_gen ) && isset( $retry_gen[$type] ) ) {
                        $content_to_verify = $retry_gen[$type];
                        $attempt++;
                    } else {
                        // If retry generation fails, fall back to review queue
                        self::add_to_review_queue( $product_id, $type, $content_to_verify, $validation['issues'], $validation['confidence_score'] );
                        $results_summary[$type] = 'review_required';
                        break;
                    }
                } else {
                    // Fail or Confidence too low -> Add to Review Required
                    self::add_to_review_queue( $product_id, $type, $content_to_verify, $validation['issues'], $validation['confidence_score'] );
                    $results_summary[$type] = 'review_required';
                    break;
                }
            }
        }

        // Consolidated Success Logging
        $success_types = [];
        $log_data = [];
        foreach ( $results_summary as $type => $status ) {
            if ( $status === 'success' ) {
                $label = ucwords( str_replace( ['short', 'long', 'title', 'desc'], ['Short Description', 'Long Description', 'Meta Title', 'Meta Description'], $type ) );
                $success_types[] = $label;
                $log_data[$label] = isset( $generation_result[$type] ) ? $generation_result[$type] : '';
            }
        }

        if ( ! empty( $success_types ) ) {
            $msg = "Successfully generated " . implode( ", ", $success_types ) . ".";
            WASGO_Logs::log_success( $product_id, $msg, 'content', $log_data );
        }

        return [ 'status' => 'complete', 'details' => $results_summary ];
    }

    /**
     * Get specs used for a specific content type
     */
    private static function get_product_specs_for_type( $product_id, $type ) {
        $keys = get_option( "wasgo_content_{$type}_specs", [] );
        $specs = [];
        foreach ( $keys as $key ) {
            $val = WASGO_Content_Utility::get_product_spec_value( $product_id, $key );
            if ( ! empty( $val ) ) {
                $specs[str_replace( '_', ' ', $key )] = $val;
            }
        }
        return $specs;
    }

    /**
     * Save verified content to the database
     */
    public static function save_product_content( $product_id, $type, $content ) {
        switch ( $type ) {
            case 'short':
                wp_update_post( [ 'ID' => $product_id, 'post_excerpt' => $content ] );
                break;
            case 'long':
                wp_update_post( [ 'ID' => $product_id, 'post_content' => $content ] );
                break;
            case 'title':
                self::update_seo_field( $product_id, 'title', $content );
                break;
            case 'desc':
                self::update_seo_field( $product_id, 'description', $content );
                break;
        }

        // Mark as AI generated
        $ai_fields = get_post_meta( $product_id, '_wasgo_ai_fields', true );
        if ( ! is_array( $ai_fields ) ) $ai_fields = [];
        if ( ! in_array( $type, $ai_fields ) ) {
            $ai_fields[] = $type;
            update_post_meta( $product_id, '_wasgo_ai_fields', $ai_fields );
        }


        // Remove from review queue if it was there
        $review_data = get_post_meta( $product_id, '_wasgo_content_review', true );
        if ( is_array( $review_data ) && isset( $review_data[$type] ) ) {
            unset( $review_data[$type] );
            if ( empty( $review_data ) ) {
                delete_post_meta( $product_id, '_wasgo_content_review' );
                delete_post_meta( $product_id, '_wasgo_needs_review' );
            } else {
                update_post_meta( $product_id, '_wasgo_content_review', $review_data );
            }
        }
    }

    /**
     * Update SEO fields for Yoast, RankMath, or The SEO Framework
     */
    private static function update_seo_field( $product_id, $field_type, $value ) {
        // Yoast SEO
        if ( defined( 'WPSEO_VERSION' ) ) {
            $meta_key = ( $field_type === 'title' ) ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc';
            update_post_meta( $product_id, $meta_key, $value );
        }
        
        // Rank Math
        if ( class_exists( 'RankMath' ) ) {
            $meta_key = ( $field_type === 'title' ) ? 'rank_math_title' : 'rank_math_description';
            update_post_meta( $product_id, $meta_key, $value );
        }

        // The SEO Framework (TSF)
        if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
            $meta_key = ( $field_type === 'title' ) ? '_genesis_title' : '_genesis_description';
            update_post_meta( $product_id, $meta_key, $value );
        }

        // Generic Fallback (In case user wants to see it somewhere else)
        update_post_meta( $product_id, "_wasgo_ai_{$field_type}", $value );
    }

    /**
     * Flag content for manual review
     */
    private static function add_to_review_queue( $product_id, $type, $content, $issues, $score = 0 ) {
        $review_data = get_post_meta( $product_id, '_wasgo_content_review', true );
        if ( ! is_array( $review_data ) ) $review_data = [];
 
        $review_data[$type] = [
            'content' => $content,
            'issues'  => $issues,
            'score'   => $score,
            'date'    => current_time( 'mysql' )
        ];

        update_post_meta( $product_id, '_wasgo_content_review', $review_data );
        update_post_meta( $product_id, '_wasgo_needs_review', '1' );
    }

    /**
     * Process content for a category
     */
    public static function process_category( $term_id, $types = [], $ignore_disabled = false ) {
        if ( empty( $types ) ) {
            $types = ['cat_title', 'cat_desc'];
        }

        $final_types = [];
        foreach ( $types as $type ) {
            if ( $ignore_disabled || ! get_option( "wasgo_content_disable_{$type}", 0 ) ) {
                $final_types[] = $type;
            }
        }

        if ( empty( $final_types ) ) {
            return [ 'status' => 'skipped', 'message' => 'All requested types are disabled.' ];
        }

        // 1. Generation Phase (GPT-4o)
        $generation_result = WASGO_Content_Generator::generate_category_content( $term_id, $final_types );

        if ( is_wp_error( $generation_result ) ) {
            WASGO_Logs::log_error( $term_id, "Generation failed: " . $generation_result->get_error_message(), 'content' );
            return [ 'status' => 'error', 'message' => $generation_result->get_error_message() ];
        }

        $results_summary = [];

        // 2. Validation & Commit Phase (Per Type)
        foreach ( $final_types as $type ) {
            if ( ! isset( $generation_result[$type] ) || $generation_result[$type] === 'UNKNOWN' ) {
                $results_summary[$type] = 'skipped_no_content';
                continue;
            }

            $content_to_verify = $generation_result[$type];
            $attempt = 1;
            $passed = false;

            while ( $attempt <= 2 && ! $passed ) {
                $prompt = get_option( "wasgo_content_{$type}_prompt", '' );
                if ( empty( $prompt ) ) {
                    if ( $type === 'cat_title' ) {
                        $prompt = "Write a high-converting, professional, and SEO-optimized meta title for this product category.\nThe meta title should be compelling, incorporate the category name naturally, and stay within 50-60 characters for maximum search engine click-through rates.";
                    } elseif ( $type === 'cat_desc' ) {
                        $prompt = "Write an engaging, SEO-optimized meta description for this product category.\nIt should entice searchers to click, describe what products they will find in this category, and contain a clear call to action while strictly staying within 150-160 characters.";
                    }
                }

                // Prepare context for validation
                $context = [
                    'type'   => $type,
                    'prompt' => $prompt,
                    'specs'  => self::get_category_specs_for_type( $term_id, $type )
                ];

                $validation = WASGO_Content_Validator::validate_category_content( $term_id, [ $type => $content_to_verify ], $context );

                if ( is_wp_error( $validation ) ) {
                    WASGO_Logs::log_error( $term_id, "Validation failed ($type): " . $validation->get_error_message(), 'content' );
                    $results_summary[$type] = 'validation_error: ' . $validation->get_error_message();
                    break;
                }

                if ( $validation['status'] === 'pass' && $validation['confidence_score'] >= 0.85 ) {
                    self::save_category_content( $term_id, $type, $content_to_verify );
                    $results_summary[$type] = 'success';
                    $passed = true;
                } elseif ( $validation['status'] === 'retry' && $attempt < 2 ) {
                    $feedback_text = implode( ", ", $validation['issues'] );
                    if ( ! empty( $validation['recommended_action'] ) ) {
                        $feedback_text .= ". Advice: " . $validation['recommended_action'];
                    }

                    $retry_gen = WASGO_Content_Generator::generate_category_content( $term_id, [ $type ], [ $type => $feedback_text ] );
                    
                    if ( ! is_wp_error( $retry_gen ) && isset( $retry_gen[$type] ) ) {
                        $content_to_verify = $retry_gen[$type];
                        $attempt++;
                    } else {
                        self::add_category_to_review_queue( $term_id, $type, $content_to_verify, $validation['issues'], $validation['confidence_score'] );
                        $results_summary[$type] = 'review_required';
                        break;
                    }
                } else {
                    self::add_category_to_review_queue( $term_id, $type, $content_to_verify, $validation['issues'], $validation['confidence_score'] );
                    $results_summary[$type] = 'review_required';
                    break;
                }
            }
        }

        // Consolidated Success Logging
        $success_types = [];
        $log_data = [];
        foreach ( $results_summary as $type => $status ) {
            if ( $status === 'success' ) {
                $label = ucwords( str_replace( ['cat_title', 'cat_desc'], ['Meta Title (Category)', 'Meta Description (Category)'], $type ) );
                $success_types[] = $label;
                $log_data[$label] = isset( $generation_result[$type] ) ? $generation_result[$type] : '';
            }
        }

        if ( ! empty( $success_types ) ) {
            $msg = "Successfully generated " . implode( ", ", $success_types ) . ".";
            WASGO_Logs::log_success( $term_id, $msg, 'content', $log_data );
        }

        return [ 'status' => 'complete', 'details' => $results_summary ];
    }

    /**
     * Get specs used for a specific category content type
     */
    private static function get_category_specs_for_type( $term_id, $type ) {
        $keys = get_option( "wasgo_content_{$type}_specs", [] );
        $specs = [];
        $term = get_term( $term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return $specs;
        }

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
                    'terms'    => $term_id
                ]
            ]
        ] );
        $product_titles = [];
        foreach ( $products as $p ) {
            $product_titles[] = $p->post_title;
        }
        $sample_products_str = ! empty( $product_titles ) ? implode( ', ', $product_titles ) : 'No products inside this category.';

        foreach ( $keys as $key ) {
            $val = '';
            if ( $key === 'category_description' ) {
                $val = $term->description;
            } elseif ( $key === 'parent_category' ) {
                $val = $parent_name;
            } elseif ( $key === 'product_count' ) {
                $val = $term->count;
            } elseif ( $key === 'latest_products' ) {
                $val = $sample_products_str;
            }

            if ( empty( $val ) ) {
                $val = 'None';
            }

            $specs[str_replace( '_', ' ', $key )] = $val;
        }
        return $specs;
    }

    /**
     * Save verified category content to the database
     */
    public static function save_category_content( $term_id, $type, $content ) {
        switch ( $type ) {
            case 'cat_title':
                self::update_category_seo_field( $term_id, 'title', $content );
                break;
            case 'cat_desc':
                self::update_category_seo_field( $term_id, 'description', $content );
                break;
        }

        // Mark as AI generated
        $ai_fields = get_term_meta( $term_id, '_wasgo_ai_fields', true );
        if ( ! is_array( $ai_fields ) ) $ai_fields = [];
        if ( ! in_array( $type, $ai_fields ) ) {
            $ai_fields[] = $type;
            update_term_meta( $term_id, '_wasgo_ai_fields', $ai_fields );
        }

        // Remove from review queue if it was there
        $review_data = get_term_meta( $term_id, '_wasgo_content_review', true );
        if ( is_array( $review_data ) && isset( $review_data[$type] ) ) {
            unset( $review_data[$type] );
            if ( empty( $review_data ) ) {
                delete_term_meta( $term_id, '_wasgo_content_review' );
                delete_term_meta( $term_id, '_wasgo_needs_review' );
            } else {
                update_term_meta( $term_id, '_wasgo_content_review', $review_data );
            }
        }
    }

    /**
     * Update SEO fields for Yoast, RankMath, or The SEO Framework for terms
     */
    private static function update_category_seo_field( $term_id, $field_type, $value ) {
        // Yoast SEO
        if ( defined( 'WPSEO_VERSION' ) ) {
            $meta_key = ( $field_type === 'title' ) ? 'wpseo_title' : 'wpseo_desc';
            update_term_meta( $term_id, $meta_key, $value );
        }
        
        // Rank Math
        if ( class_exists( 'RankMath' ) ) {
            $meta_key = ( $field_type === 'title' ) ? 'rank_math_title' : 'rank_math_description';
            update_term_meta( $term_id, $meta_key, $value );
        }

        // The SEO Framework (TSF)
        if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
            $meta_key = ( $field_type === 'title' ) ? '_genesis_title' : '_genesis_description';
            update_term_meta( $term_id, $meta_key, $value );
        }

        // Generic Fallback
        update_term_meta( $term_id, "_wasgo_ai_{$field_type}", $value );
    }

    /**
     * Flag category content for manual review
     */
    private static function add_category_to_review_queue( $term_id, $type, $content, $issues, $score = 0 ) {
        $review_data = get_term_meta( $term_id, '_wasgo_content_review', true );
        if ( ! is_array( $review_data ) ) $review_data = [];
  
        $review_data[$type] = [
            'content' => $content,
            'issues'  => $issues,
            'score'   => $score,
            'date'    => current_time( 'mysql' )
        ];

        update_term_meta( $term_id, '_wasgo_content_review', $review_data );
        update_term_meta( $term_id, '_wasgo_needs_review', '1' );
    }
}
