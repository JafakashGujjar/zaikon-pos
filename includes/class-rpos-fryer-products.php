<?php
/**
 * Fryer Products Management Class
 * Handles product-to-oil consumption mapping
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Fryer_Products {
    
    /**
     * Get all products mapped to fryer
     */
    public static function get_fryer_products() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT fpm.*, p.name as product_name, f.name as fryer_name
             FROM {$wpdb->prefix}rpos_fryer_product_map fpm
             LEFT JOIN {$wpdb->prefix}rpos_products p ON fpm.product_id = p.id
             LEFT JOIN {$wpdb->prefix}rpos_fryers f ON fpm.fryer_id = f.id
             ORDER BY p.name ASC"
        );
    }
    
    /**
     * Add product mapping
     */
    public static function add_product($product_id, $oil_units, $fryer_id = null) {
        global $wpdb;
        
        if ($product_id <= 0) {
            return false;
        }
        
        // Check if mapping already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rpos_fryer_product_map
             WHERE product_id = %d AND (fryer_id = %d OR (fryer_id IS NULL AND %d IS NULL))",
            $product_id,
            $fryer_id,
            $fryer_id
        ));
        
        if ($existing) {
            // Update existing mapping
            return $wpdb->update(
                $wpdb->prefix . 'rpos_fryer_product_map',
                array('oil_units' => floatval($oil_units)),
                array('id' => $existing->id),
                array('%f'),
                array('%d')
            );
        }
        
        // Insert new mapping
        return $wpdb->insert(
            $wpdb->prefix . 'rpos_fryer_product_map',
            array(
                'product_id' => absint($product_id),
                'oil_units' => floatval($oil_units),
                'fryer_id' => $fryer_id ? absint($fryer_id) : null
            ),
            array('%d', '%f', '%d')
        );
    }
    
    /**
     * Update product mapping
     */
    public static function update_product($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['oil_units'])) {
            $update_data['oil_units'] = floatval($data['oil_units']);
            $formats[] = '%f';
        }
        
        if (isset($data['fryer_id'])) {
            $update_data['fryer_id'] = $data['fryer_id'] ? absint($data['fryer_id']) : null;
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_fryer_product_map',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Remove product mapping
     */
    public static function remove_product($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_fryer_product_map',
            array('id' => absint($id)),
            array('%d')
        );
    }
    
    /**
     * Get oil consumption units for a product
     */
    public static function get_oil_units($product_id, $fryer_id = null) {
        global $wpdb;
        
        // First try exact match (product_id AND fryer_id)
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT oil_units FROM {$wpdb->prefix}rpos_fryer_product_map
             WHERE product_id = %d AND (fryer_id = %d OR (fryer_id IS NULL AND %d IS NULL))",
            $product_id,
            $fryer_id,
            $fryer_id
        ));
        
        // If no match found, fall back to matching by product_id only (regardless of fryer_id)
        if ($result === null) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT oil_units FROM {$wpdb->prefix}rpos_fryer_product_map
                 WHERE product_id = %d
                 ORDER BY id ASC
                 LIMIT 1",
                $product_id
            ));
        }
        
        return $result ? floatval($result) : null;
    }
    
    /**
     * Check if product is a fryer product
     */
    public static function is_fryer_product($product_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_fryer_product_map
             WHERE product_id = %d",
            $product_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Get fryer for a product
     */
    public static function get_product_fryer($product_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT fryer_id FROM {$wpdb->prefix}rpos_fryer_product_map
             WHERE product_id = %d",
            $product_id
        ));
    }
}
