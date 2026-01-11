<?php
/**
 * Ingredients Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Ingredients {
    
    /**
     * Get all ingredients
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Whitelist allowed orderby columns
        $allowed_orderby = array('id', 'name', 'unit', 'current_stock_quantity', 'cost_per_unit', 'created_at', 'updated_at');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'name';
        }
        
        // Sanitize order direction
        $order = strtoupper($args['order']);
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $orderby = $args['orderby'] . ' ' . $order;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_ingredients 
             ORDER BY {$orderby}"
        );
    }
    
    /**
     * Get ingredient by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_ingredients WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create ingredient
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'name' => '',
            'unit' => 'pcs',
            'current_stock_quantity' => 0,
            'cost_per_unit' => 0,
            'purchasing_date' => null,
            'expiry_date' => null,
            'supplier_name' => null,
            'supplier_rating' => null,
            'supplier_phone' => null,
            'supplier_location' => null,
            'reorder_level' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['name'])) {
            return false;
        }
        
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'unit' => sanitize_text_field($data['unit']),
            'current_stock_quantity' => floatval($data['current_stock_quantity']),
            'cost_per_unit' => floatval($data['cost_per_unit']),
            'reorder_level' => floatval($data['reorder_level'])
        );
        
        $format = array('%s', '%s', '%f', '%f', '%f');
        
        if (!empty($data['purchasing_date'])) {
            $insert_data['purchasing_date'] = sanitize_text_field($data['purchasing_date']);
            $format[] = '%s';
        }
        
        if (!empty($data['expiry_date'])) {
            $insert_data['expiry_date'] = sanitize_text_field($data['expiry_date']);
            $format[] = '%s';
        }
        
        if (!empty($data['supplier_name'])) {
            $insert_data['supplier_name'] = sanitize_text_field($data['supplier_name']);
            $format[] = '%s';
        }
        
        if (isset($data['supplier_rating']) && $data['supplier_rating'] !== null && $data['supplier_rating'] !== '') {
            $insert_data['supplier_rating'] = absint($data['supplier_rating']);
            $format[] = '%d';
        }
        
        if (!empty($data['supplier_phone'])) {
            $insert_data['supplier_phone'] = sanitize_text_field($data['supplier_phone']);
            $format[] = '%s';
        }
        
        if (!empty($data['supplier_location'])) {
            $insert_data['supplier_location'] = sanitize_textarea_field($data['supplier_location']);
            $format[] = '%s';
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_ingredients',
            $insert_data,
            $format
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update ingredient
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['unit'])) {
            $update_data['unit'] = sanitize_text_field($data['unit']);
            $format[] = '%s';
        }
        
        if (isset($data['current_stock_quantity'])) {
            $update_data['current_stock_quantity'] = floatval($data['current_stock_quantity']);
            $format[] = '%f';
        }
        
        if (isset($data['cost_per_unit'])) {
            $update_data['cost_per_unit'] = floatval($data['cost_per_unit']);
            $format[] = '%f';
        }
        
        if (isset($data['purchasing_date'])) {
            $update_data['purchasing_date'] = !empty($data['purchasing_date']) ? sanitize_text_field($data['purchasing_date']) : null;
            $format[] = '%s';
        }
        
        if (isset($data['expiry_date'])) {
            $update_data['expiry_date'] = !empty($data['expiry_date']) ? sanitize_text_field($data['expiry_date']) : null;
            $format[] = '%s';
        }
        
        if (isset($data['supplier_name'])) {
            $update_data['supplier_name'] = !empty($data['supplier_name']) ? sanitize_text_field($data['supplier_name']) : null;
            $format[] = '%s';
        }
        
        if (isset($data['supplier_rating'])) {
            $update_data['supplier_rating'] = ($data['supplier_rating'] !== null && $data['supplier_rating'] !== '') ? absint($data['supplier_rating']) : null;
            $format[] = '%d';
        }
        
        if (isset($data['supplier_phone'])) {
            $update_data['supplier_phone'] = !empty($data['supplier_phone']) ? sanitize_text_field($data['supplier_phone']) : null;
            $format[] = '%s';
        }
        
        if (isset($data['supplier_location'])) {
            $update_data['supplier_location'] = !empty($data['supplier_location']) ? sanitize_textarea_field($data['supplier_location']) : null;
            $format[] = '%s';
        }
        
        if (isset($data['reorder_level'])) {
            $update_data['reorder_level'] = floatval($data['reorder_level']);
            $format[] = '%f';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_ingredients',
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Delete ingredient
     */
    public static function delete($id) {
        global $wpdb;
        
        // Check if ingredient is used in any recipes
        $used_in_recipes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_product_recipes WHERE ingredient_id = %d",
            $id
        ));
        
        if ($used_in_recipes > 0) {
            return false; // Cannot delete if used in recipes
        }
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_ingredients',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Adjust ingredient stock
     */
    public static function adjust_stock($ingredient_id, $change_amount, $movement_type = 'Adjustment', $reference_id = null, $notes = '', $user_id = null) {
        global $wpdb;
        
        // Get current ingredient
        $ingredient = self::get($ingredient_id);
        if (!$ingredient) {
            return false;
        }
        
        // Calculate new quantity
        $new_quantity = floatval($ingredient->current_stock_quantity) + floatval($change_amount);
        
        // Don't allow negative stock
        if ($new_quantity < 0) {
            $new_quantity = 0;
        }
        
        // Update ingredient stock
        $wpdb->update(
            $wpdb->prefix . 'rpos_ingredients',
            array('current_stock_quantity' => $new_quantity),
            array('id' => $ingredient_id),
            array('%f'),
            array('%d')
        );
        
        // Record stock movement
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'rpos_ingredient_movements',
            array(
                'ingredient_id' => $ingredient_id,
                'change_amount' => floatval($change_amount),
                'movement_type' => sanitize_text_field($movement_type),
                'reference_id' => $reference_id,
                'notes' => sanitize_textarea_field($notes),
                'user_id' => $user_id
            ),
            array('%d', '%f', '%s', '%d', '%s', '%d')
        );
        
        return $new_quantity;
    }
    
    /**
     * Get ingredient movements
     */
    public static function get_movements($ingredient_id = null, $date_from = null, $date_to = null, $limit = 100) {
        global $wpdb;
        
        $where = array('1=1');
        $where_values = array();
        
        if ($ingredient_id) {
            $where[] = 'im.ingredient_id = %d';
            $where_values[] = $ingredient_id;
        }
        
        if ($date_from) {
            $where[] = 'im.created_at >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'im.created_at <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT im.*, i.name as ingredient_name, i.unit, u.display_name as user_name
                  FROM {$wpdb->prefix}rpos_ingredient_movements im
                  LEFT JOIN {$wpdb->prefix}rpos_ingredients i ON im.ingredient_id = i.id
                  LEFT JOIN {$wpdb->users} u ON im.user_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY im.created_at DESC
                  LIMIT %d";
        
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Get ingredient usage report
     */
    public static function get_usage_report($date_from = null, $date_to = null) {
        global $wpdb;
        
        $where_values = array();
        
        if ($date_from && $date_to) {
            // Both dates provided - use BETWEEN
            $purchased_case = "COALESCE(SUM(CASE WHEN im.movement_type = 'Purchase' AND im.created_at BETWEEN %s AND %s THEN im.change_amount ELSE 0 END), 0) as total_purchased";
            $consumed_case = "COALESCE(SUM(CASE WHEN im.movement_type IN ('Consumption', 'Sale') AND im.created_at BETWEEN %s AND %s THEN ABS(im.change_amount) ELSE 0 END), 0) as total_consumed";
            $where_values = array($date_from, $date_to, $date_from, $date_to);
        } elseif ($date_from) {
            // Only start date
            $purchased_case = "COALESCE(SUM(CASE WHEN im.movement_type = 'Purchase' AND im.created_at >= %s THEN im.change_amount ELSE 0 END), 0) as total_purchased";
            $consumed_case = "COALESCE(SUM(CASE WHEN im.movement_type IN ('Consumption', 'Sale') AND im.created_at >= %s THEN ABS(im.change_amount) ELSE 0 END), 0) as total_consumed";
            $where_values = array($date_from, $date_from);
        } elseif ($date_to) {
            // Only end date
            $purchased_case = "COALESCE(SUM(CASE WHEN im.movement_type = 'Purchase' AND im.created_at <= %s THEN im.change_amount ELSE 0 END), 0) as total_purchased";
            $consumed_case = "COALESCE(SUM(CASE WHEN im.movement_type IN ('Consumption', 'Sale') AND im.created_at <= %s THEN ABS(im.change_amount) ELSE 0 END), 0) as total_consumed";
            $where_values = array($date_to, $date_to);
        } else {
            // No date filtering
            $purchased_case = "COALESCE(SUM(CASE WHEN im.movement_type = 'Purchase' THEN im.change_amount ELSE 0 END), 0) as total_purchased";
            $consumed_case = "COALESCE(SUM(CASE WHEN im.movement_type IN ('Consumption', 'Sale') THEN ABS(im.change_amount) ELSE 0 END), 0) as total_consumed";
        }
        
        $query = "SELECT 
                    i.id,
                    i.name,
                    i.unit,
                    i.current_stock_quantity,
                    {$purchased_case},
                    {$consumed_case}
                  FROM {$wpdb->prefix}rpos_ingredients i
                  LEFT JOIN {$wpdb->prefix}rpos_ingredient_movements im ON i.id = im.ingredient_id
                  GROUP BY i.id
                  ORDER BY i.name ASC";
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            return $wpdb->get_results($query);
        }
    }
    
    /**
     * Log waste/spoilage
     */
    public static function log_waste($ingredient_id, $quantity, $reason, $notes = '', $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Insert waste record
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_ingredient_waste',
            array(
                'ingredient_id' => $ingredient_id,
                'quantity' => floatval($quantity),
                'reason' => sanitize_text_field($reason),
                'notes' => sanitize_textarea_field($notes),
                'user_id' => $user_id
            ),
            array('%d', '%f', '%s', '%s', '%d')
        );
        
        if ($result) {
            // Deduct from stock using adjust_stock with 'Waste' movement type
            self::adjust_stock(
                $ingredient_id,
                -floatval($quantity),
                'Waste',
                $wpdb->insert_id,
                $reason . (!empty($notes) ? ': ' . $notes : ''),
                $user_id
            );
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get waste history
     */
    public static function get_waste_history($ingredient_id = null, $date_from = null, $date_to = null, $limit = 100) {
        global $wpdb;
        
        $where = array('1=1');
        $where_values = array();
        
        if ($ingredient_id) {
            $where[] = 'w.ingredient_id = %d';
            $where_values[] = $ingredient_id;
        }
        
        if ($date_from) {
            $where[] = 'w.created_at >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'w.created_at <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT w.*, i.name as ingredient_name, i.unit, u.display_name as user_name
                  FROM {$wpdb->prefix}rpos_ingredient_waste w
                  LEFT JOIN {$wpdb->prefix}rpos_ingredients i ON w.ingredient_id = i.id
                  LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
                  WHERE {$where_clause}
                  ORDER BY w.created_at DESC
                  LIMIT %d";
        
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
}
