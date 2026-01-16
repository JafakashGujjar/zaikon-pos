<?php
/**
 * Gas Cylinders Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Gas_Cylinders {
    
    /**
     * Default consumption units per product item
     * Adjustable constant for cylinder consumption calculation
     */
    const DEFAULT_CONSUMPTION_UNITS_PER_ITEM = 0.001;
    
    /**
     * Get all cylinder types
     */
    public static function get_all_types() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_gas_cylinder_types ORDER BY name ASC"
        );
    }
    
    /**
     * Get cylinder type by ID
     */
    public static function get_type($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_gas_cylinder_types WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create cylinder type
     */
    public static function create_type($data) {
        global $wpdb;
        
        if (empty($data['name'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_gas_cylinder_types',
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : ''
            ),
            array('%s', '%s')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update cylinder type
     */
    public static function update_type($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_gas_cylinder_types',
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    /**
     * Delete cylinder type
     */
    public static function delete_type($id) {
        global $wpdb;
        
        // Check if type is used in any cylinders
        $used = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_gas_cylinders WHERE cylinder_type_id = %d",
            $id
        ));
        
        if ($used > 0) {
            return false; // Cannot delete if used
        }
        
        // Delete product mappings first
        $wpdb->delete(
            $wpdb->prefix . 'rpos_gas_cylinder_product_map',
            array('cylinder_type_id' => $id),
            array('%d')
        );
        
        // Delete type
        return $wpdb->delete(
            $wpdb->prefix . 'rpos_gas_cylinder_types',
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get products mapped to a cylinder type
     */
    public static function get_product_mappings($cylinder_type_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}rpos_gas_cylinder_product_map 
             WHERE cylinder_type_id = %d",
            $cylinder_type_id
        ));
    }
    
    /**
     * Set product mappings for a cylinder type
     */
    public static function set_product_mappings($cylinder_type_id, $product_ids) {
        global $wpdb;
        
        // Delete existing mappings
        $wpdb->delete(
            $wpdb->prefix . 'rpos_gas_cylinder_product_map',
            array('cylinder_type_id' => $cylinder_type_id),
            array('%d')
        );
        
        // Insert new mappings
        if (!empty($product_ids)) {
            foreach ($product_ids as $product_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'rpos_gas_cylinder_product_map',
                    array(
                        'cylinder_type_id' => $cylinder_type_id,
                        'product_id' => absint($product_id)
                    ),
                    array('%d', '%d')
                );
            }
        }
        
        return true;
    }
    
    /**
     * Get all cylinders with optional filters
     */
    public static function get_all_cylinders($filters = array()) {
        global $wpdb;
        
        $where = array();
        $params = array();
        
        if (isset($filters['status'])) {
            $where[] = "c.status = %s";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['cylinder_type_id'])) {
            $where[] = "c.cylinder_type_id = %d";
            $params[] = $filters['cylinder_type_id'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT c.*, t.name as type_name 
                  FROM {$wpdb->prefix}rpos_gas_cylinders c
                  LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
                  {$where_clause}
                  ORDER BY c.created_at DESC";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get single cylinder
     */
    public static function get_cylinder($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, t.name as type_name 
             FROM {$wpdb->prefix}rpos_gas_cylinders c
             LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
             WHERE c.id = %d",
            $id
        ));
    }
    
    /**
     * Create new cylinder (check only one active per type)
     */
    public static function create_cylinder($data) {
        global $wpdb;
        
        if (empty($data['cylinder_type_id']) || empty($data['start_date'])) {
            return false;
        }
        
        // Check if there's already an active cylinder of this type
        $active_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_gas_cylinders 
             WHERE cylinder_type_id = %d AND status = 'active'",
            $data['cylinder_type_id']
        ));
        
        if ($active_exists > 0) {
            return false; // Cannot add new active cylinder if one already exists
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rpos_gas_cylinders',
            array(
                'cylinder_type_id' => absint($data['cylinder_type_id']),
                'zone_id' => !empty($data['zone_id']) ? absint($data['zone_id']) : null,
                'purchase_date' => !empty($data['purchase_date']) ? sanitize_text_field($data['purchase_date']) : null,
                'cost' => isset($data['cost']) ? floatval($data['cost']) : 0.00,
                'start_date' => sanitize_text_field($data['start_date']),
                'end_date' => null,
                'status' => 'active',
                'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
                'vendor' => !empty($data['vendor']) ? sanitize_text_field($data['vendor']) : null,
                'created_by' => get_current_user_id()
            ),
            array('%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Finish cylinder (mark as finished)
     */
    public static function finish_cylinder($id, $end_date) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_gas_cylinders',
            array(
                'end_date' => sanitize_text_field($end_date),
                'status' => 'finished'
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get cylinder usage report (sales during cylinder's active period)
     */
    public static function get_cylinder_usage_report($cylinder_id) {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Get cylinder details
        $cylinder = self::get_cylinder($cylinder_id);
        
        if (!$cylinder) {
            error_log('RPOS Gas Cylinders: Cylinder not found with ID: ' . absint($cylinder_id));
            return false;
        }
        
        error_log('RPOS Gas Cylinders: Generating usage report for cylinder #' . absint($cylinder_id) . ' (Type: ' . sanitize_text_field($cylinder->type_name) . ')');
        
        // Get product mappings for this cylinder type
        $product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT product_id FROM {$wpdb->prefix}rpos_gas_cylinder_product_map 
             WHERE cylinder_type_id = %d",
            $cylinder->cylinder_type_id
        ));
        
        error_log('RPOS Gas Cylinders: Found ' . count($product_ids) . ' mapped products: [' . implode(', ', array_map('absint', $product_ids)) . ']');
        
        if (empty($product_ids)) {
            error_log('RPOS Gas Cylinders: No products mapped to cylinder type #' . absint($cylinder->cylinder_type_id));
            return array(
                'cylinder' => $cylinder,
                'products' => array(),
                'total_sales' => 0,
                'debug_info' => array(
                    'mapped_products' => 0,
                    'date_range' => 'N/A',
                    'execution_time' => round((microtime(true) - $start_time) * 1000, 2) . 'ms'
                )
            );
        }
        
        // Build date range
        $date_from = $cylinder->start_date . ' 00:00:00';
        $date_to = $cylinder->end_date ? ($cylinder->end_date . ' 23:59:59') : date('Y-m-d H:i:s');
        
        error_log('RPOS Gas Cylinders: Date range: ' . $date_from . ' to ' . $date_to);
        
        // CRITICAL: Clear WordPress query cache to ensure fresh data
        // WordPress caches query results by default which causes stale data issues
        // when new orders are created. We must flush the cache before querying.
        // Note: This clears ALL cached queries, not just ours, but it's necessary
        // for data accuracy. The performance impact is minimal as this report is
        // accessed infrequently (admin-only, on-demand).
        $wpdb->flush();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RPOS Gas Cylinders: Query cache flushed to ensure fresh data');
        }
        
        // Run debug queries only if WP_DEBUG is enabled to avoid production overhead
        $total_orders_in_range = 0;
        $completed_orders_in_range = 0;
        $order_items_with_products = 0;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Count total orders in date range (for debugging)
            $total_orders_in_range = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders 
                 WHERE created_at >= %s AND created_at <= %s",
                $date_from,
                $date_to
            ));
            error_log('RPOS Gas Cylinders: Total orders in date range: ' . $total_orders_in_range);
            
            // Count completed orders in date range (for debugging)
            $completed_orders_in_range = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_orders 
                 WHERE created_at >= %s AND created_at <= %s AND status = 'completed'",
                $date_from,
                $date_to
            ));
            error_log('RPOS Gas Cylinders: Completed orders in date range: ' . $completed_orders_in_range);
            
            // Count order items with mapped products (for debugging)
            // Note: $placeholders_debug is safe - it contains only '%d' strings from array_fill()
            // The actual product IDs are sanitized by wpdb->prepare() via $params_debug
            $placeholders_debug = implode(',', array_fill(0, count($product_ids), '%d'));
            $params_debug = array_merge($product_ids, array($date_from, $date_to));
            $order_items_with_products = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT oi.order_id) 
                 FROM {$wpdb->prefix}rpos_order_items oi
                 INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
                 WHERE oi.product_id IN ($placeholders_debug)
                 AND o.created_at >= %s
                 AND o.created_at <= %s
                 AND o.status = 'completed'",
                $params_debug
            ));
            error_log('RPOS Gas Cylinders: Completed orders with mapped products: ' . $order_items_with_products);
        }
        
        
        // Get sales for mapped products during cylinder period
        // Note: $placeholders is safe - it contains only '%d' strings from array_fill()
        // The actual product IDs are sanitized by wpdb->prepare() via $params
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $params = array_merge($product_ids, array($date_from, $date_to));
        
        $query = "SELECT 
                    p.name as product_name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.line_total) as total_sales
                  FROM {$wpdb->prefix}rpos_order_items oi
                  INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
                  INNER JOIN {$wpdb->prefix}rpos_products p ON oi.product_id = p.id
                  WHERE oi.product_id IN ($placeholders)
                  AND o.created_at >= %s
                  AND o.created_at <= %s
                  AND o.status = 'completed'
                  GROUP BY oi.product_id, p.name
                  ORDER BY total_sales DESC";
        
        $prepared_query = $wpdb->prepare($query, $params);
        error_log('RPOS Gas Cylinders: Executing query for ' . count($product_ids) . ' products in date range');
        
        $products = $wpdb->get_results($prepared_query);
        
        error_log('RPOS Gas Cylinders: Query returned ' . count($products) . ' product results');
        
        $total_sales = 0;
        foreach ($products as $product) {
            $total_sales += floatval($product->total_sales);
        }
        
        error_log('RPOS Gas Cylinders: Found ' . count($products) . ' distinct products with total sales: ' . number_format($total_sales, 2));
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        // Log execution time with appropriate level based on performance
        if ($execution_time > 1000) {
            error_log('RPOS Gas Cylinders: WARNING - Report generated in ' . $execution_time . 'ms (slow query)');
        } elseif ($execution_time > 100) {
            error_log('RPOS Gas Cylinders: Report generated in ' . $execution_time . 'ms');
        }
        // Queries under 100ms are considered normal and not logged
        
        return array(
            'cylinder' => $cylinder,
            'products' => $products,
            'total_sales' => $total_sales,
            'debug_info' => array(
                'mapped_products' => count($product_ids),
                'date_range' => $date_from . ' to ' . $date_to,
                'total_orders_in_range' => $total_orders_in_range,
                'completed_orders_in_range' => $completed_orders_in_range,
                'orders_with_mapped_products' => $order_items_with_products,
                'product_results' => count($products),
                'execution_time' => $execution_time . 'ms'
            )
        );
    }
    
    // ========================================================================
    // ENTERPRISE CYLINDER TRACKING - ZONE MANAGEMENT
    // ========================================================================
    
    /**
     * Get all cylinder zones
     */
    public static function get_all_zones() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}zaikon_cylinder_zones 
             WHERE is_active = 1 
             ORDER BY name ASC"
        );
    }
    
    /**
     * Get zone by ID
     */
    public static function get_zone($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cylinder_zones WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Create zone
     */
    public static function create_zone($data) {
        global $wpdb;
        
        if (empty($data['name'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_cylinder_zones',
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
                'is_active' => 1
            ),
            array('%s', '%s', '%d')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update zone
     */
    public static function update_zone($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = absint($data['is_active']);
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_cylinder_zones',
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    // ========================================================================
    // ENTERPRISE CYLINDER TRACKING - LIFECYCLE MANAGEMENT
    // ========================================================================
    
    /**
     * Start new lifecycle for cylinder
     */
    public static function start_lifecycle($cylinder_id, $data = array()) {
        global $wpdb;
        
        $cylinder = self::get_cylinder($cylinder_id);
        if (!$cylinder) {
            return false;
        }
        
        // Calculate start date once at method start for consistency
        $start_date = isset($data['start_date']) ? sanitize_text_field($data['start_date']) : date('Y-m-d');
        
        // Create lifecycle record
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_cylinder_lifecycle',
            array(
                'cylinder_id' => $cylinder_id,
                'start_date' => $start_date,
                'refill_cost' => floatval($data['refill_cost'] ?? 0),
                'vendor' => isset($data['vendor']) ? sanitize_text_field($data['vendor']) : null,
                'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : null,
                'status' => 'active'
            ),
            array('%d', '%s', '%f', '%s', '%s', '%s')
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Close lifecycle for cylinder
     */
    public static function close_lifecycle($lifecycle_id, $end_date = null) {
        global $wpdb;
        
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        $lifecycle = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cylinder_lifecycle WHERE id = %d",
            $lifecycle_id
        ));
        
        if (!$lifecycle) {
            return false;
        }
        
        // Calculate metrics
        $start = new DateTime($lifecycle->start_date);
        $end = new DateTime($end_date);
        $days_diff = $end->diff($start)->days;
        $total_days = max(1, $days_diff);
        
        // Log warning if start and end dates are the same (potential data issue)
        if ($days_diff === 0) {
            error_log('RPOS Gas Cylinders: Lifecycle #' . $lifecycle_id . ' has same start and end date, using 1 day for calculations');
        }
        
        $orders_served = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zaikon_cylinder_consumption 
             WHERE lifecycle_id = %d",
            $lifecycle_id
        ));
        
        $avg_orders_per_day = $orders_served / $total_days;
        $cost_per_order = ($orders_served > 0) ? ($lifecycle->refill_cost / $orders_served) : 0;
        
        // Update lifecycle
        return $wpdb->update(
            $wpdb->prefix . 'zaikon_cylinder_lifecycle',
            array(
                'end_date' => $end_date,
                'orders_served' => $orders_served,
                'total_days' => $total_days,
                'avg_orders_per_day' => $avg_orders_per_day,
                'cost_per_order' => $cost_per_order,
                'status' => 'completed'
            ),
            array('id' => $lifecycle_id),
            array('%s', '%d', '%d', '%f', '%f', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get active lifecycle for cylinder
     */
    public static function get_active_lifecycle($cylinder_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cylinder_lifecycle 
             WHERE cylinder_id = %d AND status = 'active' 
             ORDER BY start_date DESC LIMIT 1",
            $cylinder_id
        ));
    }
    
    /**
     * Get all lifecycles for a cylinder
     */
    public static function get_cylinder_lifecycles($cylinder_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cylinder_lifecycle 
             WHERE cylinder_id = %d 
             ORDER BY start_date DESC",
            $cylinder_id
        ));
    }
    
    // ========================================================================
    // ENTERPRISE CYLINDER TRACKING - CONSUMPTION TRACKING
    // ========================================================================
    
    /**
     * Record consumption for an order
     * This is called automatically when an order is completed
     */
    public static function record_consumption($order_id, $order_items) {
        global $wpdb;
        
        if (empty($order_items)) {
            return false;
        }
        
        // Group items by cylinder type (via product mapping)
        $cylinder_consumptions = array();
        
        foreach ($order_items as $item) {
            $product_id = is_object($item) ? $item->product_id : $item['product_id'];
            $quantity = is_object($item) ? $item->quantity : $item['quantity'];
            
            // Find which cylinder type serves this product
            $cylinder_type_id = $wpdb->get_var($wpdb->prepare(
                "SELECT cylinder_type_id FROM {$wpdb->prefix}rpos_gas_cylinder_product_map 
                 WHERE product_id = %d LIMIT 1",
                $product_id
            ));
            
            if (!$cylinder_type_id) {
                continue; // Product not mapped to any cylinder
            }
            
            // Find active cylinder for this type
            $cylinder = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rpos_gas_cylinders 
                 WHERE cylinder_type_id = %d AND status = 'active' 
                 ORDER BY start_date DESC LIMIT 1",
                $cylinder_type_id
            ));
            
            if (!$cylinder) {
                continue; // No active cylinder for this type
            }
            
            // Get active lifecycle
            $lifecycle = self::get_active_lifecycle($cylinder->id);
            if (!$lifecycle) {
                // Create lifecycle if not exists
                $lifecycle_id = self::start_lifecycle($cylinder->id);
            } else {
                $lifecycle_id = $lifecycle->id;
            }
            
            // Record consumption using configurable constant
            $wpdb->insert(
                $wpdb->prefix . 'zaikon_cylinder_consumption',
                array(
                    'cylinder_id' => $cylinder->id,
                    'lifecycle_id' => $lifecycle_id,
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'consumption_units' => $quantity * self::DEFAULT_CONSUMPTION_UNITS_PER_ITEM
                ),
                array('%d', '%d', '%d', '%d', '%d', '%f')
            );
            
            // Update cylinder orders_served counter
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}rpos_gas_cylinders 
                 SET orders_served = orders_served + 1 
                 WHERE id = %d",
                $cylinder->id
            ));
        }
        
        return true;
    }
    
    /**
     * Get consumption logs for a cylinder
     */
    public static function get_consumption_logs($cylinder_id, $limit = 100) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.name as product_name, o.order_number 
             FROM {$wpdb->prefix}zaikon_cylinder_consumption c
             LEFT JOIN {$wpdb->prefix}rpos_products p ON c.product_id = p.id
             LEFT JOIN {$wpdb->prefix}rpos_orders o ON c.order_id = o.id
             WHERE c.cylinder_id = %d
             ORDER BY c.created_at DESC
             LIMIT %d",
            $cylinder_id,
            $limit
        ));
    }
    
    // ========================================================================
    // ENTERPRISE CYLINDER TRACKING - REFILL WORKFLOW
    // ========================================================================
    
    /**
     * Process cylinder refill
     * Closes current lifecycle and starts a new one
     */
    public static function process_refill($cylinder_id, $data) {
        global $wpdb;
        
        $cylinder = self::get_cylinder($cylinder_id);
        if (!$cylinder) {
            return false;
        }
        
        // Get active lifecycle and close it
        $active_lifecycle = self::get_active_lifecycle($cylinder_id);
        if ($active_lifecycle) {
            self::close_lifecycle($active_lifecycle->id, $data['refill_date'] ?? date('Y-m-d'));
        }
        
        // Record refill
        $refill_id = $wpdb->insert(
            $wpdb->prefix . 'zaikon_cylinder_refill',
            array(
                'cylinder_id' => $cylinder_id,
                'lifecycle_id' => $active_lifecycle ? $active_lifecycle->id : null,
                'refill_date' => sanitize_text_field($data['refill_date'] ?? date('Y-m-d')),
                'vendor' => isset($data['vendor']) ? sanitize_text_field($data['vendor']) : null,
                'cost' => floatval($data['cost'] ?? 0),
                'quantity' => floatval($data['quantity'] ?? 1),
                'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : null,
                'created_by' => get_current_user_id()
            ),
            array('%d', '%d', '%s', '%s', '%f', '%f', '%s', '%d')
        );
        
        if (!$refill_id) {
            return false;
        }
        
        $refill_id = $wpdb->insert_id;
        
        // Start new lifecycle
        $lifecycle_id = self::start_lifecycle($cylinder_id, array(
            'start_date' => $data['refill_date'] ?? date('Y-m-d'),
            'refill_cost' => $data['cost'] ?? 0,
            'vendor' => $data['vendor'] ?? null,
            'notes' => 'Refilled: ' . ($data['notes'] ?? '')
        ));
        
        // Reset cylinder counters
        $wpdb->update(
            $wpdb->prefix . 'rpos_gas_cylinders',
            array(
                'orders_served' => 0,
                'remaining_percentage' => 100.00,
                'vendor' => isset($data['vendor']) ? sanitize_text_field($data['vendor']) : null,
                'status' => 'active'
            ),
            array('id' => $cylinder_id),
            array('%d', '%f', '%s', '%s'),
            array('%d')
        );
        
        return $refill_id;
    }
    
    /**
     * Get refill history for a cylinder
     */
    public static function get_refill_history($cylinder_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name as created_by_name 
             FROM {$wpdb->prefix}zaikon_cylinder_refill r
             LEFT JOIN {$wpdb->users} u ON r.created_by = u.ID
             WHERE r.cylinder_id = %d
             ORDER BY r.refill_date DESC",
            $cylinder_id
        ));
    }
    
    // ========================================================================
    // ENTERPRISE CYLINDER TRACKING - ANALYTICS & FORECASTING
    // ========================================================================
    
    /**
     * Calculate burn rate for a cylinder
     */
    public static function calculate_burn_rate($cylinder_id) {
        global $wpdb;
        
        $cylinder = self::get_cylinder($cylinder_id);
        if (!$cylinder || $cylinder->status !== 'active') {
            return false;
        }
        
        $lifecycle = self::get_active_lifecycle($cylinder_id);
        if (!$lifecycle) {
            return array(
                'orders_per_day' => 0,
                'units_per_day' => 0,
                'remaining_days' => 0
            );
        }
        
        // Calculate days since start
        $start = new DateTime($lifecycle->start_date);
        $now = new DateTime();
        $days_active = max(1, $now->diff($start)->days);
        
        // Get total orders and consumption
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as order_count, SUM(consumption_units) as total_units 
             FROM {$wpdb->prefix}zaikon_cylinder_consumption 
             WHERE lifecycle_id = %d",
            $lifecycle->id
        ));
        
        $orders_per_day = $stats->order_count / $days_active;
        $units_per_day = $stats->total_units / $days_active;
        
        // Estimate remaining days
        // Note: remaining_percentage represents the percentage of cylinder capacity remaining (0-100)
        // We assume the cylinder starts at 100% and depletes based on consumption_units
        // Formula: remaining_days = (remaining_percentage / 100) / (units_consumed_per_day / 100)
        //          Simplified: remaining_days = remaining_percentage / units_per_day
        $remaining_percentage = floatval($cylinder->remaining_percentage);
        $remaining_days = ($units_per_day > 0) ? ($remaining_percentage / $units_per_day) : 0;
        
        return array(
            'orders_per_day' => round($orders_per_day, 2),
            'units_per_day' => round($units_per_day, 4),
            'remaining_days' => round($remaining_days, 1),
            'orders_served' => intval($stats->order_count),
            'days_active' => $days_active
        );
    }
    
    /**
     * Get dashboard analytics
     */
    public static function get_dashboard_analytics() {
        global $wpdb;
        
        // Active cylinders count
        $active_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_gas_cylinders WHERE status = 'active'"
        );
        
        // Total orders served (all active cylinders)
        $total_orders = $wpdb->get_var(
            "SELECT SUM(orders_served) FROM {$wpdb->prefix}rpos_gas_cylinders WHERE status = 'active'"
        );
        
        // Average burn rate across all active cylinders
        $active_cylinders = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}rpos_gas_cylinders WHERE status = 'active'"
        );
        
        $total_burn_rate = 0;
        $total_remaining_days = 0;
        $cylinder_count = 0;
        
        foreach ($active_cylinders as $cyl) {
            $burn_rate = self::calculate_burn_rate($cyl->id);
            if ($burn_rate) {
                $total_burn_rate += $burn_rate['orders_per_day'];
                $total_remaining_days += $burn_rate['remaining_days'];
                $cylinder_count++;
            }
        }
        
        $avg_burn_rate = ($cylinder_count > 0) ? ($total_burn_rate / $cylinder_count) : 0;
        $avg_remaining_days = ($cylinder_count > 0) ? ($total_remaining_days / $cylinder_count) : 0;
        
        // Monthly refill cost
        $monthly_cost = $wpdb->get_var(
            "SELECT SUM(cost) FROM {$wpdb->prefix}zaikon_cylinder_refill 
             WHERE refill_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return array(
            'active_cylinders' => intval($active_count),
            'total_orders_served' => intval($total_orders),
            'avg_burn_rate' => round($avg_burn_rate, 2),
            'avg_remaining_days' => round($avg_remaining_days, 1),
            'monthly_refill_cost' => floatval($monthly_cost)
        );
    }
    
    /**
     * Get efficiency comparison between zones/cylinders
     */
    public static function get_efficiency_comparison() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT c.id, c.cylinder_type_id, t.name as type_name, z.name as zone_name,
                    c.orders_served, c.remaining_percentage, c.start_date,
                    DATEDIFF(NOW(), c.start_date) as days_active,
                    CASE 
                        WHEN DATEDIFF(NOW(), c.start_date) > 0 
                        THEN c.orders_served / DATEDIFF(NOW(), c.start_date)
                        ELSE 0 
                    END as orders_per_day
             FROM {$wpdb->prefix}rpos_gas_cylinders c
             LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
             LEFT JOIN {$wpdb->prefix}zaikon_cylinder_zones z ON c.zone_id = z.id
             WHERE c.status = 'active'
             ORDER BY orders_per_day DESC"
        );
    }
}
