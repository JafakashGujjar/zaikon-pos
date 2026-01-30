<?php
/**
 * Zaikon Order Service Class
 * 
 * Enterprise-grade order service with atomic order creation,
 * transaction safety, full traceability, and fault tolerance.
 * 
 * Handles deliveries, audit logging, and rider assignments
 * with guaranteed data consistency.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Order_Service {
    
    /**
     * Create complete order with delivery (if applicable) - atomic operation
     * 
     * Uses RPOS_Database transaction management for enterprise-grade
     * reliability with automatic rollback on failure.
     * 
     * @param array $order_data Order information
     * @param array $items Array of order items
     * @param array $delivery_data Delivery information (if order_type is delivery)
     * @return array Result with order_id, delivery_id, errors
     */
    public static function create_order($order_data, $items, $delivery_data = null) {
        global $wpdb;
        
        $result = array(
            'success' => false,
            'order_id' => null,
            'delivery_id' => null,
            'tracking_token' => null,
            'tracking_url' => null,
            'errors' => array()
        );
        
        // Validate required order data
        $missing_fields = RPOS_Database::validate_required_fields($order_data, 
            array('order_number', 'order_type', 'grand_total_rs'));
        
        if (!empty($missing_fields)) {
            $result['errors'][] = 'Missing required fields: ' . implode(', ', $missing_fields);
            Zaikon_System_Events::log_error('order', 0, 'validation_failed', 
                'Missing required fields', array('fields' => $missing_fields));
            return $result;
        }
        
        // Start transaction using enterprise database handler
        if (!RPOS_Database::begin_transaction()) {
            $result['errors'][] = 'Failed to start database transaction';
            return $result;
        }
        
        try {
            // 1. Create order in zaikon_orders with retry for fault tolerance
            $order_id = RPOS_Database::with_retry(function() use ($order_data) {
                return Zaikon_Orders::create($order_data);
            });
            
            if (!$order_id) {
                $db_error = $wpdb->last_error ? ' - MySQL Error: ' . $wpdb->last_error : '';
                throw new Exception('Failed to create order in zaikon_orders table' . $db_error);
            }
            
            $result['order_id'] = $order_id;
            
            // Generate tracking token for the order
            $tracking_token = Zaikon_Order_Tracking::generate_tracking_token($order_id);
            if ($tracking_token) {
                $result['tracking_token'] = $tracking_token;
                $result['tracking_url'] = Zaikon_Order_Tracking::get_tracking_url($tracking_token);
            }
            
            // Set initial order status to 'pending' if delivery, or 'confirmed' if dine-in/takeaway
            $initial_status = ($order_data['order_type'] === 'delivery') ? 'pending' : 'confirmed';
            Zaikon_Order_Tracking::update_status($order_id, $initial_status);
            
            // Log order creation with full traceability
            Zaikon_System_Events::log('order', $order_id, 'create', array(
                'order_number' => $order_data['order_number'],
                'order_type' => $order_data['order_type'],
                'grand_total_rs' => $order_data['grand_total_rs'],
                'tracking_token' => $tracking_token,
                'item_count' => count($items),
                'payment_type' => $order_data['payment_type'] ?? 'cash'
            ));
            
            // 2. Create order items
            if (!empty($items)) {
                foreach ($items as $item) {
                    $item['order_id'] = $order_id;
                    $item_id = Zaikon_Order_Items::create($item);
                    
                    if (!$item_id) {
                        $item_identifier = isset($item['product_id']) 
                            ? 'product_id=' . $item['product_id'] 
                            : ($item['product_name'] ?? 'unknown');
                        throw new Exception('Failed to create order item: ' . $item_identifier);
                    }
                }
            }
            
            // 3. If delivery order, create delivery record
            if ($order_data['order_type'] === 'delivery' && !empty($delivery_data)) {
                $delivery_result = self::create_delivery_record($order_id, $order_data, $delivery_data);
                
                if (!$delivery_result['success']) {
                    throw new Exception($delivery_result['error']);
                }
                
                $result['delivery_id'] = $delivery_result['delivery_id'];
            }
            
            // Commit transaction
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit order transaction');
            }
            
            $result['success'] = true;
            
            // Log successful completion
            Zaikon_System_Events::log('order', $order_id, 'create_complete', array(
                'order_number' => $order_data['order_number'],
                'delivery_id' => $result['delivery_id']
            ));
            
        } catch (Exception $e) {
            // Rollback on error
            RPOS_Database::rollback('Order creation failed: ' . $e->getMessage());
            
            $result['errors'][] = $e->getMessage();
            
            // Log error with full context
            Zaikon_System_Events::log_error('order', 0, 'create_failed', $e->getMessage(), array(
                'order_number' => $order_data['order_number'] ?? 'unknown',
                'order_type' => $order_data['order_type'] ?? 'unknown',
                'error_trace' => $e->getTraceAsString()
            ));
        }
        
        return $result;
    }
    
    /**
     * Create delivery record within transaction
     * 
     * @param int $order_id Order ID
     * @param array $order_data Order data
     * @param array $delivery_data Delivery data
     * @return array Result with success, delivery_id, error
     */
    private static function create_delivery_record($order_id, $order_data, $delivery_data) {
        $result = array(
            'success' => false,
            'delivery_id' => null,
            'error' => null
        );
        
        $delivery_data['order_id'] = $order_id;
        
        // Ensure delivery_charges_rs matches what's in the order
        $delivery_data['delivery_charges_rs'] = $order_data['delivery_charges_rs'] ?? 0;
        
        // Calculate and add rider payout data if rider is assigned
        if (!empty($delivery_data['assigned_rider_id'])) {
            $payout_data = self::calculate_rider_payout_data(
                $delivery_data['assigned_rider_id'], 
                $delivery_data['distance_km'] ?? 0
            );
            
            if ($payout_data) {
                $delivery_data = array_merge($delivery_data, $payout_data);
            }
        }
        
        $delivery_id = Zaikon_Deliveries::create($delivery_data);
        
        if (!$delivery_id) {
            $result['error'] = 'Failed to create delivery record';
            return $result;
        }
        
        $result['delivery_id'] = $delivery_id;
        
        // Log delivery creation
        Zaikon_System_Events::log('delivery', $delivery_id, 'create', array(
            'order_id' => $order_id,
            'customer_name' => $delivery_data['customer_name'] ?? '',
            'customer_phone' => $delivery_data['customer_phone'] ?? '',
            'location_name' => $delivery_data['location_name'] ?? '',
            'distance_km' => $delivery_data['distance_km'] ?? 0,
            'delivery_charges_rs' => $delivery_data['delivery_charges_rs'],
            'is_free_delivery' => $delivery_data['is_free_delivery'] ?? false,
            'rider_payout_amount' => $delivery_data['rider_payout_amount'] ?? null
        ));
        
        // If rider is assigned, create payout and rider_orders records
        if (!empty($delivery_data['assigned_rider_id'])) {
            $rider_result = self::create_rider_assignment(
                $order_id, 
                $delivery_id, 
                $delivery_data['assigned_rider_id'],
                $delivery_data['rider_payout_amount'] ?? null,
                $delivery_data['distance_km'] ?? 0
            );
            
            if (!$rider_result['success']) {
                $result['error'] = $rider_result['error'];
                return $result;
            }
        }
        
        $result['success'] = true;
        return $result;
    }
    
    /**
     * Create rider assignment within transaction
     */
    private static function create_rider_assignment($order_id, $delivery_id, $rider_id, $rider_pay = null, $distance_km = 0) {
        $result = array(
            'success' => false,
            'error' => null
        );
        
        if (!$rider_pay) {
            $rider_pay = Zaikon_Riders::calculate_rider_pay($rider_id, $distance_km);
        }
        
        $payout_id = Zaikon_Rider_Payouts::create(array(
            'delivery_id' => $delivery_id,
            'rider_id' => $rider_id,
            'rider_pay_rs' => $rider_pay
        ));
        
        if (!$payout_id) {
            $result['error'] = 'Failed to create rider payout';
            return $result;
        }
        
        // Create rider_orders record
        $rider_order_id = Zaikon_Rider_Orders::create(array(
            'order_id' => $order_id,
            'rider_id' => $rider_id,
            'delivery_id' => $delivery_id,
            'status' => 'assigned',
            'assigned_at' => RPOS_Timezone::current_utc_mysql()
        ));
        
        if (!$rider_order_id) {
            $result['error'] = 'Failed to create rider order record';
            return $result;
        }
        
        // Log rider assignment
        Zaikon_System_Events::log('delivery', $delivery_id, 'assign_rider', array(
            'rider_id' => $rider_id,
            'rider_pay_rs' => $rider_pay,
            'order_id' => $order_id
        ));
        
        $result['success'] = true;
        return $result;
    }
    
    /**
     * Update delivery status with transaction safety and audit logging
     * 
     * @param int $delivery_id Delivery ID
     * @param string $new_status New delivery status
     * @param int|null $rider_id Optional rider ID
     * @return bool Success status
     */
    public static function update_delivery_status($delivery_id, $new_status, $rider_id = null) {
        $delivery = Zaikon_Deliveries::get($delivery_id);
        
        if (!$delivery) {
            Zaikon_System_Events::log_error('delivery', $delivery_id, 'status_update_failed', 
                'Delivery not found');
            return false;
        }
        
        $old_status = $delivery->delivery_status;
        
        // Idempotent check
        if ($old_status === $new_status) {
            return true;
        }
        
        // Start transaction
        if (!RPOS_Database::begin_transaction()) {
            return false;
        }
        
        try {
            $update_data = array(
                'delivery_status' => $new_status
            );
            
            if ($new_status === 'delivered') {
                $update_data['delivered_at'] = RPOS_Timezone::current_utc_mysql();
            }
            
            $result = Zaikon_Deliveries::update($delivery_id, $update_data);
            
            if ($result === false) {
                throw new Exception('Failed to update delivery status');
            }
            
            // Commit
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit status update');
            }
            
            // Log status update with full traceability
            Zaikon_System_Events::log('delivery', $delivery_id, 'status_update', array(
                'old_status' => $old_status,
                'new_status' => $new_status,
                'delivered_at' => $update_data['delivered_at'] ?? null,
                'order_id' => $delivery->order_id
            ));
            
            return true;
            
        } catch (Exception $e) {
            RPOS_Database::rollback('Delivery status update failed: ' . $e->getMessage());
            
            Zaikon_System_Events::log_error('delivery', $delivery_id, 'status_update_failed', 
                $e->getMessage(), array(
                    'old_status' => $old_status,
                    'new_status' => $new_status
                ));
            
            return false;
        }
    }
    
    /**
     * Assign rider to delivery with transaction safety
     * 
     * @param int $delivery_id Delivery ID
     * @param int $rider_id Rider ID
     * @return bool Success status
     */
    public static function assign_rider($delivery_id, $rider_id) {
        $delivery = Zaikon_Deliveries::get($delivery_id);
        
        if (!$delivery) {
            return false;
        }
        
        // Calculate rider payout data
        $payout_data = self::calculate_rider_payout_data($rider_id, $delivery->distance_km);
        
        if (!$payout_data) {
            return false;
        }
        
        // Start transaction
        if (!RPOS_Database::begin_transaction()) {
            return false;
        }
        
        try {
            // Update delivery with rider and payout information
            $update_data = array_merge(
                array('assigned_rider_id' => $rider_id),
                $payout_data
            );
            
            $result = Zaikon_Deliveries::update($delivery_id, $update_data);
            
            if ($result === false) {
                throw new Exception('Failed to update delivery with rider');
            }
            
            // Create or update payout record
            $existing_payout = Zaikon_Rider_Payouts::get_by_delivery($delivery_id);
            
            if (!$existing_payout) {
                $payout_result = Zaikon_Rider_Payouts::create(array(
                    'delivery_id' => $delivery_id,
                    'rider_id' => $rider_id,
                    'rider_pay_rs' => $payout_data['rider_payout_amount']
                ));
                
                if (!$payout_result) {
                    throw new Exception('Failed to create rider payout');
                }
            }
            
            // Commit
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit rider assignment');
            }
            
            // Log assignment
            Zaikon_System_Events::log('delivery', $delivery_id, 'assign_rider', array(
                'rider_id' => $rider_id,
                'previous_rider_id' => $delivery->assigned_rider_id,
                'rider_payout_amount' => $payout_data['rider_payout_amount'],
                'order_id' => $delivery->order_id
            ));
            
            return true;
            
        } catch (Exception $e) {
            RPOS_Database::rollback('Rider assignment failed: ' . $e->getMessage());
            
            Zaikon_System_Events::log_error('delivery', $delivery_id, 'assign_rider_failed', 
                $e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Assign rider to order with full transaction safety
     * Creates both delivery assignment and rider_orders record
     * 
     * @param int $order_id Order ID
     * @param int $rider_id Rider ID
     * @param string|null $notes Optional notes
     * @return array Result with success, message, delivery_id, estimated_payout
     */
    public static function assign_rider_to_order($order_id, $rider_id, $notes = null) {
        global $wpdb;
        
        $result = array(
            'success' => false,
            'message' => '',
            'delivery_id' => null,
            'estimated_payout' => 0
        );
        
        // Get order details
        $order = Zaikon_Orders::get($order_id);
        if (!$order || $order->order_type !== 'delivery') {
            $result['message'] = 'Invalid delivery order';
            return $result;
        }
        
        // Get delivery record
        $delivery = Zaikon_Deliveries::get_by_order($order_id);
        if (!$delivery) {
            $result['message'] = 'Delivery record not found';
            return $result;
        }
        
        // Start transaction
        if (!RPOS_Database::begin_transaction()) {
            $result['message'] = 'Failed to start transaction';
            return $result;
        }
        
        try {
            // Calculate rider payout
            $payout_data = self::calculate_rider_payout_data($rider_id, $delivery->distance_km);
            if (!$payout_data) {
                throw new Exception('Failed to calculate rider payout');
            }
            
            // Update delivery with rider and payout information
            $update_data = array_merge(
                array('assigned_rider_id' => $rider_id),
                $payout_data
            );
            
            $update_result = Zaikon_Deliveries::update($delivery->id, $update_data);
            if ($update_result === false) {
                throw new Exception('Failed to assign rider to delivery');
            }
            
            // Create or update payout record
            $existing_payout = Zaikon_Rider_Payouts::get_by_delivery($delivery->id);
            if (!$existing_payout) {
                $payout_create = Zaikon_Rider_Payouts::create(array(
                    'delivery_id' => $delivery->id,
                    'rider_id' => $rider_id,
                    'rider_pay_rs' => $payout_data['rider_payout_amount']
                ));
                if (!$payout_create) {
                    throw new Exception('Failed to create rider payout');
                }
            }
            
            // Create or update rider_order record
            $existing_rider_order = Zaikon_Rider_Orders::get_by_order($order_id);
            
            if ($existing_rider_order) {
                Zaikon_Rider_Orders::update_status($existing_rider_order->id, 'assigned', $notes);
            } else {
                $rider_order_result = Zaikon_Rider_Orders::create(array(
                    'order_id' => $order_id,
                    'rider_id' => $rider_id,
                    'delivery_id' => $delivery->id,
                    'status' => 'assigned',
                    'assigned_at' => RPOS_Timezone::current_utc_mysql(),
                    'notes' => $notes
                ));
                if (!$rider_order_result) {
                    throw new Exception('Failed to create rider order record');
                }
            }
            
            // Update order tracking status to confirmed if the order is still pending
            $current_status = $order->order_status ?? 'pending';
            if ($current_status === 'pending') {
                Zaikon_Order_Tracking::update_status($order_id, 'confirmed');
            }
            
            // Commit
            if (!RPOS_Database::commit()) {
                throw new Exception('Failed to commit rider assignment');
            }
            
            // Log successful assignment
            Zaikon_System_Events::log('order', $order_id, 'rider_assigned', array(
                'rider_id' => $rider_id,
                'delivery_id' => $delivery->id,
                'payout_amount' => $payout_data['rider_payout_amount'],
                'notes' => $notes
            ));
            
            $result['success'] = true;
            $result['message'] = 'Rider assigned successfully';
            $result['delivery_id'] = $delivery->id;
            $result['estimated_payout'] = $payout_data['rider_payout_amount'];
            
        } catch (Exception $e) {
            RPOS_Database::rollback('Rider assignment to order failed: ' . $e->getMessage());
            
            $result['message'] = $e->getMessage();
            
            Zaikon_System_Events::log_error('order', $order_id, 'rider_assignment_failed', 
                $e->getMessage(), array('rider_id' => $rider_id));
        }
        
        return $result;
    }
    
    /**
     * Calculate rider payout data for a delivery
     * 
     * @param int $rider_id Rider ID
     * @param float $distance_km Distance in kilometers
     * @return array|null Payout data including amount, slab, type, and rate
     */
    private static function calculate_rider_payout_data($rider_id, $distance_km) {
        $rider = Zaikon_Riders::get($rider_id);
        if (!$rider) {
            return null;
        }
        
        $rider_pay = Zaikon_Riders::calculate_rider_pay($rider_id, $distance_km);
        
        return array(
            'rider_payout_amount' => $rider_pay,
            'rider_payout_slab' => self::determine_payout_slab($distance_km),
            'payout_type' => $rider->payout_type ?? 'per_km',
            'payout_per_km_rate' => $rider->per_km_rate ?? null
        );
    }
    
    /**
     * Determine distance-based payout slab
     * 
     * @param float $distance_km Distance in kilometers
     * @return string Slab identifier
     */
    private static function determine_payout_slab($distance_km) {
        $distance = floatval($distance_km);
        
        if ($distance <= 5) {
            return '0-5km';
        } elseif ($distance <= 10) {
            return '5-10km';
        } else {
            return '10+km';
        }
    }
    
    /**
     * Get order history (audit trail) for an order
     * 
     * @param int $order_id Order ID
     * @return array Audit events
     */
    public static function get_order_history($order_id) {
        return Zaikon_System_Events::get_entity_events('order', $order_id);
    }
    
    /**
     * Get delivery history (audit trail) for a delivery
     * 
     * @param int $delivery_id Delivery ID
     * @return array Audit events
     */
    public static function get_delivery_history($delivery_id) {
        return Zaikon_System_Events::get_entity_events('delivery', $delivery_id);
    }
}
