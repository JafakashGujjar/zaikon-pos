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
}
