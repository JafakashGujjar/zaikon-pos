<?php
/**
 * Installation and Activation Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Install {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Create user roles
        RPOS_Roles::create_roles();
        
        // Insert default settings
        self::insert_default_settings();
        
        // Set installed flag
        update_option('rpos_version', RPOS_VERSION);
        update_option('rpos_installed', time());
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = array();
        
        // Categories table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_categories (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Products table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_products (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            sku varchar(100),
            category_id bigint(20) unsigned,
            selling_price decimal(10,2) NOT NULL DEFAULT 0.00,
            image_url varchar(500),
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category_id (category_id),
            KEY sku (sku)
        ) $charset_collate;";
        
        // Inventory table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_inventory (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            quantity int(11) NOT NULL DEFAULT 0,
            cost_price decimal(10,2) DEFAULT 0.00,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id)
        ) $charset_collate;";
        
        // Stock movements table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_stock_movements (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            change_amount int(11) NOT NULL,
            reason varchar(255),
            order_id bigint(20) unsigned,
            user_id bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Orders table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_orders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_number varchar(50) NOT NULL,
            subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
            discount decimal(10,2) DEFAULT 0.00,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            cash_received decimal(10,2) DEFAULT 0.00,
            change_due decimal(10,2) DEFAULT 0.00,
            status varchar(50) NOT NULL DEFAULT 'new',
            cashier_id bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Order items table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_order_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            product_name varchar(255) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
            line_total decimal(10,2) NOT NULL DEFAULT 0.00,
            cost_price decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        
        // Settings table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        // Product recipes table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_product_recipes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            inventory_item_id bigint(20) unsigned NOT NULL,
            quantity_required decimal(10,3) NOT NULL,
            unit varchar(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY inventory_item_id (inventory_item_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table) {
            dbDelta($table);
        }
    }
    
    /**
     * Insert default settings
     */
    private static function insert_default_settings() {
        global $wpdb;
        
        $default_settings = array(
            'restaurant_name' => get_bloginfo('name'),
            'currency_symbol' => '$',
            'low_stock_threshold' => '10',
            'date_format' => 'Y-m-d H:i:s',
            'tax_rate' => '0',
            'enable_tax' => '0'
        );
        
        foreach ($default_settings as $key => $value) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rpos_settings WHERE setting_key = %s",
                $key
            ));
            
            if (!$existing) {
                $wpdb->insert(
                    $wpdb->prefix . 'rpos_settings',
                    array(
                        'setting_key' => $key,
                        'setting_value' => $value
                    ),
                    array('%s', '%s')
                );
            }
        }
    }
}
