<?php
/**
 * Gas Cylinders Management Page
 * Simplified single-file implementation with inline tabs
 */

if (!defined('ABSPATH')) {
    exit;
}

// Process form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpos_gas_nonce'])) {
    if (!wp_verify_nonce($_POST['rpos_gas_nonce'], 'rpos_gas_action') || !current_user_can('rpos_manage_inventory')) {
        wp_die('Permission denied');
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_type':
            $type_id = RPOS_Gas_Cylinders::create_type(array(
                'name' => sanitize_text_field($_POST['type_name'] ?? ''),
                'description' => sanitize_textarea_field($_POST['type_desc'] ?? '')
            ));
            $message = $type_id ? 'Cylinder type added successfully!' : 'Failed to add cylinder type.';
            $message_type = $type_id ? 'success' : 'error';
            break;
            
        case 'update_mapping':
            $result = RPOS_Gas_Cylinders::set_product_mappings(
                absint($_POST['type_id'] ?? 0),
                isset($_POST['products']) ? array_map('absint', $_POST['products']) : array()
            );
            $message = 'Product mapping updated successfully!';
            $message_type = 'success';
            break;
            
        case 'add_cylinder':
            $cylinder_id = RPOS_Gas_Cylinders::create_cylinder(array(
                'cylinder_type_id' => absint($_POST['cyl_type_id'] ?? 0),
                'purchase_date' => sanitize_text_field($_POST['purchase_date'] ?? ''),
                'cost' => floatval($_POST['cost'] ?? 0),
                'start_date' => sanitize_text_field($_POST['start_date'] ?? date('Y-m-d')),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
            ));
            $message = $cylinder_id ? 'Cylinder added successfully!' : 'Failed to add cylinder. Ensure no active cylinder exists for this type.';
            $message_type = $cylinder_id ? 'success' : 'error';
            break;
            
        case 'finish_cylinder':
            $result = RPOS_Gas_Cylinders::finish_cylinder(
                absint($_POST['cyl_id'] ?? 0),
                sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'))
            );
            $message = 'Cylinder marked as finished!';
            $message_type = 'success';
            break;
    }
}

// Get data
$tab = $_GET['tab'] ?? 'types';
$cylinder_types = RPOS_Gas_Cylinders::get_all_types();
$products = RPOS_Products::get_all();
$cylinders = RPOS_Gas_Cylinders::get_all_cylinders();
$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap">
    <h1><?php esc_html_e('Gas Cylinders Management', 'restaurant-pos'); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?>"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=restaurant-pos-gas-cylinders&tab=types" class="nav-tab <?php echo $tab === 'types' ? 'nav-tab-active' : ''; ?>">Cylinder Types</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=mapping" class="nav-tab <?php echo $tab === 'mapping' ? 'nav-tab-active' : ''; ?>">Product Mapping</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=cylinders" class="nav-tab <?php echo $tab === 'cylinders' ? 'nav-tab-active' : ''; ?>">Cylinder Records</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=report" class="nav-tab <?php echo $tab === 'report' ? 'nav-tab-active' : ''; ?>">Usage Report</a>
    </h2>
    
    <!-- Tab 1: Cylinder Types -->
    <?php if ($tab === 'types'): ?>
        <h2>Add New Cylinder Type</h2>
        <form method="post" style="max-width: 600px; background: white; padding: 20px; border-radius: 8px;">
            <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
            <input type="hidden" name="action" value="add_type">
            <table class="form-table">
                <tr><th><label>Type Name *</label></th><td><input type="text" name="type_name" required class="regular-text"></td></tr>
                <tr><th><label>Description</label></th><td><textarea name="type_desc" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <button type="submit" class="button button-primary">Add Type</button>
        </form>
        
        <h2>Existing Types</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Type Name</th><th>Description</th></tr></thead>
            <tbody>
                <?php foreach ($cylinder_types as $type): ?>
                    <tr><td><strong><?php echo esc_html($type->name); ?></strong></td><td><?php echo esc_html($type->description); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    
    <!-- Tab 2: Product Mapping -->
    <?php elseif ($tab === 'mapping'): ?>
        <?php foreach ($cylinder_types as $type): 
            $mappings = RPOS_Gas_Cylinders::get_product_mappings($type->id);
            $mapped_ids = array_map(function($m) { return $m->product_id; }, $mappings);
        ?>
            <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
                <h2><?php echo esc_html($type->name); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
                    <input type="hidden" name="action" value="update_mapping">
                    <input type="hidden" name="type_id" value="<?php echo esc_attr($type->id); ?>">
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                        <?php foreach ($products as $product): ?>
                            <label style="display: block; margin: 5px 0;">
                                <input type="checkbox" name="products[]" value="<?php echo esc_attr($product->id); ?>" <?php checked(in_array($product->id, $mapped_ids)); ?>>
                                <?php echo esc_html($product->name); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="button button-primary" style="margin-top: 10px;">Update Mapping</button>
                </form>
            </div>
        <?php endforeach; ?>
    
    <!-- Tab 3: Cylinder Records -->
    <?php elseif ($tab === 'cylinders'): ?>
        <h2>Add New Cylinder</h2>
        <form method="post" style="max-width: 600px; background: white; padding: 20px; border-radius: 8px;">
            <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
            <input type="hidden" name="action" value="add_cylinder">
            <table class="form-table">
                <tr><th><label>Cylinder Type *</label></th><td>
                    <select name="cyl_type_id" required class="regular-text">
                        <option value="">-- Select --</option>
                        <?php foreach ($cylinder_types as $type): ?>
                            <option value="<?php echo esc_attr($type->id); ?>"><?php echo esc_html($type->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td></tr>
                <tr><th><label>Purchase Date</label></th><td><input type="date" name="purchase_date" class="regular-text"></td></tr>
                <tr><th><label>Cost</label></th><td><input type="number" name="cost" step="0.01" min="0" value="0" class="regular-text"></td></tr>
                <tr><th><label>Start Date *</label></th><td><input type="date" name="start_date" required value="<?php echo date('Y-m-d'); ?>" class="regular-text"></td></tr>
                <tr><th><label>Notes</label></th><td><textarea name="notes" rows="2" class="large-text"></textarea></td></tr>
            </table>
            <button type="submit" class="button button-primary">Add Cylinder</button>
        </form>
        
        <h2>Cylinder Records</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Type</th><th>Purchase Date</th><th>Cost</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($cylinders as $cyl): ?>
                    <tr>
                        <td><strong><?php echo esc_html($cyl->type_name); ?></strong></td>
                        <td><?php echo $cyl->purchase_date ? esc_html($cyl->purchase_date) : '-'; ?></td>
                        <td><?php echo esc_html($currency . number_format($cyl->cost, 2)); ?></td>
                        <td><?php echo esc_html($cyl->start_date); ?></td>
                        <td><?php echo $cyl->end_date ? esc_html($cyl->end_date) : '-'; ?></td>
                        <td><span style="color: <?php echo $cyl->status === 'active' ? 'green' : 'gray'; ?>;"><?php echo esc_html(ucfirst($cyl->status)); ?></span></td>
                        <td>
                            <?php if ($cyl->status === 'active'): ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
                                    <input type="hidden" name="action" value="finish_cylinder">
                                    <input type="hidden" name="cyl_id" value="<?php echo esc_attr($cyl->id); ?>">
                                    <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                                    <button type="submit" class="button button-small">Finish</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    
    <!-- Tab 4: Usage Report -->
    <?php elseif ($tab === 'report'): ?>
        <h2>Cylinder Usage Report</h2>
        <p>Select a cylinder to view its usage report:</p>
        <?php
        $selected_cyl = isset($_GET['cyl_id']) ? absint($_GET['cyl_id']) : 0;
        if ($selected_cyl) {
            $report = RPOS_Gas_Cylinders::get_cylinder_usage_report($selected_cyl);
            if ($report): ?>
                <div style="background: white; padding: 20px; border-radius: 8px;">
                    <h3>Cylinder: <?php echo esc_html($report['cylinder']->type_name); ?></h3>
                    <p>Period: <?php echo esc_html($report['cylinder']->start_date); ?> to <?php echo $report['cylinder']->end_date ? esc_html($report['cylinder']->end_date) : 'Present'; ?></p>
                    <p><strong>Total Sales: <?php echo esc_html($currency . number_format($report['total_sales'], 2)); ?></strong></p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>Product</th><th>Quantity Sold</th><th>Sales Value</th></tr></thead>
                        <tbody>
                            <?php foreach ($report['products'] as $product): ?>
                                <tr>
                                    <td><?php echo esc_html($product->product_name); ?></td>
                                    <td><?php echo esc_html($product->total_quantity); ?></td>
                                    <td><?php echo esc_html($currency . number_format($product->total_sales, 2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif;
        } ?>
        
        <h3>Select Cylinder</h3>
        <ul>
            <?php foreach ($cylinders as $cyl): ?>
                <li>
                    <a href="?page=restaurant-pos-gas-cylinders&tab=report&cyl_id=<?php echo esc_attr($cyl->id); ?>">
                        <?php echo esc_html($cyl->type_name); ?> - <?php echo esc_html($cyl->start_date); ?> to <?php echo $cyl->end_date ? esc_html($cyl->end_date) : 'Present'; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
