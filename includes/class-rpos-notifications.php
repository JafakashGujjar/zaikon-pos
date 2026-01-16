<?php
/**
 * Notifications Management Class
 * Handles notifications for POS cashiers about kitchen order status changes
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Notifications {
    
    /**
     * Create a notification
     */
    public static function create($user_id, $order_id, $type, $message) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'rpos_notifications',
            array(
                'user_id' => absint($user_id),
                'order_id' => absint($order_id),
                'type' => sanitize_text_field($type),
                'message' => sanitize_text_field($message),
                'is_read' => 0
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );
    }
    
    /**
     * Get unread notifications for a user
     */
    public static function get_unread($user_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, o.order_number, o.order_type
             FROM {$wpdb->prefix}rpos_notifications n
             LEFT JOIN {$wpdb->prefix}rpos_orders o ON n.order_id = o.id
             WHERE n.user_id = %d AND n.is_read = 0
             ORDER BY n.created_at DESC
             LIMIT %d",
            $user_id, $limit
        ));
    }
    
    /**
     * Get all notifications for a user (recent)
     */
    public static function get_all($user_id, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, o.order_number, o.order_type
             FROM {$wpdb->prefix}rpos_notifications n
             LEFT JOIN {$wpdb->prefix}rpos_orders o ON n.order_id = o.id
             WHERE n.user_id = %d
             ORDER BY n.created_at DESC
             LIMIT %d",
            $user_id, $limit
        ));
    }
    
    /**
     * Get unread count for a user
     */
    public static function get_unread_count($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_notifications 
             WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }
    
    /**
     * Mark notification as read
     */
    public static function mark_as_read($notification_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_notifications',
            array('is_read' => 1),
            array('id' => absint($notification_id)),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public static function mark_all_as_read($user_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'rpos_notifications',
            array('is_read' => 1),
            array('user_id' => absint($user_id), 'is_read' => 0),
            array('%d'),
            array('%d', '%d')
        );
    }
    
    /**
     * Delete old notifications (older than 7 days)
     */
    public static function cleanup_old_notifications() {
        global $wpdb;
        
        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}rpos_notifications 
             WHERE created_at < %s",
            $seven_days_ago
        ));
    }
    
    /**
     * Notify cashier and admins about order status change
     * Creates notifications for the cashier who created the order and all restaurant administrators
     */
    public static function notify_order_status_change($order_id, $old_status, $new_status) {
        global $wpdb;
        
        // Get order details
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rpos_orders WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return false;
        }
        
        // Get cashier ID (the user who created the order)
        $cashier_id = $order->cashier_id;
        
        if (!$cashier_id) {
            return false;
        }
        
        // Determine notification type and message
        $type = 'order_status';
        $message = '';
        
        switch ($new_status) {
            case 'accepted':
                $type = 'order_accepted';
                $message = sprintf(__('Order %s has been accepted by kitchen', 'restaurant-pos'), $order->order_number);
                break;
            case 'cooking':
                $type = 'order_cooking';
                $message = sprintf(__('Order %s is now being prepared', 'restaurant-pos'), $order->order_number);
                break;
            case 'ready':
                $type = 'order_ready';
                $message = sprintf(__('Order %s is ready for pickup', 'restaurant-pos'), $order->order_number);
                break;
            case 'completed':
                $type = 'order_completed';
                $message = sprintf(__('Order %s has been completed', 'restaurant-pos'), $order->order_number);
                break;
            default:
                return false;
        }
        
        // Create notification for cashier
        $cashier_result = self::create($cashier_id, $order_id, $type, $message);
        if (!$cashier_result) {
            error_log('RPOS Notifications: Failed to create notification for cashier #' . $cashier_id . ' for order #' . $order_id);
        }
        
        // Also notify all restaurant admins (fetch only IDs for performance)
        $admin_query_args = array(
            'role__in' => array('administrator', 'rpos_restaurant_admin'),
            'number' => 100, // Limit to prevent performance issues with large user bases
            'fields' => array('ID') // Only fetch user IDs for better performance
        );
        
        // Exclude cashier from admin list if valid (to prevent double-notification)
        if ($cashier_id) {
            $admin_query_args['exclude'] = array($cashier_id);
        }
        
        $admin_users = get_users($admin_query_args);
        
        foreach ($admin_users as $admin) {
            $admin_result = self::create($admin->ID, $order_id, $type, $message);
            if (!$admin_result) {
                error_log('RPOS Notifications: Failed to create notification for admin #' . $admin->ID . ' for order #' . $order_id);
            }
        }
        
        return true;
    }
}
