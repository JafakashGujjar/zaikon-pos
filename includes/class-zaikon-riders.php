<?php
/**
 * Zaikon Riders Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Riders {
    
    /**
     * Get all riders
     */
    public static function get_all($active_only = false) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}zaikon_riders";
        
        if ($active_only) {
            $query .= " WHERE status = 'active'";
        }
        
        $query .= " ORDER BY name ASC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get rider by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_riders WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create rider
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_riders',
            array(
                'name' => sanitize_text_field($data['name']),
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'status' => sanitize_text_field($data['status'] ?? 'active'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update rider
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
            $formats[] = '%s';
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_riders',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete rider
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'zaikon_riders',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Calculate rider pay based on distance
     * This is a simple implementation - can be enhanced with more complex rules
     */
    public static function calculate_rider_pay($distance_km) {
        // Simple rule: Rs 20 base + Rs 10 per km
        $base_pay = 20;
        $per_km = 10;
        
        return $base_pay + ($distance_km * $per_km);
    }
}
