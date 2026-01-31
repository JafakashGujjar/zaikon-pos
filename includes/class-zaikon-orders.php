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
        
        // Map input data to database columns
        // Database columns use plural names: items_subtotal_rs, delivery_charges_rs, discounts_rs
        $order_data = array(
            'order_number' => sanitize_text_field($data['order_number']),
            'order_type' => sanitize_text_field($data['order_type'] ?? 'takeaway'),
            'items_subtotal_rs' => floatval($data['items_subtotal_rs'] ?? 0),
            'delivery_charges_rs' => floatval($data['delivery_charges_rs'] ?? 0),
            'discounts_rs' => floatval($data['discounts_rs'] ?? 0),
            'taxes_rs' => floatval($data['taxes_rs'] ?? 0),
            'grand_total_rs' => floatval($data['grand_total_rs']),
            'payment_status' => sanitize_text_field($data['payment_status'] ?? 'unpaid'),
            'payment_type' => sanitize_text_field($data['payment_type'] ?? 'cash'),
            'order_status' => sanitize_text_field($data['order_status'] ?? 'active'),
            'special_instructions' => isset($data['special_instructions']) ? sanitize_textarea_field($data['special_instructions']) : null,
            'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id()),
            'created_at' => RPOS_Timezone::current_utc_mysql(),
            'updated_at' => RPOS_Timezone::current_utc_mysql()
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_orders',
            $order_data,
            array('%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if (!$result) {
            // Log the actual database error for debugging
            error_log('ZAIKON: Failed to create order in zaikon_orders table. MySQL Error: ' . $wpdb->last_error);
            error_log('ZAIKON: Order data: ' . print_r($order_data, true));
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get order by ID
     */
    public static function get($id) {
        global $wpdb;
        
        // Use LEFT JOIN to fetch delivery details in a single query
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, d.id as delivery_id, d.customer_name, d.customer_phone, 
                    d.location_id, d.location_name, d.distance_km, d.delivery_charges_rs as delivery_charge,
                    d.is_free_delivery, d.special_instruction, d.delivery_status, d.assigned_rider_id
             FROM {$wpdb->prefix}zaikon_orders o
             LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON o.id = d.order_id
             WHERE o.id = %d",
            $id
        ));
        
        if ($order) {
            // Add items to order
            $order->items = Zaikon_Order_Items::get_by_order($id);
            
            // Format items for compatibility with frontend
            foreach ($order->items as &$item) {
                $item->quantity = $item->qty;
                $item->price = $item->unit_price_rs;
                $item->line_total = $item->line_total_rs;
            }
            
            // If delivery data exists, create a delivery object
            if ($order->delivery_id) {
                $order->delivery = (object) array(
                    'id' => $order->delivery_id,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'location_id' => $order->location_id,
                    'location_name' => $order->location_name,
                    'distance_km' => $order->distance_km,
                    'delivery_charges_rs' => $order->delivery_charge,
                    'is_free_delivery' => $order->is_free_delivery,
                    'special_instruction' => $order->special_instruction,
                    'delivery_status' => $order->delivery_status,
                    'assigned_rider_id' => $order->assigned_rider_id
                );
                
                // Clean up order object to avoid duplication
                unset($order->delivery_id, $order->customer_name, $order->customer_phone, 
                      $order->location_id, $order->location_name, $order->distance_km, 
                      $order->delivery_charge, $order->is_free_delivery, $order->special_instruction,
                      $order->delivery_status, $order->assigned_rider_id);
            }
        }
        
        return $order;
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
        
        $update_data['updated_at'] = RPOS_Timezone::current_utc_mysql();
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
     * 
     * Supports filtering by status (for KDS), order_type, date range, and payment status.
     * This is the unified order query service used by both KDS and order management.
     * 
     * @param array $args {
     *     Optional. Array of query arguments.
     *     @type string $status         Order status filter (e.g., 'pending', 'confirmed', 'cooking', 'ready')
     *     @type string $order_type     Order type filter (e.g., 'delivery', 'dine-in', 'takeaway')
     *     @type string $date_from      Start date for filtering (MySQL datetime format)
     *     @type string $date_to        End date for filtering (MySQL datetime format)
     *     @type string $payment_status Payment status filter (e.g., 'paid', 'unpaid')
     *     @type string $orderby        Column to order by (default: 'created_at')
     *     @type string $order          Sort direction: 'ASC' or 'DESC' (default: 'DESC')
     *     @type int    $limit          Number of results to return (default: 50)
     *     @type int    $offset         Number of results to skip (default: 0)
     * }
     * @return array Array of order objects with cashier names and items
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',              // Added for KDS compatibility
            'order_type' => '',
            'date_from' => '',
            'date_to' => '',
            'payment_status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        // Status filter (for KDS: 'new', 'cooking', 'ready', 'completed')
        // Maps to zaikon_orders.order_status field
        if (!empty($args['status'])) {
            $where[] = 'o.order_status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['order_type'])) {
            $where[] = 'o.order_type = %s';
            $where_values[] = $args['order_type'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'o.created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'o.created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if (!empty($args['payment_status'])) {
            $where[] = 'o.payment_status = %s';
            $where_values[] = $args['payment_status'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Validate and sanitize orderby and order
        $allowed_orderby = array('created_at', 'updated_at', 'order_number', 'grand_total_rs', 'order_status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Join with users table to get cashier name (KDS compatibility)
        $query = "SELECT o.*, u.display_name as cashier_name 
                  FROM {$wpdb->prefix}zaikon_orders o
                  LEFT JOIN {$wpdb->users} u ON o.cashier_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY o.{$orderby} {$order}
                  LIMIT %d OFFSET %d";
        
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        $orders = array();
        if (!empty($where_values)) {
            $orders = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $orders = $wpdb->get_results($query);
        }
        
        // Optimize: Load all items for all orders in a single query (prevents N+1)
        if (!empty($orders)) {
            $order_ids = array_map(function($order) { return $order->id; }, $orders);
            $order_ids_placeholder = implode(',', array_fill(0, count($order_ids), '%d'));
            
            $items_query = "SELECT * FROM {$wpdb->prefix}zaikon_order_items 
                           WHERE order_id IN ($order_ids_placeholder)
                           ORDER BY order_id, id";
            
            $all_items = $wpdb->get_results($wpdb->prepare($items_query, $order_ids));
            
            // Group items by order_id
            $items_by_order = array();
            foreach ($all_items as $item) {
                if (!isset($items_by_order[$item->order_id])) {
                    $items_by_order[$item->order_id] = array();
                }
                $items_by_order[$item->order_id][] = $item;
            }
            
            // Attach items to their respective orders
            foreach ($orders as &$order) {
                $order->items = isset($items_by_order[$order->id]) ? $items_by_order[$order->id] : array();
                
                // Map zaikon field names to rpos field names for backward compatibility
                // KDS expects certain field names from the old rpos_orders table
                if (!isset($order->status)) {
                    $order->status = $order->order_status; // Map order_status to status
                }
                if (!isset($order->subtotal)) {
                    $order->subtotal = $order->items_subtotal_rs; // Map items_subtotal_rs to subtotal
                }
                if (!isset($order->total)) {
                    $order->total = $order->grand_total_rs; // Map grand_total_rs to total
                }
                if (!isset($order->discount)) {
                    $order->discount = $order->discounts_rs; // Map discounts_rs to discount
                }
            }
        }
        
        return $orders;
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
