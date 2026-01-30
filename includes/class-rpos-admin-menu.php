<?php
/**
 * Admin Menu Handler
 * Modern SaaS-style menu with SVG icons and organized structure
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Admin_Menu {
    
    protected static $_instance = null;
    
    /**
     * SVG Icons for menu items (clean, consistent, same stroke weight)
     * Note: These SVG icons are hardcoded trusted values - do not make filterable without proper sanitization
     */
    private static $icons = array(
        // Main menu icon (for top-level Zaikon POS menu)
        'main' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h18v18H3z" rx="2"/><path d="M7 8h4v4H7z"/><path d="M13 8h4"/><path d="M13 12h4"/><path d="M7 16h10"/></svg>',
        
        // Dashboard / Screen icon
        'dashboard' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>',
        
        // POS Screen
        'pos' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 8h4"/><path d="M7 11h2"/></svg>',
        
        // Kitchen Display
        'kitchen' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2v6a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2"/><path d="M6 10v12"/><path d="M18 10v12"/><path d="M2 6h20"/><circle cx="12" cy="16" r="2"/></svg>',
        
        // Orders
        'orders' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><path d="M10 7h4"/><path d="M10 11h4"/><path d="M10 15h2"/></svg>',
        
        // Products (box/burger icon)
        'products' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
        
        // Categories
        'categories' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
        
        // Inventory (layers/warehouse icon)
        'inventory' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12.89 1.45l8 4A2 2 0 0 1 22 7.24v9.53a2 2 0 0 1-1.11 1.79l-8 4a2 2 0 0 1-1.79 0l-8-4a2 2 0 0 1-1.1-1.8V7.24a2 2 0 0 1 1.11-1.79l8-4a2 2 0 0 1 1.78 0z"/><path d="M2.32 6.16L12 11l9.68-4.84"/><path d="M12 22V11"/></svg>',
        
        // Ingredients
        'ingredients' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v8"/><path d="m4.93 10.93 1.41 1.41"/><path d="M2 18h2"/><path d="M20 18h2"/><path d="m19.07 10.93-1.41 1.41"/><path d="M22 22H2"/><path d="m16 6-4 4-4-4"/><path d="M16 18a4 4 0 0 0-8 0"/></svg>',
        
        // Stock Dashboard
        'stock' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>',
        
        // Batches
        'batches' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
        
        // Kitchen/Consumption
        'consumption' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z"/><path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>',
        
        // Waste/Spoilage
        'waste' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>',
        
        // Fryer Oil
        'oil' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2c2.5 0 5 4 5 8s-2.5 8-5 8-5-4-5-8 2.5-8 5-8z"/><path d="M10 2c-2.5 0-5 4-5 8s2.5 8 5 8"/><path d="M14 22v-4.5c0-2.5 5-2.5 5-5V6"/><circle cx="14" cy="6" r="2"/></svg>',
        
        // Delivery (bike/location icon)
        'delivery' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="18" r="3"/><circle cx="19" cy="18" r="3"/><path d="M12 18h3.5a2 2 0 0 0 1.6-.8L22 11h-3l-2 3h-4l-3-6H6l3 6"/><path d="M12 11V8h-2"/></svg>',
        
        // Customers
        'customers' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        
        // Rider
        'rider' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="18" r="3"/><circle cx="19" cy="18" r="3"/><path d="M12 2c1 3 4 4 4 4s-3 1-3 4"/><path d="M9 18h6"/><path d="M14 18V9s-1-1-2-1-2 1-2 1v9"/></svg>',
        
        // Reports (chart icon)
        'reports' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>',
        
        // Shift Reports
        'shift' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        
        // Cashier
        'cashier' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 12h4"/><path d="M14 12h4"/><path d="M6 16h12"/><circle cx="12" cy="8" r="2"/></svg>',
        
        // Expenses
        'expenses' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="1" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        
        // Suppliers
        'suppliers' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        
        // Gas Cylinders
        'gas' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8"/><path d="M9 2v2"/><path d="M15 2v2"/><rect x="7" y="4" width="10" height="18" rx="2"/><path d="M12 8v4"/><path d="M10 10h4"/></svg>',
        
        // Settings (gear icon)
        'settings' => '<svg class="rpos-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    );
    
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
     * Get SVG icon by name
     */
    private static function get_icon($name) {
        return isset(self::$icons[$name]) ? self::$icons[$name] : '';
    }
    
    /**
     * Create menu label with SVG icon
     */
    private static function menu_label($text, $icon_name, $is_submenu = false) {
        $icon = self::get_icon($icon_name);
        $class = $is_submenu ? 'rpos-submenu-item' : 'rpos-menu-item';
        return '<span class="' . $class . '">' . $icon . '<span class="rpos-menu-text">' . esc_html($text) . '</span></span>';
    }
    
    /**
     * Enqueue admin menu styles
     */
    public function enqueue_menu_styles($hook) {
        // The WordPress admin menu is present on all admin pages, so we load this globally
        // but only for authenticated users who can see the menu
        if (is_admin() && current_user_can('read')) {
            wp_enqueue_style('rpos-admin-menu', RPOS_PLUGIN_URL . 'assets/css/admin-menu.css', array(), RPOS_VERSION);
            
            // Add inline script to handle menu redirects to frontend URLs
            $this->add_menu_redirect_script();
        }
    }
    
    /**
     * Add inline script to redirect POS and KDS menu items to frontend
     */
    private function add_menu_redirect_script() {
        $pos_url = home_url('/pos/');
        $kds_url = home_url('/kitchen/');
        
        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            // Find POS Screen menu item and redirect to frontend
            var posLinks = document.querySelectorAll('a[href*=\"restaurant-pos-cashier\"]');
            posLinks.forEach(function(link) {
                link.setAttribute('href', '" . esc_js($pos_url) . "');
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener');
            });
            
            // Find Kitchen Display menu item and redirect to frontend
            var kdsLinks = document.querySelectorAll('a[href*=\"restaurant-pos-kds\"]');
            kdsLinks.forEach(function(link) {
                link.setAttribute('href', '" . esc_js($kds_url) . "');
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener');
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
    
    /**
     * Add admin menu items
     * Organized by: POS Operations, Products & Inventory, Kitchen & Consumption, Delivery, Reports, Suppliers & Assets, Settings
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
                'data:image/svg+xml;base64,' . base64_encode(self::get_icon('delivery')),
                30
            );
            return;
        }
        
        // Main menu with custom SVG icon
        add_menu_page(
            __('Restaurant POS', 'restaurant-pos'),
            __('Zaikon POS', 'restaurant-pos'),
            'read',
            'restaurant-pos',
            array($this, 'dashboard_page'),
            'data:image/svg+xml;base64,' . base64_encode(self::get_icon('main')),
            30
        );
        
        // =====================
        // SECTION 1: POS OPERATIONS
        // =====================
        
        // Section Header - POS Operations
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-section-header">' . self::get_icon('pos') . '<span>POS Operations</span></span>',
            'read',
            'rpos-section-pos',
            '__return_null'
        );
        
        // Dashboard
        add_submenu_page(
            'restaurant-pos',
            __('Dashboard', 'restaurant-pos'),
            self::menu_label(__('Dashboard', 'restaurant-pos'), 'dashboard', true),
            'read',
            'restaurant-pos',
            array($this, 'dashboard_page')
        );
        
        // POS Screen - Links to frontend (no longer in wp-admin)
        add_submenu_page(
            'restaurant-pos',
            __('POS Screen', 'restaurant-pos'),
            self::menu_label(__('POS Screen', 'restaurant-pos') . ' ↗', 'pos', true),
            'rpos_view_pos',
            'restaurant-pos-cashier',
            array($this, 'pos_redirect_page')
        );
        
        // Kitchen Display - Links to frontend (no longer in wp-admin)
        add_submenu_page(
            'restaurant-pos',
            __('Kitchen Display', 'restaurant-pos'),
            self::menu_label(__('Kitchen Display', 'restaurant-pos') . ' ↗', 'kitchen', true),
            'rpos_view_kds',
            'restaurant-pos-kds',
            array($this, 'kds_redirect_page')
        );
        
        // Orders
        add_submenu_page(
            'restaurant-pos',
            __('Orders', 'restaurant-pos'),
            self::menu_label(__('Orders', 'restaurant-pos'), 'orders', true),
            'rpos_view_orders',
            'restaurant-pos-orders',
            array($this, 'orders_page')
        );
        
        // =====================
        // SECTION 2: PRODUCTS & INVENTORY
        // =====================
        
        // Section Header - Products & Inventory
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-section-header">' . self::get_icon('products') . '<span>Products & Inventory</span></span>',
            'read',
            'rpos-section-products',
            '__return_null'
        );
        
        // Products
        add_submenu_page(
            'restaurant-pos',
            __('Products', 'restaurant-pos'),
            self::menu_label(__('Products', 'restaurant-pos'), 'products', true),
            'rpos_manage_products',
            'restaurant-pos-products',
            array($this, 'products_page')
        );
        
        // Categories
        add_submenu_page(
            'restaurant-pos',
            __('Categories', 'restaurant-pos'),
            self::menu_label(__('Categories', 'restaurant-pos'), 'categories', true),
            'rpos_manage_products',
            'restaurant-pos-categories',
            array($this, 'categories_page')
        );
        
        // Inventory
        add_submenu_page(
            'restaurant-pos',
            __('Inventory', 'restaurant-pos'),
            self::menu_label(__('Inventory', 'restaurant-pos'), 'inventory', true),
            'rpos_manage_inventory',
            'restaurant-pos-inventory',
            array($this, 'inventory_page')
        );
        
        // Ingredients
        add_submenu_page(
            'restaurant-pos',
            __('Ingredients', 'restaurant-pos'),
            self::menu_label(__('Ingredients', 'restaurant-pos'), 'ingredients', true),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients',
            array($this, 'ingredients_page')
        );
        
        // Stock Dashboard
        add_submenu_page(
            'restaurant-pos',
            __('Stock Dashboard', 'restaurant-pos'),
            self::menu_label(__('Stock Dashboard', 'restaurant-pos'), 'stock', true),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients-dashboard',
            array($this, 'ingredients_dashboard_page')
        );
        
        // Batches / Lots
        add_submenu_page(
            'restaurant-pos',
            __('Batches / Lots', 'restaurant-pos'),
            self::menu_label(__('Batches / Lots', 'restaurant-pos'), 'batches', true),
            'rpos_manage_inventory',
            'restaurant-pos-batches',
            array($this, 'batches_page')
        );
        
        // =====================
        // SECTION 3: KITCHEN & CONSUMPTION
        // =====================
        
        // Section Header - Kitchen & Consumption
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-section-header">' . self::get_icon('consumption') . '<span>Kitchen & Consumption</span></span>',
            'read',
            'rpos-section-kitchen',
            '__return_null'
        );
        
        // Usage Report
        add_submenu_page(
            'restaurant-pos',
            __('Usage Report', 'restaurant-pos'),
            self::menu_label(__('Usage Report', 'restaurant-pos'), 'reports', true),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients-report',
            array($this, 'ingredients_report_page')
        );
        
        // Waste / Spoilage
        add_submenu_page(
            'restaurant-pos',
            __('Waste / Spoilage', 'restaurant-pos'),
            self::menu_label(__('Waste / Spoilage', 'restaurant-pos'), 'waste', true),
            'rpos_manage_inventory',
            'restaurant-pos-ingredients-waste',
            array($this, 'ingredients_waste_page')
        );
        
        // Fryer Oil
        add_submenu_page(
            'restaurant-pos',
            __('Fryer Oil', 'restaurant-pos'),
            self::menu_label(__('Fryer Oil', 'restaurant-pos'), 'oil', true),
            'rpos_manage_inventory',
            'restaurant-pos-fryer-oil',
            array($this, 'fryer_oil_dashboard_page')
        );
        
        // Oil Batches
        add_submenu_page(
            'restaurant-pos',
            __('Oil Batches', 'restaurant-pos'),
            self::menu_label(__('Oil Batches', 'restaurant-pos'), 'batches', true),
            'rpos_manage_inventory',
            'restaurant-pos-fryer-oil-batches',
            array($this, 'fryer_oil_batches_page')
        );
        
        // Oil Settings
        add_submenu_page(
            'restaurant-pos',
            __('Oil Settings', 'restaurant-pos'),
            self::menu_label(__('Oil Settings', 'restaurant-pos'), 'settings', true),
            'rpos_manage_inventory',
            'restaurant-pos-fryer-oil-settings',
            array($this, 'fryer_oil_settings_page')
        );
        
        // =====================
        // SECTION 4: DELIVERY
        // =====================
        
        // Section Header - Delivery
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-section-header">' . self::get_icon('delivery') . '<span>Delivery</span></span>',
            'read',
            'rpos-section-delivery',
            '__return_null'
        );
        
        // Delivery Management
        add_submenu_page(
            'restaurant-pos',
            __('Delivery Management', 'restaurant-pos'),
            self::menu_label(__('Delivery Management', 'restaurant-pos'), 'delivery', true),
            'rpos_manage_settings',
            'restaurant-pos-zaikon-delivery',
            array($this, 'zaikon_delivery_management_page')
        );
        
        // Delivery Customers
        add_submenu_page(
            'restaurant-pos',
            __('Delivery Customers', 'restaurant-pos'),
            self::menu_label(__('Delivery Customers', 'restaurant-pos'), 'customers', true),
            'rpos_view_reports',
            'restaurant-pos-delivery-customers',
            array($this, 'delivery_customers_page')
        );
        
        // Rider Deliveries
        add_submenu_page(
            'restaurant-pos',
            __('Rider Deliveries', 'restaurant-pos'),
            self::menu_label(__('Rider Deliveries', 'restaurant-pos'), 'rider', true),
            'rpos_view_reports',
            'restaurant-pos-rider-deliveries-admin',
            array($this, 'rider_deliveries_admin_page')
        );
        
        // Rider Payroll
        add_submenu_page(
            'restaurant-pos',
            __('Rider Payroll', 'restaurant-pos'),
            self::menu_label(__('Rider Payroll', 'restaurant-pos'), 'expenses', true),
            'rpos_view_reports',
            'restaurant-pos-rider-payroll',
            array($this, 'rider_payroll_page')
        );
        
        // =====================
        // SECTION 5: REPORTS
        // =====================
        
        // Section Header - Reports
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-section-header">' . self::get_icon('reports') . '<span>Reports</span></span>',
            'read',
            'rpos-section-reports',
            '__return_null'
        );
        
        // Reports
        add_submenu_page(
            'restaurant-pos',
            __('Reports', 'restaurant-pos'),
            self::menu_label(__('Reports', 'restaurant-pos'), 'reports', true),
            'rpos_view_reports',
            'restaurant-pos-reports',
            array($this, 'reports_page')
        );
        
        // Shift Reports
        add_submenu_page(
            'restaurant-pos',
            __('Shift Reports', 'restaurant-pos'),
            self::menu_label(__('Shift Reports', 'restaurant-pos'), 'shift', true),
            'rpos_view_reports',
            'restaurant-pos-shift-reports',
            array($this, 'shift_reports_page')
        );
        
        // Cashier Shifts
        add_submenu_page(
            'restaurant-pos',
            __('Cashier Shifts', 'restaurant-pos'),
            self::menu_label(__('Cashier Shifts', 'restaurant-pos'), 'cashier', true),
            'rpos_view_reports',
            'restaurant-pos-cashier-shifts',
            array($this, 'cashier_shifts_page')
        );
        
        // Kitchen Staff Report
        add_submenu_page(
            'restaurant-pos',
            __('Kitchen Staff Report', 'restaurant-pos'),
            self::menu_label(__('Kitchen Staff Report', 'restaurant-pos'), 'kitchen', true),
            'rpos_view_reports',
            'restaurant-pos-kitchen-staff-report',
            array($this, 'kitchen_staff_report_page')
        );
        
        // Oil Reports
        add_submenu_page(
            'restaurant-pos',
            __('Oil Reports', 'restaurant-pos'),
            self::menu_label(__('Oil Reports', 'restaurant-pos'), 'oil', true),
            'rpos_view_reports',
            'restaurant-pos-fryer-oil-reports',
            array($this, 'fryer_oil_reports_page')
        );
        
        // Expenses History
        add_submenu_page(
            'restaurant-pos',
            __('Expenses History', 'restaurant-pos'),
            self::menu_label(__('Expenses History', 'restaurant-pos'), 'expenses', true),
            'rpos_view_reports',
            'restaurant-pos-expenses-history',
            array($this, 'expenses_history_page')
        );
        
        // =====================
        // SECTION 6: SUPPLIERS & ASSETS
        // =====================
        
        // Section Header - Suppliers & Assets
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-section-header">' . self::get_icon('suppliers') . '<span>Suppliers & Assets</span></span>',
            'read',
            'rpos-section-suppliers',
            '__return_null'
        );
        
        // Suppliers
        add_submenu_page(
            'restaurant-pos',
            __('Suppliers', 'restaurant-pos'),
            self::menu_label(__('Suppliers', 'restaurant-pos'), 'suppliers', true),
            'rpos_manage_inventory',
            'restaurant-pos-suppliers',
            array($this, 'suppliers_page')
        );
        
        // Gas Cylinders
        add_submenu_page(
            'restaurant-pos',
            __('Gas Cylinders', 'restaurant-pos'),
            self::menu_label(__('Gas Cylinders', 'restaurant-pos'), 'gas', true),
            'rpos_manage_inventory',
            'restaurant-pos-gas-cylinders',
            array($this, 'gas_cylinders_page')
        );
        
        // =====================
        // SECTION 7: SETTINGS
        // =====================
        
        // Section Header - Settings
        add_submenu_page(
            'restaurant-pos',
            '',
            '<span class="rpos-section-header">' . self::get_icon('settings') . '<span>Settings</span></span>',
            'read',
            'rpos-section-settings',
            '__return_null'
        );
        
        // Inventory Settings
        add_submenu_page(
            'restaurant-pos',
            __('Inventory Settings', 'restaurant-pos'),
            self::menu_label(__('Inventory Settings', 'restaurant-pos'), 'inventory', true),
            'rpos_manage_settings',
            'restaurant-pos-inventory-settings',
            array($this, 'inventory_settings_page')
        );
        
        // Settings
        add_submenu_page(
            'restaurant-pos',
            __('Settings', 'restaurant-pos'),
            self::menu_label(__('Settings', 'restaurant-pos'), 'settings', true),
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
     * POS page - Redirect to frontend
     * POS operations are now on the frontend for enterprise-level performance
     */
    public function pos_redirect_page() {
        $pos_url = home_url('/pos/');
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('POS Screen', 'restaurant-pos') . '</h1>';
        echo '<div class="notice notice-info" style="padding: 20px; margin-top: 20px;">';
        echo '<h2 style="margin-top: 0;">' . esc_html__('POS Has Moved to Frontend', 'restaurant-pos') . '</h2>';
        echo '<p style="font-size: 14px;">' . esc_html__('For better performance and security, the POS Cashier screen is now available on the frontend.', 'restaurant-pos') . '</p>';
        echo '<p><a href="' . esc_url($pos_url) . '" class="button button-primary button-hero" target="_blank" rel="noopener">';
        echo esc_html__('Open POS Screen', 'restaurant-pos') . ' ↗</a></p>';
        echo '<p style="color: #666; margin-bottom: 0;"><strong>' . esc_html__('Frontend URL:', 'restaurant-pos') . '</strong> <code>' . esc_html($pos_url) . '</code></p>';
        echo '</div></div>';
        
        // Auto-redirect after a short delay
        echo '<script>setTimeout(function() { window.location.href = "' . esc_js($pos_url) . '"; }, 2000);</script>';
    }
    
    /**
     * POS page (legacy method - kept for backwards compatibility with direct includes)
     */
    public function pos_page() {
        include RPOS_PLUGIN_DIR . 'includes/admin/pos.php';
    }
    
    /**
     * Kitchen Display page - Redirect to frontend
     * Kitchen Display is now on the frontend for enterprise-level usage
     */
    public function kds_redirect_page() {
        $kds_url = home_url('/kitchen/');
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Kitchen Display', 'restaurant-pos') . '</h1>';
        echo '<div class="notice notice-info" style="padding: 20px; margin-top: 20px;">';
        echo '<h2 style="margin-top: 0;">' . esc_html__('Kitchen Display Has Moved to Frontend', 'restaurant-pos') . '</h2>';
        echo '<p style="font-size: 14px;">' . esc_html__('For better performance and security, the Kitchen Display System is now available on the frontend.', 'restaurant-pos') . '</p>';
        echo '<p><a href="' . esc_url($kds_url) . '" class="button button-primary button-hero" target="_blank" rel="noopener">';
        echo esc_html__('Open Kitchen Display', 'restaurant-pos') . ' ↗</a></p>';
        echo '<p style="color: #666; margin-bottom: 0;"><strong>' . esc_html__('Frontend URL:', 'restaurant-pos') . '</strong> <code>' . esc_html($kds_url) . '</code></p>';
        echo '</div></div>';
        
        // Auto-redirect after a short delay
        echo '<script>setTimeout(function() { window.location.href = "' . esc_js($kds_url) . '"; }, 2000);</script>';
    }
    
    /**
     * Kitchen Display page (legacy method - kept for backwards compatibility with direct includes)
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
