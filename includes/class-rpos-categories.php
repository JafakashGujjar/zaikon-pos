<?php
/**
 * Categories Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Categories {
    
    /**
     * Get all categories
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'name ASC';
        }
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_categories ORDER BY {$orderby}"
        );
    }
    
    /**
     * Get category by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_categories WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create category
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_categories',
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => wp_kses_post($data['description'] ?? '')
            ),
            array('%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update category
     */
    public static function update($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_categories',
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => wp_kses_post($data['description'] ?? '')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Delete category
     */
    public static function delete($id) {
        global $wpdb;
        
        // Update products in this category to have no category
        $wpdb->update(
            $wpdb->prefix . 'rpos_products',
            array('category_id' => 0),
            array('category_id' => $id),
            array('%d'),
            array('%d')
        );
        
        // Delete category
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_categories',
            array('id' => $id),
            array('%d')
        );
    }
}
