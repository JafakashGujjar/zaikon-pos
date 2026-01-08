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
if (isset($_GET['edit']) && absint($_GET['edit'])) {
    $editing_product = RPOS_Products::get(absint($_GET['edit']));
}

// Get all categories
$categories = RPOS_Categories::get_all();

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
