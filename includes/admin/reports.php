<?php
/**
 * Reports and Analytics Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get date range
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

$date_from_full = $date_from . ' 00:00:00';
$date_to_full = $date_to . ' 23:59:59';

// Get reports data
$sales_summary = RPOS_Reports::get_sales_summary($date_from_full, $date_to_full);
$top_products_qty = RPOS_Reports::get_top_products_by_quantity(10, $date_from_full, $date_to_full);
$top_products_revenue = RPOS_Reports::get_top_products_by_revenue(10, $date_from_full, $date_to_full);
$profit_report = RPOS_Reports::get_profit_report($date_from_full, $date_to_full);
$low_stock = RPOS_Reports::get_low_stock_report();

$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap rpos-reports">
    <h1><?php echo esc_html__('Reports & Analytics', 'restaurant-pos'); ?></h1>
    
    <div class="rpos-filters">
        <form method="get" class="rpos-filter-form">
            <input type="hidden" name="page" value="restaurant-pos-reports">
            
            <label for="date_from"><?php echo esc_html__('From:', 'restaurant-pos'); ?></label>
            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
            
            <label for="date_to"><?php echo esc_html__('To:', 'restaurant-pos'); ?></label>
            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
            
            <button type="submit" class="button button-primary"><?php echo esc_html__('Generate Report', 'restaurant-pos'); ?></button>
        </form>
    </div>
    
    <!-- Sales Summary -->
    <div class="rpos-report-section">
        <h2><?php echo esc_html__('Sales Summary', 'restaurant-pos'); ?></h2>
        <div class="rpos-summary-cards">
            <div class="rpos-card">
                <h3><?php echo esc_html__('Total Sales', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($sales_summary->total_sales ?? 0, 2); ?>
                </p>
            </div>
            
            <div class="rpos-card">
                <h3><?php echo esc_html__('Total Orders', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo number_format($sales_summary->order_count ?? 0); ?>
                </p>
            </div>
            
            <div class="rpos-card">
                <h3><?php echo esc_html__('Average Order Value', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($sales_summary->average_order ?? 0, 2); ?>
                </p>
            </div>
            
            <div class="rpos-card">
                <h3><?php echo esc_html__('Total Discounts', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($sales_summary->total_discounts ?? 0, 2); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Top Products by Quantity -->
    <div class="rpos-report-section">
        <h2><?php echo esc_html__('Top Products by Quantity Sold', 'restaurant-pos'); ?></h2>
        <?php if (!empty($top_products_qty)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Rank', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Product Name', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Quantity Sold', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Total Revenue', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach ($top_products_qty as $product): ?>
                <tr>
                    <td><strong><?php echo absint($rank++); ?></strong></td>
                    <td><?php echo esc_html($product->product_name); ?></td>
                    <td><?php echo number_format($product->total_quantity); ?></td>
                    <td>
                        <?php echo esc_html($currency); ?>
                        <?php echo number_format($product->total_revenue, 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php echo esc_html__('No data available for this period.', 'restaurant-pos'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Top Products by Revenue -->
    <div class="rpos-report-section">
        <h2><?php echo esc_html__('Top Products by Revenue', 'restaurant-pos'); ?></h2>
        <?php if (!empty($top_products_revenue)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Rank', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Product Name', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Quantity Sold', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Total Revenue', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach ($top_products_revenue as $product): ?>
                <tr>
                    <td><strong><?php echo absint($rank++); ?></strong></td>
                    <td><?php echo esc_html($product->product_name); ?></td>
                    <td><?php echo number_format($product->total_quantity); ?></td>
                    <td>
                        <?php echo esc_html($currency); ?>
                        <?php echo number_format($product->total_revenue, 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php echo esc_html__('No data available for this period.', 'restaurant-pos'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Profit Report -->
    <div class="rpos-report-section">
        <h2><?php echo esc_html__('Profit Report', 'restaurant-pos'); ?></h2>
        <?php if ($profit_report && $profit_report->total_revenue > 0): ?>
        <div class="rpos-summary-cards">
            <div class="rpos-card">
                <h3><?php echo esc_html__('Total Revenue', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($profit_report->total_revenue, 2); ?>
                </p>
            </div>
            
            <div class="rpos-card">
                <h3><?php echo esc_html__('Total COGS', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($profit_report->total_cogs, 2); ?>
                </p>
                <p class="description"><?php echo esc_html__('Cost of Goods Sold', 'restaurant-pos'); ?></p>
            </div>
            
            <div class="rpos-card rpos-card-success">
                <h3><?php echo esc_html__('Gross Profit', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($profit_report->gross_profit, 2); ?>
                </p>
            </div>
            
            <div class="rpos-card">
                <h3><?php echo esc_html__('Profit Margin', 'restaurant-pos'); ?></h3>
                <p class="rpos-big-number">
                    <?php echo number_format($profit_report->profit_margin, 2); ?>%
                </p>
            </div>
        </div>
        <?php else: ?>
        <p><?php echo esc_html__('No profit data available. Make sure cost prices are set for products.', 'restaurant-pos'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Low Stock Report -->
    <div class="rpos-report-section">
        <h2><?php echo esc_html__('Low Stock Report', 'restaurant-pos'); ?></h2>
        <?php if (!empty($low_stock)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Product', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('SKU', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Current Stock', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Cost Price', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock as $item): ?>
                <tr class="rpos-low-stock">
                    <td><?php echo esc_html($item->product_name); ?></td>
                    <td><?php echo esc_html($item->sku ?: '-'); ?></td>
                    <td>
                        <span class="rpos-badge rpos-badge-warning">
                            <?php echo absint($item->quantity); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo esc_html($currency); ?>
                        <?php echo number_format($item->cost_price, 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="rpos-success-message"><?php echo esc_html__('All products are adequately stocked!', 'restaurant-pos'); ?></p>
        <?php endif; ?>
    </div>
</div>
