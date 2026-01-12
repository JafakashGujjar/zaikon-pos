<?php
/**
 * Zaikon Rider Fuel Logs Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Rider_Fuel_Logs {
    
    /**
     * Create fuel log entry
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_rider_fuel_logs',
            array(
                'rider_id' => absint($data['rider_id']),
                'amount_rs' => floatval($data['amount_rs']),
                'liters' => isset($data['liters']) ? floatval($data['liters']) : null,
                'date' => sanitize_text_field($data['date']),
                'notes' => sanitize_text_field($data['notes'] ?? ''),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%f', '%f', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get fuel log by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_rider_fuel_logs WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Update fuel log
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['amount_rs'])) {
            $update_data['amount_rs'] = floatval($data['amount_rs']);
            $formats[] = '%f';
        }
        
        if (isset($data['liters'])) {
            $update_data['liters'] = floatval($data['liters']);
            $formats[] = '%f';
        }
        
        if (isset($data['date'])) {
            $update_data['date'] = sanitize_text_field($data['date']);
            $formats[] = '%s';
        }
        
        if (isset($data['notes'])) {
            $update_data['notes'] = sanitize_text_field($data['notes']);
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_rider_fuel_logs',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete fuel log
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'zaikon_rider_fuel_logs',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get all fuel logs for a rider in date range
     */
    public static function get_rider_fuel_logs($rider_id, $date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}zaikon_rider_fuel_logs
                  WHERE rider_id = %d
                  AND date >= %s AND date <= %s
                  ORDER BY date DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $rider_id, $date_from, $date_to));
    }
    
    /**
     * Get total fuel cost for rider in date range
     */
    public static function get_rider_total_fuel_cost($rider_id, $date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT SUM(amount_rs) as total_fuel_cost
                  FROM {$wpdb->prefix}zaikon_rider_fuel_logs
                  WHERE rider_id = %d
                  AND date >= %s AND date <= %s";
        
        $result = $wpdb->get_var($wpdb->prepare($query, $rider_id, $date_from, $date_to));
        
        return $result ? floatval($result) : 0;
    }
}
