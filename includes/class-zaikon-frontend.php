<?php
/**
 * Zaikon Frontend Handler
 * Handles non-WP-admin frontend access for POS, KDS, and Rider screens
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Frontend {
    
    /**
     * Initialize frontend handler
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_filter('template_include', array(__CLASS__, 'load_template'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }
    
    /**
     * Add custom rewrite rules
     */
    public static function add_rewrite_rules() {
        // Primary frontend URLs for POS operations (simple, enterprise-friendly)
        add_rewrite_rule('^pos/?$', 'index.php?zaikon_pos_page=pos', 'top');
        add_rewrite_rule('^kitchen/?$', 'index.php?zaikon_pos_page=kds', 'top');
        
        // Legacy zaikon-pos prefixed URLs (for backwards compatibility)
        add_rewrite_rule('^zaikon-pos/?$', 'index.php?zaikon_pos_page=dashboard', 'top');
        add_rewrite_rule('^zaikon-pos/pos/?$', 'index.php?zaikon_pos_page=pos', 'top');
        add_rewrite_rule('^zaikon-pos/kds/?$', 'index.php?zaikon_pos_page=kds', 'top');
        add_rewrite_rule('^zaikon-pos/deliveries/?$', 'index.php?zaikon_pos_page=deliveries', 'top');
        
        // Public tracking page - no login required
        add_rewrite_rule('^track-order/([a-f0-9]+)/?$', 'index.php?zaikon_tracking_token=$matches[1]', 'top');
    }
    
    /**
     * Add custom query vars
     */
    public static function add_query_vars($vars) {
        $vars[] = 'zaikon_pos_page';
        $vars[] = 'zaikon_tracking_token';
        return $vars;
    }
    
    /**
     * Load custom template
     */
    public static function load_template($template) {
        $page = get_query_var('zaikon_pos_page');
        $tracking_token = get_query_var('zaikon_tracking_token');
        
        // Handle public tracking page (no login required)
        if ($tracking_token) {
            error_log('ZAIKON FRONTEND: Tracking page loaded with token: ' . substr($tracking_token, 0, 8) . '...' . substr($tracking_token, -4));
            
            $tracking_template = RPOS_PLUGIN_DIR . 'templates/tracking-page.php';
            
            if (file_exists($tracking_template)) {
                return $tracking_template;
            }
            
            error_log('ZAIKON FRONTEND: Tracking template not found at: ' . $tracking_template);
            return $template;
        }
        
        if (!$page) {
            return $template;
        }
        
        // Check if user is logged in for POS pages
        if (!is_user_logged_in()) {
            // Determine the redirect URL based on the requested page
            $redirect_url = home_url('/zaikon-pos/');
            if ($page === 'pos') {
                $redirect_url = home_url('/pos/');
            } elseif ($page === 'kds') {
                $redirect_url = home_url('/kitchen/');
            }
            wp_redirect(wp_login_url($redirect_url));
            exit;
        }
        
        // Get current user
        $current_user = wp_get_current_user();
        $roles = (array) $current_user->roles;
        
        // Role-based access control
        $allowed_pages = self::get_allowed_pages($roles);
        
        if (!in_array($page, $allowed_pages)) {
            // User doesn't have permission for this page
            wp_die(__('You do not have permission to access this page.', 'restaurant-pos'), 403);
        }
        
        // Load custom template
        $custom_template = RPOS_PLUGIN_DIR . 'templates/zaikon-pos-template.php';
        
        if (file_exists($custom_template)) {
            return $custom_template;
        }
        
        return $template;
    }
    
    /**
     * Get allowed pages for user roles
     */
    private static function get_allowed_pages($roles) {
        $allowed = array();
        
        // Admin and restaurant_admin can access everything
        if (in_array('administrator', $roles) || in_array('restaurant_admin', $roles)) {
            return array('dashboard', 'pos', 'kds', 'deliveries');
        }
        
        // Cashier can access POS and dashboard
        if (in_array('cashier', $roles)) {
            $allowed = array_merge($allowed, array('pos', 'dashboard'));
        }
        
        // Kitchen staff can access KDS
        if (in_array('kitchen_staff', $roles)) {
            $allowed[] = 'kds';
        }
        
        // Delivery rider can access deliveries
        if (in_array('delivery_rider', $roles)) {
            $allowed[] = 'deliveries';
        }
        
        return $allowed;
    }
    
    /**
     * Enqueue assets for frontend pages
     */
    public static function enqueue_assets() {
        $page = get_query_var('zaikon_pos_page');
        
        if (!$page) {
            return;
        }
        
        // Common assets
        wp_enqueue_style('zaikon-design-system', RPOS_PLUGIN_URL . 'assets/css/zaikon-design-system.css', array(), RPOS_VERSION);
        wp_enqueue_style('zaikon-components', RPOS_PLUGIN_URL . 'assets/css/zaikon-components.css', array('zaikon-design-system'), RPOS_VERSION);
        wp_enqueue_script('jquery');
        
        // Page-specific assets
        switch ($page) {
            case 'pos':
                wp_enqueue_style('zaikon-pos-screen', RPOS_PLUGIN_URL . 'assets/css/zaikon-pos-screen.css', array('zaikon-components'), RPOS_VERSION);
                wp_enqueue_style('rpos-delivery', RPOS_PLUGIN_URL . 'assets/css/delivery.css', array('zaikon-pos-screen'), RPOS_VERSION);
                wp_enqueue_script('rpos-admin', RPOS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RPOS_VERSION, true);
                wp_enqueue_script('rpos-pos-screen', RPOS_PLUGIN_URL . 'assets/js/rpos-pos-screen.js', array('jquery'), RPOS_VERSION, true);
                wp_enqueue_script('rpos-delivery', RPOS_PLUGIN_URL . 'assets/js/delivery.js', array('jquery'), RPOS_VERSION, true);
                wp_enqueue_script('rpos-rider-assignment', RPOS_PLUGIN_URL . 'assets/js/rider-assignment.js', array('jquery'), RPOS_VERSION, true);
                wp_enqueue_script('rpos-session-management', RPOS_PLUGIN_URL . 'assets/js/session-management.js', array('jquery', 'rpos-admin'), RPOS_VERSION, true);
                break;
                
            case 'kds':
                wp_enqueue_style('zaikon-kds-screen', RPOS_PLUGIN_URL . 'assets/css/zaikon-kds-screen.css', array('zaikon-components'), RPOS_VERSION);
                wp_enqueue_script('rpos-kds', RPOS_PLUGIN_URL . 'assets/js/kds.js', array('jquery'), RPOS_VERSION, true);
                break;
                
            case 'deliveries':
                wp_enqueue_style('zaikon-admin', RPOS_PLUGIN_URL . 'assets/css/zaikon-admin.css', array('zaikon-components'), RPOS_VERSION);
                break;
                
            case 'dashboard':
                wp_enqueue_style('zaikon-admin', RPOS_PLUGIN_URL . 'assets/css/zaikon-admin.css', array('zaikon-components'), RPOS_VERSION);
                wp_enqueue_style('zaikon-modern-dashboard', RPOS_PLUGIN_URL . 'assets/css/zaikon-modern-dashboard.css', array('zaikon-admin'), RPOS_VERSION);
                wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);
                break;
        }
        
        // Localize scripts
        wp_localize_script('jquery', 'rposAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rpos-admin-nonce'),
            'restUrl' => rest_url('restaurant-pos/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'currencySymbol' => RPOS_Settings::get('currency_symbol', '$')
        ));
    }
    
    /**
     * Flush rewrite rules (call this on plugin activation)
     */
    public static function flush_rules() {
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }
}
