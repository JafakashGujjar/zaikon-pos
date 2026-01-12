<?php
/**
 * Zaikon Delivery Calculator Class
 * Handles delivery charge calculations with free delivery rules and km-based slabs
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Delivery_Calculator {
    
    /**
     * Calculate delivery charge
     * Returns array with charge_rs and is_free_delivery
     */
    public static function calculate($distance_km, $items_subtotal_rs) {
        // Check free delivery rule first
        $free_rule = Zaikon_Free_Delivery_Rules::get_active_rule();
        
        if ($free_rule && 
            $distance_km <= floatval($free_rule->max_km) && 
            $items_subtotal_rs >= floatval($free_rule->min_order_amount_rs)) {
            
            return array(
                'charge_rs' => 0,
                'is_free_delivery' => 1,
                'rule_type' => 'free_delivery'
            );
        }
        
        // Otherwise, use km-based slabs
        $charge = Zaikon_Delivery_Charge_Slabs::get_charge_for_distance($distance_km);
        
        return array(
            'charge_rs' => $charge,
            'is_free_delivery' => 0,
            'rule_type' => 'slab_based'
        );
    }
    
    /**
     * Calculate delivery charge by location ID
     */
    public static function calculate_by_location($location_id, $items_subtotal_rs) {
        $location = Zaikon_Delivery_Locations::get($location_id);
        
        if (!$location) {
            return array(
                'charge_rs' => 0,
                'is_free_delivery' => 0,
                'rule_type' => 'error',
                'error' => 'Location not found'
            );
        }
        
        return self::calculate(floatval($location->distance_km), $items_subtotal_rs);
    }
}
