<?php
/**
 * Admin Menu Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Admin_Menu {
    
    protected static $_instance = null;
    
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_menu() {
        // Main menu
        add_menu_page(
            __('Restaurant POS', 'restaurant-pos'),
            __('Restaurant POS', 'restaurant-pos'),
            'read',
            'restaurant-pos',
            array($this, 'dashboard_page'),
            'dashicons-store',
            30
        );
        
        // Dashboard
        add_submenu_page(
            'restaurant-pos',
            __('Dashboard', 'restaurant-pos'),
            __('Dashboard', 'restaurant-pos'),
            'read',
            'restaurant-pos',
            array($this, 'dashboard_page')
        );
        
        // POS Screen
        add_submenu_page(
            'restaurant-pos',
            __('POS Screen', 'restaurant-pos'),
            __('POS Screen', 'restaurant-pos'),
            'rpos_view_pos',
            'restaurant-pos-cashier',
            array($this, 'pos_page')
        );
        
        // Kitchen Display
        add_submenu_page(
            'restaurant-pos',
            __('Kitchen Display', 'restaurant-pos'),
            __('Kitchen Display', 'restaurant-pos'),
            'rpos_view_kds',
            'restaurant-pos-kds',
            array($this, 'kds_page')
        );
        
        // Products
        add_submenu_page(
            'restaurant-pos',
            __('Products', 'restaurant-pos'),
            __('Products', 'restaurant-pos'),
            'rpos_manage_products',
            'restaurant-pos-products',
            array($this, 'products_page')
        );
        
        // Categories
        add_submenu_page(
            'restaurant-pos',
            __('Categories', 'restaurant-pos'),
            __('Categories', 'restaurant-pos'),
            'rpos_manage_products',
            'restaurant-pos-categories',
            array($this, 'categories_page')
        );
        
        // Inventory
        add_submenu_page(
            'restaurant-pos',
            __('Inventory', 'restaurant-pos'),
            __('Inventory', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-inventory',
            array($this, 'inventory_page')
        );
        
        // Ingredients
        add_submenu_page(
            'restaurant-pos',
            __('Ingredients', 'restaurant-pos'),
            __('Ingredients', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients',
            array($this, 'ingredients_page')
        );
        
        // Ingredients Usage Report
        add_submenu_page(
            'restaurant-pos-ingredients',
            __('Ingredients Usage Report', 'restaurant-pos'),
            __('Usage Report', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients-report',
            array($this, 'ingredients_report_page')
        );
        
        // Orders
        add_submenu_page(
            'restaurant-pos',
            __('Orders', 'restaurant-pos'),
            __('Orders', 'restaurant-pos'),
            'rpos_view_orders',
            'restaurant-pos-orders',
            array($this, 'orders_page')
        );
        
        // Reports
        add_submenu_page(
            'restaurant-pos',
            __('Reports', 'restaurant-pos'),
            __('Reports', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-reports',
            array($this, 'reports_page')
        );
        
        // Settings
        add_submenu_page(
            'restaurant-pos',
            __('Settings', 'restaurant-pos'),
            __('Settings', 'restaurant-pos'),
            'rpos_manage_settings',
            'restaurant-pos-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/dashboard.php';
    }
    
    /**
     * POS page
     */
    public function pos_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/pos.php';
    }
    
    /**
     * Kitchen Display page
     */
    public function kds_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/kds.php';
    }
    
    /**
     * Products page
     */
    public function products_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/products.php';
    }
    
    /**
     * Categories page
     */
    public function categories_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/categories.php';
    }
    
    /**
     * Inventory page
     */
    public function inventory_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/inventory.php';
    }
    
    /**
     * Ingredients page
     */
    public function ingredients_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/ingredients.php';
    }
    
    /**
     * Ingredients report page
     */
    public function ingredients_report_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/ingredients-report.php';
    }
    
    /**
     * Orders page
     */
    public function orders_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/orders.php';
    }
    
    /**
     * Reports page
     */
    public function reports_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/reports.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/settings.php';
    }
}
