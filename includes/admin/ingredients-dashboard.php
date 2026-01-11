<?php
/**
 * Ingredients Stock Intelligence Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get expiry warning days from settings
$expiry_warning_days = RPOS_Inventory_Settings::get('expiry_warning_days', 7);

// Section A: Batches Near Expiry (using batch data instead of ingredient expiry)
$expiring_batches = RPOS_Batches::get_expiring_batches($expiry_warning_days);

// Section B: Low Stock Alerts
$low_stock_ingredients = $wpdb->get_results(
    "SELECT *,
            (reorder_level - current_stock_quantity) as shortage_amount
     FROM {$wpdb->prefix}rpos_ingredients 
     WHERE current_stock_quantity <= reorder_level
     AND reorder_level > 0
     ORDER BY current_stock_quantity ASC"
);

// Section C: Fast-Moving Ingredients (Top Consumed)
$fast_moving_ingredients = $wpdb->get_results(
    "SELECT 
        i.id,
        i.name,
        i.unit,
        i.current_stock_quantity,
        ABS(SUM(im.change_amount)) as total_consumed,
        DATEDIFF(MAX(im.created_at), MIN(im.created_at)) as days_tracked,
        CASE 
            WHEN DATEDIFF(MAX(im.created_at), MIN(im.created_at)) > 0 
            THEN ABS(SUM(im.change_amount)) / DATEDIFF(MAX(im.created_at), MIN(im.created_at))
            ELSE 0 
        END as avg_daily_usage,
        CASE 
            WHEN (ABS(SUM(im.change_amount)) / DATEDIFF(MAX(im.created_at), MIN(im.created_at))) > 0
            THEN i.current_stock_quantity / (ABS(SUM(im.change_amount)) / DATEDIFF(MAX(im.created_at), MIN(im.created_at)))
            ELSE 999999
        END as days_remaining
    FROM {$wpdb->prefix}rpos_ingredient_movements im
    JOIN {$wpdb->prefix}rpos_ingredients i ON im.ingredient_id = i.id
    WHERE im.movement_type IN ('Consumption', 'Waste')
    AND im.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY im.ingredient_id
    HAVING avg_daily_usage > 0
    ORDER BY avg_daily_usage DESC
    LIMIT 20"
);

// Section D: Supplier Performance (Top 10)
$supplier_performance = $wpdb->get_results(
    "SELECT 
        s.id,
        s.supplier_name,
        s.rating,
        COUNT(DISTINCT b.id) as total_batches,
        SUM(b.quantity_purchased) as total_quantity_purchased,
        AVG(b.cost_per_unit) as avg_cost_per_unit,
        SUM(CASE WHEN b.status = 'active' THEN b.quantity_remaining * b.cost_per_unit ELSE 0 END) as current_inventory_value
    FROM {$wpdb->prefix}rpos_suppliers s
    LEFT JOIN {$wpdb->prefix}rpos_ingredient_batches b ON s.id = b.supplier_id
    WHERE b.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    GROUP BY s.id
    ORDER BY total_batches DESC
    LIMIT 10"
);

// Section E: Inventory Valuation
$total_valuation = RPOS_Batches::get_inventory_valuation();

// Section F: Current consumption strategy
$consumption_strategy = RPOS_Inventory_Settings::get('consumption_strategy', 'FEFO');

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Stock Intelligence Dashboard', 'restaurant-pos'); ?></h1>
    <a href="?page=restaurant-pos-inventory-settings" class="page-title-action"><?php esc_html_e('Settings', 'restaurant-pos'); ?></a>
    <hr class="wp-header-end">
    
    <!-- Summary Cards -->
    <div class="rpos-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div class="rpos-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #2271b1;">
            <h3 style="margin: 0; font-size: 14px; color: #666; text-transform: uppercase;"><?php esc_html_e('Inventory Value', 'restaurant-pos'); ?></h3>
            <p style="font-size: 28px; font-weight: bold; margin: 10px 0; color: #2271b1;">
                $<?php echo esc_html(number_format($total_valuation, 2)); ?>
            </p>
            <p style="font-size: 11px; color: #999; margin: 0;"><?php esc_html_e('From active batches', 'restaurant-pos'); ?></p>
        </div>
        
        <div class="rpos-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid <?php echo count($expiring_batches) > 0 ? '#d63638' : '#00a32a'; ?>;">
            <h3 style="margin: 0; font-size: 14px; color: #666; text-transform: uppercase;"><?php esc_html_e('Expiring Soon', 'restaurant-pos'); ?></h3>
            <p style="font-size: 28px; font-weight: bold; margin: 10px 0; color: <?php echo count($expiring_batches) > 0 ? '#d63638' : '#00a32a'; ?>;">
                <?php echo esc_html(count($expiring_batches)); ?>
            </p>
            <p style="font-size: 11px; color: #999; margin: 0;"><?php printf(esc_html__('Within %d days', 'restaurant-pos'), $expiry_warning_days); ?></p>
        </div>
        
        <div class="rpos-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid <?php echo count($low_stock_ingredients) > 0 ? '#dba617' : '#00a32a'; ?>;">
            <h3 style="margin: 0; font-size: 14px; color: #666; text-transform: uppercase;"><?php esc_html_e('Low Stock Items', 'restaurant-pos'); ?></h3>
            <p style="font-size: 28px; font-weight: bold; margin: 10px 0; color: <?php echo count($low_stock_ingredients) > 0 ? '#dba617' : '#00a32a'; ?>;">
                <?php echo esc_html(count($low_stock_ingredients)); ?>
            </p>
            <p style="font-size: 11px; color: #999; margin: 0;"><?php esc_html_e('Below reorder level', 'restaurant-pos'); ?></p>
        </div>
        
        <div class="rpos-card" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #7356bf;">
            <h3 style="margin: 0; font-size: 14px; color: #666; text-transform: uppercase;"><?php esc_html_e('Strategy', 'restaurant-pos'); ?></h3>
            <p style="font-size: 28px; font-weight: bold; margin: 10px 0; color: #7356bf;">
                <?php echo esc_html($consumption_strategy); ?>
            </p>
            <p style="font-size: 11px; color: #999; margin: 0;"><?php esc_html_e('Consumption strategy', 'restaurant-pos'); ?></p>
        </div>
    </div>
    
    <style>
        .rpos-dashboard-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .rpos-dashboard-card h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .rpos-status-expired {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        .rpos-status-warning {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
        }
        .rpos-status-healthy {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .rpos-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .rpos-badge-red {
            background-color: #ffcdd2;
            color: #c62828;
        }
        .rpos-badge-orange {
            background-color: #ffe0b2;
            color: #e65100;
        }
        .rpos-badge-green {
            background-color: #c8e6c9;
            color: #2e7d32;
        }
    </style>
    
    <!-- Section A: Batches Near Expiry (Batch-Aware) -->
    <div class="rpos-dashboard-card">
        <h2>🗓️ <?php esc_html_e('Batches Near Expiry', 'restaurant-pos'); ?></h2>
        <p><?php printf(esc_html__('Showing batches expiring within %d days (based on %s strategy)', 'restaurant-pos'), $expiry_warning_days, $consumption_strategy); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Status', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Batch Number', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Supplier', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Remaining Qty', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Expiry Date', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Days Left', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($expiring_batches)): ?>
                    <?php foreach ($expiring_batches as $batch): ?>
                        <?php
                        $days_left = intval($batch->days_until_expiry);
                        if ($days_left < 0) {
                            $status_class = 'rpos-badge-red';
                            $status_text = __('Expired', 'restaurant-pos');
                        } elseif ($days_left <= 3) {
                            $status_class = 'rpos-badge-red';
                            $status_text = __('Critical', 'restaurant-pos');
                        } elseif ($days_left <= 7) {
                            $status_class = 'rpos-badge-orange';
                            $status_text = __('Warning', 'restaurant-pos');
                        } else {
                            $status_class = 'rpos-badge-green';
                            $status_text = __('OK', 'restaurant-pos');
                        }
                        ?>
                        <tr>
                            <td><span class="rpos-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span></td>
                            <td><strong><?php echo esc_html($batch->ingredient_name); ?></strong></td>
                            <td><code><?php echo esc_html($batch->batch_number); ?></code></td>
                            <td><?php echo esc_html($batch->supplier_name ?: '-'); ?></td>
                            <td><?php echo esc_html(number_format($batch->quantity_remaining, 3)); ?> <?php echo esc_html($batch->unit); ?></td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($batch->expiry_date))); ?></td>
                            <td>
                                <?php if ($days_left < 0): ?>
                                    <strong style="color: #d63638;"><?php echo abs($days_left); ?> days ago</strong>
                                <?php else: ?>
                                    <?php echo esc_html($days_left); ?> days
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #00a32a;">
                            ✓ <?php esc_html_e('No batches expiring soon! All stock is fresh.', 'restaurant-pos'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Section B: Low Stock Alerts -->
    <div class="rpos-dashboard-card">
        <h2>📦 <?php esc_html_e('Low Stock Alerts', 'restaurant-pos'); ?></h2>
        <p><?php esc_html_e('Ingredients at or below their reorder level', 'restaurant-pos'); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Current Stock', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Reorder Level', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Shortage', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Supplier', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Supplier Phone', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($low_stock_ingredients)): ?>
                    <?php foreach ($low_stock_ingredients as $ing): ?>
                        <tr>
                            <td><strong><?php echo esc_html($ing->name); ?></strong></td>
                            <td>
                                <span class="rpos-badge <?php echo $ing->current_stock_quantity == 0 ? 'rpos-badge-red' : 'rpos-badge-orange'; ?>">
                                    <?php echo esc_html(number_format($ing->current_stock_quantity, 3)); ?> <?php echo esc_html($ing->unit); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(number_format($ing->reorder_level, 3)); ?> <?php echo esc_html($ing->unit); ?></td>
                            <td>
                                <?php if ($ing->shortage_amount > 0): ?>
                                    <strong style="color: #c62828;">⚠️ <?php echo esc_html(number_format($ing->shortage_amount, 3)); ?> <?php echo esc_html($ing->unit); ?></strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($ing->supplier_name) ? esc_html($ing->supplier_name) : '-'; ?></td>
                            <td><?php echo !empty($ing->supplier_phone) ? '📞 ' . esc_html($ing->supplier_phone) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('All ingredients are above reorder level. All good!', 'restaurant-pos'); ?> ✅</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Section C: Fast-Moving Ingredients -->
    <div class="rpos-dashboard-card">
        <h2>🚀 <?php esc_html_e('Fast-Moving Ingredients', 'restaurant-pos'); ?></h2>
        <p><?php esc_html_e('Based on consumption data from the last 30 days', 'restaurant-pos'); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Avg Usage/Day', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Current Stock', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Estimated Days Remaining', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($fast_moving_ingredients)): ?>
                    <?php foreach ($fast_moving_ingredients as $ing): ?>
                        <?php
                        $days_remaining = floatval($ing->days_remaining);
                        if ($days_remaining < 3) {
                            $badge_class = 'rpos-badge-red';
                        } elseif ($days_remaining < 7) {
                            $badge_class = 'rpos-badge-orange';
                        } else {
                            $badge_class = 'rpos-badge-green';
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($ing->name); ?></strong></td>
                            <td><?php echo esc_html(number_format($ing->avg_daily_usage, 3)); ?> <?php echo esc_html($ing->unit); ?>/day</td>
                            <td><?php echo esc_html(number_format($ing->current_stock_quantity, 3)); ?> <?php echo esc_html($ing->unit); ?></td>
                            <td>
                                <span class="rpos-badge <?php echo esc_attr($badge_class); ?>">
                                    <?php if ($days_remaining > 365): ?>
                                        <?php esc_html_e('365+ days', 'restaurant-pos'); ?>
                                    <?php else: ?>
                                        ~<?php echo esc_html(number_format($days_remaining, 1)); ?> <?php esc_html_e('days', 'restaurant-pos'); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('No consumption data available for the last 30 days.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Section D: Supplier Performance (Last 90 Days) -->
    <?php if (!empty($supplier_performance)): ?>
    <div class="rpos-dashboard-card">
        <h2>🏪 <?php esc_html_e('Supplier Performance', 'restaurant-pos'); ?></h2>
        <p><?php esc_html_e('Top suppliers by activity (last 90 days)', 'restaurant-pos'); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Supplier', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Rating', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Total Batches', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Avg Cost/Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Current Inventory Value', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($supplier_performance as $sp): ?>
                    <tr>
                        <td><strong><?php echo esc_html($sp->supplier_name); ?></strong></td>
                        <td>
                            <?php if ($sp->rating): ?>
                                <?php echo str_repeat('⭐', intval($sp->rating)); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($sp->total_batches); ?></td>
                        <td>$<?php echo esc_html(number_format($sp->avg_cost_per_unit, 2)); ?></td>
                        <td><strong>$<?php echo esc_html(number_format($sp->current_inventory_value, 2)); ?></strong></td>
                        <td>
                            <a href="?page=restaurant-pos-suppliers&view=performance&id=<?php echo esc_attr($sp->id); ?>" class="button button-small">
                                <?php esc_html_e('View Details', 'restaurant-pos'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Quick Links -->
    <div class="rpos-dashboard-card" style="background: #f0f6fc;">
        <h2>🔗 <?php esc_html_e('Quick Actions', 'restaurant-pos'); ?></h2>
        <p>
            <a href="?page=restaurant-pos-ingredients" class="button button-primary"><?php esc_html_e('Manage Ingredients', 'restaurant-pos'); ?></a>
            <a href="?page=restaurant-pos-suppliers" class="button"><?php esc_html_e('Manage Suppliers', 'restaurant-pos'); ?></a>
            <a href="?page=restaurant-pos-batches" class="button"><?php esc_html_e('View All Batches', 'restaurant-pos'); ?></a>
            <a href="?page=restaurant-pos-ingredients-waste" class="button"><?php esc_html_e('Log Waste', 'restaurant-pos'); ?></a>
            <a href="?page=restaurant-pos-inventory-settings" class="button"><?php esc_html_e('Settings', 'restaurant-pos'); ?></a>
        </p>
    </div>
</div>
