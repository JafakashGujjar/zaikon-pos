<?php
/**
 * Zaikon System Events (Audit Log) Management Class
 * 
 * Enterprise-grade audit logging with full traceability,
 * user tracking, and comprehensive event metadata.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_System_Events {
    
    /**
     * Event severity levels for filtering and alerting
     */
    const SEVERITY_DEBUG = 'debug';
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    
    /**
     * Log an event with full traceability
     * 
     * @param string $entity_type Type of entity (order, delivery, inventory, etc.)
     * @param int $entity_id ID of the entity
     * @param string $action Action performed (create, update, delete, etc.)
     * @param array|string $metadata Additional context data
     * @param string $severity Event severity level
     * @param int|null $user_id User who performed the action (defaults to current user)
     * @return int|false Event ID on success, false on failure
     */
    public static function log($entity_type, $entity_id, $action, $metadata = null, $severity = self::SEVERITY_INFO, $user_id = null) {
        global $wpdb;
        
        // Determine user ID for traceability
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Build comprehensive metadata
        $full_metadata = self::build_metadata($metadata, $user_id, $severity);
        
        $metadata_json = wp_json_encode($full_metadata);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_system_events',
            array(
                'entity_type' => sanitize_text_field($entity_type),
                'entity_id' => absint($entity_id),
                'action' => sanitize_text_field($action),
                'metadata' => $metadata_json,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );
        
        // Log critical events to error log as well
        if ($severity === self::SEVERITY_ERROR || $severity === self::SEVERITY_CRITICAL) {
            error_log(sprintf(
                'ZAIKON [%s] %s.%d.%s: %s',
                strtoupper($severity),
                $entity_type,
                $entity_id,
                $action,
                $metadata_json
            ));
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Build comprehensive metadata with traceability information
     * 
     * @param array|string|null $metadata Original metadata
     * @param int $user_id User ID
     * @param string $severity Event severity
     * @return array Complete metadata array
     */
    private static function build_metadata($metadata, $user_id, $severity) {
        // Convert string metadata to array
        if (is_string($metadata)) {
            $metadata = array('message' => $metadata);
        } elseif (!is_array($metadata)) {
            $metadata = array();
        }
        
        // Add traceability information
        $metadata['_audit'] = array(
            'user_id' => $user_id,
            'user_login' => self::get_user_login($user_id),
            'severity' => $severity,
            'timestamp_utc' => gmdate('Y-m-d H:i:s'),
            'timestamp_local' => current_time('mysql'),
            'ip_address' => self::get_client_ip(),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : 'CLI'
        );
        
        return $metadata;
    }
    
    /**
     * Get user login name for audit
     * 
     * @param int $user_id User ID
     * @return string User login or 'system' for automated operations
     */
    private static function get_user_login($user_id) {
        if ($user_id <= 0) {
            return 'system';
        }
        
        $user = get_userdata($user_id);
        return $user ? $user->user_login : 'unknown';
    }
    
    /**
     * Get client IP address for audit trail
     * 
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field(trim($ip)) ?: 'unknown';
    }
    
    /**
     * Log an error event
     */
    public static function log_error($entity_type, $entity_id, $action, $error_message, $additional_data = array()) {
        $metadata = array_merge(
            array('error' => $error_message),
            $additional_data
        );
        
        return self::log($entity_type, $entity_id, $action, $metadata, self::SEVERITY_ERROR);
    }
    
    /**
     * Log a warning event
     */
    public static function log_warning($entity_type, $entity_id, $action, $warning_message, $additional_data = array()) {
        $metadata = array_merge(
            array('warning' => $warning_message),
            $additional_data
        );
        
        return self::log($entity_type, $entity_id, $action, $metadata, self::SEVERITY_WARNING);
    }
    
    /**
     * Log a transaction event (for financial traceability)
     */
    public static function log_transaction($entity_type, $entity_id, $action, $amount, $additional_data = array()) {
        $metadata = array_merge(
            array(
                'transaction_amount' => $amount,
                'transaction_type' => $action
            ),
            $additional_data
        );
        
        return self::log($entity_type, $entity_id, $action, $metadata, self::SEVERITY_INFO);
    }
    
    /**
     * Log an inventory movement event
     */
    public static function log_inventory_movement($product_id, $change_amount, $reason, $additional_data = array()) {
        $metadata = array_merge(
            array(
                'change_amount' => $change_amount,
                'reason' => $reason,
                'direction' => $change_amount > 0 ? 'in' : 'out'
            ),
            $additional_data
        );
        
        return self::log('inventory', $product_id, 'stock_movement', $metadata, self::SEVERITY_INFO);
    }
    
    /**
     * Get events for an entity
     */
    public static function get_entity_events($entity_type, $entity_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_system_events 
             WHERE entity_type = %s AND entity_id = %d
             ORDER BY created_at DESC",
            $entity_type,
            $entity_id
        ));
    }
    
    /**
     * Get all events with filters
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'entity_type' => '',
            'action' => '',
            'severity' => '',
            'user_id' => '',
            'date_from' => '',
            'date_to' => '',
            'limit' => 100,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['entity_type'])) {
            $where[] = 'entity_type = %s';
            $where_values[] = $args['entity_type'];
        }
        
        if (!empty($args['action'])) {
            $where[] = 'action = %s';
            $where_values[] = $args['action'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        // Filter by severity in metadata (JSON search)
        if (!empty($args['severity'])) {
            // Validate severity against known values
            $valid_severities = array(self::SEVERITY_DEBUG, self::SEVERITY_INFO, self::SEVERITY_WARNING, self::SEVERITY_ERROR, self::SEVERITY_CRITICAL);
            if (in_array($args['severity'], $valid_severities, true)) {
                $where[] = 'metadata LIKE %s';
                $where_values[] = '%"severity":"' . $args['severity'] . '"%';
            }
        }
        
        // Filter by user_id in metadata (JSON search)
        if (!empty($args['user_id'])) {
            $where[] = 'metadata LIKE %s';
            $where_values[] = '%"user_id":' . absint($args['user_id']) . '%';
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}zaikon_system_events 
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY created_at DESC
                  LIMIT %d OFFSET %d";
        
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get event count for reporting
     */
    public static function count($args = array()) {
        global $wpdb;
        
        $where = array('1=1');
        $where_values = array();
        
        if (!empty($args['entity_type'])) {
            $where[] = 'entity_type = %s';
            $where_values[] = $args['entity_type'];
        }
        
        if (!empty($args['action'])) {
            $where[] = 'action = %s';
            $where_values[] = $args['action'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}zaikon_system_events 
                  WHERE " . implode(' AND ', $where);
        
        if (!empty($where_values)) {
            return $wpdb->get_var($wpdb->prepare($query, $where_values));
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Purge old events (for maintenance)
     * 
     * @param int $days_to_keep Number of days of events to keep
     * @return int Number of events deleted
     */
    public static function purge_old_events($days_to_keep = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}zaikon_system_events 
             WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Log the purge action
        self::log('system', 0, 'audit_purge', array(
            'cutoff_date' => $cutoff_date,
            'events_deleted' => $deleted
        ), self::SEVERITY_INFO);
        
        return $deleted;
    }
}
