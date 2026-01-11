<?php
/**
 * Batches/Lots Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$ingredient_filter = isset($_GET['ingredient_id']) ? absint($_GET['ingredient_id']) : null;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';

// Get all ingredients for filter
$all_ingredients = RPOS_Ingredients::get_all();

// Get batches
$batches = RPOS_Batches::get_all(array(
    'ingredient_id' => $ingredient_filter,
    'status' => $status_filter,
    'limit' => 200
));

// Calculate summary stats
$total_batches = count($batches);
$total_value = 0;
$expiring_soon = 0;

foreach ($batches as $batch) {
    $total_value += ($batch->quantity_remaining * $batch->cost_per_unit);
    
    if ($batch->expiry_date) {
        $days_until_expiry = floor((strtotime($batch->expiry_date) - time()) / (60 * 60 * 24));
        if ($days_until_expiry <= 7 && $days_until_expiry >= 0) {
            $expiring_soon++;
        }
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Ingredient Batches / Lots', 'restaurant-pos'); ?></h1>
    <hr class="wp-header-end">
    
    <!-- Summary Cards -->
    <div class="rpos-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div class="rpos-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0; font-size: 14px; color: #666;"><?php esc_html_e('Total Batches', 'restaurant-pos'); ?></h3>
            <p style="font-size: 28px; font-weight: bold; margin: 10px 0;"><?php echo esc_html($total_batches); ?></p>
        </div>
        
        <div class="rpos-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0; font-size: 14px; color: #666;"><?php esc_html_e('Total Value', 'restaurant-pos'); ?></h3>
            <p style="font-size: 28px; font-weight: bold; margin: 10px 0; color: #2271b1;">$<?php echo esc_html(number_format($total_value, 2)); ?></p>
        </div>
        
        <div class="rpos-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0; font-size: 14px; color: #666;"><?php esc_html_e('Expiring Soon (7 days)', 'restaurant-pos'); ?></h3>
            <p style="font-size: 28px; font-weight: bold; margin: 10px 0; color: <?php echo $expiring_soon > 0 ? '#d63638' : '#00a32a'; ?>">
                <?php echo esc_html($expiring_soon); ?>
            </p>
        </div>
    </div>
    
    <!-- Filters -->
    <form method="get" action="">
        <input type="hidden" name="page" value="restaurant-pos-batches">
        <div style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <label for="ingredient_id"><?php esc_html_e('Filter by Ingredient:', 'restaurant-pos'); ?></label>
            <select name="ingredient_id" id="ingredient_id" style="margin: 0 10px;">
                <option value=""><?php esc_html_e('All Ingredients', 'restaurant-pos'); ?></option>
                <?php foreach ($all_ingredients as $ing): ?>
                    <option value="<?php echo esc_attr($ing->id); ?>" <?php selected($ingredient_filter, $ing->id); ?>>
                        <?php echo esc_html($ing->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="status"><?php esc_html_e('Status:', 'restaurant-pos'); ?></label>
            <select name="status" id="status" style="margin: 0 10px;">
                <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'restaurant-pos'); ?></option>
                <option value="depleted" <?php selected($status_filter, 'depleted'); ?>><?php esc_html_e('Depleted', 'restaurant-pos'); ?></option>
                <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired', 'restaurant-pos'); ?></option>
                <option value="" <?php selected($status_filter, ''); ?>><?php esc_html_e('All Statuses', 'restaurant-pos'); ?></option>
            </select>
            
            <input type="submit" class="button" value="<?php esc_attr_e('Apply Filters', 'restaurant-pos'); ?>">
            <a href="?page=restaurant-pos-batches" class="button"><?php esc_html_e('Reset', 'restaurant-pos'); ?></a>
        </div>
    </form>
    
    <!-- Batches Table -->
    <div style="background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Batch Number', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Supplier', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Purchase Date', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Expiry Date', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Purchased', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Remaining', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Cost/Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Batch Value', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Status', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($batches)): ?>
                    <?php foreach ($batches as $batch): ?>
                        <?php
                        $batch_value = $batch->quantity_remaining * $batch->cost_per_unit;
                        $expiry_status = 'ok';
                        $days_left = null;
                        
                        if ($batch->expiry_date) {
                            $days_left = floor((strtotime($batch->expiry_date) - time()) / (60 * 60 * 24));
                            if ($days_left < 0) {
                                $expiry_status = 'expired';
                            } elseif ($days_left <= 3) {
                                $expiry_status = 'critical';
                            } elseif ($days_left <= 7) {
                                $expiry_status = 'warning';
                            }
                        }
                        
                        $status_colors = array(
                            'active' => '#00a32a',
                            'depleted' => '#646970',
                            'expired' => '#d63638',
                            'disposed' => '#8c8f94'
                        );
                        
                        $status_color = isset($status_colors[$batch->status]) ? $status_colors[$batch->status] : '#646970';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($batch->batch_number); ?></strong></td>
                            <td><?php echo esc_html($batch->ingredient_name); ?></td>
                            <td><?php echo esc_html($batch->supplier_name ?: '-'); ?></td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($batch->purchase_date))); ?></td>
                            <td>
                                <?php if ($batch->expiry_date): ?>
                                    <span style="padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;
                                          background-color: <?php echo $expiry_status === 'expired' ? '#ffebee' : ($expiry_status === 'critical' ? '#fff3e0' : ($expiry_status === 'warning' ? '#fff8e1' : '#e8f5e9')); ?>;
                                          color: <?php echo $expiry_status === 'expired' ? '#c62828' : ($expiry_status === 'critical' ? '#e65100' : ($expiry_status === 'warning' ? '#f57c00' : '#2e7d32')); ?>;">
                                        <?php echo esc_html(date('M d, Y', strtotime($batch->expiry_date))); ?>
                                        <?php if ($days_left !== null && $days_left >= 0): ?>
                                            (<?php echo esc_html($days_left); ?> days)
                                        <?php elseif ($days_left !== null && $days_left < 0): ?>
                                            (EXPIRED)
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(number_format($batch->quantity_purchased, 3)); ?> <?php echo esc_html($batch->unit); ?></td>
                            <td><strong><?php echo esc_html(number_format($batch->quantity_remaining, 3)); ?> <?php echo esc_html($batch->unit); ?></strong></td>
                            <td>$<?php echo esc_html(number_format($batch->cost_per_unit, 2)); ?></td>
                            <td><strong>$<?php echo esc_html(number_format($batch_value, 2)); ?></strong></td>
                            <td>
                                <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                    ● <?php echo esc_html(ucfirst($batch->status)); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 30px;">
                            <?php esc_html_e('No batches found. Batches are automatically created when you purchase ingredients.', 'restaurant-pos'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
        <h3 style="margin-top: 0;"><?php esc_html_e('About Batch Tracking', 'restaurant-pos'); ?></h3>
        <p><?php esc_html_e('Batches are automatically created when you purchase ingredients. The system uses FIFO (First In First Out) or FEFO (First Expire First Out) strategy to consume from batches during sales.', 'restaurant-pos'); ?></p>
        <p><strong><?php esc_html_e('Current Strategy:', 'restaurant-pos'); ?></strong> <?php echo esc_html(RPOS_Inventory_Settings::get('consumption_strategy', 'FEFO')); ?></p>
        <p><a href="?page=restaurant-pos-inventory-settings" class="button"><?php esc_html_e('Change Strategy', 'restaurant-pos'); ?></a></p>
    </div>
</div>
