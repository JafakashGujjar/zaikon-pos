<?php
/**
 * Orders Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Orders {
    
    /**
     * Generate unique order number
     */
    public static function generate_order_number() {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
    
    /**
     * Create order
     */
    public static function create($data) {
        global $wpdb;
        
        // Generate order number
        $order_number = self::generate_order_number();
        
        // Calculate totals
        $subtotal = floatval($data['subtotal'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);
        $total = $subtotal - $discount;
        
        // Insert order
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_orders',
            array(
                'order_number' => $order_number,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'cash_received' => floatval($data['cash_received'] ?? 0),
                'change_due' => floatval($data['change_due'] ?? 0),
                'status' => sanitize_text_field($data['status'] ?? 'new'),
                'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id())
            ),
            array('%s', '%f', '%f', '%f', '%f', '%f', '%s', '%d')
        );
        
        if (!$result) {
            return false;
        }
        
        $order_id = $wpdb->insert_id;
        
        // Insert order items
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $product = RPOS_Products::get($item['product_id']);
                $inventory = RPOS_Inventory::get_by_product($item['product_id']);
                
                $wpdb->insert(
                    $wpdb->prefix . 'rpos_order_items',
                    array(
                        'order_id' => $order_id,
                        'product_id' => $item['product_id'],
                        'product_name' => $product ? $product->name : '',
                        'quantity' => intval($item['quantity']),
                        'unit_price' => floatval($item['unit_price']),
                        'line_total' => floatval($item['line_total']),
                        'cost_price' => $inventory ? floatval($inventory->cost_price) : 0
                    ),
                    array('%d', '%d', '%s', '%d', '%f', '%f', '%f')
                );
            }
            
            // Deduct inventory if order is completed
            if (($data['status'] ?? 'new') === 'completed') {
                RPOS_Inventory::deduct_for_order($order_id, $data['items']);
            }
        }
        
        return $order_id;
    }
    
    /**
     * Get order by ID
     */
    public static function get($id) {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, u.display_name as cashier_name 
             FROM {$wpdb->prefix}rpos_orders o
             LEFT JOIN {$wpdb->users} u ON o.cashier_id = u.ID
             WHERE o.id = %d",
            $id
        ));
        
        if ($order) {
            $order->items = self::get_order_items($id);
        }
        
        return $order;
    }
    
    /**
     * Get order items
     */
    public static function get_order_items($order_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_order_items WHERE order_id = %d",
            $order_id
        ));
    }
    
    /**
     * Get all orders
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['status']) {
            $where[] = 'o.status = %s';
            $where_values[] = $args['status'];
        }
        
        if ($args['date_from']) {
            $where[] = 'o.created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where[] = 'o.created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $orderby = sanitize_sql_orderby('o.' . $args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'o.created_at DESC';
        }
        
        $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT o.*, u.display_name as cashier_name 
                 FROM {$wpdb->prefix}rpos_orders o
                 LEFT JOIN {$wpdb->users} u ON o.cashier_id = u.ID
                 WHERE {$where_clause}
                 ORDER BY {$orderby}{$limit_clause}",
                $where_values
            );
        } else {
            $query = "SELECT o.*, u.display_name as cashier_name 
                      FROM {$wpdb->prefix}rpos_orders o
                      LEFT JOIN {$wpdb->users} u ON o.cashier_id = u.ID
                      WHERE {$where_clause}
                      ORDER BY {$orderby}{$limit_clause}";
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update order status
     */
    public static function update_status($id, $status) {
        global $wpdb;
        
        $old_order = self::get($id);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'rpos_orders',
            array('status' => sanitize_text_field($status)),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        // If status changed to completed and inventory wasn't deducted yet
        if ($result && $status === 'completed' && $old_order && $old_order->status !== 'completed') {
            RPOS_Inventory::deduct_for_order($id, $old_order->items);
        }
        
        return $result;
    }
    
    /**
     * Get order count
     */
    public static function count($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders WHERE {$where_clause}",
                $where_values
            );
        } else {
            $query = "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders WHERE {$where_clause}";
        }
        
        return $wpdb->get_var($query);
    }
}
