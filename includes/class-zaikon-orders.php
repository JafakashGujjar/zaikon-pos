<?php
/**
 * Zaikon Orders Management Class
 * Manages the standardized wp_zaikon_orders table
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Orders {
    
    /**
     * Create order in zaikon_orders table
     */
    public static function create($data) {
        global $wpdb;
        
        $order_data = array(
            'order_number' => sanitize_text_field($data['order_number']),
            'order_type' => sanitize_text_field($data['order_type'] ?? 'takeaway'),
            'items_subtotal_rs' => floatval($data['items_subtotal_rs'] ?? 0),
            'delivery_charges_rs' => floatval($data['delivery_charges_rs'] ?? 0),
            'discounts_rs' => floatval($data['discounts_rs'] ?? 0),
            'taxes_rs' => floatval($data['taxes_rs'] ?? 0),
            'grand_total_rs' => floatval($data['grand_total_rs']),
            'payment_status' => sanitize_text_field($data['payment_status'] ?? 'unpaid'),
            'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id()),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_orders',
            $order_data,
            array('%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get order by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get order by order number
     */
    public static function get_by_order_number($order_number) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_orders WHERE order_number = %s",
            $order_number
        ));
    }
    
    /**
     * Update order
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['payment_status'])) {
            $update_data['payment_status'] = sanitize_text_field($data['payment_status']);
            $formats[] = '%s';
        }
        
        if (isset($data['grand_total_rs'])) {
            $update_data['grand_total_rs'] = floatval($data['grand_total_rs']);
            $formats[] = '%f';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Get all orders with filters
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'order_type' => '',
            'date_from' => '',
            'date_to' => '',
            'payment_status' => '',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['order_type'])) {
            $where[] = 'order_type = %s';
            $where_values[] = $args['order_type'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if (!empty($args['payment_status'])) {
            $where[] = 'payment_status = %s';
            $where_values[] = $args['payment_status'];
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}zaikon_orders 
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY created_at DESC
                  LIMIT %d OFFSET %d";
        
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get sales summary for date range
     */
    public static function get_sales_summary($date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN order_type = 'delivery' THEN 1 ELSE 0 END) as delivery_orders,
                    SUM(CASE WHEN order_type = 'dine_in' THEN 1 ELSE 0 END) as dine_in_orders,
                    SUM(CASE WHEN order_type = 'takeaway' THEN 1 ELSE 0 END) as takeaway_orders,
                    SUM(items_subtotal_rs) as total_items_sales,
                    SUM(delivery_charges_rs) as total_delivery_charges,
                    SUM(discounts_rs) as total_discounts,
                    SUM(taxes_rs) as total_taxes,
                    SUM(grand_total_rs) as total_grand
                  FROM {$wpdb->prefix}zaikon_orders
                  WHERE created_at >= %s AND created_at <= %s";
        
        return $wpdb->get_row($wpdb->prepare($query, $date_from, $date_to));
    }
}
