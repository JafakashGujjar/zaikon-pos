<?php
/**
 * Fryer Oil Reports Page
 * Enterprise-level reporting and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('rpos_view_reports')) {
    wp_die('Permission denied');
}

$batch_id = isset($_GET['batch_id']) ? absint($_GET['batch_id']) : null;
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

?>

<div class="wrap">
    <h1><?php _e('Fryer Oil Reports', 'restaurant-pos'); ?></h1>
    <hr class="wp-header-end">
    
    <?php if ($batch_id): ?>
        <!-- Single Batch Report -->
        <?php
        $details = RPOS_Fryer_Reports::get_batch_details($batch_id);
        
        if (!$details):
            echo '<div class="notice notice-error"><p>' . __('Batch not found.', 'restaurant-pos') . '</p></div>';
            return;
        endif;
        
        $batch = $details['batch'];
        $stats = $details['stats'];
        $products = $details['products'];
        $usage_log = $details['usage_log'];
        ?>
        
        <div style="margin: 20px 0;">
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-fryer-oil-reports'); ?>" class="button">
                &larr; <?php _e('Back to Reports', 'restaurant-pos'); ?>
            </a>
        </div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
            <h2><?php echo esc_html($batch->batch_name); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th><?php _e('Fryer', 'restaurant-pos'); ?></th>
                    <td><?php echo esc_html($batch->fryer_name ?: 'Default'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Status', 'restaurant-pos'); ?></th>
                    <td>
                        <?php if ($batch->status === 'active'): ?>
                            <span style="color: #46b450;">● <?php _e('Active', 'restaurant-pos'); ?></span>
                        <?php else: ?>
                            <span style="color: #999;">● <?php _e('Closed', 'restaurant-pos'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Oil Added', 'restaurant-pos'); ?></th>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->oil_added_at))); ?></td>
                </tr>
                <?php if ($batch->closed_at): ?>
                <tr>
                    <th><?php _e('Closed', 'restaurant-pos'); ?></th>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->closed_at))); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php _e('Total Units Used', 'restaurant-pos'); ?></th>
                    <td><?php echo number_format($stats['total_units'], 1); ?> / <?php echo number_format($batch->target_usage_units, 1); ?> (<?php echo number_format($stats['usage_percentage'], 1); ?>%)</td>
                </tr>
                <tr>
                    <th><?php _e('Time Elapsed', 'restaurant-pos'); ?></th>
                    <td><?php echo number_format($stats['time_elapsed_hours'], 1); ?> hours</td>
                </tr>
                <tr>
                    <th><?php _e('Total Products Fried', 'restaurant-pos'); ?></th>
                    <td><?php echo number_format($stats['total_products']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Created By', 'restaurant-pos'); ?></th>
                    <td><?php echo esc_html($batch->created_by_name); ?></td>
                </tr>
                <?php if ($batch->closed_by_name): ?>
                <tr>
                    <th><?php _e('Closed By', 'restaurant-pos'); ?></th>
                    <td><?php echo esc_html($batch->closed_by_name); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($batch->notes): ?>
                <tr>
                    <th><?php _e('Notes', 'restaurant-pos'); ?></th>
                    <td><?php echo nl2br(esc_html($batch->notes)); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <h2><?php _e('Products Cooked', 'restaurant-pos'); ?></h2>
        <?php if (empty($products)): ?>
            <p><?php _e('No products recorded for this batch yet.', 'restaurant-pos'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'restaurant-pos'); ?></th>
                        <th><?php _e('Quantity', 'restaurant-pos'); ?></th>
                        <th><?php _e('Units Consumed', 'restaurant-pos'); ?></th>
                        <th><?php _e('Orders', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><strong><?php echo esc_html($product->product_name); ?></strong></td>
                            <td><?php echo number_format($product->total_quantity); ?></td>
                            <td><?php echo number_format($product->total_units, 2); ?></td>
                            <td><?php echo number_format($product->order_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2 style="margin-top: 30px;"><?php _e('Usage Log', 'restaurant-pos'); ?></h2>
        <?php if (empty($usage_log)): ?>
            <p><?php _e('No usage records found.', 'restaurant-pos'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date/Time', 'restaurant-pos'); ?></th>
                        <th><?php _e('Order', 'restaurant-pos'); ?></th>
                        <th><?php _e('Product', 'restaurant-pos'); ?></th>
                        <th><?php _e('Quantity', 'restaurant-pos'); ?></th>
                        <th><?php _e('Units Consumed', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($usage_log, 0, 100) as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($log->created_at))); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=restaurant-pos-orders&order_id=' . $log->order_id); ?>">
                                    #<?php echo esc_html($log->order_id); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($log->product_name); ?></td>
                            <td><?php echo number_format($log->quantity); ?></td>
                            <td><?php echo number_format($log->units_consumed, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($usage_log) > 100): ?>
                <p><em><?php printf(__('Showing first 100 of %d records', 'restaurant-pos'), count($usage_log)); ?></em></p>
            <?php endif; ?>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Summary Reports -->
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
            <h2><?php _e('Filter Reports', 'restaurant-pos'); ?></h2>
            <form method="get">
                <input type="hidden" name="page" value="restaurant-pos-fryer-oil-reports">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="date_from"><?php _e('Date From', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="date_to"><?php _e('Date To', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Generate Report', 'restaurant-pos'); ?></button>
                </p>
            </form>
        </div>
        
        <?php
        $lifecycle_stats = RPOS_Fryer_Reports::get_lifecycle_stats($date_from . ' 00:00:00', $date_to . ' 23:59:59');
        $batch_history = RPOS_Fryer_Reports::get_batch_history(array(
            'date_from' => $date_from . ' 00:00:00',
            'date_to' => $date_to . ' 23:59:59',
            'status' => 'closed'
        ));
        ?>
        
        <h2><?php _e('Lifecycle Statistics', 'restaurant-pos'); ?></h2>
        <p><?php printf(__('Period: %s to %s', 'restaurant-pos'), $date_from, $date_to); ?></p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h3><?php _e('Total Batches', 'restaurant-pos'); ?></h3>
                <div style="font-size: 36px; font-weight: bold; color: #0073aa;">
                    <?php echo number_format($lifecycle_stats['lifecycle']->total_batches); ?>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h3><?php _e('Avg. Units/Batch', 'restaurant-pos'); ?></h3>
                <div style="font-size: 36px; font-weight: bold; color: #46b450;">
                    <?php echo number_format($lifecycle_stats['lifecycle']->avg_units_per_batch, 1); ?>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h3><?php _e('Avg. Hours/Batch', 'restaurant-pos'); ?></h3>
                <div style="font-size: 36px; font-weight: bold; color: #ffb900;">
                    <?php echo number_format($lifecycle_stats['lifecycle']->avg_hours_per_batch, 1); ?>
                </div>
            </div>
        </div>
        
        <h2 style="margin-top: 30px;"><?php _e('Top Products', 'restaurant-pos'); ?></h2>
        <?php if (empty($lifecycle_stats['products'])): ?>
            <p><?php _e('No data available for this period.', 'restaurant-pos'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'restaurant-pos'); ?></th>
                        <th><?php _e('Total Quantity', 'restaurant-pos'); ?></th>
                        <th><?php _e('Total Units', 'restaurant-pos'); ?></th>
                        <th><?php _e('Batches', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($lifecycle_stats['products'], 0, 20) as $product): ?>
                        <tr>
                            <td><strong><?php echo esc_html($product->product_name); ?></strong></td>
                            <td><?php echo number_format($product->total_quantity); ?></td>
                            <td><?php echo number_format($product->total_units, 2); ?></td>
                            <td><?php echo number_format($product->batches_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2 style="margin-top: 30px;"><?php _e('Batch History', 'restaurant-pos'); ?></h2>
        <?php if (empty($batch_history)): ?>
            <p><?php _e('No closed batches found for this period.', 'restaurant-pos'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Batch', 'restaurant-pos'); ?></th>
                        <th><?php _e('Fryer', 'restaurant-pos'); ?></th>
                        <th><?php _e('Started', 'restaurant-pos'); ?></th>
                        <th><?php _e('Closed', 'restaurant-pos'); ?></th>
                        <th><?php _e('Duration (hrs)', 'restaurant-pos'); ?></th>
                        <th><?php _e('Usage', 'restaurant-pos'); ?></th>
                        <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batch_history as $batch): ?>
                        <?php
                        $start = new DateTime($batch->oil_added_at);
                        $end = new DateTime($batch->closed_at);
                        $duration = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($batch->batch_name); ?></strong></td>
                            <td><?php echo esc_html($batch->fryer_name ?: 'Default'); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->oil_added_at))); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->closed_at))); ?></td>
                            <td><?php echo number_format($duration, 1); ?></td>
                            <td>
                                <?php echo number_format($batch->current_usage_units, 1); ?> / 
                                <?php echo number_format($batch->target_usage_units, 1); ?>
                            </td>
                            <td>
                                <a href="?page=restaurant-pos-fryer-oil-reports&batch_id=<?php echo $batch->id; ?>" 
                                   class="button button-small">
                                    <?php _e('View Details', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
