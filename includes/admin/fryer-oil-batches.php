<?php
/**
 * Fryer Oil Batches Management Page
 * Create, view, and manage oil batches
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('rpos_manage_inventory')) {
    wp_die('Permission denied');
}

$message = '';
$message_type = '';

// Handle form submissions
if (isset($_POST['rpos_fryer_batch_nonce']) && check_admin_referer('rpos_fryer_batch_action', 'rpos_fryer_batch_nonce')) {
    $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
    
    switch ($action) {
        case 'create_batch':
            $batch_id = RPOS_Fryer_Oil_Batches::create(array(
                'batch_name' => sanitize_text_field($_POST['batch_name'] ?? ''),
                'fryer_id' => !empty($_POST['fryer_id']) ? absint($_POST['fryer_id']) : null,
                'oil_added_at' => sanitize_text_field($_POST['oil_added_at'] ?? RPOS_Timezone::current_utc_mysql()),
                'oil_capacity' => !empty($_POST['oil_capacity']) ? floatval($_POST['oil_capacity']) : null,
                'target_usage_units' => floatval($_POST['target_usage_units'] ?? 120),
                'time_threshold_hours' => absint($_POST['time_threshold_hours'] ?? 24),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                'created_by' => get_current_user_id()
            ));
            
            if ($batch_id) {
                $message = 'Batch created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to create batch.';
                $message_type = 'error';
            }
            break;
        
        case 'close_batch':
            $batch_id = absint($_POST['batch_id'] ?? 0);
            $notes = sanitize_textarea_field($_POST['close_notes'] ?? '');
            
            $result = RPOS_Fryer_Oil_Batches::close_batch($batch_id, get_current_user_id(), $notes);
            
            if ($result) {
                $message = 'Batch closed successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to close batch.';
                $message_type = 'error';
            }
            break;
    }
}

// Get batches
$status_filter = $_GET['status'] ?? 'active';
$batches = RPOS_Fryer_Oil_Batches::get_all(array(
    'status' => $status_filter,
    'limit' => 100
));

// Get fryers
global $wpdb;
$fryers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rpos_fryers ORDER BY name ASC");

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Fryer Oil Batches', 'restaurant-pos'); ?></h1>
    <a href="#" class="page-title-action" onclick="document.getElementById('add-batch-form').style.display='block'; return false;">
        <?php _e('Add New Batch', 'restaurant-pos'); ?>
    </a>
    <hr class="wp-header-end">
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Add Batch Form (Hidden by default) -->
    <div id="add-batch-form" style="display: none; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2><?php _e('Add New Oil Batch', 'restaurant-pos'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('rpos_fryer_batch_action', 'rpos_fryer_batch_nonce'); ?>
            <input type="hidden" name="action" value="create_batch">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batch_name"><?php _e('Batch Name', 'restaurant-pos'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="batch_name" id="batch_name" class="regular-text" 
                               value="<?php echo esc_attr('Batch ' . date('Y-m-d H:i')); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fryer_id"><?php _e('Fryer', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <select name="fryer_id" id="fryer_id">
                            <option value=""><?php _e('Default Fryer', 'restaurant-pos'); ?></option>
                            <?php foreach ($fryers as $fryer): ?>
                                <option value="<?php echo esc_attr($fryer->id); ?>">
                                    <?php echo esc_html($fryer->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="oil_added_at"><?php _e('Oil Added At', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="datetime-local" name="oil_added_at" id="oil_added_at" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="oil_capacity"><?php _e('Oil Capacity (Liters)', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="oil_capacity" id="oil_capacity" step="0.1" class="small-text">
                        <p class="description"><?php _e('Optional: Total capacity of oil in liters', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="target_usage_units"><?php _e('Target Usage Units', 'restaurant-pos'); ?> *</label>
                    </th>
                    <td>
                        <input type="number" name="target_usage_units" id="target_usage_units" 
                               step="0.1" class="small-text" value="120" required>
                        <p class="description"><?php _e('Number of usage units before oil change is required', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="time_threshold_hours"><?php _e('Time Threshold (Hours)', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="time_threshold_hours" id="time_threshold_hours" 
                               class="small-text" value="24">
                        <p class="description"><?php _e('Hours after which reminder triggers (default: 24)', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notes"><?php _e('Notes', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <textarea name="notes" id="notes" rows="3" class="large-text"></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Create Batch', 'restaurant-pos'); ?></button>
                <button type="button" class="button" onclick="document.getElementById('add-batch-form').style.display='none';">
                    <?php _e('Cancel', 'restaurant-pos'); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- Filter Tabs -->
    <div class="nav-tab-wrapper" style="margin: 20px 0;">
        <a href="?page=restaurant-pos-fryer-oil-batches&status=active" 
           class="nav-tab <?php echo $status_filter === 'active' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Active Batches', 'restaurant-pos'); ?>
        </a>
        <a href="?page=restaurant-pos-fryer-oil-batches&status=closed" 
           class="nav-tab <?php echo $status_filter === 'closed' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Closed Batches', 'restaurant-pos'); ?>
        </a>
        <a href="?page=restaurant-pos-fryer-oil-batches&status=" 
           class="nav-tab <?php echo $status_filter === '' ? 'nav-tab-active' : ''; ?>">
            <?php _e('All Batches', 'restaurant-pos'); ?>
        </a>
    </div>
    
    <!-- Batches List -->
    <?php if (empty($batches)): ?>
        <div class="notice notice-info">
            <p><?php _e('No batches found.', 'restaurant-pos'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Batch Name', 'restaurant-pos'); ?></th>
                    <th><?php _e('Fryer', 'restaurant-pos'); ?></th>
                    <th><?php _e('Started', 'restaurant-pos'); ?></th>
                    <th><?php _e('Closed', 'restaurant-pos'); ?></th>
                    <th><?php _e('Usage', 'restaurant-pos'); ?></th>
                    <th><?php _e('Status', 'restaurant-pos'); ?></th>
                    <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $batch): ?>
                    <?php $stats = RPOS_Fryer_Oil_Batches::get_usage_stats($batch->id); ?>
                    <tr>
                        <td><strong><?php echo esc_html($batch->batch_name); ?></strong></td>
                        <td><?php echo esc_html($batch->fryer_name ?: 'Default'); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->oil_added_at))); ?></td>
                        <td>
                            <?php if ($batch->closed_at): ?>
                                <?php echo esc_html(date('Y-m-d H:i', strtotime($batch->closed_at))); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo number_format($stats['total_units'], 1); ?> / 
                            <?php echo number_format($batch->target_usage_units, 1); ?>
                            (<?php echo number_format($stats['usage_percentage'], 1); ?>%)
                        </td>
                        <td>
                            <?php if ($batch->status === 'active'): ?>
                                <span style="color: #46b450;">●</span> <?php _e('Active', 'restaurant-pos'); ?>
                            <?php else: ?>
                                <span style="color: #999;">●</span> <?php _e('Closed', 'restaurant-pos'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-fryer-oil-reports&batch_id=' . $batch->id); ?>" 
                               class="button button-small">
                                <?php _e('Details', 'restaurant-pos'); ?>
                            </a>
                            
                            <?php if ($batch->status === 'active'): ?>
                                <button class="button button-small" 
                                        onclick="closeBatch(<?php echo $batch->id; ?>, '<?php echo esc_js($batch->batch_name); ?>')">
                                    <?php _e('Close Batch', 'restaurant-pos'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Close Batch Modal -->
<div id="close-batch-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="background: #fff; max-width: 500px; margin: 100px auto; padding: 20px; border-radius: 5px;">
        <h2><?php _e('Close Oil Batch', 'restaurant-pos'); ?></h2>
        <form method="post" id="close-batch-form">
            <?php wp_nonce_field('rpos_fryer_batch_action', 'rpos_fryer_batch_nonce'); ?>
            <input type="hidden" name="action" value="close_batch">
            <input type="hidden" name="batch_id" id="close_batch_id">
            
            <p>
                <strong><?php _e('Batch:', 'restaurant-pos'); ?></strong> 
                <span id="close_batch_name"></span>
            </p>
            
            <p>
                <label for="close_notes"><?php _e('Closing Notes:', 'restaurant-pos'); ?></label><br>
                <textarea name="close_notes" id="close_notes" rows="3" style="width: 100%;" 
                          placeholder="<?php _e('Optional: Add notes about the oil change...', 'restaurant-pos'); ?>"></textarea>
            </p>
            
            <p>
                <button type="submit" class="button button-primary"><?php _e('Close Batch', 'restaurant-pos'); ?></button>
                <button type="button" class="button" onclick="document.getElementById('close-batch-modal').style.display='none';">
                    <?php _e('Cancel', 'restaurant-pos'); ?>
                </button>
            </p>
        </form>
    </div>
</div>

<script>
function closeBatch(batchId, batchName) {
    document.getElementById('close_batch_id').value = batchId;
    document.getElementById('close_batch_name').textContent = batchName;
    document.getElementById('close-batch-modal').style.display = 'block';
}
</script>
