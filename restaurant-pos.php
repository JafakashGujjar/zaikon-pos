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
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-products.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-categories.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-inventory.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-recipes.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-orders.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-settings.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-rest-api.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-pos.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-kds.php';
        require_once RPOS_PLUGIN_DIR . 'includes/class-rpos-reports.php';
    }
    
    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array('RPOS_Install', 'activate'));
        register_deactivation_hook(__FILE__, array('RPOS_Install', 'deactivate'));
        
        add_action('init', array($this, 'init'), 0);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_rpos_get_inventory_item_details', array($this, 'ajax_get_inventory_item_details'));
    }
    
    /**
     * Init Restaurant POS when WordPress initializes
     */
    public function init() {
        // Initialize components
        RPOS_Admin_Menu::instance();
        RPOS_REST_API::instance();
        
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
        
        wp_enqueue_style('rpos-admin', RPOS_PLUGIN_URL . 'assets/css/admin.css', array(), RPOS_VERSION);
        wp_enqueue_script('rpos-admin', RPOS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RPOS_VERSION, true);
        
        // Localize script with necessary data
        wp_localize_script('rpos-admin', 'rposAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rpos-admin-nonce'),
            'restUrl' => rest_url('restaurant-pos/v1/'),
            'restNonce' => wp_create_nonce('wp_rest')
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
}

/**
 * Returns the main instance of Restaurant_POS
 */
function RPOS() {
    return Restaurant_POS::instance();
}

// Initialize the plugin
RPOS();
