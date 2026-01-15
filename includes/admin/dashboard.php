<?php
/**
 * ZAIKON POS - Dashboard
 * Modern SaaS-style dashboard with charts and KPIs
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get currency symbol
$currency = RPOS_Settings::get('currency_symbol', '$');

// Get today's data
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$today_sales = RPOS_Reports::get_sales_summary($today_start, $today_end);

// Get this week's data for chart
$week_start = date('Y-m-d 00:00:00', strtotime('-6 days'));
$week_end = date('Y-m-d 23:59:59');

// Get daily sales for the past 7 days
global $wpdb;
$daily_sales = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        DATE(created_at) as date,
        COUNT(*) as order_count,
        SUM(total) as total_sales
    FROM {$wpdb->prefix}rpos_orders
    WHERE status = 'completed'
        AND created_at >= %s
        AND created_at <= %s
    GROUP BY DATE(created_at)
    ORDER BY date ASC",
    $week_start,
    $week_end
));

// Fill in missing dates with zero values
$sales_by_date = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $sales_by_date[$date] = array('order_count' => 0, 'total_sales' => 0);
}

foreach ($daily_sales as $day) {
    $sales_by_date[$day->date] = array(
        'order_count' => $day->order_count,
        'total_sales' => $day->total_sales
    );
}

// Get sales by order type (for today)
$sales_by_type = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        order_type,
        COUNT(*) as order_count,
        SUM(total) as total_sales
    FROM {$wpdb->prefix}rpos_orders
    WHERE status = 'completed'
        AND created_at >= %s
        AND created_at <= %s
    GROUP BY order_type",
    $today_start,
    $today_end
));

// Get category sales (top 5 categories by revenue today)
$category_sales = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        p.category_id,
        c.name as category_name,
        SUM(oi.line_total) as total_sales,
        SUM(oi.quantity) as total_quantity
    FROM {$wpdb->prefix}rpos_order_items oi
    INNER JOIN {$wpdb->prefix}rpos_orders o ON oi.order_id = o.id
    LEFT JOIN {$wpdb->prefix}rpos_products p ON oi.product_id = p.id
    LEFT JOIN {$wpdb->prefix}rpos_categories c ON p.category_id = c.id
    WHERE o.status = 'completed'
        AND o.created_at >= %s
        AND o.created_at <= %s
        AND c.name IS NOT NULL
    GROUP BY p.category_id, c.name
    ORDER BY total_sales DESC
    LIMIT 5",
    $today_start,
    $today_end
));

// Get recent orders
$recent_orders = RPOS_Orders::get_all(array('limit' => 8));

// Get top selling products (by quantity) today
$top_products = RPOS_Reports::get_top_products_by_quantity(5, $today_start, $today_end);

// Get delivery revenue breakdown for today (if delivery system is active)
$delivery_revenue = array();
try {
    $delivery_summary = Zaikon_Deliveries::get_delivery_summary($today_start, $today_end);
    if ($delivery_summary) {
        $delivery_revenue = $delivery_summary;
    }
} catch (Exception $e) {
    // Delivery system not available or no data
}

// Get current user info
$current_user = wp_get_current_user();
$user_avatar_url = get_avatar_url($current_user->ID);
$user_display_name = $current_user->display_name;

// Enqueue Chart.js from CDN
wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);
?>

<!-- Modern Dashboard Layout -->
<div class="zaikon-modern-dashboard">
    <!-- Top Bar -->
    <div class="zaikon-dashboard-topbar">
        <div class="zaikon-topbar-left">
            <h1 class="zaikon-page-title"><?php echo esc_html__('Dashboard', 'restaurant-pos'); ?></h1>
        </div>
        <div class="zaikon-topbar-right">
            <div class="zaikon-search-box">
                <span class="dashicons dashicons-search"></span>
                <input type="text" placeholder="<?php echo esc_attr__('Search...', 'restaurant-pos'); ?>" class="zaikon-search-input">
            </div>
            <button class="zaikon-icon-btn" title="<?php echo esc_attr__('Notifications', 'restaurant-pos'); ?>">
                <span class="dashicons dashicons-bell"></span>
            </button>
            <div class="zaikon-user-profile">
                <img src="<?php echo esc_url($user_avatar_url); ?>" alt="<?php echo esc_attr($user_display_name); ?>" class="zaikon-user-avatar">
                <span class="zaikon-user-name"><?php echo esc_html($user_display_name); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="zaikon-dashboard-content">
        <!-- Left Column: Main Content -->
        <div class="zaikon-dashboard-left">
            <!-- KPI Cards -->
            <div class="zaikon-kpi-grid">
                <div class="zaikon-kpi-card zaikon-kpi-primary">
                    <div class="zaikon-kpi-header">
                        <div class="zaikon-kpi-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <span class="zaikon-kpi-label"><?php echo esc_html__('Total Sales Today', 'restaurant-pos'); ?></span>
                    </div>
                    <div class="zaikon-kpi-value">
                        <?php echo esc_html($currency); ?><?php echo number_format($today_sales->total_sales ?? 0, 2); ?>
                    </div>
                    <div class="zaikon-kpi-meta">
                        <?php echo absint($today_sales->order_count ?? 0); ?> <?php echo esc_html__('orders completed today', 'restaurant-pos'); ?>
                    </div>
                </div>

                <div class="zaikon-kpi-card">
                    <div class="zaikon-kpi-header">
                        <div class="zaikon-kpi-icon" style="background: #4CAF50;">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <span class="zaikon-kpi-label"><?php echo esc_html__('Total Orders', 'restaurant-pos'); ?></span>
                    </div>
                    <div class="zaikon-kpi-value">
                        <?php echo absint($today_sales->order_count ?? 0); ?>
                    </div>
                    <div class="zaikon-kpi-meta">
                        <?php echo esc_html__('completed today', 'restaurant-pos'); ?>
                    </div>
                </div>

                <div class="zaikon-kpi-card">
                    <div class="zaikon-kpi-header">
                        <div class="zaikon-kpi-icon" style="background: #2196F3;">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <span class="zaikon-kpi-label"><?php echo esc_html__('Average Order', 'restaurant-pos'); ?></span>
                    </div>
                    <div class="zaikon-kpi-value">
                        <?php echo esc_html($currency); ?><?php echo number_format($today_sales->average_order ?? 0, 2); ?>
                    </div>
                    <div class="zaikon-kpi-meta">
                        <?php echo esc_html__('per order today', 'restaurant-pos'); ?>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="zaikon-charts-row">
                <!-- Sales Chart -->
                <div class="zaikon-chart-card zaikon-chart-large">
                    <div class="zaikon-chart-header">
                        <h3 class="zaikon-chart-title"><?php echo esc_html__('Sales Figures (Last 7 Days)', 'restaurant-pos'); ?></h3>
                    </div>
                    <div class="zaikon-chart-body">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>

                <!-- Category Chart -->
                <div class="zaikon-chart-card">
                    <div class="zaikon-chart-header">
                        <h3 class="zaikon-chart-title"><?php echo esc_html__('Sales by Category', 'restaurant-pos'); ?></h3>
                    </div>
                    <div class="zaikon-chart-body">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Selling Items -->
            <div class="zaikon-table-card">
                <div class="zaikon-table-header">
                    <h3 class="zaikon-table-title"><?php echo esc_html__('Top 5 Selling Items (Today)', 'restaurant-pos'); ?></h3>
                </div>
                <div class="zaikon-table-body">
                    <table class="zaikon-modern-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Item Name', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Quantity', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Total (Rs)', 'restaurant-pos'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($top_products)): ?>
                                <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($product->product_name); ?></strong></td>
                                    <td><?php echo absint($product->quantity_sold); ?></td>
                                    <td><?php echo esc_html($currency . number_format($product->total_revenue, 2)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #999;">
                                        <?php echo esc_html__('No sales data available for today', 'restaurant-pos'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Summary Panel -->
        <div class="zaikon-dashboard-right">
            <!-- Today Summary Widget -->
            <div class="zaikon-summary-card">
                <h3 class="zaikon-summary-title"><?php echo esc_html__('Today Summary', 'restaurant-pos'); ?></h3>
                <div class="zaikon-summary-items">
                    <div class="zaikon-summary-item">
                        <div class="zaikon-summary-label"><?php echo esc_html__('Total Sales', 'restaurant-pos'); ?></div>
                        <div class="zaikon-summary-value" style="color: #f97316;">
                            <?php echo esc_html($currency); ?><?php echo number_format($today_sales->total_sales ?? 0, 2); ?>
                        </div>
                    </div>
                    <?php
                    // Calculate sales by type
                    $dine_in_sales = 0;
                    $takeaway_sales = 0;
                    $delivery_sales = 0;
                    foreach ($sales_by_type as $type) {
                        if ($type->order_type === 'dine-in') {
                            $dine_in_sales = floatval($type->total_sales);
                        } elseif ($type->order_type === 'takeaway') {
                            $takeaway_sales = floatval($type->total_sales);
                        } elseif ($type->order_type === 'delivery') {
                            $delivery_sales = floatval($type->total_sales);
                        }
                    }
                    ?>
                    <div class="zaikon-summary-item">
                        <div class="zaikon-summary-label"><?php echo esc_html__('Dine-in Sales', 'restaurant-pos'); ?></div>
                        <div class="zaikon-summary-value">
                            <?php echo esc_html($currency); ?><?php echo number_format($dine_in_sales, 2); ?>
                        </div>
                    </div>
                    <div class="zaikon-summary-item">
                        <div class="zaikon-summary-label"><?php echo esc_html__('Takeaway Sales', 'restaurant-pos'); ?></div>
                        <div class="zaikon-summary-value">
                            <?php echo esc_html($currency); ?><?php echo number_format($takeaway_sales, 2); ?>
                        </div>
                    </div>
                    <div class="zaikon-summary-item">
                        <div class="zaikon-summary-label"><?php echo esc_html__('Delivery Sales', 'restaurant-pos'); ?></div>
                        <div class="zaikon-summary-value">
                            <?php echo esc_html($currency); ?><?php echo number_format($delivery_sales, 2); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Widget -->
            <div class="zaikon-summary-card">
                <h3 class="zaikon-summary-title"><?php echo esc_html__('Last Orders', 'restaurant-pos'); ?></h3>
                <div class="zaikon-recent-orders">
                    <?php if (!empty($recent_orders)): ?>
                        <?php foreach (array_slice($recent_orders, 0, 8) as $order): ?>
                        <div class="zaikon-recent-order-item">
                            <div class="zaikon-recent-order-header">
                                <span class="zaikon-recent-order-number">#<?php echo esc_html($order->order_number); ?></span>
                                <span class="zaikon-recent-order-type zaikon-badge-<?php echo esc_attr($order->order_type); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('-', ' ', $order->order_type))); ?>
                                </span>
                            </div>
                            <div class="zaikon-recent-order-footer">
                                <span class="zaikon-recent-order-time">
                                    <?php echo esc_html(date_i18n('g:i A', strtotime($order->created_at))); ?>
                                </span>
                                <span class="zaikon-recent-order-amount">
                                    <?php echo esc_html($currency . number_format($order->total, 2)); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #999; padding: 20px 0;">
                            <?php echo esc_html__('No recent orders', 'restaurant-pos'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if (current_user_can('rpos_view_orders')): ?>
                <a href="<?php echo admin_url('admin.php?page=restaurant-pos-orders'); ?>" class="zaikon-btn-link" style="display: block; text-align: center; margin-top: 10px;">
                    <?php echo esc_html__('View All Orders', 'restaurant-pos'); ?> →
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer Credit -->
    <div class="zaikon-dashboard-footer">
        <p><?php echo esc_html__('Powered by: Muhammad Jafakash Nawaz', 'restaurant-pos'); ?></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Chart.js to load
    function initCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts, 50);
            return;
        }

        // Sales Chart Data
        const salesData = {
        labels: [
            <?php 
            foreach ($sales_by_date as $date => $data) {
                echo "'" . date('M j', strtotime($date)) . "',";
            }
            ?>
        ],
        datasets: [{
            label: '<?php echo esc_js(__('Sales', 'restaurant-pos')); ?>',
            data: [<?php 
                foreach ($sales_by_date as $data) {
                    echo floatval($data['total_sales']) . ',';
                }
            ?>],
            borderColor: '#FFC107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    };

    // Sales Chart Configuration
    const salesChart = new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: salesData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?php echo esc_js($currency); ?>' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });

    // Category Chart Data
    const categoryData = {
        labels: [
            <?php 
            foreach ($category_sales as $cat) {
                echo "'" . esc_js($cat->category_name ?? 'Other') . "',";
            }
            ?>
        ],
        datasets: [{
            label: '<?php echo esc_js(__('Revenue', 'restaurant-pos')); ?>',
            data: [<?php 
                foreach ($category_sales as $cat) {
                    echo floatval($cat->total_sales) . ',';
                }
            ?>],
            backgroundColor: [
                '#FFC107',
                '#4CAF50',
                '#2196F3',
                '#FF9800',
                '#9C27B0'
            ],
            borderWidth: 0
        }]
    };

    // Category Chart Configuration
    const categoryChart = new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: categoryData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?php echo esc_js($currency); ?>' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });
    
    // Initialize charts
    initCharts();
});
</script>
