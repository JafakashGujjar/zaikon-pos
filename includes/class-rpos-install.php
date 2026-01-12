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
        
        // Check if new columns exist in ingredients table
        $table_name = $wpdb->prefix . 'rpos_ingredients';
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'purchasing_date'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `purchasing_date` date DEFAULT NULL AFTER `cost_per_unit`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'expiry_date'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `expiry_date` date DEFAULT NULL AFTER `purchasing_date`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_name'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `supplier_name` varchar(255) DEFAULT NULL AFTER `expiry_date`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_rating'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `supplier_rating` tinyint(1) DEFAULT NULL AFTER `supplier_name`");
        }
        
        // Add new supplier detail fields
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_phone'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `supplier_phone` varchar(50) DEFAULT NULL AFTER `supplier_rating`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_location'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `supplier_location` text DEFAULT NULL AFTER `supplier_phone`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'reorder_level'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `reorder_level` decimal(10,3) DEFAULT 0.000 AFTER `supplier_location`");
        }
        
        // Check if new columns exist in orders table for KDS tracking
        $table_name = $wpdb->prefix . 'rpos_orders';
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'target_prep_time'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `target_prep_time` int DEFAULT 10 AFTER `ingredients_deducted`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'actual_prep_time'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `actual_prep_time` int DEFAULT NULL AFTER `target_prep_time`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'is_late'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `is_late` tinyint(1) DEFAULT 0 AFTER `actual_prep_time`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'late_reason'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `late_reason` text DEFAULT NULL AFTER `is_late`");
        }
        
        // Check if delay_reason column exists in kitchen_activity table
        $table_name = $wpdb->prefix . 'rpos_kitchen_activity';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'delay_reason'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `delay_reason` text DEFAULT NULL AFTER `new_status`");
        }
        
        // Check if delivery columns exist in orders table
        $table_name = $wpdb->prefix . 'rpos_orders';
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'area_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `area_id` bigint(20) unsigned DEFAULT NULL AFTER `cashier_id`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'customer_name'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `customer_name` varchar(255) DEFAULT NULL AFTER `area_id`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'customer_phone'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `customer_phone` varchar(50) DEFAULT NULL AFTER `customer_name`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'delivery_charge'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `delivery_charge` decimal(10,2) DEFAULT 0.00 AFTER `customer_phone`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'is_delivery'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `is_delivery` tinyint(1) DEFAULT 0 AFTER `delivery_charge`");
        }
        
        // New delivery rider fields
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'delivery_status'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `delivery_status` varchar(50) DEFAULT NULL AFTER `is_delivery`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'delivery_km'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `delivery_km` decimal(10,2) DEFAULT 0 AFTER `delivery_status`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'rider_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `rider_id` bigint(20) unsigned DEFAULT NULL AFTER `delivery_km`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'kitchen_late'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `kitchen_late` tinyint(1) DEFAULT 0 AFTER `rider_id`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'ready_at'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `ready_at` datetime DEFAULT NULL AFTER `kitchen_late`");
        }
        
        // Add batch_id column to ingredient_movements table
        $table_name = $wpdb->prefix . 'rpos_ingredient_movements';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'batch_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `batch_id` bigint(20) unsigned DEFAULT NULL AFTER `ingredient_id`");
            $wpdb->query("ALTER TABLE `{$table_name}` ADD KEY `idx_batch` (`batch_id`)");
        }
        
        // Add batch_id column to ingredient_waste table
        $table_name = $wpdb->prefix . 'rpos_ingredient_waste';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'batch_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `batch_id` bigint(20) unsigned DEFAULT NULL AFTER `ingredient_id`");
            $wpdb->query("ALTER TABLE `{$table_name}` ADD KEY `idx_batch` (`batch_id`)");
        }
        
        // Add cost_per_unit column to ingredient_waste table
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'cost_per_unit'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `cost_per_unit` decimal(10,2) DEFAULT 0.00 AFTER `quantity`");
        }
        
        // Add new fields to ingredients table for batch system
        $table_name = $wpdb->prefix . 'rpos_ingredients';
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'default_supplier_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `default_supplier_id` bigint(20) unsigned DEFAULT NULL AFTER `reorder_level`");
            $wpdb->query("ALTER TABLE `{$table_name}` ADD KEY `idx_default_supplier` (`default_supplier_id`)");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'lead_time_days'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `lead_time_days` int DEFAULT 0 AFTER `default_supplier_id`");
        }
        
        // Run batch system migration if not already done
        $migration_done = get_option('rpos_batch_migration_done', false);
        if (!$migration_done) {
            self::migrate_to_batch_system();
            update_option('rpos_batch_migration_done', true);
        }
    }
    
    /**
     * Migrate existing inventory to batch-based system
     */
    private static function migrate_to_batch_system() {
        global $wpdb;
        
        // Set default inventory consumption strategy to FEFO
        $wpdb->replace(
            $wpdb->prefix . 'rpos_inventory_settings',
            array(
                'setting_key' => 'consumption_strategy',
                'setting_value' => 'FEFO',
                'setting_type' => 'select'
            ),
            array('%s', '%s', '%s')
        );
        
        // Set default expiry warning days
        $wpdb->replace(
            $wpdb->prefix . 'rpos_inventory_settings',
            array(
                'setting_key' => 'expiry_warning_days',
                'setting_value' => '7',
                'setting_type' => 'number'
            ),
            array('%s', '%s', '%s')
        );
        
        // Migrate existing suppliers from ingredients table to suppliers table
        // Check if required columns exist first
        $columns_exist = $wpdb->get_results(
            "SHOW COLUMNS FROM {$wpdb->prefix}rpos_ingredients WHERE Field IN ('supplier_name', 'supplier_phone', 'supplier_location', 'supplier_rating')"
        );
        
        $has_supplier_fields = count($columns_exist) >= 1; // At least supplier_name should exist
        
        if ($has_supplier_fields) {
            $existing_suppliers = $wpdb->get_results(
                "SELECT DISTINCT supplier_name, supplier_phone, supplier_location, supplier_rating
                 FROM {$wpdb->prefix}rpos_ingredients
                 WHERE supplier_name IS NOT NULL AND supplier_name != ''
                 ORDER BY supplier_name"
            );
            
            $supplier_map = array();
            foreach ($existing_suppliers as $supplier) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'rpos_suppliers',
                    array(
                        'supplier_name' => $supplier->supplier_name,
                        'phone' => isset($supplier->supplier_phone) ? $supplier->supplier_phone : null,
                        'address' => isset($supplier->supplier_location) ? $supplier->supplier_location : null,
                        'rating' => isset($supplier->supplier_rating) ? $supplier->supplier_rating : null,
                        'is_active' => 1,
                        'notes' => 'Migrated from legacy ingredient data'
                    ),
                    array('%s', '%s', '%s', '%d', '%d', '%s')
                );
                
                if ($result) {
                    $supplier_map[$supplier->supplier_name] = $wpdb->insert_id;
                }
            }
        } else {
            $supplier_map = array();
        }
        
        // Create legacy batches for existing ingredients with stock
        $ingredients = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_ingredients 
             WHERE current_stock_quantity > 0"
        );
        
        foreach ($ingredients as $ingredient) {
            $batch_number = 'LEGACY-' . $ingredient->id . '-' . date('YmdHis');
            $supplier_id = null;
            
            // Map to new supplier if exists
            if (!empty($ingredient->supplier_name) && isset($supplier_map[$ingredient->supplier_name])) {
                $supplier_id = $supplier_map[$ingredient->supplier_name];
                
                // Update ingredient with default supplier
                $wpdb->update(
                    $wpdb->prefix . 'rpos_ingredients',
                    array('default_supplier_id' => $supplier_id),
                    array('id' => $ingredient->id),
                    array('%d'),
                    array('%d')
                );
            }
            
            // Create legacy batch
            $wpdb->insert(
                $wpdb->prefix . 'rpos_ingredient_batches',
                array(
                    'batch_number' => $batch_number,
                    'ingredient_id' => $ingredient->id,
                    'supplier_id' => $supplier_id,
                    'purchase_date' => !empty($ingredient->purchasing_date) ? $ingredient->purchasing_date : date('Y-m-d'),
                    'expiry_date' => $ingredient->expiry_date,
                    'cost_per_unit' => $ingredient->cost_per_unit,
                    'quantity_purchased' => $ingredient->current_stock_quantity,
                    'quantity_remaining' => $ingredient->current_stock_quantity,
                    'notes' => 'Legacy batch created during migration',
                    'status' => 'active',
                    'created_by' => get_current_user_id()
                ),
                array('%s', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%d')
            );
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
            target_prep_time int DEFAULT 10,
            actual_prep_time int DEFAULT NULL,
            is_late tinyint(1) DEFAULT 0,
            late_reason text DEFAULT NULL,
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
            delay_reason text DEFAULT NULL,
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
            purchasing_date date DEFAULT NULL,
            expiry_date date DEFAULT NULL,
            supplier_name varchar(255) DEFAULT NULL,
            supplier_rating tinyint(1) DEFAULT NULL,
            supplier_phone varchar(50) DEFAULT NULL,
            supplier_location text DEFAULT NULL,
            reorder_level decimal(10,3) DEFAULT 0.000,
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
        
        // Ingredient waste table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_ingredient_waste (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ingredient_id bigint(20) unsigned NOT NULL,
            quantity decimal(10,3) NOT NULL,
            reason varchar(50) NOT NULL,
            notes text,
            user_id bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ingredient_id (ingredient_id),
            KEY reason (reason),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Gas Cylinder Types table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_gas_cylinder_types (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Gas Cylinder Product Mapping table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_gas_cylinder_product_map (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cylinder_type_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cylinder_type_id (cylinder_type_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        
        // Gas Cylinders table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_gas_cylinders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cylinder_type_id bigint(20) unsigned NOT NULL,
            purchase_date date DEFAULT NULL,
            cost decimal(10,2) DEFAULT 0.00,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            notes text,
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cylinder_type_id (cylinder_type_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Delivery Areas table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_delivery_areas (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            distance_value decimal(10,2) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Delivery Charges table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_delivery_charges (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            distance_from decimal(10,2) NOT NULL,
            distance_to decimal(10,2) NOT NULL,
            charge_amount decimal(10,2) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Delivery Settings table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_delivery_settings (
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            setting_type varchar(50) DEFAULT 'text',
            PRIMARY KEY (setting_key)
        ) $charset_collate;";
        
        // Delivery Logs table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_delivery_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            rider_id bigint(20) unsigned DEFAULT NULL,
            rider_name varchar(255) DEFAULT NULL,
            bike_id varchar(100) DEFAULT NULL,
            fuel_amount decimal(10,2) DEFAULT 0.00,
            fuel_unit varchar(20) DEFAULT 'liters',
            km_start decimal(10,2) DEFAULT 0.00,
            km_end decimal(10,2) DEFAULT 0.00,
            total_km decimal(10,2) DEFAULT 0.00,
            deliveries_count int DEFAULT 0,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date (date),
            KEY rider_id (rider_id),
            KEY bike_id (bike_id)
        ) $charset_collate;";
        
        // Notifications table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_notifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_read (user_id, is_read),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // Suppliers table - Multi-supplier support
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_suppliers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            supplier_name varchar(255) NOT NULL,
            phone varchar(50),
            address text,
            rating tinyint(1) DEFAULT NULL,
            contact_person varchar(255),
            gst_tax_id varchar(100),
            is_active tinyint(1) DEFAULT 1,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_name (supplier_name),
            KEY idx_active (is_active)
        ) $charset_collate;";
        
        // Ingredient Batches table - Batch/Lot tracking with FIFO/FEFO
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_ingredient_batches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_number varchar(100) NOT NULL,
            ingredient_id bigint(20) unsigned NOT NULL,
            supplier_id bigint(20) unsigned,
            purchase_date date NOT NULL,
            manufacturing_date date,
            expiry_date date,
            cost_per_unit decimal(10,2) NOT NULL DEFAULT 0.00,
            quantity_purchased decimal(10,3) NOT NULL,
            quantity_remaining decimal(10,3) NOT NULL,
            purchase_invoice_url varchar(500),
            notes text,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_batch_number (batch_number),
            KEY idx_ingredient (ingredient_id),
            KEY idx_supplier (supplier_id),
            KEY idx_purchase_date (purchase_date),
            KEY idx_expiry_date (expiry_date),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // Inventory Settings table - FIFO/FEFO and other settings
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_inventory_settings (
            setting_key varchar(100) NOT NULL,
            setting_value text,
            setting_type varchar(50) DEFAULT 'text',
            PRIMARY KEY (setting_key)
        ) $charset_collate;";
        
        // ========== ZAIKON POS DELIVERY & REPORTING TABLES ==========
        
        // Zaikon Orders table (standardized master orders table)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_orders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_number varchar(50) NOT NULL,
            order_type enum('dine_in','takeaway','delivery') NOT NULL DEFAULT 'takeaway',
            items_subtotal_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            delivery_charges_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            discounts_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            taxes_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            grand_total_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            payment_status enum('unpaid','paid','refunded','void') DEFAULT 'unpaid',
            cashier_id bigint(20) unsigned,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            KEY order_type_idx (order_type),
            KEY created_at_idx (created_at),
            KEY cashier_id (cashier_id)
        ) $charset_collate;";
        
        // Zaikon Order Items table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_order_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            product_name varchar(191) NOT NULL,
            qty int(11) NOT NULL,
            unit_price_rs decimal(10,2) NOT NULL,
            line_total_rs decimal(10,2) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_idx (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        
        // Zaikon Delivery Locations (Villages/Areas)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_delivery_locations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            distance_km decimal(6,2) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Zaikon Delivery Charge Slabs (km-based customer charges)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_delivery_charge_slabs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            min_km decimal(6,2) NOT NULL,
            max_km decimal(6,2) NOT NULL,
            charge_rs decimal(10,2) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY range_idx (is_active, min_km, max_km)
        ) $charset_collate;";
        
        // Zaikon Free Delivery Rules
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_free_delivery_rules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            max_km decimal(6,2) NOT NULL,
            min_order_amount_rs decimal(10,2) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY active_idx (is_active)
        ) $charset_collate;";
        
        // Zaikon Riders table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_riders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            phone varchar(50),
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_idx (status)
        ) $charset_collate;";
        
        // Zaikon Deliveries table (core bridge)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_deliveries (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            customer_name varchar(191) NOT NULL,
            customer_phone varchar(50) NOT NULL,
            location_id bigint(20) unsigned DEFAULT NULL,
            location_name varchar(191) NOT NULL,
            distance_km decimal(6,2) NOT NULL,
            delivery_charges_rs decimal(10,2) NOT NULL,
            is_free_delivery tinyint(1) DEFAULT 0,
            special_instruction varchar(255) DEFAULT NULL,
            assigned_rider_id bigint(20) unsigned DEFAULT NULL,
            delivery_status enum('pending','on_route','delivered','failed') DEFAULT 'pending',
            delivered_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_idx (order_id),
            KEY phone_idx (customer_phone),
            KEY rider_idx (assigned_rider_id),
            KEY location_idx (location_id)
        ) $charset_collate;";
        
        // Zaikon Rider Payouts per Delivery
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_rider_payouts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            delivery_id bigint(20) unsigned NOT NULL,
            rider_id bigint(20) unsigned NOT NULL,
            rider_pay_rs decimal(10,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY delivery_idx (delivery_id),
            KEY rider_idx (rider_id)
        ) $charset_collate;";
        
        // Zaikon Rider Fuel Logs
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_rider_fuel_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rider_id bigint(20) unsigned NOT NULL,
            amount_rs decimal(10,2) NOT NULL,
            liters decimal(10,2) DEFAULT NULL,
            date date NOT NULL,
            notes varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rider_date_idx (rider_id, date)
        ) $charset_collate;";
        
        // Zaikon System Events (audit log)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_system_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            action varchar(50) NOT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entity_idx (entity_type, entity_id)
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
            'enable_tax' => '0',
            'restaurant_phone' => '',
            'restaurant_address' => '',
            'receipt_footer_message' => 'Thank you for your order!'
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
