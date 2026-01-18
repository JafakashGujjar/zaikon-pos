<?php
/**
 * Fryer Reports Class
 * Handles enterprise-level reporting for fryer oil tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Fryer_Reports {
    
    /**
     * Get batch history with filters
     */
    public static function get_batch_history($args = array()) {
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'fryer_id' => null,
            'status' => 'closed',
            'orderby' => 'closed_at',
            'order' => 'DESC',
            'limit' => 100
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return RPOS_Fryer_Oil_Batches::get_all($args);
    }
    
    /**
     * Get lifecycle statistics
     */
    public static function get_lifecycle_stats($date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array("status = 'closed'");
        $params = array();
        
        if ($date_from) {
            $where[] = "closed_at >= %s";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = "closed_at <= %s";
            $params[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_batches,
                    AVG(current_usage_units) as avg_units_per_batch,
                    AVG(TIMESTAMPDIFF(HOUR, oil_added_at, closed_at)) as avg_hours_per_batch,
                    MIN(current_usage_units) as min_units,
                    MAX(current_usage_units) as max_units
                 FROM {$wpdb->prefix}rpos_fryer_oil_batches
                 WHERE {$where_clause}",
                $params
            );
        } else {
            $query = "SELECT 
                    COUNT(*) as total_batches,
                    AVG(current_usage_units) as avg_units_per_batch,
                    AVG(TIMESTAMPDIFF(HOUR, oil_added_at, closed_at)) as avg_hours_per_batch,
                    MIN(current_usage_units) as min_units,
                    MAX(current_usage_units) as max_units
                 FROM {$wpdb->prefix}rpos_fryer_oil_batches
                 WHERE {$where_clause}";
        }
        
        $stats = $wpdb->get_row($query);
        
        // Get product distribution
        $product_stats = $wpdb->get_results(
            "SELECT 
                product_name,
                SUM(quantity) as total_quantity,
                SUM(units_consumed) as total_units,
                COUNT(DISTINCT batch_id) as batches_count
             FROM {$wpdb->prefix}rpos_fryer_oil_usage
             WHERE batch_id IN (
                 SELECT id FROM {$wpdb->prefix}rpos_fryer_oil_batches
                 WHERE {$where_clause}
             )
             GROUP BY product_name
             ORDER BY total_units DESC"
        );
        
        return array(
            'lifecycle' => $stats,
            'products' => $product_stats
        );
    }
    
    /**
     * Get products cooked per batch
     */
    public static function get_products_cooked_report($batch_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                product_name,
                SUM(quantity) as total_quantity,
                SUM(units_consumed) as total_units,
                COUNT(DISTINCT order_id) as order_count
             FROM {$wpdb->prefix}rpos_fryer_oil_usage
             WHERE batch_id = %d
             GROUP BY product_name
             ORDER BY total_units DESC",
            $batch_id
        ));
    }
    
    /**
     * Get cost analysis
     */
    public static function get_cost_analysis($date_from = null, $date_to = null) {
        // This is a placeholder for future cost tracking
        // Would require additional fields for oil cost per batch
        
        $batches = self::get_batch_history(array(
            'date_from' => $date_from,
            'date_to' => $date_to,
            'status' => 'closed'
        ));
        
        $total_batches = count($batches);
        $total_units = 0;
        
        foreach ($batches as $batch) {
            $total_units += floatval($batch->current_usage_units);
        }
        
        $avg_units_per_batch = $total_batches > 0 ? $total_units / $total_batches : 0;
        
        return array(
            'total_batches' => $total_batches,
            'total_units' => $total_units,
            'avg_units_per_batch' => $avg_units_per_batch,
            'batches' => $batches
        );
    }
    
    /**
     * Get batch details with full history
     */
    public static function get_batch_details($batch_id) {
        $batch = RPOS_Fryer_Oil_Batches::get($batch_id);
        if (!$batch) {
            return false;
        }
        
        $stats = RPOS_Fryer_Oil_Batches::get_usage_stats($batch_id);
        $products = self::get_products_cooked_report($batch_id);
        $usage_log = RPOS_Fryer_Usage::get_usage_by_batch($batch_id);
        
        return array(
            'batch' => $batch,
            'stats' => $stats,
            'products' => $products,
            'usage_log' => $usage_log
        );
    }
    
    /**
     * Export batch report to CSV
     */
    public static function export_batch_to_csv($batch_id) {
        $details = self::get_batch_details($batch_id);
        
        if (!$details) {
            return false;
        }
        
        $csv_data = array();
        
        // Header
        $csv_data[] = array('Batch Report', $details['batch']->batch_name);
        $csv_data[] = array('Started', $details['batch']->oil_added_at);
        $csv_data[] = array('Ended', $details['batch']->closed_at ?: 'Active');
        $csv_data[] = array('Total Units', $details['stats']['total_units']);
        $csv_data[] = array('Target Units', $details['batch']->target_usage_units);
        $csv_data[] = array('Usage Percentage', $details['stats']['usage_percentage'] . '%');
        $csv_data[] = array();
        
        // Products
        $csv_data[] = array('Product Name', 'Quantity', 'Units Consumed', 'Orders');
        foreach ($details['products'] as $product) {
            $csv_data[] = array(
                $product->product_name,
                $product->total_quantity,
                $product->total_units,
                $product->order_count
            );
        }
        
        return $csv_data;
    }
}
