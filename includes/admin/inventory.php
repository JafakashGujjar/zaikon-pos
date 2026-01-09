<?php
/**
 * Inventory Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_inventory_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_inventory_nonce'], 'rpos_inventory_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_inventory')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'adjust_stock' && isset($_POST['product_id'])) {
        $product_id = absint($_POST['product_id']);
        $change_amount = intval($_POST['change_amount'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        RPOS_Inventory::adjust_stock($product_id, $change_amount, $reason);
        echo '<div class="notice notice-success"><p>' . esc_html__('Stock adjusted successfully!', 'restaurant-pos') . '</p></div>';
    } elseif ($action === 'update_cost' && isset($_POST['product_id'])) {
        $product_id = absint($_POST['product_id']);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        
        RPOS_Inventory::update($product_id, array('cost_price' => $cost_price));
        echo '<div class="notice notice-success"><p>' . esc_html__('Cost price updated successfully!', 'restaurant-pos') . '</p></div>';
    } elseif ($action === 'add_ingredient' && isset($_POST['ingredient_name'])) {
        $ingredient_name = sanitize_text_field($_POST['ingredient_name'] ?? '');
        $unit = sanitize_text_field($_POST['unit'] ?? 'pcs');
        $default_cost = floatval($_POST['default_cost_per_unit'] ?? 0);
        
        if (!empty($ingredient_name)) {
            // Create new product (ingredient)
            $product_data = array(
                'name' => $ingredient_name,
                'sku' => '',
                'category_id' => 0,
                'selling_price' => 0,
                'image_url' => '',
                'description' => '',
                'is_active' => 1
            );
            
            $product_id = RPOS_Products::create($product_data);
            
            if ($product_id) {
                // Update inventory with unit and cost
                $inventory_data = array('unit' => $unit);
                if ($default_cost > 0) {
                    $inventory_data['cost_price'] = $default_cost;
                }
                RPOS_Inventory::update($product_id, $inventory_data);
                
                echo '<div class="notice notice-success"><p>' . esc_html__('Ingredient added successfully!', 'restaurant-pos') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to create ingredient.', 'restaurant-pos') . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide ingredient name.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'add_purchase' && isset($_POST['product_id'])) {
        $product_id = absint($_POST['product_id']);
        $quantity = floatval($_POST['quantity'] ?? 0);
        $unit = sanitize_text_field($_POST['unit'] ?? 'pcs');
        $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
        $supplier = sanitize_text_field($_POST['supplier'] ?? '');
        $date_purchased = sanitize_text_field($_POST['date_purchased'] ?? date('Y-m-d'));
        
        if ($quantity > 0 && $supplier) {
            // Increase stock
            RPOS_Inventory::adjust_stock(
                $product_id, 
                $quantity, 
                'Purchase from ' . $supplier . ' (' . $quantity . ' ' . $unit . ') on ' . $date_purchased
            );
            
            // Update cost price if provided
            if ($cost_per_unit > 0) {
                RPOS_Inventory::update($product_id, array('cost_price' => $cost_per_unit));
            }
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Purchase recorded successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Please provide quantity and supplier name.', 'restaurant-pos') . '</p></div>';
        }
    }
}

// Get inventory items
$inventory_items = RPOS_Inventory::get_all();
$low_stock_threshold = intval(RPOS_Settings::get('low_stock_threshold', 10));

// Get stock movements
$stock_movements = RPOS_Inventory::get_stock_movements(null, 50);
?>

<div class="wrap rpos-inventory">
    <h1><?php echo esc_html__('Inventory Management', 'restaurant-pos'); ?></h1>
    
    <div class="rpos-inventory-list">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;"><?php echo esc_html__('Current Stock Levels', 'restaurant-pos'); ?></h2>
            <button type="button" class="button button-primary" id="rpos-add-purchase-btn">
                <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                <?php echo esc_html__('Add Purchase', 'restaurant-pos'); ?>
            </button>
        </div>
        
        <?php if (!empty($inventory_items)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Product', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('SKU', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Current Stock', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Cost Price', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory_items as $item): 
                    $is_low_stock = $item->quantity <= $low_stock_threshold;
                ?>
                <tr class="<?php echo $is_low_stock ? 'rpos-low-stock' : ''; ?>">
                    <td>
                        <?php echo esc_html($item->product_name); ?>
                        <?php if ($is_low_stock): ?>
                        <span class="rpos-badge rpos-badge-warning"><?php echo esc_html__('Low Stock', 'restaurant-pos'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($item->sku ?: '-'); ?></td>
                    <td>
                        <strong><?php echo absint($item->quantity); ?></strong>
                    </td>
                    <td>
                        <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                        <?php echo number_format($item->cost_price, 2); ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small rpos-adjust-stock" 
                                data-product-id="<?php echo esc_attr($item->product_id); ?>"
                                data-product-name="<?php echo esc_attr($item->product_name); ?>"
                                data-current-stock="<?php echo esc_attr($item->quantity); ?>">
                            <?php echo esc_html__('Adjust Stock', 'restaurant-pos'); ?>
                        </button>
                        <button type="button" class="button button-small rpos-update-cost" 
                                data-product-id="<?php echo esc_attr($item->product_id); ?>"
                                data-product-name="<?php echo esc_attr($item->product_name); ?>"
                                data-cost-price="<?php echo esc_attr($item->cost_price); ?>">
                            <?php echo esc_html__('Update Cost', 'restaurant-pos'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php echo esc_html__('No inventory items found.', 'restaurant-pos'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="rpos-stock-movements">
        <h2><?php echo esc_html__('Recent Stock Movements', 'restaurant-pos'); ?></h2>
        
        <?php if (!empty($stock_movements)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Date', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Product', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Change', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Reason', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('User', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stock_movements as $movement): ?>
                <tr>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($movement->created_at))); ?></td>
                    <td><?php echo esc_html($movement->product_name); ?></td>
                    <td>
                        <span class="rpos-stock-change <?php echo $movement->change_amount > 0 ? 'rpos-stock-increase' : 'rpos-stock-decrease'; ?>">
                            <?php echo $movement->change_amount > 0 ? '+' : ''; ?>
                            <?php echo absint($movement->change_amount); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($movement->reason ?: '-'); ?></td>
                    <td><?php echo esc_html($movement->user_name ?: 'System'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php echo esc_html__('No stock movements recorded.', 'restaurant-pos'); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="rpos-adjust-stock-modal" style="display:none;">
    <div class="rpos-modal-content">
        <h3><?php echo esc_html__('Adjust Stock', 'restaurant-pos'); ?></h3>
        <form method="post" id="rpos-adjust-stock-form">
            <?php wp_nonce_field('rpos_inventory_action', 'rpos_inventory_nonce'); ?>
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="product_id" id="adjust_product_id">
            
            <p>
                <strong><?php echo esc_html__('Product:', 'restaurant-pos'); ?></strong>
                <span id="adjust_product_name"></span>
            </p>
            <p>
                <strong><?php echo esc_html__('Current Stock:', 'restaurant-pos'); ?></strong>
                <span id="adjust_current_stock"></span>
            </p>
            
            <p>
                <label for="change_amount"><?php echo esc_html__('Change Amount:', 'restaurant-pos'); ?></label><br>
                <input type="number" id="change_amount" name="change_amount" required>
                <br><small><?php echo esc_html__('Use positive numbers to increase, negative to decrease', 'restaurant-pos'); ?></small>
            </p>
            
            <p>
                <label for="reason"><?php echo esc_html__('Reason:', 'restaurant-pos'); ?></label><br>
                <input type="text" id="reason" name="reason" class="regular-text" required>
            </p>
            
            <p>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Adjust Stock', 'restaurant-pos'); ?></button>
                <button type="button" class="button rpos-close-modal"><?php echo esc_html__('Cancel', 'restaurant-pos'); ?></button>
            </p>
        </form>
    </div>
</div>

<!-- Update Cost Modal -->
<div id="rpos-update-cost-modal" style="display:none;">
    <div class="rpos-modal-content">
        <h3><?php echo esc_html__('Update Cost Price', 'restaurant-pos'); ?></h3>
        <form method="post" id="rpos-update-cost-form">
            <?php wp_nonce_field('rpos_inventory_action', 'rpos_inventory_nonce'); ?>
            <input type="hidden" name="action" value="update_cost">
            <input type="hidden" name="product_id" id="cost_product_id">
            
            <p>
                <strong><?php echo esc_html__('Product:', 'restaurant-pos'); ?></strong>
                <span id="cost_product_name"></span>
            </p>
            
            <p>
                <label for="cost_price"><?php echo esc_html__('Cost Price:', 'restaurant-pos'); ?></label><br>
                <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" required>
            </p>
            
            <p>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Update Cost', 'restaurant-pos'); ?></button>
                <button type="button" class="button rpos-close-modal"><?php echo esc_html__('Cancel', 'restaurant-pos'); ?></button>
            </p>
        </form>
    </div>
</div>

<!-- Add Purchase Modal -->
<div id="rpos-add-purchase-modal" style="display:none;">
    <div class="rpos-modal-content">
        <h3><?php echo esc_html__('Add Purchase', 'restaurant-pos'); ?></h3>
        <form method="post" id="rpos-add-purchase-form">
            <?php wp_nonce_field('rpos_inventory_action', 'rpos_inventory_nonce'); ?>
            <input type="hidden" name="action" value="add_purchase">
            
            <p>
                <label for="purchase_product_id"><?php echo esc_html__('Inventory Item:', 'restaurant-pos'); ?> *</label><br>
                <select id="purchase_product_id" name="product_id" class="regular-text" required>
                    <option value=""><?php echo esc_html__('Select an item', 'restaurant-pos'); ?></option>
                    <option value="__add_new__"><?php echo esc_html__('+ Add New Ingredient', 'restaurant-pos'); ?></option>
                    <?php foreach ($inventory_items as $item): ?>
                    <option value="<?php echo esc_attr($item->product_id); ?>">
                        <?php echo esc_html($item->product_name); ?>
                        <?php echo $item->sku ? ' (' . esc_html($item->sku) . ')' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label for="purchase_quantity"><?php echo esc_html__('Quantity Purchased:', 'restaurant-pos'); ?> *</label><br>
                <input type="number" id="purchase_quantity" name="quantity" step="0.001" min="0.001" required class="regular-text">
            </p>
            
            <p>
                <label for="purchase_unit"><?php echo esc_html__('Unit:', 'restaurant-pos'); ?></label><br>
                <select id="purchase_unit" name="unit" class="regular-text">
                    <option value="pcs"><?php echo esc_html__('Pieces (pcs)', 'restaurant-pos'); ?></option>
                    <option value="kg"><?php echo esc_html__('Kilograms (kg)', 'restaurant-pos'); ?></option>
                    <option value="g"><?php echo esc_html__('Grams (g)', 'restaurant-pos'); ?></option>
                    <option value="l"><?php echo esc_html__('Liters (l)', 'restaurant-pos'); ?></option>
                    <option value="ml"><?php echo esc_html__('Milliliters (ml)', 'restaurant-pos'); ?></option>
                </select>
            </p>
            
            <p>
                <label for="purchase_cost"><?php echo esc_html__('Purchase Cost per Unit (optional):', 'restaurant-pos'); ?></label><br>
                <input type="number" id="purchase_cost" name="cost_per_unit" step="0.01" min="0" class="regular-text">
            </p>
            
            <p>
                <label for="purchase_supplier"><?php echo esc_html__('Supplier Name:', 'restaurant-pos'); ?> *</label><br>
                <input type="text" id="purchase_supplier" name="supplier" class="regular-text" required>
            </p>
            
            <p>
                <label for="purchase_date"><?php echo esc_html__('Date Purchased:', 'restaurant-pos'); ?></label><br>
                <input type="date" id="purchase_date" name="date_purchased" value="<?php echo date('Y-m-d'); ?>" class="regular-text">
            </p>
            
            <p>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Record Purchase', 'restaurant-pos'); ?></button>
                <button type="button" class="button rpos-close-modal"><?php echo esc_html__('Cancel', 'restaurant-pos'); ?></button>
            </p>
        </form>
    </div>
</div>

<!-- Add Ingredient Modal -->
<div id="rpos-add-ingredient-modal" style="display:none;">
    <div class="rpos-modal-content">
        <h3><?php echo esc_html__('Add New Ingredient', 'restaurant-pos'); ?></h3>
        <form method="post" id="rpos-add-ingredient-form">
            <?php wp_nonce_field('rpos_inventory_action', 'rpos_inventory_nonce'); ?>
            <input type="hidden" name="action" value="add_ingredient">
            
            <p>
                <label for="ingredient_name"><?php echo esc_html__('Ingredient Name:', 'restaurant-pos'); ?> *</label><br>
                <input type="text" id="ingredient_name" name="ingredient_name" class="regular-text" required>
            </p>
            
            <p>
                <label for="ingredient_unit"><?php echo esc_html__('Unit:', 'restaurant-pos'); ?></label><br>
                <select id="ingredient_unit" name="unit" class="regular-text">
                    <option value="pcs"><?php echo esc_html__('Pieces (pcs)', 'restaurant-pos'); ?></option>
                    <option value="kg"><?php echo esc_html__('Kilograms (kg)', 'restaurant-pos'); ?></option>
                    <option value="g"><?php echo esc_html__('Grams (g)', 'restaurant-pos'); ?></option>
                    <option value="l"><?php echo esc_html__('Liters (l)', 'restaurant-pos'); ?></option>
                    <option value="ml"><?php echo esc_html__('Milliliters (ml)', 'restaurant-pos'); ?></option>
                </select>
            </p>
            
            <p>
                <label for="ingredient_cost"><?php echo esc_html__('Default Cost per Unit (optional):', 'restaurant-pos'); ?></label><br>
                <input type="number" id="ingredient_cost" name="default_cost_per_unit" step="0.01" min="0" class="regular-text">
            </p>
            
            <p>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Save Ingredient', 'restaurant-pos'); ?></button>
                <button type="button" class="button rpos-close-modal" id="rpos-cancel-add-ingredient"><?php echo esc_html__('Cancel', 'restaurant-pos'); ?></button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Adjust stock modal
    $('.rpos-adjust-stock').on('click', function() {
        var productId = $(this).data('product-id');
        var productName = $(this).data('product-name');
        var currentStock = $(this).data('current-stock');
        
        $('#adjust_product_id').val(productId);
        $('#adjust_product_name').text(productName);
        $('#adjust_current_stock').text(currentStock);
        $('#change_amount').val('');
        $('#reason').val('');
        
        $('#rpos-adjust-stock-modal').fadeIn();
    });
    
    // Update cost modal
    $('.rpos-update-cost').on('click', function() {
        var productId = $(this).data('product-id');
        var productName = $(this).data('product-name');
        var costPrice = $(this).data('cost-price');
        
        $('#cost_product_id').val(productId);
        $('#cost_product_name').text(productName);
        $('#cost_price').val(costPrice);
        
        $('#rpos-update-cost-modal').fadeIn();
    });
    
    // Add purchase modal
    $('#rpos-add-purchase-btn').on('click', function() {
        $('#purchase_product_id').val('');
        $('#purchase_quantity').val('');
        $('#purchase_unit').val('pcs');
        $('#purchase_cost').val('');
        $('#purchase_supplier').val('');
        $('#purchase_date').val('<?php echo date('Y-m-d'); ?>');
        
        $('#rpos-add-purchase-modal').fadeIn();
    });
    
    // Close modal
    $('.rpos-close-modal').on('click', function() {
        $(this).closest('[id$="-modal"]').fadeOut();
    });
    
    // Handle "+ Add New Ingredient" selection
    $('#purchase_product_id').on('change', function() {
        if ($(this).val() === '__add_new__') {
            // Reset and show the add ingredient modal
            $('#ingredient_name').val('');
            $('#ingredient_unit').val('pcs');
            $('#ingredient_cost').val('');
            $('#rpos-add-ingredient-modal').fadeIn();
        }
    });
    
    // Handle cancel in add ingredient modal
    $('#rpos-cancel-add-ingredient').on('click', function() {
        $('#rpos-add-ingredient-modal').fadeOut();
        // Reset the dropdown to "Select an item"
        $('#purchase_product_id').val('');
    });
    
    // Handle add ingredient form submission via AJAX
    $('#rpos-add-ingredient-form').on('submit', function(e) {
        e.preventDefault();
        
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Saving...');
        
        var formData = {
            action: 'rpos_add_ingredient',
            nonce: rposAdmin.nonce,
            ingredient_name: $('#ingredient_name').val(),
            unit: $('#ingredient_unit').val(),
            default_cost_per_unit: $('#ingredient_cost').val()
        };
        
        $.ajax({
            url: rposAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    // Add new option to the dropdown
                    var newOption = $('<option></option>')
                        .attr('value', response.data.product_id)
                        .text(response.data.product_name);
                    
                    // Insert after the "+ Add New Ingredient" option
                    $('#purchase_product_id option[value="__add_new__"]').after(newOption);
                    
                    // Select the newly added ingredient
                    $('#purchase_product_id').val(response.data.product_id);
                    
                    // Close the modal
                    $('#rpos-add-ingredient-modal').fadeOut();
                    
                    // Reload page to show the new ingredient in the list
                    location.reload();
                } else {
                    var errorMsg = response.data.message || 'Failed to add ingredient.';
                    if (confirm(errorMsg + '\n\nWould you like to try again?')) {
                        // Keep modal open for retry
                    } else {
                        $('#rpos-add-ingredient-modal').fadeOut();
                        $('#purchase_product_id').val('');
                    }
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false).text(originalText);
                if (confirm('An error occurred. Please try again.\n\nWould you like to retry?')) {
                    // Keep modal open for retry
                } else {
                    $('#rpos-add-ingredient-modal').fadeOut();
                    $('#purchase_product_id').val('');
                }
            }
        });
    });
});
</script>
