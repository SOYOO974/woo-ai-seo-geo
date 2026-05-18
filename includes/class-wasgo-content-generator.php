<?php
/**
 * Content Generator Class (GPT-4o)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Content_Generator {

    /**
     * Generate content using GPT-4o
     * 
     * @param int   $product_id    The product ID.
     * @param array $content_types List of types to generate (short, long, title, desc).
     * @return array|WP_Error      The generated content JSON or error.
     */
    public static function generate_content( $product_id, $content_types, $feedback = [] ) {
        $api_key = WASGO_Settings::get_openai_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_api_key', 'OpenAI API Key is missing.' );
        }

        $product_title = get_the_title( $product_id );
        $image_data_url = '';
        if ( get_option( 'wasgo_content_image_required', 0 ) ) {
            $image_id = get_post_thumbnail_id( $product_id );
            if ( ! $image_id ) {
                return new WP_Error( 'missing_image', 'Product image is required but missing.' );
            }
            
            // Prefer local file path for reliable reading and encoding
            $image_path = get_attached_file( $image_id );
            if ( $image_path && file_exists( $image_path ) ) {
                $raw_data = @file_get_contents( $image_path );
                $mime = get_post_mime_type( $image_id ) ?: 'image/jpeg';
                if ( $raw_data ) {
                    $image_data_url = 'data:' . $mime . ';base64,' . base64_encode( $raw_data );
                }
            }

            // Fallback to public URL only if local reading failed (e.g. cloud storage)
            if ( empty( $image_data_url ) ) {
                $image_data_url = wp_get_attachment_url( $image_id );
            }
        }

        $target_lang = WASGO_Settings::get_content_language();
        $include_cats = WASGO_Settings::should_include_categories();
        $categories = $include_cats ? WASGO_Content_Utility::get_product_categories_string( $product_id ) : '';
        $attributes = WASGO_Content_Utility::get_product_attributes_string( $product_id );

        $sections_to_generate = [];
        $full_prompt = "### PRODUCT IDENTITY:\n";
        $full_prompt .= "Name: $product_title\n";
        if ( ! empty( $categories ) ) {
            $full_prompt .= "Categories: $categories\n";
        }
        if ( ! empty( $attributes ) ) {
            $full_prompt .= "Attributes:\n$attributes\n";
        }
        $full_prompt .= "Target Language: $target_lang\n\n";

        foreach ( $content_types as $type ) {
            $prompt_template = get_option( "wasgo_content_{$type}_prompt", '' );
            if ( empty( $prompt_template ) ) {
                WASGO_Logs::log_error( $product_id, "Configuration Missing: No prompt defined for $type.", 'content' );
                continue; // Skip types without prompts
            }

            $specs_keys = get_option( "wasgo_content_{$type}_specs", [] );
            $specs_content = "";
            if ( ! empty( $specs_keys ) ) {
                foreach ( $specs_keys as $key ) {
                    $val = WASGO_Content_Utility::get_product_spec_value( $product_id, $key );
                    if ( ! empty( $val ) ) {
                        $label = str_replace( '_', ' ', $key );
                        $specs_content .= "- $label: $val\n";
                    }
                }
            }

            $type_label = str_replace( '_', ' ', $type );
            $full_prompt .= "### Instructions for $type_label:\n";
            $full_prompt .= $prompt_template . "\n";
            if ( ! empty( $specs_content ) ) {
                $full_prompt .= "Use the following verified product specs for this section:\n$specs_content\n";
            }

            if ( ! empty( $feedback[$type] ) ) {
                $full_prompt .= "### FEEDBACK FROM PREVIOUS ATTEMPT:\n";
                $full_prompt .= "Your previous output for this section was rejected for the following reasons:\n";
                $full_prompt .= "- " . $feedback[$type] . "\n";
                $full_prompt .= "Please regenerate this section and fix these specific errors while maintaining the overall tone.\n";
            }
            $full_prompt .= "\n";
            $sections_to_generate[] = $type;
        }

        if ( empty( $sections_to_generate ) ) {
            return new WP_Error( 'no_prompts', 'No prompts configured for requested content types.' );
        }

        // Define properties for JSON Schema
        $properties = [];
        $required = [];

        foreach ( $sections_to_generate as $type ) {
            $properties[$type] = [ 'type' => 'string' ];
            $required[] = $type;
        }

        // Standard metadata fields
        $properties['confidence_score'] = [ 'type' => 'number' ];
        $properties['ambiguity_flags']  = [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ];
        $properties['assumptions_made'] = [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ];
        
        $required[] = 'confidence_score';
        $required[] = 'ambiguity_flags';
        $required[] = 'assumptions_made';

        $json_schema = [
            'name'   => 'content_generation_result',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => $properties,
                'required'             => $required,
                'additionalProperties' => false,
            ]
        ];

        $system_instruction = "You are an expert WooCommerce SEO agent. Return the requested content fields in a structured format based on the product identity and specs provided. 
        IMPORTANT: You MUST generate all content in the specified Target Language: $target_lang.
        If you cannot generate a field accurately, use 'UNKNOWN'. 
        Do not hallucinate facts not present in the title or specs.";

        $messages = [
            [ 'role' => 'system', 'content' => $system_instruction ],
            [ 'role' => 'user', 'content' => $full_prompt ]
        ];

        // Add image context if available
        if ( ! empty( $image_data_url ) ) {
            $messages[1]['content'] = [
                [ 'type' => 'text', 'text' => $full_prompt ],
                [ 'type' => 'image_url', 'image_url' => [ 'url' => $image_data_url ] ]
            ];
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'body'    => wp_json_encode( [
                'model'           => 'gpt-4o-2024-08-06',
                'messages'        => $messages,
                'response_format' => [ 
                    'type'        => 'json_schema',
                    'json_schema' => $json_schema
                ],
                'temperature'     => 0.7
            ] ),
            'timeout' => 60
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            return new WP_Error( 'openai_error', $data['error']['message'] );
        }

        $content_json = isset( $data['choices'][0]['message']['content'] ) ? json_decode( $data['choices'][0]['message']['content'], true ) : null;

        if ( ! $content_json ) {
            return new WP_Error( 'invalid_json', 'GPT-4o failed to return valid JSON.' );
        }

        return $content_json;
    }

    /**
     * Generate content for WooCommerce categories using GPT-4o
     * 
     * @param int   $term_id       The category term ID.
     * @param array $content_types List of types to generate (cat_title, cat_desc).
     * @return array|WP_Error      The generated content JSON or error.
     */
    public static function generate_category_content( $term_id, $content_types, $feedback = [] ) {
        $api_key = WASGO_Settings::get_openai_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_api_key', 'OpenAI API Key is missing.' );
        }

        $term = get_term( $term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'invalid_category', 'Category not found.' );
        }

        $image_data_url = '';
        $thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true );
        if ( $thumbnail_id ) {
            $image_path = get_attached_file( $thumbnail_id );
            if ( $image_path && file_exists( $image_path ) ) {
                $raw_data = @file_get_contents( $image_path );
                $mime = get_post_mime_type( $thumbnail_id ) ?: 'image/jpeg';
                if ( $raw_data ) {
                    $image_data_url = 'data:' . $mime . ';base64,' . base64_encode( $raw_data );
                }
            }
            if ( empty( $image_data_url ) ) {
                $image_data_url = wp_get_attachment_url( $thumbnail_id );
            }
        }

        $target_lang = WASGO_Settings::get_content_language();

        // Get parent category name
        $parent_name = 'None';
        if ( $term->parent ) {
            $parent_term = get_term( $term->parent, 'product_cat' );
            if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                $parent_name = $parent_term->name;
            }
        }

        // Get 3 sample products under this category
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

        $sections_to_generate = [];
        $full_prompt = "### CATEGORY IDENTITY:\n";
        $full_prompt .= "Name: {$term->name}\n";
        $full_prompt .= "Target Language: $target_lang\n\n";

        foreach ( $content_types as $type ) {
            $prompt_template = get_option( "wasgo_content_{$type}_prompt", '' );
            if ( empty( $prompt_template ) ) {
                WASGO_Logs::log_error( $term_id, "Configuration Missing: No prompt defined for $type.", 'content' );
                continue;
            }

            $specs_keys = get_option( "wasgo_content_{$type}_specs", [] );
            $specs_content = "";
            if ( ! empty( $specs_keys ) ) {
                foreach ( $specs_keys as $key ) {
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

                    $label = ucwords( str_replace( '_', ' ', $key ) );
                    $specs_content .= "- $label: $val\n";
                }
            }

            $type_label = str_replace( ['cat_title', 'cat_desc'], ['PRODUCT CATEGORY META TITLE', 'PRODUCT CATEGORY META DESCRIPTION'], $type );
            $full_prompt .= "### Instructions for $type_label:\n";
            $full_prompt .= $prompt_template . "\n";
            if ( ! empty( $specs_content ) ) {
                $full_prompt .= "Use the following verified WooCommerce category details for this section:\n$specs_content\n";
            }

            if ( ! empty( $feedback[$type] ) ) {
                $full_prompt .= "### FEEDBACK FROM PREVIOUS ATTEMPT:\n";
                $full_prompt .= "Your previous output for this section was rejected for the following reasons:\n";
                $full_prompt .= "- " . $feedback[$type] . "\n";
                $full_prompt .= "Please regenerate this section and fix these specific errors while maintaining the overall tone.\n";
            }
            $full_prompt .= "\n";
            $sections_to_generate[] = $type;
        }

        if ( empty( $sections_to_generate ) ) {
            return new WP_Error( 'no_prompts', 'No prompts configured for requested category content types.' );
        }

        // Define properties for JSON Schema
        $properties = [];
        $required = [];

        foreach ( $sections_to_generate as $type ) {
            $properties[$type] = [ 'type' => 'string' ];
            $required[] = $type;
        }

        $properties['confidence_score'] = [ 'type' => 'number' ];
        $properties['ambiguity_flags']  = [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ];
        $properties['assumptions_made'] = [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ];
        
        $required[] = 'confidence_score';
        $required[] = 'ambiguity_flags';
        $required[] = 'assumptions_made';

        $json_schema = [
            'name'   => 'category_content_generation_result',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'properties'           => $properties,
                'required'             => $required,
                'additionalProperties' => false,
            ]
        ];

        $system_instruction = "You are an expert WooCommerce SEO agent. Return the requested content fields in a structured format based on the category identity and specs provided. 
        IMPORTANT: You MUST generate all content in the specified Target Language: $target_lang.
        If you cannot generate a field accurately, use 'UNKNOWN'. 
        Do not hallucinate facts not present in the name or details.";

        $messages = [
            [ 'role' => 'system', 'content' => $system_instruction ],
            [ 'role' => 'user', 'content' => $full_prompt ]
        ];

        // Add category image context if available
        if ( ! empty( $image_data_url ) ) {
            $messages[1]['content'] = [
                [ 'type' => 'text', 'text' => $full_prompt ],
                [ 'type' => 'image_url', 'image_url' => [ 'url' => $image_data_url ] ]
            ];
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ],
            'body'    => wp_json_encode( [
                'model'           => 'gpt-4o-2024-08-06',
                'messages'        => $messages,
                'response_format' => [ 
                    'type'        => 'json_schema',
                    'json_schema' => $json_schema
                ],
                'temperature'     => 0.7
            ] ),
            'timeout' => 60
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            return new WP_Error( 'openai_error', $data['error']['message'] );
        }

        $content_json = isset( $data['choices'][0]['message']['content'] ) ? json_decode( $data['choices'][0]['message']['content'], true ) : null;

        if ( ! $content_json ) {
            return new WP_Error( 'invalid_json', 'GPT-4o failed to return valid JSON.' );
        }

        return $content_json;
    }
}
