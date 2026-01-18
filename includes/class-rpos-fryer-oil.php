<?php
/**
 * Fryer Oil Management - Main Module Class
 * Handles initialization and hooks for the fryer oil tracking system
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Fryer_Oil {
    
    protected static $_instance = null;
    
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX endpoints
        add_action('wp_ajax_rpos_check_fryer_alerts', array($this, 'ajax_check_fryer_alerts'));
        add_action('wp_ajax_rpos_get_fryer_batches', array($this, 'ajax_get_fryer_batches'));
        add_action('wp_ajax_rpos_create_fryer_batch', array($this, 'ajax_create_fryer_batch'));
        add_action('wp_ajax_rpos_close_fryer_batch', array($this, 'ajax_close_fryer_batch'));
        add_action('wp_ajax_rpos_get_fryer_products', array($this, 'ajax_get_fryer_products'));
        add_action('wp_ajax_rpos_save_fryer_product', array($this, 'ajax_save_fryer_product'));
        add_action('wp_ajax_rpos_delete_fryer_product', array($this, 'ajax_delete_fryer_product'));
        add_action('wp_ajax_rpos_get_fryers', array($this, 'ajax_get_fryers'));
        add_action('wp_ajax_rpos_save_fryer', array($this, 'ajax_save_fryer'));
        add_action('wp_ajax_rpos_delete_fryer', array($this, 'ajax_delete_fryer'));
    }
    
    /**
     * Check for active fryer alerts (AJAX)
     */
    public function ajax_check_fryer_alerts() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        $alerts = RPOS_Fryer_Reminders::get_active_alerts();
        
        wp_send_json_success(array(
            'alerts' => $alerts,
            'has_alerts' => !empty($alerts)
        ));
    }
    
    /**
     * Get fryer batches (AJAX)
     */
    public function ajax_get_fryer_batches() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        $fryer_id = isset($_POST['fryer_id']) ? absint($_POST['fryer_id']) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
        
        $batches = RPOS_Fryer_Oil_Batches::get_all(array(
            'fryer_id' => $fryer_id,
            'status' => $status
        ));
        
        wp_send_json_success(array('batches' => $batches));
    }
    
    /**
     * Create fryer batch (AJAX)
     */
    public function ajax_create_fryer_batch() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_inventory')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $data = array(
            'batch_name' => sanitize_text_field($_POST['batch_name'] ?? ''),
            'fryer_id' => isset($_POST['fryer_id']) ? absint($_POST['fryer_id']) : null,
            'oil_added_at' => sanitize_text_field($_POST['oil_added_at'] ?? RPOS_Timezone::current_utc_mysql()),
            'oil_capacity' => isset($_POST['oil_capacity']) ? floatval($_POST['oil_capacity']) : null,
            'target_usage_units' => floatval($_POST['target_usage_units'] ?? 120),
            'time_threshold_hours' => absint($_POST['time_threshold_hours'] ?? 24),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'created_by' => get_current_user_id()
        );
        
        $batch_id = RPOS_Fryer_Oil_Batches::create($data);
        
        if ($batch_id) {
            wp_send_json_success(array(
                'message' => 'Batch created successfully',
                'batch_id' => $batch_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create batch'));
        }
    }
    
    /**
     * Close fryer batch (AJAX)
     */
    public function ajax_close_fryer_batch() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_inventory')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $batch_id = absint($_POST['batch_id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        $result = RPOS_Fryer_Oil_Batches::close_batch($batch_id, get_current_user_id(), $notes);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Batch closed successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to close batch'));
        }
    }
    
    /**
     * Get fryer products (AJAX)
     */
    public function ajax_get_fryer_products() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        $products = RPOS_Fryer_Products::get_fryer_products();
        
        wp_send_json_success(array('products' => $products));
    }
    
    /**
     * Save fryer product mapping (AJAX)
     */
    public function ajax_save_fryer_product() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_inventory')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $product_id = absint($_POST['product_id'] ?? 0);
        $oil_units = floatval($_POST['oil_units'] ?? 1);
        $fryer_id = isset($_POST['fryer_id']) ? absint($_POST['fryer_id']) : null;
        
        if ($product_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid product ID'));
        }
        
        $result = RPOS_Fryer_Products::add_product($product_id, $oil_units, $fryer_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Product mapping saved'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save product mapping'));
        }
    }
    
    /**
     * Delete fryer product mapping (AJAX)
     */
    public function ajax_delete_fryer_product() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_inventory')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $id = absint($_POST['id'] ?? 0);
        
        $result = RPOS_Fryer_Products::remove_product($id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Product mapping deleted'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete product mapping'));
        }
    }
    
    /**
     * Get fryers (AJAX)
     */
    public function ajax_get_fryers() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        global $wpdb;
        
        $fryers = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_fryers ORDER BY name ASC"
        );
        
        wp_send_json_success(array('fryers' => $fryers));
    }
    
    /**
     * Save fryer (AJAX)
     */
    public function ajax_save_fryer() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_inventory')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Fryer name is required'));
        }
        
        $data = array(
            'name' => $name,
            'description' => $description,
            'is_active' => $is_active
        );
        
        if ($id > 0) {
            // Update existing
            $result = $wpdb->update(
                $wpdb->prefix . 'rpos_fryers',
                $data,
                array('id' => $id),
                array('%s', '%s', '%d'),
                array('%d')
            );
        } else {
            // Create new
            $result = $wpdb->insert(
                $wpdb->prefix . 'rpos_fryers',
                $data,
                array('%s', '%s', '%d')
            );
            $id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Fryer saved successfully',
                'id' => $id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save fryer'));
        }
    }
    
    /**
     * Delete fryer (AJAX)
     */
    public function ajax_delete_fryer() {
        check_ajax_referer('rpos_pos_nonce', 'nonce');
        
        if (!current_user_can('rpos_manage_inventory')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        
        $id = absint($_POST['id'] ?? 0);
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'rpos_fryers',
            array('id' => $id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success(array('message' => 'Fryer deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete fryer'));
        }
    }
}
