<?php
/**
 * Reports and Analytics Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Reports {
    
    /**
     * Get sales summary
     */
    public static function get_sales_summary($date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array("status = 'completed'");
        $where_values = array();
        
        if ($date_from) {
            $where[] = 'created_at >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'created_at <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(*) as order_count,
                    SUM(total) as total_sales,
                    SUM(discount) as total_discounts,
                    AVG(total) as average_order
                FROM {$wpdb->prefix}rpos_orders
                WHERE {$where_clause}",
                $where_values
            );
        } else {
            $query = "SELECT 
                    COUNT(*) as order_count,
                    SUM(total) as total_sales,
                    SUM(discount) as total_discounts,
                    AVG(total) as average_order
                FROM {$wpdb->prefix}rpos_orders
                WHERE {$where_clause}";
        }
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Get top products by quantity
     */
    public static function get_top_products_by_quantity($limit = 10, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array("o.status = 'completed'");
        $where_values = array();
        
        if ($date_from) {
            $where[] = 'o.created_at >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'o.created_at <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT 
                    oi.product_id,
                    oi.product_name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.line_total) as total_revenue
                FROM {$wpdb->prefix}rpos_order_items oi
                INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
                WHERE {$where_clause}
                GROUP BY oi.product_id, oi.product_name
                ORDER BY total_quantity DESC
                LIMIT %d";
        
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Get top products by revenue
     */
    public static function get_top_products_by_revenue($limit = 10, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array("o.status = 'completed'");
        $where_values = array();
        
        if ($date_from) {
            $where[] = 'o.created_at >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'o.created_at <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT 
                    oi.product_id,
                    oi.product_name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.line_total) as total_revenue
                FROM {$wpdb->prefix}rpos_order_items oi
                INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
                WHERE {$where_clause}
                GROUP BY oi.product_id, oi.product_name
                ORDER BY total_revenue DESC
                LIMIT %d";
        
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Get profit report
     */
    public static function get_profit_report($date_from = null, $date_to = null) {
        global $wpdb;
        
        $where = array("o.status = 'completed'");
        $where_values = array();
        
        if ($date_from) {
            $where[] = 'o.created_at >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where[] = 'o.created_at <= %s';
            $where_values[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT 
                    SUM(oi.line_total) as total_revenue,
                    SUM(oi.cost_price * oi.quantity) as total_cogs
                FROM {$wpdb->prefix}rpos_order_items oi
                INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
                WHERE {$where_clause}",
                $where_values
            );
        } else {
            $query = "SELECT 
                    SUM(oi.line_total) as total_revenue,
                    SUM(oi.cost_price * oi.quantity) as total_cogs
                FROM {$wpdb->prefix}rpos_order_items oi
                INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
                WHERE {$where_clause}";
        }
        
        $result = $wpdb->get_row($query);
        
        if ($result) {
            $result->gross_profit = $result->total_revenue - $result->total_cogs;
            $result->profit_margin = $result->total_revenue > 0 
                ? ($result->gross_profit / $result->total_revenue) * 100 
                : 0;
        }
        
        return $result;
    }
    
    /**
     * Get low stock report
     */
    public static function get_low_stock_report() {
        global $wpdb;
        
        // Get low stock products
        $low_stock_products = RPOS_Inventory::get_all(array('low_stock_only' => true));
        
        // Get low stock ingredients
        $threshold = RPOS_Settings::get('low_stock_threshold', 10);
        $low_stock_ingredients = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, unit, current_stock_quantity as quantity
             FROM {$wpdb->prefix}rpos_ingredients
             WHERE current_stock_quantity <= %d
             ORDER BY name ASC",
            $threshold
        ));
        
        // Merge both arrays
        return array_merge($low_stock_products, $low_stock_ingredients);
    }
    
    /**
     * Get kitchen activity report
     */
    public static function get_kitchen_activity_report($date = null) {
        global $wpdb;
        
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $date_from = $date . ' 00:00:00';
        $date_to = $date . ' 23:59:59';
        
        // Get kitchen staff activity summary
        $activity_summary = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ka.user_id,
                u.display_name as user_name,
                COUNT(DISTINCT CASE WHEN ka.new_status = 'ready' THEN ka.order_id END) as orders_ready,
                COUNT(DISTINCT CASE WHEN ka.new_status = 'cooking' THEN ka.order_id END) as orders_cooking,
                COUNT(DISTINCT ka.order_id) as total_orders_handled
            FROM {$wpdb->prefix}rpos_kitchen_activity ka
            LEFT JOIN {$wpdb->users} u ON ka.user_id = u.ID
            WHERE ka.created_at >= %s AND ka.created_at <= %s
            GROUP BY ka.user_id, u.display_name
            ORDER BY orders_ready DESC, u.display_name ASC",
            $date_from,
            $date_to
        ));
        
        // For each kitchen staff, get product counts
        foreach ($activity_summary as $index => $staff) {
            $activity_summary[$index]->products = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    oi.product_name,
                    SUM(oi.quantity) as total_quantity
                FROM {$wpdb->prefix}rpos_kitchen_activity ka
                INNER JOIN {$wpdb->prefix}rpos_order_items oi ON ka.order_id = oi.order_id
                WHERE ka.user_id = %d 
                    AND ka.new_status = 'ready'
                    AND ka.created_at >= %s 
                    AND ka.created_at <= %s
                GROUP BY oi.product_name
                ORDER BY total_quantity DESC",
                $staff->user_id,
                $date_from,
                $date_to
            ));
        }
        
        return $activity_summary;
    }
}
