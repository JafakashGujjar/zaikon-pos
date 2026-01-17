<?php
/**
 * Suppliers Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Suppliers {
    
    /**
     * Get all suppliers
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'orderby' => 'supplier_name',
            'order' => 'ASC',
            'is_active' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['is_active'] !== null) {
            $where[] = 'is_active = %d';
            $where_values[] = $args['is_active'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Whitelist allowed orderby columns
        $allowed_orderby = array('id', 'supplier_name', 'phone', 'rating', 'created_at');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'supplier_name';
        }
        
        // Sanitize order direction
        $order = strtoupper($args['order']);
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        $orderby = $args['orderby'] . ' ' . $order;
        
        $query = "SELECT * FROM {$wpdb->prefix}rpos_suppliers 
                  WHERE {$where_clause}
                  ORDER BY {$orderby}";
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            return $wpdb->get_results($query);
        }
    }
    
    /**
     * Get supplier by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_suppliers WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create supplier
     */
    public static function create($data) {
        global $wpdb;
        
        $defaults = array(
            'supplier_name' => '',
            'phone' => '',
            'address' => '',
            'rating' => null,
            'contact_person' => '',
            'gst_tax_id' => '',
            'is_active' => 1,
            'notes' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['supplier_name'])) {
            return false;
        }
        
        $insert_data = array(
            'supplier_name' => sanitize_text_field($data['supplier_name']),
            'phone' => sanitize_text_field($data['phone']),
            'address' => sanitize_textarea_field($data['address']),
            'contact_person' => sanitize_text_field($data['contact_person']),
            'gst_tax_id' => sanitize_text_field($data['gst_tax_id']),
            'is_active' => absint($data['is_active']),
            'notes' => sanitize_textarea_field($data['notes'])
        );
        
        $format = array('%s', '%s', '%s', '%s', '%s', '%d', '%s');
        
        if (isset($data['rating']) && $data['rating'] !== null && $data['rating'] !== '') {
            $rating_val = absint($data['rating']);
            // Validate rating range (1-5)
            if ($rating_val >= 1 && $rating_val <= 5) {
                $insert_data['rating'] = $rating_val;
                $format[] = '%d';
            }
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_suppliers',
            $insert_data,
            $format
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update supplier
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['supplier_name'])) {
            $update_data['supplier_name'] = sanitize_text_field($data['supplier_name']);
            $format[] = '%s';
        }
        
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
            $format[] = '%s';
        }
        
        if (isset($data['address'])) {
            $update_data['address'] = sanitize_textarea_field($data['address']);
            $format[] = '%s';
        }
        
        if (isset($data['rating'])) {
            $rating_val = ($data['rating'] !== null && $data['rating'] !== '') ? absint($data['rating']) : null;
            // Validate rating range (1-5) if provided
            if ($rating_val !== null && ($rating_val < 1 || $rating_val > 5)) {
                $rating_val = null;
            }
            $update_data['rating'] = $rating_val;
            $format[] = $rating_val !== null ? '%d' : '%s';
        }
        
        if (isset($data['contact_person'])) {
            $update_data['contact_person'] = sanitize_text_field($data['contact_person']);
            $format[] = '%s';
        }
        
        if (isset($data['gst_tax_id'])) {
            $update_data['gst_tax_id'] = sanitize_text_field($data['gst_tax_id']);
            $format[] = '%s';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = absint($data['is_active']);
            $format[] = '%d';
        }
        
        if (isset($data['notes'])) {
            $update_data['notes'] = sanitize_textarea_field($data['notes']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_suppliers',
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Delete supplier
     */
    public static function delete($id) {
        global $wpdb;
        
        // Check if supplier is used in any batches
        $used_in_batches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_ingredient_batches WHERE supplier_id = %d",
            $id
        ));
        
        if ($used_in_batches > 0) {
            return false; // Cannot delete if used in batches
        }
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_suppliers',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get supplier performance metrics
     */
    public static function get_performance_metrics($supplier_id, $days = 90) {
        global $wpdb;
        
        $date_from = RPOS_Timezone::now()->modify("-{$days} days")->format('Y-m-d');
        
        // Get total batches from this supplier
        $total_batches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_ingredient_batches 
             WHERE supplier_id = %d AND purchase_date >= %s",
            $supplier_id, $date_from
        ));
        
        // Get waste from batches by this supplier
        $waste_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT w.id)
             FROM {$wpdb->prefix}rpos_ingredient_waste w
             JOIN {$wpdb->prefix}rpos_ingredient_batches b ON w.batch_id = b.id
             WHERE b.supplier_id = %d AND w.created_at >= %s
             AND w.reason IN ('Expired', 'Spoiled')",
            $supplier_id, $date_from
        ));
        
        // Calculate quality score
        $quality_score = 0;
        if ($total_batches > 0) {
            $good_batches = $total_batches - $waste_count;
            $quality_score = ($good_batches / $total_batches) * 100;
        }
        
        // Get average cost per unit
        $avg_cost = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(cost_per_unit) FROM {$wpdb->prefix}rpos_ingredient_batches 
             WHERE supplier_id = %d AND purchase_date >= %s",
            $supplier_id, $date_from
        ));
        
        return array(
            'total_batches' => $total_batches,
            'waste_count' => $waste_count,
            'quality_score' => round($quality_score, 2),
            'avg_cost' => round($avg_cost, 2)
        );
    }
}
