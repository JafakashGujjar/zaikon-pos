<?php
/**
 * Zaikon Order Tracking Class
 * Handles order tracking functionality including token generation and status updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Order_Tracking {
    
    /**
     * Regex pattern for valid tracking tokens
     * Tokens must be 16-64 character lowercase hexadecimal strings
     */
    const TOKEN_PATTERN = '/^[a-f0-9]{16,64}$/';
    
    /**
     * Create a partial token preview for secure logging
     * Shows first 8 and last 4 characters for tokens >= 12 chars
     * For shorter tokens, shows asterisks
     * 
     * @param string|null $token The token to preview
     * @return string Token preview (e.g., "da276119...4ff3") or "NULL"
     */
    private static function create_token_preview($token) {
        if (empty($token)) {
            return 'NULL';
        }
        
        $length = strlen($token);
        
        // For tokens shorter than 12 chars, show asterisks (though valid tokens are 16+ chars)
        if ($length < 12) {
            return str_repeat('*', $length);
        }
        
        // Show first 8 and last 4 characters with ellipsis
        return substr($token, 0, 8) . '...' . substr($token, -4);
    }
    
    /**
     * Generate a unique tracking token for an order
     */
    public static function generate_tracking_token($order_id) {
        global $wpdb;
        
        error_log('ZAIKON TRACKING: generate_tracking_token called for order ID: ' . $order_id);
        
        // Generate a cryptographically secure random token
        $token = bin2hex(random_bytes(16)); // 32 character hex string
        
        error_log('ZAIKON TRACKING: Generated token: ' . self::create_token_preview($token) . ' for order ' . $order_id);
        
        // Ensure token is unique
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}zaikon_orders WHERE tracking_token = %s",
            $token
        ));
        
        if ($exists) {
            // Collision detected (extremely rare), try again
            error_log('ZAIKON TRACKING: Token collision detected, regenerating');
            return self::generate_tracking_token($order_id);
        }
        
        // Update order with tracking token
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            array('tracking_token' => $token),
            array('id' => $order_id),
            array('%s'),
            array('%d')
        );
        
        error_log('ZAIKON TRACKING: wpdb->update result for order ' . $order_id . ': ' . var_export($result, true));
        
        // Verify the update was successful
        // $wpdb->update() returns false on error, 0 if no rows matched OR no rows changed
        if ($result === false) {
            error_log('ZAIKON TRACKING: Database error saving tracking token for order ' . $order_id . '. Error: ' . $wpdb->last_error);
            return null;
        }
        
        // Verify token was actually saved by reading it back
        // This handles both $result === 0 (no change needed, token already set) and $result > 0 (token updated)
        $saved_token = $wpdb->get_var($wpdb->prepare(
            "SELECT tracking_token FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        error_log('ZAIKON TRACKING: Token verification for order ' . $order_id . ' - saved: ' . self::create_token_preview($saved_token));
        
        if ($saved_token !== $token) {
            // Log only partial tokens for security
            error_log('ZAIKON TRACKING: Token verification FAILED for order ' . $order_id . 
                      '. Expected: ' . self::create_token_preview($token) . 
                      ', Got: ' . self::create_token_preview($saved_token));
            return null;
        }
        
        error_log('ZAIKON TRACKING: Token successfully saved for order ' . $order_id);
        
        return $token;
    }
    
    /**
     * Get tracking URL for an order
     */
    public static function get_tracking_url($token) {
        return home_url('/track-order/' . $token);
    }
    
    /**
     * Get order by tracking token (public access)
     */
    public static function get_order_by_token($token) {
        global $wpdb;
        
        // Log incoming token for debugging (partial token for security)
        $token_preview = self::create_token_preview($token);
        error_log('ZAIKON TRACKING: get_order_by_token called with token: ' . $token_preview);
        
        if (empty($token)) {
            error_log('ZAIKON TRACKING: Token is empty, returning null');
            return null;
        }
        
        // Validate token format before querying
        if (!preg_match(self::TOKEN_PATTERN, $token)) {
            error_log('ZAIKON TRACKING: Token format invalid: ' . $token_preview);
            return null;
        }
        
        // STEP 1: First, do a simple lookup to find the order by token (no JOINs)
        // This ensures we find the order even if there are issues with related tables
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}zaikon_orders WHERE tracking_token = %s",
            $token
        ));
        
        if (!$order_id) {
            error_log('ZAIKON TRACKING: No order found for token ' . $token_preview);
            
            // DEBUG ONLY: Check if token exists with case-insensitive match
            // This helps diagnose if there's a case mismatch (tokens should always be lowercase)
            // NOTE: This query intentionally uses LOWER() for debugging purposes only.
            // It will perform a full table scan but runs only when the primary lookup fails,
            // which should be rare. This helps identify case mismatch issues without adding
            // overhead to the normal happy path.
            $debug_result = $wpdb->get_row($wpdb->prepare(
                "SELECT id, order_number, tracking_token FROM {$wpdb->prefix}zaikon_orders WHERE LOWER(tracking_token) = LOWER(%s)",
                $token
            ));
            
            if ($debug_result) {
                error_log('ZAIKON TRACKING: Found order with case-insensitive match! Order ID: ' . $debug_result->id . 
                          ', Order#: ' . $debug_result->order_number . 
                          ', DB token: ' . self::create_token_preview($debug_result->tracking_token));
            } else {
                // Check total orders with tokens
                $total_with_tokens = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}zaikon_orders WHERE tracking_token IS NOT NULL AND tracking_token != ''");
                error_log('ZAIKON TRACKING: Total orders with tokens in DB: ' . $total_with_tokens);
            }
            
            // Log last SQL error if any
            if ($wpdb->last_error) {
                error_log('ZAIKON TRACKING: DB error: ' . $wpdb->last_error);
            }
            
            return null;
        }
        
        error_log('ZAIKON TRACKING: Order ID ' . $order_id . ' found for token ' . $token_preview);
        
        // STEP 2: Now get full order details using the order ID (with JOINs for delivery/rider info)
        // This approach ensures we always find the order first, then enrich with related data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.id, o.order_number, o.order_type, o.items_subtotal_rs, 
                    o.delivery_charges_rs AS order_delivery_charges_rs, o.discounts_rs, 
                    o.taxes_rs, o.grand_total_rs, o.payment_status, o.payment_type, 
                    o.order_status, o.cooking_eta_minutes, o.delivery_eta_minutes,
                    o.confirmed_at, o.cooking_started_at, o.ready_at, o.dispatched_at,
                    o.created_at, o.updated_at,
                    d.customer_name, d.customer_phone, d.location_name, 
                    d.delivery_status, d.special_instruction,
                    d.delivery_charges_rs AS delivery_charges_rs,
                    d.delivered_at,
                    r.name AS rider_name, r.phone AS rider_phone, r.vehicle_number AS rider_vehicle
             FROM {$wpdb->prefix}zaikon_orders o
             LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON o.id = d.order_id
             LEFT JOIN {$wpdb->prefix}zaikon_riders r ON d.assigned_rider_id = r.id
             WHERE o.id = %d",
            $order_id
        ));
        
        // FALLBACK: If JOIN query somehow fails, fall back to separate queries
        // This should be rare but provides robustness against edge cases.
        // Note: This results in multiple queries (N+1) but only executes when
        // the optimized JOIN fails, ensuring the order is still returned.
        if (!$order) {
            error_log('ZAIKON TRACKING: JOIN query failed for order ID ' . $order_id . ', using basic query');
            
            // Get basic order info without JOINs
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT id, order_number, order_type, items_subtotal_rs, 
                        delivery_charges_rs AS order_delivery_charges_rs, discounts_rs, 
                        taxes_rs, grand_total_rs, payment_status, payment_type, 
                        order_status, cooking_eta_minutes, delivery_eta_minutes,
                        confirmed_at, cooking_started_at, ready_at, dispatched_at,
                        created_at, updated_at
                 FROM {$wpdb->prefix}zaikon_orders
                 WHERE id = %d",
                $order_id
            ));
            
            if (!$order) {
                error_log('ZAIKON TRACKING: Even basic query failed for order ID ' . $order_id);
                return null;
            }
            
            // Try to get delivery info separately
            $delivery = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_name, customer_phone, location_name, 
                        delivery_status, special_instruction,
                        delivery_charges_rs, delivered_at, assigned_rider_id
                 FROM {$wpdb->prefix}zaikon_deliveries
                 WHERE order_id = %d",
                $order_id
            ));
            
            if ($delivery) {
                $order->customer_name = $delivery->customer_name;
                $order->customer_phone = $delivery->customer_phone;
                $order->location_name = $delivery->location_name;
                $order->delivery_status = $delivery->delivery_status;
                $order->special_instruction = $delivery->special_instruction;
                $order->delivery_charges_rs = $delivery->delivery_charges_rs;
                $order->delivered_at = $delivery->delivered_at;
                
                // Try to get rider info
                if ($delivery->assigned_rider_id) {
                    $rider = $wpdb->get_row($wpdb->prepare(
                        "SELECT name AS rider_name, phone AS rider_phone, vehicle_number AS rider_vehicle
                         FROM {$wpdb->prefix}zaikon_riders
                         WHERE id = %d",
                        $delivery->assigned_rider_id
                    ));
                    
                    if ($rider) {
                        $order->rider_name = $rider->rider_name;
                        $order->rider_phone = $rider->rider_phone;
                        $order->rider_vehicle = $rider->rider_vehicle;
                    }
                }
            }
        }
        
        error_log('ZAIKON TRACKING: Order found! ID: ' . $order->id . ', Order#: ' . $order->order_number . ', Status: ' . $order->order_status);
        
        // Get order items with more details
        $order->items = $wpdb->get_results($wpdb->prepare(
            "SELECT product_name, qty, unit_price_rs, line_total_rs
             FROM {$wpdb->prefix}zaikon_order_items
             WHERE order_id = %d
             ORDER BY id ASC",
            $order->id
        ));
        
        return $order;
    }
    
    /**
     * Get order by ID with delivery and rider details (for fallback lookup)
     * This is the same query logic as get_order_by_token but searches by ID instead
     * 
     * @param int $order_id The order ID to lookup
     * @return object|null The order object with delivery details, or null if not found
     */
    public static function get_order_by_id($order_id) {
        global $wpdb;
        
        if (empty($order_id)) {
            return null;
        }
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.id, o.order_number, o.order_type, o.items_subtotal_rs, 
                    o.delivery_charges_rs AS order_delivery_charges_rs, o.discounts_rs, 
                    o.taxes_rs, o.grand_total_rs, o.payment_status, o.payment_type, 
                    o.order_status, o.cooking_eta_minutes, o.delivery_eta_minutes,
                    o.confirmed_at, o.cooking_started_at, o.ready_at, o.dispatched_at,
                    o.created_at, o.updated_at,
                    d.customer_name, d.customer_phone, d.location_name, 
                    d.delivery_status, d.special_instruction,
                    d.delivery_charges_rs AS delivery_charges_rs,
                    d.delivered_at,
                    r.name AS rider_name, r.phone AS rider_phone, r.vehicle_number AS rider_vehicle
             FROM {$wpdb->prefix}zaikon_orders o
             LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON o.id = d.order_id
             LEFT JOIN {$wpdb->prefix}zaikon_riders r ON d.assigned_rider_id = r.id
             WHERE o.id = %d",
            $order_id
        ));
        
        if (!$order) {
            return null;
        }
        
        // Get order items
        $order->items = $wpdb->get_results($wpdb->prepare(
            "SELECT product_name, qty, unit_price_rs, line_total_rs
             FROM {$wpdb->prefix}zaikon_order_items
             WHERE order_id = %d
             ORDER BY id ASC",
            $order->id
        ));
        
        return $order;
    }
    
    /**
     * Update order status and timestamps
     */
    public static function update_status($order_id, $new_status, $user_id = null, $source = 'api') {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Valid statuses matching DB enum: pending, confirmed, cooking, ready, dispatched, delivered, active, completed, cancelled, replacement
        $valid_statuses = array('pending', 'confirmed', 'cooking', 'ready', 'dispatched', 'delivered', 'completed', 'cancelled', 'active', 'replacement');
        
        if (!in_array($new_status, $valid_statuses)) {
            error_log('ZAIKON TRACKING: Invalid status "' . $new_status . '" attempted for order #' . $order_id . '. Valid: ' . implode(', ', $valid_statuses));
            return new WP_Error('invalid_status', 'Invalid order status: ' . $new_status);
        }
        
        // Get current status for audit logging
        $old_status = $wpdb->get_var($wpdb->prepare(
            "SELECT order_status FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        // Idempotent: if status is same, return success without update
        if ($old_status === $new_status) {
            error_log('ZAIKON TRACKING: Order #' . $order_id . ' already has status "' . $new_status . '", no change needed (source: ' . $source . ')');
            return true;
        }
        
        error_log('ZAIKON TRACKING: Updating order #' . $order_id . ' status from "' . $old_status . '" to "' . $new_status . '" (source: ' . $source . ')');
        
        // Prepare update data
        $update_data = array(
            'order_status' => $new_status,
            'updated_at' => RPOS_Timezone::current_utc_mysql()
        );
        
        // Add timestamp based on status
        switch ($new_status) {
            case 'confirmed':
                $update_data['confirmed_at'] = RPOS_Timezone::current_utc_mysql();
                break;
            case 'cooking':
                $update_data['cooking_started_at'] = RPOS_Timezone::current_utc_mysql();
                // Set default cooking ETA if not set
                $current_eta = $wpdb->get_var($wpdb->prepare(
                    "SELECT cooking_eta_minutes FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
                    $order_id
                ));
                if ($current_eta === null) {
                    $update_data['cooking_eta_minutes'] = 20;
                }
                break;
            case 'ready':
                $update_data['ready_at'] = RPOS_Timezone::current_utc_mysql();
                // Set default delivery ETA if not set (for when rider is assigned)
                $current_eta = $wpdb->get_var($wpdb->prepare(
                    "SELECT delivery_eta_minutes FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
                    $order_id
                ));
                if ($current_eta === null) {
                    $update_data['delivery_eta_minutes'] = 10; // 10 min default for delivery countdown
                }
                break;
            case 'dispatched':
                $update_data['dispatched_at'] = RPOS_Timezone::current_utc_mysql();
                // Set default delivery ETA if not set
                $current_eta = $wpdb->get_var($wpdb->prepare(
                    "SELECT delivery_eta_minutes FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
                    $order_id
                ));
                if ($current_eta === null) {
                    $update_data['delivery_eta_minutes'] = 10; // 10 min default for delivery countdown
                }
                // Update delivery status too
                $wpdb->update(
                    $wpdb->prefix . 'zaikon_deliveries',
                    array('delivery_status' => 'on_route'),
                    array('order_id' => $order_id),
                    array('%s'),
                    array('%d')
                );
                break;
            case 'delivered':
                // Update delivery record
                $wpdb->update(
                    $wpdb->prefix . 'zaikon_deliveries',
                    array(
                        'delivery_status' => 'delivered',
                        'delivered_at' => RPOS_Timezone::current_utc_mysql()
                    ),
                    array('order_id' => $order_id),
                    array('%s', '%s'),
                    array('%d')
                );
                break;
        }
        
        // Update order
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            $update_data,
            array('id' => $order_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
        
        if ($result === false) {
            // Enterprise traceability: strict logging on DB failures
            error_log('ZAIKON TRACKING [ERROR]: Failed to update order #' . $order_id . ' status from "' . $old_status . '" to "' . $new_status . '". MySQL Error: ' . $wpdb->last_error);
            
            // Log failure to system events for audit trail
            Zaikon_System_Events::log_error('order', $order_id, 'status_update_failed', 
                'Database update failed: ' . $wpdb->last_error, array(
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                    'source' => $source,
                    'user_id' => $user_id
                ));
            
            return new WP_Error('update_failed', 'Failed to update order status. Please try again.');
        }
        
        // Extract timestamp fields that were set for audit logging
        $timestamps_set = self::extract_timestamp_fields($update_data);
        
        // Enterprise audit logging with full traceability
        Zaikon_System_Events::log('order', $order_id, 'status_changed', array(
            'old_status' => $old_status,
            'new_status' => $new_status,
            'source' => $source,
            'user_id' => $user_id,
            'timestamps_set' => $timestamps_set
        ));
        
        // Enhanced logging with timestamp details
        $timestamp_log = '';
        if (!empty($timestamps_set)) {
            $timestamp_log = ' | Timestamps set: ' . implode(', ', $timestamps_set);
        }
        
        error_log('ZAIKON TRACKING [SUCCESS]: Order #' . $order_id . ' status changed: "' . $old_status . '" → "' . $new_status . '" (source: ' . $source . ')' . $timestamp_log);
        
        return true;
    }
    
    /**
     * Extract timestamp field names from update data for audit logging
     * 
     * @param array $update_data The data being updated
     * @return array List of timestamp field names that were set
     */
    private static function extract_timestamp_fields($update_data) {
        $timestamp_fields = array();
        foreach (array_keys($update_data) as $key) {
            if (strpos($key, '_at') !== false || strpos($key, 'updated') !== false) {
                $timestamp_fields[] = $key;
            }
        }
        return $timestamp_fields;
    }
    
    /**
     * Extend cooking ETA by minutes
     */
    public static function extend_cooking_eta($order_id, $additional_minutes = 5) {
        global $wpdb;
        
        $current_eta = $wpdb->get_var($wpdb->prepare(
            "SELECT cooking_eta_minutes FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        $new_eta = ($current_eta ?: 20) + $additional_minutes;
        
        $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            array('cooking_eta_minutes' => $new_eta),
            array('id' => $order_id),
            array('%d'),
            array('%d')
        );
        
        // Log event
        Zaikon_System_Events::log('order', $order_id, 'cooking_eta_extended', array(
            'old_eta' => $current_eta,
            'new_eta' => $new_eta,
            'additional_minutes' => $additional_minutes
        ));
        
        return $new_eta;
    }
    
    /**
     * Check if cooking has exceeded ETA and auto-extend if needed
     */
    public static function check_and_extend_cooking_eta($order_id) {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT cooking_started_at, cooking_eta_minutes, order_status 
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE id = %d",
            $order_id
        ));
        
        if (!$order || $order->order_status !== 'cooking' || !$order->cooking_started_at) {
            return false;
        }
        
        $cooking_start = strtotime($order->cooking_started_at);
        $current_time = time();
        $elapsed_minutes = ($current_time - $cooking_start) / 60;
        $eta_minutes = $order->cooking_eta_minutes ?: 20;
        
        // If elapsed time exceeds ETA, extend by 5 minutes
        if ($elapsed_minutes >= $eta_minutes) {
            return self::extend_cooking_eta($order_id, 5);
        }
        
        return false;
    }
    
    /**
     * Assign rider to order
     */
    public static function assign_rider($order_id, $rider_data) {
        global $wpdb;
        
        // Update delivery record with rider info
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_deliveries',
            array(
                'rider_name' => sanitize_text_field($rider_data['rider_name']),
                'rider_phone' => sanitize_text_field($rider_data['rider_phone']),
                'rider_avatar' => isset($rider_data['rider_avatar']) ? esc_url_raw($rider_data['rider_avatar']) : null,
                'assigned_rider_id' => isset($rider_data['rider_id']) ? absint($rider_data['rider_id']) : null,
                'delivery_status' => 'assigned'
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to assign rider');
        }
        
        // Log event
        Zaikon_System_Events::log('order', $order_id, 'rider_assigned', array(
            'rider_name' => $rider_data['rider_name'],
            'rider_phone' => $rider_data['rider_phone']
        ));
        
        return true;
    }
    
    /**
     * Calculate remaining ETA in minutes
     */
    public static function get_remaining_eta($order_id) {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT order_status, cooking_started_at, cooking_eta_minutes, 
                    ready_at, dispatched_at, delivery_eta_minutes
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return null;
        }
        
        $result = array(
            'status' => $order->order_status,
            'cooking_eta_remaining' => null,
            'delivery_eta_remaining' => null,
            'cooking_started_at' => $order->cooking_started_at,
            'ready_at' => $order->ready_at,
            'dispatched_at' => $order->dispatched_at
        );
        
        // Calculate cooking ETA if in cooking status
        if ($order->order_status === 'cooking' && $order->cooking_started_at) {
            $cooking_start = strtotime($order->cooking_started_at);
            $current_time = time();
            $elapsed_minutes = ($current_time - $cooking_start) / 60;
            $eta_minutes = $order->cooking_eta_minutes ?: 20;
            $remaining = max(0, $eta_minutes - $elapsed_minutes);
            $result['cooking_eta_remaining'] = round($remaining);
        }
        
        // Calculate delivery ETA if ready or dispatched
        // Uses dispatched_at if available, otherwise ready_at
        if (in_array($order->order_status, array('ready', 'dispatched'))) {
            $delivery_start_time = $order->dispatched_at ?: $order->ready_at;
            if ($delivery_start_time) {
                $start_time = strtotime($delivery_start_time);
                $current_time = time();
                $elapsed_minutes = ($current_time - $start_time) / 60;
                $eta_minutes = $order->delivery_eta_minutes ?: 10; // 10 min default for delivery
                $remaining = max(0, $eta_minutes - $elapsed_minutes);
                $result['delivery_eta_remaining'] = round($remaining);
            }
        }
        
        return $result;
    }
    
    /**
     * Auto-complete old orders after 2 hours
     * 
     * This method is designed to be called by a cron job.
     * It safely marks orders as completed if they are older than 2 hours.
     * 
     * Enterprise requirements:
     * - Idempotent: safe to run multiple times
     * - Does not corrupt reports/inventory (only changes status)
     * - Full logging for audit trail
     * 
     * @param int $hours Number of hours after which orders should be auto-completed (default: 2)
     * @return array Result with counts and details
     */
    public static function auto_complete_old_orders($hours = 2) {
        global $wpdb;
        
        $results = array(
            'total_processed' => 0,
            'completed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => array()
        );
        
        // Calculate the cutoff time (2 hours ago)
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        error_log('ZAIKON AUTO-COMPLETE: Starting auto-complete job. Cutoff time: ' . $cutoff_time . ' (orders older than ' . $hours . ' hours)');
        
        // Find orders that are:
        // 1. Created more than 2 hours ago
        // 2. Not in terminal statuses (completed, cancelled, delivered)
        // 3. In any active/processing status that should be auto-completed
        $open_orders = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_number, order_status, created_at, order_type 
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE created_at <= %s 
             AND order_status NOT IN ('completed', 'cancelled', 'delivered', 'replacement')
             ORDER BY created_at ASC
             LIMIT 100",
            $cutoff_time
        ));
        
        if (empty($open_orders)) {
            error_log('ZAIKON AUTO-COMPLETE: No orders found older than ' . $hours . ' hours that need completion.');
            return $results;
        }
        
        error_log('ZAIKON AUTO-COMPLETE: Found ' . count($open_orders) . ' orders to process.');
        
        foreach ($open_orders as $order) {
            $results['total_processed']++;
            
            // For delivery orders, mark as delivered; for others, mark as completed
            $new_status = ($order->order_type === 'delivery') ? 'delivered' : 'completed';
            
            // Use the centralized update_status method for consistency
            $update_result = self::update_status($order->id, $new_status, 0, 'system');
            
            if (is_wp_error($update_result)) {
                $results['errors']++;
                $results['details'][] = array(
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'action' => 'error',
                    'error' => $update_result->get_error_message()
                );
                error_log('ZAIKON AUTO-COMPLETE [ERROR]: Failed to auto-complete order #' . $order->order_number . '. Error: ' . $update_result->get_error_message());
            } else {
                $results['completed']++;
                $results['details'][] = array(
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'old_status' => $order->order_status,
                    'new_status' => $new_status,
                    'action' => 'completed'
                );
                error_log('ZAIKON AUTO-COMPLETE [SUCCESS]: Order #' . $order->order_number . ' auto-completed: ' . $order->order_status . ' → ' . $new_status);
                
                // Log to system events for audit trail
                Zaikon_System_Events::log('order', $order->id, 'auto_completed', array(
                    'old_status' => $order->order_status,
                    'new_status' => $new_status,
                    'reason' => 'Order older than ' . $hours . ' hours',
                    'created_at' => $order->created_at
                ));
            }
        }
        
        error_log('ZAIKON AUTO-COMPLETE: Job completed. Total: ' . $results['total_processed'] . ', Completed: ' . $results['completed'] . ', Errors: ' . $results['errors']);
        
        return $results;
    }
    
    /**
     * Extend delivery ETA by minutes (similar to cooking ETA extension)
     * 
     * @param int $order_id Order ID
     * @param int $additional_minutes Minutes to add (default: 5)
     * @return int New ETA value
     */
    public static function extend_delivery_eta($order_id, $additional_minutes = 5) {
        global $wpdb;
        
        $current_eta = $wpdb->get_var($wpdb->prepare(
            "SELECT delivery_eta_minutes FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        // Use null coalescing since 0 is not a valid ETA value (minimum is 10 minutes)
        $new_eta = ($current_eta ?? 10) + $additional_minutes;
        
        $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            array('delivery_eta_minutes' => $new_eta),
            array('id' => $order_id),
            array('%d'),
            array('%d')
        );
        
        // Log event
        Zaikon_System_Events::log('order', $order_id, 'delivery_eta_extended', array(
            'old_eta' => $current_eta,
            'new_eta' => $new_eta,
            'additional_minutes' => $additional_minutes
        ));
        
        error_log('ZAIKON TRACKING: Delivery ETA extended for order #' . $order_id . ': ' . $current_eta . ' → ' . $new_eta . ' minutes');
        
        return $new_eta;
    }
    
    /**
     * Check if delivery has exceeded ETA and auto-extend if needed
     * 
     * @param int $order_id Order ID
     * @return int|false New ETA or false if no extension needed
     */
    public static function check_and_extend_delivery_eta($order_id) {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT dispatched_at, ready_at, delivery_eta_minutes, order_status 
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE id = %d",
            $order_id
        ));
        
        if (!$order || !in_array($order->order_status, array('ready', 'dispatched'))) {
            return false;
        }
        
        // Use dispatched_at if available, otherwise ready_at
        $delivery_start = $order->dispatched_at ?: $order->ready_at;
        if (!$delivery_start) {
            return false;
        }
        
        $start_time = strtotime($delivery_start);
        $current_time = time();
        $elapsed_minutes = ($current_time - $start_time) / 60;
        // Use null coalescing since 0 is not a valid ETA value (minimum is 10 minutes)
        $eta_minutes = $order->delivery_eta_minutes ?? 10;
        
        // If elapsed time exceeds ETA, extend by 5 minutes
        if ($elapsed_minutes >= $eta_minutes) {
            return self::extend_delivery_eta($order_id, 5);
        }
        
        return false;
    }
}
