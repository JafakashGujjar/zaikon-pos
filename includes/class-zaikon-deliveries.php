<?php
/**
 * Zaikon Deliveries Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Deliveries {
    
    /**
     * Create delivery record
     */
    public static function create($data) {
        global $wpdb;
        
        $delivery_data = array(
            'order_id' => absint($data['order_id']),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_phone' => sanitize_text_field($data['customer_phone']),
            'location_id' => isset($data['location_id']) ? absint($data['location_id']) : null,
            'location_name' => sanitize_text_field($data['location_name']),
            'distance_km' => floatval($data['distance_km']),
            'delivery_charges_rs' => floatval($data['delivery_charges_rs']),
            'is_free_delivery' => intval($data['is_free_delivery'] ?? 0),
            'special_instruction' => sanitize_text_field($data['special_instruction'] ?? ''),
            'assigned_rider_id' => isset($data['assigned_rider_id']) ? absint($data['assigned_rider_id']) : null,
            'delivery_status' => sanitize_text_field($data['delivery_status'] ?? 'pending'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_deliveries',
            $delivery_data,
            array('%d', '%s', '%s', '%d', '%s', '%f', '%f', '%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get delivery by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_deliveries WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get delivery by order ID
     */
    public static function get_by_order($order_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_deliveries WHERE order_id = %d",
            $order_id
        ));
    }
    
    /**
     * Update delivery
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['assigned_rider_id'])) {
            $update_data['assigned_rider_id'] = absint($data['assigned_rider_id']);
            $formats[] = '%d';
        }
        
        if (isset($data['delivery_status'])) {
            $update_data['delivery_status'] = sanitize_text_field($data['delivery_status']);
            $formats[] = '%s';
        }
        
        if (isset($data['delivered_at'])) {
            $update_data['delivered_at'] = sanitize_text_field($data['delivered_at']);
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_deliveries',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Get all deliveries with filters
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'rider_id' => '',
            'location_id' => '',
            'delivery_status' => '',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if (!empty($args['rider_id'])) {
            $where[] = 'assigned_rider_id = %d';
            $where_values[] = $args['rider_id'];
        }
        
        if (!empty($args['location_id'])) {
            $where[] = 'location_id = %d';
            $where_values[] = $args['location_id'];
        }
        
        if (!empty($args['delivery_status'])) {
            $where[] = 'delivery_status = %s';
            $where_values[] = $args['delivery_status'];
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}zaikon_deliveries 
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY created_at DESC
                  LIMIT %d OFFSET %d";
        
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get delivery revenue summary
     */
    public static function get_delivery_summary($date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT 
                    COUNT(*) as total_deliveries,
                    SUM(delivery_charges_rs) as total_delivery_revenue,
                    SUM(distance_km) as total_distance_km,
                    SUM(CASE WHEN is_free_delivery = 1 THEN 1 ELSE 0 END) as free_deliveries_count,
                    AVG(delivery_charges_rs) as avg_delivery_charge,
                    AVG(distance_km) as avg_distance_km
                  FROM {$wpdb->prefix}zaikon_deliveries
                  WHERE created_at >= %s AND created_at <= %s";
        
        return $wpdb->get_row($wpdb->prepare($query, $date_from, $date_to));
    }
    
    /**
     * Get delivery summary by location
     */
    public static function get_location_summary($date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT 
                    location_name,
                    COUNT(*) as delivery_count,
                    SUM(delivery_charges_rs) as total_charges,
                    SUM(distance_km) as total_km,
                    AVG(delivery_charges_rs) as avg_charge
                  FROM {$wpdb->prefix}zaikon_deliveries
                  WHERE created_at >= %s AND created_at <= %s
                  GROUP BY location_name
                  ORDER BY delivery_count DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to));
    }
    
    /**
     * Get customer delivery analytics
     */
    public static function get_customer_analytics($date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT 
                    d.customer_phone,
                    d.customer_name,
                    COUNT(d.id) as delivery_orders_count,
                    SUM(d.delivery_charges_rs) as total_delivery_charges,
                    MIN(d.created_at) as first_delivery_date,
                    MAX(d.created_at) as last_delivery_date,
                    SUM(o.grand_total_rs) as total_amount_spent
                  FROM {$wpdb->prefix}zaikon_deliveries d
                  INNER JOIN {$wpdb->prefix}zaikon_orders o ON d.order_id = o.id
                  WHERE d.created_at >= %s AND d.created_at <= %s
                  GROUP BY d.customer_phone, d.customer_name
                  ORDER BY delivery_orders_count DESC, total_amount_spent DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to));
    }
}
