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
        
        // Run upgrades if needed
        self::maybe_upgrade();
        
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
     * Check and run upgrades if needed
     */
    private static function maybe_upgrade() {
        $current_version = get_option('rpos_version', '0.0.0');
        
        global $wpdb;
        
        // Check if unit column exists in inventory table
        $table_name = $wpdb->prefix . 'rpos_inventory';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'unit'");
        
        if (empty($column_exists)) {
            // Add unit column
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `unit` varchar(20) DEFAULT 'pcs' AFTER `quantity`");
        }
        
        // Check if expiry_date column exists in stock_movements table
        $table_name = $wpdb->prefix . 'rpos_stock_movements';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'expiry_date'");
        
        if (empty($column_exists)) {
            // Add expiry_date column
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `expiry_date` date DEFAULT NULL AFTER `user_id`");
        }
        
        // Check if order_type column exists in orders table
        $table_name = $wpdb->prefix . 'rpos_orders';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'order_type'");
        
        if (empty($column_exists)) {
            // Add order_type column
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `order_type` varchar(20) DEFAULT 'dine-in' AFTER `status`");
        }
        
        // Check if special_instructions column exists in orders table
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'special_instructions'");
        
        if (empty($column_exists)) {
            // Add special_instructions column
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `special_instructions` text AFTER `order_type`");
        }
        
        // Check if ingredients_deducted column exists in orders table
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'ingredients_deducted'");
        
        if (empty($column_exists)) {
            // Add ingredients_deducted column
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `ingredients_deducted` tinyint(1) DEFAULT 0 AFTER `cashier_id`");
        }
        
        // Check if ingredient_id column exists in product_recipes table
        $table_name = $wpdb->prefix . 'rpos_product_recipes';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'ingredient_id'");
        
        if (empty($column_exists)) {
            // Add ingredient_id column (will be used instead of inventory_item_id going forward)
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `ingredient_id` bigint(20) unsigned DEFAULT NULL AFTER `inventory_item_id`");
            $wpdb->query("ALTER TABLE `{$table_name}` ADD KEY `ingredient_id` (`ingredient_id`)");
        }
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
            quantity decimal(10,3) NOT NULL DEFAULT 0.000,
            unit varchar(20) DEFAULT 'pcs',
            cost_price decimal(10,2) DEFAULT 0.00,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_id (product_id)
        ) $charset_collate;";
        
        // Stock movements table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_stock_movements (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            change_amount decimal(10,3) NOT NULL,
            reason varchar(255),
            order_id bigint(20) unsigned,
            user_id bigint(20) unsigned,
            expiry_date date DEFAULT NULL,
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
            order_type varchar(20) DEFAULT 'dine-in',
            special_instructions text,
            cashier_id bigint(20) unsigned,
            ingredients_deducted tinyint(1) DEFAULT 0,
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
        // Note: Foreign key constraints are intentionally not used to maintain compatibility
        // with various MySQL storage engines and follow WordPress best practices
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
        
        // Kitchen activity table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_kitchen_activity (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            old_status varchar(50),
            new_status varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Ingredients table - dedicated table for ingredient management
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_ingredients (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            unit varchar(20) NOT NULL DEFAULT 'pcs',
            current_stock_quantity decimal(10,3) NOT NULL DEFAULT 0.000,
            cost_per_unit decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name)
        ) $charset_collate;";
        
        // Ingredient stock movements table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_ingredient_movements (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ingredient_id bigint(20) unsigned NOT NULL,
            change_amount decimal(10,3) NOT NULL,
            movement_type varchar(50) NOT NULL,
            reference_id bigint(20) unsigned,
            notes text,
            user_id bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ingredient_id (ingredient_id),
            KEY movement_type (movement_type),
            KEY created_at (created_at)
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
