<?php
/**
 * Dashboard Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get today's sales
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$today_sales = RPOS_Reports::get_sales_summary($today_start, $today_end);

// Get low stock items
$low_stock = RPOS_Reports::get_low_stock_report();
$low_stock_count = count($low_stock);

// Get recent orders
$recent_orders = RPOS_Orders::get_all(array('limit' => 5));
?>

<div class="wrap rpos-dashboard">
    <h1><?php echo esc_html__('Restaurant POS Dashboard', 'restaurant-pos'); ?></h1>
    
    <div class="rpos-dashboard-widgets">
        <div class="rpos-widget">
            <h3><?php echo esc_html__("Today's Sales", 'restaurant-pos'); ?></h3>
            <div class="rpos-widget-content">
                <p class="rpos-big-number">
                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                    <?php echo number_format($today_sales->total_sales ?? 0, 2); ?>
                </p>
                <p class="rpos-meta">
                    <?php echo absint($today_sales->order_count ?? 0); ?> 
                    <?php echo esc_html__('orders', 'restaurant-pos'); ?>
                </p>
            </div>
        </div>
        
        <div class="rpos-widget">
            <h3><?php echo esc_html__('Average Order', 'restaurant-pos'); ?></h3>
            <div class="rpos-widget-content">
                <p class="rpos-big-number">
                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                    <?php echo number_format($today_sales->average_order ?? 0, 2); ?>
                </p>
            </div>
        </div>
        
        <div class="rpos-widget rpos-widget-warning">
            <h3><?php echo esc_html__('Low Stock Items', 'restaurant-pos'); ?></h3>
            <div class="rpos-widget-content">
                <p class="rpos-big-number"><?php echo absint($low_stock_count); ?></p>
                <?php if ($low_stock_count > 0): ?>
                <p class="rpos-meta">
                    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-inventory'); ?>">
                        <?php echo esc_html__('View Inventory', 'restaurant-pos'); ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="rpos-quick-actions">
        <h2><?php echo esc_html__('Quick Actions', 'restaurant-pos'); ?></h2>
        <div class="rpos-actions-grid">
            <?php if (current_user_can('rpos_view_pos')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-cashier'); ?>" class="rpos-action-btn rpos-action-primary">
                <span class="dashicons dashicons-cart"></span>
                <?php echo esc_html__('Open POS', 'restaurant-pos'); ?>
            </a>
            <?php endif; ?>
            
            <?php if (current_user_can('rpos_view_kds')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-kds'); ?>" class="rpos-action-btn">
                <span class="dashicons dashicons-food"></span>
                <?php echo esc_html__('Kitchen Display', 'restaurant-pos'); ?>
            </a>
            <?php endif; ?>
            
            <?php if (current_user_can('rpos_manage_products')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-products'); ?>" class="rpos-action-btn">
                <span class="dashicons dashicons-products"></span>
                <?php echo esc_html__('Manage Products', 'restaurant-pos'); ?>
            </a>
            <?php endif; ?>
            
            <?php if (current_user_can('rpos_manage_inventory')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-inventory'); ?>" class="rpos-action-btn">
                <span class="dashicons dashicons-archive"></span>
                <?php echo esc_html__('Manage Inventory', 'restaurant-pos'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (current_user_can('rpos_view_orders')): ?>
    <div class="rpos-recent-orders">
        <h2><?php echo esc_html__('Recent Orders', 'restaurant-pos'); ?></h2>
        <?php if (!empty($recent_orders)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Order Number', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Date', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Total', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Cashier', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td><?php echo esc_html($order->order_number); ?></td>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($order->created_at))); ?></td>
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php echo esc_html__('No orders yet.', 'restaurant-pos'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
