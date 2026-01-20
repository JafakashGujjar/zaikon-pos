<?php
/**
 * Categories Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue WordPress media scripts and color picker
wp_enqueue_media();
wp_enqueue_style('wp-color-picker');
wp_enqueue_script('wp-color-picker');

// Enqueue custom category admin script
wp_enqueue_script(
    'rpos-category-admin',
    RPOS_PLUGIN_URL . 'assets/js/category-admin.js',
    array('jquery', 'wp-color-picker'),
    RPOS_VERSION,
    true
);

// Localize script for translations
wp_localize_script('rpos-category-admin', 'rposCategoryAdmin', array(
    'uploadTitle' => __('Choose Category Image', 'restaurant-pos'),
    'useImageText' => __('Use this image', 'restaurant-pos')
));

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
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'image_url' => esc_url_raw($_POST['image_url'] ?? ''),
            'bg_color' => sanitize_hex_color($_POST['bg_color'] ?? '')
        );
        
        $category_id = RPOS_Categories::create($data);
        
        if ($category_id) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Category created successfully!', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'update' && isset($_POST['category_id'])) {
        $category_id = absint($_POST['category_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'image_url' => esc_url_raw($_POST['image_url'] ?? ''),
            'bg_color' => sanitize_hex_color($_POST['bg_color'] ?? '')
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
                    <tr>
                        <th><label for="image_url"><?php echo esc_html__('Category Image', 'restaurant-pos'); ?></label></th>
                        <td>
                            <div class="rpos-category-image-upload">
                                <input type="hidden" id="image_url" name="image_url" value="<?php echo esc_attr($editing_category->image_url ?? ''); ?>">
                                <div class="rpos-image-preview" id="rpos-category-image-preview">
                                    <?php if (!empty($editing_category->image_url)): ?>
                                        <img src="<?php echo esc_url($editing_category->image_url); ?>" alt="Category Image">
                                    <?php else: ?>
                                        <span class="dashicons dashicons-format-image"></span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button" id="rpos-upload-category-image">
                                    <?php echo esc_html__('Choose Image', 'restaurant-pos'); ?>
                                </button>
                                <button type="button" class="button" id="rpos-remove-category-image" style="<?php echo empty($editing_category->image_url) ? 'display:none;' : ''; ?>">
                                    <?php echo esc_html__('Remove Image', 'restaurant-pos'); ?>
                                </button>
                                <p class="description"><?php echo esc_html__('Upload an image for this category. Recommended size: 100x100px', 'restaurant-pos'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bg_color"><?php echo esc_html__('Background Color', 'restaurant-pos'); ?></label></th>
                        <td>
                            <input type="text" id="bg_color" name="bg_color" class="rpos-color-picker" 
                                   value="<?php echo esc_attr($editing_category->bg_color ?? '#4A5568'); ?>">
                            <p class="description"><?php echo esc_html__('Choose a background color for the circular icon', 'restaurant-pos'); ?></p>
                            <div class="rpos-color-presets">
                                <span class="rpos-color-preset" data-color="#C53030" style="background-color: #C53030;" title="Red"></span>
                                <span class="rpos-color-preset" data-color="#DD6B20" style="background-color: #DD6B20;" title="Orange"></span>
                                <span class="rpos-color-preset" data-color="#D53F8C" style="background-color: #D53F8C;" title="Pink"></span>
                                <span class="rpos-color-preset" data-color="#805AD5" style="background-color: #805AD5;" title="Purple"></span>
                                <span class="rpos-color-preset" data-color="#38A169" style="background-color: #38A169;" title="Green"></span>
                                <span class="rpos-color-preset" data-color="#3182CE" style="background-color: #3182CE;" title="Blue"></span>
                                <span class="rpos-color-preset" data-color="#718096" style="background-color: #718096;" title="Gray"></span>
                            </div>
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
