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
            "SELECT id, name as product_name, NULL as sku, unit, current_stock_quantity as quantity, cost_per_unit as cost_price
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
            $date = RPOS_Timezone::now()->format('Y-m-d');
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
    
    /**
     * Get kitchen staff report for a specific user and date range
     */
    public static function get_kitchen_staff_report($user_id, $date_from, $date_to) {
        global $wpdb;
        
        $date_from_time = $date_from . ' 00:00:00';
        $date_to_time = $date_to . ' 23:59:59';
        
        // Get all orders worked on by this kitchen user (via kitchen_activity)
        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT order_id 
             FROM {$wpdb->prefix}rpos_kitchen_activity 
             WHERE user_id = %d 
             AND created_at >= %s 
             AND created_at <= %s",
            $user_id, $date_from_time, $date_to_time
        ));
        
        $total_orders = count($order_ids);
        
        // Count completed orders
        $completed_orders = 0;
        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            $completed_orders = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders 
                 WHERE id IN ($placeholders) AND status = 'completed'",
                $order_ids
            ));
        }
        
        // Get products prepared (from orders they worked on)
        $products = array();
        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            $products = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    oi.product_name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.line_total) as total_sales
                 FROM {$wpdb->prefix}rpos_order_items oi
                 WHERE oi.order_id IN ($placeholders)
                 GROUP BY oi.product_id, oi.product_name
                 ORDER BY total_quantity DESC",
                $order_ids
            ));
        }
        
        // Get ingredients consumed (based on order_id matching)
        $ingredients = array();
        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
            
            // Get ingredient movements for these orders
            $ingredient_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT ingredient_id 
                 FROM {$wpdb->prefix}rpos_ingredient_movements 
                 WHERE movement_type = 'order_deduction' 
                 AND reference_id IN ($placeholders)",
                $order_ids
            ));
            
            if (!empty($ingredient_ids)) {
                $ing_placeholders = implode(',', array_fill(0, count($ingredient_ids), '%d'));
                $order_placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
                $params = array_merge($ingredient_ids, $order_ids);
                
                $ingredients = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        i.name,
                        i.unit,
                        i.current_stock_quantity as current_stock,
                        COALESCE(SUM(ABS(im.change_amount)), 0) as consumed
                     FROM {$wpdb->prefix}rpos_ingredients i
                     LEFT JOIN {$wpdb->prefix}rpos_ingredient_movements im 
                         ON i.id = im.ingredient_id 
                         AND im.movement_type = 'order_deduction'
                         AND im.reference_id IN ($order_placeholders)
                     WHERE i.id IN ($ing_placeholders)
                     GROUP BY i.id, i.name, i.unit, i.current_stock_quantity
                     ORDER BY consumed DESC",
                    $params
                ));
            }
        }
        
        return array(
            'total_orders' => $total_orders,
            'completed_orders' => $completed_orders,
            'products' => $products,
            'ingredients' => $ingredients
        );
    }
}
