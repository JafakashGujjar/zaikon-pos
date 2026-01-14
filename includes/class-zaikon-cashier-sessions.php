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
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_orders 
             WHERE cashier_id = %d 
             AND created_at >= %s 
             AND (session_end IS NULL OR created_at <= %s)",
            $session->cashier_id,
            $session->session_start,
            $session->session_end ?? current_time('mysql')
        ));
        
        $cash_sales = 0;
        $cod_collected = 0;
        
        foreach ($orders as $order) {
            if ($order->payment_type === 'cash' && $order->payment_status === 'paid') {
                $cash_sales += floatval($order->grand_total_rs);
            } elseif ($order->payment_type === 'cod' && $order->payment_status === 'paid') {
                $cod_collected += floatval($order->grand_total_rs);
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
