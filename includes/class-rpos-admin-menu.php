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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_menu_styles'));
    }
    
    /**
     * Enqueue admin menu styles
     */
    public function enqueue_menu_styles($hook) {
        // Only enqueue on admin pages where the menu is visible
        // The WordPress admin menu is present on all admin pages, so we load this globally
        // but only for authenticated users who can see the menu
        if (is_admin() && current_user_can('read')) {
            wp_enqueue_style('rpos-admin-menu', RPOS_PLUGIN_URL . 'assets/css/admin-menu.css', array(), RPOS_VERSION);
        }
    }
    
    /**
     * Add admin menu items
     */
    public function add_menu() {
        $current_user = wp_get_current_user();
        
        // If user is a delivery rider, only show rider menu
        if (in_array('delivery_rider', (array) $current_user->roles)) {
            add_menu_page(
                __('My Deliveries', 'restaurant-pos'),
                __('My Deliveries', 'restaurant-pos'),
                'read',
                'restaurant-pos-rider',
                array($this, 'rider_deliveries_page'),
                'dashicons-car',
                30
            );
            return;
        }
        
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
        
        // Separator 1
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-separator">───</span>',
            'read',
            'rpos-separator-1',
            '__return_null'
        );
        
        // Products Section Header
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-section-header">▸ Products</span>',
            'read',
            'rpos-products-header',
            '__return_null'
        );
        
        // Products
        add_submenu_page(
            'restaurant-pos',
            __('Products', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Products', 'restaurant-pos'),
            'rpos_manage_products',
            'restaurant-pos-products',
            array($this, 'products_page')
        );
        
        // Categories
        add_submenu_page(
            'restaurant-pos',
            __('Categories', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Categories', 'restaurant-pos'),
            'rpos_manage_products',
            'restaurant-pos-categories',
            array($this, 'categories_page')
        );
        
        // Separator 2
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-separator">───</span>',
            'read',
            'rpos-separator-2',
            '__return_null'
        );
        
        // Inventory Section Header
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-section-header">▸ Inventory</span>',
            'read',
            'rpos-inventory-header',
            '__return_null'
        );
        
        // Inventory
        add_submenu_page(
            'restaurant-pos',
            __('Inventory', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Inventory', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-inventory',
            array($this, 'inventory_page')
        );
        
        // Ingredients
        add_submenu_page(
            'restaurant-pos',
            __('Ingredients', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Ingredients', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients',
            array($this, 'ingredients_page')
        );
        
        // Ingredients Usage Report
        add_submenu_page(
            'restaurant-pos',
            __('Ingredients Usage Report', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Usage Report', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients-report',
            array($this, 'ingredients_report_page')
        );
        
        // Ingredients Waste/Spoilage
        add_submenu_page(
            'restaurant-pos',
            __('Waste / Spoilage', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Waste / Spoilage', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients-waste',
            array($this, 'ingredients_waste_page')
        );
        
        // Stock Intelligence Dashboard
        add_submenu_page(
            'restaurant-pos',
            __('Stock Dashboard', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Stock Dashboard', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients-dashboard',
            array($this, 'ingredients_dashboard_page')
        );
        
        // Suppliers
        add_submenu_page(
            'restaurant-pos',
            __('Suppliers', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Suppliers', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-suppliers',
            array($this, 'suppliers_page')
        );
        
        // Batches/Lots
        add_submenu_page(
            'restaurant-pos',
            __('Batches / Lots', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Batches / Lots', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-batches',
            array($this, 'batches_page')
        );
        
        // Inventory Settings
        add_submenu_page(
            'restaurant-pos',
            __('Inventory Settings', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Inventory Settings', 'restaurant-pos'),
            'rpos_manage_settings',
            'restaurant-pos-inventory-settings',
            array($this, 'inventory_settings_page')
        );
        
        // Gas Cylinders
        add_submenu_page(
            'restaurant-pos',
            __('Gas Cylinders', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Gas Cylinders', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-gas-cylinders',
            array($this, 'gas_cylinders_page')
        );
        
        // Fryer Oil Management
        add_submenu_page(
            'restaurant-pos',
            __('Fryer Oil Dashboard', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Fryer Oil', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-fryer-oil',
            array($this, 'fryer_oil_dashboard_page')
        );
        
        add_submenu_page(
            'restaurant-pos',
            __('Fryer Oil Batches', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Oil Batches', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-fryer-oil-batches',
            array($this, 'fryer_oil_batches_page')
        );
        
        add_submenu_page(
            'restaurant-pos',
            __('Fryer Oil Settings', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Oil Settings', 'restaurant-pos'),
            'rpos_manage_inventory',
            'restaurant-pos-fryer-oil-settings',
            array($this, 'fryer_oil_settings_page')
        );
        
        // Separator 3
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-separator">───</span>',
            'read',
            'rpos-separator-3',
            '__return_null'
        );
        
        // Orders & Reports Section Header
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-section-header">▸ Orders &amp; Reports</span>',
            'read',
            'rpos-orders-header',
            '__return_null'
        );
        
        // Orders
        add_submenu_page(
            'restaurant-pos',
            __('Orders', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Orders', 'restaurant-pos'),
            'rpos_view_orders',
            'restaurant-pos-orders',
            array($this, 'orders_page')
        );
        
        // Reports
        add_submenu_page(
            'restaurant-pos',
            __('Reports', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Reports', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-reports',
            array($this, 'reports_page')
        );
        
        // Shift Reports
        add_submenu_page(
            'restaurant-pos',
            __('Shift Reports', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Shift Reports', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-shift-reports',
            array($this, 'shift_reports_page')
        );
        
        // Kitchen Staff Report (under Reports)
        add_submenu_page(
            'restaurant-pos',
            __('Kitchen Staff Report', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Kitchen Staff Report', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-kitchen-staff-report',
            array($this, 'kitchen_staff_report_page')
        );
        
        // Oil Reports
        add_submenu_page(
            'restaurant-pos',
            __('Fryer Oil Reports', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Oil Reports', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-fryer-oil-reports',
            array($this, 'fryer_oil_reports_page')
        );
        
        // Cashier Shifts & Expenses
        add_submenu_page(
            'restaurant-pos',
            __('Cashier Shifts & Expenses', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Cashier Shifts', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-cashier-shifts',
            array($this, 'cashier_shifts_page')
        );
        
        // Expenses History
        add_submenu_page(
            'restaurant-pos',
            __('Expenses History', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Expenses History', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-expenses-history',
            array($this, 'expenses_history_page')
        );
        
        // Separator 4
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-separator">───</span>',
            'read',
            'rpos-separator-4',
            '__return_null'
        );
        
        // Delivery Section Header
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-section-header">▸ Delivery</span>',
            'read',
            'rpos-delivery-header',
            '__return_null'
        );
        
        // Zaikon Delivery Management (New comprehensive system)
        add_submenu_page(
            'restaurant-pos',
            __('Delivery Management', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Delivery Management', 'restaurant-pos'),
            'rpos_manage_settings',
            'restaurant-pos-zaikon-delivery',
            array($this, 'zaikon_delivery_management_page')
        );
        
        // Delivery Customers Analytics
        add_submenu_page(
            'restaurant-pos',
            __('Delivery Customers', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Delivery Customers', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-delivery-customers',
            array($this, 'delivery_customers_page')
        );
        
        // Rider Payroll
        add_submenu_page(
            'restaurant-pos',
            __('Rider Payroll', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Rider Payroll', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-rider-payroll',
            array($this, 'rider_payroll_page')
        );
        
        // Rider Deliveries Admin View
        add_submenu_page(
            'restaurant-pos',
            __('Rider Deliveries (Admin)', 'restaurant-pos'),
            '&nbsp;&nbsp;&nbsp;&nbsp;' . __('Rider Deliveries (Admin)', 'restaurant-pos'),
            'rpos_view_reports',
            'restaurant-pos-rider-deliveries-admin',
            array($this, 'rider_deliveries_admin_page')
        );
        
        // Separator 5
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-menu-separator">───</span>',
            'read',
            'rpos-separator-5',
            '__return_null'
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
     * Ingredients waste page
     */
    public function ingredients_waste_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/ingredients-waste.php';
    }
    
    /**
     * Ingredients dashboard page
     */
    public function ingredients_dashboard_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/ingredients-dashboard.php';
    }
    
    /**
     * Gas Cylinders page
     */
    public function gas_cylinders_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/gas-cylinders-enterprise.php';
    }
    
    /**
     * Fryer Oil Dashboard page
     */
    public function fryer_oil_dashboard_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/fryer-oil-dashboard.php';
    }
    
    /**
     * Fryer Oil Batches page
     */
    public function fryer_oil_batches_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/fryer-oil-batches.php';
    }
    
    /**
     * Fryer Oil Settings page
     */
    public function fryer_oil_settings_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/fryer-oil-settings.php';
    }
    
    /**
     * Fryer Oil Reports page
     */
    public function fryer_oil_reports_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/fryer-oil-reports.php';
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
    
    /**
     * Zaikon Delivery Management page
     */
    public function zaikon_delivery_management_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/zaikon-delivery-management.php';
    }
    
    /**
     * Suppliers page
     */
    public function suppliers_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/suppliers.php';
    }
    
    /**
     * Batches page
     */
    public function batches_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/batches.php';
    }
    
    /**
     * Inventory Settings page
     */
    public function inventory_settings_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/inventory-settings.php';
    }
    
    /**
     * Delivery Customers page (Zaikon v2)
     */
    public function delivery_customers_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/delivery-customers.php';
    }
    
    /**
     * Kitchen Staff Report page
     */
    public function kitchen_staff_report_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/kitchen-staff-report.php';
    }
    
    /**
     * Rider Deliveries page (for delivery riders only)
     */
    public function rider_deliveries_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/rider-deliveries.php';
    }
    
    /**
     * Rider Payroll page
     */
    public function rider_payroll_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/rider-payroll.php';
    }
    
    /**
     * Shift Reports page
     */
    public function shift_reports_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/shift-reports.php';
    }
    
    /**
     * Cashier Shifts & Expenses page
     */
    public function cashier_shifts_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/cashier-shifts-expenses.php';
    }
    
    /**
     * Expenses History page
     */
    public function expenses_history_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/expenses-history.php';
    }
    
    /**
     * Rider Deliveries Admin page
     */
    public function rider_deliveries_admin_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/rider-deliveries-admin.php';
    }
}
