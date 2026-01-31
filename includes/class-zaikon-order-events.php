<?php
/**
 * Zaikon Order Events - Event-Based State Management
 * 
 * This class implements an event-based state model for order tracking,
 * providing a single source of truth for all order lifecycle events.
 * 
 * Instead of directly manipulating status strings, systems should fire
 * lifecycle events which automatically:
 * - Update order status
 * - Set appropriate timestamps
 * - Log audit trail
 * - Trigger notifications
 * 
 * @package Zaikon_POS
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Order_Events {
    
    /**
     * Lifecycle event types
     * These represent discrete state transitions in the order lifecycle
     */
    const EVENT_ORDER_CREATED = 'order_created';
    const EVENT_ORDER_CONFIRMED = 'order_confirmed';
    const EVENT_COOKING_STARTED = 'cooking_started';
    const EVENT_KITCHEN_COMPLETED = 'kitchen_completed';
    const EVENT_RIDER_ASSIGNED = 'rider_assigned';
    const EVENT_ORDER_DISPATCHED = 'order_dispatched';
    const EVENT_ORDER_DELIVERED = 'order_delivered';
    const EVENT_ORDER_CANCELLED = 'order_cancelled';
    const EVENT_ORDER_COMPLETED = 'order_completed';
    
    /**
     * Event to status mapping
     * Maps lifecycle events to their corresponding order status
     */
    const EVENT_STATUS_MAP = array(
        self::EVENT_ORDER_CREATED => 'pending',
        self::EVENT_ORDER_CONFIRMED => 'confirmed',
        self::EVENT_COOKING_STARTED => 'cooking',
        self::EVENT_KITCHEN_COMPLETED => 'ready',
        self::EVENT_RIDER_ASSIGNED => 'ready', // Status remains ready, just track rider assignment
        self::EVENT_ORDER_DISPATCHED => 'dispatched',
        self::EVENT_ORDER_DELIVERED => 'delivered',
        self::EVENT_ORDER_CANCELLED => 'cancelled',
        self::EVENT_ORDER_COMPLETED => 'completed'
    );
    
    /**
     * Event to timestamp field mapping
     * Maps lifecycle events to the timestamp field they should update
     */
    const EVENT_TIMESTAMP_MAP = array(
        self::EVENT_ORDER_CREATED => 'created_at',
        self::EVENT_ORDER_CONFIRMED => 'confirmed_at',
        self::EVENT_COOKING_STARTED => 'cooking_started_at',
        self::EVENT_KITCHEN_COMPLETED => 'ready_at',
        self::EVENT_RIDER_ASSIGNED => 'rider_assigned_at',
        self::EVENT_ORDER_DISPATCHED => 'dispatched_at',
        self::EVENT_ORDER_DELIVERED => 'delivered_at',
        self::EVENT_ORDER_CANCELLED => null, // No specific timestamp, uses updated_at
        self::EVENT_ORDER_COMPLETED => null  // No specific timestamp, uses updated_at
    );
    
    /**
     * Event sources
     */
    const SOURCE_POS = 'pos';
    const SOURCE_KDS = 'kds';
    const SOURCE_TRACKING = 'tracking';
    const SOURCE_API = 'api';
    const SOURCE_SYSTEM = 'system';
    const SOURCE_RIDER = 'rider';
    
    /**
     * Dispatch a lifecycle event
     * 
     * This is the primary method for triggering state changes.
     * It ensures atomic updates of status and timestamps.
     * 
     * @param int $order_id Order ID
     * @param string $event Event type (use EVENT_* constants)
     * @param array $options Additional options
     *   - source: Event source (pos, kds, api, etc.)
     *   - user_id: User who triggered the event
     *   - rider_id: Rider ID (for rider assignment events)
     *   - timestamp: Custom timestamp (defaults to current UTC)
     *   - notes: Additional notes for audit trail
     * @return array Result with success status and message
     */
    public static function dispatch($order_id, $event, $options = array()) {
        global $wpdb;
        
        $result = array(
            'success' => false,
            'event' => $event,
            'message' => '',
            'data' => array()
        );
        
        // Validate event type
        if (!self::is_valid_event($event)) {
            $result['message'] = 'Invalid event type: ' . $event;
            error_log('ZAIKON EVENTS [ERROR]: ' . $result['message'] . ' (order #' . $order_id . ')');
            return $result;
        }
        
        // Get order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT id, order_number, order_status FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            $result['message'] = 'Order not found: #' . $order_id;
            error_log('ZAIKON EVENTS [ERROR]: ' . $result['message']);
            return $result;
        }
        
        // Extract options
        $source = isset($options['source']) ? $options['source'] : self::SOURCE_SYSTEM;
        $user_id = isset($options['user_id']) ? absint($options['user_id']) : get_current_user_id();
        $rider_id = isset($options['rider_id']) ? absint($options['rider_id']) : null;
        $timestamp = isset($options['timestamp']) ? $options['timestamp'] : RPOS_Timezone::current_utc_mysql();
        $notes = isset($options['notes']) ? sanitize_text_field($options['notes']) : '';
        
        // Get new status from event
        $new_status = self::EVENT_STATUS_MAP[$event];
        $old_status = $order->order_status;
        
        // Build update data with proper format specifiers
        $update_data = array(
            'order_status' => $new_status,
            'updated_at' => $timestamp
        );
        
        $update_formats = array('%s', '%s'); // order_status, updated_at
        
        // Set appropriate timestamp field based on event
        $timestamp_field = self::EVENT_TIMESTAMP_MAP[$event];
        if ($timestamp_field && $timestamp_field !== 'created_at') {
            $update_data[$timestamp_field] = $timestamp;
            $update_formats[] = '%s'; // datetime fields use %s
        }
        
        // For rider assignment, also track rider_id in deliveries table
        if ($event === self::EVENT_RIDER_ASSIGNED && $rider_id) {
            $wpdb->update(
                $wpdb->prefix . 'zaikon_deliveries',
                array('assigned_rider_id' => $rider_id),
                array('order_id' => $order_id),
                array('%d'),
                array('%d')
            );
        }
        
        // Update order in database with explicit format specifiers
        $updated = $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            $update_data,
            array('id' => $order_id),
            $update_formats,
            array('%d')
        );
        
        if ($updated === false) {
            $result['message'] = 'Failed to update order: ' . $wpdb->last_error;
            error_log('ZAIKON EVENTS [ERROR]: ' . $result['message']);
            return $result;
        }
        
        // Log event to audit trail
        self::log_event_audit($order_id, $event, $old_status, $new_status, $source, $user_id, $notes);
        
        // Build success result
        $result['success'] = true;
        $result['message'] = 'Event ' . $event . ' dispatched successfully';
        $result['data'] = array(
            'order_id' => $order_id,
            'order_number' => $order->order_number,
            'event' => $event,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'timestamp' => $timestamp,
            'timestamp_field' => $timestamp_field,
            'source' => $source
        );
        
        error_log(sprintf(
            'ZAIKON EVENTS [SUCCESS]: Order #%d (%s) - Event: %s, Status: %s → %s, Source: %s, Timestamp Field: %s',
            $order_id,
            $order->order_number,
            $event,
            $old_status,
            $new_status,
            $source,
            $timestamp_field ?: 'none'
        ));
        
        return $result;
    }
    
    /**
     * Check if an event type is valid
     * 
     * @param string $event Event type to validate
     * @return bool True if valid
     */
    public static function is_valid_event($event) {
        return array_key_exists($event, self::EVENT_STATUS_MAP);
    }
    
    /**
     * Get the status that would result from an event
     * 
     * @param string $event Event type
     * @return string|null Status or null if invalid event
     */
    public static function get_status_for_event($event) {
        return isset(self::EVENT_STATUS_MAP[$event]) ? self::EVENT_STATUS_MAP[$event] : null;
    }
    
    /**
     * Get the timestamp field that would be set by an event
     * 
     * @param string $event Event type
     * @return string|null Timestamp field name or null if invalid event
     */
    public static function get_timestamp_field_for_event($event) {
        return isset(self::EVENT_TIMESTAMP_MAP[$event]) ? self::EVENT_TIMESTAMP_MAP[$event] : null;
    }
    
    /**
     * Log event to audit trail
     * 
     * @param int $order_id Order ID
     * @param string $event Event type
     * @param string $old_status Previous status
     * @param string $new_status New status
     * @param string $source Event source
     * @param int|null $user_id User ID
     * @param string $notes Additional notes
     */
    private static function log_event_audit($order_id, $event, $old_status, $new_status, $source, $user_id, $notes) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zaikon_status_audit',
            array(
                'order_id' => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'source' => $source,
                'actor_user_id' => $user_id,
                'event_type' => $event,
                'notes' => $notes,
                'created_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get event history for an order
     * 
     * @param int $order_id Order ID
     * @param array $options Query options (limit, offset, event_type)
     * @return array Array of event records
     */
    public static function get_event_history($order_id, $options = array()) {
        global $wpdb;
        
        $limit = isset($options['limit']) ? absint($options['limit']) : 50;
        $offset = isset($options['offset']) ? absint($options['offset']) : 0;
        $event_type = isset($options['event_type']) ? sanitize_text_field($options['event_type']) : null;
        
        $where = $wpdb->prepare("order_id = %d", $order_id);
        
        if ($event_type) {
            $where .= $wpdb->prepare(" AND event_type = %s", $event_type);
        }
        
        $events = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}zaikon_status_audit 
             WHERE {$where}
             ORDER BY created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        
        return $events ?: array();
    }
    
    /**
     * Get current lifecycle state for an order
     * Returns all lifecycle timestamps in one query
     * 
     * @param int $order_id Order ID
     * @return object|null Object with all timestamp fields
     */
    public static function get_lifecycle_state($order_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, order_number, order_status, 
                    created_at, confirmed_at, cooking_started_at, ready_at,
                    rider_assigned_at, dispatched_at, delivered_at,
                    updated_at
             FROM {$wpdb->prefix}zaikon_orders
             WHERE id = %d",
            $order_id
        ));
    }
    
    /**
     * Convenience method: Fire cooking started event
     * 
     * @param int $order_id Order ID
     * @param string $source Event source
     * @param int|null $user_id User ID
     * @return array Result
     */
    public static function cooking_started($order_id, $source = self::SOURCE_KDS, $user_id = null) {
        return self::dispatch($order_id, self::EVENT_COOKING_STARTED, array(
            'source' => $source,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Convenience method: Fire kitchen completed event
     * 
     * @param int $order_id Order ID
     * @param string $source Event source
     * @param int|null $user_id User ID
     * @return array Result
     */
    public static function kitchen_completed($order_id, $source = self::SOURCE_KDS, $user_id = null) {
        return self::dispatch($order_id, self::EVENT_KITCHEN_COMPLETED, array(
            'source' => $source,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Convenience method: Fire rider assigned event
     * 
     * @param int $order_id Order ID
     * @param int $rider_id Rider ID
     * @param string $source Event source
     * @param int|null $user_id User ID
     * @return array Result
     */
    public static function rider_assigned($order_id, $rider_id, $source = self::SOURCE_POS, $user_id = null) {
        return self::dispatch($order_id, self::EVENT_RIDER_ASSIGNED, array(
            'source' => $source,
            'user_id' => $user_id,
            'rider_id' => $rider_id
        ));
    }
    
    /**
     * Convenience method: Fire order dispatched event
     * 
     * @param int $order_id Order ID
     * @param string $source Event source
     * @param int|null $user_id User ID
     * @return array Result
     */
    public static function order_dispatched($order_id, $source = self::SOURCE_POS, $user_id = null) {
        return self::dispatch($order_id, self::EVENT_ORDER_DISPATCHED, array(
            'source' => $source,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Convenience method: Fire order delivered event
     * 
     * @param int $order_id Order ID
     * @param string $source Event source
     * @param int|null $user_id User ID
     * @return array Result
     */
    public static function order_delivered($order_id, $source = self::SOURCE_RIDER, $user_id = null) {
        return self::dispatch($order_id, self::EVENT_ORDER_DELIVERED, array(
            'source' => $source,
            'user_id' => $user_id
        ));
    }
}
