<?php
/**
 * Zaikon Expenses Management Class
 * Manages cashier expenses during shifts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Expenses {
    
    /**
     * Create new expense
     */
    public static function create($data) {
        global $wpdb;
        
        $expense_data = array(
            'session_id' => absint($data['session_id']),
            'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id()),
            'amount_rs' => floatval($data['amount_rs']),
            'category' => sanitize_text_field($data['category']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'rider_id' => isset($data['rider_id']) ? absint($data['rider_id']) : null,
            'expense_date' => isset($data['expense_date']) ? $data['expense_date'] : current_time('mysql'),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_expenses',
            $expense_data,
            array('%d', '%d', '%f', '%s', '%s', '%d', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get expense by ID
     */
    public static function get($expense_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_expenses WHERE id = %d",
            $expense_id
        ));
    }
    
    /**
     * Get expenses for session
     */
    public static function get_by_session($session_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, r.name as rider_name 
             FROM {$wpdb->prefix}zaikon_expenses e
             LEFT JOIN {$wpdb->prefix}zaikon_riders r ON e.rider_id = r.id
             WHERE e.session_id = %d 
             ORDER BY e.expense_date DESC",
            $session_id
        ));
    }
    
    /**
     * Get expenses for cashier
     */
    public static function get_by_cashier($cashier_id, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $where = array("e.cashier_id = %d");
        $values = array($cashier_id);
        
        if ($start_date) {
            $where[] = "e.expense_date >= %s";
            $values[] = $start_date;
        }
        
        if ($end_date) {
            $where[] = "e.expense_date <= %s";
            $values[] = $end_date;
        }
        
        $sql = "SELECT e.*, r.name as rider_name 
                FROM {$wpdb->prefix}zaikon_expenses e
                LEFT JOIN {$wpdb->prefix}zaikon_riders r ON e.rider_id = r.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.expense_date DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    /**
     * Update expense
     */
    public static function update($expense_id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['amount_rs'])) {
            $update_data['amount_rs'] = floatval($data['amount_rs']);
            $formats[] = '%f';
        }
        
        if (isset($data['category'])) {
            $update_data['category'] = sanitize_text_field($data['category']);
            $formats[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $formats[] = '%s';
        }
        
        if (isset($data['rider_id'])) {
            $update_data['rider_id'] = $data['rider_id'] ? absint($data['rider_id']) : null;
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_expenses',
            $update_data,
            array('id' => $expense_id),
            $formats,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete expense
     */
    public static function delete($expense_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'zaikon_expenses',
            array('id' => $expense_id),
            array('%d')
        );
    }
    
    /**
     * Get expense categories
     */
    public static function get_categories() {
        return array(
            'rider_payout' => __('Rider Payout', 'restaurant-pos'),
            'fuel' => __('Fuel', 'restaurant-pos'),
            'supplies' => __('Supplies', 'restaurant-pos'),
            'utilities' => __('Utilities', 'restaurant-pos'),
            'maintenance' => __('Maintenance', 'restaurant-pos'),
            'other' => __('Other', 'restaurant-pos')
        );
    }
    
    /**
     * Get total expenses for session
     */
    public static function get_session_total($session_id) {
        global $wpdb;
        
        return floatval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount_rs), 0) FROM {$wpdb->prefix}zaikon_expenses 
             WHERE session_id = %d",
            $session_id
        )));
    }
}
