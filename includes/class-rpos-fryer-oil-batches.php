<?php
/**
 * Fryer Oil Batches Management Class
 * Handles oil batch registration, tracking, and lifecycle
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Fryer_Oil_Batches {
    
    /**
     * Create new oil batch
     */
    public static function create($data) {
        global $wpdb;
        
        if (empty($data['batch_name'])) {
            return false;
        }
        
        // Close any active batch for the same fryer
        if (!empty($data['fryer_id'])) {
            self::close_active_batches($data['fryer_id']);
        }
        
        $insert_data = array(
            'batch_name' => sanitize_text_field($data['batch_name']),
            'oil_added_at' => isset($data['oil_added_at']) ? sanitize_text_field($data['oil_added_at']) : RPOS_Timezone::current_utc_mysql(),
            'target_usage_units' => floatval($data['target_usage_units'] ?? 120),
            'current_usage_units' => 0,
            'time_threshold_hours' => absint($data['time_threshold_hours'] ?? 24),
            'status' => 'active',
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => absint($data['created_by'] ?? get_current_user_id())
        );
        
        $formats = array('%s', '%s', '%f', '%f', '%d', '%s', '%s', '%d');
        
        // Add optional fields
        if (isset($data['fryer_id'])) {
            $insert_data['fryer_id'] = absint($data['fryer_id']);
            $formats[] = '%d';
        }
        
        if (isset($data['oil_capacity'])) {
            $insert_data['oil_capacity'] = floatval($data['oil_capacity']);
            $formats[] = '%f';
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_fryer_oil_batches',
            $insert_data,
            $formats
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get batch by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, u.display_name as created_by_name, u2.display_name as closed_by_name,
                    f.name as fryer_name
             FROM {$wpdb->prefix}rpos_fryer_oil_batches b
             LEFT JOIN {$wpdb->users} u ON b.created_by = u.ID
             LEFT JOIN {$wpdb->users} u2 ON b.closed_by = u2.ID
             LEFT JOIN {$wpdb->prefix}rpos_fryers f ON b.fryer_id = f.id
             WHERE b.id = %d",
            $id
        ));
    }
    
    /**
     * Get active batch for a fryer
     */
    public static function get_active($fryer_id = null) {
        global $wpdb;
        
        $query = "SELECT b.*, u.display_name as created_by_name, f.name as fryer_name
                  FROM {$wpdb->prefix}rpos_fryer_oil_batches b
                  LEFT JOIN {$wpdb->users} u ON b.created_by = u.ID
                  LEFT JOIN {$wpdb->prefix}rpos_fryers f ON b.fryer_id = f.id
                  WHERE b.status = 'active'";
        
        if ($fryer_id !== null && $fryer_id > 0) {
            $query .= $wpdb->prepare(" AND b.fryer_id = %d", $fryer_id);
        } else {
            // For default/unassigned fryer, get any active batch (prefer ones without fryer_id first)
            $query .= " AND (b.fryer_id IS NULL OR b.fryer_id = 0)";
        }
        
        $query .= " ORDER BY b.created_at DESC LIMIT 1";
        
        $batch = $wpdb->get_row($query);
        
        // Fallback: if no batch found for null/0 fryer_id, try to get ANY active batch
        if (!$batch && ($fryer_id === null || $fryer_id === 0)) {
            $query = "SELECT b.*, u.display_name as created_by_name, f.name as fryer_name
                      FROM {$wpdb->prefix}rpos_fryer_oil_batches b
                      LEFT JOIN {$wpdb->users} u ON b.created_by = u.ID
                      LEFT JOIN {$wpdb->prefix}rpos_fryers f ON b.fryer_id = f.id
                      WHERE b.status = 'active'
                      ORDER BY b.created_at DESC LIMIT 1";
            $batch = $wpdb->get_row($query);
        }
        
        return $batch;
    }
    
    /**
     * Get all batches with filtering
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'fryer_id' => null,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Whitelist allowed orderby columns for security
        $allowed_orderby = array('id', 'batch_name', 'oil_added_at', 'closed_at', 'created_at', 'current_usage_units', 'status');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'created_at';
        }
        
        // Sanitize order direction
        $order = strtoupper($args['order']);
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'DESC';
        }
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where[] = 'b.status = %s';
            $where_values[] = $args['status'];
        }
        
        if ($args['fryer_id'] !== null) {
            $where[] = 'b.fryer_id = %d';
            $where_values[] = $args['fryer_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $orderby = 'b.' . $args['orderby'] . ' ' . $order;
        
        $limit_clause = $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT b.*, u.display_name as created_by_name, u2.display_name as closed_by_name,
                        f.name as fryer_name
                 FROM {$wpdb->prefix}rpos_fryer_oil_batches b
                 LEFT JOIN {$wpdb->users} u ON b.created_by = u.ID
                 LEFT JOIN {$wpdb->users} u2 ON b.closed_by = u2.ID
                 LEFT JOIN {$wpdb->prefix}rpos_fryers f ON b.fryer_id = f.id
                 WHERE {$where_clause}
                 ORDER BY {$orderby}",
                $where_values
            ) . $limit_clause;
        } else {
            $query = "SELECT b.*, u.display_name as created_by_name, u2.display_name as closed_by_name,
                             f.name as fryer_name
                      FROM {$wpdb->prefix}rpos_fryer_oil_batches b
                      LEFT JOIN {$wpdb->users} u ON b.created_by = u.ID
                      LEFT JOIN {$wpdb->users} u2 ON b.closed_by = u2.ID
                      LEFT JOIN {$wpdb->prefix}rpos_fryers f ON b.fryer_id = f.id
                      WHERE {$where_clause}
                      ORDER BY {$orderby}" . $limit_clause;
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Update batch
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['batch_name'])) {
            $update_data['batch_name'] = sanitize_text_field($data['batch_name']);
            $formats[] = '%s';
        }
        
        if (isset($data['oil_capacity'])) {
            $update_data['oil_capacity'] = floatval($data['oil_capacity']);
            $formats[] = '%f';
        }
        
        if (isset($data['target_usage_units'])) {
            $update_data['target_usage_units'] = floatval($data['target_usage_units']);
            $formats[] = '%f';
        }
        
        if (isset($data['current_usage_units'])) {
            $update_data['current_usage_units'] = floatval($data['current_usage_units']);
            $formats[] = '%f';
        }
        
        if (isset($data['time_threshold_hours'])) {
            $update_data['time_threshold_hours'] = absint($data['time_threshold_hours']);
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
            $wpdb->prefix . 'rpos_fryer_oil_batches',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Close batch and create historical entry
     */
    public static function close_batch($id, $user_id, $notes = '') {
        global $wpdb;
        
        $batch = self::get($id);
        if (!$batch || $batch->status !== 'active') {
            return false;
        }
        
        $update_data = array(
            'status' => 'closed',
            'closed_at' => RPOS_Timezone::current_utc_mysql(),
            'closed_by' => absint($user_id)
        );
        
        if (!empty($notes)) {
            $update_data['notes'] = sanitize_textarea_field($notes);
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_fryer_oil_batches',
            $update_data,
            array('id' => $id),
            array('%s', '%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Close all active batches for a fryer
     */
    private static function close_active_batches($fryer_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_fryer_oil_batches',
            array(
                'status' => 'closed',
                'closed_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array(
                'fryer_id' => absint($fryer_id),
                'status' => 'active'
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
    }
    
    /**
     * Get usage statistics for a batch
     */
    public static function get_usage_stats($batch_id) {
        global $wpdb;
        
        $batch = self::get($batch_id);
        if (!$batch) {
            return false;
        }
        
        $usage_records = $wpdb->get_results($wpdb->prepare(
            "SELECT product_name, SUM(quantity) as total_quantity, SUM(units_consumed) as total_units
             FROM {$wpdb->prefix}rpos_fryer_oil_usage
             WHERE batch_id = %d
             GROUP BY product_name
             ORDER BY total_units DESC",
            $batch_id
        ));
        
        $total_units = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(units_consumed)
             FROM {$wpdb->prefix}rpos_fryer_oil_usage
             WHERE batch_id = %d",
            $batch_id
        ));
        
        $total_products = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(quantity)
             FROM {$wpdb->prefix}rpos_fryer_oil_usage
             WHERE batch_id = %d",
            $batch_id
        ));
        
        $usage_percentage = 0;
        if ($batch->target_usage_units > 0) {
            $usage_percentage = ($total_units / $batch->target_usage_units) * 100;
        }
        
        $time_elapsed = 0;
        if ($batch->status === 'active') {
            $start = new DateTime($batch->oil_added_at);
            $now = RPOS_Timezone::now();
            $time_elapsed = $now->getTimestamp() - $start->getTimestamp();
        } else if ($batch->closed_at) {
            $start = new DateTime($batch->oil_added_at);
            $end = new DateTime($batch->closed_at);
            $time_elapsed = $end->getTimestamp() - $start->getTimestamp();
        }
        
        $time_elapsed_hours = $time_elapsed / 3600;
        
        return array(
            'batch' => $batch,
            'usage_records' => $usage_records,
            'total_units' => floatval($total_units),
            'total_products' => intval($total_products),
            'usage_percentage' => round($usage_percentage, 2),
            'time_elapsed_hours' => round($time_elapsed_hours, 2),
            'remaining_units' => $batch->target_usage_units - floatval($total_units)
        );
    }
    
    /**
     * Increment usage for a batch
     */
    public static function increment_usage($batch_id, $units) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}rpos_fryer_oil_batches
             SET current_usage_units = current_usage_units + %f
             WHERE id = %d",
            floatval($units),
            absint($batch_id)
        ));
    }
}
