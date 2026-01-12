<?php
/**
 * Delivery Areas Management Class
 * 
 * @deprecated This class is deprecated in favor of Zaikon_Delivery_Locations
 * @see Zaikon_Delivery_Locations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @deprecated Use Zaikon_Delivery_Locations instead
 */
class RPOS_Delivery_Areas {
    
    /**
     * Get all delivery areas
     */
    public static function get_all($active_only = false) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}rpos_delivery_areas";
        
        if ($active_only) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY name ASC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get active delivery areas
     */
    public static function get_active() {
        return self::get_all(true);
    }
    
    /**
     * Get delivery area by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_delivery_areas WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create delivery area
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_delivery_areas',
            array(
                'name' => sanitize_text_field($data['name']),
                'distance_value' => floatval($data['distance_value']),
                'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1
            ),
            array('%s', '%f', '%d')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update delivery area
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['distance_value'])) {
            $update_data['distance_value'] = floatval($data['distance_value']);
            $formats[] = '%f';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_delivery_areas',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete delivery area
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_delivery_areas',
            array('id' => $id),
            array('%d')
        );
    }
}
