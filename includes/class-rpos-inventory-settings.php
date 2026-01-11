<?php
/**
 * Inventory Settings Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Inventory_Settings {
    
    /**
     * Get setting value
     */
    public static function get($key, $default = null) {
        global $wpdb;
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}rpos_inventory_settings WHERE setting_key = %s",
            $key
        ));
        
        if ($value !== null) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Set setting value
     */
    public static function set($key, $value, $type = 'text') {
        global $wpdb;
        
        return $wpdb->replace(
            $wpdb->prefix . 'rpos_inventory_settings',
            array(
                'setting_key' => sanitize_text_field($key),
                'setting_value' => sanitize_textarea_field($value),
                'setting_type' => sanitize_text_field($type)
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Get all settings
     */
    public static function get_all() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_inventory_settings ORDER BY setting_key ASC"
        );
        
        $settings = array();
        foreach ($results as $row) {
            $settings[$row->setting_key] = $row->setting_value;
        }
        
        return $settings;
    }
    
    /**
     * Delete setting
     */
    public static function delete($key) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_inventory_settings',
            array('setting_key' => $key),
            array('%s')
        );
    }
    
    /**
     * Get default settings
     */
    public static function get_defaults() {
        return array(
            'consumption_strategy' => 'FEFO',
            'expiry_warning_days' => '7',
            'low_stock_warning_days' => '3',
            'enable_batch_tracking' => '1',
            'require_batch_on_purchase' => '1',
            'auto_expire_batches' => '1',
            'inventory_currency_symbol' => 'Rs',
            'quantity_decimal_places' => '0',
            'price_decimal_places' => '2'
        );
    }
    
    /**
     * Initialize default settings if not exist
     */
    public static function init_defaults() {
        $defaults = self::get_defaults();
        
        foreach ($defaults as $key => $value) {
            $existing = self::get($key);
            if ($existing === null) {
                self::set($key, $value);
            }
        }
    }
    
    /**
     * Format currency value using inventory settings
     */
    public static function format_currency($value) {
        $symbol = self::get('inventory_currency_symbol', 'Rs');
        $decimals = intval(self::get('price_decimal_places', 2));
        
        return $symbol . number_format(floatval($value), $decimals);
    }
    
    /**
     * Format quantity value using inventory settings
     */
    public static function format_quantity($value, $unit = '') {
        $decimals = intval(self::get('quantity_decimal_places', 0));
        $formatted = number_format(floatval($value), $decimals);
        
        return $unit ? $formatted . ' ' . $unit : $formatted;
    }
}
