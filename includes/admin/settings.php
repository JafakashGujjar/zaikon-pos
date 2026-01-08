<?php
/**
 * Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_settings_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_settings_nonce'], 'rpos_settings_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_settings') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to perform this action');
    }
    
    // Update settings
    RPOS_Settings::update('restaurant_name', sanitize_text_field($_POST['restaurant_name'] ?? ''));
    RPOS_Settings::update('currency_symbol', sanitize_text_field($_POST['currency_symbol'] ?? '$'));
    RPOS_Settings::update('low_stock_threshold', absint($_POST['low_stock_threshold'] ?? 10));
    RPOS_Settings::update('date_format', sanitize_text_field($_POST['date_format'] ?? 'Y-m-d H:i:s'));
    
    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'restaurant-pos') . '</p></div>';
}

// Get current settings
$settings = RPOS_Settings::get_all();
?>

<div class="wrap rpos-settings">
    <h1><?php echo esc_html__('Restaurant POS Settings', 'restaurant-pos'); ?></h1>
    
    <form method="post" class="rpos-settings-form">
        <?php wp_nonce_field('rpos_settings_action', 'rpos_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="restaurant_name"><?php echo esc_html__('Restaurant Name', 'restaurant-pos'); ?></label>
                </th>
                <td>
                    <input type="text" id="restaurant_name" name="restaurant_name" class="regular-text" 
                           value="<?php echo esc_attr($settings['restaurant_name'] ?? get_bloginfo('name')); ?>" required>
                    <p class="description">
                        <?php echo esc_html__('This name will appear on receipts and reports', 'restaurant-pos'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="currency_symbol"><?php echo esc_html__('Currency Symbol', 'restaurant-pos'); ?></label>
                </th>
                <td>
                    <input type="text" id="currency_symbol" name="currency_symbol" class="small-text" 
                           value="<?php echo esc_attr($settings['currency_symbol'] ?? '$'); ?>" required>
                    <p class="description">
                        <?php echo esc_html__('Currency symbol to display (e.g., $, €, £)', 'restaurant-pos'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="low_stock_threshold"><?php echo esc_html__('Low Stock Threshold', 'restaurant-pos'); ?></label>
                </th>
                <td>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="small-text" min="0"
                           value="<?php echo esc_attr($settings['low_stock_threshold'] ?? 10); ?>" required>
                    <p class="description">
                        <?php echo esc_html__('Products with stock at or below this level will be flagged as low stock', 'restaurant-pos'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="date_format"><?php echo esc_html__('Date Format', 'restaurant-pos'); ?></label>
                </th>
                <td>
                    <select id="date_format" name="date_format">
                        <option value="Y-m-d H:i:s" <?php selected($settings['date_format'] ?? 'Y-m-d H:i:s', 'Y-m-d H:i:s'); ?>>
                            <?php echo esc_html__('YYYY-MM-DD HH:MM:SS', 'restaurant-pos'); ?>
                        </option>
                        <option value="m/d/Y h:i A" <?php selected($settings['date_format'] ?? '', 'm/d/Y h:i A'); ?>>
                            <?php echo esc_html__('MM/DD/YYYY HH:MM AM/PM', 'restaurant-pos'); ?>
                        </option>
                        <option value="d/m/Y H:i" <?php selected($settings['date_format'] ?? '', 'd/m/Y H:i'); ?>>
                            <?php echo esc_html__('DD/MM/YYYY HH:MM', 'restaurant-pos'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Format for displaying dates and times', 'restaurant-pos'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php echo esc_html__('Save Settings', 'restaurant-pos'); ?>
            </button>
        </p>
    </form>
    
    <hr>
    
    <div class="rpos-info-section">
        <h2><?php echo esc_html__('Plugin Information', 'restaurant-pos'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php echo esc_html__('Version:', 'restaurant-pos'); ?></th>
                <td><?php echo esc_html(RPOS_VERSION); ?></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Database Version:', 'restaurant-pos'); ?></th>
                <td><?php echo esc_html(get_option('rpos_version', 'Unknown')); ?></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Installed:', 'restaurant-pos'); ?></th>
                <td>
                    <?php 
                    $installed_time = get_option('rpos_installed');
                    echo $installed_time ? esc_html(date('Y-m-d H:i:s', $installed_time)) : esc_html__('Unknown', 'restaurant-pos');
                    ?>
                </td>
            </tr>
        </table>
    </div>
</div>
