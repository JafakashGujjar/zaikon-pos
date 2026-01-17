<?php
/**
 * Delivery Customers Dashboard (Zaikon v2)
 * SQL-based analytics grouped by customer phone
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get filters from request with validation
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
$min_deliveries = isset($_GET['min_deliveries']) ? absint($_GET['min_deliveries']) : 0;

// Validate sort_by against whitelist
$allowed_sort_options = array('deliveries', 'amount', 'date');
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed_sort_options) 
    ? sanitize_text_field($_GET['sort_by']) 
    : 'deliveries';

// Build query
$where_conditions = array("d.created_at >= %s", "d.created_at <= %s");
$where_values = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');

if ($min_deliveries > 0) {
    // Will be applied in HAVING clause
}

// Determine ORDER BY clause
$order_clause = "ORDER BY deliveries_count DESC, total_amount_spent DESC";
if ($sort_by === 'amount') {
    $order_clause = "ORDER BY total_amount_spent DESC, deliveries_count DESC";
} elseif ($sort_by === 'date') {
    $order_clause = "ORDER BY last_delivery_date DESC";
}

// Execute query
$query = "
    SELECT 
        d.customer_phone,
        MAX(d.customer_name) as customer_name,
        COUNT(d.id) as deliveries_count,
        SUM(d.delivery_charges_rs) as total_delivery_charges,
        MIN(d.created_at) as first_delivery_date,
        MAX(d.created_at) as last_delivery_date,
        SUM(o.grand_total_rs) as total_amount_spent,
        AVG(o.grand_total_rs) as avg_order_amount,
        AVG(d.delivery_charges_rs) as avg_delivery_charge,
        (
            SELECT location_name 
            FROM {$wpdb->prefix}zaikon_deliveries d2 
            WHERE d2.customer_phone = d.customer_phone 
            GROUP BY location_name 
            ORDER BY COUNT(*) DESC 
            LIMIT 1
        ) as primary_location_name
    FROM {$wpdb->prefix}zaikon_deliveries d
    INNER JOIN {$wpdb->prefix}zaikon_orders o ON d.order_id = o.id
    WHERE " . implode(' AND ', $where_conditions) . "
    GROUP BY d.customer_phone
";

if ($min_deliveries > 0) {
    $query .= " HAVING deliveries_count >= " . intval($min_deliveries);
}

$query .= " " . $order_clause;

$customers = $wpdb->get_results($wpdb->prepare($query, $where_values));

// Get summary stats
$total_customers = count($customers);
$total_deliveries = array_sum(array_column($customers, 'deliveries_count'));
$total_revenue = array_sum(array_column($customers, 'total_amount_spent'));
$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap">
    <h1><?php echo esc_html__('Delivery Customers Analytics', 'restaurant-pos'); ?></h1>
    
    <!-- Filters -->
    <div class="zaikon-card" style="margin-bottom: 20px; padding: 20px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="restaurant-pos-delivery-customers">
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                <div>
                    <label><strong><?php echo esc_html__('Date From:', 'restaurant-pos'); ?></strong></label><br>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" required>
                </div>
                
                <div>
                    <label><strong><?php echo esc_html__('Date To:', 'restaurant-pos'); ?></strong></label><br>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" required>
                </div>
                
                <div>
                    <label><strong><?php echo esc_html__('Min. Deliveries:', 'restaurant-pos'); ?></strong></label><br>
                    <input type="number" name="min_deliveries" value="<?php echo esc_attr($min_deliveries); ?>" min="0" style="width: 100px;">
                </div>
                
                <div>
                    <label><strong><?php echo esc_html__('Sort By:', 'restaurant-pos'); ?></strong></label><br>
                    <select name="sort_by">
                        <option value="deliveries" <?php selected($sort_by, 'deliveries'); ?>><?php echo esc_html__('Total Deliveries', 'restaurant-pos'); ?></option>
                        <option value="amount" <?php selected($sort_by, 'amount'); ?>><?php echo esc_html__('Total Amount', 'restaurant-pos'); ?></option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Filter', 'restaurant-pos'); ?></button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="zaikon-card" style="padding: 20px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold; color: var(--zaikon-blue);"><?php echo number_format($total_customers); ?></div>
            <div style="color: #666; margin-top: 8px;"><?php echo esc_html__('Total Customers', 'restaurant-pos'); ?></div>
        </div>
        
        <div class="zaikon-card" style="padding: 20px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold; color: var(--zaikon-green);"><?php echo number_format($total_deliveries); ?></div>
            <div style="color: #666; margin-top: 8px;"><?php echo esc_html__('Total Deliveries', 'restaurant-pos'); ?></div>
        </div>
        
        <div class="zaikon-card" style="padding: 20px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold; color: var(--zaikon-yellow);"><?php echo esc_html($currency) . number_format($total_revenue, 2); ?></div>
            <div style="color: #666; margin-top: 8px;"><?php echo esc_html__('Total Revenue', 'restaurant-pos'); ?></div>
        </div>
    </div>
    
    <!-- Customers Table -->
    <div class="zaikon-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Customer Phone', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Customer Name', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Primary Location', 'restaurant-pos'); ?></th>
                    <th class="text-center"><?php echo esc_html__('Deliveries', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('First Order', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Last Order', 'restaurant-pos'); ?></th>
                    <th class="text-right"><?php echo esc_html__('Total Delivery Charges', 'restaurant-pos'); ?></th>
                    <th class="text-right"><?php echo esc_html__('Total Order Amount', 'restaurant-pos'); ?></th>
                    <th class="text-right"><?php echo esc_html__('Avg Order Amount', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            <?php echo esc_html__('No delivery customers found for the selected period.', 'restaurant-pos'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?php echo esc_html($customer->customer_phone); ?></strong></td>
                            <td><?php echo esc_html($customer->customer_name); ?></td>
                            <td><?php echo esc_html($customer->primary_location_name ?: '-'); ?></td>
                            <td class="text-center">
                                <span style="background: var(--zaikon-blue); color: white; padding: 4px 12px; border-radius: 12px; font-weight: bold;">
                                    <?php echo esc_html($customer->deliveries_count); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(RPOS_Timezone::format($customer->first_delivery_date, 'Y-m-d')); ?></td>
                            <td><?php echo esc_html(RPOS_Timezone::format($customer->last_delivery_date, 'Y-m-d')); ?></td>
                            <td class="text-right"><?php echo esc_html($currency . number_format($customer->total_delivery_charges, 2)); ?></td>
                            <td class="text-right"><strong><?php echo esc_html($currency . number_format($customer->total_amount_spent, 2)); ?></strong></td>
                            <td class="text-right"><?php echo esc_html($currency . number_format($customer->avg_order_amount, 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.text-center {
    text-align: center !important;
}
.text-right {
    text-align: right !important;
}
.zaikon-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
}
</style>
