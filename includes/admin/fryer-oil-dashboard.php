<?php
/**
 * Fryer Oil Dashboard Page
 * Main dashboard showing active batches and alerts
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('rpos_manage_inventory')) {
    wp_die('Permission denied');
}

// Get active batches
$active_batches = RPOS_Fryer_Oil_Batches::get_all(array(
    'status' => 'active',
    'limit' => 10
));

// Get active alerts
$alerts = RPOS_Fryer_Reminders::get_active_alerts();

// Get fryers
global $wpdb;
$fryers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rpos_fryers WHERE is_active = 1 ORDER BY name ASC");

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Fryer Oil Dashboard', 'restaurant-pos'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-fryer-oil-batches'); ?>" class="page-title-action">
        <?php _e('Manage Batches', 'restaurant-pos'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-fryer-oil-settings'); ?>" class="page-title-action">
        <?php _e('Settings', 'restaurant-pos'); ?>
    </a>
    <hr class="wp-header-end">
    
    <?php if (!empty($alerts)): ?>
        <div class="rpos-fryer-alerts" style="margin: 20px 0;">
            <?php foreach ($alerts as $alert): ?>
                <div class="notice notice-<?php echo $alert['severity'] === 'high' ? 'error' : ($alert['severity'] === 'medium' ? 'warning' : 'info'); ?>" style="padding: 15px; margin-bottom: 15px;">
                    <strong><?php echo esc_html(RPOS_Fryer_Reminders::get_alert_message($alert)); ?></strong>
                    <p>
                        <?php printf(__('Current Usage: %s / %s units (%s%%)', 'restaurant-pos'), 
                            number_format($alert['current_usage'], 1),
                            number_format($alert['target_usage'], 1),
                            number_format($alert['usage_percentage'], 1)
                        ); ?>
                        <br>
                        <?php printf(__('Time Elapsed: %s hours', 'restaurant-pos'), 
                            number_format($alert['time_elapsed_hours'], 1)
                        ); ?>
                    </p>
                    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-fryer-oil-batches&action=close&batch_id=' . $alert['batch_id']); ?>" 
                       class="button button-primary" style="margin-top: 10px;">
                        <?php _e('Change Oil & Close Batch', 'restaurant-pos'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="rpos-fryer-dashboard" style="margin: 20px 0;">
        <h2><?php _e('Active Oil Batches', 'restaurant-pos'); ?></h2>
        
        <?php if (empty($active_batches)): ?>
            <div class="notice notice-info">
                <p><?php _e('No active oil batches found. Add a new batch to start tracking.', 'restaurant-pos'); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-fryer-oil-batches&action=add'); ?>" class="button button-primary">
                <?php _e('Add New Batch', 'restaurant-pos'); ?>
            </a>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Batch Name', 'restaurant-pos'); ?></th>
                        <th><?php _e('Fryer', 'restaurant-pos'); ?></th>
                        <th><?php _e('Started', 'restaurant-pos'); ?></th>
                        <th><?php _e('Usage', 'restaurant-pos'); ?></th>
                        <th><?php _e('Progress', 'restaurant-pos'); ?></th>
                        <th><?php _e('Time Elapsed', 'restaurant-pos'); ?></th>
                        <th><?php _e('Status', 'restaurant-pos'); ?></th>
                        <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_batches as $batch): ?>
                        <?php
                        $stats = RPOS_Fryer_Oil_Batches::get_usage_stats($batch->id);
                        $alert = RPOS_Fryer_Reminders::should_remind($batch->id);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($batch->batch_name); ?></strong></td>
                            <td><?php echo esc_html($batch->fryer_name ?: 'Default'); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->oil_added_at))); ?></td>
                            <td>
                                <?php echo number_format($stats['total_units'], 1); ?> / 
                                <?php echo number_format($batch->target_usage_units, 1); ?> units
                            </td>
                            <td>
                                <div style="background: #e0e0e0; height: 20px; border-radius: 3px; overflow: hidden;">
                                    <div style="background: <?php echo $stats['usage_percentage'] >= 100 ? '#dc3232' : ($stats['usage_percentage'] >= 80 ? '#ffb900' : '#46b450'); ?>; 
                                         height: 100%; width: <?php echo min($stats['usage_percentage'], 100); ?>%;"></div>
                                </div>
                                <small><?php echo number_format($stats['usage_percentage'], 1); ?>%</small>
                            </td>
                            <td><?php echo number_format($stats['time_elapsed_hours'], 1); ?> hrs</td>
                            <td>
                                <?php if ($alert): ?>
                                    <span class="dashicons dashicons-warning" style="color: <?php echo $alert['severity'] === 'high' ? '#dc3232' : '#ffb900'; ?>;"></span>
                                    <?php echo esc_html($alert['message']); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php _e('Normal', 'restaurant-pos'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=restaurant-pos-fryer-oil-reports&batch_id=' . $batch->id); ?>" 
                                   class="button button-small">
                                    <?php _e('View Details', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="rpos-fryer-stats" style="margin: 30px 0;">
        <h2><?php _e('Quick Stats', 'restaurant-pos'); ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="rpos-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h3><?php _e('Active Batches', 'restaurant-pos'); ?></h3>
                <div style="font-size: 36px; font-weight: bold; color: #0073aa;"><?php echo count($active_batches); ?></div>
            </div>
            <div class="rpos-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h3><?php _e('Active Alerts', 'restaurant-pos'); ?></h3>
                <div style="font-size: 36px; font-weight: bold; color: <?php echo count($alerts) > 0 ? '#dc3232' : '#46b450'; ?>;">
                    <?php echo count($alerts); ?>
                </div>
            </div>
            <div class="rpos-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h3><?php _e('Active Fryers', 'restaurant-pos'); ?></h3>
                <div style="font-size: 36px; font-weight: bold; color: #0073aa;"><?php echo count($fryers); ?></div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-refresh alerts every 60 seconds using AJAX
    jQuery(document).ready(function($) {
        function refreshAlerts() {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'rpos_check_fryer_alerts',
                    nonce: '<?php echo wp_create_nonce('rpos_pos_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.has_alerts) {
                        // Reload page if there are new alerts
                        location.reload();
                    }
                }
            });
        }
        
        setInterval(refreshAlerts, 60000);
    });
    </script>
</div>
