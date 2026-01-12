<?php
/**
 * Zaikon Rider Payouts Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Rider_Payouts {
    
    /**
     * Create payout record
     */
    public static function create($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_rider_payouts',
            array(
                'delivery_id' => absint($data['delivery_id']),
                'rider_id' => absint($data['rider_id']),
                'rider_pay_rs' => floatval($data['rider_pay_rs']),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%f', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get payout by delivery ID
     */
    public static function get_by_delivery($delivery_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_rider_payouts WHERE delivery_id = %d",
            $delivery_id
        ));
    }
    
    /**
     * Get all payouts for a rider in date range
     */
    public static function get_rider_payouts($rider_id, $date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT p.*, d.order_id, d.distance_km, d.location_name
                  FROM {$wpdb->prefix}zaikon_rider_payouts p
                  INNER JOIN {$wpdb->prefix}zaikon_deliveries d ON p.delivery_id = d.id
                  WHERE p.rider_id = %d
                  AND p.created_at >= %s AND p.created_at <= %s
                  ORDER BY p.created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $rider_id, $date_from, $date_to));
    }
    
    /**
     * Get total payout for rider in date range
     */
    public static function get_rider_total_payout($rider_id, $date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT SUM(rider_pay_rs) as total_payout
                  FROM {$wpdb->prefix}zaikon_rider_payouts
                  WHERE rider_id = %d
                  AND created_at >= %s AND created_at <= %s";
        
        $result = $wpdb->get_var($wpdb->prepare($query, $rider_id, $date_from, $date_to));
        
        return $result ? floatval($result) : 0;
    }
}
