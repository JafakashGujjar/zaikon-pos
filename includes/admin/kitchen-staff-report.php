<?php
/**
 * Kitchen Staff Report Page
 * Shows orders prepared, products cooked, and ingredients consumed per kitchen user
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters using plugin timezone
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : RPOS_Timezone::now()->modify('first day of this month')->format('Y-m-d');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : RPOS_Timezone::now()->format('Y-m-d');
$kitchen_user_id = isset($_GET['kitchen_user_id']) ? absint($_GET['kitchen_user_id']) : '';

// Get kitchen staff users
$kitchen_users = get_users(array(
    'role' => 'kitchen_staff',
    'orderby' => 'display_name',
    'order' => 'ASC'
));

// Get report data if user selected
$report_data = null;
if ($kitchen_user_id) {
    $report_data = RPOS_Reports::get_kitchen_staff_report($kitchen_user_id, $date_from, $date_to);
}

// Get currency
$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap rpos-kitchen-staff-report">
    <h1><?php echo esc_html__('Kitchen Staff Report', 'restaurant-pos'); ?></h1>
    
    <div class="rpos-reports-filters" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
        <form method="get" class="rpos-filter-form">
            <input type="hidden" name="page" value="restaurant-pos-kitchen-staff-report">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="kitchen_user_id"><?php echo esc_html__('Kitchen Staff', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select id="kitchen_user_id" name="kitchen_user_id" required>
                            <option value=""><?php echo esc_html__('-- Select Kitchen Staff --', 'restaurant-pos'); ?></option>
                            <?php foreach ($kitchen_users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($kitchen_user_id, $user->ID); ?>>
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="date_from"><?php echo esc_html__('Date From', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="date_to"><?php echo esc_html__('Date To', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Generate Report', 'restaurant-pos'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=restaurant-pos-kitchen-staff-report')); ?>" class="button"><?php echo esc_html__('Reset', 'restaurant-pos'); ?></a>
            </p>
        </form>
    </div>
    
    <?php if ($report_data): ?>
        <div class="rpos-report-summary" style="margin: 20px 0;">
            <h2><?php echo esc_html__('Summary', 'restaurant-pos'); ?></h2>
            
            <div class="rpos-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Orders', 'restaurant-pos'); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($report_data['total_orders']); ?></p>
                </div>
                
                <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Orders Completed', 'restaurant-pos'); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html($report_data['completed_orders']); ?></p>
                </div>
                
                <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Products Prepared', 'restaurant-pos'); ?></h3>
                    <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html(array_sum(array_column($report_data['products'], 'total_quantity'))); ?></p>
                </div>
            </div>
        </div>
        
        <div class="rpos-products-prepared" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
            <h2><?php echo esc_html__('Products Prepared', 'restaurant-pos'); ?></h2>
            
            <?php if (empty($report_data['products'])): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    <?php echo esc_html__('No products prepared in the selected period.', 'restaurant-pos'); ?>
                </p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Product', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Quantity', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Total Sales', 'restaurant-pos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['products'] as $product): ?>
                            <tr>
                                <td><?php echo esc_html($product->product_name); ?></td>
                                <td><strong><?php echo esc_html($product->total_quantity); ?></strong></td>
                                <td><?php echo esc_html($currency . number_format($product->total_sales, 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td><?php echo esc_html__('Total', 'restaurant-pos'); ?></td>
                            <td><?php echo esc_html(array_sum(array_column($report_data['products'], 'total_quantity'))); ?></td>
                            <td><?php echo esc_html($currency . number_format(array_sum(array_column($report_data['products'], 'total_sales')), 2)); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="rpos-ingredients-consumed" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
            <h2><?php echo esc_html__('Ingredients Consumed', 'restaurant-pos'); ?></h2>
            
            <?php if (empty($report_data['ingredients'])): ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    <?php echo esc_html__('No ingredient consumption data available.', 'restaurant-pos'); ?>
                </p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Ingredient', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Consumed', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Unit', 'restaurant-pos'); ?></th>
                            <th><?php echo esc_html__('Current Stock', 'restaurant-pos'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['ingredients'] as $ingredient): ?>
                            <tr>
                                <td><?php echo esc_html($ingredient->name); ?></td>
                                <td><strong><?php echo esc_html(number_format($ingredient->consumed, 3)); ?></strong></td>
                                <td><?php echo esc_html($ingredient->unit); ?></td>
                                <td>
                                    <?php echo esc_html(number_format($ingredient->current_stock, 3)); ?>
                                    <?php if ($ingredient->current_stock < 10): ?>
                                        <span style="color: #d63638; font-weight: bold;"> ⚠ Low Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
