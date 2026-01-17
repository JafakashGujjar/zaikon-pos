<?php
/**
 * Settings Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Settings {
    
    /**
     * Get setting value
     */
    public static function get($key, $default = '') {
        // Enforce mandatory defaults for currency and timezone
        if ($key === 'currency_symbol') {
            return 'Rs';
        }
        if ($key === 'pos_timezone') {
            return 'Asia/Karachi';
        }
        
        global $wpdb;
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}rpos_settings WHERE setting_key = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }
    
    /**
     * Update or create setting
     */
    public static function update($key, $value) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rpos_settings WHERE setting_key = %s",
            $key
        ));
        
        if ($existing) {
            return $wpdb->update(
                $wpdb->prefix . 'rpos_settings',
                array('setting_value' => $value),
                array('setting_key' => $key),
                array('%s'),
                array('%s')
            );
        } else {
            return $wpdb->insert(
                $wpdb->prefix . 'rpos_settings',
                array(
                    'setting_key' => $key,
                    'setting_value' => $value
                ),
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Get all settings
     */
    public static function get_all() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT setting_key, setting_value FROM {$wpdb->prefix}rpos_settings",
            ARRAY_A
        );
        
        $settings = array();
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }
}
