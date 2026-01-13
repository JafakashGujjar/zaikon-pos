<?php
/**
 * Zaikon Order Service Class
 * Handles atomic order creation with deliveries, audit logging, and rider assignments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Order_Service {
    
    /**
     * Create complete order with delivery (if applicable) - atomic operation
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
            'errors' => array()
        );
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // 1. Create order in zaikon_orders
            $order_id = Zaikon_Orders::create($order_data);
            
            if (!$order_id) {
                throw new Exception('Failed to create order');
            }
            
            $result['order_id'] = $order_id;
            
            // Log order creation
            Zaikon_System_Events::log('order', $order_id, 'create', array(
                'order_number' => $order_data['order_number'],
                'order_type' => $order_data['order_type'],
                'grand_total_rs' => $order_data['grand_total_rs']
            ));
            
            // 2. Create order items
            if (!empty($items)) {
                foreach ($items as $item) {
                    $item['order_id'] = $order_id;
                    $item_id = Zaikon_Order_Items::create($item);
                    
                    if (!$item_id) {
                        throw new Exception('Failed to create order item: ' . $item['product_name']);
                    }
                }
            }
            
            // 3. If delivery order, create delivery record
            if ($order_data['order_type'] === 'delivery' && !empty($delivery_data)) {
                $delivery_data['order_id'] = $order_id;
                
                // Ensure delivery_charges_rs matches what's in the order
                $delivery_data['delivery_charges_rs'] = $order_data['delivery_charges_rs'];
                
                $delivery_id = Zaikon_Deliveries::create($delivery_data);
                
                if (!$delivery_id) {
                    throw new Exception('Failed to create delivery record');
                }
                
                $result['delivery_id'] = $delivery_id;
                
                // Log delivery creation
                Zaikon_System_Events::log('delivery', $delivery_id, 'create', array(
                    'order_id' => $order_id,
                    'customer_name' => $delivery_data['customer_name'],
                    'customer_phone' => $delivery_data['customer_phone'],
                    'location_name' => $delivery_data['location_name'],
                    'distance_km' => $delivery_data['distance_km'],
                    'delivery_charges_rs' => $delivery_data['delivery_charges_rs'],
                    'is_free_delivery' => $delivery_data['is_free_delivery']
                ));
                
                // 4. If rider is assigned, create payout record
                if (!empty($delivery_data['assigned_rider_id'])) {
                    $rider_pay = Zaikon_Riders::calculate_rider_pay($delivery_data['assigned_rider_id'], $delivery_data['distance_km']);
                    
                    $payout_id = Zaikon_Rider_Payouts::create(array(
                        'delivery_id' => $delivery_id,
                        'rider_id' => $delivery_data['assigned_rider_id'],
                        'rider_pay_rs' => $rider_pay
                    ));
                    
                    if (!$payout_id) {
                        throw new Exception('Failed to create rider payout');
                    }
                    
                    // Log rider assignment
                    Zaikon_System_Events::log('delivery', $delivery_id, 'assign_rider', array(
                        'rider_id' => $delivery_data['assigned_rider_id'],
                        'rider_pay_rs' => $rider_pay
                    ));
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            $result['errors'][] = $e->getMessage();
            
            // Log error
            Zaikon_System_Events::log('order', 0, 'create_failed', array(
                'error' => $e->getMessage(),
                'order_data' => $order_data
            ));
        }
        
        return $result;
    }
    
    /**
     * Update delivery status and handle rider payout if needed
     */
    public static function update_delivery_status($delivery_id, $new_status, $rider_id = null) {
        global $wpdb;
        
        $delivery = Zaikon_Deliveries::get($delivery_id);
        
        if (!$delivery) {
            return false;
        }
        
        $update_data = array(
            'delivery_status' => $new_status
        );
        
        if ($new_status === 'delivered') {
            $update_data['delivered_at'] = current_time('mysql');
        }
        
        $result = Zaikon_Deliveries::update($delivery_id, $update_data);
        
        // Log status update
        Zaikon_System_Events::log('delivery', $delivery_id, 'status_update', array(
            'old_status' => $delivery->delivery_status,
            'new_status' => $new_status,
            'delivered_at' => $update_data['delivered_at'] ?? null
        ));
        
        return $result;
    }
    
    /**
     * Assign rider to delivery
     */
    public static function assign_rider($delivery_id, $rider_id) {
        $delivery = Zaikon_Deliveries::get($delivery_id);
        
        if (!$delivery) {
            return false;
        }
        
        // Update delivery with rider
        $result = Zaikon_Deliveries::update($delivery_id, array(
            'assigned_rider_id' => $rider_id
        ));
        
        if (!$result) {
            return false;
        }
        
        // Create or update payout record
        $existing_payout = Zaikon_Rider_Payouts::get_by_delivery($delivery_id);
        
        if (!$existing_payout) {
            $rider_pay = Zaikon_Riders::calculate_rider_pay($rider_id, $delivery->distance_km);
            
            Zaikon_Rider_Payouts::create(array(
                'delivery_id' => $delivery_id,
                'rider_id' => $rider_id,
                'rider_pay_rs' => $rider_pay
            ));
        }
        
        // Log assignment
        Zaikon_System_Events::log('delivery', $delivery_id, 'assign_rider', array(
            'rider_id' => $rider_id,
            'previous_rider_id' => $delivery->assigned_rider_id
        ));
        
        return true;
    }
    
    /**
     * Assign rider to order (creates both delivery assignment and rider_orders record)
     */
    public static function assign_rider_to_order($order_id, $rider_id, $notes = null) {
        global $wpdb;
        
        // Get order details
        $order = Zaikon_Orders::get($order_id);
        if (!$order || $order->order_type !== 'delivery') {
            return array('success' => false, 'message' => 'Invalid delivery order');
        }
        
        // Get delivery record
        $delivery = Zaikon_Deliveries::get_by_order($order_id);
        if (!$delivery) {
            return array('success' => false, 'message' => 'Delivery record not found');
        }
        
        // Assign rider to delivery
        $result = self::assign_rider($delivery->id, $rider_id);
        if (!$result) {
            return array('success' => false, 'message' => 'Failed to assign rider to delivery');
        }
        
        // Create or update rider_order record
        $existing_rider_order = Zaikon_Rider_Orders::get_by_order($order_id);
        
        if ($existing_rider_order) {
            // Update existing record
            Zaikon_Rider_Orders::update_status($existing_rider_order->id, 'assigned', $notes);
        } else {
            // Create new rider_order record
            Zaikon_Rider_Orders::create(array(
                'order_id' => $order_id,
                'rider_id' => $rider_id,
                'delivery_id' => $delivery->id,
                'status' => 'assigned',
                'assigned_at' => current_time('mysql'),
                'notes' => $notes
            ));
        }
        
        // Calculate estimated payout
        $estimated_payout = Zaikon_Riders::calculate_rider_pay($rider_id, $delivery->distance_km);
        
        return array(
            'success' => true,
            'message' => 'Rider assigned successfully',
            'delivery_id' => $delivery->id,
            'estimated_payout' => $estimated_payout
        );
    }
}
