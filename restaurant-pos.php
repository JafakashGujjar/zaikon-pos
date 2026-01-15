<?php
/**
 * Plugin Name: Restaurant POS
 * Plugin URI: https://github.com/JafakashGujjar/gpt-pos
 * Description: Complete Restaurant Point of Sale system with POS, Kitchen Display, Inventory Management, and Analytics
 * Version: 1.0.0
 * Author: Restaurant POS Team
 * Author URI: https://github.com/JafakashGujjar
 * Text Domain: restaurant-pos
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RPOS_VERSION', '1.0.0');
define('RPOS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RPOS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RPOS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Restaurant POS Class
 */
class Restaurant_POS {
    
    /**
     * The single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Main Restaurant POS Instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required core files
     */
    public function includes() {
        // Core classes
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-install.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-database.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-roles.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-admin-menu.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-admin-notices.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-products.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-categories.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-inventory.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-ingredients.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-recipes.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-orders.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-settings.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-rest-api.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-pos.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-kds.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-reports.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-gas-cylinders.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-riders.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-notifications.php';
        
        // Batch/Lot inventory system classes
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-suppliers.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-batches.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-inventory-settings.php';
        
        // Zaikon POS Delivery & Reporting system classes
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-orders.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-order-items.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-delivery-locations.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-delivery-charge-slabs.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-free-delivery-rules.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-riders.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-deliveries.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-rider-orders.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-rider-payouts.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-rider-fuel-logs.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-system-events.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-delivery-calculator.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-order-service.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-reports.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-cashier-sessions.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-expenses.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-shift-reports-ajax.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-frontend.php';
    }
    
    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array('RPOS_Install', 'activate'));
        register_deactivation_hook(__FILE__, array('RPOS_Install', 'deactivate'));
        
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'check_version_and_migrate'), 5);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_rpos_get_inventory_item_details', array($this, 'ajax_get_inventory_item_details'));
        add_action('wp_ajax_rpos_add_ingredient', array($this, 'ajax_add_ingredient'));
    }
    
    /**
     * Check plugin version and run migrations if needed
     */
    public function check_version_and_migrate() {
        $stored_version = get_option('rpos_version', '0.0.0');
        
        // If version has changed, run migrations
        if (version_compare($stored_version, RPOS_VERSION, '<')) {
            // Run the rider system migration
            RPOS_Install::migrate_rider_system();
            
            // Update the stored version
            update_option('rpos_version', RPOS_VERSION);
            
            // Log the upgrade
            error_log('RPOS: Plugin upgraded from ' . $stored_version . ' to ' . RPOS_VERSION);
        }
    }
    
    /**
     * Init Restaurant POS when WordPress initializes
     */
    public function init() {
        // Initialize components
        RPOS_Admin_Menu::instance();
        RPOS_Admin_Notices::instance();
        RPOS_REST_API::instance();
        Zaikon_Frontend::init();
        
        // Load plugin text domain
        load_plugin_textdomain('restaurant-pos', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'restaurant-pos') === false) {
            return;
        }
        
        // Enqueue ZAIKON design system
        wp_enqueue_style('zaikon-design-system', RPOS_PLUGIN_URL . 'assets/css/zaikon-design-system.css', array(), RPOS_VERSION);
        wp_enqueue_style('zaikon-components', RPOS_PLUGIN_URL . 'assets/css/zaikon-components.css', array('zaikon-design-system'), RPOS_VERSION);
        
        // Enqueue screen-specific styles
        if (strpos($hook, 'restaurant-pos-cashier') !== false) {
            wp_enqueue_style('zaikon-pos-screen', RPOS_PLUGIN_URL . 'assets/css/zaikon-pos-screen.css', array('zaikon-design-system', 'zaikon-components'), RPOS_VERSION);
            wp_enqueue_style('rpos-delivery', RPOS_PLUGIN_URL . 'assets/css/delivery.css', array('zaikon-pos-screen'), RPOS_VERSION);
            wp_enqueue_script('rpos-delivery', RPOS_PLUGIN_URL . 'assets/js/delivery.js', array('jquery', 'rpos-admin'), RPOS_VERSION, true);
            wp_enqueue_script('rpos-rider-assignment', RPOS_PLUGIN_URL . 'assets/js/rider-assignment.js', array('jquery', 'rpos-admin'), RPOS_VERSION, true);
            wp_enqueue_script('rpos-session-management', RPOS_PLUGIN_URL . 'assets/js/session-management.js', array('jquery', 'rpos-admin'), RPOS_VERSION, true);
        } elseif (strpos($hook, 'restaurant-pos-kds') !== false) {
            wp_enqueue_style('zaikon-kds-screen', RPOS_PLUGIN_URL . 'assets/css/zaikon-kds-screen.css', array('zaikon-design-system', 'zaikon-components'), RPOS_VERSION);
        } else {
            // For all other admin pages
            wp_enqueue_style('zaikon-admin', RPOS_PLUGIN_URL . 'assets/css/zaikon-admin.css', array('zaikon-design-system', 'zaikon-components'), RPOS_VERSION);
            
            // Load modern dashboard styles on main dashboard page
            if (strpos($hook, 'toplevel_page_restaurant-pos') !== false) {
                wp_enqueue_style('zaikon-modern-dashboard', RPOS_PLUGIN_URL . 'assets/css/zaikon-modern-dashboard.css', array('zaikon-admin'), RPOS_VERSION);
            }
        }
        
        // Keep legacy admin.css for backwards compatibility (will be removed after templates are updated)
        wp_enqueue_style('rpos-admin', RPOS_PLUGIN_URL . 'assets/css/admin.css', array(), RPOS_VERSION);
        
        wp_enqueue_script('rpos-admin', RPOS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RPOS_VERSION, true);
        
        // Localize script with necessary data
        wp_localize_script('rpos-admin', 'rposAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rpos-admin-nonce'),
            'restUrl' => rest_url('restaurant-pos/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'currencySymbol' => RPOS_Settings::get('currency_symbol', '$')
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_scripts() {
        wp_enqueue_style('rpos-frontend', RPOS_PLUGIN_URL . 'assets/css/frontend.css', array(), RPOS_VERSION);
        wp_enqueue_script('rpos-frontend', RPOS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), RPOS_VERSION, true);
    }
    
    /**
     * AJAX handler to get inventory item details
     * Note: Currently using data attributes in the form, but this handler
     * is available for future use when dynamic inventory data is needed
     * (e.g., when adding ingredients via AJAX without page reload)
     */
    public function ajax_get_inventory_item_details() {
        check_ajax_referer('rpos-admin-nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_products')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        
        if (!$item_id) {
            wp_send_json_error(array('message' => 'Invalid item ID'));
            return;
        }
        
        $item = RPOS_Inventory::get_by_id($item_id);
        
        if (!$item) {
            wp_send_json_error(array('message' => 'Item not found'));
            return;
        }
        
        wp_send_json_success(array(
            'unit' => $item->unit ?: 'pcs',
            'cost_per_unit' => floatval($item->cost_price)
        ));
    }
    
    /**
     * AJAX handler to add a new ingredient
     */
    public function ajax_add_ingredient() {
        check_ajax_referer('rpos-admin-nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_inventory')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $ingredient_name = sanitize_text_field($_POST['ingredient_name'] ?? '');
        $unit = sanitize_text_field($_POST['unit'] ?? 'pcs');
        $default_cost = floatval($_POST['default_cost_per_unit'] ?? 0);
        
        if (empty($ingredient_name)) {
            wp_send_json_error(array('message' => 'Please provide ingredient name.'));
            return;
        }
        
        // Create new product (ingredient)
        $product_data = array(
            'name' => $ingredient_name,
            'sku' => '',
            'category_id' => 0,
            'selling_price' => 0,
            'image_url' => '',
            'description' => '',
            'is_active' => 1
        );
        
        $product_id = RPOS_Products::create($product_data);
        
        if ($product_id) {
            // Update inventory with unit and cost
            $inventory_data = array('unit' => $unit);
            if ($default_cost > 0) {
                $inventory_data['cost_price'] = $default_cost;
            }
            RPOS_Inventory::update($product_id, $inventory_data);
            
            wp_send_json_success(array(
                'message' => 'Ingredient added successfully!',
                'product_id' => $product_id,
                'product_name' => $ingredient_name
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create ingredient.'));
        }
    }
}

/**
 * Returns the main instance of Restaurant_POS
 */
function RPOS() {
    return Restaurant_POS::instance();
}

// Initialize the plugin
RPOS();
