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
     * Generate a unique tracking token for an order
     */
    public static function generate_tracking_token($order_id) {
        global $wpdb;
        
        // Generate a cryptographically secure random token
        $token = bin2hex(random_bytes(16)); // 32 character hex string
        
        // Ensure token is unique
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}zaikon_orders WHERE tracking_token = %s",
            $token
        ));
        
        if ($exists) {
            // Collision detected (extremely rare), try again
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
        
        // Verify the update was successful
        // $wpdb->update() returns false on error, 0 if no rows matched OR no rows changed
        if ($result === false) {
            error_log('ZAIKON: Database error saving tracking token for order ' . $order_id . '. Error: ' . $wpdb->last_error);
            return null;
        }
        
        // Verify token was actually saved by reading it back
        // This handles both $result === 0 (no change needed, token already set) and $result > 0 (token updated)
        $saved_token = $wpdb->get_var($wpdb->prepare(
            "SELECT tracking_token FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        if ($saved_token !== $token) {
            // Log only partial token for security (first 8 and last 4 chars)
            $token_preview = substr($token, 0, 8) . '...' . substr($token, -4);
            $saved_preview = $saved_token ? (substr($saved_token, 0, 8) . '...' . substr($saved_token, -4)) : 'NULL';
            error_log('ZAIKON: Tracking token verification failed for order ' . $order_id . '. Expected: ' . $token_preview . ', Got: ' . $saved_preview);
            return null;
        }
        
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
        
        if (empty($token)) {
            return null;
        }
        
        // Get order with delivery details - include all necessary fields for tracking
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
             WHERE o.tracking_token = %s",
            $token
        ));
        
        if (!$order) {
            return null;
        }
        
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
     * Update order status and timestamps
     */
    public static function update_status($order_id, $new_status, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $valid_statuses = array('pending', 'confirmed', 'cooking', 'ready', 'dispatched', 'delivered', 'cancelled');
        
        if (!in_array($new_status, $valid_statuses)) {
            return new WP_Error('invalid_status', 'Invalid order status');
        }
        
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
                break;
            case 'dispatched':
                $update_data['dispatched_at'] = RPOS_Timezone::current_utc_mysql();
                // Set default delivery ETA if not set
                $current_eta = $wpdb->get_var($wpdb->prepare(
                    "SELECT delivery_eta_minutes FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
                    $order_id
                ));
                if ($current_eta === null) {
                    $update_data['delivery_eta_minutes'] = 15;
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
            error_log('ZAIKON: Failed to update order status. Error: ' . $wpdb->last_error);
            return new WP_Error('update_failed', 'Failed to update order status');
        }
        
        // Log to system events
        Zaikon_System_Events::log('order', $order_id, 'status_changed', array(
            'new_status' => $new_status,
            'user_id' => $user_id
        ));
        
        return true;
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
                    dispatched_at, delivery_eta_minutes
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
            'delivery_eta_remaining' => null
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
        
        // Calculate delivery ETA if dispatched
        if ($order->order_status === 'dispatched' && $order->dispatched_at) {
            $dispatch_time = strtotime($order->dispatched_at);
            $current_time = time();
            $elapsed_minutes = ($current_time - $dispatch_time) / 60;
            $eta_minutes = $order->delivery_eta_minutes ?: 15;
            $remaining = max(0, $eta_minutes - $elapsed_minutes);
            $result['delivery_eta_remaining'] = round($remaining);
        }
        
        return $result;
    }
}
