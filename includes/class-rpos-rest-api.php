<?php
/**
 * REST API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_REST_API {
    
    protected static $_instance = null;
    
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'restaurant-pos/v1';
        
        // Products endpoints
        register_rest_route($namespace, '/products', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_products'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_product'),
                'permission_callback' => array($this, 'check_manage_products_permission')
            )
        ));
        
        register_rest_route($namespace, '/products/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_product'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_product'),
                'permission_callback' => array($this, 'check_manage_products_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_product'),
                'permission_callback' => array($this, 'check_manage_products_permission')
            )
        ));
        
        // Categories endpoints
        register_rest_route($namespace, '/categories', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_categories'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));
        
        // Orders endpoints
        register_rest_route($namespace, '/orders', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_orders'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_order'),
                'permission_callback' => array($this, 'check_process_orders_permission')
            )
        ));
        
        register_rest_route($namespace, '/orders/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_order'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_order_status'),
                'permission_callback' => array($this, 'check_kds_permission')
            )
        ));
        
        // Inventory endpoints
        register_rest_route($namespace, '/inventory/adjust', array(
            'methods' => 'POST',
            'callback' => array($this, 'adjust_inventory'),
            'permission_callback' => array($this, 'check_manage_inventory_permission')
        ));
        
        // Delivery Areas endpoints
        register_rest_route($namespace, '/delivery-areas', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_delivery_areas'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_delivery_area'),
                'permission_callback' => array($this, 'check_manage_settings_permission')
            )
        ));
        
        register_rest_route($namespace, '/delivery-areas/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_delivery_area'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_delivery_area'),
                'permission_callback' => array($this, 'check_manage_settings_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_delivery_area'),
                'permission_callback' => array($this, 'check_manage_settings_permission')
            )
        ));
        
        // Delivery Charge calculation endpoint
        register_rest_route($namespace, '/delivery-charge/(?P<area_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'calculate_delivery_charge'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Delivery Logs endpoints
        register_rest_route($namespace, '/delivery-logs', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_delivery_logs'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_delivery_log'),
                'permission_callback' => array($this, 'check_manage_inventory_permission')
            )
        ));
        
        register_rest_route($namespace, '/delivery-logs/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_delivery_log'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_delivery_log'),
                'permission_callback' => array($this, 'check_manage_inventory_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_delivery_log'),
                'permission_callback' => array($this, 'check_manage_inventory_permission')
            )
        ));
        
        // Rider endpoints
        register_rest_route($namespace, '/riders/update-status', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_rider_delivery_status'),
            'permission_callback' => array($this, 'check_rider_permission')
        ));
        
        register_rest_route($namespace, '/riders/update-km', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_rider_delivery_km'),
            'permission_callback' => array($this, 'check_rider_permission')
        ));
        
        register_rest_route($namespace, '/riders/assign', array(
            'methods' => 'POST',
            'callback' => array($this, 'assign_order_to_rider'),
            'permission_callback' => array($this, 'check_manage_inventory_permission')
        ));
    }
    
    /**
     * Get products
     */
    public function get_products($request) {
        $params = $request->get_params();
        
        $args = array(
            'category_id' => $params['category_id'] ?? 0,
            'is_active' => isset($params['is_active']) ? intval($params['is_active']) : 1,
            'search' => $params['search'] ?? ''
        );
        
        $products = RPOS_Products::get_all($args);
        
        // Filter out ingredients (products with selling_price <= 0) for POS
        // Only show actual menu items that can be sold
        $products = array_filter($products, function($product) {
            return floatval($product->selling_price) > 0;
        });
        
        // Re-index array after filtering
        $products = array_values($products);
        
        // Add inventory data to each product
        foreach ($products as &$product) {
            $inventory = RPOS_Inventory::get_by_product($product->id);
            $product->quantity = $inventory ? $inventory->quantity : 0;
            $product->cost_price = $inventory ? $inventory->cost_price : 0;
        }
        
        return rest_ensure_response($products);
    }
    
    /**
     * Get single product
     */
    public function get_product($request) {
        $id = $request['id'];
        $product = RPOS_Products::get($id);
        
        if (!$product) {
            return new WP_Error('not_found', 'Product not found', array('status' => 404));
        }
        
        $inventory = RPOS_Inventory::get_by_product($id);
        $product->quantity = $inventory ? $inventory->quantity : 0;
        $product->cost_price = $inventory ? $inventory->cost_price : 0;
        
        return rest_ensure_response($product);
    }
    
    /**
     * Create product
     */
    public function create_product($request) {
        $data = $request->get_json_params();
        
        $product_id = RPOS_Products::create($data);
        
        if (!$product_id) {
            return new WP_Error('creation_failed', 'Failed to create product', array('status' => 500));
        }
        
        return rest_ensure_response(array('id' => $product_id));
    }
    
    /**
     * Update product
     */
    public function update_product($request) {
        $id = $request['id'];
        $data = $request->get_json_params();
        
        $result = RPOS_Products::update($id, $data);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update product', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Delete product
     */
    public function delete_product($request) {
        $id = $request['id'];
        
        $result = RPOS_Products::delete($id);
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete product', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get categories
     */
    public function get_categories($request) {
        $categories = RPOS_Categories::get_all();
        return rest_ensure_response($categories);
    }
    
    /**
     * Get orders
     */
    public function get_orders($request) {
        $params = $request->get_params();
        
        $args = array(
            'status' => $params['status'] ?? '',
            'date_from' => $params['date_from'] ?? '',
            'date_to' => $params['date_to'] ?? '',
            'limit' => $params['limit'] ?? 50
        );
        
        $orders = RPOS_Orders::get_all($args);
        return rest_ensure_response($orders);
    }
    
    /**
     * Get single order
     */
    public function get_order($request) {
        $id = $request['id'];
        $order = RPOS_Orders::get($id);
        
        if (!$order) {
            return new WP_Error('not_found', 'Order not found', array('status' => 404));
        }
        
        return rest_ensure_response($order);
    }
    
    /**
     * Create order
     */
    public function create_order($request) {
        $data = $request->get_json_params();
        
        $order_id = RPOS_Orders::create($data);
        
        if (!$order_id) {
            return new WP_Error('creation_failed', 'Failed to create order', array('status' => 500));
        }
        
        $order = RPOS_Orders::get($order_id);
        return rest_ensure_response($order);
    }
    
    /**
     * Update order status
     */
    public function update_order_status($request) {
        global $wpdb;
        
        $id = absint($request['id']);
        $data = $request->get_json_params();
        
        if (!isset($data['status'])) {
            return new WP_Error('missing_status', 'Status is required', array('status' => 400));
        }
        
        $new_status = sanitize_text_field($data['status']);
        
        // Get current order status
        $old_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}rpos_orders WHERE id = %d",
            $id
        ));
        
        if ($old_status === null) {
            return new WP_Error('not_found', 'Order not found', array('status' => 404));
        }
        
        error_log('RPOS REST API: update_order_status called for order #' . $id . ' from "' . $old_status . '" to "' . $new_status . '"');
        
        // Update the order status (this now handles idempotent calls properly)
        $result = RPOS_Orders::update_status($id, $new_status);
        
        if ($result === false) {
            error_log('RPOS REST API: Failed to update order #' . $id . ' status');
            return new WP_Error('update_failed', 'Failed to update order status', array('status' => 500));
        }
        
        // Log kitchen activity only if status actually changed
        if ($old_status !== $new_status) {
            $current_user_id = get_current_user_id();
            
            $activity_data = array(
                'order_id' => $id,
                'user_id' => $current_user_id,
                'old_status' => $old_status,
                'new_status' => $new_status
            );
            $activity_format = array('%d', '%d', '%s', '%s');
            
            // Add delay reason if provided
            if (isset($data['delay_reason']) && !empty($data['delay_reason'])) {
                $activity_data['delay_reason'] = sanitize_textarea_field($data['delay_reason']);
                $activity_format[] = '%s';
            }
            
            $wpdb->insert(
                $wpdb->prefix . 'rpos_kitchen_activity',
                $activity_data,
                $activity_format
            );
            
            error_log('RPOS REST API: Kitchen activity logged for order #' . $id);
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Adjust inventory
     */
    public function adjust_inventory($request) {
        $data = $request->get_json_params();
        
        $new_quantity = RPOS_Inventory::adjust_stock(
            $data['product_id'],
            $data['change_amount'],
            $data['reason'] ?? ''
        );
        
        return rest_ensure_response(array('new_quantity' => $new_quantity));
    }
    
    /**
     * Basic permission check
     */
    public function check_permission($request) {
        return current_user_can('read');
    }
    
    /**
     * Check manage products permission
     */
    public function check_manage_products_permission($request) {
        return current_user_can('rpos_manage_products') || current_user_can('manage_options');
    }
    
    /**
     * Check manage inventory permission
     */
    public function check_manage_inventory_permission($request) {
        return current_user_can('rpos_manage_inventory') || current_user_can('manage_options');
    }
    
    /**
     * Check process orders permission
     */
    public function check_process_orders_permission($request) {
        return current_user_can('rpos_process_orders') || current_user_can('manage_options');
    }
    
    /**
     * Check KDS permission
     */
    public function check_kds_permission($request) {
        return current_user_can('rpos_view_kds') || current_user_can('manage_options');
    }
    
    /**
     * Check manage settings permission
     */
    public function check_manage_settings_permission($request) {
        return current_user_can('rpos_manage_settings') || current_user_can('manage_options');
    }
    
    /**
     * Get delivery areas
     */
    public function get_delivery_areas($request) {
        $params = $request->get_params();
        $active_only = isset($params['active_only']) ? (bool)$params['active_only'] : false;
        
        $areas = RPOS_Delivery_Areas::get_all($active_only);
        return rest_ensure_response($areas);
    }
    
    /**
     * Get single delivery area
     */
    public function get_delivery_area($request) {
        $id = $request['id'];
        $area = RPOS_Delivery_Areas::get($id);
        
        if (!$area) {
            return new WP_Error('not_found', 'Delivery area not found', array('status' => 404));
        }
        
        return rest_ensure_response($area);
    }
    
    /**
     * Create delivery area
     */
    public function create_delivery_area($request) {
        $data = $request->get_json_params();
        
        $area_id = RPOS_Delivery_Areas::create($data);
        
        if (!$area_id) {
            return new WP_Error('creation_failed', 'Failed to create delivery area', array('status' => 500));
        }
        
        return rest_ensure_response(array('id' => $area_id));
    }
    
    /**
     * Update delivery area
     */
    public function update_delivery_area($request) {
        $id = $request['id'];
        $data = $request->get_json_params();
        
        $result = RPOS_Delivery_Areas::update($id, $data);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update delivery area', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Delete delivery area
     */
    public function delete_delivery_area($request) {
        $id = $request['id'];
        
        $result = RPOS_Delivery_Areas::delete($id);
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete delivery area', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Calculate delivery charge
     */
    public function calculate_delivery_charge($request) {
        $area_id = $request['area_id'];
        $params = $request->get_params();
        $subtotal = isset($params['subtotal']) ? floatval($params['subtotal']) : 0;
        
        $minimum_free_delivery = floatval(RPOS_Delivery_Settings::get('minimum_free_delivery_amount', 0));
        
        $charge = RPOS_Delivery_Charges::calculate_charge($subtotal, $area_id, $minimum_free_delivery);
        
        return rest_ensure_response(array(
            'delivery_charge' => $charge,
            'is_free' => $charge == 0 && $subtotal >= $minimum_free_delivery
        ));
    }
    
    /**
     * Get delivery logs
     */
    public function get_delivery_logs($request) {
        $params = $request->get_params();
        
        $args = array(
            'date_from' => $params['date_from'] ?? '',
            'date_to' => $params['date_to'] ?? '',
            'rider_id' => $params['rider_id'] ?? '',
            'bike_id' => $params['bike_id'] ?? '',
            'limit' => $params['limit'] ?? 50
        );
        
        $logs = RPOS_Delivery_Logs::get_all($args);
        return rest_ensure_response($logs);
    }
    
    /**
     * Get single delivery log
     */
    public function get_delivery_log($request) {
        $id = $request['id'];
        $log = RPOS_Delivery_Logs::get($id);
        
        if (!$log) {
            return new WP_Error('not_found', 'Delivery log not found', array('status' => 404));
        }
        
        return rest_ensure_response($log);
    }
    
    /**
     * Create delivery log
     */
    public function create_delivery_log($request) {
        $data = $request->get_json_params();
        
        $log_id = RPOS_Delivery_Logs::create($data);
        
        if (!$log_id) {
            return new WP_Error('creation_failed', 'Failed to create delivery log', array('status' => 500));
        }
        
        return rest_ensure_response(array('id' => $log_id));
    }
    
    /**
     * Update delivery log
     */
    public function update_delivery_log($request) {
        $id = $request['id'];
        $data = $request->get_json_params();
        
        $result = RPOS_Delivery_Logs::update($id, $data);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update delivery log', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Delete delivery log
     */
    public function delete_delivery_log($request) {
        $id = $request['id'];
        
        $result = RPOS_Delivery_Logs::delete($id);
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete delivery log', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Check rider permission
     */
    public function check_rider_permission() {
        $current_user = wp_get_current_user();
        return current_user_can('rpos_view_deliveries') || 
               in_array('delivery_rider', (array) $current_user->roles) ||
               current_user_can('manage_options');
    }
    
    /**
     * Update rider delivery status
     */
    public function update_rider_delivery_status($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['order_id']) || !isset($params['status'])) {
            return new WP_Error('missing_params', 'Order ID and status are required', array('status' => 400));
        }
        
        $order_id = absint($params['order_id']);
        $status = sanitize_text_field($params['status']);
        
        // Validate status
        if (!in_array($status, array('out_for_delivery', 'delivered'))) {
            return new WP_Error('invalid_status', 'Invalid status', array('status' => 400));
        }
        
        $result = RPOS_Riders::update_delivery_status($order_id, $status);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update delivery status', array('status' => 500));
        }
        
        return array(
            'success' => true,
            'message' => 'Delivery status updated successfully'
        );
    }
    
    /**
     * Update rider delivery km
     */
    public function update_rider_delivery_km($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['order_id']) || !isset($params['km'])) {
            return new WP_Error('missing_params', 'Order ID and km are required', array('status' => 400));
        }
        
        $order_id = absint($params['order_id']);
        $km = floatval($params['km']);
        
        if ($km < 0) {
            return new WP_Error('invalid_km', 'Invalid km value', array('status' => 400));
        }
        
        $result = RPOS_Riders::update_delivery_km($order_id, $km);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update delivery km', array('status' => 500));
        }
        
        return array(
            'success' => true,
            'message' => 'Delivery km updated successfully'
        );
    }
    
    /**
     * Assign order to rider
     */
    public function assign_order_to_rider($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['order_id']) || !isset($params['rider_id'])) {
            return new WP_Error('missing_params', 'Order ID and rider ID are required', array('status' => 400));
        }
        
        $order_id = absint($params['order_id']);
        $rider_id = absint($params['rider_id']);
        
        $result = RPOS_Riders::assign_order($order_id, $rider_id);
        
        if ($result === false) {
            return new WP_Error('assign_failed', 'Failed to assign order to rider', array('status' => 500));
        }
        
        return array(
            'success' => true,
            'message' => 'Order assigned to rider successfully'
        );
    }
}
