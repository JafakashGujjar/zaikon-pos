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
                'status_from' => sanitize_text_field($old_status ?: 'NULL'),
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
        }
        
        return $results;
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
