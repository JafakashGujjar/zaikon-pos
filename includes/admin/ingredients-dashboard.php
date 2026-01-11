<?php
/**
 * Ingredients Stock Intelligence Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get configurable days for expiry warning (default 7)
$expiry_warning_days = 7;

// Section A: Ingredients Near Expiry
$expiring_ingredients = $wpdb->get_results($wpdb->prepare(
    "SELECT *, 
            DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
     FROM {$wpdb->prefix}rpos_ingredients 
     WHERE expiry_date IS NOT NULL 
     AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)
     ORDER BY expiry_date ASC",
    $expiry_warning_days
));

// Section B: Low Stock Alerts
$low_stock_ingredients = $wpdb->get_results(
    "SELECT *,
            (reorder_level - current_stock_quantity) as shortage_amount
     FROM {$wpdb->prefix}rpos_ingredients 
     WHERE current_stock_quantity <= reorder_level
     AND reorder_level > 0
     ORDER BY current_stock_quantity ASC"
);

// Section C: Fast-Moving Ingredients
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
    WHERE im.movement_type = 'Consumption'
    AND im.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY im.ingredient_id
    HAVING avg_daily_usage > 0
    ORDER BY avg_daily_usage DESC
    LIMIT 20"
);

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Stock Intelligence Dashboard', 'restaurant-pos'); ?></h1>
    <hr class="wp-header-end">
    
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
    
    <!-- Section A: Ingredients Near Expiry -->
    <div class="rpos-dashboard-card">
        <h2>🗓️ <?php esc_html_e('Ingredients Near Expiry', 'restaurant-pos'); ?></h2>
        <p><?php printf(esc_html__('Showing ingredients expiring within %d days', 'restaurant-pos'), $expiry_warning_days); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Status', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Current Stock', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Expiry Date', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Days Until Expiry', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($expiring_ingredients)): ?>
                    <?php foreach ($expiring_ingredients as $ing): ?>
                        <?php
                        $days_left = intval($ing->days_until_expiry);
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
                            <td><strong><?php echo esc_html($ing->name); ?></strong></td>
                            <td><?php echo esc_html(number_format($ing->current_stock_quantity, 3)); ?> <?php echo esc_html($ing->unit); ?></td>
                            <td><?php echo esc_html($ing->expiry_date); ?></td>
                            <td>
                                <?php if ($days_left < 0): ?>
                                    <strong style="color: #c62828;"><?php printf(esc_html__('Expired %d days ago', 'restaurant-pos'), abs($days_left)); ?></strong>
                                <?php else: ?>
                                    <?php echo esc_html($days_left); ?> <?php esc_html_e('days', 'restaurant-pos'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No ingredients expiring soon. All good!', 'restaurant-pos'); ?> ✅</td>
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
</div>
