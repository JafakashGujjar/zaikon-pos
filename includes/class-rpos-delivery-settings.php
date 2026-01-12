<?php
/**
 * Delivery Settings Management Class
 * 
 * @deprecated This class is deprecated - delivery settings now managed via Zaikon tables
 * @see Zaikon_Delivery_Charge_Slabs, Zaikon_Free_Delivery_Rules
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @deprecated Use Zaikon delivery management classes instead
 */
class RPOS_Delivery_Settings {
    
    /**
     * Get setting value
     */
    public static function get($key, $default = '') {
        global $wpdb;
        
        $setting = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_delivery_settings WHERE setting_key = %s",
            $key
        ));
        
        if (!$setting) {
            return $default;
        }
        
        return $setting->setting_value;
    }
    
    /**
     * Set setting value
     */
    public static function set($key, $value, $type = 'text') {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_key FROM {$wpdb->prefix}rpos_delivery_settings WHERE setting_key = %s",
            $key
        ));
        
        if ($existing) {
            return $wpdb->update(
                $wpdb->prefix . 'rpos_delivery_settings',
                array(
                    'setting_value' => sanitize_text_field($value),
                    'setting_type' => sanitize_text_field($type)
                ),
                array('setting_key' => $key),
                array('%s', '%s'),
                array('%s')
            );
        } else {
            return $wpdb->insert(
                $wpdb->prefix . 'rpos_delivery_settings',
                array(
                    'setting_key' => sanitize_text_field($key),
                    'setting_value' => sanitize_text_field($value),
                    'setting_type' => sanitize_text_field($type)
                ),
                array('%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Get all settings
     */
    public static function get_all() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_delivery_settings ORDER BY setting_key ASC"
        );
        
        $settings = array();
        foreach ($results as $row) {
            $settings[$row->setting_key] = array(
                'value' => $row->setting_value,
                'type' => $row->setting_type
            );
        }
        
        return $settings;
    }
}
