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
        
        // Notifications endpoints
        register_rest_route($namespace, '/notifications/unread', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_unread_notifications'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($namespace, '/notifications/mark-read/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_notification_read'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($namespace, '/notifications/mark-all-read', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_all_notifications_read'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Zaikon Delivery Charge Calculation endpoint
        $zaikon_namespace = 'zaikon/v1';
        register_rest_route($zaikon_namespace, '/calc-delivery-charges', array(
            'methods' => 'POST',
            'callback' => array($this, 'zaikon_calc_delivery_charges'),
            'permission_callback' => array($this, 'check_permission')
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
        
        // For delivery orders, use the new Zaikon system
        if (isset($data['order_type']) && $data['order_type'] === 'delivery' && isset($data['is_delivery']) && $data['is_delivery']) {
            return $this->create_delivery_order_v2($data);
        }
        
        // For non-delivery orders, use legacy system
        $order_id = RPOS_Orders::create($data);
        
        if (!$order_id) {
            return new WP_Error('creation_failed', 'Failed to create order', array('status' => 500));
        }
        
        $order = RPOS_Orders::get($order_id);
        return rest_ensure_response($order);
    }
    
    /**
     * Create delivery order using Zaikon v2 system
     */
    private function create_delivery_order_v2($data) {
        // Generate order number
        $order_number = RPOS_Orders::generate_order_number();
        
        // Calculate totals
        $subtotal = floatval($data['subtotal'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);
        $delivery_charge = floatval($data['delivery_charge'] ?? 0);
        
        // Prepare Zaikon order data
        $order_data = array(
            'order_number' => $order_number,
            'order_type' => 'delivery',
            'items_subtotal_rs' => $subtotal,
            'delivery_charges_rs' => $delivery_charge,
            'discounts_rs' => $discount,
            'taxes_rs' => 0, // Can be extended later
            'grand_total_rs' => $subtotal + $delivery_charge - $discount,
            'payment_status' => 'paid',
            'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id())
        );
        
        // Prepare order items
        $items = array();
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $product = RPOS_Products::get($item['product_id']);
                $items[] = array(
                    'product_id' => $item['product_id'],
                    'product_name' => $product ? $product->name : '',
                    'qty' => intval($item['quantity']),
                    'unit_price_rs' => floatval($item['unit_price']),
                    'line_total_rs' => floatval($item['line_total'])
                );
            }
        }
        
        // Get location details
        $location_id = isset($data['area_id']) ? absint($data['area_id']) : null;
        $location = null;
        $location_name = '';
        $distance_km = 0;
        
        // Prefer location details from frontend if provided
        if (isset($data['location_name']) && !empty($data['location_name'])) {
            $location_name = sanitize_text_field($data['location_name']);
        }
        if (isset($data['distance_km'])) {
            $distance_km = floatval($data['distance_km']);
        }
        
        // Fallback to database lookup if not provided
        if (($location_name === '' || $distance_km === 0) && $location_id) {
            $location = Zaikon_Delivery_Locations::get($location_id);
            if ($location) {
                if ($location_name === '') {
                    $location_name = $location->name;
                }
                if ($distance_km === 0) {
                    $distance_km = floatval($location->distance_km);
                }
            }
        }
        
        // Prepare delivery data
        $delivery_data = array(
            'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'location_id' => $location_id,
            'location_name' => $location_name,
            'distance_km' => $distance_km,
            'delivery_charges_rs' => $delivery_charge,
            'is_free_delivery' => isset($data['is_free_delivery']) ? intval($data['is_free_delivery']) : (($delivery_charge == 0) ? 1 : 0),
            'special_instruction' => sanitize_textarea_field($data['special_instructions'] ?? ''),
            'delivery_status' => 'pending'
        );
        
        // Create order atomically using Zaikon_Order_Service
        $result = Zaikon_Order_Service::create_order($order_data, $items, $delivery_data);
        
        if (!$result['success']) {
            $error_msg = !empty($result['errors']) ? implode(', ', $result['errors']) : 'Failed to create order';
            return new WP_Error('creation_failed', $error_msg, array('status' => 500));
        }
        
        // Deduct stock for completed orders
        if (!empty($data['items'])) {
            RPOS_Orders::deduct_stock_for_order($result['order_id'], $data['items']);
        }
        
        // Get the created order
        $order = Zaikon_Orders::get($result['order_id']);
        
        // Add delivery info to response
        if ($result['delivery_id']) {
            $delivery = Zaikon_Deliveries::get($result['delivery_id']);
            $order->delivery = $delivery;
        }
        
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
        
        // Try to get Zaikon locations first (new system)
        $zaikon_locations = Zaikon_Delivery_Locations::get_all($active_only);
        
        if (!empty($zaikon_locations)) {
            // Return Zaikon locations with normalized field names for compatibility
            $areas = array();
            foreach ($zaikon_locations as $location) {
                $area = (array) $location;
                // Add distance_value for backward compatibility with old code
                $area['distance_value'] = $location->distance_km;
                $areas[] = (object) $area;
            }
            return rest_ensure_response($areas);
        }
        
        // Fallback to old RPOS system if no Zaikon locations exist
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
    
    /**
     * Get unread notifications for current user
     */
    public function get_unread_notifications($request) {
        $user_id = get_current_user_id();
        
        $notifications = RPOS_Notifications::get_unread($user_id);
        $count = RPOS_Notifications::get_unread_count($user_id);
        
        return array(
            'notifications' => $notifications,
            'unread_count' => $count
        );
    }
    
    /**
     * Mark notification as read
     */
    public function mark_notification_read($request) {
        $notification_id = $request['id'];
        
        $result = RPOS_Notifications::mark_as_read($notification_id);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to mark notification as read', array('status' => 500));
        }
        
        return array(
            'success' => true,
            'message' => 'Notification marked as read'
        );
    }
    
    /**
     * Mark all notifications as read for current user
     */
    public function mark_all_notifications_read($request) {
        $user_id = get_current_user_id();
        
        $result = RPOS_Notifications::mark_all_as_read($user_id);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to mark notifications as read', array('status' => 500));
        }
        
        return array(
            'success' => true,
            'message' => 'All notifications marked as read'
        );
    }
    
    /**
     * Zaikon: Calculate delivery charges
     * Endpoint: POST /zaikon/v1/calc-delivery-charges
     * Params: distance_km, items_subtotal_rs OR location_id, items_subtotal_rs
     */
    public function zaikon_calc_delivery_charges($request) {
        $params = $request->get_params();
        
        $items_subtotal = floatval($params['items_subtotal_rs'] ?? 0);
        
        if ($items_subtotal <= 0) {
            return new WP_Error('invalid_subtotal', 'Invalid items subtotal', array('status' => 400));
        }
        
        // Calculate by location_id or distance_km
        if (!empty($params['location_id'])) {
            $result = Zaikon_Delivery_Calculator::calculate_by_location(
                intval($params['location_id']),
                $items_subtotal
            );
        } elseif (isset($params['distance_km'])) {
            $distance_km = floatval($params['distance_km']);
            if ($distance_km <= 0) {
                return new WP_Error('invalid_distance', 'Invalid distance', array('status' => 400));
            }
            $result = Zaikon_Delivery_Calculator::calculate($distance_km, $items_subtotal);
        } else {
            return new WP_Error('missing_params', 'Either location_id or distance_km is required', array('status' => 400));
        }
        
        if (isset($result['error'])) {
            return new WP_Error('calculation_error', $result['error'], array('status' => 400));
        }
        
        return rest_ensure_response(array(
            'delivery_charges_rs' => $result['charge_rs'],
            'is_free_delivery' => $result['is_free_delivery'],
            'rule_type' => $result['rule_type']
        ));
    }
}
