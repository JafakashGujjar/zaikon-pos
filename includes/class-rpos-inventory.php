<?php
/**
 * Inventory Management Class
 * 
 * Enterprise-grade inventory management with transaction safety,
 * full traceability, and fault tolerance.
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
             INNER JOIN {$wpdb->prefix}rpos_products p ON i.product_id = p.id
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
             INNER JOIN {$wpdb->prefix}rpos_products p ON i.product_id = p.id
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
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_inventory',
            array(
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit' => $unit,
                'cost_price' => $cost_price
            ),
            array('%d', '%d', '%s', '%f')
        );
        
        if ($result) {
            // Log inventory creation
            Zaikon_System_Events::log_inventory_movement($product_id, $quantity, 'initial_stock', array(
                'cost_price' => $cost_price,
                'unit' => $unit
            ));
        }
        
        return $result;
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
     * Adjust stock quantity with transaction safety and audit logging
     * 
     * @param int $product_id Product ID
     * @param float $change_amount Amount to add (positive) or subtract (negative)
     * @param string $reason Reason for adjustment
     * @param int|null $order_id Related order ID
     * @param int|null $user_id User making the change
     * @param string|null $expiry_date Expiry date for the stock
     * @return float|false New quantity on success, false on failure
     */
    public static function adjust_stock($product_id, $change_amount, $reason = '', $order_id = null, $user_id = null, $expiry_date = null) {
        global $wpdb;
        
        // Start transaction for atomic stock adjustment
        if (!RPOS_Database::begin_transaction()) {
            RPOS_Database::log_error('adjust_stock', 'Failed to start transaction', 'Product: ' . $product_id);
            return false;
        }
        
        try {
            // Get current inventory with row lock to prevent race conditions
            $inventory = RPOS_Database::lock_for_update('inventory', array('product_id' => $product_id));
            
            if (!$inventory) {
                // Create inventory record if doesn't exist
                self::create_for_product($product_id);
                $inventory = self::get_by_product($product_id);
                
                if (!$inventory) {
                    throw new Exception('Failed to create inventory record');
                }
            }
            
            // Calculate new quantity
            $old_quantity = floatval($inventory->quantity);
            $new_quantity = $old_quantity + floatval($change_amount);
            
            // Prevent negative stock (optional - can be configured)
            if ($new_quantity < 0) {
                // Log warning but allow negative stock for tracking purposes
                Zaikon_System_Events::log_warning('inventory', $product_id, 'negative_stock', 
                    'Stock went negative', array(
                        'old_quantity' => $old_quantity,
                        'change_amount' => $change_amount,
                        'new_quantity' => $new_quantity
                    ));
            }
            
            // Update inventory with retry for fault tolerance
            $update_result = RPOS_Database::with_retry(function() use ($wpdb, $product_id, $new_quantity) {
                return $wpdb->update(
                    $wpdb->prefix . 'rpos_inventory',
                    array('quantity' => $new_quantity),
                    array('product_id' => $product_id),
                    array('%f'),
                    array('%d')
                );
            });
            
            if ($update_result === false) {
                throw new Exception('Failed to update inventory: ' . $wpdb->last_error);
            }
            
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
            
            $movement_result = $wpdb->insert(
                $wpdb->prefix . 'rpos_stock_movements',
                $movement_data,
                $movement_format
            );
            
            if (!$movement_result) {
                throw new Exception('Failed to record stock movement: ' . $wpdb->last_error);
            }
            
            // Commit transaction
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit stock adjustment');
            }
            
            // Log inventory movement event
            Zaikon_System_Events::log_inventory_movement($product_id, $change_amount, $reason, array(
                'old_quantity' => $old_quantity,
                'new_quantity' => $new_quantity,
                'order_id' => $order_id
            ));
            
            return $new_quantity;
            
        } catch (Exception $e) {
            RPOS_Database::rollback('Stock adjustment failed: ' . $e->getMessage());
            
            Zaikon_System_Events::log_error('inventory', $product_id, 'stock_adjustment_failed', 
                $e->getMessage(), array(
                    'change_amount' => $change_amount,
                    'reason' => $reason
                ));
            
            return false;
        }
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
     * Deduct stock for order items with transaction support
     * 
     * Note: This method expects to be called within an existing transaction
     * from RPOS_Orders::deduct_stock_for_order()
     * 
     * @param int $order_id Order ID
     * @param array $items Order items
     */
    public static function deduct_for_order($order_id, $items) {
        if (empty($items)) {
            error_log('RPOS Inventory: deduct_for_order called with empty items for order #' . $order_id);
            return;
        }
        
        error_log('RPOS Inventory: Starting product stock deduction for order #' . $order_id . ' with ' . count($items) . ' items');
        
        foreach ($items as $item) {
            // Handle both object and array formats
            $product_id = is_object($item) ? $item->product_id : (isset($item['product_id']) ? $item['product_id'] : 0);
            $quantity = is_object($item) ? $item->quantity : (isset($item['quantity']) ? $item['quantity'] : 0);
            
            if (empty($product_id) || empty($quantity)) {
                error_log('RPOS Inventory: Skipping item with invalid product_id or quantity in order #' . $order_id);
                continue;
            }
            
            // Use direct database update when in transaction to avoid nested transaction issues
            if (RPOS_Database::in_transaction()) {
                $updated_quantity = self::adjust_stock_direct($product_id, -$quantity, 'Order #' . $order_id, $order_id);
            } else {
                $updated_quantity = self::adjust_stock($product_id, -$quantity, 'Order #' . $order_id, $order_id);
            }
            
            error_log('RPOS Inventory: Deducted ' . $quantity . ' from product #' . $product_id . ' for order #' . $order_id . '. New stock: ' . $updated_quantity);
        }
    }
    
    /**
     * Direct stock adjustment without transaction wrapper
     * For use within existing transactions
     * 
     * @param int $product_id Product ID
     * @param float $change_amount Amount to change
     * @param string $reason Reason for change
     * @param int|null $order_id Related order ID
     * @return float|false New quantity or false on failure
     */
    private static function adjust_stock_direct($product_id, $change_amount, $reason = '', $order_id = null) {
        global $wpdb;
        
        // Get current inventory
        $inventory = self::get_by_product($product_id);
        if (!$inventory) {
            // Create inventory record if doesn't exist
            self::create_for_product($product_id);
            $inventory = self::get_by_product($product_id);
        }
        
        if (!$inventory) {
            return false;
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
        $user_id = get_current_user_id();
        
        $wpdb->insert(
            $wpdb->prefix . 'rpos_stock_movements',
            array(
                'product_id' => $product_id,
                'change_amount' => floatval($change_amount),
                'reason' => sanitize_text_field($reason),
                'order_id' => $order_id,
                'user_id' => $user_id
            ),
            array('%d', '%f', '%s', '%d', '%d')
        );
        
        return $new_quantity;
    }
    
    /**
     * Get current stock level for a product
     * 
     * @param int $product_id Product ID
     * @return float Stock quantity
     */
    public static function get_stock_level($product_id) {
        $inventory = self::get_by_product($product_id);
        return $inventory ? floatval($inventory->quantity) : 0;
    }
    
    /**
     * Check if product has sufficient stock
     * 
     * @param int $product_id Product ID
     * @param float $required_quantity Required quantity
     * @return bool True if sufficient stock
     */
    public static function has_sufficient_stock($product_id, $required_quantity) {
        $current_stock = self::get_stock_level($product_id);
        return $current_stock >= $required_quantity;
    }
    
    /**
     * Get low stock products
     * 
     * @param int|null $threshold Low stock threshold (uses settings if null)
     * @return array Products with low stock
     */
    public static function get_low_stock_products($threshold = null) {
        global $wpdb;
        
        if ($threshold === null) {
            $threshold = RPOS_Settings::get('low_stock_threshold', 10);
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, p.name as product_name, p.sku 
             FROM {$wpdb->prefix}rpos_inventory i
             INNER JOIN {$wpdb->prefix}rpos_products p ON i.product_id = p.id
             WHERE i.quantity <= %d
             ORDER BY i.quantity ASC",
            $threshold
        ));
    }
    
    /**
     * Get inventory history for a product
     * 
     * @param int $product_id Product ID
     * @return array Audit events for the product
     */
    public static function get_inventory_history($product_id) {
        return Zaikon_System_Events::get_entity_events('inventory', $product_id);
    }
}
