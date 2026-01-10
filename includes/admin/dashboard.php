<?php
/**
 * ZAIKON POS - Dashboard
 * Modern dashboard with KPI cards
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

$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap zaikon-admin">
    <h1><?php echo esc_html__('ZAIKON POS Dashboard', 'restaurant-pos'); ?></h1>
    
    <div class="zaikon-dashboard-widgets">
        <div class="zaikon-widget zaikon-widget-primary">
            <div class="zaikon-widget-header">
                <h3 class="zaikon-widget-title"><?php echo esc_html__("Today's Sales", 'restaurant-pos'); ?></h3>
                <div class="zaikon-widget-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
            </div>
            <div class="zaikon-widget-value">
                <?php echo esc_html($currency); ?>
                <?php echo number_format($today_sales->total_sales ?? 0, 2); ?>
            </div>
            <div class="zaikon-widget-meta">
                <?php echo absint($today_sales->order_count ?? 0); ?> 
                <?php echo esc_html__('orders completed', 'restaurant-pos'); ?>
            </div>
        </div>
        
        <div class="zaikon-widget">
            <div class="zaikon-widget-header">
                <h3 class="zaikon-widget-title"><?php echo esc_html__('Average Order', 'restaurant-pos'); ?></h3>
                <div class="zaikon-widget-icon">
                    <span class="dashicons dashicons-cart"></span>
                </div>
            </div>
            <div class="zaikon-widget-value">
                <?php echo esc_html($currency); ?>
                <?php echo number_format($today_sales->average_order ?? 0, 2); ?>
            </div>
            <div class="zaikon-widget-meta">
                <?php echo esc_html__('per order today', 'restaurant-pos'); ?>
            </div>
        </div>
        
        <div class="zaikon-widget <?php echo $low_stock_count > 0 ? 'zaikon-widget-warning' : ''; ?>">
            <div class="zaikon-widget-header">
                <h3 class="zaikon-widget-title"><?php echo esc_html__('Low Stock Items', 'restaurant-pos'); ?></h3>
                <div class="zaikon-widget-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
            </div>
            <div class="zaikon-widget-value"><?php echo absint($low_stock_count); ?></div>
            <div class="zaikon-widget-meta">
                <?php if ($low_stock_count > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-inventory'); ?>" class="zaikon-widget-link">
                        <?php echo esc_html__('View Inventory →', 'restaurant-pos'); ?>
                    </a>
                <?php else: ?>
                    <?php echo esc_html__('All items in stock', 'restaurant-pos'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="zaikon-quick-actions">
        <h2><?php echo esc_html__('Quick Actions', 'restaurant-pos'); ?></h2>
        <div class="zaikon-actions-grid">
            <?php if (current_user_can('rpos_view_pos')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-cashier'); ?>" class="zaikon-action-card">
                <div class="zaikon-action-icon">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <span class="zaikon-action-text"><?php echo esc_html__('Open POS', 'restaurant-pos'); ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (current_user_can('rpos_view_kds')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-kds'); ?>" class="zaikon-action-card">
                <div class="zaikon-action-icon" style="background: var(--zaikon-yellow); color: var(--zaikon-dark);">
                    <span class="dashicons dashicons-food"></span>
                </div>
                <span class="zaikon-action-text"><?php echo esc_html__('Kitchen Display', 'restaurant-pos'); ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (current_user_can('rpos_manage_products')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-products'); ?>" class="zaikon-action-card">
                <div class="zaikon-action-icon" style="background: var(--zaikon-dark);">
                    <span class="dashicons dashicons-products"></span>
                </div>
                <span class="zaikon-action-text"><?php echo esc_html__('Manage Products', 'restaurant-pos'); ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (current_user_can('rpos_manage_inventory')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-inventory'); ?>" class="zaikon-action-card">
                <div class="zaikon-action-icon" style="background: var(--stock-good);">
                    <span class="dashicons dashicons-archive"></span>
                </div>
                <span class="zaikon-action-text"><?php echo esc_html__('Manage Inventory', 'restaurant-pos'); ?></span>
            </a>
            <?php endif; ?>
            
            <?php if (current_user_can('rpos_view_reports')): ?>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-reports'); ?>" class="zaikon-action-card">
                <div class="zaikon-action-icon" style="background: var(--status-new);">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <span class="zaikon-action-text"><?php echo esc_html__('View Reports', 'restaurant-pos'); ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (current_user_can('rpos_view_orders') && !empty($recent_orders)): ?>
    <div class="zaikon-list-section">
        <div class="zaikon-list-header">
            <h2 class="zaikon-list-title"><?php echo esc_html__('Recent Orders', 'restaurant-pos'); ?></h2>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-orders'); ?>" class="zaikon-btn zaikon-btn-outline-orange">
                <?php echo esc_html__('View All Orders →', 'restaurant-pos'); ?>
            </a>
        </div>
        
        <table class="zaikon-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Order #', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Type', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Total', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Date', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td><strong>#<?php echo esc_html($order->order_number); ?></strong></td>
                    <td>
                        <?php 
                        $type_classes = array(
                            'dine-in' => 'zaikon-badge-dine-in',
                            'takeaway' => 'zaikon-badge-takeaway',
                            'delivery' => 'zaikon-badge-delivery'
                        );
                        $type_class = $type_classes[$order->order_type] ?? 'zaikon-badge-dark';
                        ?>
                        <span class="zaikon-badge <?php echo esc_attr($type_class); ?>">
                            <?php echo esc_html(ucfirst(str_replace('-', ' ', $order->order_type))); ?>
                        </span>
                    </td>
                    <td><strong><?php echo esc_html($currency . number_format($order->total, 2)); ?></strong></td>
                    <td>
                        <?php
                        $status_classes = array(
                            'new' => 'zaikon-badge-new',
                            'cooking' => 'zaikon-badge-cooking',
                            'ready' => 'zaikon-badge-ready',
                            'completed' => 'zaikon-badge-completed'
                        );
                        $status_class = $status_classes[$order->status] ?? 'zaikon-badge-dark';
                        ?>
                        <span class="zaikon-badge <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html(ucfirst($order->status)); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($order->created_at))); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

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
