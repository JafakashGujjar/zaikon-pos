<?php
/**
 * Zaikon Delivery Locations Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Delivery_Locations {
    
    /**
     * Get all locations
     */
    public static function get_all($active_only = false) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}zaikon_delivery_locations";
        
        if ($active_only) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY name ASC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get location by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_delivery_locations WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create location
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_delivery_locations',
            array(
                'name' => sanitize_text_field($data['name']),
                'distance_km' => floatval($data['distance_km']),
                'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%f', '%d', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update location
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['distance_km'])) {
            $update_data['distance_km'] = floatval($data['distance_km']);
            $formats[] = '%f';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_delivery_locations',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete location
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'zaikon_delivery_locations',
            array('id' => $id),
            array('%d')
        );
    }
}
