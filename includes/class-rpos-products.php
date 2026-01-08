<?php
/**
 * Products Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Products {
    
    /**
     * Get all products
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'category_id' => 0,
            'is_active' => null,
            'search' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['category_id']) {
            $where[] = 'category_id = %d';
            $where_values[] = $args['category_id'];
        }
        
        if (!is_null($args['is_active'])) {
            $where[] = 'is_active = %d';
            $where_values[] = $args['is_active'];
        }
        
        if ($args['search']) {
            $where[] = '(name LIKE %s OR sku LIKE %s)';
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'name ASC';
        }
        
        $limit_clause = '';
        if ($args['limit']) {
            $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rpos_products WHERE {$where_clause} ORDER BY {$orderby}{$limit_clause}",
                $where_values
            );
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}rpos_products WHERE {$where_clause} ORDER BY {$orderby}{$limit_clause}";
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get product by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_products WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create product
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'name' => '',
            'sku' => '',
            'category_id' => 0,
            'selling_price' => 0,
            'image_url' => '',
            'description' => '',
            'is_active' => 1
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_products',
            array(
                'name' => sanitize_text_field($data['name']),
                'sku' => sanitize_text_field($data['sku']),
                'category_id' => absint($data['category_id']),
                'selling_price' => floatval($data['selling_price']),
                'image_url' => esc_url_raw($data['image_url']),
                'description' => wp_kses_post($data['description']),
                'is_active' => absint($data['is_active'])
            ),
            array('%s', '%s', '%d', '%f', '%s', '%s', '%d')
        );
        
        if ($result) {
            $product_id = $wpdb->insert_id;
            
            // Create inventory record
            RPOS_Inventory::create_for_product($product_id);
            
            return $product_id;
        }
        
        return false;
    }
    
    /**
     * Update product
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['sku'])) {
            $update_data['sku'] = sanitize_text_field($data['sku']);
            $format[] = '%s';
        }
        
        if (isset($data['category_id'])) {
            $update_data['category_id'] = absint($data['category_id']);
            $format[] = '%d';
        }
        
        if (isset($data['selling_price'])) {
            $update_data['selling_price'] = floatval($data['selling_price']);
            $format[] = '%f';
        }
        
        if (isset($data['image_url'])) {
            $update_data['image_url'] = esc_url_raw($data['image_url']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = wp_kses_post($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = absint($data['is_active']);
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_products',
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Delete product
     */
    public static function delete($id) {
        global $wpdb;
        
        // Delete inventory record
        $wpdb->delete(
            $wpdb->prefix . 'rpos_inventory',
            array('product_id' => $id),
            array('%d')
        );
        
        // Delete product
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_products',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get product count
     */
    public static function count($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'category_id' => 0,
            'is_active' => null,
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['category_id']) {
            $where[] = 'category_id = %d';
            $where_values[] = $args['category_id'];
        }
        
        if (!is_null($args['is_active'])) {
            $where[] = 'is_active = %d';
            $where_values[] = $args['is_active'];
        }
        
        if ($args['search']) {
            $where[] = '(name LIKE %s OR sku LIKE %s)';
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_products WHERE {$where_clause}",
                $where_values
            );
        } else {
            $query = "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_products WHERE {$where_clause}";
        }
        
        return $wpdb->get_var($query);
    }
}
