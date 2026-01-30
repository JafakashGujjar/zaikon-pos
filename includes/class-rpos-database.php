<?php
/**
 * Database Helper Class
 * 
 * Enterprise-grade database operations with transaction safety,
 * fault tolerance, and audit logging support.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Database {
    
    /**
     * Transaction nesting level counter
     * Tracks nested transaction calls to prevent premature commits
     */
    private static $transaction_level = 0;
    
    /**
     * Default retry configuration for fault tolerance
     */
    const DEFAULT_MAX_RETRIES = 3;
    const DEFAULT_RETRY_DELAY_MS = 100;
    
    /**
     * Get table name with prefix
     */
    public static function get_table($table) {
        global $wpdb;
        return $wpdb->prefix . 'rpos_' . $table;
    }
    
    /**
     * Get Zaikon table name with prefix
     */
    public static function get_zaikon_table($table) {
        global $wpdb;
        return $wpdb->prefix . 'zaikon_' . $table;
    }
    
    /**
     * Get wpdb instance
     */
    public static function wpdb() {
        global $wpdb;
        return $wpdb;
    }
    
    /**
     * Begin a database transaction with nesting support
     * 
     * Supports nested transactions by tracking transaction level.
     * Only the outermost begin_transaction actually starts the transaction.
     * 
     * @return bool True if transaction started successfully
     */
    public static function begin_transaction() {
        global $wpdb;
        
        self::$transaction_level++;
        
        // Only start actual transaction at level 1
        if (self::$transaction_level === 1) {
            $result = $wpdb->query('START TRANSACTION');
            if ($result === false) {
                self::$transaction_level--;
                self::log_error('begin_transaction', 'Failed to start transaction', $wpdb->last_error);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Commit a database transaction with nesting support
     * 
     * Only the outermost commit actually commits the transaction.
     * 
     * @return bool True if commit was successful
     */
    public static function commit() {
        global $wpdb;
        
        if (self::$transaction_level <= 0) {
            self::log_error('commit', 'Attempted to commit without active transaction');
            return false;
        }
        
        self::$transaction_level--;
        
        // Only commit when we reach level 0
        if (self::$transaction_level === 0) {
            $result = $wpdb->query('COMMIT');
            if ($result === false) {
                self::log_error('commit', 'Failed to commit transaction', $wpdb->last_error);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Rollback a database transaction
     * 
     * Always rolls back the entire transaction regardless of nesting level.
     * 
     * @param string $reason Optional reason for the rollback (for logging)
     * @return bool True if rollback was successful
     */
    public static function rollback($reason = '') {
        global $wpdb;
        
        // Always reset transaction level on rollback
        $was_in_transaction = self::$transaction_level > 0;
        self::$transaction_level = 0;
        
        if (!$was_in_transaction) {
            self::log_error('rollback', 'Attempted to rollback without active transaction');
            return false;
        }
        
        $result = $wpdb->query('ROLLBACK');
        
        if ($reason) {
            self::log_error('rollback', 'Transaction rolled back: ' . $reason);
        }
        
        return $result !== false;
    }
    
    /**
     * Check if currently in a transaction
     * 
     * @return bool True if in an active transaction
     */
    public static function in_transaction() {
        return self::$transaction_level > 0;
    }
    
    /**
     * Get current transaction nesting level
     * 
     * @return int Current transaction level (0 = no transaction)
     */
    public static function get_transaction_level() {
        return self::$transaction_level;
    }
    
    /**
     * Execute a callback within a transaction with automatic rollback on failure
     * 
     * This is the preferred method for executing transactional operations.
     * It handles begin, commit, and rollback automatically.
     * 
     * @param callable $callback The callback to execute within the transaction
     * @param string $operation_name Name of the operation for logging
     * @return array Result array with 'success' and 'data' or 'error' keys
     */
    public static function transaction($callback, $operation_name = 'unknown') {
        $result = array(
            'success' => false,
            'data' => null,
            'error' => null
        );
        
        // Start transaction
        if (!self::begin_transaction()) {
            $result['error'] = 'Failed to start transaction';
            return $result;
        }
        
        try {
            // Execute the callback
            $data = call_user_func($callback);
            
            // Commit the transaction
            if (self::commit()) {
                $result['success'] = true;
                $result['data'] = $data;
            } else {
                self::rollback('Commit failed for ' . $operation_name);
                $result['error'] = 'Failed to commit transaction';
            }
            
        } catch (Exception $e) {
            self::rollback('Exception in ' . $operation_name . ': ' . $e->getMessage());
            $result['error'] = $e->getMessage();
            
            // Log the exception
            self::log_error($operation_name, 'Transaction failed with exception', $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Execute a database operation with retry logic for fault tolerance
     * 
     * Useful for handling transient database errors like deadlocks
     * or connection timeouts.
     * 
     * @param callable $callback The operation to execute
     * @param int $max_retries Maximum number of retry attempts
     * @param int $delay_ms Delay between retries in milliseconds
     * @return mixed Result of the callback or false on failure
     */
    public static function with_retry($callback, $max_retries = self::DEFAULT_MAX_RETRIES, $delay_ms = self::DEFAULT_RETRY_DELAY_MS) {
        $attempts = 0;
        $last_error = null;
        
        while ($attempts < $max_retries) {
            $attempts++;
            
            try {
                $result = call_user_func($callback);
                
                // If callback succeeded (not false), return the result
                if ($result !== false) {
                    return $result;
                }
                
                // Log retry attempt
                if ($attempts < $max_retries) {
                    global $wpdb;
                    $last_error = $wpdb->last_error;
                    self::log_error('with_retry', "Attempt $attempts failed, retrying...", $last_error);
                    usleep($delay_ms * 1000); // Convert ms to microseconds
                }
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                
                if ($attempts < $max_retries) {
                    self::log_error('with_retry', "Attempt $attempts failed with exception, retrying...", $last_error);
                    usleep($delay_ms * 1000);
                }
            }
        }
        
        // All retries exhausted
        self::log_error('with_retry', "All $max_retries attempts failed", $last_error);
        return false;
    }
    
    /**
     * Perform a safe insert operation with validation
     * 
     * @param string $table Table name (without prefix)
     * @param array $data Data to insert
     * @param array $format Optional format array
     * @param bool $use_rpos_prefix Whether to use rpos_ prefix (true) or zaikon_ prefix (false)
     * @return int|false Insert ID on success, false on failure
     */
    public static function safe_insert($table, $data, $format = null, $use_rpos_prefix = true) {
        global $wpdb;
        
        if (empty($data)) {
            self::log_error('safe_insert', 'Empty data provided for insert', $table);
            return false;
        }
        
        $full_table = $use_rpos_prefix ? self::get_table($table) : self::get_zaikon_table($table);
        
        $result = $wpdb->insert($full_table, $data, $format);
        
        if ($result === false) {
            self::log_error('safe_insert', 'Insert failed on ' . $table, $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Perform a safe update operation with validation
     * 
     * @param string $table Table name (without prefix)
     * @param array $data Data to update
     * @param array $where Where clause conditions
     * @param array $format Optional format array for data
     * @param array $where_format Optional format array for where clause
     * @param bool $use_rpos_prefix Whether to use rpos_ prefix (true) or zaikon_ prefix (false)
     * @return int|false Number of rows affected on success, false on failure
     */
    public static function safe_update($table, $data, $where, $format = null, $where_format = null, $use_rpos_prefix = true) {
        global $wpdb;
        
        if (empty($data) || empty($where)) {
            self::log_error('safe_update', 'Empty data or where clause provided', $table);
            return false;
        }
        
        $full_table = $use_rpos_prefix ? self::get_table($table) : self::get_zaikon_table($table);
        
        $result = $wpdb->update($full_table, $data, $where, $format, $where_format);
        
        if ($result === false) {
            self::log_error('safe_update', 'Update failed on ' . $table, $wpdb->last_error);
            return false;
        }
        
        return $result;
    }
    
    /**
     * Lock rows for update to prevent race conditions
     * 
     * Use within a transaction for read-modify-write operations.
     * 
     * @param string $table Table name (without prefix)
     * @param array $where Where conditions as key-value pairs
     * @param bool $use_rpos_prefix Whether to use rpos_ prefix
     * @return object|null Row object or null if not found
     */
    public static function lock_for_update($table, $where, $use_rpos_prefix = true) {
        global $wpdb;
        
        if (!self::in_transaction()) {
            self::log_error('lock_for_update', 'Must be called within a transaction');
            return null;
        }
        
        $full_table = $use_rpos_prefix ? self::get_table($table) : self::get_zaikon_table($table);
        
        $where_clauses = array();
        $where_values = array();
        
        foreach ($where as $column => $value) {
            $where_clauses[] = "`" . esc_sql($column) . "` = %s";
            $where_values[] = $value;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = $wpdb->prepare(
            "SELECT * FROM `{$full_table}` WHERE {$where_sql} FOR UPDATE",
            $where_values
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Validate that required fields are present in data array
     * 
     * @param array $data Data to validate
     * @param array $required_fields List of required field names
     * @return array Array of missing fields (empty if all present)
     */
    public static function validate_required_fields($data, $required_fields) {
        $missing = array();
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Log a database error with context
     * 
     * @param string $operation The operation that failed
     * @param string $message Error message
     * @param string $details Additional details (e.g., MySQL error)
     */
    public static function log_error($operation, $message, $details = '') {
        $log_message = sprintf(
            'RPOS Database [%s]: %s%s',
            $operation,
            $message,
            $details ? ' - Details: ' . $details : ''
        );
        
        error_log($log_message);
    }
    
    /**
     * Log an audit event for traceability
     * 
     * @param string $entity_type Type of entity (order, inventory, delivery, etc.)
     * @param int $entity_id ID of the entity
     * @param string $action Action performed
     * @param array $metadata Additional metadata
     * @param int $user_id Optional user ID (defaults to current user)
     * @return int|false Event ID on success, false on failure
     */
    public static function log_audit($entity_type, $entity_id, $action, $metadata = array(), $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Add user ID to metadata for traceability
        $metadata['user_id'] = $user_id;
        $metadata['timestamp'] = current_time('mysql');
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'zaikon_system_events',
            array(
                'entity_type' => sanitize_text_field($entity_type),
                'entity_id' => absint($entity_id),
                'action' => sanitize_text_field($action),
                'metadata' => wp_json_encode($metadata),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Check database connection health
     * 
     * @return bool True if connection is healthy
     */
    public static function check_connection() {
        global $wpdb;
        
        $result = $wpdb->get_var('SELECT 1');
        return $result === '1';
    }
    
    /**
     * Reset transaction state (for emergency recovery)
     * Should only be used when transaction state is corrupted
     */
    public static function reset_transaction_state() {
        global $wpdb;
        
        // Attempt to rollback any pending transaction
        $wpdb->query('ROLLBACK');
        
        // Reset internal counter
        self::$transaction_level = 0;
        
        self::log_error('reset_transaction_state', 'Transaction state was reset');
    }
}
