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
    RPOS_Settings::update('pos_timezone', sanitize_text_field($_POST['pos_timezone'] ?? ''));
    RPOS_Settings::update('restaurant_phone', sanitize_text_field($_POST['restaurant_phone'] ?? ''));
    RPOS_Settings::update('restaurant_address', sanitize_textarea_field($_POST['restaurant_address'] ?? ''));
    RPOS_Settings::update('receipt_footer_message', sanitize_textarea_field($_POST['receipt_footer_message'] ?? 'Thank you for your order!'));
    
    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'restaurant-pos') . '</p></div>';
}

// Get current settings
$settings = RPOS_Settings::get_all();
$tab = $_GET['tab'] ?? 'general';
?>

<style>
.rpos-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.rpos-kpi-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #2271b1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.rpos-chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

<div class="wrap rpos-settings">
    <h1><?php echo esc_html__('Restaurant POS Settings', 'restaurant-pos'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=restaurant-pos-settings&tab=general" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">
            ⚙️ General Settings
        </a>
        <a href="?page=restaurant-pos-settings&tab=display" class="nav-tab <?php echo $tab === 'display' ? 'nav-tab-active' : ''; ?>">
            🎨 Display Settings
        </a>
        <a href="?page=restaurant-pos-settings&tab=stock" class="nav-tab <?php echo $tab === 'stock' ? 'nav-tab-active' : ''; ?>">
            📦 Stock Settings
        </a>
        <a href="?page=restaurant-pos-settings&tab=info" class="nav-tab <?php echo $tab === 'info' ? 'nav-tab-active' : ''; ?>">
            ℹ️ Plugin Info
        </a>
    </h2>
    
    <?php if ($tab === 'general'): ?>
        <!-- Tab 1: General Settings -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('General Settings', 'restaurant-pos'); ?></h2>
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
                            <label for="restaurant_phone"><?php echo esc_html__('Restaurant Phone Number', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="restaurant_phone" name="restaurant_phone" class="regular-text" 
                                   value="<?php echo esc_attr($settings['restaurant_phone'] ?? ''); ?>">
                            <p class="description">
                                <?php echo esc_html__('Phone number to display on receipts', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="restaurant_address"><?php echo esc_html__('Restaurant Address', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <textarea id="restaurant_address" name="restaurant_address" class="large-text" rows="3"><?php echo esc_textarea($settings['restaurant_address'] ?? ''); ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('Full address to display on receipts', 'restaurant-pos'); ?>
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
        </div>
    
    <?php elseif ($tab === 'display'): ?>
        <!-- Tab 2: Display Settings -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Display Settings', 'restaurant-pos'); ?></h2>
            <form method="post" class="rpos-settings-form">
                <?php wp_nonce_field('rpos_settings_action', 'rpos_settings_nonce'); ?>
                
                <table class="form-table">
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
                    
                    <tr>
                        <th scope="row">
                            <label for="pos_timezone"><?php echo esc_html__('Time Zone', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <select id="pos_timezone" name="pos_timezone" class="regular-text">
                                <?php
                                $current_timezone = $settings['pos_timezone'] ?? get_option('timezone_string', 'UTC');
                                $timezones = timezone_identifiers_list();
                                
                                // Add default option
                                echo '<option value=""' . selected($current_timezone, '', false) . '>';
                                echo esc_html__('Use WordPress Site Timezone', 'restaurant-pos');
                                echo ' (' . esc_html(get_option('timezone_string') ?: 'UTC') . ')';
                                echo '</option>';
                                
                                // Group timezones by continent for better UX
                                $timezone_continents = array();
                                foreach ($timezones as $timezone) {
                                    $parts = explode('/', $timezone);
                                    if (count($parts) > 1) {
                                        $continent = $parts[0];
                                        if (!isset($timezone_continents[$continent])) {
                                            $timezone_continents[$continent] = array();
                                        }
                                        $timezone_continents[$continent][] = $timezone;
                                    }
                                }
                                
                                // Output grouped timezones
                                foreach ($timezone_continents as $continent => $continent_timezones) {
                                    echo '<optgroup label="' . esc_attr($continent) . '">';
                                    foreach ($continent_timezones as $timezone) {
                                        $selected = selected($current_timezone, $timezone, false);
                                        echo '<option value="' . esc_attr($timezone) . '"' . $selected . '>';
                                        echo esc_html(str_replace('_', ' ', $timezone));
                                        echo '</option>';
                                    }
                                    echo '</optgroup>';
                                }
                                ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Select the timezone for displaying all dates and times in the plugin. This fixes the kitchen display time discrepancy.', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="receipt_footer_message"><?php echo esc_html__('Receipt Footer Message', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <textarea id="receipt_footer_message" name="receipt_footer_message" class="large-text" rows="2"><?php echo esc_textarea($settings['receipt_footer_message'] ?? 'Thank you for your order!'); ?></textarea>
                            <p class="description">
                                <?php echo esc_html__('Message to display at the bottom of receipts', 'restaurant-pos'); ?>
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
        </div>
    
    <?php elseif ($tab === 'stock'): ?>
        <!-- Tab 3: Stock Settings -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Stock Settings', 'restaurant-pos'); ?></h2>
            <form method="post" class="rpos-settings-form">
                <?php wp_nonce_field('rpos_settings_action', 'rpos_settings_nonce'); ?>
                
                <table class="form-table">
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
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        <?php echo esc_html__('Save Settings', 'restaurant-pos'); ?>
                    </button>
                </p>
            </form>
        </div>
    
    <?php elseif ($tab === 'info'): ?>
        <!-- Tab 4: Plugin Info -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Plugin Information', 'restaurant-pos'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('Version:', 'restaurant-pos'); ?></th>
                    <td><strong><?php echo esc_html(RPOS_VERSION); ?></strong></td>
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
                        echo $installed_time ? esc_html(RPOS_Timezone::format($installed_time)) : esc_html__('Unknown', 'restaurant-pos');
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    <?php endif; ?>
</div>
