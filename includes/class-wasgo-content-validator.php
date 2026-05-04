<?php
/**
 * Content Validator Class (GPT-4o)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Content_Validator {

    /**
     * Validate generated content using GPT-4o
     * 
     * @param int   $product_id      The product ID.
     * @param array $generated_json  The JSON content from GPT-4o.
     * @param array $context         Additional context (Title, Specs, Prompt, Type).
     * @return array|WP_Error        The validation result JSON or error.
     */
    public static function validate_content( $product_id, $generated_json, $context ) {
        $api_key = WASGO_Settings::get_openai_api_key();
        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_openai_key', 'OpenAI API Key is missing for validation.' );
        }

        $product_title = get_the_title( $product_id );
        $image_url = '';
        if ( get_option( 'wasgo_content_image_required', 0 ) ) {
            $image_id = get_post_thumbnail_id( $product_id );
            if ( $image_id ) {
                $image_url = wp_get_attachment_url( $image_id );
            }
        }
        
        $validation_prompt = "You are a critical content validator. Your job is to verify that the AI-generated product content is 100% accurate and does NOT contain hallucinations.\n\n";
        $validation_prompt .= "### PRODUCT CONTEXT:\n";
        $validation_prompt .= "Title: $product_title\n";
        if ( ! empty( $context['specs'] ) ) {
            $validation_prompt .= "Verified Specs:\n";
            foreach ( $context['specs'] as $label => $val ) {
                $validation_prompt .= "- $label: $val\n";
            }
        }
        
        $validation_prompt .= "\n### GENERATION CONTEXT:\n";
        $validation_prompt .= "Content Type: " . $context['type'] . "\n";
        $validation_prompt .= "Prompt Used: " . $context['prompt'] . "\n";
        
        $validation_prompt .= "\n### GENERATED CONTENT TO VALIDATE:\n";
        $validation_prompt .= wp_json_encode( $generated_json, JSON_PRETTY_PRINT ) . "\n\n";
        
        $validation_prompt .= "### YOUR INSTRUCTIONS:\n";
        $validation_prompt .= "1. **Distinguish Flow from Hallucination**: Do NOT flag standard 'Copywriting Bridge Words' (e.g., 'confort', 'protection', 'dextérité', 'polyvalent') if they are logically inherent to the product category (e.g., gloves protect hands). These are necessary for a natural tone.\n";
        $validation_prompt .= "2. **Flag Hard Hallucinations**: You MUST flag claims that are explicitly contradicted by specs (e.g., claiming 'Waterproof' when specs say 'Non') or technical specs not mentioned anywhere (e.g., claiming '3000 lumens' if not in specs).\n";
        $validation_prompt .= "3. **Visual Audit**: Use the Product Image to confirm colors, materials, and basic type. Flag if the text describes a red product but the image is blue.\n";
        $validation_prompt .= "4. **Strict Interpretations**: Ensure no technical codes or model numbers were added unless present in the input.\n";
        $validation_prompt .= "5. Output your decision in a strict JSON format.";

        $json_schema = [
            'name'   => 'content_validation_result',
            'strict' => true,
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'status'             => [ 'type' => 'string', 'enum' => [ 'pass', 'retry', 'fail' ] ],
                    'confidence_score'   => [ 'type' => 'number' ],
                    'issues'             => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'recommended_action' => [ 'type' => 'string' ]
                ],
                'required'             => [ 'status', 'confidence_score', 'issues', 'recommended_action' ],
                'additionalProperties' => false
            ]
        ];

        $messages = [
            [ 'role' => 'system', 'content' => 'You are a professional fact-checker for e-commerce product descriptions.' ],
            [ 'role' => 'user', 'content' => $validation_prompt ]
        ];

        // Add vision context if available
        if ( ! empty( $image_url ) ) {
            $messages[1]['content'] = [
                [ 'type' => 'text', 'text' => $validation_prompt ],
                [ 'type' => 'image_url', 'image_url' => [ 'url' => $image_url ] ]
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
                'temperature'     => 0
            ] ),
            'timeout' => 60
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            return new WP_Error( 'openai_validation_error', $data['error']['message'] );
        }

        $content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
        $validation_json = json_decode( $content, true );

        if ( ! $validation_json ) {
            return new WP_Error( 'invalid_validation_json', 'GPT-4o failed to return valid validation JSON.' );
        }

        return $validation_json;
    }
}
