<?php
/**
 * Reports and Analytics Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get date range using plugin timezone
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : RPOS_Timezone::now()->modify('-30 days')->format('Y-m-d');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : RPOS_Timezone::now()->format('Y-m-d');
$kitchen_report_date = isset($_GET['kitchen_date']) ? sanitize_text_field($_GET['kitchen_date']) : RPOS_Timezone::now()->format('Y-m-d');
$tab = $_GET['tab'] ?? 'sales';

$date_from_full = $date_from . ' 00:00:00';
$date_to_full = $date_to . ' 23:59:59';

// Get reports data
$sales_summary = RPOS_Reports::get_sales_summary($date_from_full, $date_to_full);
$top_products_qty = RPOS_Reports::get_top_products_by_quantity(10, $date_from_full, $date_to_full);
$top_products_revenue = RPOS_Reports::get_top_products_by_revenue(10, $date_from_full, $date_to_full);
$profit_report = RPOS_Reports::get_profit_report($date_from_full, $date_to_full);
$low_stock = RPOS_Reports::get_low_stock_report();
$kitchen_activity = RPOS_Reports::get_kitchen_activity_report($kitchen_report_date);

$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<style>
.rpos-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.rpos-kpi-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #2271b1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.rpos-kpi-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}
.rpos-kpi-card p {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
}
.rpos-chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

<div class="wrap rpos-reports">
    <h1><?php echo esc_html__('Reports & Analytics', 'restaurant-pos'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=restaurant-pos-reports&tab=sales&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="nav-tab <?php echo $tab === 'sales' ? 'nav-tab-active' : ''; ?>">
            💰 Sales & Profit
        </a>
        <a href="?page=restaurant-pos-reports&tab=products&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="nav-tab <?php echo $tab === 'products' ? 'nav-tab-active' : ''; ?>">
            📦 Product Performance
        </a>
        <a href="?page=restaurant-pos-reports&tab=stock&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="nav-tab <?php echo $tab === 'stock' ? 'nav-tab-active' : ''; ?>">
            📊 Stock & Inventory
        </a>
        <a href="?page=restaurant-pos-reports&tab=kitchen&kitchen_date=<?php echo $kitchen_report_date; ?>" 
           class="nav-tab <?php echo $tab === 'kitchen' ? 'nav-tab-active' : ''; ?>">
            👨‍🍳 Kitchen Activity
        </a>
    </h2>
    
    <?php if ($tab === 'sales'): ?>
        <!-- Sales & Profit Tab -->
        <div class="rpos-chart-container">
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="restaurant-pos-reports">
                <input type="hidden" name="tab" value="sales">
                
                <label for="date_from"><?php echo esc_html__('From:', 'restaurant-pos'); ?></label>
                <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                
                <label for="date_to"><?php echo esc_html__('To:', 'restaurant-pos'); ?></label>
                <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                
                <button type="submit" class="button button-primary"><?php echo esc_html__('Generate Report', 'restaurant-pos'); ?></button>
            </form>
        </div>
        
        <!-- Sales Summary -->
        <div class="rpos-kpi-grid">
            <div class="rpos-kpi-card">
                <h3><?php echo esc_html__('Total Sales', 'restaurant-pos'); ?></h3>
                <p style="color: #2271b1;">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($sales_summary->total_sales ?? 0, 2); ?>
                </p>
            </div>
            
            <div class="rpos-kpi-card">
                <h3><?php echo esc_html__('Total Orders', 'restaurant-pos'); ?></h3>
                <p style="color: #2271b1;">
                    <?php echo number_format($sales_summary->order_count ?? 0); ?>
                </p>
            </div>
            
            <div class="rpos-kpi-card">
                <h3><?php echo esc_html__('Average Order Value', 'restaurant-pos'); ?></h3>
                <p style="color: #2271b1;">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($sales_summary->average_order ?? 0, 2); ?>
                </p>
            </div>
            
            <div class="rpos-kpi-card">
                <h3><?php echo esc_html__('Total Discounts', 'restaurant-pos'); ?></h3>
                <p style="color: #2271b1;">
                    <?php echo esc_html($currency); ?>
                    <?php echo number_format($sales_summary->total_discounts ?? 0, 2); ?>
                </p>
            </div>
        </div>
        
        <!-- Profit Report -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Profit Report', 'restaurant-pos'); ?></h2>
            <?php if ($profit_report && $profit_report->total_revenue > 0): ?>
            <div class="rpos-kpi-grid">
                <div class="rpos-kpi-card">
                    <h3><?php echo esc_html__('Total Revenue', 'restaurant-pos'); ?></h3>
                    <p style="color: #2271b1;">
                        <?php echo esc_html($currency); ?>
                        <?php echo number_format($profit_report->total_revenue, 2); ?>
                    </p>
                </div>
                
                <div class="rpos-kpi-card">
                    <h3><?php echo esc_html__('Total COGS', 'restaurant-pos'); ?></h3>
                    <p style="color: #dc3232;">
                        <?php echo esc_html($currency); ?>
                        <?php echo number_format($profit_report->total_cogs, 2); ?>
                    </p>
                    <p class="description"><?php echo esc_html__('Cost of Goods Sold', 'restaurant-pos'); ?></p>
                </div>
                
                <div class="rpos-kpi-card" style="border-left-color: #46b450;">
                    <h3><?php echo esc_html__('Gross Profit', 'restaurant-pos'); ?></h3>
                    <p style="color: #46b450;">
                        <?php echo esc_html($currency); ?>
                        <?php echo number_format($profit_report->gross_profit, 2); ?>
                    </p>
                </div>
                
                <div class="rpos-kpi-card">
                    <h3><?php echo esc_html__('Profit Margin', 'restaurant-pos'); ?></h3>
                    <p style="color: #2271b1;">
                        <?php echo number_format($profit_report->profit_margin, 2); ?>%
                    </p>
                </div>
            </div>
            <?php else: ?>
            <p><?php echo esc_html__('No profit data available. Make sure cost prices are set for products.', 'restaurant-pos'); ?></p>
            <?php endif; ?>
        </div>
    
    <?php elseif ($tab === 'products'): ?>
        <!-- Product Performance Tab -->
        <div class="rpos-chart-container">
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="restaurant-pos-reports">
                <input type="hidden" name="tab" value="products">
                
                <label for="date_from"><?php echo esc_html__('From:', 'restaurant-pos'); ?></label>
                <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                
                <label for="date_to"><?php echo esc_html__('To:', 'restaurant-pos'); ?></label>
                <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                
                <button type="submit" class="button button-primary"><?php echo esc_html__('Generate Report', 'restaurant-pos'); ?></button>
            </form>
        </div>
        
        <!-- Top Products by Quantity -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Top Products by Quantity Sold', 'restaurant-pos'); ?></h2>
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
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('💰 Top Products by Revenue', 'restaurant-pos'); ?></h2>
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
    
    <?php elseif ($tab === 'stock'): ?>
        <!-- Stock & Inventory Tab -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Low Stock Report', 'restaurant-pos'); ?></h2>
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
                    <tr>
                        <td><?php echo esc_html($item->product_name); ?></td>
                        <td><?php echo esc_html($item->sku ?: '-'); ?></td>
                        <td>
                            <span style="background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold;">
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
            <p style="color: #46b450; font-weight: bold;"><?php echo esc_html__('All products are adequately stocked!', 'restaurant-pos'); ?></p>
            <?php endif; ?>
        </div>
    
    <?php elseif ($tab === 'kitchen'): ?>
        <!-- Kitchen Activity Tab -->
        <div class="rpos-chart-container">
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="restaurant-pos-reports">
                <input type="hidden" name="tab" value="kitchen">
                
                <label for="kitchen_date"><?php echo esc_html__('Date:', 'restaurant-pos'); ?></label>
                <input type="date" name="kitchen_date" id="kitchen_date" value="<?php echo esc_attr($kitchen_report_date); ?>">
                
                <button type="submit" class="button button-primary"><?php echo esc_html__('View Report', 'restaurant-pos'); ?></button>
            </form>
        </div>
        
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Kitchen Activity Report', 'restaurant-pos'); ?></h2>
            <?php if (!empty($kitchen_activity)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Kitchen Staff', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Orders Ready Today', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Orders Cooking', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Total Orders Handled', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Items Prepared', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kitchen_activity as $staff): ?>
                    <tr>
                        <td><strong><?php echo esc_html($staff->user_name ?: 'User #' . $staff->user_id); ?></strong></td>
                        <td><?php echo absint($staff->orders_ready); ?></td>
                        <td><?php echo absint($staff->orders_cooking); ?></td>
                        <td><?php echo absint($staff->total_orders_handled); ?></td>
                        <td>
                            <?php if (!empty($staff->products)): ?>
                                <?php
                                $items = array();
                                foreach ($staff->products as $product) {
                                    $items[] = absint($product->total_quantity) . '× ' . esc_html($product->product_name);
                                }
                                echo implode(', ', $items);
                                ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php echo esc_html__('No kitchen activity recorded for this date.', 'restaurant-pos'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
