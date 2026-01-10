<?php
/**
 * Gas Cylinders Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Gas_Cylinders {
    
    /**
     * Get all cylinder types
     */
    public static function get_all_types() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_gas_cylinder_types ORDER BY name ASC"
        );
    }
    
    /**
     * Get cylinder type by ID
     */
    public static function get_type($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_gas_cylinder_types WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create cylinder type
     */
    public static function create_type($data) {
        global $wpdb;
        
        if (empty($data['name'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_gas_cylinder_types',
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : ''
            ),
            array('%s', '%s')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update cylinder type
     */
    public static function update_type($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_gas_cylinder_types',
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Delete cylinder type
     */
    public static function delete_type($id) {
        global $wpdb;
        
        // Check if type is used in any cylinders
        $used = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_gas_cylinders WHERE cylinder_type_id = %d",
            $id
        ));
        
        if ($used > 0) {
            return false; // Cannot delete if used
        }
        
        // Delete product mappings first
        $wpdb->delete(
            $wpdb->prefix . 'rpos_gas_cylinder_product_map',
            array('cylinder_type_id' => $id),
            array('%d')
        );
        
        // Delete type
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_gas_cylinder_types',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get products mapped to a cylinder type
     */
    public static function get_product_mappings($cylinder_type_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}rpos_gas_cylinder_product_map 
             WHERE cylinder_type_id = %d",
            $cylinder_type_id
        ));
    }
    
    /**
     * Set product mappings for a cylinder type
     */
    public static function set_product_mappings($cylinder_type_id, $product_ids) {
        global $wpdb;
        
        // Delete existing mappings
        $wpdb->delete(
            $wpdb->prefix . 'rpos_gas_cylinder_product_map',
            array('cylinder_type_id' => $cylinder_type_id),
            array('%d')
        );
        
        // Insert new mappings
        if (!empty($product_ids)) {
            foreach ($product_ids as $product_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'rpos_gas_cylinder_product_map',
                    array(
                        'cylinder_type_id' => $cylinder_type_id,
                        'product_id' => absint($product_id)
                    ),
                    array('%d', '%d')
                );
            }
        }
        
        return true;
    }
    
    /**
     * Get all cylinders with optional filters
     */
    public static function get_all_cylinders($filters = array()) {
        global $wpdb;
        
        $where = array();
        $params = array();
        
        if (isset($filters['status'])) {
            $where[] = "c.status = %s";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['cylinder_type_id'])) {
            $where[] = "c.cylinder_type_id = %d";
            $params[] = $filters['cylinder_type_id'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT c.*, t.name as type_name 
                  FROM {$wpdb->prefix}rpos_gas_cylinders c
                  LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
                  {$where_clause}
                  ORDER BY c.created_at DESC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get single cylinder
     */
    public static function get_cylinder($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, t.name as type_name 
             FROM {$wpdb->prefix}rpos_gas_cylinders c
             LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
             WHERE c.id = %d",
            $id
        ));
    }
    
    /**
     * Create new cylinder (check only one active per type)
     */
    public static function create_cylinder($data) {
        global $wpdb;
        
        if (empty($data['cylinder_type_id']) || empty($data['start_date'])) {
            return false;
        }
        
        // Check if there's already an active cylinder of this type
        $active_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_gas_cylinders 
             WHERE cylinder_type_id = %d AND status = 'active'",
            $data['cylinder_type_id']
        ));
        
        if ($active_exists > 0) {
            return false; // Cannot add new active cylinder if one already exists
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_gas_cylinders',
            array(
                'cylinder_type_id' => absint($data['cylinder_type_id']),
                'purchase_date' => !empty($data['purchase_date']) ? sanitize_text_field($data['purchase_date']) : null,
                'cost' => isset($data['cost']) ? floatval($data['cost']) : 0.00,
                'start_date' => sanitize_text_field($data['start_date']),
                'end_date' => null,
                'status' => 'active',
                'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
                'created_by' => get_current_user_id()
            ),
            array('%d', '%s', '%f', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Finish cylinder (mark as finished)
     */
    public static function finish_cylinder($id, $end_date) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_gas_cylinders',
            array(
                'end_date' => sanitize_text_field($end_date),
                'status' => 'finished'
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get cylinder usage report (sales during cylinder's active period)
     */
    public static function get_cylinder_usage_report($cylinder_id) {
        global $wpdb;
        
        // Get cylinder details
        $cylinder = self::get_cylinder($cylinder_id);
        
        if (!$cylinder) {
            return false;
        }
        
        // Get product mappings for this cylinder type
        $product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}rpos_gas_cylinder_product_map 
             WHERE cylinder_type_id = %d",
            $cylinder->cylinder_type_id
        ));
        
        if (empty($product_ids)) {
            return array(
                'cylinder' => $cylinder,
                'products' => array(),
                'total_sales' => 0
            );
        }
        
        // Build date range
        $date_from = $cylinder->start_date . ' 00:00:00';
        $date_to = $cylinder->end_date ? ($cylinder->end_date . ' 23:59:59') : date('Y-m-d H:i:s');
        
        // Get sales for mapped products during cylinder period
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $params = array_merge($product_ids, array($date_from, $date_to));
        
        $query = "SELECT 
                    p.name as product_name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.line_total) as total_sales
                  FROM {$wpdb->prefix}rpos_order_items oi
                  INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
                  INNER JOIN {$wpdb->prefix}rpos_products p ON oi.product_id = p.id
                  WHERE oi.product_id IN ($placeholders)
                  AND o.created_at >= %s
                  AND o.created_at <= %s
                  AND o.status NOT IN ('cancelled', 'refunded')
                  GROUP BY oi.product_id, p.name
                  ORDER BY total_sales DESC";
        
        $products = $wpdb->get_results($wpdb->prepare($query, $params));
        
        $total_sales = 0;
        foreach ($products as $product) {
            $total_sales += floatval($product->total_sales);
        }
        
        return array(
            'cylinder' => $cylinder,
            'products' => $products,
            'total_sales' => $total_sales
        );
    }
}
