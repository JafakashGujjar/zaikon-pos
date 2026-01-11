<?php
/**
 * Suppliers Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_supplier_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_supplier_nonce'], 'rpos_supplier_action')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('rpos_manage_inventory')) {
        wp_die('You do not have permission to perform this action');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        // Validate and sanitize rating
        $rating = null;
        if (isset($_POST['rating']) && $_POST['rating'] !== '') {
            $rating_val = absint($_POST['rating']);
            if ($rating_val >= 1 && $rating_val <= 5) {
                $rating = $rating_val;
            }
        }
        
        $data = array(
            'supplier_name' => sanitize_text_field($_POST['supplier_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'rating' => $rating,
            'contact_person' => sanitize_text_field($_POST['contact_person'] ?? ''),
            'gst_tax_id' => sanitize_text_field($_POST['gst_tax_id'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        $supplier_id = RPOS_Suppliers::create($data);
        
        if ($supplier_id) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Supplier created successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to create supplier.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'update' && isset($_POST['supplier_id'])) {
        $supplier_id = absint($_POST['supplier_id']);
        
        // Validate and sanitize rating
        $rating = null;
        if (isset($_POST['rating']) && $_POST['rating'] !== '') {
            $rating_val = absint($_POST['rating']);
            if ($rating_val >= 1 && $rating_val <= 5) {
                $rating = $rating_val;
            }
        }
        $data = array(
            'supplier_name' => sanitize_text_field($_POST['supplier_name'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'rating' => $rating,
            'contact_person' => sanitize_text_field($_POST['contact_person'] ?? ''),
            'gst_tax_id' => sanitize_text_field($_POST['gst_tax_id'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        $result = RPOS_Suppliers::update($supplier_id, $data);
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Supplier updated successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to update supplier.', 'restaurant-pos') . '</p></div>';
        }
    } elseif ($action === 'delete' && isset($_POST['supplier_id'])) {
        $supplier_id = absint($_POST['supplier_id']);
        $result = RPOS_Suppliers::delete($supplier_id);
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Supplier deleted successfully!', 'restaurant-pos') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete supplier. It may be used in batches.', 'restaurant-pos') . '</p></div>';
        }
    }
}

// Get current view
$view = $_GET['view'] ?? 'list';
$supplier_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

// Get supplier data for edit
$supplier = null;
if ($view === 'edit' && $supplier_id) {
    $supplier = RPOS_Suppliers::get($supplier_id);
    if (!$supplier) {
        $view = 'list';
    }
}

// Get all suppliers
$suppliers = RPOS_Suppliers::get_all();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Suppliers', 'restaurant-pos'); ?></h1>
    
    <?php if ($view === 'list'): ?>
        <a href="?page=restaurant-pos-suppliers&view=add" class="page-title-action">
            <?php esc_html_e('Add New Supplier', 'restaurant-pos'); ?>
        </a>
        <hr class="wp-header-end">
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Supplier Name', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Contact Person', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Phone', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Rating', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Status', 'restaurant-pos'); ?></th>
                    <th><?php esc_html_e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr>
                            <td><strong><?php echo esc_html($sup->supplier_name); ?></strong></td>
                            <td><?php echo esc_html($sup->contact_person ?: '-'); ?></td>
                            <td><?php echo esc_html($sup->phone ?: '-'); ?></td>
                            <td>
                                <?php if ($sup->rating): ?>
                                    <?php echo str_repeat('⭐', intval($sup->rating)); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sup->is_active): ?>
                                    <span style="color: green;">●</span> <?php esc_html_e('Active', 'restaurant-pos'); ?>
                                <?php else: ?>
                                    <span style="color: red;">●</span> <?php esc_html_e('Inactive', 'restaurant-pos'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=restaurant-pos-suppliers&view=edit&id=<?php echo esc_attr($sup->id); ?>">
                                    <?php esc_html_e('Edit', 'restaurant-pos'); ?>
                                </a>
                                |
                                <a href="?page=restaurant-pos-suppliers&view=performance&id=<?php echo esc_attr($sup->id); ?>">
                                    <?php esc_html_e('Performance', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No suppliers found. Add your first supplier to get started.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    <?php elseif ($view === 'add' || $view === 'edit'): ?>
        <hr class="wp-header-end">
        
        <form method="post" action="?page=restaurant-pos-suppliers">
            <?php wp_nonce_field('rpos_supplier_action', 'rpos_supplier_nonce'); ?>
            <input type="hidden" name="action" value="<?php echo $view === 'edit' ? 'update' : 'create'; ?>">
            <?php if ($view === 'edit'): ?>
                <input type="hidden" name="supplier_id" value="<?php echo esc_attr($supplier->id); ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="supplier_name"><?php esc_html_e('Supplier Name', 'restaurant-pos'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="supplier_name" id="supplier_name" 
                               value="<?php echo $supplier ? esc_attr($supplier->supplier_name) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="contact_person"><?php esc_html_e('Contact Person', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="contact_person" id="contact_person" 
                               value="<?php echo $supplier ? esc_attr($supplier->contact_person) : ''; ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="phone"><?php esc_html_e('Phone', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="phone" id="phone" 
                               value="<?php echo $supplier ? esc_attr($supplier->phone) : ''; ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="address"><?php esc_html_e('Address', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <textarea name="address" id="address" rows="3" class="large-text"><?php echo $supplier ? esc_textarea($supplier->address) : ''; ?></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gst_tax_id"><?php esc_html_e('GST/Tax ID', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="gst_tax_id" id="gst_tax_id" 
                               value="<?php echo $supplier ? esc_attr($supplier->gst_tax_id) : ''; ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rating"><?php esc_html_e('Rating (1-5)', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <select name="rating" id="rating">
                            <option value=""><?php esc_html_e('-- Not Rated --', 'restaurant-pos'); ?></option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($supplier && $supplier->rating == $i); ?>>
                                    <?php echo str_repeat('⭐', $i); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="is_active"><?php esc_html_e('Active', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               <?php checked(!$supplier || $supplier->is_active); ?>>
                        <label for="is_active"><?php esc_html_e('Supplier is active', 'restaurant-pos'); ?></label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notes"><?php esc_html_e('Notes', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <textarea name="notes" id="notes" rows="4" class="large-text"><?php echo $supplier ? esc_textarea($supplier->notes) : ''; ?></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" 
                       value="<?php echo $view === 'edit' ? esc_attr__('Update Supplier', 'restaurant-pos') : esc_attr__('Create Supplier', 'restaurant-pos'); ?>">
                <a href="?page=restaurant-pos-suppliers" class="button">
                    <?php esc_html_e('Cancel', 'restaurant-pos'); ?>
                </a>
            </p>
        </form>
        
        <?php if ($view === 'edit'): ?>
            <hr>
            <h2><?php esc_html_e('Delete Supplier', 'restaurant-pos'); ?></h2>
            <form method="post" action="?page=restaurant-pos-suppliers" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this supplier?', 'restaurant-pos'); ?>');">
                <?php wp_nonce_field('rpos_supplier_action', 'rpos_supplier_nonce'); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="supplier_id" value="<?php echo esc_attr($supplier->id); ?>">
                <input type="submit" name="submit" class="button button-secondary" 
                       value="<?php esc_attr_e('Delete Supplier', 'restaurant-pos'); ?>">
            </form>
        <?php endif; ?>
        
    <?php elseif ($view === 'performance' && $supplier_id): ?>
        <?php
        $supplier = RPOS_Suppliers::get($supplier_id);
        $metrics = RPOS_Suppliers::get_performance_metrics($supplier_id, 90);
        ?>
        <a href="?page=restaurant-pos-suppliers" class="page-title-action">
            <?php esc_html_e('← Back to Suppliers', 'restaurant-pos'); ?>
        </a>
        <hr class="wp-header-end">
        
        <h2><?php echo esc_html($supplier->supplier_name); ?> - <?php esc_html_e('Performance Metrics', 'restaurant-pos'); ?></h2>
        <p><?php esc_html_e('Last 90 days', 'restaurant-pos'); ?></p>
        
        <div class="rpos-metrics-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div class="rpos-metric-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e('Total Batches', 'restaurant-pos'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo esc_html($metrics['total_batches']); ?></p>
            </div>
            
            <div class="rpos-metric-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e('Quality Score', 'restaurant-pos'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 10px 0; color: <?php echo $metrics['quality_score'] >= 80 ? 'green' : ($metrics['quality_score'] >= 60 ? 'orange' : 'red'); ?>">
                    <?php echo esc_html($metrics['quality_score']); ?>%
                </p>
            </div>
            
            <div class="rpos-metric-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e('Waste Incidents', 'restaurant-pos'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo esc_html($metrics['waste_count']); ?></p>
            </div>
            
            <div class="rpos-metric-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e('Avg Cost/Unit', 'restaurant-pos'); ?></h3>
                <p style="font-size: 32px; font-weight: bold; margin: 10px 0;">$<?php echo esc_html(number_format($metrics['avg_cost'], 2)); ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>
