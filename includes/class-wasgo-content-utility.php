<?php
/**
 * Content Utility Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WASGO_Content_Utility {

    /**
     * Scan all products to find unique meta keys (Useful Specs)
     * 
     * @return array List of meta keys
     */
    public static function get_available_specs() {
        global $wpdb;

        // Fetch meta keys from products only
        $keys = $wpdb->get_col( "
            SELECT DISTINCT pm.meta_key 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND pm.meta_key NOT LIKE '\_%'
            ORDER BY pm.meta_key ASC
        " );

        // Include some important internal ones that don't start with underscore if needed, 
        // but usually, product attributes and custom fields are what we want.
        // We can also explicitly include SKU or others if they are hidden.
        
        $visible_internal = [
            '_sku',
            '_price',
            '_weight',
            '_length',
            '_width',
            '_height',
            '_stock'
        ];

        $keys = array_merge( $keys, $visible_internal );
        sort( $keys );

        return array_unique( $keys );
    }

    /**
     * Get value of a spec for a specific product
     */
    public static function get_product_spec_value( $product_id, $meta_key ) {
        // Special handling for some WC core fields if requested as meta
        if ( $meta_key === '_sku' ) {
            $product = wc_get_product( $product_id );
            return $product ? $product->get_sku() : '';
        }

        return get_post_meta( $product_id, $meta_key, true );
    }

    /**
     * Get product categories as a formatted string
     */
    public static function get_product_categories_string( $product_id ) {
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '';
        }

        $categories = [];
        foreach ( $terms as $term ) {
            $categories[] = $term->name;
        }

        return implode( ', ', $categories );
    }

    /**
     * Get all product attributes as a formatted string
     */
    public static function get_product_attributes_string( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return '';
        }

        $attributes = $product->get_attributes();
        if ( empty( $attributes ) ) {
            return '';
        }

        $formatted = [];
        foreach ( $attributes as $attr ) {
            $name = $attr->is_taxonomy() ? wc_attribute_label( $attr->get_name() ) : $attr->get_name();
            
            if ( $attr->is_taxonomy() ) {
                $terms = $attr->get_terms();
                $values = [];
                foreach ( $terms as $term ) {
                    $values[] = $term->name;
                }
                $val_str = implode( ', ', $values );
            } else {
                $val_str = implode( ', ', $attr->get_options() );
            }

            if ( ! empty( $val_str ) ) {
                $formatted[] = "$name: $val_str";
            }
        }

        return implode( "\n", $formatted );
    }
}
