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
        
        // Flush rewrite rules for frontend URLs
        require_once RPOS_PLUGIN_DIR . 'includes/class-zaikon-frontend.php';
        Zaikon_Frontend::flush_rules();
    }
    
    /**
     * Run database migrations (public wrapper for maybe_upgrade)
     */
    public static function run_migrations() {
        self::maybe_upgrade();
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
        
        // Check if payment_type column exists in rpos_orders table
        $table_name = $wpdb->prefix . 'rpos_orders';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'payment_type'");
        if (empty($column_exists)) {
            $result = $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `payment_type` enum('cash','cod','online') DEFAULT 'cash' AFTER `status`");
            if ($result === false) {
                error_log('RPOS Migration: Failed to add payment_type column to rpos_orders table: ' . $wpdb->last_error);
            }
        }
        
        // Check if payment_status column exists in rpos_orders table
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'payment_status'");
        if (empty($column_exists)) {
            $result = $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `payment_status` enum('unpaid','paid','refunded','void') DEFAULT 'paid' AFTER `payment_type`");
            if ($result === false) {
                error_log('RPOS Migration: Failed to add payment_status column to rpos_orders table: ' . $wpdb->last_error);
            }
        }
        
        // Check if new columns exist in orders table for KDS tracking
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'target_prep_time'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `target_prep_time` int(11) DEFAULT NULL AFTER `ingredients_deducted`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'prep_started_at'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `prep_started_at` datetime DEFAULT NULL AFTER `target_prep_time`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'prep_completed_at'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `prep_completed_at` datetime DEFAULT NULL AFTER `prep_started_at`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'actual_prep_time'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `actual_prep_time` int(11) DEFAULT NULL AFTER `prep_completed_at`");
        }
        
        // Check if payment_type and order_status columns exist in zaikon_orders table
        $table_name = $wpdb->prefix . 'zaikon_orders';
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'payment_type'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `payment_type` enum('cash','cod','online') DEFAULT 'cash' AFTER `payment_status`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'order_status'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `order_status` enum('active','cancelled','replacement','completed') DEFAULT 'active' AFTER `payment_type`");
        }
        
        // Rename columns in zaikon_orders to match expected naming
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'subtotal_rs'");
        if (empty($column_exists)) {
            // Check if items_subtotal_rs exists first
            $old_column = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'items_subtotal_rs'");
            if (!empty($old_column)) {
                $wpdb->query("ALTER TABLE `{$table_name}` CHANGE COLUMN `items_subtotal_rs` `subtotal_rs` decimal(10,2) NOT NULL DEFAULT 0.00");
            }
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'delivery_charge_rs'");
        if (empty($column_exists)) {
            $old_column = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'delivery_charges_rs'");
            if (!empty($old_column)) {
                $wpdb->query("ALTER TABLE `{$table_name}` CHANGE COLUMN `delivery_charges_rs` `delivery_charge_rs` decimal(10,2) NOT NULL DEFAULT 0.00");
            }
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'discount_rs'");
        if (empty($column_exists)) {
            $old_column = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'discounts_rs'");
            if (!empty($old_column)) {
                $wpdb->query("ALTER TABLE `{$table_name}` CHANGE COLUMN `discounts_rs` `discount_rs` decimal(10,2) NOT NULL DEFAULT 0.00");
            }
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
        
        // Add new columns to rpos_gas_cylinders table for enterprise tracking
        $table_name = $wpdb->prefix . 'rpos_gas_cylinders';
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'zone_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `zone_id` bigint(20) unsigned DEFAULT NULL AFTER `cylinder_type_id`");
            $wpdb->query("ALTER TABLE `{$table_name}` ADD KEY `zone_id` (`zone_id`)");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'orders_served'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `orders_served` int DEFAULT 0 AFTER `notes`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'remaining_percentage'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `remaining_percentage` decimal(5,2) DEFAULT 100.00 AFTER `orders_served`");
        }
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'vendor'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `vendor` varchar(255) DEFAULT NULL AFTER `remaining_percentage`");
        }
        
        // Run batch system migration if not already done
        $migration_done = get_option('rpos_batch_migration_done', false);
        if (!$migration_done) {
            self::migrate_to_batch_system();
            update_option('rpos_batch_migration_done', true);
        }
        
        // Run rider system migration if not already done
        $rider_migration_done = get_option('rpos_rider_system_migration_done', false);
        if (!$rider_migration_done) {
            self::migrate_rider_system();
            update_option('rpos_rider_system_migration_done', true);
        }
        
        // Run cashier session system migration if not already done
        $cashier_session_migration_done = get_option('rpos_cashier_session_migration_done', false);
        if (!$cashier_session_migration_done) {
            self::migrate_cashier_session_system();
            update_option('rpos_cashier_session_migration_done', true);
        }
        
        // Run kitchen staff capability upgrade if not already done
        $kitchen_staff_upgrade_done = get_option('rpos_kitchen_staff_capability_upgrade_done', false);
        if (!$kitchen_staff_upgrade_done) {
            self::upgrade_kitchen_staff_capabilities();
            update_option('rpos_kitchen_staff_capability_upgrade_done', true);
        }
        
        // Run delivery tracking system migration if not already done
        $delivery_tracking_migration_done = get_option('rpos_delivery_tracking_migration_done', false);
        if (!$delivery_tracking_migration_done) {
            self::migrate_delivery_tracking_system();
            update_option('rpos_delivery_tracking_migration_done', true);
        }
        
        // Add image_url and bg_color columns to categories table
        $categories_image_migration_done = get_option('rpos_categories_image_migration_done', false);
        if (!$categories_image_migration_done) {
            $table_name = $wpdb->prefix . 'rpos_categories';
            
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'image_url'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `image_url` varchar(500) DEFAULT NULL AFTER `description`");
            }
            
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'bg_color'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `bg_color` varchar(20) DEFAULT NULL AFTER `image_url`");
            }
            
            update_option('rpos_categories_image_migration_done', true);
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
            $batch_number = 'LEGACY-' . $ingredient->id . '-' . RPOS_Timezone::now()->format('YmdHis');
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
                    'purchase_date' => !empty($ingredient->purchasing_date) ? $ingredient->purchasing_date : RPOS_Timezone::now()->format('Y-m-d'),
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
            image_url varchar(500),
            bg_color varchar(20),
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
            zone_id bigint(20) unsigned DEFAULT NULL,
            purchase_date date DEFAULT NULL,
            cost decimal(10,2) DEFAULT 0.00,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            notes text,
            orders_served int DEFAULT 0,
            remaining_percentage decimal(5,2) DEFAULT 100.00,
            vendor varchar(255) DEFAULT NULL,
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cylinder_type_id (cylinder_type_id),
            KEY zone_id (zone_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Cylinder Zones table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_cylinder_zones (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Cylinder Lifecycle table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_cylinder_lifecycle (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cylinder_id bigint(20) unsigned NOT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            orders_served int DEFAULT 0,
            total_days int DEFAULT 0,
            avg_orders_per_day decimal(10,2) DEFAULT 0.00,
            refill_cost decimal(10,2) DEFAULT 0.00,
            cost_per_order decimal(10,4) DEFAULT 0.0000,
            vendor varchar(255) DEFAULT NULL,
            notes text,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cylinder_id (cylinder_id),
            KEY status (status),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset_collate;";
        
        // Cylinder Consumption table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_cylinder_consumption (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cylinder_id bigint(20) unsigned NOT NULL,
            lifecycle_id bigint(20) unsigned DEFAULT NULL,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            quantity int NOT NULL,
            consumption_units decimal(10,4) DEFAULT 0.0000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cylinder_id (cylinder_id),
            KEY lifecycle_id (lifecycle_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Cylinder Refill table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_cylinder_refill (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cylinder_id bigint(20) unsigned NOT NULL,
            lifecycle_id bigint(20) unsigned DEFAULT NULL,
            refill_date date NOT NULL,
            vendor varchar(255) DEFAULT NULL,
            cost decimal(10,2) DEFAULT 0.00,
            quantity decimal(10,2) DEFAULT 1.00,
            notes text,
            created_by bigint(20) unsigned,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cylinder_id (cylinder_id),
            KEY lifecycle_id (lifecycle_id),
            KEY refill_date (refill_date)
        ) $charset_collate;";
        
        // Cylinder Forecast Cache table (optional)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_cylinder_forecast_cache (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cylinder_id bigint(20) unsigned NOT NULL,
            burn_rate_orders_per_day decimal(10,2) DEFAULT 0.00,
            burn_rate_units_per_day decimal(10,4) DEFAULT 0.0000,
            projected_depletion_date date DEFAULT NULL,
            remaining_days decimal(10,2) DEFAULT 0.00,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cylinder_id (cylinder_id),
            KEY last_updated (last_updated)
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
            tracking_token varchar(100) UNIQUE DEFAULT NULL,
            order_type enum('dine_in','takeaway','delivery') NOT NULL DEFAULT 'takeaway',
            items_subtotal_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            delivery_charges_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            discounts_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            taxes_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            grand_total_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            payment_status enum('unpaid','paid','refunded','void') DEFAULT 'unpaid',
            payment_type enum('cash','card','online','cod','credit') DEFAULT 'cash',
            order_status enum('pending','confirmed','cooking','ready','dispatched','delivered','active','completed','cancelled','replacement') DEFAULT 'pending',
            cooking_eta_minutes int(11) DEFAULT 20,
            delivery_eta_minutes int(11) DEFAULT 15,
            confirmed_at datetime DEFAULT NULL,
            cooking_started_at datetime DEFAULT NULL,
            ready_at datetime DEFAULT NULL,
            dispatched_at datetime DEFAULT NULL,
            special_instructions text DEFAULT NULL,
            cashier_id bigint(20) unsigned,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            UNIQUE KEY tracking_token_idx (tracking_token),
            KEY order_type_idx (order_type),
            KEY order_status_idx (order_status),
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
            payout_type enum('per_delivery','per_km','hybrid') DEFAULT 'per_km',
            per_delivery_rate decimal(10,2) DEFAULT 0.00,
            per_km_rate decimal(10,2) DEFAULT 10.00,
            base_rate decimal(10,2) DEFAULT 20.00,
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
            delivery_instructions varchar(255) DEFAULT NULL,
            assigned_rider_id bigint(20) unsigned DEFAULT NULL,
            delivery_status enum('pending','assigned','picked','on_route','delivered','failed') DEFAULT 'pending',
            rider_payout_amount decimal(10,2) DEFAULT NULL,
            rider_payout_slab varchar(50) DEFAULT NULL,
            payout_type enum('per_delivery','per_km','hybrid') DEFAULT NULL,
            fuel_multiplier decimal(5,2) DEFAULT 1.00,
            payout_per_km_rate decimal(10,2) DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_idx (order_id),
            KEY phone_idx (customer_phone),
            KEY rider_idx (assigned_rider_id),
            KEY location_idx (location_id)
        ) $charset_collate;";
        
        // Zaikon Rider Orders table (rider-order lifecycle tracking)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_rider_orders (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            rider_id bigint(20) unsigned NOT NULL,
            delivery_id bigint(20) unsigned DEFAULT NULL,
            status enum('pending','assigned','picked','delivered','failed') DEFAULT 'pending',
            assigned_at datetime DEFAULT NULL,
            picked_at datetime DEFAULT NULL,
            delivered_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            failure_reason varchar(255) DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_idx (order_id),
            KEY rider_idx (rider_id),
            KEY delivery_idx (delivery_id),
            KEY status_idx (status)
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
        
        // Zaikon Cashier Sessions (cash drawer management)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_cashier_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cashier_id bigint(20) unsigned NOT NULL,
            opening_cash_rs decimal(10,2) NOT NULL DEFAULT 0.00,
            closing_cash_rs decimal(10,2) DEFAULT NULL,
            expected_cash_rs decimal(10,2) DEFAULT NULL,
            cash_difference_rs decimal(10,2) DEFAULT NULL,
            total_cash_sales_rs decimal(10,2) DEFAULT 0.00,
            total_cod_collected_rs decimal(10,2) DEFAULT 0.00,
            total_expenses_rs decimal(10,2) DEFAULT 0.00,
            session_start datetime NOT NULL,
            session_end datetime DEFAULT NULL,
            status enum('open','closed') DEFAULT 'open',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cashier_idx (cashier_id),
            KEY status_idx (status),
            KEY session_start_idx (session_start)
        ) $charset_collate;";
        
        // Zaikon Expenses (cashier session expenses tracking)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zaikon_expenses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            cashier_id bigint(20) unsigned NOT NULL,
            amount_rs decimal(10,2) NOT NULL,
            category varchar(100) NOT NULL,
            description text,
            rider_id bigint(20) unsigned DEFAULT NULL,
            expense_date datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_idx (session_id),
            KEY cashier_idx (cashier_id),
            KEY rider_idx (rider_id),
            KEY expense_date_idx (expense_date)
        ) $charset_collate;";
        
        // ========== FRYER OIL TRACKING SYSTEM ==========
        
        // Fryers table - Multi-fryer support
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_fryers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Fryer Oil Batches table - Oil batch registration
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_fryer_oil_batches (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_name varchar(255) NOT NULL,
            fryer_id bigint(20) unsigned DEFAULT NULL,
            oil_added_at datetime NOT NULL,
            oil_capacity decimal(10,3) DEFAULT NULL,
            target_usage_units decimal(10,3) NOT NULL DEFAULT 120.000,
            current_usage_units decimal(10,3) NOT NULL DEFAULT 0.000,
            time_threshold_hours int DEFAULT 24,
            status enum('active','closed') DEFAULT 'active',
            closed_at datetime DEFAULT NULL,
            closed_by bigint(20) unsigned DEFAULT NULL,
            notes text,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY fryer_id (fryer_id),
            KEY status (status),
            KEY oil_added_at (oil_added_at)
        ) $charset_collate;";
        
        // Fryer Product Map table - Product oil consumption mapping
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_fryer_product_map (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            oil_units decimal(10,3) NOT NULL DEFAULT 1.000,
            fryer_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_fryer (product_id, fryer_id),
            KEY product_id (product_id),
            KEY fryer_id (fryer_id)
        ) $charset_collate;";
        
        // Fryer Oil Usage table - Usage log (automatic tracking)
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_fryer_oil_usage (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned DEFAULT NULL,
            product_id bigint(20) unsigned NOT NULL,
            product_name varchar(255) NOT NULL,
            quantity int NOT NULL,
            units_consumed decimal(10,3) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY batch_id (batch_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Fryer Oil Settings table - Module settings
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rpos_fryer_oil_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
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
            'currency_symbol' => 'Rs',
            'low_stock_threshold' => '10',
            'date_format' => 'Y-m-d H:i:s',
            'pos_timezone' => 'Asia/Karachi',
            'tax_rate' => '0',
            'enable_tax' => '0',
            'restaurant_phone' => '',
            'restaurant_address' => '',
            'receipt_footer_message' => 'Thank you for your order!',
            // Obfuscated developer credit - base64 encoded
            'dev_credit' => base64_encode('Muhammad Jafakash Nawaz')
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
    
    /**
     * Migrate rider system to new flexible payout model
     */
    public static function migrate_rider_system() {
        global $wpdb;
        
        // Check if payout columns exist in zaikon_riders table
        $table_name = $wpdb->prefix . 'zaikon_riders';
        
        // Verify table exists before altering
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return; // Table doesn't exist, skip migration
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'payout_type'
        ));
        
        if (empty($column_exists)) {
            // Add payout model fields - using esc_sql for table name safety
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `payout_type` enum('per_delivery','per_km','hybrid') DEFAULT 'per_km' AFTER `status`");
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `per_delivery_rate` decimal(10,2) DEFAULT 0.00 AFTER `payout_type`");
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `per_km_rate` decimal(10,2) DEFAULT 10.00 AFTER `per_delivery_rate`");
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `base_rate` decimal(10,2) DEFAULT 20.00 AFTER `per_km_rate`");
        }
        
        // Check if new delivery statuses exist in zaikon_deliveries
        $table_name = $wpdb->prefix . 'zaikon_deliveries';
        
        // Verify table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return; // Table doesn't exist, skip migration
        }
        
        $column_info = $wpdb->get_row($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'delivery_status'
        ));
        
        if ($column_info && strpos($column_info->Type, 'assigned') === false) {
            // Update enum to include new statuses
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` MODIFY `delivery_status` enum('pending','assigned','picked','on_route','delivered','failed') DEFAULT 'pending'");
        }
        
        // Add delivery_instructions column to separate delivery vs kitchen instructions
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'delivery_instructions'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `delivery_instructions` varchar(255) DEFAULT NULL AFTER `special_instruction`");
        }
        
        // Add rider payout fields to zaikon_deliveries table
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'rider_payout_amount'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `rider_payout_amount` decimal(10,2) DEFAULT NULL AFTER `delivery_status`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'rider_payout_slab'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `rider_payout_slab` varchar(50) DEFAULT NULL AFTER `rider_payout_amount`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'payout_type'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `payout_type` enum('per_delivery','per_km','hybrid') DEFAULT NULL AFTER `rider_payout_slab`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'fuel_multiplier'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `fuel_multiplier` decimal(5,2) DEFAULT 1.00 AFTER `payout_type`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'payout_per_km_rate'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `payout_per_km_rate` decimal(10,2) DEFAULT NULL AFTER `fuel_multiplier`");
        }
        
        // Create rider_orders records for existing deliveries with assigned riders
        $existing_deliveries = $wpdb->get_results(
            "SELECT d.*, o.order_number 
             FROM {$wpdb->prefix}zaikon_deliveries d
             LEFT JOIN {$wpdb->prefix}zaikon_orders o ON d.order_id = o.id
             WHERE d.assigned_rider_id IS NOT NULL"
        );
        
        foreach ($existing_deliveries as $delivery) {
            // Check if rider_order already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}zaikon_rider_orders 
                 WHERE order_id = %d AND rider_id = %d",
                $delivery->order_id,
                $delivery->assigned_rider_id
            ));
            
            if (!$exists) {
                // Determine status based on delivery status
                $status = 'assigned';
                $assigned_at = $delivery->created_at;
                $delivered_at = null;
                
                if ($delivery->delivery_status === 'delivered') {
                    $status = 'delivered';
                    $delivered_at = $delivery->delivered_at;
                } elseif ($delivery->delivery_status === 'on_route') {
                    $status = 'picked';
                } elseif ($delivery->delivery_status === 'failed') {
                    $status = 'failed';
                }
                
                // Create rider_order record
                $wpdb->insert(
                    $wpdb->prefix . 'zaikon_rider_orders',
                    array(
                        'order_id' => $delivery->order_id,
                        'rider_id' => $delivery->assigned_rider_id,
                        'delivery_id' => $delivery->id,
                        'status' => $status,
                        'assigned_at' => $assigned_at,
                        'delivered_at' => $delivered_at,
                        'notes' => 'Migrated from existing delivery record',
                        'created_at' => $delivery->created_at
                    ),
                    array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
                );
            }
        }
    }
    
    /**
     * Migrate cashier session system - add new fields to zaikon_orders
     */
    public static function migrate_cashier_session_system() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zaikon_orders';
        
        // Verify table exists before altering
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return; // Table doesn't exist, skip migration
        }
        
        // Add payment_type field
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'payment_type'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `payment_type` enum('cash','cod','online') DEFAULT 'cash' AFTER `payment_status`");
        }
        
        // Add order_status field (different from payment_status)
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'order_status'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `order_status` enum('active','completed','cancelled','replacement') DEFAULT 'active' AFTER `payment_type`");
        }
        
        // Add special_instructions field if it doesn't exist
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'special_instructions'
        ));
        
        if (empty($column_exists)) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` ADD COLUMN `special_instructions` text AFTER `order_status`");
        }
        
        // Update payment_status enum to include COD_PENDING and COD_RECEIVED
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'payment_status'");
        if ($column_info && strpos($column_info->Type, 'cod_pending') === false) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` MODIFY `payment_status` ENUM('unpaid','paid','cod_pending','cod_received','refunded','void') DEFAULT 'unpaid'");
        }
        
        // Add 'delivered' to order_status enum
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'order_status'");
        if ($column_info && strpos($column_info->Type, 'delivered') === false) {
            $safe_table = esc_sql($table_name);
            $wpdb->query("ALTER TABLE `{$safe_table}` MODIFY `order_status` ENUM('active','delivered','completed','cancelled','replacement') DEFAULT 'active'");
        }
    }
    
    /**
     * Upgrade kitchen staff role to include rpos_view_orders capability
     * This ensures existing kitchen_staff users can view orders in KDS
     */
    public static function upgrade_kitchen_staff_capabilities() {
        // Get the kitchen_staff role
        $kitchen_staff = get_role('kitchen_staff');
        
        // If role exists and doesn't have the capability, add it
        if ($kitchen_staff && !$kitchen_staff->has_cap('rpos_view_orders')) {
            $kitchen_staff->add_cap('rpos_view_orders');
            error_log('RPOS: Added rpos_view_orders capability to kitchen_staff role');
        }
    }
    
    /**
     * Migrate delivery tracking system - add tracking fields to orders and deliveries
     */
    public static function migrate_delivery_tracking_system() {
        global $wpdb;
        
        // ========== Zaikon Orders Table Updates ==========
        $orders_table = $wpdb->prefix . 'zaikon_orders';
        
        // Verify table exists before altering
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $orders_table
        ));
        
        if (!$table_exists) {
            error_log('RPOS: zaikon_orders table does not exist, skipping tracking migration');
            return;
        }
        
        // Add tracking_token field for shareable tracking links
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$orders_table}` LIKE %s",
            'tracking_token'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `tracking_token` varchar(100) UNIQUE DEFAULT NULL AFTER `order_number`");
            error_log('RPOS: Added tracking_token column to zaikon_orders');
        }
        
        // Extend order_status enum to include tracking states
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM `{$orders_table}` LIKE 'order_status'");
        if ($column_info && strpos($column_info->Type, 'confirmed') === false) {
            $wpdb->query("ALTER TABLE `{$orders_table}` MODIFY `order_status` ENUM('pending','confirmed','cooking','ready','dispatched','delivered','active','completed','cancelled','replacement') DEFAULT 'pending'");
            error_log('RPOS: Extended order_status enum with tracking states');
        }
        
        // Add cooking_eta and delivery_eta fields
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$orders_table}` LIKE %s",
            'cooking_eta_minutes'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `cooking_eta_minutes` int(11) DEFAULT 20 AFTER `order_status`");
            error_log('RPOS: Added cooking_eta_minutes column to zaikon_orders');
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$orders_table}` LIKE %s",
            'delivery_eta_minutes'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `delivery_eta_minutes` int(11) DEFAULT 15 AFTER `cooking_eta_minutes`");
            error_log('RPOS: Added delivery_eta_minutes column to zaikon_orders');
        }
        
        // Add timestamp fields for tracking
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$orders_table}` LIKE %s",
            'confirmed_at'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `confirmed_at` datetime DEFAULT NULL AFTER `delivery_eta_minutes`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$orders_table}` LIKE %s",
            'cooking_started_at'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `cooking_started_at` datetime DEFAULT NULL AFTER `confirmed_at`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$orders_table}` LIKE %s",
            'ready_at'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `ready_at` datetime DEFAULT NULL AFTER `cooking_started_at`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$orders_table}` LIKE %s",
            'dispatched_at'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$orders_table}` ADD COLUMN `dispatched_at` datetime DEFAULT NULL AFTER `ready_at`");
        }
        
        // ========== Zaikon Deliveries Table Updates ==========
        $deliveries_table = $wpdb->prefix . 'zaikon_deliveries';
        
        // Verify table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $deliveries_table
        ));
        
        if (!$table_exists) {
            error_log('RPOS: zaikon_deliveries table does not exist, skipping delivery tracking migration');
            return;
        }
        
        // Add rider info fields for tracking page
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$deliveries_table}` LIKE %s",
            'rider_name'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$deliveries_table}` ADD COLUMN `rider_name` varchar(191) DEFAULT NULL AFTER `assigned_rider_id`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$deliveries_table}` LIKE %s",
            'rider_phone'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$deliveries_table}` ADD COLUMN `rider_phone` varchar(50) DEFAULT NULL AFTER `rider_name`");
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$deliveries_table}` LIKE %s",
            'rider_avatar'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$deliveries_table}` ADD COLUMN `rider_avatar` varchar(500) DEFAULT NULL AFTER `rider_phone`");
        }
        
        error_log('RPOS: Delivery tracking migration completed successfully');
    }
}
