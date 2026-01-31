<?php
/**
 * Zaikon Order Status Service
 * 
 * Enterprise-grade centralized status management service.
 * Single source of truth for all order status transitions,
 * validations, timestamp management, and audit logging.
 * 
 * This service ensures:
 * - Unified status vocabulary across POS, KDS, and Tracking
 * - Automatic timestamp setting on status changes
 * - Full audit trail with source tracking (POS/KDS/API)
 * - Health check and auto-repair capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Order_Status_Service {
    
    /**
     * Valid order statuses matching DB enum
     * Reference: includes/class-rpos-install.php line ~988
     */
    const VALID_STATUSES = array(
        'pending',      // Order created, awaiting confirmation
        'confirmed',    // Order confirmed, awaiting kitchen
        'cooking',      // Kitchen started preparation
        'ready',        // Food ready for pickup/dispatch
        'dispatched',   // Rider/customer picked up, en route
        'delivered',    // Order delivered to customer
        'active',       // Legacy status (equivalent to pending)
        'completed',    // Order fully completed
        'cancelled',    // Order cancelled
        'replacement'   // Replacement order
    );
    
    /**
     * Status sources for audit trail
     */
    const SOURCE_POS = 'pos';
    const SOURCE_KDS = 'kds';
    const SOURCE_API = 'api';
    const SOURCE_SYSTEM = 'system';
    const SOURCE_TRACKING = 'tracking';
    
    /**
     * Timestamp field mapping for each status
     */
    const STATUS_TIMESTAMP_MAP = array(
        'confirmed' => 'confirmed_at',
        'cooking' => 'cooking_started_at',
        'ready' => 'ready_at',
        'dispatched' => 'dispatched_at'
    );
    
    /**
     * Default ETA values in minutes
     * Used by Zaikon_Order_Tracking::update_status() and tracking page
     * Reference: templates/tracking-page.php uses these values for countdown timers
     */
    const DEFAULT_COOKING_ETA = 20;
    const DEFAULT_DELIVERY_ETA = 10;
    const OVERTIME_EXTENSION = 5;
    
    /**
     * Transition order status with full validation and audit
     * 
     * This is the primary method for changing order status.
     * It ensures consistency across POS, KDS, and Tracking.
     * 
     * @param int $order_id Order ID
     * @param string $new_status New status to set
     * @param string $source Source of the change (pos, kds, api, system)
     * @param int|null $user_id User performing the action
     * @param array $options Additional options (force, skip_timestamp, notes)
     * @return array Result with success, message, and data
     */
    public static function transition_status($order_id, $new_status, $source = self::SOURCE_API, $user_id = null, $options = array()) {
        global $wpdb;
        
        $result = array(
            'success' => false,
            'message' => '',
            'old_status' => null,
            'new_status' => $new_status,
            'timestamps_set' => array()
        );
        
        // Validate status
        if (!self::is_valid_status($new_status)) {
            $result['message'] = 'Invalid status: ' . $new_status . '. Valid statuses: ' . implode(', ', self::VALID_STATUSES);
            error_log('ZAIKON STATUS SERVICE [ERROR]: ' . $result['message'] . ' (order #' . $order_id . ')');
            return $result;
        }
        
        // Get current order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT id, order_number, order_status, order_type, 
                    cooking_eta_minutes, delivery_eta_minutes,
                    confirmed_at, cooking_started_at, ready_at, dispatched_at
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            $result['message'] = 'Order not found: #' . $order_id;
            error_log('ZAIKON STATUS SERVICE [ERROR]: ' . $result['message']);
            return $result;
        }
        
        $result['old_status'] = $order->order_status;
        
        // Idempotent check
        if ($order->order_status === $new_status && empty($options['force'])) {
            $result['success'] = true;
            $result['message'] = 'Status already set to ' . $new_status;
            return $result;
        }
        
        // Use Zaikon_Order_Tracking for the actual update (single source of truth)
        $update_result = Zaikon_Order_Tracking::update_status($order_id, $new_status, $user_id, $source);
        
        if (is_wp_error($update_result)) {
            $result['message'] = $update_result->get_error_message();
            return $result;
        }
        
        // Log to status audit table
        self::log_status_audit($order_id, $order->order_status, $new_status, $source, $user_id);
        
        $result['success'] = true;
        $result['message'] = 'Status updated from ' . $order->order_status . ' to ' . $new_status;
        
        return $result;
    }
    
    /**
     * Check if a status is valid
     * 
     * @param string $status Status to validate
     * @return bool True if valid
     */
    public static function is_valid_status($status) {
        return in_array($status, self::VALID_STATUSES, true);
    }
    
    /**
     * Get the timestamp field for a given status
     * 
     * @param string $status Order status
     * @return string|null Timestamp field name or null
     */
    public static function get_timestamp_field($status) {
        return isset(self::STATUS_TIMESTAMP_MAP[$status]) ? self::STATUS_TIMESTAMP_MAP[$status] : null;
    }
    
    /**
     * Log status change to audit table
     * 
     * @param int $order_id Order ID
     * @param string $old_status Previous status
     * @param string $new_status New status
     * @param string $source Source of change
     * @param int|null $user_id User ID
     * @return int|false Insert ID or false on failure
     */
    public static function log_status_audit($order_id, $old_status, $new_status, $source, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Log to zaikon_status_audit table
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_status_audit',
            array(
                'order_id' => absint($order_id),
                'status_from' => $old_status ? sanitize_text_field($old_status) : null,
                'status_to' => sanitize_text_field($new_status),
                'actor_user_id' => absint($user_id),
                'source' => sanitize_text_field($source),
                'created_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('ZAIKON STATUS SERVICE [WARNING]: Failed to log status audit. Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Health check: Find orders with status/timestamp inconsistencies
     * 
     * @return array List of orders with issues
     */
    public static function health_check() {
        global $wpdb;
        
        $issues = array();
        
        // ========== Enterprise Requirement: Detect table desync ==========
        
        // Find orders in rpos_orders but NOT in zaikon_orders (missing sync)
        $desync_rpos_only = $wpdb->get_results(
            "SELECT r.id AS rpos_id, r.order_number, r.status AS rpos_status, r.created_at 
             FROM {$wpdb->prefix}rpos_orders r
             LEFT JOIN {$wpdb->prefix}zaikon_orders z ON r.order_number = z.order_number
             WHERE z.id IS NULL
             AND r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY r.id DESC LIMIT 50"
        );
        
        foreach ($desync_rpos_only as $order) {
            $issues[] = array(
                'order_id' => $order->rpos_id,
                'order_number' => $order->order_number,
                'issue' => 'desync_missing_in_zaikon',
                'status' => $order->rpos_status,
                'missing_field' => null,
                'table' => 'rpos_orders',
                'description' => 'Order exists in rpos_orders but not synced to zaikon_orders'
            );
        }
        
        // Find orders in zaikon_orders but NOT in rpos_orders (opposite desync, less common)
        $desync_zaikon_only = $wpdb->get_results(
            "SELECT z.id AS zaikon_id, z.order_number, z.order_status AS zaikon_status, z.created_at 
             FROM {$wpdb->prefix}zaikon_orders z
             LEFT JOIN {$wpdb->prefix}rpos_orders r ON z.order_number = r.order_number
             WHERE r.id IS NULL
             AND z.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY z.id DESC LIMIT 50"
        );
        
        foreach ($desync_zaikon_only as $order) {
            $issues[] = array(
                'order_id' => $order->zaikon_id,
                'order_number' => $order->order_number,
                'issue' => 'desync_missing_in_rpos',
                'status' => $order->zaikon_status,
                'missing_field' => null,
                'table' => 'zaikon_orders',
                'description' => 'Order exists in zaikon_orders but not in rpos_orders (KDS may not see it)'
            );
        }
        
        // Find orders with status mismatch between tables
        // Status progression rules (must match map_rpos_to_zaikon_status):
        // - completed in rpos should map to completed/delivered in zaikon
        // - cooking in rpos can map to cooking/ready/dispatched/delivered/completed in zaikon (forward progress OK)
        // - ready in rpos can map to ready/dispatched/delivered/completed in zaikon (forward progress OK)
        $status_mismatch = $wpdb->get_results(
            "SELECT r.id AS rpos_id, z.id AS zaikon_id, r.order_number, 
                    r.status AS rpos_status, z.order_status AS zaikon_status,
                    r.created_at
             FROM {$wpdb->prefix}rpos_orders r
             INNER JOIN {$wpdb->prefix}zaikon_orders z ON r.order_number = z.order_number
             WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND (
                 (r.status = 'completed' AND z.order_status NOT IN ('completed', 'delivered'))
                 OR (r.status = 'cooking' AND z.order_status NOT IN ('cooking', 'ready', 'dispatched', 'delivered', 'completed'))
                 OR (r.status = 'ready' AND z.order_status NOT IN ('ready', 'dispatched', 'delivered', 'completed'))
             )
             ORDER BY r.id DESC LIMIT 50"
        );
        
        foreach ($status_mismatch as $order) {
            $issues[] = array(
                'order_id' => $order->zaikon_id,
                'order_number' => $order->order_number,
                'issue' => 'status_mismatch_between_tables',
                'status' => $order->zaikon_status,
                'rpos_status' => $order->rpos_status,
                'missing_field' => null,
                'description' => 'Status mismatch: rpos=' . $order->rpos_status . ', zaikon=' . $order->zaikon_status
            );
        }
        
        // ========== Original timestamp checks ==========
        
        // Find orders with cooking status but no cooking_started_at
        $cooking_issues = $wpdb->get_results(
            "SELECT id, order_number, order_status, cooking_started_at 
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE order_status = 'cooking' AND (cooking_started_at IS NULL OR cooking_started_at = '')
             ORDER BY id DESC LIMIT 100"
        );
        
        foreach ($cooking_issues as $order) {
            $issues[] = array(
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'issue' => 'cooking_no_timestamp',
                'status' => $order->order_status,
                'missing_field' => 'cooking_started_at'
            );
        }
        
        // Find orders with dispatched status but no dispatched_at
        $dispatched_issues = $wpdb->get_results(
            "SELECT id, order_number, order_status, dispatched_at 
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE order_status = 'dispatched' AND (dispatched_at IS NULL OR dispatched_at = '')
             ORDER BY id DESC LIMIT 100"
        );
        
        foreach ($dispatched_issues as $order) {
            $issues[] = array(
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'issue' => 'dispatched_no_timestamp',
                'status' => $order->order_status,
                'missing_field' => 'dispatched_at'
            );
        }
        
        // Find orders with ready status but no ready_at
        $ready_issues = $wpdb->get_results(
            "SELECT id, order_number, order_status, ready_at 
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE order_status = 'ready' AND (ready_at IS NULL OR ready_at = '')
             ORDER BY id DESC LIMIT 100"
        );
        
        foreach ($ready_issues as $order) {
            $issues[] = array(
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'issue' => 'ready_no_timestamp',
                'status' => $order->order_status,
                'missing_field' => 'ready_at'
            );
        }
        
        // Find orders with invalid statuses (not in enum)
        $invalid_status_sql = "SELECT id, order_number, order_status 
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE order_status NOT IN ('" . implode("','", array_map('esc_sql', self::VALID_STATUSES)) . "')
             ORDER BY id DESC LIMIT 100";
        
        $invalid_statuses = $wpdb->get_results($invalid_status_sql);
        
        foreach ($invalid_statuses as $order) {
            $issues[] = array(
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'issue' => 'invalid_status',
                'status' => $order->order_status,
                'missing_field' => null
            );
        }
        
        return $issues;
    }
    
    /**
     * Auto-repair: Fix orders with status/timestamp inconsistencies
     * 
     * @param bool $dry_run If true, only report what would be fixed
     * @return array Repair results
     */
    public static function auto_repair($dry_run = true) {
        global $wpdb;
        
        $results = array(
            'checked' => 0,
            'fixed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => array()
        );
        
        $issues = self::health_check();
        $results['checked'] = count($issues);
        
        foreach ($issues as $issue) {
            $order_id = $issue['order_id'];
            
            // Handle missing timestamp issues
            if (in_array($issue['issue'], array('cooking_no_timestamp', 'dispatched_no_timestamp', 'ready_no_timestamp'))) {
                $field = $issue['missing_field'];
                
                if ($dry_run) {
                    $results['details'][] = array(
                        'order_id' => $order_id,
                        'action' => 'would_set_timestamp',
                        'field' => $field
                    );
                    $results['skipped']++;
                } else {
                    $update_result = $wpdb->update(
                        $wpdb->prefix . 'zaikon_orders',
                        array(
                            $field => RPOS_Timezone::current_utc_mysql(),
                            'updated_at' => RPOS_Timezone::current_utc_mysql()
                        ),
                        array('id' => $order_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                    
                    if ($update_result !== false) {
                        $results['fixed']++;
                        $results['details'][] = array(
                            'order_id' => $order_id,
                            'action' => 'set_timestamp',
                            'field' => $field
                        );
                        
                        // Log the auto-repair
                        self::log_status_audit($order_id, $issue['status'], $issue['status'], self::SOURCE_SYSTEM, 0);
                        Zaikon_System_Events::log('order', $order_id, 'auto_repair', array(
                            'issue' => $issue['issue'],
                            'field_fixed' => $field
                        ));
                    } else {
                        $results['errors']++;
                        $results['details'][] = array(
                            'order_id' => $order_id,
                            'action' => 'error',
                            'error' => $wpdb->last_error
                        );
                    }
                }
            }
            
            // Handle invalid status issues - map to closest valid status
            if ($issue['issue'] === 'invalid_status') {
                $old_status = $issue['status'];
                $new_status = self::map_invalid_status($old_status);
                
                if ($dry_run) {
                    $results['details'][] = array(
                        'order_id' => $order_id,
                        'action' => 'would_fix_status',
                        'from' => $old_status,
                        'to' => $new_status
                    );
                    $results['skipped']++;
                } else {
                    $transition = self::transition_status($order_id, $new_status, self::SOURCE_SYSTEM, 0, array('force' => true));
                    
                    if ($transition['success']) {
                        $results['fixed']++;
                        $results['details'][] = array(
                            'order_id' => $order_id,
                            'action' => 'fixed_status',
                            'from' => $old_status,
                            'to' => $new_status
                        );
                    } else {
                        $results['errors']++;
                        $results['details'][] = array(
                            'order_id' => $order_id,
                            'action' => 'error',
                            'error' => $transition['message']
                        );
                    }
                }
            }
            
            // Handle desync: order exists in rpos_orders but not in zaikon_orders
            if ($issue['issue'] === 'desync_missing_in_zaikon') {
                $order_number = $issue['order_number'];
                
                if ($dry_run) {
                    $results['details'][] = array(
                        'order_id' => $order_id,
                        'order_number' => $order_number,
                        'action' => 'would_sync_to_zaikon',
                        'issue' => $issue['issue']
                    );
                    $results['skipped']++;
                } else {
                    // Get full order from rpos_orders and sync to zaikon_orders
                    $rpos_order = RPOS_Orders::get($order_id);
                    
                    if ($rpos_order) {
                        $sync_result = self::sync_rpos_order_to_zaikon($rpos_order);
                        
                        if ($sync_result) {
                            $results['fixed']++;
                            $results['details'][] = array(
                                'order_id' => $order_id,
                                'order_number' => $order_number,
                                'action' => 'synced_to_zaikon',
                                'zaikon_order_id' => $sync_result
                            );
                            
                            Zaikon_System_Events::log('order', $order_id, 'auto_repair_sync', array(
                                'issue' => $issue['issue'],
                                'from_table' => 'rpos_orders',
                                'to_table' => 'zaikon_orders',
                                'zaikon_order_id' => $sync_result
                            ));
                        } else {
                            $results['errors']++;
                            $results['details'][] = array(
                                'order_id' => $order_id,
                                'order_number' => $order_number,
                                'action' => 'error',
                                'error' => 'Failed to sync order to zaikon_orders'
                            );
                        }
                    } else {
                        $results['errors']++;
                        $results['details'][] = array(
                            'order_id' => $order_id,
                            'order_number' => $order_number,
                            'action' => 'error',
                            'error' => 'Could not fetch rpos_order data'
                        );
                    }
                }
            }
            
            // Handle status mismatch between tables - sync from latest timestamp
            if ($issue['issue'] === 'status_mismatch_between_tables') {
                $order_number = $issue['order_number'];
                $rpos_status = $issue['rpos_status'];
                $zaikon_status = $issue['status'];
                
                if ($dry_run) {
                    $results['details'][] = array(
                        'order_id' => $order_id,
                        'order_number' => $order_number,
                        'action' => 'would_sync_status',
                        'rpos_status' => $rpos_status,
                        'zaikon_status' => $zaikon_status
                    );
                    $results['skipped']++;
                } else {
                    // Use rpos_orders status as source of truth for KDS-driven orders
                    // Map rpos status to zaikon status using KDS mapping
                    $target_zaikon_status = self::map_rpos_to_zaikon_status($rpos_status);
                    
                    if ($target_zaikon_status) {
                        $update_result = Zaikon_Order_Tracking::update_status($order_id, $target_zaikon_status, 0, self::SOURCE_SYSTEM);
                        
                        if (!is_wp_error($update_result)) {
                            $results['fixed']++;
                            $results['details'][] = array(
                                'order_id' => $order_id,
                                'order_number' => $order_number,
                                'action' => 'synced_status',
                                'from' => $zaikon_status,
                                'to' => $target_zaikon_status
                            );
                            
                            Zaikon_System_Events::log('order', $order_id, 'auto_repair_status_sync', array(
                                'issue' => $issue['issue'],
                                'rpos_status' => $rpos_status,
                                'old_zaikon_status' => $zaikon_status,
                                'new_zaikon_status' => $target_zaikon_status
                            ));
                        } else {
                            $results['errors']++;
                            $results['details'][] = array(
                                'order_id' => $order_id,
                                'order_number' => $order_number,
                                'action' => 'error',
                                'error' => $update_result->get_error_message()
                            );
                        }
                    } else {
                        $results['skipped']++;
                        $results['details'][] = array(
                            'order_id' => $order_id,
                            'order_number' => $order_number,
                            'action' => 'skipped',
                            'reason' => 'No valid status mapping for: ' . $rpos_status
                        );
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Map rpos_orders status to zaikon_orders status
     * 
     * @param string $rpos_status Status from rpos_orders
     * @return string|null Mapped zaikon status or null if no mapping
     */
    private static function map_rpos_to_zaikon_status($rpos_status) {
        $mapping = array(
            'new' => 'pending',
            'cooking' => 'cooking',
            'ready' => 'ready',
            'completed' => 'dispatched', // KDS complete triggers dispatch for delivery
            'cancelled' => 'cancelled'
        );
        
        return isset($mapping[$rpos_status]) ? $mapping[$rpos_status] : null;
    }
    
    /**
     * Sync an order from rpos_orders to zaikon_orders
     * Used by auto-repair to fix desync issues
     * 
     * @param object $rpos_order The order object from RPOS_Orders::get()
     * @return int|false The zaikon order ID on success, false on failure
     */
    private static function sync_rpos_order_to_zaikon($rpos_order) {
        if (!$rpos_order || empty($rpos_order->order_number)) {
            return false;
        }
        
        // Check if already exists (safety check)
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}zaikon_orders WHERE order_number = %s",
            $rpos_order->order_number
        ));
        
        if ($existing) {
            return $existing; // Already synced
        }
        
        // Map order data to zaikon_orders format
        // Note: taxes_rs is set to 0 because rpos_orders does not store tax data separately.
        // Tax calculation in zaikon system is done at the UI level if needed.
        $mapped_status = self::map_rpos_to_zaikon_status($rpos_order->status);
        if (!$mapped_status) {
            error_log('ZAIKON AUTO-REPAIR: Unmapped rpos status "' . ($rpos_order->status ?? 'null') . '" for order #' . $rpos_order->order_number . ', defaulting to pending');
            $mapped_status = 'pending';
        }
        
        $zaikon_order_data = array(
            'order_number' => $rpos_order->order_number,
            'order_type' => sanitize_text_field($rpos_order->order_type ?? 'takeaway'),
            'items_subtotal_rs' => floatval($rpos_order->subtotal ?? 0),
            'delivery_charges_rs' => floatval($rpos_order->delivery_charge ?? 0),
            'discounts_rs' => floatval($rpos_order->discount ?? 0),
            'taxes_rs' => 0, // rpos_orders does not store tax - calculated at UI level
            'grand_total_rs' => floatval($rpos_order->total ?? 0),
            'payment_type' => sanitize_text_field($rpos_order->payment_type ?? 'cash'),
            'payment_status' => sanitize_text_field($rpos_order->payment_status ?? 'paid'),
            'order_status' => $mapped_status,
            'special_instructions' => sanitize_textarea_field($rpos_order->special_instructions ?? ''),
            'cashier_id' => absint($rpos_order->cashier_id ?? 0)
        );
        
        // Create in zaikon_orders
        $zaikon_order_id = Zaikon_Orders::create($zaikon_order_data);
        
        if (!$zaikon_order_id) {
            error_log('ZAIKON AUTO-REPAIR: Failed to create zaikon order for order #' . $rpos_order->order_number);
            return false;
        }
        
        // Sync order items if available
        if (!empty($rpos_order->items)) {
            foreach ($rpos_order->items as $item) {
                $item_data = array(
                    'order_id' => $zaikon_order_id,
                    'product_id' => absint($item->product_id ?? 0),
                    'product_name' => sanitize_text_field($item->product_name ?? ''),
                    'qty' => absint($item->quantity ?? 1),
                    'unit_price_rs' => floatval($item->unit_price ?? 0),
                    'line_total_rs' => floatval($item->line_total ?? 0)
                );
                
                Zaikon_Order_Items::create($item_data);
            }
        }
        
        // Generate tracking token
        Zaikon_Order_Tracking::generate_tracking_token($zaikon_order_id);
        
        error_log('ZAIKON AUTO-REPAIR: Successfully synced order #' . $rpos_order->order_number . ' to zaikon_orders (ID: ' . $zaikon_order_id . ')');
        
        return $zaikon_order_id;
    }
    
    /**
     * Map invalid/legacy statuses to valid ones
     * 
     * @param string $invalid_status The invalid status
     * @return string Closest valid status
     */
    private static function map_invalid_status($invalid_status) {
        // Handle common legacy/invalid status mappings
        $mappings = array(
            'preparing' => 'cooking',      // Legacy UI status
            'on_the_way' => 'dispatched',  // Legacy UI status
            'in_progress' => 'cooking',
            'out_for_delivery' => 'dispatched',
            'new' => 'pending',
            '' => 'pending'
        );
        
        $lower_status = strtolower(trim($invalid_status));
        
        if (isset($mappings[$lower_status])) {
            return $mappings[$lower_status];
        }
        
        // Default to pending for unknown statuses
        return 'pending';
    }
    
    /**
     * Get status audit history for an order
     * 
     * @param int $order_id Order ID
     * @param int $limit Max records to return
     * @return array Audit records
     */
    public static function get_status_history($order_id, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, status_from, status_to, actor_user_id, source, created_at
             FROM {$wpdb->prefix}zaikon_status_audit
             WHERE order_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $order_id,
            $limit
        ));
    }
    
    /**
     * Get stats on status transitions
     * 
     * @param string $date_from Start date (Y-m-d format)
     * @param string $date_to End date (Y-m-d format)
     * @return array Statistics
     */
    public static function get_transition_stats($date_from = null, $date_to = null) {
        global $wpdb;
        
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$date_to) {
            $date_to = date('Y-m-d');
        }
        
        // Count transitions by source
        $by_source = $wpdb->get_results($wpdb->prepare(
            "SELECT source, COUNT(*) as count
             FROM {$wpdb->prefix}zaikon_status_audit
             WHERE created_at BETWEEN %s AND %s
             GROUP BY source
             ORDER BY count DESC",
            $date_from . ' 00:00:00',
            $date_to . ' 23:59:59'
        ));
        
        // Count transitions by status pair
        $by_transition = $wpdb->get_results($wpdb->prepare(
            "SELECT CONCAT(status_from, ' → ', status_to) as transition, COUNT(*) as count
             FROM {$wpdb->prefix}zaikon_status_audit
             WHERE created_at BETWEEN %s AND %s
             GROUP BY status_from, status_to
             ORDER BY count DESC
             LIMIT 20",
            $date_from . ' 00:00:00',
            $date_to . ' 23:59:59'
        ));
        
        return array(
            'date_range' => array('from' => $date_from, 'to' => $date_to),
            'by_source' => $by_source,
            'by_transition' => $by_transition
        );
    }
}
