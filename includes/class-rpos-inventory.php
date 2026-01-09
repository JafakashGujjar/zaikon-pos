<?php
/**
 * Inventory Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Inventory {
    
    /**
     * Get inventory for product
     */
    public static function get_by_product($product_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_inventory WHERE product_id = %d",
            $product_id
        ));
    }
    
    /**
     * Get inventory item by inventory ID
     */
    public static function get_by_id($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, p.name as product_name, p.sku
             FROM {$wpdb->prefix}rpos_inventory i
             LEFT JOIN {$wpdb->prefix}rpos_products p ON i.product_id = p.id
             WHERE i.id = %d",
            $id
        ));
    }
    
    /**
     * Get all inventory items with product details
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'low_stock_only' => false,
            'orderby' => 'p.name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = '1=1';
        
        if ($args['low_stock_only']) {
            $threshold = RPOS_Settings::get('low_stock_threshold', 10);
            $where .= $wpdb->prepare(' AND i.quantity <= %d', $threshold);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'p.name ASC';
        }
        
        return $wpdb->get_results(
            "SELECT i.*, p.name as product_name, p.sku 
             FROM {$wpdb->prefix}rpos_inventory i
             LEFT JOIN {$wpdb->prefix}rpos_products p ON i.product_id = p.id
             WHERE {$where}
             ORDER BY {$orderby}"
        );
    }
    
    /**
     * Create inventory record for product
     */
    public static function create_for_product($product_id, $quantity = 0, $cost_price = 0, $unit = 'pcs') {
        global $wpdb;
        
        // Check if already exists
        $exists = self::get_by_product($product_id);
        if ($exists) {
            return false;
        }
        
        return $wpdb->insert(
            $wpdb->prefix . 'rpos_inventory',
            array(
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit' => $unit,
                'cost_price' => $cost_price
            ),
            array('%d', '%d', '%s', '%f')
        );
    }
    
    /**
     * Update inventory
     */
    public static function update($product_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['quantity'])) {
            $update_data['quantity'] = floatval($data['quantity']);
            $format[] = '%f';
        }
        
        if (isset($data['unit'])) {
            $update_data['unit'] = sanitize_text_field($data['unit']);
            $format[] = '%s';
        }
        
        if (isset($data['cost_price'])) {
            $update_data['cost_price'] = floatval($data['cost_price']);
            $format[] = '%f';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_inventory',
            $update_data,
            array('product_id' => $product_id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Adjust stock quantity
     */
    public static function adjust_stock($product_id, $change_amount, $reason = '', $order_id = null, $user_id = null, $expiry_date = null) {
        global $wpdb;
        
        // Get current inventory
        $inventory = self::get_by_product($product_id);
        if (!$inventory) {
            // Create inventory record if doesn't exist
            self::create_for_product($product_id);
            $inventory = self::get_by_product($product_id);
        }
        
        // Calculate new quantity
        $new_quantity = floatval($inventory->quantity) + floatval($change_amount);
        
        // Update inventory
        $wpdb->update(
            $wpdb->prefix . 'rpos_inventory',
            array('quantity' => $new_quantity),
            array('product_id' => $product_id),
            array('%f'),
            array('%d')
        );
        
        // Record stock movement
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $movement_data = array(
            'product_id' => $product_id,
            'change_amount' => floatval($change_amount),
            'reason' => sanitize_text_field($reason),
            'order_id' => $order_id,
            'user_id' => $user_id
        );
        $movement_format = array('%d', '%f', '%s', '%d', '%d');
        
        // Validate and add expiry_date if provided
        if ($expiry_date) {
            // Validate date format (YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date)) {
                $movement_data['expiry_date'] = sanitize_text_field($expiry_date);
                $movement_format[] = '%s';
            }
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'rpos_stock_movements',
            $movement_data,
            $movement_format
        );
        
        return $new_quantity;
    }
    
    /**
     * Get stock movements
     */
    public static function get_stock_movements($product_id = null, $limit = 100) {
        global $wpdb;
        
        $where = '1=1';
        $where_values = array();
        
        if ($product_id) {
            $where .= ' AND sm.product_id = %d';
            $where_values[] = $product_id;
        }
        
        $query = "SELECT sm.*, p.name as product_name, u.display_name as user_name
                  FROM {$wpdb->prefix}rpos_stock_movements sm
                  LEFT JOIN {$wpdb->prefix}rpos_products p ON sm.product_id = p.id
                  LEFT JOIN {$wpdb->users} u ON sm.user_id = u.ID
                  WHERE {$where}
                  ORDER BY sm.created_at DESC
                  LIMIT %d";
        
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Deduct stock for order items
     */
    public static function deduct_for_order($order_id, $items) {
        foreach ($items as $item) {
            self::adjust_stock(
                $item['product_id'],
                -$item['quantity'],
                'Order #' . $order_id,
                $order_id
            );
        }
    }
}
