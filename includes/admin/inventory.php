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
        <h2><?php echo esc_html__('Current Stock Levels', 'restaurant-pos'); ?></h2>
        
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
    
    // Close modal
    $('.rpos-close-modal').on('click', function() {
        $(this).closest('[id$="-modal"]').fadeOut();
    });
});
</script>
