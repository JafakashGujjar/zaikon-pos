<?php
/**
 * Categories Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_category_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_category_nonce'], 'rpos_category_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_products')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => wp_kses_post($_POST['description'] ?? '')
        );
        
        $category_id = RPOS_Categories::create($data);
        
        if ($category_id) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Category created successfully!', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'update' && isset($_POST['category_id'])) {
        $category_id = absint($_POST['category_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => wp_kses_post($_POST['description'] ?? '')
        );
        
        RPOS_Categories::update($category_id, $data);
        echo '<div class="notice notice-success"><p>' . esc_html__('Category updated successfully!', 'restaurant-pos') . '</p></div>';
    } elseif ($action === 'delete' && isset($_POST['category_id'])) {
        $category_id = absint($_POST['category_id']);
        RPOS_Categories::delete($category_id);
        echo '<div class="notice notice-success"><p>' . esc_html__('Category deleted successfully!', 'restaurant-pos') . '</p></div>';
    }
}

$editing_category = null;
if (isset($_GET['edit']) && absint($_GET['edit'])) {
    $editing_category = RPOS_Categories::get(absint($_GET['edit']));
}

$categories = RPOS_Categories::get_all();
?>

<div class="wrap rpos-categories">
    <h1><?php echo esc_html__('Categories Management', 'restaurant-pos'); ?></h1>
    
    <div class="rpos-content-wrapper">
        <div class="rpos-form-section">
            <h2><?php echo $editing_category ? esc_html__('Edit Category', 'restaurant-pos') : esc_html__('Add New Category', 'restaurant-pos'); ?></h2>
            
            <form method="post" class="rpos-form">
                <?php wp_nonce_field('rpos_category_action', 'rpos_category_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo $editing_category ? 'update' : 'create'; ?>">
                <?php if ($editing_category): ?>
                <input type="hidden" name="category_id" value="<?php echo esc_attr($editing_category->id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="name"><?php echo esc_html__('Category Name', 'restaurant-pos'); ?> *</label></th>
                        <td>
                            <input type="text" id="name" name="name" class="regular-text" 
                                   value="<?php echo esc_attr($editing_category->name ?? ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description"><?php echo esc_html__('Description', 'restaurant-pos'); ?></label></th>
                        <td>
                            <textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea($editing_category->description ?? ''); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $editing_category ? esc_html__('Update Category', 'restaurant-pos') : esc_html__('Add Category', 'restaurant-pos'); ?>
                    </button>
                    <?php if ($editing_category): ?>
                    <a href="<?php echo admin_url('admin.php?page=restaurant-pos-categories'); ?>" class="button">
                        <?php echo esc_html__('Cancel', 'restaurant-pos'); ?>
                    </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <div class="rpos-list-section">
            <h2><?php echo esc_html__('Categories List', 'restaurant-pos'); ?></h2>
            
            <?php if (!empty($categories)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Description', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><strong><?php echo esc_html($category->name); ?></strong></td>
                        <td><?php echo esc_html($category->description ?: '-'); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=restaurant-pos-categories&edit=' . $category->id); ?>" class="button button-small">
                                <?php echo esc_html__('Edit', 'restaurant-pos'); ?>
                            </a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure? Products in this category will be uncategorized.', 'restaurant-pos')); ?>');">
                                <?php wp_nonce_field('rpos_category_action', 'rpos_category_nonce'); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category_id" value="<?php echo esc_attr($category->id); ?>">
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
            <p><?php echo esc_html__('No categories found. Add your first category above.', 'restaurant-pos'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
