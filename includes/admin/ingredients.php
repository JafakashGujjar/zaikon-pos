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
            'cost_per_unit' => floatval($_POST['cost_per_unit'] ?? 0)
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
            'cost_per_unit' => floatval($_POST['cost_per_unit'] ?? 0)
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
    }
}

// Get current view
$view = $_GET['view'] ?? 'list';
$ingredient_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

// Get ingredient data for edit
$ingredient = null;
if ($view === 'edit' && $ingredient_id) {
    $ingredient = RPOS_Ingredients::get($ingredient_id);
    if (!$ingredient) {
        $view = 'list';
    }
}

// Get all ingredients
$ingredients = RPOS_Ingredients::get_all();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Ingredients', 'restaurant-pos'); ?></h1>
    
    <?php if ($view === 'list'): ?>
        <a href="?page=restaurant-pos-ingredients&view=add" class="page-title-action"><?php esc_html_e('Add New Ingredient', 'restaurant-pos'); ?></a>
        <hr class="wp-header-end">
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ingredient Name', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Current Stock Quantity', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Cost per Unit', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($ingredients)): ?>
                    <?php foreach ($ingredients as $ing): ?>
                        <tr>
                            <td><strong><?php echo esc_html($ing->name); ?></strong></td>
                            <td><?php echo esc_html($ing->unit); ?></td>
                            <td><?php echo esc_html(number_format($ing->current_stock_quantity, 3)); ?></td>
                            <td><?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')) . esc_html(number_format($ing->cost_per_unit, 2)); ?></td>
                            <td>
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
                        <td colspan="5"><?php esc_html_e('No ingredients found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    <?php elseif ($view === 'add' || $view === 'edit'): ?>
        <hr class="wp-header-end">
        
        <form method="post" action="?page=restaurant-pos-ingredients">
            <?php wp_nonce_field('rpos_ingredient_action', 'rpos_ingredient_nonce'); ?>
            <input type="hidden" name="action" value="<?php echo $view === 'edit' ? 'update' : 'create'; ?>">
            <?php if ($view === 'edit'): ?>
                <input type="hidden" name="ingredient_id" value="<?php echo esc_attr($ingredient->id); ?>">
            <?php endif; ?>
            
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
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $view === 'edit' ? esc_html__('Update Ingredient', 'restaurant-pos') : esc_html__('Add Ingredient', 'restaurant-pos'); ?>
                </button>
                <a href="?page=restaurant-pos-ingredients" class="button"><?php esc_html_e('Cancel', 'restaurant-pos'); ?></a>
            </p>
        </form>
    <?php endif; ?>
</div>
