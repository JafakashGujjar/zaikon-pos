<?php
/**
 * Ingredients Waste/Spoilage Logging Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_waste_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_waste_nonce'], 'rpos_waste_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_inventory')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $ingredient_id = absint($_POST['ingredient_id'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $reason = sanitize_text_field($_POST['reason'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if ($ingredient_id && $quantity > 0 && !empty($reason)) {
        $result = RPOS_Ingredients::log_waste($ingredient_id, $quantity, $reason, $notes);
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Waste logged successfully! Stock has been deducted.', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to log waste.', 'restaurant-pos') . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html__('Please fill in all required fields.', 'restaurant-pos') . '</p></div>';
    }
}

// Get all ingredients for dropdown
$ingredients = RPOS_Ingredients::get_all();

// Get waste history
$waste_history = RPOS_Ingredients::get_waste_history(null, null, null, 50);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Waste / Spoilage Log', 'restaurant-pos'); ?></h1>
    <hr class="wp-header-end">
    
    <div style="max-width: 800px; margin: 20px 0;">
        <h2><?php esc_html_e('Log Waste/Spoilage', 'restaurant-pos'); ?></h2>
        <form method="post" action="?page=restaurant-pos-ingredients-waste">
            <?php wp_nonce_field('rpos_waste_action', 'rpos_waste_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ingredient_id"><?php esc_html_e('Ingredient', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select name="ingredient_id" id="ingredient_id" class="regular-text" required onchange="loadBatches(this.value)">
                            <option value=""><?php esc_html_e('-- Select Ingredient --', 'restaurant-pos'); ?></option>
                            <?php foreach ($ingredients as $ing): ?>
                                <option value="<?php echo esc_attr($ing->id); ?>">
                                    <?php echo esc_html($ing->name); ?> (<?php echo esc_html(RPOS_Inventory_Settings::format_quantity($ing->current_stock_quantity, $ing->unit)); ?> available)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr id="batch_selector" style="display:none;">
                    <th scope="row">
                        <label for="batch_id"><?php esc_html_e('Batch (Optional)', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <select name="batch_id" id="batch_id" class="regular-text">
                            <option value=""><?php esc_html_e('-- Auto-select by FIFO/FEFO --', 'restaurant-pos'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Leave empty to let the system auto-select batches based on your consumption strategy.', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="quantity"><?php esc_html_e('Quantity', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" name="quantity" id="quantity" 
                               step="0.001" min="0.001" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Amount of ingredient wasted/spoiled.', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="reason"><?php esc_html_e('Reason', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select name="reason" id="reason" required>
                            <option value=""><?php esc_html_e('-- Select Reason --', 'restaurant-pos'); ?></option>
                            <option value="Expired"><?php esc_html_e('Expired', 'restaurant-pos'); ?></option>
                            <option value="Spoiled"><?php esc_html_e('Spoiled / Contaminated', 'restaurant-pos'); ?></option>
                            <option value="Burnt"><?php esc_html_e('Burnt / Overcooked', 'restaurant-pos'); ?></option>
                            <option value="Returned"><?php esc_html_e('Returned by Customer', 'restaurant-pos'); ?></option>
                            <option value="Preparation Error"><?php esc_html_e('Preparation Error', 'restaurant-pos'); ?></option>
                            <option value="Lost"><?php esc_html_e('Lost / Miscount', 'restaurant-pos'); ?></option>
                            <option value="Theft"><?php esc_html_e('Theft (Sensitive)', 'restaurant-pos'); ?></option>
                            <option value="Damaged"><?php esc_html_e('Damaged / Broken', 'restaurant-pos'); ?></option>
                            <option value="Other"><?php esc_html_e('Other', 'restaurant-pos'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notes"><?php esc_html_e('Notes', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <textarea name="notes" id="notes" class="regular-text" rows="3"></textarea>
                        <p class="description"><?php esc_html_e('Additional notes about the waste (optional).', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Log Waste', 'restaurant-pos'); ?>
                </button>
            </p>
        </form>
    </div>
    
    <script>
    function loadBatches(ingredientId) {
        if (!ingredientId) {
            document.getElementById('batch_selector').style.display = 'none';
            return;
        }
        
        // Show batch selector
        document.getElementById('batch_selector').style.display = 'table-row';
        
        // Load batches via AJAX (simplified - in production use proper AJAX)
        var batchSelect = document.getElementById('batch_id');
        batchSelect.innerHTML = '<option value=""><?php esc_html_e('-- Auto-select by FIFO/FEFO --', 'restaurant-pos'); ?></option>';
        
        <?php
        // Pre-load batch data for all ingredients (simplified approach)
        global $wpdb;
        $all_batches = $wpdb->get_results(
            "SELECT b.*, i.unit
             FROM {$wpdb->prefix}rpos_ingredient_batches b
             LEFT JOIN {$wpdb->prefix}rpos_ingredients i ON b.ingredient_id = i.id
             WHERE b.status = 'active' AND b.quantity_remaining > 0
             ORDER BY b.expiry_date ASC, b.purchase_date ASC"
        );
        
        $batches_by_ingredient = array();
        foreach ($all_batches as $batch) {
            if (!isset($batches_by_ingredient[$batch->ingredient_id])) {
                $batches_by_ingredient[$batch->ingredient_id] = array();
            }
            $batches_by_ingredient[$batch->ingredient_id][] = $batch;
        }
        ?>
        
        var batchData = <?php echo json_encode($batches_by_ingredient); ?>;
        
        if (batchData[ingredientId]) {
            batchData[ingredientId].forEach(function(batch) {
                var option = document.createElement('option');
                option.value = batch.id;
                var expiryText = batch.expiry_date ? ' (Exp: ' + batch.expiry_date + ')' : '';
                option.textContent = batch.batch_number + ' - ' + parseFloat(batch.quantity_remaining).toFixed(3) + ' ' + batch.unit + expiryText;
                batchSelect.appendChild(option);
            });
        }
    }
    </script>
    
    <hr style="margin: 40px 0;">
    
    <h2><?php esc_html_e('Waste History', 'restaurant-pos'); ?></h2>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Date', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Ingredient', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Batch', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Quantity', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Reason', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Notes', 'restaurant-pos'); ?></th>
                <th><?php esc_html_e('Logged By', 'restaurant-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($waste_history)): ?>
                <?php foreach ($waste_history as $record): ?>
                    <?php
                    // Get batch info if batch_id exists
                    $batch_info = '';
                    if (!empty($record->batch_id)) {
                        $batch = RPOS_Batches::get($record->batch_id);
                        if ($batch) {
                            $batch_info = $batch->batch_number;
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html(RPOS_Timezone::format($record->created_at)); ?></td>
                        <td><strong><?php echo esc_html($record->ingredient_name); ?></strong></td>
                        <td>
                            <?php if ($batch_info): ?>
                                <code><?php echo esc_html($batch_info); ?></code>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(RPOS_Inventory_Settings::format_quantity($record->quantity, $record->unit)); ?></td>
                        <td>
                            <span class="badge" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; background-color: 
                                <?php 
                                switch($record->reason) {
                                    case 'Expired': echo '#ffebee; color: #c62828;'; break;
                                    case 'Spoiled': echo '#fff3e0; color: #ef6c00;'; break;
                                    case 'Burnt': echo '#fff8e1; color: #f57c00;'; break;
                                    case 'Theft': echo '#fce4ec; color: #ad1457;'; break;
                                    case 'Damaged': echo '#fce4ec; color: #c2185b;'; break;
                                    default: echo '#f5f5f5; color: #616161;';
                                }
                                ?>
                            ">
                                <?php echo esc_html($record->reason); ?>
                            </span>
                        </td>
                        <td><?php echo !empty($record->notes) ? esc_html($record->notes) : '-'; ?></td>
                        <td><?php echo !empty($record->user_name) ? esc_html($record->user_name) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No waste records found.', 'restaurant-pos'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
