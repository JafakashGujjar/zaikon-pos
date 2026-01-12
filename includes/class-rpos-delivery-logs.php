<?php
/**
 * Delivery Logs Management Class
 * 
 * @deprecated This class is deprecated in favor of Zaikon_Deliveries
 * @see Zaikon_Deliveries
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @deprecated Use Zaikon_Deliveries instead
 */
class RPOS_Delivery_Logs {
    
    /**
     * Get all delivery logs
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'rider_id' => '',
            'bike_id' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['date_from']) {
            $where[] = 'date >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where[] = 'date <= %s';
            $where_values[] = $args['date_to'];
        }
        
        if ($args['rider_id']) {
            $where[] = 'rider_id = %d';
            $where_values[] = $args['rider_id'];
        }
        
        if ($args['bike_id']) {
            $where[] = 'bike_id = %s';
            $where_values[] = $args['bike_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'date DESC';
        }
        
        $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rpos_delivery_logs 
                 WHERE {$where_clause}
                 ORDER BY {$orderby}{$limit_clause}",
                $where_values
            );
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}rpos_delivery_logs 
                      WHERE {$where_clause}
                      ORDER BY {$orderby}{$limit_clause}";
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get delivery log by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_delivery_logs WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create delivery log
     */
    public static function create($data) {
        global $wpdb;
        
        // Calculate total km if not provided
        $km_start = floatval($data['km_start'] ?? 0);
        $km_end = floatval($data['km_end'] ?? 0);
        $total_km = $km_end - $km_start;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_delivery_logs',
            array(
                'date' => sanitize_text_field($data['date']),
                'rider_id' => !empty($data['rider_id']) ? intval($data['rider_id']) : null,
                'rider_name' => sanitize_text_field($data['rider_name'] ?? ''),
                'bike_id' => sanitize_text_field($data['bike_id'] ?? ''),
                'fuel_amount' => floatval($data['fuel_amount'] ?? 0),
                'fuel_unit' => sanitize_text_field($data['fuel_unit'] ?? 'liters'),
                'km_start' => $km_start,
                'km_end' => $km_end,
                'total_km' => $total_km,
                'deliveries_count' => intval($data['deliveries_count'] ?? 0),
                'notes' => sanitize_textarea_field($data['notes'] ?? '')
            ),
            array('%s', '%d', '%s', '%s', '%f', '%s', '%f', '%f', '%f', '%d', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update delivery log
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['date'])) {
            $update_data['date'] = sanitize_text_field($data['date']);
            $formats[] = '%s';
        }
        
        if (isset($data['rider_id'])) {
            $update_data['rider_id'] = !empty($data['rider_id']) ? intval($data['rider_id']) : null;
            $formats[] = '%d';
        }
        
        if (isset($data['rider_name'])) {
            $update_data['rider_name'] = sanitize_text_field($data['rider_name']);
            $formats[] = '%s';
        }
        
        if (isset($data['bike_id'])) {
            $update_data['bike_id'] = sanitize_text_field($data['bike_id']);
            $formats[] = '%s';
        }
        
        if (isset($data['fuel_amount'])) {
            $update_data['fuel_amount'] = floatval($data['fuel_amount']);
            $formats[] = '%f';
        }
        
        if (isset($data['fuel_unit'])) {
            $update_data['fuel_unit'] = sanitize_text_field($data['fuel_unit']);
            $formats[] = '%s';
        }
        
        if (isset($data['km_start'])) {
            $update_data['km_start'] = floatval($data['km_start']);
            $formats[] = '%f';
        }
        
        if (isset($data['km_end'])) {
            $update_data['km_end'] = floatval($data['km_end']);
            $formats[] = '%f';
        }
        
        // Recalculate total_km if either km value is updated
        if (isset($data['km_start']) || isset($data['km_end'])) {
            $current = self::get($id);
            $km_start = isset($data['km_start']) ? floatval($data['km_start']) : floatval($current->km_start);
            $km_end = isset($data['km_end']) ? floatval($data['km_end']) : floatval($current->km_end);
            $update_data['total_km'] = $km_end - $km_start;
            $formats[] = '%f';
        }
        
        if (isset($data['deliveries_count'])) {
            $update_data['deliveries_count'] = intval($data['deliveries_count']);
            $formats[] = '%d';
        }
        
        if (isset($data['notes'])) {
            $update_data['notes'] = sanitize_textarea_field($data['notes']);
            $formats[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_delivery_logs',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete delivery log
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_delivery_logs',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get daily report with aggregated data
     */
    public static function get_daily_report($date_from, $date_to, $rider_id = null, $bike_id = null) {
        global $wpdb;
        
        $where = array("l.date >= %s", "l.date <= %s");
        $where_values = array($date_from, $date_to);
        
        if ($rider_id) {
            $where[] = 'l.rider_id = %d';
            $where_values[] = $rider_id;
        }
        
        if ($bike_id) {
            $where[] = 'l.bike_id = %s';
            $where_values[] = $bike_id;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get logs with delivery charge totals from orders
        $query = $wpdb->prepare(
            "SELECT 
                l.*,
                COALESCE(
                    (SELECT SUM(o.delivery_charge)
                     FROM {$wpdb->prefix}rpos_orders o
                     WHERE o.is_delivery = 1
                     AND DATE(o.created_at) = l.date
                     " . ($rider_id ? "AND (l.rider_name = o.customer_name OR l.rider_id IS NOT NULL)" : "") . "
                    ), 0
                ) as total_delivery_charges
             FROM {$wpdb->prefix}rpos_delivery_logs l
             WHERE {$where_clause}
             ORDER BY l.date DESC, l.id DESC",
            $where_values
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get delivery count for rider on a specific date (from actual orders)
     */
    public static function get_delivery_count_for_rider($rider_id, $date) {
        global $wpdb;
        
        $date_start = $date . ' 00:00:00';
        $date_end = $date . ' 23:59:59';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders 
             WHERE rider_id = %d 
             AND is_delivery = 1
             AND delivery_status = 'delivered'
             AND created_at >= %s 
             AND created_at <= %s",
            $rider_id, $date_start, $date_end
        ));
        
        return intval($count);
    }
    
    /**
     * Get total km from delivered orders for rider on a specific date
     */
    public static function get_total_km_for_rider($rider_id, $date) {
        global $wpdb;
        
        $date_start = $date . ' 00:00:00';
        $date_end = $date . ' 23:59:59';
        
        $total_km = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(delivery_km) FROM {$wpdb->prefix}rpos_orders 
             WHERE rider_id = %d 
             AND is_delivery = 1
             AND delivery_status = 'delivered'
             AND created_at >= %s 
             AND created_at <= %s",
            $rider_id, $date_start, $date_end
        ));
        
        return floatval($total_km);
    }
}
