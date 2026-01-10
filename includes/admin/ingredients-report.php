<?php
/**
 * Ingredients Usage Report Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get date range from request
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Validate and sanitize dates
function validate_date($date) {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
        return false;
    }
    $year = (int)$matches[1];
    $month = (int)$matches[2];
    $day = (int)$matches[3];
    return checkdate($month, $day, $year) ? $date : false;
}

$date_from = validate_date($date_from) ?: date('Y-m-d');
$date_to = validate_date($date_to) ?: date('Y-m-d');

// Get usage report
$report_data = RPOS_Ingredients::get_usage_report($date_from . ' 00:00:00', $date_to . ' 23:59:59');
$currency_symbol = RPOS_Settings::get('currency_symbol', '$');

// Calculate total inventory value
$all_ingredients = RPOS_Ingredients::get_all();
$total_inventory_value = 0;
foreach ($all_ingredients as $ing) {
    $total_inventory_value += floatval($ing->current_stock_quantity) * floatval($ing->cost_per_unit);
}

// Get most-used ingredients for the selected date range
global $wpdb;
$most_used_query = "
    SELECT 
        i.name,
        i.unit,
        ABS(SUM(im.change_amount)) as total_used
    FROM {$wpdb->prefix}rpos_ingredient_movements im
    INNER JOIN {$wpdb->prefix}rpos_ingredients i ON im.ingredient_id = i.id
    WHERE im.movement_type = 'Consumption'
    AND im.created_at >= %s
    AND im.created_at <= %s
    GROUP BY im.ingredient_id, i.name, i.unit
    ORDER BY total_used DESC
    LIMIT 10
";
$most_used_ingredients = $wpdb->get_results($wpdb->prepare($most_used_query, $date_from . ' 00:00:00', $date_to . ' 23:59:59'));
?>

<div class="wrap">
    <h1><?php esc_html_e('Ingredients Usage Report', 'restaurant-pos'); ?></h1>
    
    <form method="get" action="">
        <input type="hidden" name="page" value="restaurant-pos-ingredients-report">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="date_from"><?php esc_html_e('From Date', 'restaurant-pos'); ?></label>
                </th>
                <td>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                </td>
                
                <th scope="row">
                    <label for="date_to"><?php esc_html_e('To Date', 'restaurant-pos'); ?></label>
                </th>
                <td>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                </td>
                
                <td>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'restaurant-pos'); ?></button>
                    <a href="?page=restaurant-pos-ingredients-report" class="button"><?php esc_html_e('Reset', 'restaurant-pos'); ?></a>
                </td>
            </tr>
        </table>
    </form>
    
    <hr>
    
    <!-- Total Inventory Value Dashboard -->
    <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2><?php esc_html_e('Total Inventory Value', 'restaurant-pos'); ?></h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px; color: white;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;"><?php esc_html_e('Current Total Value', 'restaurant-pos'); ?></h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold;"><?php echo esc_html($currency_symbol . number_format($total_inventory_value, 2)); ?></p>
            </div>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef;">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #6c757d;"><?php esc_html_e('Total Ingredients', 'restaurant-pos'); ?></h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #495057;"><?php echo count($all_ingredients); ?></p>
            </div>
        </div>
        
        <h3 style="margin-top: 20px; margin-bottom: 10px;"><?php esc_html_e('Value Breakdown by Ingredient', 'restaurant-pos'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Current Stock', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Cost per Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Total Value', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_ingredients)): ?>
                    <?php foreach ($all_ingredients as $ing): ?>
                        <?php $item_value = floatval($ing->current_stock_quantity) * floatval($ing->cost_per_unit); ?>
                        <tr>
                            <td><strong><?php echo esc_html($ing->name); ?></strong></td>
                            <td><?php echo esc_html($ing->unit); ?></td>
                            <td><?php echo esc_html(number_format($ing->current_stock_quantity, 3)); ?></td>
                            <td><?php echo esc_html($currency_symbol . number_format($ing->cost_per_unit, 2)); ?></td>
                            <td><strong><?php echo esc_html($currency_symbol . number_format($item_value, 2)); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No ingredients found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Most-Used Ingredients Section -->
    <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h2><?php esc_html_e('Most-Used Ingredients', 'restaurant-pos'); ?></h2>
        <p><?php printf(esc_html__('Top ingredients consumed from %s to %s', 'restaurant-pos'), esc_html($date_from), esc_html($date_to)); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Rank', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Used Quantity (period)', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($most_used_ingredients)): ?>
                    <?php $rank = 1; ?>
                    <?php foreach ($most_used_ingredients as $item): ?>
                        <tr>
                            <td><strong>#<?php echo $rank++; ?></strong></td>
                            <td><strong><?php echo esc_html($item->name); ?></strong></td>
                            <td><?php echo esc_html($item->unit); ?></td>
                            <td style="color: #dc3545; font-weight: bold;"><?php echo esc_html(number_format(floatval($item->total_used), 3)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('No consumption data available for the selected date range.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <hr>
    
    <h2><?php esc_html_e('Usage Summary', 'restaurant-pos'); ?></h2>
    <p><?php printf(esc_html__('Showing data from %s to %s', 'restaurant-pos'), esc_html($date_from), esc_html($date_to)); ?></p>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Unit', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Purchased', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Consumed', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Current Balance', 'restaurant-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($report_data)): ?>
                <?php foreach ($report_data as $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html($row->name); ?></strong></td>
                        <td><?php echo esc_html($row->unit); ?></td>
                        <td><?php echo esc_html(number_format(floatval($row->total_purchased), 3)); ?></td>
                        <td><?php echo esc_html(number_format(floatval($row->total_consumed), 3)); ?></td>
                        <td>
                            <strong><?php echo esc_html(number_format(floatval($row->current_stock_quantity), 3)); ?></strong>
                            <?php if (floatval($row->current_stock_quantity) < 10): ?>
                                <span style="color: #dc3232;"><?php esc_html_e('(Low Stock)', 'restaurant-pos'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5"><?php esc_html_e('No data available for the selected date range.', 'restaurant-pos'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <br>
    <p>
        <a href="?page=restaurant-pos-ingredients" class="button"><?php esc_html_e('Back to Ingredients', 'restaurant-pos'); ?></a>
    </p>
</div>
