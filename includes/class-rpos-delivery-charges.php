<?php
/**
 * Delivery Charges Management Class
 * 
 * @deprecated This class is deprecated in favor of Zaikon_Delivery_Calculator
 * @see Zaikon_Delivery_Calculator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @deprecated Use Zaikon_Delivery_Calculator instead
 */
class RPOS_Delivery_Charges {
    
    /**
     * Get all delivery charges
     */
    public static function get_all($active_only = false) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}rpos_delivery_charges";
        
        if ($active_only) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY distance_from ASC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get delivery charge by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_delivery_charges WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get charge by distance
     */
    public static function get_charge_by_distance($distance) {
        global $wpdb;
        
        $charge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_delivery_charges 
             WHERE is_active = 1 
             AND distance_from <= %f 
             AND distance_to >= %f
             ORDER BY distance_from ASC
             LIMIT 1",
            $distance,
            $distance
        ));
        
        return $charge ? floatval($charge->charge_amount) : 0;
    }
    
    /**
     * Calculate delivery charge
     */
    public static function calculate_charge($subtotal, $area_id, $minimum_free_delivery_amount = 0) {
        // If subtotal meets minimum for free delivery
        if ($minimum_free_delivery_amount > 0 && $subtotal >= $minimum_free_delivery_amount) {
            return 0;
        }
        
        // Get area distance
        $area = RPOS_Delivery_Areas::get($area_id);
        if (!$area) {
            return 0;
        }
        
        // Get charge by distance
        return self::get_charge_by_distance($area->distance_value);
    }
    
    /**
     * Create delivery charge rule
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_delivery_charges',
            array(
                'distance_from' => floatval($data['distance_from']),
                'distance_to' => floatval($data['distance_to']),
                'charge_amount' => floatval($data['charge_amount']),
                'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1
            ),
            array('%f', '%f', '%f', '%d')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update delivery charge rule
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['distance_from'])) {
            $update_data['distance_from'] = floatval($data['distance_from']);
            $formats[] = '%f';
        }
        
        if (isset($data['distance_to'])) {
            $update_data['distance_to'] = floatval($data['distance_to']);
            $formats[] = '%f';
        }
        
        if (isset($data['charge_amount'])) {
            $update_data['charge_amount'] = floatval($data['charge_amount']);
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
            $wpdb->prefix . 'rpos_delivery_charges',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete delivery charge rule
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_delivery_charges',
            array('id' => $id),
            array('%d')
        );
    }
}
