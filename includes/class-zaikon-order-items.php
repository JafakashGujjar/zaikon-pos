<?php
/**
 * Zaikon Order Items Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Order_Items {
    
    /**
     * Create order item
     */
    public static function create($data) {
        global $wpdb;
        
        $item_data = array(
            'order_id' => absint($data['order_id']),
            'product_id' => isset($data['product_id']) ? absint($data['product_id']) : null,
            'product_name' => sanitize_text_field($data['product_name']),
            'qty' => intval($data['qty']),
            'unit_price_rs' => floatval($data['unit_price_rs']),
            'line_total_rs' => floatval($data['line_total_rs']),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_order_items',
            $item_data,
            array('%d', '%d', '%s', '%d', '%f', '%f', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get items for an order
     */
    public static function get_by_order($order_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_order_items WHERE order_id = %d",
            $order_id
        ));
    }
    
    /**
     * Get product-wise sales summary
     */
    public static function get_product_sales($date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT 
                    oi.product_id,
                    oi.product_name,
                    SUM(oi.qty) as total_qty,
                    SUM(oi.line_total_rs) as total_revenue
                  FROM {$wpdb->prefix}zaikon_order_items oi
                  INNER JOIN {$wpdb->prefix}zaikon_orders o ON oi.order_id = o.id
                  WHERE o.created_at >= %s AND o.created_at <= %s
                  GROUP BY oi.product_id, oi.product_name
                  ORDER BY total_revenue DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to));
    }
}
