<?php
/**
 * Zaikon Delivery Charge Slabs Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Delivery_Charge_Slabs {
    
    /**
     * Get all slabs
     */
    public static function get_all($active_only = false) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}zaikon_delivery_charge_slabs";
        
        if ($active_only) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY min_km ASC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get slab by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_delivery_charge_slabs WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get charge for distance
     */
    public static function get_charge_for_distance($distance_km) {
        global $wpdb;
        
        $slab = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_delivery_charge_slabs 
             WHERE is_active = 1 
             AND min_km <= %f 
             AND max_km >= %f
             ORDER BY min_km ASC
             LIMIT 1",
            $distance_km,
            $distance_km
        ));
        
        return $slab ? floatval($slab->charge_rs) : 0;
    }
    
    /**
     * Create slab
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_delivery_charge_slabs',
            array(
                'min_km' => floatval($data['min_km']),
                'max_km' => floatval($data['max_km']),
                'charge_rs' => floatval($data['charge_rs']),
                'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%f', '%f', '%f', '%d', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update slab
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['min_km'])) {
            $update_data['min_km'] = floatval($data['min_km']);
            $formats[] = '%f';
        }
        
        if (isset($data['max_km'])) {
            $update_data['max_km'] = floatval($data['max_km']);
            $formats[] = '%f';
        }
        
        if (isset($data['charge_rs'])) {
            $update_data['charge_rs'] = floatval($data['charge_rs']);
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
            $wpdb->prefix . 'zaikon_delivery_charge_slabs',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete slab
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'zaikon_delivery_charge_slabs',
            array('id' => $id),
            array('%d')
        );
    }
}
