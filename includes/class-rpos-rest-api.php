<?php
/**
 * REST API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_REST_API {
    
    protected static $_instance = null;
    
    /**
     * Retry configuration for tracking token generation
     */
    const TRACKING_TOKEN_MAX_RETRIES = 3;
    const TRACKING_TOKEN_RETRY_DELAY_US = 100000; // 100ms in microseconds
    
    /**
     * Status mapping from zaikon/POS statuses to rpos/KDS statuses
     * Used for bidirectional sync between order tables
     */
    const STATUS_MAP_ZAIKON_TO_RPOS = array(
        'active' => 'new',
        'pending' => 'new',
        'confirmed' => 'new',
        'cooking' => 'cooking',
        'ready' => 'ready',
        'dispatched' => 'ready',
        'delivered' => 'completed',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        'replacement' => 'cancelled'
    );
    
    /**
     * Status mapping from rpos/KDS statuses to zaikon/tracking statuses
     * Used for syncing KDS updates to tracking system
     * 
     * Tracking step definitions (see templates/tracking-page.php line ~929):
     * - Step 1: Confirmed (pending/confirmed status)
     * - Step 2: Cooking (cooking status with 20-min countdown)
     * - Step 3: Dispatched (dispatched/ready status with 10-min delivery countdown)
     */
    const STATUS_MAP_RPOS_TO_ZAIKON = array(
        'new' => 'pending',            // Step 1: Order confirmed
        'cooking' => 'cooking',        // Step 2: Kitchen preparing
        'ready' => 'ready',            // Step 3: Food ready (pre-dispatch)
        'completed' => 'dispatched'    // Step 3: KDS complete triggers dispatched for delivery countdown
    );
    
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
        
        // Delivery Areas endpoints (read-only for compatibility)
        register_rest_route($namespace, '/delivery-areas', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_delivery_areas'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));
        
        // Rider endpoints
        register_rest_route($namespace, '/riders/active', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_active_riders'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
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
        
        register_rest_route($namespace, '/assign-rider', array(
            'methods' => 'POST',
            'callback' => array($this, 'assign_rider_to_order'),
            'permission_callback' => array($this, 'check_process_orders_permission')
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
        
        // Zaikon Delivery Areas endpoint
        register_rest_route($zaikon_namespace, '/delivery-areas', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_delivery_areas'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Cashier Sessions endpoints
        register_rest_route($zaikon_namespace, '/sessions/current', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_current_session'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/sessions/open', array(
            'methods' => 'POST',
            'callback' => array($this, 'open_session'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/sessions/(?P<id>\d+)/close', array(
            'methods' => 'POST',
            'callback' => array($this, 'close_session'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/sessions/(?P<id>\d+)/totals', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_session_totals'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Expenses endpoints
        register_rest_route($zaikon_namespace, '/expenses', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_expenses'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_expense'),
                'permission_callback' => array($this, 'check_process_orders_permission')
            )
        ));
        
        register_rest_route($zaikon_namespace, '/expenses/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_expense'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_expense'),
                'permission_callback' => array($this, 'check_process_orders_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_expense'),
                'permission_callback' => array($this, 'check_process_orders_permission')
            )
        ));
        
        // Cashier Orders Management endpoints
        register_rest_route($zaikon_namespace, '/orders/cashier', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cashier_orders'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/payment-status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_order_payment_status'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/order-status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_order_order_status'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        // Mark order as delivered (from cashier view)
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/mark-delivered', array(
            'methods' => 'PUT',
            'callback' => array($this, 'mark_order_delivered'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        // Mark COD as received
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/mark-cod-received', array(
            'methods' => 'PUT',
            'callback' => array($this, 'mark_cod_received'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        // ========== Order Tracking Endpoints ==========
        
        // Public tracking endpoint (no authentication required)
        register_rest_route($zaikon_namespace, '/track/(?P<token>[a-f0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_by_tracking_token'),
            'permission_callback' => '__return_true' // Public endpoint
        ));
        
        // Public tracking by order number (returns full order data for tracking page)
        register_rest_route($zaikon_namespace, '/track/order/(?P<order_number>[a-zA-Z0-9\-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'public_track_by_order_number'),
            'permission_callback' => '__return_true' // Public endpoint
        ));
        
        // Public tracking by phone number (returns list of recent orders for that phone)
        register_rest_route($zaikon_namespace, '/track/phone/(?P<phone>[0-9\+\-\s]{7,20})', array(
            'methods' => 'GET',
            'callback' => array($this, 'public_track_by_phone'),
            'permission_callback' => '__return_true' // Public endpoint
        ));
        
        // Update order tracking status (KDS, POS)
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/tracking-status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_order_tracking_status'),
            'permission_callback' => array($this, 'check_kds_permission')
        ));
        
        // Assign rider with contact info
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/assign-rider-info', array(
            'methods' => 'PUT',
            'callback' => array($this, 'assign_rider_info'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        // Get tracking URL for order
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/tracking-url', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_tracking_url'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Get tracking URL by order number
        register_rest_route($zaikon_namespace, '/orders/by-number/(?P<order_number>[a-zA-Z0-9\-]+)/tracking-url', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_tracking_url_by_number'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Get delivery orders by customer phone number
        register_rest_route($zaikon_namespace, '/orders/by-phone/(?P<phone>[0-9\+\-\s]{7,20})/tracking', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_delivery_orders_by_phone'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Extend cooking ETA
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/extend-eta', array(
            'methods' => 'POST',
            'callback' => array($this, 'extend_cooking_eta'),
            'permission_callback' => array($this, 'check_kds_permission')
        ));
        
        // Get remaining ETA
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/eta', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_remaining_eta'),
            'permission_callback' => '__return_true' // Public for tracking page
        ));
        
        // Cylinder endpoints
        register_rest_route($zaikon_namespace, '/cylinders/consumption', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cylinder_consumption'),
            'permission_callback' => array($this, 'check_manage_inventory_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/cylinders/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cylinder_analytics'),
            'permission_callback' => array($this, 'check_manage_inventory_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/cylinders/(?P<id>\d+)/forecast', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cylinder_forecast'),
            'permission_callback' => array($this, 'check_manage_inventory_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/cylinders/(?P<id>\d+)/refill', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_cylinder_refill'),
            'permission_callback' => array($this, 'check_manage_inventory_permission')
        ));
        
        register_rest_route($zaikon_namespace, '/cylinders/zones', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cylinder_zones'),
            'permission_callback' => array($this, 'check_manage_inventory_permission')
        ));
        
        // Health Check Endpoint (enterprise status monitoring)
        register_rest_route($zaikon_namespace, '/status/health-check', array(
            'methods' => 'GET',
            'callback' => array($this, 'status_health_check'),
            'permission_callback' => array($this, 'check_manage_settings_permission')
        ));
        
        // Auto-Repair Endpoint (fix status/timestamp issues)
        register_rest_route($zaikon_namespace, '/status/auto-repair', array(
            'methods' => 'POST',
            'callback' => array($this, 'status_auto_repair'),
            'permission_callback' => array($this, 'check_manage_settings_permission')
        ));
        
        // Status Audit History Endpoint
        register_rest_route($zaikon_namespace, '/orders/(?P<id>\d+)/status-history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_status_history'),
            'permission_callback' => array($this, 'check_process_orders_permission')
        ));
        
        // Status Transition Stats Endpoint
        register_rest_route($zaikon_namespace, '/status/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status_transition_stats'),
            'permission_callback' => array($this, 'check_manage_settings_permission')
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
     * 
     * Creates orders in both rpos_orders (for KDS) and zaikon_orders (for POS popup)
     * to ensure synchronization across all system components.
     */
    public function create_order($request) {
        $data = $request->get_json_params();
        
        // For delivery orders, use the new Zaikon system
        if (isset($data['order_type']) && $data['order_type'] === 'delivery' && isset($data['is_delivery']) && $data['is_delivery']) {
            return $this->create_delivery_order_v2($data);
        }
        
        // For non-delivery orders (dine-in, takeaway), create in both systems
        // 1. Create in legacy rpos_orders for KDS compatibility
        $order_id = RPOS_Orders::create($data);
        
        if (!$order_id) {
            return new WP_Error('creation_failed', 'Failed to create order', array('status' => 500));
        }
        
        $order = RPOS_Orders::get($order_id);
        
        // 2. Also create in zaikon_orders for POS popup synchronization
        // This ensures orders appear in "My Orders" modal on POS screen
        $this->sync_order_to_zaikon($order, $data);
        
        return rest_ensure_response($order);
    }
    
    /**
     * Sync order from rpos_orders to zaikon_orders table
     * 
     * Creates a corresponding entry in zaikon_orders for orders created through
     * the legacy system, ensuring synchronization across POS, KDS, and Backend.
     * 
     * @param object $order The order object from rpos_orders
     * @param array $data Original order data with items
     * @return int|false The zaikon order ID on success, false on failure
     */
    private function sync_order_to_zaikon($order, $data) {
        if (!$order || !isset($order->order_number)) {
            error_log('ZAIKON: sync_order_to_zaikon - Invalid order object');
            return false;
        }
        
        // Map order data to zaikon_orders format
        // Note: taxes_rs is set from $data if available since rpos_orders may not have tax field
        $zaikon_order_data = array(
            'order_number' => $order->order_number,
            'order_type' => sanitize_text_field($order->order_type ?? $data['order_type'] ?? 'takeaway'),
            'items_subtotal_rs' => floatval($order->subtotal ?? 0),
            'delivery_charges_rs' => floatval($order->delivery_charge ?? 0),
            'discounts_rs' => floatval($order->discount ?? 0),
            'taxes_rs' => floatval($data['tax'] ?? $data['taxes'] ?? $order->tax ?? 0),
            'grand_total_rs' => floatval($order->total ?? 0),
            'payment_type' => sanitize_text_field($order->payment_type ?? $data['payment_type'] ?? 'cash'),
            'payment_status' => sanitize_text_field($order->payment_status ?? $data['payment_status'] ?? 'paid'),
            'order_status' => 'active',
            'special_instructions' => sanitize_textarea_field($order->special_instructions ?? ''),
            'cashier_id' => absint($order->cashier_id ?? get_current_user_id())
        );
        
        // Create in zaikon_orders
        $zaikon_order_id = Zaikon_Orders::create($zaikon_order_data);
        
        if (!$zaikon_order_id) {
            error_log('ZAIKON: sync_order_to_zaikon - Failed to create zaikon order for order #' . $order->order_number);
            return false;
        }
        
        // Pre-fetch product names for items that don't have them to avoid N+1 queries
        $product_ids_missing_names = array();
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                if (empty($item['product_name']) && !empty($item['product_id'])) {
                    $product_ids_missing_names[] = absint($item['product_id']);
                }
            }
        }
        
        $product_names_cache = array();
        if (!empty($product_ids_missing_names)) {
            // Bulk fetch product names
            foreach (array_unique($product_ids_missing_names) as $product_id) {
                $product = RPOS_Products::get($product_id);
                if ($product) {
                    $product_names_cache[$product_id] = $product->name;
                }
            }
        }
        
        // Create order items in zaikon_order_items
        $items_failed = 0;
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $product_id = absint($item['product_id']);
                $product_name = isset($item['product_name']) ? sanitize_text_field($item['product_name']) : '';
                
                // Use cached product name if not provided
                if (empty($product_name) && isset($product_names_cache[$product_id])) {
                    $product_name = $product_names_cache[$product_id];
                }
                
                $item_data = array(
                    'order_id' => $zaikon_order_id,
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'qty' => absint($item['quantity']),
                    'unit_price_rs' => floatval($item['unit_price']),
                    'line_total_rs' => floatval($item['line_total'])
                );
                
                $item_result = Zaikon_Order_Items::create($item_data);
                if (!$item_result) {
                    $items_failed++;
                    error_log('ZAIKON: sync_order_to_zaikon - Failed to create item for product #' . $product_id . ' in order #' . $order->order_number);
                }
            }
        }
        
        // Log any item creation failures (order still synced but may be incomplete)
        if ($items_failed > 0) {
            error_log('ZAIKON: sync_order_to_zaikon - ' . $items_failed . ' item(s) failed to sync for order #' . $order->order_number);
        }
        
        // Generate tracking token at order creation (enterprise requirement)
        // This ensures all orders are trackable immediately after creation
        // Use retry mechanism for fault tolerance with exception handling
        $tracking_token = null;
        for ($attempt = 1; $attempt <= self::TRACKING_TOKEN_MAX_RETRIES; $attempt++) {
            try {
                $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($zaikon_order_id);
                if ($tracking_token !== null && $tracking_token !== '' && $tracking_token !== false) {
                    break; // Successfully generated token
                }
                
                if ($attempt < self::TRACKING_TOKEN_MAX_RETRIES) {
                    error_log('ZAIKON: sync_order_to_zaikon - Tracking token generation attempt ' . $attempt . ' failed for order #' . $order->order_number . ' (ID: ' . $zaikon_order_id . '), retrying...');
                    usleep(self::TRACKING_TOKEN_RETRY_DELAY_US);
                }
            } catch (Exception $e) {
                error_log('ZAIKON: sync_order_to_zaikon - Tracking token generation attempt ' . $attempt . ' threw exception for order #' . $order->order_number . ' (ID: ' . $zaikon_order_id . '): ' . $e->getMessage());
                if ($attempt < self::TRACKING_TOKEN_MAX_RETRIES) {
                    usleep(self::TRACKING_TOKEN_RETRY_DELAY_US);
                }
            }
        }
        
        if ($tracking_token !== null && $tracking_token !== '' && $tracking_token !== false) {
            error_log('ZAIKON: sync_order_to_zaikon - Generated tracking token for order #' . $order->order_number . ' (ID: ' . $zaikon_order_id . ')');
        } else {
            // Log as critical warning but allow order creation to succeed
            // Tracking token can be generated lazily when tracking URL is requested
            error_log('ZAIKON: sync_order_to_zaikon - CRITICAL: Failed to generate tracking token for order #' . $order->order_number . ' (ID: ' . $zaikon_order_id . ') after ' . self::TRACKING_TOKEN_MAX_RETRIES . ' attempts');
        }
        
        error_log('ZAIKON: sync_order_to_zaikon - Successfully synced order #' . $order->order_number . ' to zaikon_orders (ID: ' . $zaikon_order_id . ')');
        
        return $zaikon_order_id;
    }
    
    /**
     * Create delivery order using Zaikon v2 system
     */
    private function create_delivery_order_v2($data) {
        // Add debug logging (mask sensitive data for security)
        // For privacy, only show first 1-2 chars of name/phone with ***
        $mask_string = function($str) {
            if (empty($str)) return '';
            $len = strlen($str);
            if ($len <= 2) return '***'; // Fully mask very short strings
            return substr($str, 0, 2) . '***'; // Show max 2 chars for longer strings
        };
        
        error_log('ZAIKON: Creating delivery order v2 with data: ' . print_r(array(
            'customer_name' => isset($data['customer_name']) ? $mask_string($data['customer_name']) : '',
            'customer_phone' => isset($data['customer_phone']) ? $mask_string($data['customer_phone']) : '',
            'location_name' => $data['location_name'] ?? '',
            'area_id' => $data['area_id'] ?? '',
            'distance_km' => $data['distance_km'] ?? 0,
            'delivery_charge' => $data['delivery_charge'] ?? 0,
            'items_count' => count($data['items'] ?? [])
        ), true));
        
        // Generate order number
        $order_number = RPOS_Orders::generate_order_number();
        
        // Calculate totals
        $subtotal = floatval($data['subtotal'] ?? 0);
        $discount = floatval($data['discount'] ?? 0);
        $delivery_charge = floatval($data['delivery_charge'] ?? 0);
        
        // Prepare Zaikon order data
        $payment_type = sanitize_text_field($data['payment_type'] ?? 'cash');
        $payment_status = ($payment_type === 'cod') ? 'unpaid' : 'paid';
        
        $order_data = array(
            'order_number' => $order_number,
            'order_type' => 'delivery',
            'items_subtotal_rs' => $subtotal,
            'delivery_charges_rs' => $delivery_charge,
            'discounts_rs' => $discount,
            'taxes_rs' => 0, // Can be extended later
            'grand_total_rs' => $subtotal + $delivery_charge - $discount,
            'payment_type' => $payment_type,
            'payment_status' => $payment_status,
            'order_status' => 'active',
            'special_instructions' => sanitize_textarea_field($data['special_instructions'] ?? ''),
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
        $location_name = isset($data['location_name']) ? sanitize_text_field($data['location_name']) : '';
        $distance_km = isset($data['distance_km']) ? floatval($data['distance_km']) : 0;
        
        // If location_name or distance not provided, fetch from database
        if ((!$location_name || !$distance_km) && $location_id) {
            $location = Zaikon_Delivery_Locations::get($location_id);
            if ($location) {
                if (!$location_name) {
                    $location_name = $location->name;
                }
                if (!$distance_km) {
                    $distance_km = floatval($location->distance_km);
                }
            }
        }
        
        // Prepare delivery data
        $kitchen_instructions = sanitize_textarea_field($data['special_instructions'] ?? '');
        $delivery_instructions = sanitize_textarea_field($data['delivery_instructions'] ?? '');
        $delivery_data = array(
            'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'location_id' => $location_id,
            'location_name' => $location_name,
            'distance_km' => $distance_km,
            'delivery_charges_rs' => $delivery_charge,
            'is_free_delivery' => ($delivery_charge == 0) ? 1 : 0,
            'special_instruction' => $delivery_instructions, // For backward compatibility
            'delivery_instructions' => $delivery_instructions,
            'delivery_status' => 'pending',
            'assigned_rider_id' => (isset($data['rider_id']) && $data['rider_id'] > 0) ? absint($data['rider_id']) : null
        );
        
        // Create order atomically using Zaikon_Order_Service
        error_log('ZAIKON: About to create order with order_data: ' . json_encode(array(
            'order_number' => $order_data['order_number'],
            'order_type' => $order_data['order_type'],
            'items_subtotal_rs' => $order_data['items_subtotal_rs'],
            'delivery_charges_rs' => $order_data['delivery_charges_rs'],
            'grand_total_rs' => $order_data['grand_total_rs'],
            'payment_type' => $order_data['payment_type'],
            'payment_status' => $order_data['payment_status'],
            'order_status' => $order_data['order_status'],
            'items_count' => count($items)
        )));
        
        $result = Zaikon_Order_Service::create_order($order_data, $items, $delivery_data);
        
        if (!$result['success']) {
            $error_msg = !empty($result['errors']) ? implode(', ', $result['errors']) : 'Failed to create order';
            error_log('ZAIKON: Delivery order creation failed: ' . $error_msg);
            return new WP_Error('creation_failed', $error_msg, array('status' => 500));
        }
        
        // Log successful creation
        error_log('ZAIKON: Delivery order created successfully - Order ID: ' . $result['order_id'] . ', Delivery ID: ' . ($result['delivery_id'] ?? 'N/A'));
        
        // Also create in legacy rpos_orders for KDS compatibility
        $legacy_order_data = array(
            'order_number' => $order_number,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal + $delivery_charge - $discount,
            'status' => 'new',
            'order_type' => 'delivery',
            'special_instructions' => $kitchen_instructions, // Kitchen instructions only
            'items' => $data['items'],
            'cashier_id' => absint($data['cashier_id'] ?? get_current_user_id()),
            'is_delivery' => true,
            'delivery_charge' => $delivery_charge,
            'area_id' => $location_id,
            'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? '')
        );
        $legacy_order_id = RPOS_Orders::create($legacy_order_data);
        
        if (!$legacy_order_id) {
            error_log('ZAIKON: Warning - Failed to create legacy order in rpos_orders for KDS. Order ID: ' . $result['order_id']);
            // Deduct stock directly for the Zaikon order even though legacy order creation failed
            // This ensures inventory is properly tracked even if KDS integration has issues
            if (!empty($data['items'])) {
                error_log('ZAIKON: Deducting stock directly for order ID: ' . $result['order_id']);
                RPOS_Inventory::deduct_for_order($result['order_id'], $data['items']);
                RPOS_Recipes::deduct_ingredients_for_order($result['order_id'], $data['items']);
            }
        } else {
            error_log('ZAIKON: Legacy order created in rpos_orders for KDS. Legacy Order ID: ' . $legacy_order_id);
            // Deduct stock for completed orders using legacy order ID
            // The legacy_order_id is used because has_ingredients_deducted and mark_ingredients_deducted
            // check/update the rpos_orders table
            if (!empty($data['items'])) {
                RPOS_Orders::deduct_stock_for_order($legacy_order_id, $data['items']);
            }
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
        
        // Sync status to zaikon_orders table for tracking functionality
        // Map KDS status to tracking status using class constant
        if (isset(self::STATUS_MAP_RPOS_TO_ZAIKON[$new_status])) {
            $tracking_status = self::STATUS_MAP_RPOS_TO_ZAIKON[$new_status];
            
            // Get the order number from rpos_orders to find matching zaikon_orders
            $order_number = $wpdb->get_var($wpdb->prepare(
                "SELECT order_number FROM {$wpdb->prefix}rpos_orders WHERE id = %d",
                $id
            ));
            
            if ($order_number) {
                // Find corresponding zaikon_orders record
                $zaikon_order_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}zaikon_orders WHERE order_number = %s",
                    $order_number
                ));
                
                if ($zaikon_order_id) {
                    // Update zaikon_orders with tracking status and timestamps
                    $sync_result = Zaikon_Order_Tracking::update_status($zaikon_order_id, $tracking_status);
                    if (is_wp_error($sync_result)) {
                        error_log('RPOS REST API: FAILED to sync status to zaikon_orders #' . $zaikon_order_id . ' -> ' . $tracking_status . '. Error: ' . $sync_result->get_error_message());
                    } else {
                        error_log('RPOS REST API: Synced status to zaikon_orders #' . $zaikon_order_id . ' -> ' . $tracking_status);
                    }
                } else {
                    error_log('RPOS REST API: WARNING - No matching zaikon_orders record found for order_number: ' . $order_number . ' (KDS order #' . $id . ')');
                }
            } else {
                error_log('RPOS REST API: WARNING - Could not get order_number for rpos_orders #' . $id);
            }
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
            
            // Trigger notification for cashier and admins when order is ready
            if ($new_status === 'ready') {
                RPOS_Notifications::notify_order_status_change($id, $old_status, $new_status);
                error_log('RPOS REST API: Notification triggered for order #' . $id . ' ready status');
            }
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
        
        // Get Zaikon delivery locations (new system only)
        $zaikon_locations = Zaikon_Delivery_Locations::get_all($active_only);
        
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
        global $wpdb;
        
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
        
        // Sync status to zaikon_orders for tracking
        // Get the order number from rpos_orders to find matching zaikon_orders
        $order_number = $wpdb->get_var($wpdb->prepare(
            "SELECT order_number FROM {$wpdb->prefix}rpos_orders WHERE id = %d",
            $order_id
        ));
        
        if ($order_number) {
            // Find corresponding zaikon_orders record
            $zaikon_order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}zaikon_orders WHERE order_number = %s",
                $order_number
            ));
            
            if ($zaikon_order_id) {
                // Map rider status to tracking status
                $tracking_status = ($status === 'out_for_delivery') ? 'dispatched' : 'delivered';
                Zaikon_Order_Tracking::update_status($zaikon_order_id, $tracking_status);
                error_log('RPOS REST API: Synced rider status to tracking for order #' . $zaikon_order_id . ' -> ' . $tracking_status);
            }
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
        
        // Get distance_km for the response
        $distance_km = 0;
        if (!empty($params['location_id'])) {
            $location = Zaikon_Delivery_Locations::get(intval($params['location_id']));
            if ($location) {
                $distance_km = floatval($location->distance_km);
            }
        } elseif (isset($params['distance_km'])) {
            $distance_km = floatval($params['distance_km']);
        }
        
        return rest_ensure_response(array(
            'delivery_charges_rs' => $result['charge_rs'],
            'is_free_delivery' => $result['is_free_delivery'],
            'rule_type' => $result['rule_type'],
            'distance_km' => $distance_km
        ));
    }
    
    /**
     * Get active riders with workload info
     */
    public function get_active_riders($request) {
        $riders = Zaikon_Riders::get_all(true); // Active riders only
        
        // Add workload info for each rider
        foreach ($riders as &$rider) {
            $pending_deliveries = Zaikon_Rider_Orders::get_pending_for_rider($rider->id);
            $rider->pending_deliveries = count($pending_deliveries);
        }
        
        return rest_ensure_response($riders);
    }
    
    /**
     * Assign rider to order (new unified endpoint)
     */
    public function assign_rider_to_order($request) {
        $params = json_decode($request->get_body(), true);
        
        $order_id = isset($params['order_id']) ? absint($params['order_id']) : 0;
        $rider_id = isset($params['rider_id']) ? absint($params['rider_id']) : 0;
        $notes = isset($params['notes']) ? sanitize_textarea_field($params['notes']) : null;
        
        if (!$order_id || !$rider_id) {
            return new WP_Error('invalid_params', 'Order ID and Rider ID are required', array('status' => 400));
        }
        
        $result = Zaikon_Order_Service::assign_rider_to_order($order_id, $rider_id, $notes);
        
        if ($result['success']) {
            return rest_ensure_response($result);
        } else {
            return new WP_Error('assignment_failed', $result['message'], array('status' => 500));
        }
    }
    
    /**
     * Get current cashier session
     */
    public function get_current_session($request) {
        $session = Zaikon_Cashier_Sessions::get_active_session();
        
        if (!$session) {
            return rest_ensure_response(array(
                'session' => null,
                'has_active_session' => false
            ));
        }
        
        return rest_ensure_response(array(
            'session' => $session,
            'has_active_session' => true
        ));
    }
    
    /**
     * Open new cashier session
     */
    public function open_session($request) {
        $params = $request->get_json_params();
        
        // Check if there's already an active session
        $existing_session = Zaikon_Cashier_Sessions::get_active_session();
        if ($existing_session) {
            return new WP_Error('session_exists', 'You already have an active session', array('status' => 400));
        }
        
        $session_id = Zaikon_Cashier_Sessions::create($params);
        
        if (!$session_id) {
            return new WP_Error('creation_failed', 'Failed to create session', array('status' => 500));
        }
        
        $session = Zaikon_Cashier_Sessions::get($session_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'session' => $session
        ));
    }
    
    /**
     * Close cashier session
     */
    public function close_session($request) {
        $session_id = $request['id'];
        $params = $request->get_json_params();
        
        // Get session totals
        $totals = Zaikon_Cashier_Sessions::calculate_session_totals($session_id);
        
        if ($totals === false) {
            return new WP_Error('not_found', 'Session not found', array('status' => 404));
        }
        
        // Merge calculated totals with provided data
        $closing_data = array(
            'expected_cash_rs' => $totals['expected_cash'],
            'total_cash_sales_rs' => $totals['cash_sales'],
            'total_cod_collected_rs' => $totals['cod_collected'],
            'total_expenses_rs' => $totals['expenses'],
            'closing_cash_rs' => isset($params['closing_cash_rs']) ? floatval($params['closing_cash_rs']) : $totals['expected_cash'],
            'notes' => isset($params['notes']) ? $params['notes'] : ''
        );
        
        // Calculate difference
        $closing_data['cash_difference_rs'] = $closing_data['closing_cash_rs'] - $closing_data['expected_cash_rs'];
        
        $result = Zaikon_Cashier_Sessions::close_session($session_id, $closing_data);
        
        if (!$result) {
            return new WP_Error('close_failed', 'Failed to close session', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'totals' => $totals,
            'closing_data' => $closing_data
        ));
    }
    
    /**
     * Get session totals
     */
    public function get_session_totals($request) {
        $session_id = $request['id'];
        
        $totals = Zaikon_Cashier_Sessions::calculate_session_totals($session_id);
        
        if ($totals === false) {
            return new WP_Error('not_found', 'Session not found', array('status' => 404));
        }
        
        return rest_ensure_response($totals);
    }
    
    /**
     * Get expenses
     */
    public function get_expenses($request) {
        $params = $request->get_params();
        
        if (isset($params['session_id'])) {
            $expenses = Zaikon_Expenses::get_by_session($params['session_id']);
        } else {
            $cashier_id = isset($params['cashier_id']) ? absint($params['cashier_id']) : get_current_user_id();
            $start_date = $params['start_date'] ?? null;
            $end_date = $params['end_date'] ?? null;
            $expenses = Zaikon_Expenses::get_by_cashier($cashier_id, $start_date, $end_date);
        }
        
        return rest_ensure_response($expenses);
    }
    
    /**
     * Create expense
     */
    public function create_expense($request) {
        $params = $request->get_json_params();
        
        // Verify session is active
        $session_id = absint($params['session_id'] ?? 0);
        if (!$session_id) {
            return new WP_Error('invalid_session', 'Session ID is required', array('status' => 400));
        }
        
        $session = Zaikon_Cashier_Sessions::get($session_id);
        if (!$session || $session->status !== 'open') {
            return new WP_Error('invalid_session', 'Session is not active', array('status' => 400));
        }
        
        $expense_id = Zaikon_Expenses::create($params);
        
        if (!$expense_id) {
            return new WP_Error('creation_failed', 'Failed to create expense', array('status' => 500));
        }
        
        $expense = Zaikon_Expenses::get($expense_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'expense' => $expense
        ));
    }
    
    /**
     * Get single expense
     */
    public function get_expense($request) {
        $expense_id = $request['id'];
        $expense = Zaikon_Expenses::get($expense_id);
        
        if (!$expense) {
            return new WP_Error('not_found', 'Expense not found', array('status' => 404));
        }
        
        return rest_ensure_response($expense);
    }
    
    /**
     * Update expense
     */
    public function update_expense($request) {
        $expense_id = $request['id'];
        $params = $request->get_json_params();
        
        $result = Zaikon_Expenses::update($expense_id, $params);
        
        if (!$result) {
            return new WP_Error('update_failed', 'Failed to update expense', array('status' => 500));
        }
        
        $expense = Zaikon_Expenses::get($expense_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'expense' => $expense
        ));
    }
    
    /**
     * Delete expense
     */
    public function delete_expense($request) {
        $expense_id = $request['id'];
        
        $result = Zaikon_Expenses::delete($expense_id);
        
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete expense', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Expense deleted successfully'
        ));
    }
    
    /**
     * Get orders for cashier
     */
    public function get_cashier_orders($request) {
        global $wpdb;
        
        $params = $request->get_params();
        $cashier_id = isset($params['cashier_id']) ? absint($params['cashier_id']) : get_current_user_id();
        $date = $params['date'] ?? RPOS_Timezone::now()->format('Y-m-d');
        
        // Convert local date to UTC date range for proper timezone handling
        // The database stores timestamps in UTC, so we need to convert local date boundaries to UTC
        $utc_timezone = new DateTimeZone('UTC');
        $local_start = new DateTime($date . ' 00:00:00', RPOS_Timezone::get_timezone());
        $local_end = new DateTime($date . ' 23:59:59', RPOS_Timezone::get_timezone());
        
        // Convert to UTC for database query
        $local_start->setTimezone($utc_timezone);
        $local_end->setTimezone($utc_timezone);
        
        $utc_start = $local_start->format('Y-m-d H:i:s');
        $utc_end = $local_end->format('Y-m-d H:i:s');
        
        // Get orders for cashier on specified date (using UTC range)
        // SECURITY: Exclude customer_name and customer_phone from cashier view
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT o.id, o.order_number, o.order_type, o.payment_type, o.payment_status, 
                    o.order_status, o.items_subtotal_rs, o.delivery_charges_rs, o.discounts_rs, 
                    o.grand_total_rs, o.created_at, o.cashier_id,
                    d.assigned_rider_id, d.delivery_status, r.name as rider_name
             FROM {$wpdb->prefix}zaikon_orders o
             LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON o.id = d.order_id
             LEFT JOIN {$wpdb->prefix}zaikon_riders r ON d.assigned_rider_id = r.id
             WHERE o.cashier_id = %d 
             AND o.created_at >= %s
             AND o.created_at <= %s
             ORDER BY o.created_at DESC",
            $cashier_id,
            $utc_start,
            $utc_end
        ));
        
        return rest_ensure_response($orders);
    }
    
    /**
     * Update order payment status
     */
    public function update_order_payment_status($request) {
        global $wpdb;
        
        $order_id = $request['id'];
        $params = $request->get_json_params();
        
        $payment_status = sanitize_text_field($params['payment_status'] ?? '');
        
        if (!in_array($payment_status, array('unpaid', 'paid', 'cod_pending', 'cod_received', 'refunded', 'void'))) {
            return new WP_Error('invalid_status', 'Invalid payment status', array('status' => 400));
        }
        
        // Prepare update data
        $update_data = array(
            'payment_status' => $payment_status,
            'updated_at' => RPOS_Timezone::current_utc_mysql()
        );
        
        $format = array('%s', '%s');
        
        // Add support for updating payment_type when provided
        $payment_type = sanitize_text_field($params['payment_type'] ?? '');
        if ($payment_type && in_array($payment_type, array('cash', 'online', 'cod'))) {
            $update_data['payment_type'] = $payment_type;
            $format[] = '%s';
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            $update_data,
            array('id' => $order_id),
            $format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update payment status', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Payment status updated successfully'
        ));
    }
    
    /**
     * Update order status (active/completed/cancelled/replacement)
     * 
     * Updates status in zaikon_orders and syncs to rpos_orders for KDS visibility.
     */
    public function update_order_order_status($request) {
        global $wpdb;
        
        $order_id = $request['id'];
        $params = $request->get_json_params();
        
        $order_status = sanitize_text_field($params['order_status'] ?? '');
        
        if (!in_array($order_status, array('active', 'delivered', 'completed', 'cancelled', 'replacement'))) {
            return new WP_Error('invalid_status', 'Invalid order status', array('status' => 400));
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            array(
                'order_status' => $order_status,
                'updated_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array('id' => $order_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update order status', array('status' => 500));
        }
        
        // Sync status to rpos_orders for KDS visibility
        $this->sync_status_to_legacy_order($order_id, $order_status);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Order status updated successfully'
        ));
    }
    
    /**
     * Mark order as delivered
     * 
     * Updates status in zaikon_orders and syncs to rpos_orders for KDS visibility.
     */
    public function mark_order_delivered($request) {
        global $wpdb;
        
        $order_id = absint($request['id']);
        
        // Update zaikon_orders table
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            array(
                'order_status' => 'delivered',
                'updated_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array('id' => $order_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to mark order as delivered', array('status' => 500));
        }
        
        // Sync status to rpos_orders for KDS visibility
        $this->sync_status_to_legacy_order($order_id, 'completed');
        
        // Also update zaikon_deliveries table
        $delivery_result = $wpdb->update(
            $wpdb->prefix . 'zaikon_deliveries',
            array(
                'delivery_status' => 'delivered',
                'delivered_at' => RPOS_Timezone::current_utc_mysql(),
                'updated_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Log warning if delivery update fails, but don't fail the request
        if ($delivery_result === false && $wpdb->last_error) {
            error_log("Failed to update zaikon_deliveries for order {$order_id}: " . $wpdb->last_error);
        }
        
        // Update rider_orders status
        $rider_result = $wpdb->update(
            $wpdb->prefix . 'zaikon_rider_orders',
            array(
                'status' => 'delivered',
                'delivered_at' => RPOS_Timezone::current_utc_mysql(),
                'updated_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array('order_id' => $order_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Log warning if rider_orders update fails, but don't fail the request
        if ($rider_result === false && $wpdb->last_error) {
            error_log("Failed to update zaikon_rider_orders for order {$order_id}: " . $wpdb->last_error);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Order marked as delivered'
        ));
    }
    
    /**
     * Mark COD as received
     */
    public function mark_cod_received($request) {
        global $wpdb;
        
        $order_id = absint($request['id']);
        
        // Verify this is a COD order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return new WP_Error('not_found', 'Order not found', array('status' => 404));
        }
        
        if ($order->payment_type !== 'cod') {
            return new WP_Error('invalid_payment_type', 'This order is not COD', array('status' => 400));
        }
        
        // Update payment status to cod_received and order_status to completed
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_orders',
            array(
                'payment_status' => 'cod_received',
                'order_status' => 'completed',
                'updated_at' => RPOS_Timezone::current_utc_mysql()
            ),
            array('id' => $order_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to mark COD as received', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'COD marked as received'
        ));
    }
    
    /**
     * Get cylinder consumption logs
     */
    public function get_cylinder_consumption($request) {
        $params = $request->get_params();
        $cylinder_id = isset($params['cylinder_id']) ? absint($params['cylinder_id']) : 0;
        $limit = isset($params['limit']) ? absint($params['limit']) : 100;
        
        global $wpdb;
        
        $query = "SELECT c.*, p.name as product_name, o.order_number, 
                         cyl.cylinder_type_id, t.name as cylinder_type
                  FROM {$wpdb->prefix}zaikon_cylinder_consumption c
                  LEFT JOIN {$wpdb->prefix}rpos_products p ON c.product_id = p.id
                  LEFT JOIN {$wpdb->prefix}rpos_orders o ON c.order_id = o.id
                  LEFT JOIN {$wpdb->prefix}rpos_gas_cylinders cyl ON c.cylinder_id = cyl.id
                  LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON cyl.cylinder_type_id = t.id";
        
        if ($cylinder_id > 0) {
            $query .= $wpdb->prepare(" WHERE c.cylinder_id = %d", $cylinder_id);
        }
        
        $query .= $wpdb->prepare(" ORDER BY c.created_at DESC LIMIT %d", $limit);
        
        $consumption = $wpdb->get_results($query);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $consumption
        ));
    }
    
    /**
     * Get cylinder analytics
     */
    public function get_cylinder_analytics($request) {
        $analytics = RPOS_Gas_Cylinders::get_dashboard_analytics();
        $efficiency = RPOS_Gas_Cylinders::get_efficiency_comparison();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'dashboard' => $analytics,
                'efficiency' => $efficiency
            )
        ));
    }
    
    /**
     * Get cylinder forecast
     */
    public function get_cylinder_forecast($request) {
        $cylinder_id = absint($request['id']);
        
        if ($cylinder_id <= 0) {
            return new WP_Error('invalid_id', 'Invalid cylinder ID', array('status' => 400));
        }
        
        $burn_rate = RPOS_Gas_Cylinders::calculate_burn_rate($cylinder_id);
        $cylinder = RPOS_Gas_Cylinders::get_cylinder($cylinder_id);
        
        if (!$cylinder) {
            return new WP_Error('not_found', 'Cylinder not found', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'cylinder' => $cylinder,
                'forecast' => $burn_rate
            )
        ));
    }
    
    /**
     * Process cylinder refill
     */
    public function process_cylinder_refill($request) {
        $cylinder_id = absint($request['id']);
        $params = $request->get_json_params();
        
        if ($cylinder_id <= 0) {
            return new WP_Error('invalid_id', 'Invalid cylinder ID', array('status' => 400));
        }
        
        $refill_data = array(
            'refill_date' => isset($params['refill_date']) ? sanitize_text_field($params['refill_date']) : RPOS_Timezone::now()->format('Y-m-d'),
            'vendor' => isset($params['vendor']) ? sanitize_text_field($params['vendor']) : null,
            'cost' => isset($params['cost']) ? floatval($params['cost']) : 0,
            'quantity' => isset($params['quantity']) ? floatval($params['quantity']) : 1,
            'notes' => isset($params['notes']) ? sanitize_textarea_field($params['notes']) : null
        );
        
        $refill_id = RPOS_Gas_Cylinders::process_refill($cylinder_id, $refill_data);
        
        if (!$refill_id) {
            return new WP_Error('refill_failed', 'Failed to process refill', array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Refill processed successfully',
            'refill_id' => $refill_id
        ));
    }
    
    /**
     * Get cylinder zones
     */
    public function get_cylinder_zones($request) {
        $zones = RPOS_Gas_Cylinders::get_all_zones();
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $zones
        ));
    }
    
    // ========== Order Tracking API Methods ==========
    
    /**
     * Get order by tracking token (public endpoint)
     */
    public function get_order_by_tracking_token($request) {
        $token = $request->get_param('token');
        
        // Log the incoming request for debugging
        $token_preview = strlen($token) >= 12 ? substr($token, 0, 8) . '...' . substr($token, -4) : '***';
        error_log('ZAIKON TRACKING API: /track/{token} endpoint called with token: ' . $token_preview);
        
        // Validate token format (current tokens are 32 chars, allow 16-64 for flexibility)
        if (empty($token) || !preg_match(Zaikon_Order_Tracking::TOKEN_PATTERN, $token)) {
            error_log('ZAIKON TRACKING API: Token validation failed for: ' . $token_preview);
            return new WP_Error('invalid_token', 'Invalid tracking link. Please check your URL.', array('status' => 400));
        }
        
        $order = Zaikon_Order_Tracking::get_order_by_token($token);
        
        if (!$order) {
            error_log('ZAIKON TRACKING API: Order not found for token: ' . $token_preview);
            
            // Fallback: Try direct database lookup to diagnose the issue
            global $wpdb;
            
            // Check if the token exists in the database (exact match)
            $direct_check = $wpdb->get_row($wpdb->prepare(
                "SELECT id, order_number, order_status, tracking_token 
                 FROM {$wpdb->prefix}zaikon_orders 
                 WHERE tracking_token = %s",
                $token
            ));
            
            if ($direct_check) {
                error_log('ZAIKON TRACKING API: DIRECT DB CHECK - Token FOUND! Order ID: ' . $direct_check->id . 
                          ', Order#: ' . $direct_check->order_number . 
                          '. But get_order_by_token returned NULL - possible JOIN issue');
                
                // The token exists but get_order_by_token failed - try getting order by ID
                // Use the centralized method to avoid code duplication
                error_log('ZAIKON TRACKING API: Trying fallback lookup by order ID');
                $order = Zaikon_Order_Tracking::get_order_by_id($direct_check->id);
                
                if ($order) {
                    error_log('ZAIKON TRACKING API: Fallback lookup by ID SUCCESS! Order#: ' . $order->order_number);
                } else {
                    error_log('ZAIKON TRACKING API: Fallback lookup by ID also FAILED');
                }
            } else {
                error_log('ZAIKON TRACKING API: DIRECT DB CHECK - Token NOT in database');
                
                // Check for partial match or similar tokens (debugging)
                $similar = $wpdb->get_results(
                    "SELECT id, order_number, tracking_token, created_at 
                     FROM {$wpdb->prefix}zaikon_orders 
                     WHERE tracking_token IS NOT NULL 
                     ORDER BY created_at DESC 
                     LIMIT 5"
                );
                
                if ($similar) {
                    error_log('ZAIKON TRACKING API: Recent tokens in DB:');
                    foreach ($similar as $s) {
                        $s_preview = strlen($s->tracking_token) >= 12 ? 
                            substr($s->tracking_token, 0, 8) . '...' . substr($s->tracking_token, -4) : '***';
                        error_log('  - Order#: ' . $s->order_number . ', Token: ' . $s_preview . ', Created: ' . $s->created_at);
                    }
                }
            }
            
            if (!$order) {
                return new WP_Error('not_found', 'Order not found. The tracking link may have expired or the order does not exist.', array('status' => 404));
            }
        }
        
        error_log('ZAIKON TRACKING API: Order found successfully! Order#: ' . $order->order_number);
        
        // Get ETA information
        $eta = Zaikon_Order_Tracking::get_remaining_eta($order->id);
        
        return rest_ensure_response(array(
            'success' => true,
            'order' => $order,
            'eta' => $eta
        ));
    }
    
    /**
     * Public tracking by order number (returns full order data)
     * This allows customers to track using their order number
     */
    public function public_track_by_order_number($request) {
        global $wpdb;
        
        $order_number = sanitize_text_field($request->get_param('order_number'));
        
        if (empty($order_number)) {
            return new WP_Error('invalid_order_number', 'Please provide a valid order number.', array('status' => 400));
        }
        
        // Get order with delivery details
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.id, o.order_number, o.order_type, o.items_subtotal_rs, 
                    o.delivery_charges_rs AS order_delivery_charges_rs, o.discounts_rs, 
                    o.taxes_rs, o.grand_total_rs, o.payment_status, o.payment_type, 
                    o.order_status, o.cooking_eta_minutes, o.delivery_eta_minutes,
                    o.confirmed_at, o.cooking_started_at, o.ready_at, o.dispatched_at,
                    o.created_at, o.updated_at, o.tracking_token,
                    d.customer_name, d.customer_phone, d.location_name, 
                    d.delivery_status, d.special_instruction,
                    d.delivery_charges_rs AS delivery_charges_rs,
                    d.delivered_at,
                    r.name AS rider_name, r.phone AS rider_phone, r.vehicle_number AS rider_vehicle
             FROM {$wpdb->prefix}zaikon_orders o
             LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON o.id = d.order_id
             LEFT JOIN {$wpdb->prefix}zaikon_riders r ON d.assigned_rider_id = r.id
             WHERE o.order_number = %s",
            $order_number
        ));
        
        if (!$order) {
            return new WP_Error('not_found', 'Order not found. Please check your order number and try again.', array('status' => 404));
        }
        
        // Get order items
        $order->items = $wpdb->get_results($wpdb->prepare(
            "SELECT product_name, qty, unit_price_rs, line_total_rs
             FROM {$wpdb->prefix}zaikon_order_items
             WHERE order_id = %d
             ORDER BY id ASC",
            $order->id
        ));
        
        // Generate tracking token if it doesn't exist or is invalid
        if (empty($order->tracking_token) || !preg_match(Zaikon_Order_Tracking::TOKEN_PATTERN, $order->tracking_token)) {
            // Token is missing or has invalid format, generate a new one
            $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($order->id);
            if (empty($tracking_token)) {
                return new WP_Error('token_error', 'Failed to generate tracking token. Please try again.', array('status' => 500));
            }
            $order->tracking_token = $tracking_token;
        }
        
        // Get ETA information
        $eta = Zaikon_Order_Tracking::get_remaining_eta($order->id);
        
        return rest_ensure_response(array(
            'success' => true,
            'order' => $order,
            'eta' => $eta,
            'tracking_url' => Zaikon_Order_Tracking::get_tracking_url($order->tracking_token)
        ));
    }
    
    /**
     * Public tracking by phone number (returns list of recent delivery orders)
     * This allows customers to track all their recent orders by phone number
     */
    public function public_track_by_phone($request) {
        global $wpdb;
        
        $phone = sanitize_text_field($request->get_param('phone'));
        
        if (empty($phone) || strlen($phone) < 7) {
            return new WP_Error('invalid_phone', 'Please provide a valid phone number.', array('status' => 400));
        }
        
        // Clean up phone number for matching - keep only digits
        $phone_cleaned = preg_replace('/[^\d]/', '', $phone);
        
        if (strlen($phone_cleaned) < 7) {
            return new WP_Error('invalid_phone', 'Please provide a valid phone number with at least 7 digits.', array('status' => 400));
        }
        
        // Search for recent delivery orders with this phone number (last 30 days)
        // Include items count in the query to avoid N+1 problem
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT o.id, o.order_number, o.order_type, o.order_status, o.tracking_token,
                    o.grand_total_rs, o.created_at, o.items_subtotal_rs,
                    o.delivery_charges_rs AS order_delivery_charges_rs,
                    d.customer_name, d.customer_phone, d.location_name, 
                    d.delivery_status, d.delivery_charges_rs,
                    (SELECT COALESCE(SUM(i.qty), 0) FROM {$wpdb->prefix}zaikon_order_items i WHERE i.order_id = o.id) AS items_count
             FROM {$wpdb->prefix}zaikon_orders o
             INNER JOIN {$wpdb->prefix}zaikon_deliveries d ON o.id = d.order_id
             WHERE REPLACE(REPLACE(REPLACE(d.customer_phone, '-', ''), ' ', ''), '+', '') LIKE %s
             AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY o.created_at DESC
             LIMIT 10",
            '%' . $phone_cleaned . '%'
        ));
        
        if (empty($orders)) {
            return new WP_Error('not_found', 'No delivery orders found for this phone number in the last 30 days.', array('status' => 404));
        }
        
        // Process orders to include tracking URLs
        $result_orders = array();
        foreach ($orders as $order) {
            // Generate tracking token if it doesn't exist or is invalid
            if (empty($order->tracking_token) || !preg_match(Zaikon_Order_Tracking::TOKEN_PATTERN, $order->tracking_token)) {
                $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($order->id);
                // Skip this order if token generation fails
                if (empty($tracking_token)) {
                    error_log('ZAIKON: Skipping order ' . $order->id . ' in public phone tracking - token generation failed');
                    continue;
                }
            } else {
                $tracking_token = $order->tracking_token;
            }
            
            $tracking_url = Zaikon_Order_Tracking::get_tracking_url($tracking_token);
            
            $result_orders[] = array(
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_type' => $order->order_type,
                'order_status' => $order->order_status,
                'delivery_status' => $order->delivery_status,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'location_name' => $order->location_name,
                'grand_total_rs' => $order->grand_total_rs,
                'items_count' => intval($order->items_count ?: 0),
                'created_at' => $order->created_at,
                'tracking_url' => $tracking_url,
                'tracking_token' => $tracking_token
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'orders' => $result_orders,
            'count' => count($result_orders)
        ));
    }
    
    /**
     * Update order tracking status
     */
    public function update_order_tracking_status($request) {
        $order_id = $request->get_param('id');
        $new_status = $request->get_param('status');
        
        if (empty($new_status)) {
            return new WP_Error('missing_param', 'Status is required', array('status' => 400));
        }
        
        $result = Zaikon_Order_Tracking::update_status($order_id, $new_status);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Order status updated successfully'
        ));
    }
    
    /**
     * Assign rider information to order
     */
    public function assign_rider_info($request) {
        $order_id = $request->get_param('id');
        $rider_data = array(
            'rider_name' => $request->get_param('rider_name'),
            'rider_phone' => $request->get_param('rider_phone'),
            'rider_avatar' => $request->get_param('rider_avatar'),
            'rider_id' => $request->get_param('rider_id')
        );
        
        if (empty($rider_data['rider_name']) || empty($rider_data['rider_phone'])) {
            return new WP_Error('missing_param', 'Rider name and phone are required', array('status' => 400));
        }
        
        $result = Zaikon_Order_Tracking::assign_rider($order_id, $rider_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Rider assigned successfully'
        ));
    }
    
    /**
     * Get tracking URL for order
     */
    public function get_order_tracking_url($request) {
        global $wpdb;
        
        $order_id = $request->get_param('id');
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT tracking_token, order_number, order_type, order_status FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return new WP_Error('not_found', 'Order not found', array('status' => 404));
        }
        
        // Generate token if it doesn't exist or is invalid
        if (empty($order->tracking_token) || !preg_match(Zaikon_Order_Tracking::TOKEN_PATTERN, $order->tracking_token)) {
            // Token is missing or has invalid format, generate a new one
            $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($order_id);
            if (empty($tracking_token)) {
                return new WP_Error('token_error', 'Failed to generate tracking token. Please try again.', array('status' => 500));
            }
        } else {
            $tracking_token = $order->tracking_token;
        }
        
        $tracking_url = Zaikon_Order_Tracking::get_tracking_url($tracking_token);
        
        return rest_ensure_response(array(
            'success' => true,
            'tracking_url' => $tracking_url,
            'tracking_token' => $tracking_token,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'order_status' => $order->order_status
        ));
    }
    
    /**
     * Get tracking URL by order number
     */
    public function get_order_tracking_url_by_number($request) {
        global $wpdb;
        
        $order_number = $request->get_param('order_number');
        
        error_log('ZAIKON TRACKING API: get_order_tracking_url_by_number called for order: ' . $order_number);
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT id, tracking_token, order_number, order_type, order_status FROM {$wpdb->prefix}zaikon_orders WHERE order_number = %s",
            $order_number
        ));
        
        if (!$order) {
            error_log('ZAIKON TRACKING API: Order not found: ' . $order_number);
            return new WP_Error('not_found', 'Order not found. Please check the order number.', array('status' => 404));
        }
        
        error_log('ZAIKON TRACKING API: Order found. ID: ' . $order->id . ', existing token: ' . ($order->tracking_token ? substr($order->tracking_token, 0, 8) . '...' : 'NULL'));
        
        // Generate token if it doesn't exist or is invalid
        if (empty($order->tracking_token) || !preg_match(Zaikon_Order_Tracking::TOKEN_PATTERN, $order->tracking_token)) {
            // Token is missing or has invalid format, generate a new one
            error_log('ZAIKON TRACKING API: Generating new token for order ' . $order->id);
            $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($order->id);
            if (empty($tracking_token)) {
                error_log('ZAIKON TRACKING API: Failed to generate token for order ' . $order->id);
                return new WP_Error('token_error', 'Failed to generate tracking token. Please try again.', array('status' => 500));
            }
            error_log('ZAIKON TRACKING API: New token generated: ' . substr($tracking_token, 0, 8) . '...');
            
            // Verify the token was saved correctly by reading it back
            $verify_token = $wpdb->get_var($wpdb->prepare(
                "SELECT tracking_token FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
                $order->id
            ));
            error_log('ZAIKON TRACKING API: Token verification - DB now has: ' . ($verify_token ? substr($verify_token, 0, 8) . '...' : 'NULL'));
            
            if ($verify_token !== $tracking_token) {
                error_log('ZAIKON TRACKING API: WARNING - Token mismatch! Generated: ' . substr($tracking_token, 0, 8) . ', DB has: ' . ($verify_token ? substr($verify_token, 0, 8) : 'NULL'));
                return new WP_Error('token_error', 'Failed to save tracking token. Please try again.', array('status' => 500));
            }
            
            // Final verification: Can we look up the order using this token?
            $verify_order = Zaikon_Order_Tracking::get_order_by_token($tracking_token);
            if (!$verify_order) {
                error_log('ZAIKON TRACKING API: CRITICAL - Token lookup verification FAILED for newly generated token!');
                return new WP_Error('token_error', 'Failed to verify tracking token. Please contact support.', array('status' => 500));
            }
            error_log('ZAIKON TRACKING API: Token lookup verification SUCCESS - Order ' . $verify_order->order_number . ' found');
        } else {
            $tracking_token = $order->tracking_token;
            error_log('ZAIKON TRACKING API: Using existing token: ' . substr($tracking_token, 0, 8) . '...');
            
            // Verify that we can look up the order with the existing token
            $verify_order = Zaikon_Order_Tracking::get_order_by_token($tracking_token);
            if (!$verify_order) {
                error_log('ZAIKON TRACKING API: CRITICAL - Existing token lookup FAILED! Token: ' . substr($tracking_token, 0, 8) . '... Order ID: ' . $order->id);
                
                // Since the existing token doesn't work, regenerate it
                error_log('ZAIKON TRACKING API: Regenerating token since existing token lookup failed');
                $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($order->id);
                if (empty($tracking_token)) {
                    error_log('ZAIKON TRACKING API: Token regeneration also failed');
                    return new WP_Error('token_error', 'Failed to regenerate tracking token. Please try again.', array('status' => 500));
                }
                error_log('ZAIKON TRACKING API: New token generated after failed lookup: ' . substr($tracking_token, 0, 8) . '...');
                
                // Verify the newly regenerated token works
                $verify_new = Zaikon_Order_Tracking::get_order_by_token($tracking_token);
                if (!$verify_new) {
                    error_log('ZAIKON TRACKING API: CRITICAL - Even newly regenerated token lookup FAILED!');
                    return new WP_Error('token_error', 'Failed to verify tracking token. Please contact support.', array('status' => 500));
                }
                error_log('ZAIKON TRACKING API: Regenerated token verification SUCCESS');
            } else {
                error_log('ZAIKON TRACKING API: Existing token lookup SUCCESS - Order ' . $verify_order->order_number . ' found');
            }
        }
        
        $tracking_url = Zaikon_Order_Tracking::get_tracking_url($tracking_token);
        
        return rest_ensure_response(array(
            'success' => true,
            'tracking_url' => $tracking_url,
            'tracking_token' => $tracking_token,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'order_status' => $order->order_status
        ));
    }
    
    /**
     * Get delivery orders by customer phone number
     */
    public function get_delivery_orders_by_phone($request) {
        global $wpdb;
        
        $phone = $request->get_param('phone');
        // Clean phone number - remove spaces and dashes for flexible matching
        $phone_cleaned = preg_replace('/[\s\-]+/', '', $phone);
        
        // Search for delivery orders with this phone number
        // Use REPLACE to normalize phone numbers in database for comparison
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT o.id, o.order_number, o.order_type, o.order_status, o.tracking_token,
                    o.grand_total_rs, o.created_at, d.customer_name, d.customer_phone,
                    d.location_name, d.delivery_status
             FROM {$wpdb->prefix}zaikon_orders o
             INNER JOIN {$wpdb->prefix}zaikon_deliveries d ON o.id = d.order_id
             WHERE REPLACE(REPLACE(d.customer_phone, '-', ''), ' ', '') LIKE %s
             ORDER BY o.created_at DESC
             LIMIT 10",
            '%' . $wpdb->esc_like($phone_cleaned) . '%'
        ));
        
        if (empty($orders)) {
            return new WP_Error('not_found', 'No delivery orders found for this phone number.', array('status' => 404));
        }
        
        // Process orders to include tracking URLs
        $result_orders = array();
        foreach ($orders as $order) {
            // Generate tracking token if it doesn't exist or is invalid
            if (empty($order->tracking_token) || !preg_match(Zaikon_Order_Tracking::TOKEN_PATTERN, $order->tracking_token)) {
                $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($order->id);
                // Skip this order if token generation fails
                if (empty($tracking_token)) {
                    error_log('ZAIKON: Skipping order ' . $order->id . ' in phone tracking - token generation failed');
                    continue;
                }
            } else {
                $tracking_token = $order->tracking_token;
            }
            
            $tracking_url = Zaikon_Order_Tracking::get_tracking_url($tracking_token);
            
            $result_orders[] = array(
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_type' => $order->order_type,
                'order_status' => $order->order_status,
                'delivery_status' => $order->delivery_status,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'location_name' => $order->location_name,
                'grand_total_rs' => $order->grand_total_rs,
                'created_at' => $order->created_at,
                'tracking_url' => $tracking_url,
                'tracking_token' => $tracking_token
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'orders' => $result_orders,
            'count' => count($result_orders)
        ));
    }
    
    /**
     * Extend cooking ETA
     */
    public function extend_cooking_eta($request) {
        $order_id = $request->get_param('id');
        $additional_minutes = $request->get_param('additional_minutes') ?: 5;
        
        $new_eta = Zaikon_Order_Tracking::extend_cooking_eta($order_id, $additional_minutes);
        
        return rest_ensure_response(array(
            'success' => true,
            'new_eta' => $new_eta,
            'message' => 'Cooking ETA extended by ' . $additional_minutes . ' minutes'
        ));
    }
    
    /**
     * Get remaining ETA (public endpoint)
     */
    public function get_remaining_eta($request) {
        $order_id = $request->get_param('id');
        
        $eta = Zaikon_Order_Tracking::get_remaining_eta($order_id);
        
        if (!$eta) {
            return new WP_Error('not_found', 'Order not found', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'eta' => $eta
        ));
    }
    
    /**
     * Sync order status from zaikon_orders to rpos_orders
     * 
     * Maps zaikon status to rpos status and updates the corresponding
     * record in rpos_orders for KDS visibility and synchronization.
     * 
     * @param int $zaikon_order_id The zaikon order ID
     * @param string $status The status to sync
     * @return bool True on success, false on failure
     */
    private function sync_status_to_legacy_order($zaikon_order_id, $status) {
        global $wpdb;
        
        // Get the order number from zaikon_orders
        $order_number = $wpdb->get_var($wpdb->prepare(
            "SELECT order_number FROM {$wpdb->prefix}zaikon_orders WHERE id = %d",
            $zaikon_order_id
        ));
        
        if (!$order_number) {
            error_log('ZAIKON: sync_status_to_legacy_order - Order #' . $zaikon_order_id . ' not found in zaikon_orders');
            return false;
        }
        
        // Map zaikon status to rpos status using class constant
        $rpos_status = isset(self::STATUS_MAP_ZAIKON_TO_RPOS[$status]) 
            ? self::STATUS_MAP_ZAIKON_TO_RPOS[$status] 
            : $status;
        
        // Find matching rpos_order by order_number
        $rpos_order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}rpos_orders WHERE order_number = %s",
            $order_number
        ));
        
        if (!$rpos_order_id) {
            error_log('ZAIKON: sync_status_to_legacy_order - No matching rpos_order found for order #' . $order_number);
            return false;
        }
        
        // Update rpos_orders status
        $result = RPOS_Orders::update_status($rpos_order_id, $rpos_status);
        
        if ($result) {
            error_log('ZAIKON: sync_status_to_legacy_order - Successfully synced status "' . $status . '" -> "' . $rpos_status . '" for order #' . $order_number);
        } else {
            error_log('ZAIKON: sync_status_to_legacy_order - Failed to sync status for order #' . $order_number);
        }
        
        return $result;
    }
    
    /**
     * Health check endpoint - Find orders with status/timestamp inconsistencies
     * 
     * Enterprise monitoring for order status sync issues.
     */
    public function status_health_check($request) {
        $issues = Zaikon_Order_Status_Service::health_check();
        
        return rest_ensure_response(array(
            'success' => true,
            'timestamp' => RPOS_Timezone::current_utc_mysql(),
            'issues_found' => count($issues),
            'issues' => $issues,
            'status' => count($issues) === 0 ? 'healthy' : 'issues_detected'
        ));
    }
    
    /**
     * Auto-repair endpoint - Fix status/timestamp issues
     * 
     * Enterprise self-healing for order status inconsistencies.
     */
    public function status_auto_repair($request) {
        $params = $request->get_json_params();
        $dry_run = isset($params['dry_run']) ? (bool)$params['dry_run'] : true;
        
        $results = Zaikon_Order_Status_Service::auto_repair($dry_run);
        
        return rest_ensure_response(array(
            'success' => true,
            'dry_run' => $dry_run,
            'timestamp' => RPOS_Timezone::current_utc_mysql(),
            'results' => $results
        ));
    }
    
    /**
     * Get order status history (audit trail)
     */
    public function get_order_status_history($request) {
        $order_id = $request->get_param('id');
        $limit = $request->get_param('limit') ?: 50;
        
        $history = Zaikon_Order_Status_Service::get_status_history($order_id, $limit);
        
        return rest_ensure_response(array(
            'success' => true,
            'order_id' => $order_id,
            'history' => $history
        ));
    }
    
    /**
     * Get status transition statistics
     */
    public function get_status_transition_stats($request) {
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        
        $stats = Zaikon_Order_Status_Service::get_transition_stats($date_from, $date_to);
        
        return rest_ensure_response(array(
            'success' => true,
            'stats' => $stats
        ));
    }
}
