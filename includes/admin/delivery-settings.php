<?php
/**
 * Delivery Settings Page
 * 
 * @deprecated This page is deprecated in favor of Zaikon Delivery Management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Show deprecation notice
?>
<div class="wrap">
    <h1><?php echo esc_html__('Delivery Settings', 'restaurant-pos'); ?></h1>
    
    <div class="notice notice-warning is-dismissible" style="padding: 15px; margin-top: 20px;">
        <h2 style="margin-top: 0;">⚠️ <?php echo esc_html__('This Page is Deprecated', 'restaurant-pos'); ?></h2>
        <p><?php echo esc_html__('This legacy delivery settings page has been replaced by the new Zaikon Delivery Management system.', 'restaurant-pos'); ?></p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=restaurant-pos-zaikon-delivery')); ?>" class="button button-primary">
                <?php echo esc_html__('Go to Zaikon Delivery Management', 'restaurant-pos'); ?>
            </a>
        </p>
    </div>
</div>
<?php
return; // Stop executing the rest of the page

// Handle form submissions
$message = '';
$message_type = '';

// Handle Configuration Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_delivery_config_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_delivery_config_nonce'], 'rpos_delivery_config_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_settings') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to perform this action');
    }
    
    RPOS_Delivery_Settings::set('minimum_free_delivery_amount', sanitize_text_field($_POST['minimum_free_delivery_amount'] ?? '0'), 'amount');
    RPOS_Delivery_Settings::set('fuel_unit', sanitize_text_field($_POST['fuel_unit'] ?? 'liters'), 'text');
    
    $message = __('Configuration saved successfully!', 'restaurant-pos');
    $message_type = 'success';
}

// Handle Delivery Area Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_delivery_area_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_delivery_area_nonce'], 'rpos_delivery_area_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_settings') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $area_data = array(
        'name' => sanitize_text_field($_POST['area_name']),
        'distance_value' => floatval($_POST['distance_value']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    );
    
    if (isset($_POST['area_id']) && $_POST['area_id']) {
        RPOS_Delivery_Areas::update($_POST['area_id'], $area_data);
        $message = __('Delivery area updated successfully!', 'restaurant-pos');
    } else {
        RPOS_Delivery_Areas::create($area_data);
        $message = __('Delivery area created successfully!', 'restaurant-pos');
    }
    $message_type = 'success';
}

// Handle Delivery Charge Rule Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_delivery_charge_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_delivery_charge_nonce'], 'rpos_delivery_charge_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_settings') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $charge_data = array(
        'distance_from' => floatval($_POST['distance_from']),
        'distance_to' => floatval($_POST['distance_to']),
        'charge_amount' => floatval($_POST['charge_amount']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    );
    
    if (isset($_POST['charge_id']) && $_POST['charge_id']) {
        RPOS_Delivery_Charges::update($_POST['charge_id'], $charge_data);
        $message = __('Delivery charge rule updated successfully!', 'restaurant-pos');
    } else {
        RPOS_Delivery_Charges::create($charge_data);
        $message = __('Delivery charge rule created successfully!', 'restaurant-pos');
    }
    $message_type = 'success';
}

// Handle Delete Requests
if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
    if ($_GET['action'] === 'delete_area' && wp_verify_nonce($_GET['_wpnonce'], 'delete_area_' . $_GET['id'])) {
        RPOS_Delivery_Areas::delete($_GET['id']);
        $message = __('Delivery area deleted successfully!', 'restaurant-pos');
        $message_type = 'success';
    } elseif ($_GET['action'] === 'delete_charge' && wp_verify_nonce($_GET['_wpnonce'], 'delete_charge_' . $_GET['id'])) {
        RPOS_Delivery_Charges::delete($_GET['id']);
        $message = __('Delivery charge rule deleted successfully!', 'restaurant-pos');
        $message_type = 'success';
    }
}

// Get current settings
$currency = RPOS_Settings::get('currency_symbol', '$');
$minimum_free_delivery = RPOS_Delivery_Settings::get('minimum_free_delivery_amount', '0');
$fuel_unit = RPOS_Delivery_Settings::get('fuel_unit', 'liters');

// Get all areas and charges
$delivery_areas = RPOS_Delivery_Areas::get_all();
$delivery_charges = RPOS_Delivery_Charges::get_all();

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'config';
?>

<div class="wrap rpos-delivery-settings">
    <h1><?php echo esc_html__('Delivery Settings', 'restaurant-pos'); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=restaurant-pos-delivery-settings&tab=config" class="nav-tab <?php echo $active_tab == 'config' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Configuration', 'restaurant-pos'); ?>
        </a>
        <a href="?page=restaurant-pos-delivery-settings&tab=areas" class="nav-tab <?php echo $active_tab == 'areas' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Delivery Areas', 'restaurant-pos'); ?>
        </a>
        <a href="?page=restaurant-pos-delivery-settings&tab=charges" class="nav-tab <?php echo $active_tab == 'charges' ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html__('Delivery Charges', 'restaurant-pos'); ?>
        </a>
    </h2>
    
    <?php if ($active_tab == 'config'): ?>
        <!-- Configuration Tab -->
        <div class="tab-content">
            <form method="post" class="rpos-settings-form">
                <?php wp_nonce_field('rpos_delivery_config_action', 'rpos_delivery_config_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="minimum_free_delivery_amount"><?php echo esc_html__('Minimum Order for Free Delivery', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="minimum_free_delivery_amount" name="minimum_free_delivery_amount" 
                                   class="regular-text" step="0.01" min="0"
                                   value="<?php echo esc_attr($minimum_free_delivery); ?>">
                            <p class="description">
                                <?php echo esc_html__('Orders above this amount will have free delivery. Set to 0 to disable.', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fuel_unit"><?php echo esc_html__('Fuel Unit', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <select id="fuel_unit" name="fuel_unit">
                                <option value="liters" <?php selected($fuel_unit, 'liters'); ?>><?php echo esc_html__('Liters', 'restaurant-pos'); ?></option>
                                <option value="rupees" <?php selected($fuel_unit, 'rupees'); ?>><?php echo esc_html__('Rupees', 'restaurant-pos'); ?></option>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Unit for tracking fuel in delivery logs', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php echo esc_html__('Currency', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <strong><?php echo esc_html($currency); ?></strong>
                            <p class="description">
                                <?php echo esc_html__('Set in main POS settings', 'restaurant-pos'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Save Configuration', 'restaurant-pos'); ?>">
                </p>
            </form>
        </div>
        
    <?php elseif ($active_tab == 'areas'): ?>
        <!-- Delivery Areas Tab -->
        <div class="tab-content">
            <h2><?php echo esc_html__('Add/Edit Delivery Area', 'restaurant-pos'); ?></h2>
            <form method="post" class="rpos-settings-form">
                <?php wp_nonce_field('rpos_delivery_area_action', 'rpos_delivery_area_nonce'); ?>
                <input type="hidden" id="area_id" name="area_id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="area_name"><?php echo esc_html__('Area Name', 'restaurant-pos'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="area_name" name="area_name" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="distance_value"><?php echo esc_html__('Distance (km)', 'restaurant-pos'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="distance_value" name="distance_value" class="regular-text" step="0.01" min="0" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="is_active"><?php echo esc_html__('Active', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Save Area', 'restaurant-pos'); ?>">
                    <button type="button" class="button" onclick="document.getElementById('area_id').value=''; document.getElementById('area_name').value=''; document.getElementById('distance_value').value=''; document.getElementById('is_active').checked=true;"><?php echo esc_html__('Clear', 'restaurant-pos'); ?></button>
                </p>
            </form>
            
            <h2><?php echo esc_html__('Existing Delivery Areas', 'restaurant-pos'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Distance (km)', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($delivery_areas)): ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('No delivery areas found.', 'restaurant-pos'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($delivery_areas as $area): ?>
                            <tr>
                                <td><?php echo esc_html($area->name); ?></td>
                                <td><?php echo esc_html($area->distance_value); ?></td>
                                <td><?php echo $area->is_active ? esc_html__('Active', 'restaurant-pos') : esc_html__('Inactive', 'restaurant-pos'); ?></td>
                                <td>
                                    <button type="button" class="button button-small" onclick="editArea(<?php echo esc_js(json_encode($area)); ?>)"><?php echo esc_html__('Edit', 'restaurant-pos'); ?></button>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=restaurant-pos-delivery-settings&tab=areas&action=delete_area&id=' . $area->id), 'delete_area_' . $area->id)); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this area?', 'restaurant-pos')); ?>')">
                                        <?php echo esc_html__('Delete', 'restaurant-pos'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($active_tab == 'charges'): ?>
        <!-- Delivery Charges Tab -->
        <div class="tab-content">
            <h2><?php echo esc_html__('Add/Edit Delivery Charge Rule', 'restaurant-pos'); ?></h2>
            <form method="post" class="rpos-settings-form">
                <?php wp_nonce_field('rpos_delivery_charge_action', 'rpos_delivery_charge_nonce'); ?>
                <input type="hidden" id="charge_id" name="charge_id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="distance_from"><?php echo esc_html__('Distance From (km)', 'restaurant-pos'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="distance_from" name="distance_from" class="regular-text" step="0.01" min="0" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="distance_to"><?php echo esc_html__('Distance To (km)', 'restaurant-pos'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="distance_to" name="distance_to" class="regular-text" step="0.01" min="0" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="charge_amount"><?php echo esc_html__('Charge Amount', 'restaurant-pos'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="number" id="charge_amount" name="charge_amount" class="regular-text" step="0.01" min="0" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="charge_is_active"><?php echo esc_html__('Active', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="charge_is_active" name="is_active" value="1" checked>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Save Charge Rule', 'restaurant-pos'); ?>">
                    <button type="button" class="button" onclick="document.getElementById('charge_id').value=''; document.getElementById('distance_from').value=''; document.getElementById('distance_to').value=''; document.getElementById('charge_amount').value=''; document.getElementById('charge_is_active').checked=true;"><?php echo esc_html__('Clear', 'restaurant-pos'); ?></button>
                </p>
            </form>
            
            <h2><?php echo esc_html__('Existing Delivery Charge Rules', 'restaurant-pos'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Distance From (km)', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Distance To (km)', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Charge Amount', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($delivery_charges)): ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No delivery charge rules found.', 'restaurant-pos'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($delivery_charges as $charge): ?>
                            <tr>
                                <td><?php echo esc_html($charge->distance_from); ?></td>
                                <td><?php echo esc_html($charge->distance_to); ?></td>
                                <td><?php echo esc_html($currency . number_format($charge->charge_amount, 2)); ?></td>
                                <td><?php echo $charge->is_active ? esc_html__('Active', 'restaurant-pos') : esc_html__('Inactive', 'restaurant-pos'); ?></td>
                                <td>
                                    <button type="button" class="button button-small" onclick="editCharge(<?php echo esc_js(json_encode($charge)); ?>)"><?php echo esc_html__('Edit', 'restaurant-pos'); ?></button>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=restaurant-pos-delivery-settings&tab=charges&action=delete_charge&id=' . $charge->id), 'delete_charge_' . $charge->id)); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this charge rule?', 'restaurant-pos')); ?>')">
                                        <?php echo esc_html__('Delete', 'restaurant-pos'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function editArea(area) {
    document.getElementById('area_id').value = area.id;
    document.getElementById('area_name').value = area.name;
    document.getElementById('distance_value').value = area.distance_value;
    document.getElementById('is_active').checked = area.is_active == 1;
    window.scrollTo(0, 0);
}

function editCharge(charge) {
    document.getElementById('charge_id').value = charge.id;
    document.getElementById('distance_from').value = charge.distance_from;
    document.getElementById('distance_to').value = charge.distance_to;
    document.getElementById('charge_amount').value = charge.charge_amount;
    document.getElementById('charge_is_active').checked = charge.is_active == 1;
    window.scrollTo(0, 0);
}
</script>

<style>
.required {
    color: red;
}
.tab-content {
    margin-top: 20px;
}
</style>
