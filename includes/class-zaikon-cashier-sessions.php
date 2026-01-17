<?php
/**
 * Zaikon Cashier Sessions Management Class
 * Manages cashier shift/drawer sessions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Cashier_Sessions {
    
    /**
     * Create new cashier session
     */
    public static function create($data) {
        global $wpdb;
        
        $session_data = array(
            'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id()),
            'opening_cash_rs' => floatval($data['opening_cash_rs'] ?? 0),
            'session_start' => current_time('mysql'),
            'status' => 'open',
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_cashier_sessions',
            $session_data,
            array('%d', '%f', '%s', '%s', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get active session for cashier
     */
    public static function get_active_session($cashier_id = null) {
        global $wpdb;
        
        if (!$cashier_id) {
            $cashier_id = get_current_user_id();
        }
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cashier_sessions 
             WHERE cashier_id = %d AND status = 'open' 
             ORDER BY session_start DESC LIMIT 1",
            $cashier_id
        ));
        
        return $session;
    }
    
    /**
     * Close cashier session
     */
    public static function close_session($session_id, $data = array()) {
        global $wpdb;
        
        $closing_data = array(
            'closing_cash_rs' => floatval($data['closing_cash_rs'] ?? 0),
            'expected_cash_rs' => floatval($data['expected_cash_rs'] ?? 0),
            'cash_difference_rs' => floatval($data['cash_difference_rs'] ?? 0),
            'total_cash_sales_rs' => floatval($data['total_cash_sales_rs'] ?? 0),
            'total_cod_collected_rs' => floatval($data['total_cod_collected_rs'] ?? 0),
            'total_expenses_rs' => floatval($data['total_expenses_rs'] ?? 0),
            'session_end' => current_time('mysql'),
            'status' => 'closed',
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_cashier_sessions',
            $closing_data,
            array('id' => $session_id),
            array('%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Calculate session totals from orders and expenses
     */
    public static function calculate_session_totals($session_id) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cashier_sessions WHERE id = %d",
            $session_id
        ));
        
        if (!$session) {
            return false;
        }
        
        // Get all orders from this cashier since session start
        $end_time = $session->session_end ?? current_time('mysql');
        
        // Get delivery orders from zaikon_orders table
        $zaikon_orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_orders 
             WHERE cashier_id = %d 
             AND created_at >= %s 
             AND created_at <= %s
             AND order_type = 'delivery'",
            $session->cashier_id,
            $session->session_start,
            $end_time
        ));
        
        $cash_sales = 0;
        $cod_collected = 0;
        
        // Calculate totals from zaikon_orders (delivery orders only)
        foreach ($zaikon_orders as $order) {
            if ($order->payment_type === 'cash' && $order->payment_status === 'paid') {
                $cash_sales += floatval($order->grand_total_rs);
            } elseif ($order->payment_type === 'cod' && 
                    ($order->payment_status === 'cod_received' || $order->payment_status === 'paid')) {
                $cod_collected += floatval($order->grand_total_rs);
            }
        }
        
        // Also get non-delivery orders from rpos_orders table (dine-in and takeaway)
        // These don't exist in zaikon_orders, so we need to query rpos_orders
        // Check if payment_type column exists in rpos_orders
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}rpos_orders");
        $has_payment_type = in_array('payment_type', $columns);
        $has_payment_status = in_array('payment_status', $columns);
        
        if ($has_payment_type && $has_payment_status) {
            // Get all cash/paid dine-in and takeaway orders
            // Include orders that are:
            // 1. Cash payment type with paid status
            // 2. NULL/empty payment type (legacy orders) that are not cancelled
            $rpos_orders = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rpos_orders 
                 WHERE cashier_id = %d 
                 AND created_at >= %s 
                 AND created_at <= %s
                 AND order_type IN ('dine-in', 'takeaway')
                 AND status NOT IN ('cancelled', 'void', 'refunded')
                 AND (
                     (payment_type = 'cash' AND payment_status = 'paid')
                     OR
                     (payment_type IS NULL OR payment_type = '')
                 )",
                $session->cashier_id,
                $session->session_start,
                $end_time
            ));
            
            foreach ($rpos_orders as $order) {
                $cash_sales += floatval($order->total);
            }
        } else {
            // Legacy schema - assume all completed dine-in and takeaway orders are cash and paid
            $rpos_orders = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rpos_orders 
                 WHERE cashier_id = %d 
                 AND created_at >= %s 
                 AND created_at <= %s
                 AND order_type IN ('dine-in', 'takeaway')
                 AND status NOT IN ('cancelled', 'void', 'refunded')",
                $session->cashier_id,
                $session->session_start,
                $end_time
            ));
            
            foreach ($rpos_orders as $order) {
                // Legacy orders are assumed to be cash sales
                $cash_sales += floatval($order->total);
            }
        }
        
        // Calculate online payments from both zaikon_orders and rpos_orders
        $online_payments = 0;
        
        // Calculate online payments from zaikon_orders (delivery orders)
        foreach ($zaikon_orders as $order) {
            if ($order->payment_type === 'online' && 
                ($order->payment_status === 'paid' || $order->payment_status === 'completed')) {
                $online_payments += floatval($order->grand_total_rs);
            }
        }
        
        // Also check rpos_orders for online/card payments (if schema supports it)
        if ($has_payment_type && $has_payment_status) {
            $online_rpos_orders = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rpos_orders 
                 WHERE cashier_id = %d 
                 AND created_at >= %s 
                 AND created_at <= %s
                 AND order_type IN ('dine-in', 'takeaway')
                 AND status NOT IN ('cancelled', 'void', 'refunded')
                 AND (
                     (payment_type IN ('online', 'card') AND payment_status = 'paid')
                     OR
                     (payment_type = 'cod' AND payment_status IN ('paid', 'cod_received'))
                 )",
                $session->cashier_id,
                $session->session_start,
                $end_time
            ));
            
            foreach ($online_rpos_orders as $order) {
                $online_payments += floatval($order->total);
            }
        }
        
        // Get total expenses for this session
        $expenses = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_rs), 0) FROM {$wpdb->prefix}zaikon_expenses 
             WHERE session_id = %d",
            $session_id
        ));
        
        return array(
            'cash_sales' => $cash_sales,
            'cod_collected' => $cod_collected,
            'online_payments' => $online_payments,
            'expenses' => floatval($expenses),
            'expected_cash' => floatval($session->opening_cash_rs) + $cash_sales + $cod_collected - floatval($expenses)
        );
    }
    
    /**
     * Get session by ID
     */
    public static function get($session_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cashier_sessions WHERE id = %d",
            $session_id
        ));
    }
    
    /**
     * Get sessions for cashier
     */
    public static function get_sessions($cashier_id = null, $limit = 50) {
        global $wpdb;
        
        if ($cashier_id) {
            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaikon_cashier_sessions 
                 WHERE cashier_id = %d 
                 ORDER BY session_start DESC LIMIT %d",
                $cashier_id,
                $limit
            ));
        } else {
            $sessions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaikon_cashier_sessions 
                 ORDER BY session_start DESC LIMIT %d",
                $limit
            ));
        }
        
        return $sessions;
    }
}
