<?php
/**
 * Orders Management Class
 * 
 * Enterprise-grade order management with transaction safety,
 * full traceability, and fault tolerance.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Orders {
    
    /**
     * Generate unique order number
     */
    public static function generate_order_number() {
        return 'ORD-' . RPOS_Timezone::now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
    
    /**
     * Create order with transaction safety
     * 
     * All order creation operations are wrapped in a transaction
     * to ensure data reliability and atomicity.
     * 
     * @param array $data Order data
     * @return int|false Order ID on success, false on failure
     */
    public static function create($data) {
        global $wpdb;
        
        // Validate that items array exists and is not empty
        if (empty($data['items'])) {
            RPOS_Database::log_error('create_order', 'Missing or empty items array');
            // Note: We allow order creation without items for edge cases,
            // but log the issue for visibility
        }
        
        // Generate order number
        $order_number = self::generate_order_number();
        
        // Calculate totals
        $subtotal = floatval($data['subtotal'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);
        $delivery_charge = floatval($data['delivery_charge'] ?? 0);
        $total = $subtotal + $delivery_charge - $discount;
        
        // Prepare order data
        $order_data = array(
            'order_number' => $order_number,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'cash_received' => floatval($data['cash_received'] ?? 0),
            'change_due' => floatval($data['change_due'] ?? 0),
            'status' => sanitize_text_field($data['status'] ?? 'new'),
            'payment_type' => sanitize_text_field($data['payment_type'] ?? 'cash'),
            'payment_status' => sanitize_text_field($data['payment_status'] ?? 'paid'),
            'order_type' => sanitize_text_field($data['order_type'] ?? 'dine-in'),
            'special_instructions' => sanitize_textarea_field($data['special_instructions'] ?? ''),
            'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id()),
            'created_at' => RPOS_Timezone::current_utc_mysql()
        );
        
        // Build formats array dynamically
        $formats = array('%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s');
        
        // Add delivery fields if it's a delivery order
        if (isset($data['is_delivery']) && $data['is_delivery']) {
            $order_data['is_delivery'] = 1;
            $order_data['delivery_charge'] = $delivery_charge;
            $order_data['area_id'] = isset($data['area_id']) ? absint($data['area_id']) : null;
            $order_data['customer_name'] = sanitize_text_field($data['customer_name'] ?? '');
            $order_data['customer_phone'] = sanitize_text_field($data['customer_phone'] ?? '');
            $formats = array_merge($formats, array('%d', '%f', '%d', '%s', '%s'));
        }
        
        // Start transaction for atomic operation
        if (!RPOS_Database::begin_transaction()) {
            RPOS_Database::log_error('create_order', 'Failed to start transaction');
            return false;
        }
        
        try {
            // Insert order with retry for fault tolerance
            $result = RPOS_Database::with_retry(function() use ($wpdb, $order_data, $formats) {
                return $wpdb->insert(
                    $wpdb->prefix . 'rpos_orders',
                    $order_data,
                    $formats
                );
            });
            
            if (!$result) {
                throw new Exception('Failed to insert order: ' . $wpdb->last_error);
            }
            
            $order_id = $wpdb->insert_id;
            
            // Insert order items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $product = RPOS_Products::get($item['product_id']);
                    $inventory = RPOS_Inventory::get_by_product($item['product_id']);
                    
                    $item_result = $wpdb->insert(
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
                    
                    if (!$item_result) {
                        throw new Exception('Failed to insert order item: ' . $wpdb->last_error);
                    }
                }
            }
            
            // Commit transaction
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit transaction');
            }
            
            // Log successful order creation (outside transaction for audit trail)
            Zaikon_System_Events::log('order', $order_id, 'create', array(
                'order_number' => $order_number,
                'order_type' => $order_data['order_type'],
                'total' => $total,
                'item_count' => count($data['items'] ?? array()),
                'payment_type' => $order_data['payment_type']
            ));
            
            // Deduct inventory if order is completed
            if (($data['status'] ?? 'new') === 'completed') {
                self::deduct_stock_for_order($order_id, $data['items']);
            }
            
            return $order_id;
            
        } catch (Exception $e) {
            RPOS_Database::rollback('Order creation failed: ' . $e->getMessage());
            
            // Log failure event
            Zaikon_System_Events::log_error('order', 0, 'create_failed', $e->getMessage(), array(
                'order_number' => $order_number,
                'error_trace' => $e->getTraceAsString()
            ));
            
            return false;
        }
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
            
            // Add compatibility fields for frontend
            foreach ($order->items as &$item) {
                if (!isset($item->price)) {
                    $item->price = $item->unit_price;
                }
                // line_total already exists in database, no mapping needed
            }
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
     * Note: This method does NOT filter by cashier_id, allowing kitchen staff
     * and other users with appropriate permissions to see all orders.
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
     * Update order status with transaction safety and audit logging
     * 
     * @param int $id Order ID
     * @param string $status New status
     * @param string $reason Optional reason for status change
     * @return bool True on success, false on failure
     */
    public static function update_status($id, $status, $reason = '') {
        global $wpdb;
        
        $old_order = self::get($id);
        
        if (!$old_order) {
            RPOS_Database::log_error('update_status', 'Order not found', 'Order ID: ' . $id);
            return false;
        }
        
        $old_status = $old_order->status;
        
        // If status is already the same, consider it a success (idempotent)
        if ($old_status === $status) {
            error_log('RPOS Orders: Order #' . $id . ' already has status "' . $status . '", no change needed');
            return true;
        }
        
        error_log('RPOS Orders: Updating order #' . $id . ' from "' . $old_status . '" to "' . $status . '"');
        
        // Start transaction for status update
        if (!RPOS_Database::begin_transaction()) {
            return false;
        }
        
        try {
            // Update status with retry for fault tolerance
            $result = RPOS_Database::with_retry(function() use ($wpdb, $id, $status) {
                return $wpdb->update(
                    $wpdb->prefix . 'rpos_orders',
                    array('status' => sanitize_text_field($status)),
                    array('id' => $id),
                    array('%s'),
                    array('%d')
                );
            });
            
            // $wpdb->update returns false on error, 0 if no rows changed, or number of rows changed
            if ($result === false) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }
            
            // Commit the status update
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit status update');
            }
            
            // Log status change event
            Zaikon_System_Events::log('order', $id, 'status_change', array(
                'old_status' => $old_status,
                'new_status' => $status,
                'reason' => $reason,
                'order_number' => $old_order->order_number
            ));
            
            // If status changed to completed, trigger stock deduction
            if ($status === 'completed') {
                error_log('RPOS Orders: Order #' . $id . ' completed, triggering stock deduction');
                self::deduct_stock_for_order($id, $old_order->items);
            }
            
            return true;
            
        } catch (Exception $e) {
            RPOS_Database::rollback('Status update failed: ' . $e->getMessage());
            
            Zaikon_System_Events::log_error('order', $id, 'status_update_failed', $e->getMessage(), array(
                'old_status' => $old_status,
                'new_status' => $status
            ));
            
            return false;
        }
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
    
    /**
     * Deduct stock for an order - centralized single-point deduction with transaction safety
     * Prevents double-run via ingredients_deducted flag
     * 
     * @param int $order_id The order ID to deduct stock for
     * @param array|null $order_items Optional array of order items. If null, will be loaded from database
     * @return bool|int False if already deducted, otherwise result of mark_ingredients_deducted
     */
    public static function deduct_stock_for_order($order_id, $order_items = null) {
        // Prevent double deduction with early check
        if (self::has_ingredients_deducted($order_id)) {
            error_log('RPOS Orders: Stock already deducted for order #' . $order_id . ', skipping');
            return false;
        }
        
        // Load order items if not provided
        if ($order_items === null || (is_array($order_items) && empty($order_items))) {
            $order_items = self::get_order_items($order_id);
            $item_count = is_array($order_items) ? count($order_items) : 0;
            error_log('RPOS Orders: Loaded ' . $item_count . ' items from DB for order #' . $order_id);
        }
        
        if (empty($order_items)) {
            error_log('RPOS Orders: No items to deduct for order #' . $order_id);
            // Still mark as deducted to prevent retries
            return self::mark_ingredients_deducted($order_id);
        }
        
        $item_count = count($order_items);
        error_log('RPOS Orders: Starting stock deduction for order #' . $order_id . ' with ' . $item_count . ' items');
        
        // Start transaction for atomic stock deduction
        if (!RPOS_Database::begin_transaction()) {
            RPOS_Database::log_error('deduct_stock', 'Failed to start transaction', 'Order: ' . $order_id);
            return false;
        }
        
        try {
            // Double-check deduction flag within transaction to prevent race condition
            if (self::has_ingredients_deducted($order_id)) {
                RPOS_Database::rollback('Stock already deducted (race condition prevented)');
                return false;
            }
            
            // Deduct product stock
            RPOS_Inventory::deduct_for_order($order_id, $order_items);
            
            // Deduct ingredient stock
            RPOS_Recipes::deduct_ingredients_for_order($order_id, $order_items);
            
            // Track cylinder consumption (enterprise feature)
            RPOS_Gas_Cylinders::record_consumption($order_id, $order_items);
            
            // Track fryer oil usage (enterprise feature)
            RPOS_Fryer_Usage::record_usage_from_order($order_id, $order_items);
            
            // Mark as deducted within the same transaction
            $result = self::mark_ingredients_deducted($order_id);
            
            // Commit the transaction
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit stock deduction');
            }
            
            // Log successful stock deduction
            Zaikon_System_Events::log('order', $order_id, 'stock_deducted', array(
                'item_count' => $item_count
            ));
            
            error_log('RPOS Orders: Completed stock deduction for order #' . $order_id);
            
            return $result;
            
        } catch (Exception $e) {
            RPOS_Database::rollback('Stock deduction failed: ' . $e->getMessage());
            
            Zaikon_System_Events::log_error('order', $order_id, 'stock_deduction_failed', $e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Mark ingredients as deducted for an order
     */
    public static function mark_ingredients_deducted($order_id) {
        global $wpdb;
        
        $order_id = absint($order_id);
        if ($order_id <= 0) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_orders',
            array('ingredients_deducted' => 1),
            array('id' => $order_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Check if ingredients have been deducted for an order
     */
    public static function has_ingredients_deducted($order_id) {
        global $wpdb;
        
        $order_id = absint($order_id);
        if ($order_id <= 0) {
            return false;
        }
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT ingredients_deducted FROM {$wpdb->prefix}rpos_orders WHERE id = %d",
            $order_id
        ));
        
        return intval($result) === 1;
    }
    
    /**
     * Cancel an order with reason tracking
     * 
     * @param int $id Order ID
     * @param string $reason Cancellation reason
     * @return bool True on success
     */
    public static function cancel($id, $reason = '') {
        $order = self::get($id);
        
        if (!$order) {
            return false;
        }
        
        // Cannot cancel completed orders
        if ($order->status === 'completed') {
            Zaikon_System_Events::log_warning('order', $id, 'cancel_rejected', 
                'Cannot cancel completed order');
            return false;
        }
        
        // Log cancellation with reason
        Zaikon_System_Events::log('order', $id, 'cancelled', array(
            'previous_status' => $order->status,
            'reason' => $reason,
            'order_number' => $order->order_number,
            'total' => $order->total
        ));
        
        return self::update_status($id, 'cancelled', $reason);
    }
    
    /**
     * Get order history (audit trail) for an order
     * 
     * @param int $order_id Order ID
     * @return array Array of audit events
     */
    public static function get_order_history($order_id) {
        return Zaikon_System_Events::get_entity_events('order', $order_id);
    }
}
