<?php
/**
 * Ingredient Batches Management Class - FIFO/FEFO Support
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Batches {
    
    /**
     * Get all batches
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'purchase_date',
            'order' => 'DESC',
            'ingredient_id' => null,
            'status' => null,
            'limit' => 100
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['ingredient_id']) {
            $where[] = 'b.ingredient_id = %d';
            $where_values[] = $args['ingredient_id'];
        }
        
        if ($args['status']) {
            $where[] = 'b.status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Whitelist allowed orderby columns
        $allowed_orderby = array('id', 'batch_number', 'purchase_date', 'expiry_date', 'quantity_remaining');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'purchase_date';
        }
        
        // Sanitize order direction
        $order = strtoupper($args['order']);
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'DESC';
        }
        
        $orderby = 'b.' . $args['orderby'] . ' ' . $order;
        
        $query = "SELECT b.*, i.name as ingredient_name, i.unit, s.supplier_name
                  FROM {$wpdb->prefix}rpos_ingredient_batches b
                  LEFT JOIN {$wpdb->prefix}rpos_ingredients i ON b.ingredient_id = i.id
                  LEFT JOIN {$wpdb->prefix}rpos_suppliers s ON b.supplier_id = s.id
                  WHERE {$where_clause}
                  ORDER BY {$orderby}
                  LIMIT %d";
        
        $where_values[] = $args['limit'];
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Get batch by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, i.name as ingredient_name, i.unit, s.supplier_name
             FROM {$wpdb->prefix}rpos_ingredient_batches b
             LEFT JOIN {$wpdb->prefix}rpos_ingredients i ON b.ingredient_id = i.id
             LEFT JOIN {$wpdb->prefix}rpos_suppliers s ON b.supplier_id = s.id
             WHERE b.id = %d",
            $id
        ));
    }
    
    /**
     * Get batch by batch number
     */
    public static function get_by_batch_number($batch_number) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_ingredient_batches WHERE batch_number = %s",
            $batch_number
        ));
    }
    
    /**
     * Create batch
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'batch_number' => self::generate_batch_number(),
            'ingredient_id' => 0,
            'supplier_id' => null,
            'purchase_date' => date('Y-m-d'),
            'manufacturing_date' => null,
            'expiry_date' => null,
            'cost_per_unit' => 0,
            'quantity_purchased' => 0,
            'quantity_remaining' => 0,
            'purchase_invoice_url' => '',
            'notes' => '',
            'status' => 'active',
            'created_by' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['ingredient_id']) || empty($data['quantity_purchased'])) {
            return false;
        }
        
        // Set quantity_remaining to quantity_purchased if not specified
        if (empty($data['quantity_remaining'])) {
            $data['quantity_remaining'] = $data['quantity_purchased'];
        }
        
        $insert_data = array(
            'batch_number' => sanitize_text_field($data['batch_number']),
            'ingredient_id' => absint($data['ingredient_id']),
            'purchase_date' => sanitize_text_field($data['purchase_date']),
            'cost_per_unit' => floatval($data['cost_per_unit']),
            'quantity_purchased' => floatval($data['quantity_purchased']),
            'quantity_remaining' => floatval($data['quantity_remaining']),
            'notes' => sanitize_textarea_field($data['notes']),
            'status' => sanitize_text_field($data['status']),
            'created_by' => absint($data['created_by'])
        );
        
        $format = array('%s', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%d');
        
        if (!empty($data['supplier_id'])) {
            $insert_data['supplier_id'] = absint($data['supplier_id']);
            $format[] = '%d';
        }
        
        if (!empty($data['manufacturing_date'])) {
            $insert_data['manufacturing_date'] = sanitize_text_field($data['manufacturing_date']);
            $format[] = '%s';
        }
        
        if (!empty($data['expiry_date'])) {
            $insert_data['expiry_date'] = sanitize_text_field($data['expiry_date']);
            $format[] = '%s';
        }
        
        if (!empty($data['purchase_invoice_url'])) {
            $insert_data['purchase_invoice_url'] = esc_url_raw($data['purchase_invoice_url']);
            $format[] = '%s';
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_ingredient_batches',
            $insert_data,
            $format
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update batch
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        $allowed_fields = array(
            'supplier_id' => '%d',
            'manufacturing_date' => '%s',
            'expiry_date' => '%s',
            'cost_per_unit' => '%f',
            'quantity_remaining' => '%f',
            'purchase_invoice_url' => '%s',
            'notes' => '%s',
            'status' => '%s'
        );
        
        foreach ($allowed_fields as $field => $field_format) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $format[] = $field_format;
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_ingredient_batches',
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Generate unique batch number
     */
    public static function generate_batch_number() {
        return 'BATCH-' . date('YmdHis') . '-' . wp_rand(1000, 9999);
    }
    
    /**
     * Get available batches for ingredient consumption (FIFO or FEFO)
     */
    public static function get_available_batches($ingredient_id, $strategy = 'FEFO') {
        global $wpdb;
        
        $order_by = ($strategy === 'FIFO') ? 'purchase_date ASC' : 'expiry_date ASC, purchase_date ASC';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_ingredient_batches 
             WHERE ingredient_id = %d 
             AND status = 'active' 
             AND quantity_remaining > 0
             ORDER BY {$order_by}",
            $ingredient_id
        ));
    }
    
    /**
     * Consume quantity from batches using FIFO/FEFO strategy
     */
    public static function consume_from_batches($ingredient_id, $quantity, $movement_type = 'Consumption', $reference_id = null, $notes = '', $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Get consumption strategy from settings
        $strategy = RPOS_Inventory_Settings::get('consumption_strategy', 'FEFO');
        
        // Get available batches
        $batches = self::get_available_batches($ingredient_id, $strategy);
        
        if (empty($batches)) {
            error_log("RPOS: No available batches for ingredient ID {$ingredient_id}");
            return false;
        }
        
        $remaining_to_consume = floatval($quantity);
        $consumed_batches = array();
        
        foreach ($batches as $batch) {
            if ($remaining_to_consume <= 0) {
                break;
            }
            
            $available = floatval($batch->quantity_remaining);
            $to_consume = min($remaining_to_consume, $available);
            
            // Update batch quantity
            $new_quantity = $available - $to_consume;
            $new_status = ($new_quantity <= 0) ? 'depleted' : 'active';
            
            $wpdb->update(
                $wpdb->prefix . 'rpos_ingredient_batches',
                array(
                    'quantity_remaining' => $new_quantity,
                    'status' => $new_status
                ),
                array('id' => $batch->id),
                array('%f', '%s'),
                array('%d')
            );
            
            // Record movement with batch reference
            $wpdb->insert(
                $wpdb->prefix . 'rpos_ingredient_movements',
                array(
                    'ingredient_id' => $ingredient_id,
                    'batch_id' => $batch->id,
                    'change_amount' => -$to_consume,
                    'movement_type' => $movement_type,
                    'reference_id' => $reference_id,
                    'notes' => $notes,
                    'user_id' => $user_id
                ),
                array('%d', '%d', '%f', '%s', '%d', '%s', '%d')
            );
            
            $consumed_batches[] = array(
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'consumed' => $to_consume
            );
            
            $remaining_to_consume -= $to_consume;
        }
        
        if ($remaining_to_consume > 0) {
            error_log("RPOS: Warning - Could not consume full quantity for ingredient ID {$ingredient_id}. Remaining: {$remaining_to_consume}");
        }
        
        return $consumed_batches;
    }
    
    /**
     * Get earliest expiry date for ingredient (for display)
     */
    public static function get_earliest_expiry($ingredient_id) {
        global $wpdb;
        
        $strategy = RPOS_Inventory_Settings::get('consumption_strategy', 'FEFO');
        $order_by = ($strategy === 'FIFO') ? 'purchase_date ASC' : 'expiry_date ASC';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT expiry_date FROM {$wpdb->prefix}rpos_ingredient_batches 
             WHERE ingredient_id = %d 
             AND status = 'active' 
             AND quantity_remaining > 0
             AND expiry_date IS NOT NULL
             ORDER BY {$order_by}
             LIMIT 1",
            $ingredient_id
        ));
    }
    
    /**
     * Get batches expiring soon
     */
    public static function get_expiring_batches($days = 7) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, i.name as ingredient_name, i.unit, s.supplier_name,
                    DATEDIFF(b.expiry_date, CURDATE()) as days_until_expiry
             FROM {$wpdb->prefix}rpos_ingredient_batches b
             LEFT JOIN {$wpdb->prefix}rpos_ingredients i ON b.ingredient_id = i.id
             LEFT JOIN {$wpdb->prefix}rpos_suppliers s ON b.supplier_id = s.id
             WHERE b.expiry_date IS NOT NULL 
             AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
             AND b.status = 'active'
             AND b.quantity_remaining > 0
             ORDER BY b.expiry_date ASC",
            $days
        ));
    }
    
    /**
     * Calculate total inventory valuation
     */
    public static function get_inventory_valuation($ingredient_id = null) {
        global $wpdb;
        
        $where = "b.status = 'active' AND b.quantity_remaining > 0";
        $where_values = array();
        
        if ($ingredient_id) {
            $where .= " AND b.ingredient_id = %d";
            $where_values[] = $ingredient_id;
        }
        
        $query = "SELECT SUM(b.quantity_remaining * b.cost_per_unit) as total_value
                  FROM {$wpdb->prefix}rpos_ingredient_batches b
                  WHERE {$where}";
        
        if (!empty($where_values)) {
            return floatval($wpdb->get_var($wpdb->prepare($query, $where_values)));
        } else {
            return floatval($wpdb->get_var($query));
        }
    }
    
    /**
     * Get weighted average cost per unit for an ingredient from available batches
     */
    public static function get_weighted_average_cost($ingredient_id) {
        $batches = self::get_available_batches(
            $ingredient_id, 
            RPOS_Inventory_Settings::get('consumption_strategy', 'FEFO')
        );
        
        if (empty($batches)) {
            return 0;
        }
        
        $total_cost_value = 0;
        $total_quantity = 0;
        
        foreach ($batches as $batch) {
            $batch_qty = floatval($batch->quantity_remaining);
            $batch_cost = floatval($batch->cost_per_unit);
            $total_cost_value += $batch_qty * $batch_cost;
            $total_quantity += $batch_qty;
        }
        
        return $total_quantity > 0 ? ($total_cost_value / $total_quantity) : 0;
    }
}
