<?php
/**
 * Zaikon Reports Class
 * Comprehensive reporting for delivery and rider analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Reports {
    
    /**
     * Get daily/monthly sales and delivery summary
     */
    public static function get_sales_summary($date_from, $date_to) {
        // Get order summary
        $order_summary = Zaikon_Orders::get_sales_summary($date_from, $date_to);
        
        // Get delivery summary
        $delivery_summary = Zaikon_Deliveries::get_delivery_summary($date_from, $date_to);
        
        return array(
            'orders' => $order_summary,
            'deliveries' => $delivery_summary
        );
    }
    
    /**
     * Get delivery revenue and distance report
     */
    public static function get_delivery_revenue_report($date_from, $date_to) {
        $delivery_summary = Zaikon_Deliveries::get_delivery_summary($date_from, $date_to);
        $location_summary = Zaikon_Deliveries::get_location_summary($date_from, $date_to);
        
        return array(
            'summary' => $delivery_summary,
            'by_location' => $location_summary
        );
    }
    
    /**
     * Get rider performance, cost and profitability report
     */
    public static function get_rider_performance($rider_id, $date_from, $date_to) {
        global $wpdb;
        
        $rider = Zaikon_Riders::get($rider_id);
        
        if (!$rider) {
            return null;
        }
        
        // Get deliveries for this rider
        $deliveries = Zaikon_Deliveries::get_all(array(
            'rider_id' => $rider_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'limit' => 99999
        ));
        
        // Calculate metrics
        $deliveries_count = count($deliveries);
        $total_distance_km = 0;
        $total_delivery_charges = 0;
        
        foreach ($deliveries as $delivery) {
            $total_distance_km += floatval($delivery->distance_km);
            $total_delivery_charges += floatval($delivery->delivery_charges_rs);
        }
        
        // Get rider payouts
        $total_rider_pay = Zaikon_Rider_Payouts::get_rider_total_payout($rider_id, $date_from, $date_to);
        
        // Get fuel costs
        $total_fuel_cost = Zaikon_Rider_Fuel_Logs::get_rider_total_fuel_cost($rider_id, $date_from, $date_to);
        
        // Calculate derived metrics
        $net_delivery_profit = $total_delivery_charges - $total_rider_pay - $total_fuel_cost;
        $cost_per_km = $total_distance_km > 0 ? ($total_rider_pay + $total_fuel_cost) / $total_distance_km : 0;
        $avg_delivery_charge = $deliveries_count > 0 ? $total_delivery_charges / $deliveries_count : 0;
        
        return array(
            'rider' => $rider,
            'deliveries_count' => $deliveries_count,
            'total_distance_km' => $total_distance_km,
            'total_rider_pay' => $total_rider_pay,
            'total_delivery_charges' => $total_delivery_charges,
            'total_fuel_cost' => $total_fuel_cost,
            'net_delivery_profit' => $net_delivery_profit,
            'cost_per_km' => $cost_per_km,
            'avg_delivery_charge' => $avg_delivery_charge
        );
    }
    
    /**
     * Get all riders performance summary
     */
    public static function get_all_riders_performance($date_from, $date_to) {
        $riders = Zaikon_Riders::get_all(true); // active riders only
        
        $results = array();
        
        foreach ($riders as $rider) {
            $performance = self::get_rider_performance($rider->id, $date_from, $date_to);
            if ($performance) {
                $results[] = $performance;
            }
        }
        
        return $results;
    }
    
    /**
     * Get customer delivery analytics
     */
    public static function get_customer_analytics($date_from, $date_to) {
        return Zaikon_Deliveries::get_customer_analytics($date_from, $date_to);
    }
    
    /**
     * Get product-wise sales report
     */
    public static function get_product_sales($date_from, $date_to) {
        return Zaikon_Order_Items::get_product_sales($date_from, $date_to);
    }
    
    /**
     * Get comprehensive dashboard data
     */
    public static function get_dashboard_data($date_from = null, $date_to = null) {
        if (!$date_from) {
            $date_from = date('Y-m-d 00:00:00');
        }
        if (!$date_to) {
            $date_to = date('Y-m-d 23:59:59');
        }
        
        $sales_summary = self::get_sales_summary($date_from, $date_to);
        $rider_performance = self::get_all_riders_performance($date_from, $date_to);
        
        return array(
            'date_from' => $date_from,
            'date_to' => $date_to,
            'sales_summary' => $sales_summary,
            'rider_performance' => $rider_performance
        );
    }
    
    /**
     * Get rider delivery summary for the period
     */
    public static function get_rider_delivery_summary($date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT 
                    r.id as rider_id,
                    r.name as rider_name,
                    COUNT(ro.id) as total_deliveries,
                    SUM(CASE WHEN ro.status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
                    SUM(CASE WHEN ro.status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries,
                    SUM(d.distance_km) as total_distance_km,
                    AVG(d.distance_km) as avg_distance_km
                  FROM {$wpdb->prefix}zaikon_riders r
                  LEFT JOIN {$wpdb->prefix}zaikon_rider_orders ro ON r.id = ro.rider_id
                  LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON ro.delivery_id = d.id
                  WHERE r.status = 'active'
                  AND ro.created_at >= %s AND ro.created_at <= %s
                  GROUP BY r.id, r.name
                  ORDER BY completed_deliveries DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to));
    }
    
    /**
     * Get rider efficiency report
     */
    public static function get_rider_efficiency_report($rider_id, $date_from, $date_to) {
        global $wpdb;
        
        // Get all deliveries for the rider in the period
        $deliveries = $wpdb->get_results($wpdb->prepare(
            "SELECT ro.*, 
                    TIMESTAMPDIFF(MINUTE, ro.assigned_at, ro.delivered_at) as delivery_time_minutes
             FROM {$wpdb->prefix}zaikon_rider_orders ro
             WHERE ro.rider_id = %d
             AND ro.status = 'delivered'
             AND ro.created_at >= %s AND ro.created_at <= %s
             ORDER BY ro.created_at ASC",
            $rider_id, $date_from, $date_to
        ));
        
        if (empty($deliveries)) {
            return null;
        }
        
        $total_deliveries = count($deliveries);
        $total_time = 0;
        $delivery_times = array();
        
        foreach ($deliveries as $delivery) {
            if ($delivery->delivery_time_minutes !== null) {
                $total_time += $delivery->delivery_time_minutes;
                $delivery_times[] = $delivery->delivery_time_minutes;
            }
        }
        
        $avg_time_per_delivery = $total_deliveries > 0 ? $total_time / $total_deliveries : 0;
        
        // Calculate deliveries per hour (based on working hours in the period)
        $period_hours = (strtotime($date_to) - strtotime($date_from)) / 3600;
        $deliveries_per_hour = $period_hours > 0 ? $total_deliveries / $period_hours : 0;
        
        return array(
            'rider_id' => $rider_id,
            'total_deliveries' => $total_deliveries,
            'avg_time_per_delivery_minutes' => round($avg_time_per_delivery, 2),
            'deliveries_per_hour' => round($deliveries_per_hour, 2),
            'period_hours' => round($period_hours, 2),
            'date_from' => $date_from,
            'date_to' => $date_to
        );
    }
    
    /**
     * Get payout summary by payout type
     */
    public static function get_payout_summary($date_from, $date_to) {
        global $wpdb;
        
        $query = "SELECT 
                    r.payout_type,
                    COUNT(DISTINCT r.id) as rider_count,
                    SUM(rp.rider_pay_rs) as total_payout,
                    AVG(rp.rider_pay_rs) as avg_payout_per_delivery
                  FROM {$wpdb->prefix}zaikon_riders r
                  LEFT JOIN {$wpdb->prefix}zaikon_rider_payouts rp ON r.id = rp.rider_id
                  WHERE rp.created_at >= %s AND rp.created_at <= %s
                  GROUP BY r.payout_type";
        
        return $wpdb->get_results($wpdb->prepare($query, $date_from, $date_to));
    }
    
    /**
     * Get delivery profitability analysis
     */
    public static function get_delivery_profitability($date_from, $date_to) {
        global $wpdb;
        
        // Get delivery charges revenue
        $revenue_query = "SELECT 
                            SUM(delivery_charges_rs) as total_delivery_revenue,
                            COUNT(*) as total_deliveries
                          FROM {$wpdb->prefix}zaikon_deliveries
                          WHERE created_at >= %s AND created_at <= %s";
        
        $revenue = $wpdb->get_row($wpdb->prepare($revenue_query, $date_from, $date_to));
        
        // Get rider costs (payouts)
        $payout_query = "SELECT 
                           SUM(rider_pay_rs) as total_rider_costs
                         FROM {$wpdb->prefix}zaikon_rider_payouts
                         WHERE created_at >= %s AND created_at <= %s";
        
        $payouts = $wpdb->get_row($wpdb->prepare($payout_query, $date_from, $date_to));
        
        // Get fuel costs
        $fuel_query = "SELECT 
                         SUM(amount_rs) as total_fuel_costs
                       FROM {$wpdb->prefix}zaikon_rider_fuel_logs
                       WHERE date >= %s AND date <= %s";
        
        $fuel = $wpdb->get_row($wpdb->prepare($fuel_query, date('Y-m-d', strtotime($date_from)), date('Y-m-d', strtotime($date_to))));
        
        $total_revenue = floatval($revenue->total_delivery_revenue ?? 0);
        $total_rider_costs = floatval($payouts->total_rider_costs ?? 0);
        $total_fuel_costs = floatval($fuel->total_fuel_costs ?? 0);
        $total_costs = $total_rider_costs + $total_fuel_costs;
        $net_profit = $total_revenue - $total_costs;
        $profit_margin = $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0;
        
        return array(
            'total_deliveries' => intval($revenue->total_deliveries ?? 0),
            'total_delivery_revenue' => $total_revenue,
            'total_rider_costs' => $total_rider_costs,
            'total_fuel_costs' => $total_fuel_costs,
            'total_costs' => $total_costs,
            'net_profit' => $net_profit,
            'profit_margin_percent' => round($profit_margin, 2)
        );
    }
}
