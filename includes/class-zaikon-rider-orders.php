<?php
/**
 * Zaikon Rider Orders Class
 * Manages rider-order lifecycle tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Rider_Orders {
    
    /**
     * Create rider-order assignment
     */
    public static function create($data) {
        global $wpdb;
        
        $insert_data = array(
            'order_id' => absint($data['order_id']),
            'rider_id' => absint($data['rider_id']),
            'status' => sanitize_text_field($data['status'] ?? 'assigned'),
            'created_at' => RPOS_Timezone::current_utc_mysql()
        );
        
        $formats = array('%d', '%d', '%s', '%s');
        
        // Optional fields
        if (isset($data['delivery_id'])) {
            $insert_data['delivery_id'] = absint($data['delivery_id']);
            $formats[] = '%d';
        }
        if (isset($data['assigned_at'])) {
            $insert_data['assigned_at'] = sanitize_text_field($data['assigned_at']);
            $formats[] = '%s';
        } else {
            $insert_data['assigned_at'] = RPOS_Timezone::current_utc_mysql();
            $formats[] = '%s';
        }
        if (isset($data['notes'])) {
            $insert_data['notes'] = sanitize_textarea_field($data['notes']);
            $formats[] = '%s';
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_rider_orders',
            $insert_data,
            $formats
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get single rider-order record by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_rider_orders WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get rider-order assignment by order ID
     */
    public static function get_by_order($order_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_rider_orders WHERE order_id = %d ORDER BY id DESC LIMIT 1",
            $order_id
        ));
    }
    
    /**
     * Get rider's assignments with optional filters
     */
    public static function get_by_rider($rider_id, $filters = array()) {
        global $wpdb;
        
        $query = "SELECT ro.*, o.order_number, o.created_at as order_created_at, 
                         d.customer_name, d.customer_phone, d.location_name, d.distance_km
                  FROM {$wpdb->prefix}zaikon_rider_orders ro
                  LEFT JOIN {$wpdb->prefix}zaikon_orders o ON ro.order_id = o.id
                  LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON ro.delivery_id = d.id
                  WHERE ro.rider_id = %d";
        
        $params = array($rider_id);
        
        // Add status filter
        if (!empty($filters['status'])) {
            $query .= " AND ro.status = %s";
            $params[] = $filters['status'];
        }
        
        // Add date range filter
        if (!empty($filters['date_from'])) {
            $query .= " AND ro.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND ro.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        $query .= " ORDER BY ro.created_at DESC";
        
        // Add limit if specified
        if (!empty($filters['limit'])) {
            $query .= " LIMIT %d";
            $params[] = intval($filters['limit']);
        }
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Update rider-order status with timestamp tracking
     */
    public static function update_status($id, $status, $notes = null) {
        global $wpdb;
        
        $update_data = array(
            'status' => sanitize_text_field($status),
            'updated_at' => RPOS_Timezone::current_utc_mysql()
        );
        
        // Set appropriate timestamp based on status
        switch ($status) {
            case 'assigned':
                $update_data['assigned_at'] = RPOS_Timezone::current_utc_mysql();
                break;
            case 'picked':
                $update_data['picked_at'] = RPOS_Timezone::current_utc_mysql();
                break;
            case 'delivered':
                $update_data['delivered_at'] = RPOS_Timezone::current_utc_mysql();
                break;
            case 'failed':
                $update_data['failed_at'] = RPOS_Timezone::current_utc_mysql();
                break;
        }
        
        if ($notes !== null) {
            $update_data['notes'] = sanitize_textarea_field($notes);
        }
        
        // Add failure reason for failed status
        if ($status === 'failed' && $notes !== null) {
            $update_data['failure_reason'] = sanitize_text_field($notes);
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_rider_orders',
            $update_data,
            array('id' => $id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
    }
    
    /**
     * Get pending assignments for a rider
     */
    public static function get_pending_for_rider($rider_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ro.*, o.order_number, o.created_at as order_created_at,
                    d.customer_name, d.customer_phone, d.location_name, d.distance_km
             FROM {$wpdb->prefix}zaikon_rider_orders ro
             LEFT JOIN {$wpdb->prefix}zaikon_orders o ON ro.order_id = o.id
             LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON ro.delivery_id = d.id
             WHERE ro.rider_id = %d 
             AND ro.status IN ('pending', 'assigned')
             ORDER BY ro.created_at ASC",
            $rider_id
        ));
    }
    
    /**
     * Get active deliveries (picked/on route) for a rider
     */
    public static function get_active_deliveries($rider_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ro.*, o.order_number, o.created_at as order_created_at,
                    d.customer_name, d.customer_phone, d.location_name, d.distance_km
             FROM {$wpdb->prefix}zaikon_rider_orders ro
             LEFT JOIN {$wpdb->prefix}zaikon_orders o ON ro.order_id = o.id
             LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON ro.delivery_id = d.id
             WHERE ro.rider_id = %d 
             AND ro.status = 'picked'
             ORDER BY ro.picked_at ASC",
            $rider_id
        ));
    }
    
    /**
     * Get all rider-order assignments with optional filters
     */
    public static function get_all($filters = array()) {
        global $wpdb;
        
        $query = "SELECT ro.*, 
                         r.name as rider_name, r.phone as rider_phone,
                         o.order_number, o.created_at as order_created_at,
                         d.customer_name, d.customer_phone, d.location_name, d.distance_km
                  FROM {$wpdb->prefix}zaikon_rider_orders ro
                  LEFT JOIN {$wpdb->prefix}zaikon_riders r ON ro.rider_id = r.id
                  LEFT JOIN {$wpdb->prefix}zaikon_orders o ON ro.order_id = o.id
                  LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON ro.delivery_id = d.id
                  WHERE 1=1";
        
        $params = array();
        
        // Add rider filter
        if (!empty($filters['rider_id'])) {
            $query .= " AND ro.rider_id = %d";
            $params[] = intval($filters['rider_id']);
        }
        
        // Add status filter
        if (!empty($filters['status'])) {
            $query .= " AND ro.status = %s";
            $params[] = $filters['status'];
        }
        
        // Add date range filter
        if (!empty($filters['date_from'])) {
            $query .= " AND ro.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND ro.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        $query .= " ORDER BY ro.created_at DESC";
        
        // Add limit if specified
        if (!empty($filters['limit'])) {
            $query .= " LIMIT %d";
            $params[] = intval($filters['limit']);
        }
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        
        return $wpdb->get_results($query);
    }
}
