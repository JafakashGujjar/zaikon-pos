<?php
/**
 * Zaikon Free Delivery Rules Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Free_Delivery_Rules {
    
    /**
     * Get active rule (only one should be active)
     */
    public static function get_active_rule() {
        global $wpdb;
        
        return $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}zaikon_free_delivery_rules 
             WHERE is_active = 1 
             LIMIT 1"
        );
    }
    
    /**
     * Get all rules
     */
    public static function get_all() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}zaikon_free_delivery_rules 
             ORDER BY created_at DESC"
        );
    }
    
    /**
     * Get rule by ID
     */
    public static function get($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_free_delivery_rules WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create rule
     */
    public static function create($data) {
        global $wpdb;
        
        // Deactivate all other rules first if this one is active
        if (!empty($data['is_active'])) {
            $wpdb->update(
                $wpdb->prefix . 'zaikon_free_delivery_rules',
                array('is_active' => 0),
                array(),
                array('%d'),
                array()
            );
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_free_delivery_rules',
            array(
                'max_km' => floatval($data['max_km']),
                'min_order_amount_rs' => floatval($data['min_order_amount_rs']),
                'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%f', '%f', '%d', '%s', '%s')
        );
        
        if (!$result) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update rule
     */
    public static function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $formats = array();
        
        if (isset($data['max_km'])) {
            $update_data['max_km'] = floatval($data['max_km']);
            $formats[] = '%f';
        }
        
        if (isset($data['min_order_amount_rs'])) {
            $update_data['min_order_amount_rs'] = floatval($data['min_order_amount_rs']);
            $formats[] = '%f';
        }
        
        if (isset($data['is_active'])) {
            // Deactivate all other rules first if activating this one
            if (intval($data['is_active']) === 1) {
                $wpdb->update(
                    $wpdb->prefix . 'zaikon_free_delivery_rules',
                    array('is_active' => 0),
                    array(),
                    array('%d'),
                    array()
                );
            }
            
            $update_data['is_active'] = intval($data['is_active']);
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_free_delivery_rules',
            $update_data,
            array('id' => $id),
            $formats,
            array('%d')
        );
    }
    
    /**
     * Delete rule
     */
    public static function delete($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'zaikon_free_delivery_rules',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Check if delivery qualifies for free delivery
     */
    public static function qualifies_for_free_delivery($distance_km, $items_subtotal_rs) {
        $rule = self::get_active_rule();
        
        if (!$rule) {
            return false;
        }
        
        return ($distance_km <= floatval($rule->max_km) && 
                $items_subtotal_rs >= floatval($rule->min_order_amount_rs));
    }
}
