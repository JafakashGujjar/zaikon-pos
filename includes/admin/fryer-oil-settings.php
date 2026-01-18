<?php
/**
 * Fryer Oil Settings Page
 * Configure fryers and product-oil mappings
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
if (isset($_POST['rpos_fryer_settings_nonce']) && check_admin_referer('rpos_fryer_settings_action', 'rpos_fryer_settings_nonce')) {
    $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
    
    switch ($action) {
        case 'add_fryer':
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'rpos_fryers',
                array(
                    'name' => sanitize_text_field($_POST['fryer_name'] ?? ''),
                    'description' => sanitize_textarea_field($_POST['fryer_description'] ?? ''),
                    'is_active' => 1
                ),
                array('%s', '%s', '%d')
            );
            
            if ($result) {
                $message = 'Fryer added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to add fryer.';
                $message_type = 'error';
            }
            break;
        
        case 'delete_fryer':
            global $wpdb;
            $fryer_id = absint($_POST['fryer_id'] ?? 0);
            
            $result = $wpdb->delete(
                $wpdb->prefix . 'rpos_fryers',
                array('id' => $fryer_id),
                array('%d')
            );
            
            if ($result) {
                $message = 'Fryer deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete fryer.';
                $message_type = 'error';
            }
            break;
        
        case 'add_product_mapping':
            $product_id = absint($_POST['product_id'] ?? 0);
            $oil_units = floatval($_POST['oil_units'] ?? 1);
            $fryer_id = !empty($_POST['fryer_id']) ? absint($_POST['fryer_id']) : null;
            
            $result = RPOS_Fryer_Products::add_product($product_id, $oil_units, $fryer_id);
            
            if ($result) {
                $message = 'Product mapping added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to add product mapping. Product may already be mapped.';
                $message_type = 'error';
            }
            break;
        
        case 'delete_product_mapping':
            $mapping_id = absint($_POST['mapping_id'] ?? 0);
            
            $result = RPOS_Fryer_Products::remove_product($mapping_id);
            
            if ($result) {
                $message = 'Product mapping deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to delete product mapping.';
                $message_type = 'error';
            }
            break;
    }
}

// Get data
global $wpdb;
$fryers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rpos_fryers ORDER BY name ASC");
$fryer_products = RPOS_Fryer_Products::get_fryer_products();
$all_products = RPOS_Products::get_all();

$tab = $_GET['tab'] ?? 'fryers';

?>

<div class="wrap">
    <h1><?php _e('Fryer Oil Settings', 'restaurant-pos'); ?></h1>
    <hr class="wp-header-end">
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="nav-tab-wrapper" style="margin: 20px 0;">
        <a href="?page=restaurant-pos-fryer-oil-settings&tab=fryers" 
           class="nav-tab <?php echo $tab === 'fryers' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Fryers', 'restaurant-pos'); ?>
        </a>
        <a href="?page=restaurant-pos-fryer-oil-settings&tab=products" 
           class="nav-tab <?php echo $tab === 'products' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Product Mappings', 'restaurant-pos'); ?>
        </a>
    </div>
    
    <?php if ($tab === 'fryers'): ?>
        <!-- Fryers Management -->
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
            <h2><?php _e('Add Fryer', 'restaurant-pos'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('rpos_fryer_settings_action', 'rpos_fryer_settings_nonce'); ?>
                <input type="hidden" name="action" value="add_fryer">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fryer_name"><?php _e('Fryer Name', 'restaurant-pos'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" name="fryer_name" id="fryer_name" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fryer_description"><?php _e('Description', 'restaurant-pos'); ?></label>
                        </th>
                        <td>
                            <textarea name="fryer_description" id="fryer_description" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Add Fryer', 'restaurant-pos'); ?></button>
                </p>
            </form>
        </div>
        
        <h2><?php _e('Existing Fryers', 'restaurant-pos'); ?></h2>
        <?php if (empty($fryers)): ?>
            <p><?php _e('No fryers configured. Add a fryer to get started.', 'restaurant-pos'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'restaurant-pos'); ?></th>
                        <th><?php _e('Description', 'restaurant-pos'); ?></th>
                        <th><?php _e('Status', 'restaurant-pos'); ?></th>
                        <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fryers as $fryer): ?>
                        <tr>
                            <td><strong><?php echo esc_html($fryer->name); ?></strong></td>
                            <td><?php echo esc_html($fryer->description); ?></td>
                            <td>
                                <?php if ($fryer->is_active): ?>
                                    <span style="color: #46b450;">●</span> <?php _e('Active', 'restaurant-pos'); ?>
                                <?php else: ?>
                                    <span style="color: #999;">●</span> <?php _e('Inactive', 'restaurant-pos'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline;" 
                                      onsubmit="return confirm('<?php _e('Are you sure you want to delete this fryer?', 'restaurant-pos'); ?>');">
                                    <?php wp_nonce_field('rpos_fryer_settings_action', 'rpos_fryer_settings_nonce'); ?>
                                    <input type="hidden" name="action" value="delete_fryer">
                                    <input type="hidden" name="fryer_id" value="<?php echo esc_attr($fryer->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete">
                                        <?php _e('Delete', 'restaurant-pos'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Product Mappings -->
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
            <h2><?php _e('Add Product Mapping', 'restaurant-pos'); ?></h2>
            <p><?php _e('Configure which products use fryer oil and how much they consume per unit.', 'restaurant-pos'); ?></p>
            
            <form method="post">
                <?php wp_nonce_field('rpos_fryer_settings_action', 'rpos_fryer_settings_nonce'); ?>
                <input type="hidden" name="action" value="add_product_mapping">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="product_id"><?php _e('Product', 'restaurant-pos'); ?> *</label>
                        </th>
                        <td>
                            <select name="product_id" id="product_id" class="regular-text" required>
                                <option value=""><?php _e('Select Product', 'restaurant-pos'); ?></option>
                                <?php foreach ($all_products as $product): ?>
                                    <option value="<?php echo esc_attr($product->id); ?>">
                                        <?php echo esc_html($product->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="oil_units"><?php _e('Oil Units Per Item', 'restaurant-pos'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" name="oil_units" id="oil_units" step="0.1" 
                                   class="small-text" value="1" required>
                            <p class="description">
                                <?php _e('Example: 1.0 for regular items, 1.5 for larger items, 0.5 for small items', 'restaurant-pos'); ?>
                            </p>
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
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Add Mapping', 'restaurant-pos'); ?></button>
                </p>
            </form>
        </div>
        
        <h2><?php _e('Existing Product Mappings', 'restaurant-pos'); ?></h2>
        <?php if (empty($fryer_products)): ?>
            <p><?php _e('No products mapped yet. Add product mappings to start tracking oil usage.', 'restaurant-pos'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'restaurant-pos'); ?></th>
                        <th><?php _e('Oil Units Per Item', 'restaurant-pos'); ?></th>
                        <th><?php _e('Fryer', 'restaurant-pos'); ?></th>
                        <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fryer_products as $mapping): ?>
                        <tr>
                            <td><strong><?php echo esc_html($mapping->product_name); ?></strong></td>
                            <td><?php echo number_format($mapping->oil_units, 2); ?> units</td>
                            <td><?php echo esc_html($mapping->fryer_name ?: 'Default'); ?></td>
                            <td>
                                <form method="post" style="display: inline;" 
                                      onsubmit="return confirm('<?php _e('Are you sure you want to remove this mapping?', 'restaurant-pos'); ?>');">
                                    <?php wp_nonce_field('rpos_fryer_settings_action', 'rpos_fryer_settings_nonce'); ?>
                                    <input type="hidden" name="action" value="delete_product_mapping">
                                    <input type="hidden" name="mapping_id" value="<?php echo esc_attr($mapping->id); ?>">
                                    <button type="submit" class="button button-small button-link-delete">
                                        <?php _e('Remove', 'restaurant-pos'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
