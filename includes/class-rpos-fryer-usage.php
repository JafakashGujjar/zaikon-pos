<?php
/**
 * Fryer Usage Tracking Class
 * Handles automatic tracking of oil usage when orders are completed
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Fryer_Usage {
    
    /**
     * Record usage from order items
     */
    public static function record_usage_from_order($order_id, $items) {
        if (empty($items)) {
            return false;
        }
        
        global $wpdb;
        
        $recorded = false;
        
        foreach ($items as $item) {
            $product_id = is_object($item) ? $item->product_id : $item['product_id'];
            $quantity = is_object($item) ? $item->quantity : $item['quantity'];
            $product_name = is_object($item) ? $item->product_name : $item['product_name'];
            $order_item_id = is_object($item) && isset($item->id) ? $item->id : null;
            
            // Check if this product uses fryer oil
            if (!RPOS_Fryer_Products::is_fryer_product($product_id)) {
                continue;
            }
            
            // Get the fryer for this product
            $fryer_id = RPOS_Fryer_Products::get_product_fryer($product_id);
            
            // Get active batch for this fryer
            $batch = RPOS_Fryer_Oil_Batches::get_active($fryer_id);
            
            if (!$batch) {
                error_log("RPOS Fryer: No active batch found for fryer #" . ($fryer_id ?: 'default') . ", skipping product #" . $product_id);
                continue;
            }
            
            // Get oil units for this product
            $oil_units = RPOS_Fryer_Products::get_oil_units($product_id, $fryer_id);
            
            if ($oil_units === null) {
                error_log("RPOS Fryer: No oil units configured for product #" . $product_id);
                continue;
            }
            
            // Calculate total units consumed
            $units_consumed = floatval($oil_units) * intval($quantity);
            
            // Record usage
            $result = $wpdb->insert(
                $wpdb->prefix . 'rpos_fryer_oil_usage',
                array(
                    'batch_id' => $batch->id,
                    'order_id' => absint($order_id),
                    'order_item_id' => $order_item_id,
                    'product_id' => absint($product_id),
                    'product_name' => sanitize_text_field($product_name),
                    'quantity' => absint($quantity),
                    'units_consumed' => $units_consumed
                ),
                array('%d', '%d', '%d', '%d', '%s', '%d', '%f')
            );
            
            if ($result) {
                // Increment batch usage
                RPOS_Fryer_Oil_Batches::increment_usage($batch->id, $units_consumed);
                $recorded = true;
                error_log("RPOS Fryer: Recorded usage for product #" . $product_id . " (" . $quantity . " x " . $oil_units . " = " . $units_consumed . " units) in batch #" . $batch->id);
            }
        }
        
        return $recorded;
    }
    
    /**
     * Get usage records by batch
     */
    public static function get_usage_by_batch($batch_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_fryer_oil_usage
             WHERE batch_id = %d
             ORDER BY created_at DESC",
            $batch_id
        ));
    }
    
    /**
     * Get batch summary
     */
    public static function get_batch_summary($batch_id) {
        $batch = RPOS_Fryer_Oil_Batches::get($batch_id);
        if (!$batch) {
            return false;
        }
        
        return RPOS_Fryer_Oil_Batches::get_usage_stats($batch_id);
    }
    
    /**
     * Get usage by order
     */
    public static function get_usage_by_order($order_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_fryer_oil_usage
             WHERE order_id = %d
             ORDER BY created_at DESC",
            $order_id
        ));
    }
    
    /**
     * Get usage by date range
     */
    public static function get_usage_by_date_range($date_from, $date_to, $batch_id = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}rpos_fryer_oil_usage WHERE 1=1";
        $params = array();
        
        if ($date_from) {
            $query .= " AND created_at >= %s";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $query .= " AND created_at <= %s";
            $params[] = $date_to;
        }
        
        if ($batch_id !== null) {
            $query .= " AND batch_id = %d";
            $params[] = $batch_id;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        
        return $wpdb->get_results($query);
    }
}
