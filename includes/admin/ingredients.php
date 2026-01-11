<?php
/**
 * Ingredients Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_ingredient_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_ingredient_nonce'], 'rpos_ingredient_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_inventory')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'unit' => sanitize_text_field($_POST['unit'] ?? 'pcs'),
            'current_stock_quantity' => floatval($_POST['current_stock_quantity'] ?? 0),
            'cost_per_unit' => floatval($_POST['cost_per_unit'] ?? 0),
            'purchasing_date' => sanitize_text_field($_POST['purchasing_date'] ?? ''),
            'expiry_date' => sanitize_text_field($_POST['expiry_date'] ?? ''),
            'supplier_name' => sanitize_text_field($_POST['supplier_name'] ?? ''),
            'supplier_rating' => isset($_POST['supplier_rating']) && $_POST['supplier_rating'] !== '' ? absint($_POST['supplier_rating']) : null,
            'supplier_phone' => sanitize_text_field($_POST['supplier_phone'] ?? ''),
            'supplier_location' => sanitize_textarea_field($_POST['supplier_location'] ?? ''),
            'reorder_level' => floatval($_POST['reorder_level'] ?? 0)
        );
        
        $ingredient_id = RPOS_Ingredients::create($data);
        
        if ($ingredient_id) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Ingredient created successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to create ingredient.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'update' && isset($_POST['ingredient_id'])) {
        $ingredient_id = absint($_POST['ingredient_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'unit' => sanitize_text_field($_POST['unit'] ?? 'pcs'),
            'current_stock_quantity' => floatval($_POST['current_stock_quantity'] ?? 0),
            'cost_per_unit' => floatval($_POST['cost_per_unit'] ?? 0),
            'purchasing_date' => sanitize_text_field($_POST['purchasing_date'] ?? ''),
            'expiry_date' => sanitize_text_field($_POST['expiry_date'] ?? ''),
            'supplier_name' => sanitize_text_field($_POST['supplier_name'] ?? ''),
            'supplier_rating' => isset($_POST['supplier_rating']) && $_POST['supplier_rating'] !== '' ? absint($_POST['supplier_rating']) : null,
            'supplier_phone' => sanitize_text_field($_POST['supplier_phone'] ?? ''),
            'supplier_location' => sanitize_textarea_field($_POST['supplier_location'] ?? ''),
            'reorder_level' => floatval($_POST['reorder_level'] ?? 0)
        );
        
        $result = RPOS_Ingredients::update($ingredient_id, $data);
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Ingredient updated successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to update ingredient.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'delete' && isset($_POST['ingredient_id'])) {
        $ingredient_id = absint($_POST['ingredient_id']);
        $result = RPOS_Ingredients::delete($ingredient_id);
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Ingredient deleted successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete ingredient. It may be used in product recipes.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'purchase' && isset($_POST['ingredient_id'])) {
        $ingredient_id = absint($_POST['ingredient_id']);
        
        // Prepare purchase data for batch creation
        $purchase_data = array(
            'quantity' => floatval($_POST['quantity'] ?? 0),
            'cost_per_unit' => floatval($_POST['cost_per_unit'] ?? 0),
            'supplier_id' => !empty($_POST['supplier_id']) ? absint($_POST['supplier_id']) : null,
            'purchase_date' => !empty($_POST['purchase_date']) ? sanitize_text_field($_POST['purchase_date']) : date('Y-m-d'),
            'manufacturing_date' => !empty($_POST['manufacturing_date']) ? sanitize_text_field($_POST['manufacturing_date']) : null,
            'expiry_date' => !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null,
            'invoice_url' => !empty($_POST['invoice_url']) ? esc_url_raw($_POST['invoice_url']) : '',
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        // Use the new purchase method which creates batch
        $batch_id = RPOS_Ingredients::purchase($ingredient_id, $purchase_data);
        
        if ($batch_id) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Purchase recorded successfully! New batch created.', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to record purchase.', 'restaurant-pos') . '</p></div>';
        }
    }
}

// Get current view
$view = $_GET['view'] ?? 'list';
$ingredient_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

// Get ingredient data for edit or purchase
$ingredient = null;
if (($view === 'edit' || $view === 'purchase') && $ingredient_id) {
    $ingredient = RPOS_Ingredients::get($ingredient_id);
    if (!$ingredient) {
        $view = 'list';
    }
}

// Get all ingredients
$ingredients = RPOS_Ingredients::get_all();

// Calculate summary statistics
$total_ingredients = count($ingredients);
$low_stock_count = 0;
$expired_count = 0;
$expiring_soon_count = 0;
$total_value = 0;

foreach ($ingredients as $ing) {
    $total_value += floatval($ing->current_stock_quantity) * floatval($ing->cost_per_unit);
    
    if (floatval($ing->current_stock_quantity) <= floatval($ing->reorder_level ?? 0) && floatval($ing->reorder_level ?? 0) > 0) {
        $low_stock_count++;
    }
    
    if (!empty($ing->expiry_date)) {
        $days_until_expiry = (strtotime($ing->expiry_date) - time()) / (60 * 60 * 24);
        if ($days_until_expiry < 0) {
            $expired_count++;
        } elseif ($days_until_expiry <= 7) {
            $expiring_soon_count++;
        }
    }
}
?>

<style>
.rpos-ingredient-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}
.rpos-status-expired {
    background-color: #ffebee !important;
    border-left: 4px solid #f44336 !important;
}
.rpos-status-low-stock {
    background-color: #fff3e0 !important;
    border-left: 4px solid #ff9800 !important;
}
.rpos-status-healthy {
    background-color: #e8f5e9 !important;
    border-left: 4px solid #4caf50 !important;
}
.rpos-icon {
    margin-right: 5px;
}
.rpos-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}
.rpos-summary-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-left: 4px solid #2271b1;
}
.rpos-summary-card.danger {
    border-left-color: #f44336;
}
.rpos-summary-card.warning {
    border-left-color: #ff9800;
}
.rpos-summary-card.success {
    border-left-color: #4caf50;
}
.rpos-summary-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}
.rpos-summary-card .value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}
.rpos-form-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.rpos-form-section h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Ingredients', 'restaurant-pos'); ?></h1>
    
    <?php if ($view === 'list'): ?>
        <a href="?page=restaurant-pos-ingredients&view=add" class="page-title-action"><?php esc_html_e('Add New Ingredient', 'restaurant-pos'); ?></a>
        <hr class="wp-header-end">
        
        <!-- Summary Cards -->
        <div class="rpos-summary-cards">
            <div class="rpos-summary-card">
                <h3><?php esc_html_e('Total Ingredients', 'restaurant-pos'); ?></h3>
                <div class="value"><?php echo esc_html($total_ingredients); ?></div>
            </div>
            <div class="rpos-summary-card success">
                <h3><?php esc_html_e('Total Inventory Value', 'restaurant-pos'); ?></h3>
                <div class="value"><?php echo esc_html(RPOS_Inventory_Settings::format_currency($total_value)); ?></div>
            </div>
            <div class="rpos-summary-card warning">
                <h3><?php esc_html_e('Low Stock Items', 'restaurant-pos'); ?></h3>
                <div class="value"><?php echo esc_html($low_stock_count); ?></div>
            </div>
            <div class="rpos-summary-card danger">
                <h3><?php esc_html_e('Expired Items', 'restaurant-pos'); ?></h3>
                <div class="value"><?php echo esc_html($expired_count); ?></div>
            </div>
            <div class="rpos-summary-card warning">
                <h3><?php esc_html_e('Expiring Soon', 'restaurant-pos'); ?></h3>
                <div class="value"><?php echo esc_html($expiring_soon_count); ?></div>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ingredient Name', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Current Stock', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Reorder Level', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Cost per Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Supplier', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Phone', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Location', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Rating', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Expiry Date', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($ingredients)): ?>
                    <?php foreach ($ingredients as $ing): ?>
                        <?php
                        // Determine row status class
                        $row_class = '';
                        $status_text = '';
                        
                        // Check if expired
                        if (!empty($ing->expiry_date)) {
                            $days_until_expiry = (strtotime($ing->expiry_date) - time()) / (60 * 60 * 24);
                            if ($days_until_expiry < 0) {
                                $row_class = 'rpos-status-expired';
                                $status_text = '⚠️ Expired';
                            } elseif ($days_until_expiry <= 7) {
                                $row_class = 'rpos-status-low-stock';
                                $status_text = '⏰ Expiring Soon';
                            }
                        }
                        
                        // Check if low stock (overrides expiry status if no expiry)
                        if (floatval($ing->current_stock_quantity) == 0) {
                            $row_class = 'rpos-status-expired';
                            $status_text = '🚫 Out of Stock';
                        } elseif (floatval($ing->current_stock_quantity) <= floatval($ing->reorder_level ?? 0) && floatval($ing->reorder_level ?? 0) > 0) {
                            if (empty($row_class)) {
                                $row_class = 'rpos-status-low-stock';
                                $status_text = '📦 Low Stock';
                            }
                        }
                        
                        if (empty($row_class)) {
                            $row_class = 'rpos-status-healthy';
                        }
                        ?>
                        <tr class="<?php echo esc_attr($row_class); ?>">
                            <td>
                                <strong><?php echo esc_html($ing->name); ?></strong>
                                <?php if (!empty($status_text)): ?>
                                    <br><small style="color: #666;"><?php echo esc_html($status_text); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($ing->unit); ?></td>
                            <td>
                                <span class="rpos-icon">📦</span>
                                <?php echo esc_html(RPOS_Inventory_Settings::format_quantity($ing->current_stock_quantity)); ?>
                            </td>
                            <td><?php echo esc_html(RPOS_Inventory_Settings::format_quantity($ing->reorder_level ?? 0)); ?></td>
                            <td>
                                <span class="rpos-icon">💰</span>
                                <?php echo esc_html(RPOS_Inventory_Settings::format_currency($ing->cost_per_unit)); ?>
                            </td>
                            <td><?php echo !empty($ing->supplier_name) ? esc_html($ing->supplier_name) : '-'; ?></td>
                            <td>
                                <?php if (!empty($ing->supplier_phone)): ?>
                                    <span class="rpos-icon">📞</span><?php echo esc_html($ing->supplier_phone); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($ing->supplier_location)): ?>
                                    <span class="rpos-icon">📍</span><?php echo esc_html($ing->supplier_location); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($ing->supplier_rating)) {
                                    echo '<span class="rpos-icon">⭐</span>';
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $ing->supplier_rating ? '⭐' : '☆';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($ing->expiry_date)): ?>
                                    <span class="rpos-icon">📅</span><?php echo esc_html($ing->expiry_date); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=restaurant-pos-ingredients&view=purchase&id=<?php echo esc_attr($ing->id); ?>" class="button button-small button-primary">
                                    <?php esc_html_e('Purchase', 'restaurant-pos'); ?>
                                </a>
                                <a href="?page=restaurant-pos-ingredients&view=edit&id=<?php echo esc_attr($ing->id); ?>" class="button button-small"><?php esc_html_e('Edit', 'restaurant-pos'); ?></a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this ingredient?', 'restaurant-pos'); ?>');">
                                    <?php wp_nonce_field('rpos_ingredient_action', 'rpos_ingredient_nonce'); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="ingredient_id" value="<?php echo esc_attr($ing->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('Delete', 'restaurant-pos'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11"><?php esc_html_e('No ingredients found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    <?php elseif ($view === 'add' || $view === 'edit'): ?>
        <hr class="wp-header-end">
        
        <?php if ($view === 'add'): ?>
            <!-- Existing Ingredient Selector -->
            <div class="rpos-form-section" id="ingredient-selector" style="max-width: 600px;">
                <h3>🔍 <?php esc_html_e('Select Existing Ingredient or Create New', 'restaurant-pos'); ?></h3>
                <p><?php esc_html_e('To avoid duplicates, first check if the ingredient already exists:', 'restaurant-pos'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="existing_ingredient"><?php esc_html_e('Search Existing Ingredients', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <select id="existing_ingredient" class="regular-text" style="width: 100%;">
                                <option value=""><?php esc_html_e('-- Type to search or create new --', 'restaurant-pos'); ?></option>
                                <?php foreach ($ingredients as $ing): ?>
                                    <option value="<?php echo esc_attr($ing->id); ?>" data-url="?page=restaurant-pos-ingredients&view=edit&id=<?php echo esc_attr($ing->id); ?>">
                                        <?php echo esc_html($ing->name); ?> (<?php echo esc_html(RPOS_Inventory_Settings::format_quantity($ing->current_stock_quantity, $ing->unit)); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select to edit existing ingredient, or leave empty to create new.', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" id="show-new-form" class="button button-primary">
                        ➕ <?php esc_html_e('Create New Ingredient', 'restaurant-pos'); ?>
                    </button>
                </p>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Handle existing ingredient selection
                $('#existing_ingredient').on('change', function() {
                    var url = $(this).find(':selected').data('url');
                    if (url) {
                        window.location.href = url;
                    }
                });
                
                // Show new ingredient form
                $('#show-new-form').on('click', function() {
                    $('#ingredient-selector').hide();
                    $('#new-ingredient-form').show();
                });
            });
            </script>
        <?php endif; ?>
        
        <form method="post" action="?page=restaurant-pos-ingredients" id="new-ingredient-form" <?php if ($view === 'add'): ?>style="display: none;"<?php endif; ?>>
            <?php wp_nonce_field('rpos_ingredient_action', 'rpos_ingredient_nonce'); ?>
            <input type="hidden" name="action" value="<?php echo $view === 'edit' ? 'update' : 'create'; ?>">
            <?php if ($view === 'edit'): ?>
                <input type="hidden" name="ingredient_id" value="<?php echo esc_attr($ingredient->id); ?>">
            <?php endif; ?>
            
            <!-- Basic Information Section -->
            <div class="rpos-form-section">
                <h3>📝 <?php esc_html_e('Basic Information', 'restaurant-pos'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="name"><?php esc_html_e('Ingredient Name', 'restaurant-pos'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" 
                                   value="<?php echo $ingredient ? esc_attr($ingredient->name) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="unit"><?php esc_html_e('Unit', 'restaurant-pos'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <select name="unit" id="unit" required>
                                <option value="pcs" <?php selected($ingredient ? $ingredient->unit : 'pcs', 'pcs'); ?>>pcs (pieces)</option>
                                <option value="kg" <?php selected($ingredient ? $ingredient->unit : '', 'kg'); ?>>kg (kilograms)</option>
                                <option value="g" <?php selected($ingredient ? $ingredient->unit : '', 'g'); ?>>g (grams)</option>
                                <option value="l" <?php selected($ingredient ? $ingredient->unit : '', 'l'); ?>>l (liters)</option>
                                <option value="ml" <?php selected($ingredient ? $ingredient->unit : '', 'ml'); ?>>ml (milliliters)</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="current_stock_quantity"><?php esc_html_e('Current Stock Quantity', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="current_stock_quantity" id="current_stock_quantity" 
                                   step="0.001" min="0" class="regular-text"
                                   value="<?php echo $ingredient ? esc_attr($ingredient->current_stock_quantity) : '0'; ?>">
                            <p class="description"><?php esc_html_e('Initial stock quantity (can be adjusted later).', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cost_per_unit"><?php esc_html_e('Cost per Unit', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="cost_per_unit" id="cost_per_unit" 
                                   step="0.01" min="0" class="regular-text"
                                   value="<?php echo $ingredient ? esc_attr($ingredient->cost_per_unit) : '0'; ?>">
                            <p class="description"><?php esc_html_e('Average cost per unit (optional).', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="reorder_level"><?php esc_html_e('Reorder Level', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="reorder_level" id="reorder_level" 
                                   step="0.001" min="0" class="regular-text"
                                   value="<?php echo $ingredient ? esc_attr($ingredient->reorder_level ?? 0) : '0'; ?>">
                            <p class="description"><?php esc_html_e('Alert when stock falls below this level.', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Dates Section -->
            <div class="rpos-form-section">
                <h3>📅 <?php esc_html_e('Dates', 'restaurant-pos'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="purchasing_date"><?php esc_html_e('Purchasing Date', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="purchasing_date" id="purchasing_date" class="regular-text"
                               value="<?php echo $ingredient && !empty($ingredient->purchasing_date) ? esc_attr($ingredient->purchasing_date) : ''; ?>">
                            <p class="description"><?php esc_html_e('Date when this ingredient was purchased (optional).', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="expiry_date"><?php esc_html_e('Expiry Date', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="date" name="expiry_date" id="expiry_date" class="regular-text"
                                   value="<?php echo $ingredient && !empty($ingredient->expiry_date) ? esc_attr($ingredient->expiry_date) : ''; ?>">
                            <p class="description"><?php esc_html_e('Date when this ingredient expires (optional).', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Supplier Details Section -->
            <div class="rpos-form-section">
                <h3>🏪 <?php esc_html_e('Supplier Details', 'restaurant-pos'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="supplier_name"><?php esc_html_e('Supplier Name', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="supplier_name" id="supplier_name" class="regular-text"
                                   value="<?php echo $ingredient && !empty($ingredient->supplier_name) ? esc_attr($ingredient->supplier_name) : ''; ?>">
                            <p class="description"><?php esc_html_e('Name of the supplier for this ingredient (optional).', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="supplier_phone"><?php esc_html_e('Supplier Phone', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="supplier_phone" id="supplier_phone" class="regular-text"
                                   value="<?php echo $ingredient && !empty($ingredient->supplier_phone) ? esc_attr($ingredient->supplier_phone) : ''; ?>">
                            <p class="description"><?php esc_html_e('Phone number of the supplier (optional).', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="supplier_location"><?php esc_html_e('Supplier Location', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <textarea name="supplier_location" id="supplier_location" class="regular-text" rows="3"><?php echo $ingredient && !empty($ingredient->supplier_location) ? esc_textarea($ingredient->supplier_location) : ''; ?></textarea>
                            <p class="description"><?php esc_html_e('Address or location of the supplier (optional).', 'restaurant-pos'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="supplier_rating"><?php esc_html_e('Supplier Rating', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                        <select name="supplier_rating" id="supplier_rating">
                            <option value=""><?php esc_html_e('-- No Rating --', 'restaurant-pos'); ?></option>
                            <option value="1" <?php selected($ingredient && !empty($ingredient->supplier_rating) ? $ingredient->supplier_rating : '', '1'); ?>>⭐ (1 Star)</option>
                            <option value="2" <?php selected($ingredient && !empty($ingredient->supplier_rating) ? $ingredient->supplier_rating : '', '2'); ?>>⭐⭐ (2 Stars)</option>
                            <option value="3" <?php selected($ingredient && !empty($ingredient->supplier_rating) ? $ingredient->supplier_rating : '', '3'); ?>>⭐⭐⭐ (3 Stars)</option>
                            <option value="4" <?php selected($ingredient && !empty($ingredient->supplier_rating) ? $ingredient->supplier_rating : '', '4'); ?>>⭐⭐⭐⭐ (4 Stars)</option>
                            <option value="5" <?php selected($ingredient && !empty($ingredient->supplier_rating) ? $ingredient->supplier_rating : '', '5'); ?>>⭐⭐⭐⭐⭐ (5 Stars)</option>
                        </select>
                        <p class="description"><?php esc_html_e('Rate the supplier quality (optional).', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo $view === 'edit' ? esc_html__('Update Ingredient', 'restaurant-pos') : esc_html__('Add Ingredient', 'restaurant-pos'); ?>
            </button>
            <?php if ($view === 'add'): ?>
                <button type="button" id="back-to-selector" class="button"><?php esc_html_e('← Back to Selector', 'restaurant-pos'); ?></button>
            <?php endif; ?>
            <a href="?page=restaurant-pos-ingredients" class="button"><?php esc_html_e('Cancel', 'restaurant-pos'); ?></a>
        </p>
    </form>
    
    <?php elseif ($view === 'purchase' && $ingredient): ?>
        <a href="?page=restaurant-pos-ingredients" class="button">← <?php esc_html_e('Back to List', 'restaurant-pos'); ?></a>
        <hr class="wp-header-end">
        
        <h2><?php esc_html_e('Purchase / Restock Ingredient', 'restaurant-pos'); ?></h2>
        <div style="background: #f0f6fc; padding: 15px; border-left: 4px solid #2271b1; border-radius: 4px; margin: 20px 0; max-width: 800px;">
            <p><strong><?php esc_html_e('Ingredient:', 'restaurant-pos'); ?></strong> <?php echo esc_html($ingredient->name); ?></p>
            <p><strong><?php esc_html_e('Current Stock:', 'restaurant-pos'); ?></strong> <?php echo esc_html(RPOS_Inventory_Settings::format_quantity($ingredient->current_stock_quantity, $ingredient->unit)); ?></p>
        </div>
        
        <form method="post" action="" style="max-width: 800px;">
            <?php wp_nonce_field('rpos_ingredient_action', 'rpos_ingredient_nonce'); ?>
            <input type="hidden" name="action" value="purchase">
            <input type="hidden" name="ingredient_id" value="<?php echo esc_attr($ingredient->id); ?>">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="quantity"><?php esc_html_e('Quantity Purchased', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" name="quantity" id="quantity" step="0.001" min="0.001" class="regular-text" required>
                        <span><?php echo esc_html($ingredient->unit); ?></span>
                        <p class="description"><?php esc_html_e('Amount of ingredient being purchased.', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="cost_per_unit"><?php esc_html_e('Cost per Unit', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" name="cost_per_unit" id="cost_per_unit" step="0.01" min="0" 
                               value="<?php echo esc_attr($ingredient->cost_per_unit); ?>" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Cost per unit for this batch.', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="supplier_id"><?php esc_html_e('Supplier', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <select name="supplier_id" id="supplier_id" class="regular-text">
                            <option value=""><?php esc_html_e('-- Select Supplier --', 'restaurant-pos'); ?></option>
                            <?php
                            $suppliers = RPOS_Suppliers::get_all(array('is_active' => 1));
                            foreach ($suppliers as $sup):
                            ?>
                                <option value="<?php echo esc_attr($sup->id); ?>" <?php selected($ingredient->default_supplier_id, $sup->id); ?>>
                                    <?php echo esc_html($sup->supplier_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the supplier for this batch. ', 'restaurant-pos'); ?>
                            <a href="?page=restaurant-pos-suppliers&view=add" target="_blank"><?php esc_html_e('Add New Supplier', 'restaurant-pos'); ?></a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="purchase_date"><?php esc_html_e('Purchase Date', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="purchase_date" id="purchase_date" value="<?php echo date('Y-m-d'); ?>" class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="manufacturing_date"><?php esc_html_e('Manufacturing Date', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="manufacturing_date" id="manufacturing_date" class="regular-text">
                        <p class="description"><?php esc_html_e('Optional: Date when the ingredient was manufactured.', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="expiry_date"><?php esc_html_e('Expiry Date', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="expiry_date" id="expiry_date" class="regular-text">
                        <p class="description"><?php esc_html_e('Important for FEFO (First Expire First Out) consumption strategy.', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="invoice_url"><?php esc_html_e('Invoice URL', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="invoice_url" id="invoice_url" class="regular-text" placeholder="https://">
                        <p class="description"><?php esc_html_e('Optional: Link to uploaded invoice (PDF/image).', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notes"><?php esc_html_e('Notes', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <textarea name="notes" id="notes" rows="4" class="large-text"></textarea>
                        <p class="description"><?php esc_html_e('Any additional notes about this purchase/batch.', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Record Purchase', 'restaurant-pos'); ?>">
                <a href="?page=restaurant-pos-ingredients" class="button"><?php esc_html_e('Cancel', 'restaurant-pos'); ?></a>
            </p>
        </form>
        
        <!-- Display recent batches for this ingredient -->
        <?php
        $recent_batches = RPOS_Batches::get_all(array(
            'ingredient_id' => $ingredient->id,
            'orderby' => 'purchase_date',
            'order' => 'DESC',
            'limit' => 5
        ));
        
        if (!empty($recent_batches)):
        ?>
            <hr style="margin: 30px 0;">
            <h3><?php esc_html_e('Recent Batches', 'restaurant-pos'); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="max-width: 800px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Batch #', 'restaurant-pos'); ?></th>
                        <th><?php esc_html_e('Purchase Date', 'restaurant-pos'); ?></th>
                        <th><?php esc_html_e('Expiry Date', 'restaurant-pos'); ?></th>
                        <th><?php esc_html_e('Remaining', 'restaurant-pos'); ?></th>
                        <th><?php esc_html_e('Status', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_batches as $batch): ?>
                        <tr>
                            <td><?php echo esc_html($batch->batch_number); ?></td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($batch->purchase_date))); ?></td>
                            <td><?php echo $batch->expiry_date ? esc_html(date('M d, Y', strtotime($batch->expiry_date))) : '-'; ?></td>
                            <td><?php echo esc_html(RPOS_Inventory_Settings::format_quantity($batch->quantity_remaining, $ingredient->unit)); ?></td>
                            <td><?php echo esc_html(ucfirst($batch->status)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
    <?php endif; ?>
    
    <?php if ($view === 'add'): ?>
    <script>
    jQuery(document).ready(function($) {
        $('#back-to-selector').on('click', function() {
            $('#new-ingredient-form').hide();
            $('#ingredient-selector').show();
        });
    });
    </script>
    <?php endif; ?>
</div>
