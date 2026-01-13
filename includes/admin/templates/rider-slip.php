<?php
/**
 * Rider Slip Template
 * Print-optimized delivery slip for riders
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get order ID from query parameter
$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

if (!$order_id) {
    wp_die('Invalid order ID');
}

// Get order details
$order = Zaikon_Orders::get($order_id);
if (!$order) {
    wp_die('Order not found');
}

// Get delivery details
$delivery = Zaikon_Deliveries::get_by_order($order_id);
if (!$delivery) {
    wp_die('Delivery details not found');
}

// Get order items
$items = Zaikon_Order_Items::get_by_order($order_id);

// Get rider details if assigned
$rider = null;
if ($delivery->assigned_rider_id) {
    $rider = Zaikon_Riders::get($delivery->assigned_rider_id);
}

// Calculate estimated payout
$estimated_payout = 0;
if ($rider) {
    $estimated_payout = Zaikon_Riders::calculate_rider_pay($rider->id, $delivery->distance_km);
}

// Get restaurant settings
$restaurant_name = RPOS_Settings::get('restaurant_name', get_bloginfo('name'));
$restaurant_phone = RPOS_Settings::get('restaurant_phone', '');
$restaurant_address = RPOS_Settings::get('restaurant_address', '');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Slip - Order #<?php echo esc_html($order->order_number); ?></title>
    <link rel="stylesheet" href="<?php echo RPOS_PLUGIN_URL; ?>assets/css/rider-slip-print.css">
</head>
<body>
    <div class="rider-slip">
        <!-- Restaurant Header -->
        <div class="slip-header">
            <h1><?php echo esc_html($restaurant_name); ?></h1>
            <?php if ($restaurant_phone): ?>
                <p class="restaurant-phone"><?php echo esc_html($restaurant_phone); ?></p>
            <?php endif; ?>
            <?php if ($restaurant_address): ?>
                <p class="restaurant-address"><?php echo esc_html($restaurant_address); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="slip-divider"></div>
        
        <!-- Order Information -->
        <div class="slip-section">
            <h2>DELIVERY ORDER</h2>
            <div class="info-row">
                <span class="label">Order #:</span>
                <span class="value"><?php echo esc_html($order->order_number); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Date:</span>
                <span class="value"><?php echo esc_html(date('M d, Y h:i A', strtotime($order->created_at))); ?></span>
            </div>
        </div>
        
        <div class="slip-divider"></div>
        
        <!-- Customer Information -->
        <div class="slip-section">
            <h2>CUSTOMER DETAILS</h2>
            <div class="info-row">
                <span class="label">Name:</span>
                <span class="value"><?php echo esc_html($delivery->customer_name); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Phone:</span>
                <span class="value"><?php echo esc_html($delivery->customer_phone); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Location:</span>
                <span class="value"><?php echo esc_html($delivery->location_name); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Distance:</span>
                <span class="value"><?php echo esc_html(number_format($delivery->distance_km, 2)); ?> km</span>
            </div>
        </div>
        
        <?php if ($delivery->delivery_instructions): ?>
            <div class="slip-section special-instructions">
                <h3>⚠ DELIVERY INSTRUCTIONS:</h3>
                <p><?php echo esc_html($delivery->delivery_instructions); ?></p>
            </div>
        <?php elseif ($delivery->special_instruction): ?>
            <div class="slip-section special-instructions">
                <h3>⚠ DELIVERY INSTRUCTIONS:</h3>
                <p><?php echo esc_html($delivery->special_instruction); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="slip-divider"></div>
        
        <!-- Order Items -->
        <div class="slip-section">
            <h2>ORDER ITEMS</h2>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="qty">Qty</th>
                        <th class="price">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->product_name); ?></td>
                            <td class="qty"><?php echo esc_html($item->qty); ?></td>
                            <td class="price">Rs <?php echo esc_html(number_format($item->line_total_rs, 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="slip-divider"></div>
        
        <!-- Payment Summary -->
        <div class="slip-section">
            <h2>PAYMENT</h2>
            <div class="payment-row">
                <span class="label">Subtotal:</span>
                <span class="value">Rs <?php echo esc_html(number_format($order->items_subtotal_rs, 2)); ?></span>
            </div>
            <div class="payment-row">
                <span class="label">Delivery Charges:</span>
                <span class="value">Rs <?php echo esc_html(number_format($order->delivery_charges_rs, 2)); ?></span>
            </div>
            <?php if ($delivery->is_free_delivery): ?>
                <div class="payment-row free-delivery">
                    <span class="label">Free Delivery Applied:</span>
                    <span class="value">- Rs <?php echo esc_html(number_format($order->delivery_charges_rs, 2)); ?></span>
                </div>
            <?php endif; ?>
            <div class="payment-row total">
                <span class="label">TOTAL TO COLLECT:</span>
                <span class="value">Rs <?php echo esc_html(number_format($order->grand_total_rs, 2)); ?></span>
            </div>
        </div>
        
        <div class="slip-divider"></div>
        
        <!-- Rider Information -->
        <div class="slip-section">
            <h2>RIDER DETAILS</h2>
            <?php if ($rider): ?>
                <div class="info-row">
                    <span class="label">Rider:</span>
                    <span class="value"><?php echo esc_html($rider->name); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Phone:</span>
                    <span class="value"><?php echo esc_html($rider->phone); ?></span>
                </div>
                <div class="info-row payout">
                    <span class="label">Est. Payout:</span>
                    <span class="value">Rs <?php echo esc_html(number_format($estimated_payout, 2)); ?></span>
                </div>
            <?php else: ?>
                <p class="no-rider">Rider not yet assigned</p>
            <?php endif; ?>
        </div>
        
        <div class="slip-divider"></div>
        
        <!-- Customer Signature -->
        <div class="slip-section signature">
            <h3>CUSTOMER SIGNATURE</h3>
            <div class="signature-line"></div>
            <p class="signature-label">Customer Signature</p>
        </div>
        
        <!-- Footer -->
        <div class="slip-footer">
            <p>Thank you for ordering!</p>
            <p>Please call <?php echo esc_html($restaurant_phone); ?> for any issues</p>
        </div>
    </div>
    
    <script>
        // Auto-print on page load
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
