<?php
/**
 * Delivery Riders Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Riders {
    
    /**
     * Get all delivery riders
     */
    public static function get_all_riders() {
        $users = get_users(array(
            'role' => 'delivery_rider',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        return $users;
    }
    
    /**
     * Get rider by ID
     */
    public static function get_rider($user_id) {
        $user = get_user_by('id', $user_id);
        
        if ($user && in_array('delivery_rider', (array) $user->roles)) {
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get orders assigned to a rider
     */
    public static function get_rider_orders($rider_id, $status = null) {
        global $wpdb;
        
        $where = array('rider_id = %d', 'is_delivery = 1');
        $where_values = array($rider_id);
        
        if ($status) {
            $where[] = 'delivery_status = %s';
            $where_values[] = $status;
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}rpos_orders 
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Get today's orders for a rider
     */
    public static function get_today_orders($rider_id) {
        global $wpdb;
        
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $query = "SELECT o.*, 
                  da.name as area_name,
                  da.distance_value as area_distance
                  FROM {$wpdb->prefix}rpos_orders o
                  LEFT JOIN {$wpdb->prefix}rpos_delivery_areas da ON o.area_id = da.id
                  WHERE o.rider_id = %d 
                  AND o.is_delivery = 1
                  AND o.created_at >= %s 
                  AND o.created_at <= %s
                  ORDER BY o.created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $rider_id, $today_start, $today_end));
    }
    
    /**
     * Get pending orders for a rider (including today's and undelivered)
     */
    public static function get_pending_orders($rider_id) {
        global $wpdb;
        
        $query = "SELECT o.*, 
                  da.name as area_name,
                  da.distance_value as area_distance
                  FROM {$wpdb->prefix}rpos_orders o
                  LEFT JOIN {$wpdb->prefix}rpos_delivery_areas da ON o.area_id = da.id
                  WHERE o.rider_id = %d 
                  AND o.is_delivery = 1
                  AND (o.delivery_status IS NULL OR o.delivery_status != 'delivered')
                  ORDER BY o.created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $rider_id));
    }
    
    /**
     * Assign order to rider
     */
    public static function assign_order($order_id, $rider_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_orders',
            array('rider_id' => $rider_id),
            array('id' => $order_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Update order delivery status
     */
    public static function update_delivery_status($order_id, $status) {
        global $wpdb;
        
        $update_data = array('delivery_status' => $status);
        
        // If marking as out for delivery or delivered, record the timestamp
        if ($status === 'delivered') {
            // For delivered, we might want to track completion time
            // but ready_at is for kitchen ready time
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_orders',
            $update_data,
            array('id' => $order_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Update delivery distance
     */
    public static function update_delivery_km($order_id, $km) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_orders',
            array('delivery_km' => floatval($km)),
            array('id' => $order_id),
            array('%f'),
            array('%d')
        );
    }
    
    /**
     * Get rider statistics
     */
    public static function get_rider_stats($rider_id, $date_from = null, $date_to = null) {
        global $wpdb;
        
        if (!$date_from) {
            $date_from = date('Y-m-d 00:00:00');
        }
        if (!$date_to) {
            $date_to = date('Y-m-d 23:59:59');
        }
        
        $stats = array(
            'total_deliveries' => 0,
            'delivered' => 0,
            'out_for_delivery' => 0,
            'pending' => 0,
            'total_km' => 0,
            'total_charges' => 0
        );
        
        // Total deliveries
        $stats['total_deliveries'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders 
             WHERE rider_id = %d AND is_delivery = 1
             AND created_at >= %s AND created_at <= %s",
            $rider_id, $date_from, $date_to
        ));
        
        // Delivered count
        $stats['delivered'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders 
             WHERE rider_id = %d AND is_delivery = 1
             AND delivery_status = 'delivered'
             AND created_at >= %s AND created_at <= %s",
            $rider_id, $date_from, $date_to
        ));
        
        // Out for delivery
        $stats['out_for_delivery'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders 
             WHERE rider_id = %d AND is_delivery = 1
             AND delivery_status = 'out_for_delivery'
             AND created_at >= %s AND created_at <= %s",
            $rider_id, $date_from, $date_to
        ));
        
        // Pending
        $stats['pending'] = $stats['total_deliveries'] - $stats['delivered'] - $stats['out_for_delivery'];
        
        // Total km
        $stats['total_km'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(delivery_km) FROM {$wpdb->prefix}rpos_orders 
             WHERE rider_id = %d AND is_delivery = 1
             AND created_at >= %s AND created_at <= %s",
            $rider_id, $date_from, $date_to
        )) ?: 0;
        
        // Total charges collected
        $stats['total_charges'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(delivery_charge) FROM {$wpdb->prefix}rpos_orders 
             WHERE rider_id = %d AND is_delivery = 1
             AND delivery_status = 'delivered'
             AND created_at >= %s AND created_at <= %s",
            $rider_id, $date_from, $date_to
        )) ?: 0;
        
        return $stats;
    }
}
