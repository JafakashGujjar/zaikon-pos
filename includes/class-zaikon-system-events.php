<?php
/**
 * Zaikon System Events (Audit Log) Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_System_Events {
    
    /**
     * Log an event
     */
    public static function log($entity_type, $entity_id, $action, $metadata = null) {
        global $wpdb;
        
        $metadata_json = null;
        if (!is_null($metadata)) {
            $metadata_json = is_string($metadata) ? $metadata : json_encode($metadata);
        }
        
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
        
        return $result ? $wpdb->insert_id : false;
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
}
