<?php
/**
 * Orders Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Build query args
$args = array('limit' => 100);
if ($status_filter) {
    $args['status'] = $status_filter;
}
if ($date_from) {
    $args['date_from'] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $args['date_to'] = $date_to . ' 23:59:59';
}

// Get orders
$orders = RPOS_Orders::get_all($args);

// Get order details if viewing
$viewing_order = null;
if (isset($_GET['view']) && absint($_GET['view'])) {
    $viewing_order = RPOS_Orders::get(absint($_GET['view']));
}
?>

<div class="wrap rpos-orders">
    <h1><?php echo esc_html__('Orders Management', 'restaurant-pos'); ?></h1>
    
    <?php if ($viewing_order): ?>
    <!-- Order Detail View -->
    <div class="rpos-order-detail">
        <h2>
            <?php echo esc_html__('Order Details:', 'restaurant-pos'); ?> 
            <?php echo esc_html($viewing_order->order_number); ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-orders'); ?>" class="button" style="margin-left: 10px;">
                <?php echo esc_html__('Back to Orders', 'restaurant-pos'); ?>
            </a>
        </h2>
        
        <div class="rpos-order-info">
            <div class="rpos-info-section">
                <h3><?php echo esc_html__('Order Information', 'restaurant-pos'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Order Number:', 'restaurant-pos'); ?></th>
                        <td><?php echo esc_html($viewing_order->order_number); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Date:', 'restaurant-pos'); ?></th>
                        <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($viewing_order->created_at))); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Status:', 'restaurant-pos'); ?></th>
                        <td>
                            <span class="rpos-status rpos-status-<?php echo esc_attr($viewing_order->status); ?>">
                                <?php echo esc_html(ucfirst($viewing_order->status)); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Order Type:', 'restaurant-pos'); ?></th>
                        <td><?php echo esc_html(ucfirst(str_replace('-', ' ', $viewing_order->order_type ?? 'dine-in'))); ?></td>
                    </tr>
                    <?php if (!empty($viewing_order->special_instructions)): ?>
                    <tr>
                        <th><?php echo esc_html__('Special Instructions:', 'restaurant-pos'); ?></th>
                        <td><?php echo esc_html($viewing_order->special_instructions); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php echo esc_html__('Cashier:', 'restaurant-pos'); ?></th>
                        <td><?php echo esc_html($viewing_order->cashier_name ?? 'N/A'); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="rpos-info-section">
                <h3><?php echo esc_html__('Order Items', 'restaurant-pos'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Product', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Quantity', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Unit Price', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Total', 'restaurant-pos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viewing_order->items as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->product_name); ?></td>
                            <td><?php echo absint($item->quantity); ?></td>
                            <td>
                                <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                                <?php echo number_format($item->unit_price, 2); ?>
                            </td>
                            <td>
                                <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                                <?php echo number_format($item->line_total, 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="rpos-info-section">
                <h3><?php echo esc_html__('Payment Details', 'restaurant-pos'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Subtotal:', 'restaurant-pos'); ?></th>
                        <td>
                            <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                            <?php echo number_format($viewing_order->subtotal, 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Discount:', 'restaurant-pos'); ?></th>
                        <td>
                            <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                            <?php echo number_format($viewing_order->discount, 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Total:', 'restaurant-pos'); ?></th>
                        <td>
                            <strong>
                                <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                                <?php echo number_format($viewing_order->total, 2); ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Cash Received:', 'restaurant-pos'); ?></th>
                        <td>
                            <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                            <?php echo number_format($viewing_order->cash_received, 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Change Due:', 'restaurant-pos'); ?></th>
                        <td>
                            <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                            <?php echo number_format($viewing_order->change_due, 2); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Orders List View -->
    <div class="rpos-filters">
        <form method="get" class="rpos-filter-form">
            <input type="hidden" name="page" value="restaurant-pos-orders">
            
            <label for="status"><?php echo esc_html__('Status:', 'restaurant-pos'); ?></label>
            <select name="status" id="status">
                <option value=""><?php echo esc_html__('All Statuses', 'restaurant-pos'); ?></option>
                <option value="new" <?php selected($status_filter, 'new'); ?>><?php echo esc_html__('New', 'restaurant-pos'); ?></option>
                <option value="cooking" <?php selected($status_filter, 'cooking'); ?>><?php echo esc_html__('Cooking', 'restaurant-pos'); ?></option>
                <option value="ready" <?php selected($status_filter, 'ready'); ?>><?php echo esc_html__('Ready', 'restaurant-pos'); ?></option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php echo esc_html__('Completed', 'restaurant-pos'); ?></option>
            </select>
            
            <label for="date_from"><?php echo esc_html__('From:', 'restaurant-pos'); ?></label>
            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
            
            <label for="date_to"><?php echo esc_html__('To:', 'restaurant-pos'); ?></label>
            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
            
            <button type="submit" class="button"><?php echo esc_html__('Filter', 'restaurant-pos'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-orders'); ?>" class="button">
                <?php echo esc_html__('Clear', 'restaurant-pos'); ?>
            </a>
        </form>
    </div>
    
    <?php if (!empty($orders)): ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('Order Number', 'restaurant-pos'); ?></th>
                <th><?php echo esc_html__('Date', 'restaurant-pos'); ?></th>
                <th><?php echo esc_html__('Type', 'restaurant-pos'); ?></th>
                <th><?php echo esc_html__('Total', 'restaurant-pos'); ?></th>
                <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                <th><?php echo esc_html__('Cashier', 'restaurant-pos'); ?></th>
                <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($order->created_at))); ?></td>
                <td><?php echo esc_html(ucfirst(str_replace('-', ' ', $order->order_type ?? 'dine-in'))); ?></td>
                <td>
                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                    <?php echo number_format($order->total, 2); ?>
                </td>
                <td>
                    <span class="rpos-status rpos-status-<?php echo esc_attr($order->status); ?>">
                        <?php echo esc_html(ucfirst($order->status)); ?>
                    </span>
                </td>
                <td><?php echo esc_html($order->cashier_name ?? 'N/A'); ?></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-orders&view=' . $order->id); ?>" class="button button-small">
                        <?php echo esc_html__('View Details', 'restaurant-pos'); ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p><?php echo esc_html__('No orders found.', 'restaurant-pos'); ?></p>
    <?php endif; ?>
    
    <?php endif; ?>
</div>
