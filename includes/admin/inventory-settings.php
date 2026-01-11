<?php
/**
 * Inventory Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_inventory_settings_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_inventory_settings_nonce'], 'rpos_inventory_settings_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_settings')) {
        wp_die('You do not have permission to perform this action');
    }
    
    // Save settings
    $settings_to_save = array(
        'consumption_strategy' => sanitize_text_field($_POST['consumption_strategy'] ?? 'FEFO'),
        'expiry_warning_days' => absint($_POST['expiry_warning_days'] ?? 7),
        'low_stock_warning_days' => absint($_POST['low_stock_warning_days'] ?? 3),
        'enable_batch_tracking' => isset($_POST['enable_batch_tracking']) ? '1' : '0',
        'require_batch_on_purchase' => isset($_POST['require_batch_on_purchase']) ? '1' : '0',
        'auto_expire_batches' => isset($_POST['auto_expire_batches']) ? '1' : '0'
    );
    
    foreach ($settings_to_save as $key => $value) {
        RPOS_Inventory_Settings::set($key, $value);
    }
    
    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'restaurant-pos') . '</p></div>';
}

// Initialize default settings if not exist
RPOS_Inventory_Settings::init_defaults();

// Get current settings
$settings = RPOS_Inventory_Settings::get_all();
?>

<div class="wrap">
    <h1><?php esc_html_e('Inventory Settings', 'restaurant-pos'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('rpos_inventory_settings_action', 'rpos_inventory_settings_nonce'); ?>
        
        <div style="max-width: 800px;">
            <!-- Consumption Strategy -->
            <div class="rpos-settings-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2><?php esc_html_e('Batch Consumption Strategy', 'restaurant-pos'); ?></h2>
                <p class="description"><?php esc_html_e('Choose how the system selects batches when consuming ingredients during sales.', 'restaurant-pos'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Strategy', 'restaurant-pos'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="consumption_strategy" value="FIFO" 
                                           <?php checked($settings['consumption_strategy'] ?? 'FEFO', 'FIFO'); ?>>
                                    <strong><?php esc_html_e('FIFO (First In First Out)', 'restaurant-pos'); ?></strong>
                                    <p class="description"><?php esc_html_e('Consume batches based on purchase date - oldest purchases first.', 'restaurant-pos'); ?></p>
                                </label>
                                <br><br>
                                <label>
                                    <input type="radio" name="consumption_strategy" value="FEFO" 
                                           <?php checked($settings['consumption_strategy'] ?? 'FEFO', 'FEFO'); ?>>
                                    <strong><?php esc_html_e('FEFO (First Expire First Out)', 'restaurant-pos'); ?></strong>
                                    <span style="background: #00a32a; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 8px;">
                                        <?php esc_html_e('RECOMMENDED', 'restaurant-pos'); ?>
                                    </span>
                                    <p class="description"><?php esc_html_e('Consume batches based on expiry date - items expiring soonest first. Best for food safety and reducing waste.', 'restaurant-pos'); ?></p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Warning Thresholds -->
            <div class="rpos-settings-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2><?php esc_html_e('Alert Thresholds', 'restaurant-pos'); ?></h2>
                <p class="description"><?php esc_html_e('Configure when the system should alert you about expiring or low stock items.', 'restaurant-pos'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="expiry_warning_days"><?php esc_html_e('Expiry Warning (Days)', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="expiry_warning_days" id="expiry_warning_days" 
                                   value="<?php echo esc_attr($settings['expiry_warning_days'] ?? 7); ?>" 
                                   min="1" max="90" class="small-text">
                            <p class="description">
                                <?php esc_html_e('Show batches expiring within this many days in the dashboard. Default: 7 days.', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="low_stock_warning_days"><?php esc_html_e('Low Stock Warning (Days)', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="low_stock_warning_days" id="low_stock_warning_days" 
                                   value="<?php echo esc_attr($settings['low_stock_warning_days'] ?? 3); ?>" 
                                   min="1" max="30" class="small-text">
                            <p class="description">
                                <?php esc_html_e('Show alert when estimated stock will run out in this many days. Default: 3 days.', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Batch Tracking Options -->
            <div class="rpos-settings-section" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2><?php esc_html_e('Batch Tracking Options', 'restaurant-pos'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Batch Tracking', 'restaurant-pos'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_batch_tracking" value="1" 
                                       <?php checked($settings['enable_batch_tracking'] ?? '1', '1'); ?>>
                                <?php esc_html_e('Track ingredients by batch/lot', 'restaurant-pos'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Enable batch-based inventory tracking with FIFO/FEFO support. Recommended for food safety compliance.', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Require Batch on Purchase', 'restaurant-pos'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_batch_on_purchase" value="1" 
                                       <?php checked($settings['require_batch_on_purchase'] ?? '1', '1'); ?>>
                                <?php esc_html_e('Always create batch when purchasing ingredients', 'restaurant-pos'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, all ingredient purchases will create batch records. Disable to allow direct stock updates without batches.', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Expire Batches', 'restaurant-pos'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_expire_batches" value="1" 
                                       <?php checked($settings['auto_expire_batches'] ?? '1', '1'); ?>>
                                <?php esc_html_e('Automatically mark batches as expired after expiry date', 'restaurant-pos'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, batches past their expiry date will be automatically marked as "expired" status.', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Info Box -->
            <div style="background: #f0f6fc; padding: 15px; border-left: 4px solid #2271b1; border-radius: 4px; margin: 20px 0;">
                <h3 style="margin-top: 0;"><?php esc_html_e('About Batch-Based Inventory', 'restaurant-pos'); ?></h3>
                <p>
                    <?php esc_html_e('Batch-based inventory tracking helps you:', 'restaurant-pos'); ?>
                </p>
                <ul>
                    <li><?php esc_html_e('Track expiry dates accurately with FEFO consumption', 'restaurant-pos'); ?></li>
                    <li><?php esc_html_e('Reduce food waste by using oldest/expiring items first', 'restaurant-pos'); ?></li>
                    <li><?php esc_html_e('Maintain food safety compliance', 'restaurant-pos'); ?></li>
                    <li><?php esc_html_e('Identify supplier quality issues', 'restaurant-pos'); ?></li>
                    <li><?php esc_html_e('Calculate accurate inventory valuation', 'restaurant-pos'); ?></li>
                </ul>
                <p>
                    <strong><?php esc_html_e('Current Inventory Value:', 'restaurant-pos'); ?></strong> 
                    $<?php echo esc_html(number_format(RPOS_Batches::get_inventory_valuation(), 2)); ?>
                </p>
            </div>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" 
                       value="<?php esc_attr_e('Save Settings', 'restaurant-pos'); ?>">
            </p>
        </div>
    </form>
</div>
