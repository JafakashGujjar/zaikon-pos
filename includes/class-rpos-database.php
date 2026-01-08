<?php
/**
 * Database Helper Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Database {
    
    /**
     * Get table name with prefix
     */
    public static function get_table($table) {
        global $wpdb;
        return $wpdb->prefix . 'rpos_' . $table;
    }
    
    /**
     * Get wpdb instance
     */
    public static function wpdb() {
        global $wpdb;
        return $wpdb;
    }
}
