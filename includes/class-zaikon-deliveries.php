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
        
        // Get existing columns in the table for defensive coding
        $table_name = $wpdb->prefix . 'zaikon_deliveries';
        $existing_columns = self::get_table_columns($table_name);
        
        // Build delivery data array with all fields
        $all_delivery_data = array(
            'order_id' => absint($data['order_id']),
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_phone' => sanitize_text_field($data['customer_phone']),
            'location_id' => isset($data['location_id']) ? absint($data['location_id']) : null,
            'location_name' => sanitize_text_field($data['location_name']),
            'distance_km' => floatval($data['distance_km']),
            'delivery_charges_rs' => floatval($data['delivery_charges_rs']),
            'is_free_delivery' => intval($data['is_free_delivery'] ?? 0),
            'special_instruction' => sanitize_text_field($data['special_instruction'] ?? ''),
            'delivery_instructions' => sanitize_text_field($data['delivery_instructions'] ?? ''),
            'assigned_rider_id' => isset($data['assigned_rider_id']) ? absint($data['assigned_rider_id']) : null,
            'delivery_status' => sanitize_text_field($data['delivery_status'] ?? 'pending'),
            'rider_payout_amount' => isset($data['rider_payout_amount']) ? floatval($data['rider_payout_amount']) : null,
            'rider_payout_slab' => isset($data['rider_payout_slab']) ? sanitize_text_field($data['rider_payout_slab']) : null,
            'payout_type' => isset($data['payout_type']) ? sanitize_text_field($data['payout_type']) : null,
            'fuel_multiplier' => isset($data['fuel_multiplier']) ? floatval($data['fuel_multiplier']) : 1.00,
            'payout_per_km_rate' => isset($data['payout_per_km_rate']) ? floatval($data['payout_per_km_rate']) : null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Optional columns that may not exist in older schemas
        $optional_columns = array(
            'delivery_instructions',
            'rider_payout_amount',
            'rider_payout_slab',
            'payout_type',
            'fuel_multiplier',
            'payout_per_km_rate'
        );
        
        // Filter data to only include columns that exist in the table
        $delivery_data = array();
        $missing_columns = array();
        
        foreach ($all_delivery_data as $column => $value) {
            if (in_array($column, $existing_columns)) {
                $delivery_data[$column] = $value;
            } elseif (in_array($column, $optional_columns)) {
                $missing_columns[] = $column;
            }
        }
        
        // Log warning if optional columns are missing
        if (!empty($missing_columns)) {
            error_log('ZAIKON: Warning - Missing columns in zaikon_deliveries table: ' . implode(', ', $missing_columns) . '. Please run database migration.');
        }
        
        // Build formats array dynamically based on included columns
        $formats = array();
        foreach ($delivery_data as $column => $value) {
            if (in_array($column, array('order_id', 'location_id', 'assigned_rider_id', 'is_free_delivery'))) {
                $formats[] = '%d';
            } elseif (in_array($column, array('distance_km', 'delivery_charges_rs', 'rider_payout_amount', 'fuel_multiplier', 'payout_per_km_rate'))) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        
        $result = $wpdb->insert(
            $table_name,
            $delivery_data,
            $formats
        );
        
        if (!$result) {
            // Log the actual database error for debugging
            error_log('ZAIKON: Failed to create delivery record. DB Error: ' . $wpdb->last_error);
            // Log query structure without sensitive data
            error_log('ZAIKON: Insert failed for table: ' . $table_name);
            error_log('ZAIKON: Number of fields: ' . count($delivery_data));
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get table columns for defensive coding
     */
    private static function get_table_columns($table_name) {
        global $wpdb;
        
        // Cache column list to avoid repeated queries
        static $column_cache = array();
        
        if (isset($column_cache[$table_name])) {
            return $column_cache[$table_name];
        }
        
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`");
        
        if ($columns) {
            $column_cache[$table_name] = $columns;
            return $columns;
        }
        
        // Return empty array if table doesn't exist or query fails
        return array();
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
        
        if (isset($data['rider_payout_amount'])) {
            $update_data['rider_payout_amount'] = floatval($data['rider_payout_amount']);
            $formats[] = '%f';
        }
        
        if (isset($data['rider_payout_slab'])) {
            $update_data['rider_payout_slab'] = sanitize_text_field($data['rider_payout_slab']);
            $formats[] = '%s';
        }
        
        if (isset($data['payout_type'])) {
            $update_data['payout_type'] = sanitize_text_field($data['payout_type']);
            $formats[] = '%s';
        }
        
        if (isset($data['fuel_multiplier'])) {
            $update_data['fuel_multiplier'] = floatval($data['fuel_multiplier']);
            $formats[] = '%f';
        }
        
        if (isset($data['payout_per_km_rate'])) {
            $update_data['payout_per_km_rate'] = floatval($data['payout_per_km_rate']);
            $formats[] = '%f';
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
