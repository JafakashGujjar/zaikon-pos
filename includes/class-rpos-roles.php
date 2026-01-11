<?php
/**
 * User Roles and Capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Roles {
    
    /**
     * Create custom roles
     */
    public static function create_roles() {
        // Restaurant Admin - Full access
        add_role('restaurant_admin', __('Restaurant Admin', 'restaurant-pos'), array(
            'read' => true,
            'rpos_manage_products' => true,
            'rpos_manage_inventory' => true,
            'rpos_view_pos' => true,
            'rpos_process_orders' => true,
            'rpos_view_kds' => true,
            'rpos_view_reports' => true,
            'rpos_manage_settings' => true,
            'rpos_view_orders' => true,
        ));
        
        // Cashier - POS and orders only
        add_role('cashier', __('Cashier', 'restaurant-pos'), array(
            'read' => true,
            'rpos_view_pos' => true,
            'rpos_process_orders' => true,
            'rpos_view_orders' => true,
        ));
        
        // Kitchen Staff - Kitchen display only
        add_role('kitchen_staff', __('Kitchen Staff', 'restaurant-pos'), array(
            'read' => true,
            'rpos_view_kds' => true,
        ));
        
        // Inventory Manager - Products and inventory only
        add_role('inventory_manager', __('Inventory Manager', 'restaurant-pos'), array(
            'read' => true,
            'rpos_manage_products' => true,
            'rpos_manage_inventory' => true,
        ));
        
        // Delivery Rider - View assigned deliveries only
        add_role('delivery_rider', __('Delivery Rider', 'restaurant-pos'), array(
            'read' => true,
            'rpos_view_deliveries' => true,
        ));
        
        // Add capabilities to administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('rpos_manage_products');
            $admin->add_cap('rpos_manage_inventory');
            $admin->add_cap('rpos_view_pos');
            $admin->add_cap('rpos_process_orders');
            $admin->add_cap('rpos_view_kds');
            $admin->add_cap('rpos_view_reports');
            $admin->add_cap('rpos_manage_settings');
            $admin->add_cap('rpos_view_orders');
        }
    }
    
    /**
     * Remove custom roles
     */
    public static function remove_roles() {
        remove_role('restaurant_admin');
        remove_role('cashier');
        remove_role('kitchen_staff');
        remove_role('inventory_manager');
        remove_role('delivery_rider');
        
        // Remove capabilities from administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->remove_cap('rpos_manage_products');
            $admin->remove_cap('rpos_manage_inventory');
            $admin->remove_cap('rpos_view_pos');
            $admin->remove_cap('rpos_process_orders');
            $admin->remove_cap('rpos_view_kds');
            $admin->remove_cap('rpos_view_reports');
            $admin->remove_cap('rpos_manage_settings');
            $admin->remove_cap('rpos_view_orders');
        }
    }
}
