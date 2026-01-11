<?php
/**
 * Daily Rider Log Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_delivery_log_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_delivery_log_nonce'], 'rpos_delivery_log_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_inventory') && !current_user_can('manage_options')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $log_data = array(
        'date' => sanitize_text_field($_POST['log_date']),
        'rider_id' => !empty($_POST['rider_id']) ? absint($_POST['rider_id']) : null,
        'rider_name' => sanitize_text_field($_POST['rider_name']),
        'bike_id' => sanitize_text_field($_POST['bike_id']),
        'fuel_amount' => floatval($_POST['fuel_amount']),
        'fuel_unit' => sanitize_text_field($_POST['fuel_unit']),
        'km_start' => floatval($_POST['km_start']),
        'km_end' => floatval($_POST['km_end']),
        'deliveries_count' => absint($_POST['deliveries_count']),
        'notes' => sanitize_textarea_field($_POST['notes'])
    );
    
    if (isset($_POST['log_id']) && $_POST['log_id']) {
        RPOS_Delivery_Logs::update($_POST['log_id'], $log_data);
        $message = __('Delivery log updated successfully!', 'restaurant-pos');
    } else {
        RPOS_Delivery_Logs::create($log_data);
        $message = __('Delivery log created successfully!', 'restaurant-pos');
    }
    $message_type = 'success';
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_log_' . $_GET['id'])) {
        RPOS_Delivery_Logs::delete($_GET['id']);
        $message = __('Delivery log deleted successfully!', 'restaurant-pos');
        $message_type = 'success';
    }
}

// Get settings
$fuel_unit = RPOS_Delivery_Settings::get('fuel_unit', 'liters');

// Get all logs
$logs = RPOS_Delivery_Logs::get_all(array('limit' => 50));

// Get WordPress users for rider selection
$users = get_users(array('orderby' => 'display_name'));
?>

<div class="wrap rpos-delivery-logs">
    <h1><?php echo esc_html__('Daily Rider Log', 'restaurant-pos'); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="rpos-log-form-section">
        <h2><?php echo esc_html__('Add/Edit Daily Log', 'restaurant-pos'); ?></h2>
        <form method="post" class="rpos-delivery-log-form">
            <?php wp_nonce_field('rpos_delivery_log_action', 'rpos_delivery_log_nonce'); ?>
            <input type="hidden" id="log_id" name="log_id" value="">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="log_date"><?php echo esc_html__('Date', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="date" id="log_date" name="log_date" class="regular-text" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rider_name"><?php echo esc_html__('Rider Name', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="rider_name" name="rider_name" class="regular-text" required>
                        <p class="description">
                            <?php echo esc_html__('Enter rider name or select from users', 'restaurant-pos'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rider_id"><?php echo esc_html__('Or Select User', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <select id="rider_id" name="rider_id" class="regular-text" onchange="if(this.value) document.getElementById('rider_name').value = this.options[this.selectedIndex].text;">
                            <option value=""><?php echo esc_html__('-- Select User --', 'restaurant-pos'); ?></option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="bike_id"><?php echo esc_html__('Bike ID', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="bike_id" name="bike_id" class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="fuel_amount"><?php echo esc_html__('Fuel Filled', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" id="fuel_amount" name="fuel_amount" class="regular-text" step="0.01" min="0" required>
                        <span class="fuel-unit-label"><?php echo esc_html($fuel_unit); ?></span>
                        <input type="hidden" id="fuel_unit" name="fuel_unit" value="<?php echo esc_attr($fuel_unit); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="km_start"><?php echo esc_html__('Odometer Start', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" id="km_start" name="km_start" class="regular-text" step="0.01" min="0" required>
                        <span>km</span>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="km_end"><?php echo esc_html__('Odometer End', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" id="km_end" name="km_end" class="regular-text" step="0.01" min="0" required>
                        <span>km</span>
                        <p class="description">
                            <span id="total_km_display"></span>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="deliveries_count"><?php echo esc_html__('Number of Deliveries', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" id="deliveries_count" name="deliveries_count" class="regular-text" min="0" value="0" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notes"><?php echo esc_html__('Notes', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <textarea id="notes" name="notes" class="large-text" rows="3"></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Save Log', 'restaurant-pos'); ?>">
                <button type="button" class="button" onclick="clearForm()"><?php echo esc_html__('Clear', 'restaurant-pos'); ?></button>
            </p>
        </form>
    </div>
    
    <div class="rpos-logs-list-section">
        <h2><?php echo esc_html__('Recent Logs', 'restaurant-pos'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Date', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Rider', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Bike', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Fuel', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('KM Start', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('KM End', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Total KM', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Deliveries', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="9"><?php echo esc_html__('No logs found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        // Get actual delivery count from orders if rider_id is available
                        $actual_delivery_count = null;
                        $actual_total_km = null;
                        if ($log->rider_id) {
                            $actual_delivery_count = RPOS_Delivery_Logs::get_delivery_count_for_rider($log->rider_id, $log->date);
                            $actual_total_km = RPOS_Delivery_Logs::get_total_km_for_rider($log->rider_id, $log->date);
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($log->date))); ?></td>
                            <td><?php echo esc_html($log->rider_name); ?></td>
                            <td><?php echo esc_html($log->bike_id); ?></td>
                            <td><?php echo esc_html(number_format($log->fuel_amount, 2) . ' ' . $log->fuel_unit); ?></td>
                            <td><?php echo esc_html(number_format($log->km_start, 2)); ?></td>
                            <td><?php echo esc_html(number_format($log->km_end, 2)); ?></td>
                            <td>
                                <?php echo esc_html(number_format($log->total_km, 2)); ?>
                                <?php if ($actual_total_km !== null && $actual_total_km > 0): ?>
                                    <br><small style="color: #666;">(Orders: <?php echo esc_html(number_format($actual_total_km, 2)); ?> km)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($log->deliveries_count); ?>
                                <?php if ($actual_delivery_count !== null): ?>
                                    <br><small style="color: #2271b1; font-weight: bold;">(Actual: <?php echo esc_html($actual_delivery_count); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small" onclick="editLog(<?php echo esc_js(json_encode($log)); ?>)"><?php echo esc_html__('Edit', 'restaurant-pos'); ?></button>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=restaurant-pos-delivery-logs&action=delete&id=' . $log->id), 'delete_log_' . $log->id)); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this log?', 'restaurant-pos')); ?>')">
                                    <?php echo esc_html__('Delete', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('log_id').value = '';
    document.getElementById('log_date').value = '<?php echo esc_js(date('Y-m-d')); ?>';
    document.getElementById('rider_name').value = '';
    document.getElementById('rider_id').value = '';
    document.getElementById('bike_id').value = '';
    document.getElementById('fuel_amount').value = '';
    document.getElementById('km_start').value = '';
    document.getElementById('km_end').value = '';
    document.getElementById('deliveries_count').value = '0';
    document.getElementById('notes').value = '';
    document.getElementById('total_km_display').textContent = '';
}

function editLog(log) {
    document.getElementById('log_id').value = log.id;
    document.getElementById('log_date').value = log.date;
    document.getElementById('rider_name').value = log.rider_name;
    document.getElementById('rider_id').value = log.rider_id || '';
    document.getElementById('bike_id').value = log.bike_id;
    document.getElementById('fuel_amount').value = log.fuel_amount;
    document.getElementById('km_start').value = log.km_start;
    document.getElementById('km_end').value = log.km_end;
    document.getElementById('deliveries_count').value = log.deliveries_count;
    document.getElementById('notes').value = log.notes || '';
    updateTotalKm();
    window.scrollTo(0, 0);
}

function updateTotalKm() {
    var kmStart = parseFloat(document.getElementById('km_start').value) || 0;
    var kmEnd = parseFloat(document.getElementById('km_end').value) || 0;
    var totalKm = kmEnd - kmStart;
    if (totalKm > 0) {
        document.getElementById('total_km_display').textContent = 'Total: ' + totalKm.toFixed(2) + ' km';
    } else {
        document.getElementById('total_km_display').textContent = '';
    }
}

document.getElementById('km_start').addEventListener('input', updateTotalKm);
document.getElementById('km_end').addEventListener('input', updateTotalKm);
</script>

<style>
.required {
    color: red;
}
.rpos-log-form-section,
.rpos-logs-list-section {
    margin-top: 20px;
}
.fuel-unit-label {
    margin-left: 5px;
    font-weight: bold;
}
#total_km_display {
    font-weight: bold;
    color: #0073aa;
}
</style>
