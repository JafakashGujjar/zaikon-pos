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

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = date('Y-m-d');
}

// Get usage report
$report_data = RPOS_Ingredients::get_usage_report($date_from . ' 00:00:00', $date_to . ' 23:59:59');
$currency_symbol = RPOS_Settings::get('currency_symbol', '$');
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
