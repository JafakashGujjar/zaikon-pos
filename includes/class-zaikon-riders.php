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
        
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $formats = array('%s', '%s', '%s', '%s', '%s');
        
        // Add payout fields if provided
        if (isset($data['payout_type'])) {
            $insert_data['payout_type'] = sanitize_text_field($data['payout_type']);
            $formats[] = '%s';
        }
        if (isset($data['per_delivery_rate'])) {
            $insert_data['per_delivery_rate'] = floatval($data['per_delivery_rate']);
            $formats[] = '%f';
        }
        if (isset($data['per_km_rate'])) {
            $insert_data['per_km_rate'] = floatval($data['per_km_rate']);
            $formats[] = '%f';
        }
        if (isset($data['base_rate'])) {
            $insert_data['base_rate'] = floatval($data['base_rate']);
            $formats[] = '%f';
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_riders',
            $insert_data,
            $formats
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
        
        // Add payout fields if provided
        if (isset($data['payout_type'])) {
            $update_data['payout_type'] = sanitize_text_field($data['payout_type']);
            $formats[] = '%s';
        }
        if (isset($data['per_delivery_rate'])) {
            $update_data['per_delivery_rate'] = floatval($data['per_delivery_rate']);
            $formats[] = '%f';
        }
        if (isset($data['per_km_rate'])) {
            $update_data['per_km_rate'] = floatval($data['per_km_rate']);
            $formats[] = '%f';
        }
        if (isset($data['base_rate'])) {
            $update_data['base_rate'] = floatval($data['base_rate']);
            $formats[] = '%f';
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
     * Calculate rider pay based on rider's payout model and distance
     * @param int $rider_id Rider ID
     * @param float $distance_km Distance in kilometers
     * @return float Calculated pay amount
     */
    public static function calculate_rider_pay($rider_id, $distance_km) {
        $rider = self::get($rider_id);
        if (!$rider) return 0;
        
        // Default values if fields don't exist (backward compatibility)
        $payout_type = isset($rider->payout_type) ? $rider->payout_type : 'per_km';
        $per_delivery_rate = isset($rider->per_delivery_rate) ? floatval($rider->per_delivery_rate) : 0;
        $per_km_rate = isset($rider->per_km_rate) ? floatval($rider->per_km_rate) : 10;
        $base_rate = isset($rider->base_rate) ? floatval($rider->base_rate) : 20;
        
        switch ($payout_type) {
            case 'per_delivery':
                return $per_delivery_rate;
            case 'per_km':
                return $base_rate + ($distance_km * $per_km_rate);
            case 'hybrid':
                return $per_delivery_rate + ($distance_km * $per_km_rate);
            default:
                return $base_rate + ($distance_km * $per_km_rate);
        }
    }
}
