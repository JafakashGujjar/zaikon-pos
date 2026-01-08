<?php
/**
 * Products Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_product_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_product_nonce'], 'rpos_product_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_products')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'sku' => sanitize_text_field($_POST['sku'] ?? ''),
            'category_id' => absint($_POST['category_id'] ?? 0),
            'selling_price' => floatval($_POST['selling_price'] ?? 0),
            'image_url' => esc_url_raw($_POST['image_url'] ?? ''),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        $product_id = RPOS_Products::create($data);
        
        if ($product_id) {
            // Save recipe if provided
            if (isset($_POST['recipe_ingredients']) && is_array($_POST['recipe_ingredients'])) {
                $recipe_data = array();
                foreach ($_POST['recipe_ingredients'] as $index => $ingredient_id) {
                    if (!empty($ingredient_id) && !empty($_POST['recipe_quantities'][$index])) {
                        $recipe_data[] = array(
                            'inventory_item_id' => absint($ingredient_id),
                            'quantity_required' => floatval($_POST['recipe_quantities'][$index]),
                            'unit' => sanitize_text_field($_POST['recipe_units'][$index] ?? '')
                        );
                    }
                }
                if (!empty($recipe_data)) {
                    RPOS_Recipes::save_recipe($product_id, $recipe_data);
                }
            }
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Product created successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to create product.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'update' && isset($_POST['product_id'])) {
        $product_id = absint($_POST['product_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'sku' => sanitize_text_field($_POST['sku'] ?? ''),
            'category_id' => absint($_POST['category_id'] ?? 0),
            'selling_price' => floatval($_POST['selling_price'] ?? 0),
            'image_url' => esc_url_raw($_POST['image_url'] ?? ''),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        $result = RPOS_Products::update($product_id, $data);
        
        if ($result !== false) {
            // Save recipe
            if (isset($_POST['recipe_ingredients']) && is_array($_POST['recipe_ingredients'])) {
                $recipe_data = array();
                foreach ($_POST['recipe_ingredients'] as $index => $ingredient_id) {
                    if (!empty($ingredient_id) && !empty($_POST['recipe_quantities'][$index])) {
                        $recipe_data[] = array(
                            'inventory_item_id' => absint($ingredient_id),
                            'quantity_required' => floatval($_POST['recipe_quantities'][$index]),
                            'unit' => sanitize_text_field($_POST['recipe_units'][$index] ?? '')
                        );
                    }
                }
                RPOS_Recipes::save_recipe($product_id, $recipe_data);
            }
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Product updated successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to update product.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'delete' && isset($_POST['product_id'])) {
        $product_id = absint($_POST['product_id']);
        $result = RPOS_Products::delete($product_id);
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Product deleted successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete product.', 'restaurant-pos') . '</p></div>';
        }
    }
}

// Get edit product if editing
$editing_product = null;
$existing_recipe = array();
if (isset($_GET['edit']) && absint($_GET['edit'])) {
    $editing_product = RPOS_Products::get(absint($_GET['edit']));
    $existing_recipe = RPOS_Recipes::get_by_product($editing_product->id);
}

// Get all categories
$categories = RPOS_Categories::get_all();

// Get all inventory items for recipe dropdown
$inventory_items = RPOS_Inventory::get_all();

// Get all products
$products = RPOS_Products::get_all();
?>

<div class="wrap rpos-products">
    <h1><?php echo esc_html__('Products Management', 'restaurant-pos'); ?></h1>
    
    <div class="rpos-content-wrapper">
        <div class="rpos-form-section">
            <h2><?php echo $editing_product ? esc_html__('Edit Product', 'restaurant-pos') : esc_html__('Add New Product', 'restaurant-pos'); ?></h2>
            
            <form method="post" class="rpos-form">
                <?php wp_nonce_field('rpos_product_action', 'rpos_product_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $editing_product ? 'update' : 'create'; ?>">
                <?php if ($editing_product): ?>
                <input type="hidden" name="product_id" value="<?php echo esc_attr($editing_product->id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="name"><?php echo esc_html__('Product Name', 'restaurant-pos'); ?> *</label></th>
                        <td>
                            <input type="text" id="name" name="name" class="regular-text" 
                                   value="<?php echo esc_attr($editing_product->name ?? ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sku"><?php echo esc_html__('SKU', 'restaurant-pos'); ?></label></th>
                        <td>
                            <input type="text" id="sku" name="sku" class="regular-text" 
                                   value="<?php echo esc_attr($editing_product->sku ?? ''); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="category_id"><?php echo esc_html__('Category', 'restaurant-pos'); ?></label></th>
                        <td>
                            <select id="category_id" name="category_id">
                                <option value="0"><?php echo esc_html__('No Category', 'restaurant-pos'); ?></option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>" 
                                    <?php selected($editing_product->category_id ?? 0, $category->id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="selling_price"><?php echo esc_html__('Selling Price', 'restaurant-pos'); ?> *</label></th>
                        <td>
                            <input type="number" id="selling_price" name="selling_price" step="0.01" min="0" 
                                   value="<?php echo esc_attr($editing_product->selling_price ?? '0.00'); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="image_url"><?php echo esc_html__('Image URL', 'restaurant-pos'); ?></label></th>
                        <td>
                            <input type="url" id="image_url" name="image_url" class="regular-text" 
                                   value="<?php echo esc_attr($editing_product->image_url ?? ''); ?>">
                            <p class="description"><?php echo esc_html__('Enter the full URL of the product image', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description"><?php echo esc_html__('Description', 'restaurant-pos'); ?></label></th>
                        <td>
                            <textarea id="description" name="description" rows="4" class="large-text"><?php echo esc_textarea($editing_product->description ?? ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="is_active"><?php echo esc_html__('Active', 'restaurant-pos'); ?></label></th>
                        <td>
                            <input type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php checked($editing_product->is_active ?? 1, 1); ?>>
                            <label for="is_active"><?php echo esc_html__('Product is active and available for sale', 'restaurant-pos'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <h3><?php echo esc_html__('Ingredients / Recipe & Costing', 'restaurant-pos'); ?></h3>
                <p class="description"><?php echo esc_html__('Define ingredients required to make this product. When sold, inventory will be automatically deducted based on the recipe.', 'restaurant-pos'); ?></p>
                
                <table class="wp-list-table widefat fixed striped" id="rpos-recipe-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;"><?php echo esc_html__('Ingredient (Inventory Item)', 'restaurant-pos'); ?></th>
                            <th style="width: 15%;"><?php echo esc_html__('Quantity Used', 'restaurant-pos'); ?></th>
                            <th style="width: 12%;"><?php echo esc_html__('Unit', 'restaurant-pos'); ?></th>
                            <th style="width: 15%;"><?php echo esc_html__('Cost per Unit', 'restaurant-pos'); ?></th>
                            <th style="width: 15%;"><?php echo esc_html__('Ingredient Cost', 'restaurant-pos'); ?></th>
                            <th style="width: 8%;"><?php echo esc_html__('Action', 'restaurant-pos'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="rpos-recipe-rows">
                        <?php if (!empty($existing_recipe)): ?>
                            <?php foreach ($existing_recipe as $recipe_item): 
                                $inv_item = RPOS_Inventory::get_by_id($recipe_item->inventory_item_id);
                                $unit = $inv_item ? $inv_item->unit : '';
                                $cost_per_unit = $inv_item ? floatval($inv_item->cost_price) : 0;
                                $ingredient_cost = floatval($recipe_item->quantity_required) * $cost_per_unit;
                            ?>
                            <tr class="rpos-recipe-row">
                                <td>
                                    <select name="recipe_ingredients[]" class="ingredient-select regular-text" required>
                                        <option value=""><?php echo esc_html__('Select ingredient', 'restaurant-pos'); ?></option>
                                        <?php foreach ($inventory_items as $inv_item): ?>
                                        <option value="<?php echo esc_attr($inv_item->id); ?>" 
                                            data-unit="<?php echo esc_attr($inv_item->unit ?: 'pcs'); ?>"
                                            data-cost="<?php echo esc_attr($inv_item->cost_price ?: 0); ?>"
                                            <?php selected($recipe_item->inventory_item_id, $inv_item->id); ?>>
                                            <?php echo esc_html($inv_item->product_name); ?>
                                            <?php echo $inv_item->sku ? ' (' . esc_html($inv_item->sku) . ')' : ''; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="recipe_quantities[]" class="ingredient-qty" step="0.001" min="0.001" 
                                           value="<?php echo esc_attr($recipe_item->quantity_required); ?>" required>
                                </td>
                                <td>
                                    <span class="unit-display"><?php echo esc_html($unit); ?></span>
                                    <input type="hidden" name="recipe_units[]" class="unit-input" value="<?php echo esc_attr($unit); ?>">
                                </td>
                                <td>
                                    <span class="cost-per-unit" data-value="<?php echo esc_attr($cost_per_unit); ?>">
                                        <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?><?php echo number_format($cost_per_unit, 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ingredient-cost">
                                        <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?><?php echo number_format($ingredient_cost, 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button rpos-remove-recipe-row" title="<?php echo esc_attr__('Remove', 'restaurant-pos'); ?>">×</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="rpos-recipe-row">
                            <td>
                                <select name="recipe_ingredients[]" class="ingredient-select regular-text">
                                    <option value=""><?php echo esc_html__('Select ingredient', 'restaurant-pos'); ?></option>
                                    <?php foreach ($inventory_items as $inv_item): ?>
                                    <option value="<?php echo esc_attr($inv_item->id); ?>"
                                        data-unit="<?php echo esc_attr($inv_item->unit ?: 'pcs'); ?>"
                                        data-cost="<?php echo esc_attr($inv_item->cost_price ?: 0); ?>">
                                        <?php echo esc_html($inv_item->product_name); ?>
                                        <?php echo $inv_item->sku ? ' (' . esc_html($inv_item->sku) . ')' : ''; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="recipe_quantities[]" class="ingredient-qty" step="0.001" min="0.001" placeholder="0.000">
                            </td>
                            <td>
                                <span class="unit-display">-</span>
                                <input type="hidden" name="recipe_units[]" class="unit-input" value="">
                            </td>
                            <td>
                                <span class="cost-per-unit" data-value="0">
                                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>0.00
                                </span>
                            </td>
                            <td>
                                <span class="ingredient-cost">
                                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>0.00
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button rpos-remove-recipe-row" title="<?php echo esc_attr__('Remove', 'restaurant-pos'); ?>">×</button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" class="button" id="rpos-add-recipe-row">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        <?php echo esc_html__('Add Ingredient', 'restaurant-pos'); ?>
                    </button>
                </p>
                
                <!-- Cost Summary Box -->
                <div class="rpos-cost-summary-box">
                    <h4><?php echo esc_html__('Cost Summary', 'restaurant-pos'); ?></h4>
                    <table class="rpos-cost-summary-table">
                        <tr>
                            <td><?php echo esc_html__('Total Ingredient Cost:', 'restaurant-pos'); ?></td>
                            <td class="amount">
                                <span id="total-ingredient-cost">
                                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>0.00
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html__('Selling Price:', 'restaurant-pos'); ?></td>
                            <td class="amount">
                                <span id="display-selling-price">
                                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?><?php echo number_format($editing_product->selling_price ?? 0, 2); ?>
                                </span>
                            </td>
                        </tr>
                        <tr class="separator">
                            <td colspan="2"><hr></td>
                        </tr>
                        <tr class="highlight">
                            <td><?php echo esc_html__('Gross Profit:', 'restaurant-pos'); ?></td>
                            <td class="amount">
                                <span id="gross-profit">
                                    <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>0.00
                                </span>
                            </td>
                        </tr>
                        <tr class="highlight">
                            <td><?php echo esc_html__('Gross Margin:', 'restaurant-pos'); ?></td>
                            <td class="amount">
                                <span id="gross-margin">0.0%</span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $editing_product ? esc_html__('Update Product', 'restaurant-pos') : esc_html__('Add Product', 'restaurant-pos'); ?>
                    </button>
                    <?php if ($editing_product): ?>
                    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-products'); ?>" class="button">
                        <?php echo esc_html__('Cancel', 'restaurant-pos'); ?>
                    </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <div class="rpos-list-section">
            <h2><?php echo esc_html__('Products List', 'restaurant-pos'); ?></h2>
            
            <?php if (!empty($products)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('SKU', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Category', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Price', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): 
                        $category = $product->category_id ? RPOS_Categories::get($product->category_id) : null;
                    ?>
                    <tr>
                        <td><?php echo esc_html($product->name); ?></td>
                        <td><?php echo esc_html($product->sku ?: '-'); ?></td>
                        <td><?php echo esc_html($category ? $category->name : '-'); ?></td>
                        <td>
                            <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>
                            <?php echo number_format($product->selling_price, 2); ?>
                        </td>
                        <td>
                            <?php if ($product->is_active): ?>
                            <span class="rpos-badge rpos-badge-success"><?php echo esc_html__('Active', 'restaurant-pos'); ?></span>
                            <?php else: ?>
                            <span class="rpos-badge rpos-badge-inactive"><?php echo esc_html__('Inactive', 'restaurant-pos'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-products&edit=' . $product->id); ?>" class="button button-small">
                                <?php echo esc_html__('Edit', 'restaurant-pos'); ?>
                            </a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete this product?', 'restaurant-pos')); ?>');">
                                <?php wp_nonce_field('rpos_product_action', 'rpos_product_nonce'); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo esc_attr($product->id); ?>">
                                <button type="submit" class="button button-small button-link-delete">
                                    <?php echo esc_html__('Delete', 'restaurant-pos'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?php echo esc_html__('No products found. Add your first product above.', 'restaurant-pos'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recipe row template -->
<script type="text/template" id="rpos-recipe-row-template">
    <tr class="rpos-recipe-row">
        <td>
            <select name="recipe_ingredients[]" class="ingredient-select regular-text">
                <option value=""><?php echo esc_html__('Select ingredient', 'restaurant-pos'); ?></option>
                <?php foreach ($inventory_items as $inv_item): ?>
                <option value="<?php echo esc_attr($inv_item->id); ?>"
                    data-unit="<?php echo esc_attr($inv_item->unit ?: 'pcs'); ?>"
                    data-cost="<?php echo esc_attr($inv_item->cost_price ?: 0); ?>">
                    <?php echo esc_html($inv_item->product_name); ?>
                    <?php echo $inv_item->sku ? ' (' . esc_html($inv_item->sku) . ')' : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" name="recipe_quantities[]" class="ingredient-qty" step="0.001" min="0.001" placeholder="0.000">
        </td>
        <td>
            <span class="unit-display">-</span>
            <input type="hidden" name="recipe_units[]" class="unit-input" value="">
        </td>
        <td>
            <span class="cost-per-unit" data-value="0">
                <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>0.00
            </span>
        </td>
        <td>
            <span class="ingredient-cost">
                <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?>0.00
            </span>
        </td>
        <td>
            <button type="button" class="button rpos-remove-recipe-row" title="<?php echo esc_attr__('Remove', 'restaurant-pos'); ?>">×</button>
        </td>
    </tr>
</script>

<script>
jQuery(document).ready(function($) {
    var currencySymbol = '<?php echo esc_js(RPOS_Settings::get('currency_symbol', '$')); ?>';
    
    // Add recipe row
    $('#rpos-add-recipe-row').on('click', function() {
        var template = $('#rpos-recipe-row-template').html();
        $('#rpos-recipe-rows').append(template);
    });
    
    // Remove recipe row
    $(document).on('click', '.rpos-remove-recipe-row', function() {
        if ($('.rpos-recipe-row').length > 1) {
            $(this).closest('.rpos-recipe-row').remove();
            recalculateTotals();
        } else {
            // If it's the last row, just clear it
            var $row = $(this).closest('.rpos-recipe-row');
            $row.find('select').val('');
            $row.find('input').val('');
            $row.find('.unit-display').text('-');
            $row.find('.cost-per-unit').text(currencySymbol + '0.00').data('value', 0);
            $row.find('.ingredient-cost').text(currencySymbol + '0.00');
            recalculateTotals();
        }
    });
    
    // When ingredient dropdown changes, fetch unit and cost
    $(document).on('change', '.ingredient-select', function() {
        var $row = $(this).closest('tr');
        var $option = $(this).find('option:selected');
        var unit = $option.data('unit') || 'pcs';
        var cost = parseFloat($option.data('cost')) || 0;
        
        // Update unit display
        $row.find('.unit-display').text(unit);
        $row.find('.unit-input').val(unit);
        
        // Update cost per unit display
        $row.find('.cost-per-unit').text(currencySymbol + cost.toFixed(2)).data('value', cost);
        
        // Recalculate totals
        recalculateTotals();
    });
    
    // Recalculate when quantity changes (with debouncing)
    var recalculateTimer;
    $(document).on('input', '.ingredient-qty', function() {
        clearTimeout(recalculateTimer);
        recalculateTimer = setTimeout(function() {
            recalculateTotals();
        }, 300); // 300ms debounce
    });
    
    // Recalculate when selling price changes
    $('#selling_price').on('input', function() {
        var sellingPrice = parseFloat($(this).val()) || 0;
        $('#display-selling-price').text(currencySymbol + sellingPrice.toFixed(2));
        clearTimeout(recalculateTimer);
        recalculateTimer = setTimeout(function() {
            recalculateTotals();
        }, 300); // 300ms debounce
    });
    
    function recalculateTotals() {
        var totalCost = 0;
        
        $('.rpos-recipe-row').each(function() {
            var $row = $(this);
            var $qtyInput = $row.find('.ingredient-qty');
            var $costPerUnit = $row.find('.cost-per-unit');
            var $ingredientCost = $row.find('.ingredient-cost');
            
            var qty = parseFloat($qtyInput.val()) || 0;
            var costPerUnit = parseFloat($costPerUnit.data('value')) || 0;
            var lineCost = qty * costPerUnit;
            
            $ingredientCost.text(currencySymbol + lineCost.toFixed(2));
            totalCost += lineCost;
        });
        
        var sellingPrice = parseFloat($('#selling_price').val()) || 0;
        var grossProfit = sellingPrice - totalCost;
        var grossMargin = sellingPrice > 0 ? (grossProfit / sellingPrice * 100) : 0;
        
        $('#total-ingredient-cost').text(currencySymbol + totalCost.toFixed(2));
        $('#gross-profit').text(currencySymbol + grossProfit.toFixed(2));
        $('#gross-margin').text(grossMargin.toFixed(1) + '%');
    }
    
    // Initial calculation on page load
    recalculateTotals();
});
</script>
