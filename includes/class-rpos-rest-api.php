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
        $id = $request['id'];
        $data = $request->get_json_params();
        
        $result = RPOS_Orders::update_status($id, $data['status']);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update order status', array('status' => 500));
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
}
