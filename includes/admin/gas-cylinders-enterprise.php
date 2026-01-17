<?php
/**
 * Enterprise Gas Cylinders Management Page
 * Complete dashboard with lifecycle tracking, analytics, and forecasting
 */

if (!defined('ABSPATH')) {
    exit;
}

// Process form submissions
$message = '';
$message_type = '';

if (isset($_POST['rpos_gas_nonce']) && check_admin_referer('rpos_gas_action', 'rpos_gas_nonce')) {
    if (!current_user_can('rpos_manage_inventory')) {
        wp_die('Permission denied');
    }
    
    $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
    
    switch ($action) {
        case 'add_type':
            $type_id = RPOS_Gas_Cylinders::create_type(array(
                'name' => sanitize_text_field($_POST['type_name'] ?? ''),
                'description' => sanitize_textarea_field($_POST['type_desc'] ?? '')
            ));
            $message = $type_id ? 'Cylinder type added successfully!' : 'Failed to add cylinder type.';
            $message_type = $type_id ? 'success' : 'error';
            break;
            
        case 'update_type':
            $result = RPOS_Gas_Cylinders::update_type(
                absint($_POST['type_id'] ?? 0),
                array(
                    'name' => sanitize_text_field($_POST['type_name'] ?? ''),
                    'description' => sanitize_textarea_field($_POST['type_desc'] ?? '')
                )
            );
            $message = $result !== false ? 'Cylinder type updated successfully!' : 'Failed to update cylinder type.';
            $message_type = $result !== false ? 'success' : 'error';
            break;
            
        case 'delete_type':
            $result = RPOS_Gas_Cylinders::delete_type(absint($_POST['type_id'] ?? 0));
            $message = $result ? 'Cylinder type deleted successfully!' : 'Cannot delete cylinder type. It may be in use by existing cylinders.';
            $message_type = $result ? 'success' : 'error';
            break;
        
        case 'add_zone':
            $zone_id = RPOS_Gas_Cylinders::create_zone(array(
                'name' => sanitize_text_field($_POST['zone_name'] ?? ''),
                'description' => sanitize_textarea_field($_POST['zone_desc'] ?? '')
            ));
            $message = $zone_id ? 'Zone added successfully!' : 'Failed to add zone.';
            $message_type = $zone_id ? 'success' : 'error';
            break;
            
        case 'add_cylinder':
            $cylinder_id = RPOS_Gas_Cylinders::create_cylinder(array(
                'cylinder_type_id' => absint($_POST['cylinder_type_id'] ?? 0),
                'zone_id' => !empty($_POST['zone_id']) ? absint($_POST['zone_id']) : null,
                'purchase_date' => !empty($_POST['purchase_date']) ? sanitize_text_field($_POST['purchase_date']) : null,
                'cost' => isset($_POST['cost']) ? floatval($_POST['cost']) : 0,
                'start_date' => sanitize_text_field($_POST['start_date'] ?? current_time('Y-m-d')),
                'vendor' => !empty($_POST['vendor']) ? sanitize_text_field($_POST['vendor']) : null,
                'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : ''
            ));
            $message = $cylinder_id ? 'Cylinder added successfully!' : 'Failed to add cylinder. Ensure no active cylinder exists for this type.';
            $message_type = $cylinder_id ? 'success' : 'error';
            break;
            
        case 'process_refill':
            $refill_id = RPOS_Gas_Cylinders::process_refill(
                absint($_POST['cylinder_id'] ?? 0),
                array(
                    'refill_date' => sanitize_text_field($_POST['refill_date'] ?? date('Y-m-d')),
                    'vendor' => sanitize_text_field($_POST['vendor'] ?? ''),
                    'cost' => floatval($_POST['cost'] ?? 0),
                    'quantity' => floatval($_POST['quantity'] ?? 1),
                    'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
                )
            );
            $message = $refill_id ? 'Cylinder refilled successfully!' : 'Failed to process refill.';
            $message_type = $refill_id ? 'success' : 'error';
            break;
            
        case 'update_mapping':
            $result = RPOS_Gas_Cylinders::set_product_mappings(
                absint($_POST['type_id'] ?? 0),
                isset($_POST['products']) ? array_map('absint', $_POST['products']) : array()
            );
            $message = 'Product mapping updated successfully!';
            $message_type = 'success';
            break;
    }
}

// Get data
$tab = $_GET['tab'] ?? 'dashboard';
$currency = RPOS_Settings::get('currency_symbol', '$');
$zones = RPOS_Gas_Cylinders::get_all_zones();
$cylinders = RPOS_Gas_Cylinders::get_all_cylinders();
$cylinder_types = RPOS_Gas_Cylinders::get_all_types();
$products = RPOS_Products::get_all();
$analytics = RPOS_Gas_Cylinders::get_dashboard_analytics();
?>

<style>
.rpos-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.rpos-kpi-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #2271b1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.rpos-kpi-card .kpi-icon {
    font-size: 32px;
    margin-bottom: 10px;
}
.rpos-kpi-card .kpi-value {
    font-size: 32px;
    font-weight: bold;
    color: #2271b1;
    margin: 10px 0;
}
.rpos-kpi-card .kpi-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}
.rpos-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}
.rpos-status-active {
    background: #d4edda;
    color: #155724;
}
.rpos-status-completed {
    background: #cce5ff;
    color: #004085;
}
.rpos-status-low {
    background: #fff3cd;
    color: #856404;
}
.rpos-chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

/* Enhanced Product Mapping Styles */
.rpos-product-item:hover {
    background: #f0f8ff !important;
    border-color: #2271b1 !important;
    transform: translateX(3px);
}
.rpos-product-item input[type="checkbox"]:checked + div {
    background: transparent;
}
.rpos-product-item:has(input[type="checkbox"]:checked) {
    background: #e8f4f8 !important;
    border-color: #2271b1 !important;
}
.rpos-product-search {
    padding: 8px 12px !important;
    border: 2px solid #ddd !important;
    border-radius: 4px !important;
    transition: border-color 0.3s ease !important;
}
.rpos-product-search:focus {
    border-color: #2271b1 !important;
    outline: none !important;
    box-shadow: 0 0 0 1px #2271b1 !important;
}
.rpos-product-item.hidden {
    display: none !important;
}
.rpos-selection-counter {
    background: #f0f8ff;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #2271b1;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Function to update selection counter
    function updateCounter(cylinderType) {
        var container = $('.rpos-product-list[data-cylinder-type="' + cylinderType + '"]');
        var counter = $('.rpos-selection-counter[data-cylinder-type="' + cylinderType + '"]');
        
        var total = container.find('.rpos-product-checkbox').not('.hidden').length;
        var selected = container.find('.rpos-product-checkbox:checked').not('.hidden').length;
        
        counter.find('.selected-count').text(selected);
        counter.find('.total-count').text(total);
    }
    
    // Search functionality
    $('.rpos-product-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        var cylinderType = $(this).data('cylinder-type');
        var container = $('.rpos-product-list[data-cylinder-type="' + cylinderType + '"]');
        
        container.find('.rpos-product-item').each(function() {
            var productName = $(this).data('product-name');
            if (productName.indexOf(searchTerm) !== -1) {
                $(this).removeClass('hidden').show();
            } else {
                $(this).addClass('hidden').hide();
            }
        });
        
        // Hide/show category headers if all products in category are hidden
        container.find('.rpos-product-category').each(function() {
            var visibleItems = $(this).find('.rpos-product-item:not(.hidden)').length;
            if (visibleItems === 0) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
        
        updateCounter(cylinderType);
    });
    
    // Select All functionality
    $('.rpos-select-all').on('click', function() {
        var cylinderType = $(this).data('cylinder-type');
        var container = $('.rpos-product-list[data-cylinder-type="' + cylinderType + '"]');
        container.find('.rpos-product-checkbox:not(.hidden)').prop('checked', true);
        updateCounter(cylinderType);
    });
    
    // Deselect All functionality
    $('.rpos-deselect-all').on('click', function() {
        var cylinderType = $(this).data('cylinder-type');
        var container = $('.rpos-product-list[data-cylinder-type="' + cylinderType + '"]');
        container.find('.rpos-product-checkbox:not(.hidden)').prop('checked', false);
        updateCounter(cylinderType);
    });
    
    // Update counter on checkbox change
    $('.rpos-product-checkbox').on('change', function() {
        var cylinderType = $(this).data('cylinder-type');
        updateCounter(cylinderType);
    });
    
    // Initialize counters
    $('.rpos-product-list').each(function() {
        var cylinderType = $(this).data('cylinder-type');
        updateCounter(cylinderType);
    });
});
</script>

<div class="wrap">
    <h1><?php esc_html_e('Enterprise Cylinder Management', 'restaurant-pos'); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?>"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=restaurant-pos-gas-cylinders&tab=dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">📊 Dashboard</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=types" class="nav-tab <?php echo $tab === 'types' ? 'nav-tab-active' : ''; ?>">⚙️ Manage Types</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=zones" class="nav-tab <?php echo $tab === 'zones' ? 'nav-tab-active' : ''; ?>">🏭 Zones</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=mapping" class="nav-tab <?php echo $tab === 'mapping' ? 'nav-tab-active' : ''; ?>">📦 Product Mapping</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=cylinders" class="nav-tab <?php echo $tab === 'cylinders' ? 'nav-tab-active' : ''; ?>">⛽ Cylinders</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=lifecycle" class="nav-tab <?php echo $tab === 'lifecycle' ? 'nav-tab-active' : ''; ?>">🔄 Lifecycle</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=consumption" class="nav-tab <?php echo $tab === 'consumption' ? 'nav-tab-active' : ''; ?>">📈 Consumption</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=refill" class="nav-tab <?php echo $tab === 'refill' ? 'nav-tab-active' : ''; ?>">⛽ Refill</a>
        <a href="?page=restaurant-pos-gas-cylinders&tab=analytics" class="nav-tab <?php echo $tab === 'analytics' ? 'nav-tab-active' : ''; ?>">📊 Analytics</a>
    </h2>
    
    <!-- Tab 1: Dashboard -->
    <?php if ($tab === 'dashboard'): ?>
        <div class="rpos-kpi-grid">
            <div class="rpos-kpi-card">
                <div class="kpi-icon">⛽</div>
                <div class="kpi-value"><?php echo esc_html($analytics['active_cylinders']); ?></div>
                <div class="kpi-label">Active Cylinders</div>
            </div>
            <div class="rpos-kpi-card">
                <div class="kpi-icon">🔥</div>
                <div class="kpi-value"><?php echo esc_html(number_format($analytics['avg_burn_rate'], 1)); ?></div>
                <div class="kpi-label">Avg Orders/Day</div>
            </div>
            <div class="rpos-kpi-card">
                <div class="kpi-icon">⏳</div>
                <div class="kpi-value"><?php echo esc_html(number_format($analytics['avg_remaining_days'], 1)); ?></div>
                <div class="kpi-label">Avg Days Remaining</div>
            </div>
            <div class="rpos-kpi-card">
                <div class="kpi-icon">💰</div>
                <div class="kpi-value"><?php echo esc_html($currency . number_format($analytics['monthly_refill_cost'], 0)); ?></div>
                <div class="kpi-label">Monthly Refill Cost</div>
            </div>
            <div class="rpos-kpi-card">
                <div class="kpi-icon">📦</div>
                <div class="kpi-value"><?php echo esc_html(number_format($analytics['total_orders_served'])); ?></div>
                <div class="kpi-label">Total Orders Served</div>
            </div>
        </div>
        
        <div class="rpos-chart-container">
            <h2>Active Cylinders Overview</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Zone</th>
                        <th>Start Date</th>
                        <th>Orders Served</th>
                        <th>Remaining</th>
                        <th>Burn Rate</th>
                        <th>Est. Days Left</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $active_cylinders = RPOS_Gas_Cylinders::get_all_cylinders(array('status' => 'active'));
                    foreach ($active_cylinders as $cyl): 
                        $burn_rate = RPOS_Gas_Cylinders::calculate_burn_rate($cyl->id);
                        $zone = $cyl->zone_id ? RPOS_Gas_Cylinders::get_zone($cyl->zone_id) : null;
                        $status_class = $burn_rate['remaining_days'] < 3 ? 'rpos-status-low' : 'rpos-status-active';
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($cyl->type_name); ?></strong></td>
                            <td><?php echo $zone ? esc_html($zone->name) : '-'; ?></td>
                            <td><?php echo esc_html($cyl->start_date); ?></td>
                            <td><?php echo esc_html($cyl->orders_served); ?></td>
                            <td><?php echo esc_html(number_format($cyl->remaining_percentage, 1)); ?>%</td>
                            <td><?php echo esc_html(number_format($burn_rate['orders_per_day'], 1)); ?>/day</td>
                            <td><?php echo esc_html(number_format($burn_rate['remaining_days'], 1)); ?> days</td>
                            <td><span class="rpos-status-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo $burn_rate['remaining_days'] < 3 ? 'LOW' : 'ACTIVE'; ?>
                            </span></td>
                            <td>
                                <a href="?page=restaurant-pos-gas-cylinders&tab=refill&cyl_id=<?php echo esc_attr($cyl->id); ?>" class="button button-small">Refill</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="rpos-chart-container">
            <h2>Recent Activity</h2>
            <?php
            global $wpdb;
            $recent_consumption = $wpdb->get_results(
                "SELECT c.*, p.name as product_name, o.order_number, cyl.cylinder_type_id, t.name as cylinder_type
                 FROM {$wpdb->prefix}zaikon_cylinder_consumption c
                 LEFT JOIN {$wpdb->prefix}rpos_products p ON c.product_id = p.id
                 LEFT JOIN {$wpdb->prefix}rpos_orders o ON c.order_id = o.id
                 LEFT JOIN {$wpdb->prefix}rpos_gas_cylinders cyl ON c.cylinder_id = cyl.id
                 LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON cyl.cylinder_type_id = t.id
                 ORDER BY c.created_at DESC
                 LIMIT 20"
            );
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Order</th>
                        <th>Product</th>
                        <th>Cylinder Type</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_consumption as $log): ?>
                        <tr>
                            <td><?php echo esc_html(RPOS_Timezone::format($log->created_at, 'M d, Y H:i')); ?></td>
                            <td><?php echo esc_html($log->order_number); ?></td>
                            <td><?php echo esc_html($log->product_name); ?></td>
                            <td><?php echo esc_html($log->cylinder_type); ?></td>
                            <td><?php echo esc_html($log->quantity); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    
    <!-- Tab 2: Cylinder Types -->
    <?php elseif ($tab === 'types'): ?>
        <div class="notice notice-info" style="margin: 20px 0; padding: 15px; background: #e8f4f8; border-left: 4px solid #2271b1;">
            <p style="margin: 0; font-size: 14px;">
                <strong>ℹ️ Manage Cylinder Types:</strong> Define custom cylinder types here (e.g., "Grill Cylinder", "Fryer Cylinder", "Backup Cylinder"). 
                These types will be available when adding new cylinders.
            </p>
        </div>
        
        <h2>Add New Cylinder Type</h2>
        <form method="post" style="max-width: 600px; background: white; padding: 20px; border-radius: 8px;">
            <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
            <input type="hidden" name="action" value="add_type">
            <table class="form-table">
                <tr><th><label>Type Name *</label></th><td><input type="text" name="type_name" required class="regular-text" placeholder="e.g., Grill Cylinder, Fryer Cylinder"></td></tr>
                <tr><th><label>Description</label></th><td><textarea name="type_desc" rows="3" class="large-text" placeholder="Optional description for this cylinder type"></textarea></td></tr>
            </table>
            <button type="submit" class="button button-primary">Add Type</button>
        </form>
        
        <h2>Existing Cylinder Types</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Type Name</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cylinder_types)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 20px;">No cylinder types found. Add a new type using the form above.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    // Fetch all usage counts in a single query to avoid N+1 problem
                    global $wpdb;
                    // Extract and validate type IDs (ensure they're integers)
                    $type_ids = array_map('absint', array_column($cylinder_types, 'id'));
                    $type_ids = array_filter($type_ids); // Remove any zero/invalid values
                    
                    $usage_counts = array();
                    if (!empty($type_ids)) {
                        // Note: $placeholders is safe as it only contains '%d' strings from array_fill()
                        // The actual IDs are sanitized by array_map('absint') and passed via wpdb::prepare()
                        $placeholders = implode(',', array_fill(0, count($type_ids), '%d'));
                        $usage_counts = $wpdb->get_results($wpdb->prepare(
                            "SELECT cylinder_type_id, COUNT(*) as count 
                             FROM {$wpdb->prefix}rpos_gas_cylinders 
                             WHERE cylinder_type_id IN ($placeholders) 
                             GROUP BY cylinder_type_id",
                            $type_ids
                        ), OBJECT_K);
                    }
                    
                    foreach ($cylinder_types as $type): 
                        $in_use = isset($usage_counts[$type->id]) ? $usage_counts[$type->id]->count : 0;
                    ?>
                        <tr id="type-row-<?php echo esc_attr($type->id); ?>">
                            <td><strong><?php echo esc_html($type->name); ?></strong></td>
                            <td><?php echo esc_html($type->description); ?></td>
                            <td>
                                <button type="button" class="button button-small" onclick="editType(<?php echo esc_attr($type->id); ?>, '<?php echo esc_js($type->name); ?>', '<?php echo esc_js($type->description); ?>')">Edit</button>
                                <?php if ($in_use == 0): ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this cylinder type?');">
                                        <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_type">
                                        <input type="hidden" name="type_id" value="<?php echo esc_attr($type->id); ?>">
                                        <button type="submit" class="button button-small button-link-delete">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">(In use - cannot delete)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Edit Type Modal (inline form) -->
        <div id="edit-type-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); z-index: 10000; min-width: 500px;">
            <h2 style="margin-top: 0;">Edit Cylinder Type</h2>
            <form method="post" id="edit-type-form">
                <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
                <input type="hidden" name="action" value="update_type">
                <input type="hidden" name="type_id" id="edit-type-id">
                <table class="form-table">
                    <tr><th><label>Type Name *</label></th><td><input type="text" name="type_name" id="edit-type-name" required class="regular-text"></td></tr>
                    <tr><th><label>Description</label></th><td><textarea name="type_desc" id="edit-type-desc" rows="3" class="large-text"></textarea></td></tr>
                </table>
                <div style="margin-top: 15px;">
                    <button type="submit" class="button button-primary">Update Type</button>
                    <button type="button" class="button" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
        <div id="edit-type-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;" onclick="closeEditModal()"></div>
        
        <script>
        function editType(id, name, description) {
            document.getElementById('edit-type-id').value = id;
            document.getElementById('edit-type-name').value = name;
            document.getElementById('edit-type-desc').value = description;
            document.getElementById('edit-type-modal').style.display = 'block';
            document.getElementById('edit-type-overlay').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('edit-type-modal').style.display = 'none';
            document.getElementById('edit-type-overlay').style.display = 'none';
        }
        </script>
    
    <!-- Tab 3: Zones -->
    <?php elseif ($tab === 'zones'): ?>
        <h2>Add New Zone</h2>
        <form method="post" style="max-width: 600px; background: white; padding: 20px; border-radius: 8px;">
            <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
            <input type="hidden" name="action" value="add_zone">
            <table class="form-table">
                <tr><th><label>Zone Name *</label></th><td><input type="text" name="zone_name" required class="regular-text" placeholder="e.g., Oven, Counter, Grill"></td></tr>
                <tr><th><label>Description</label></th><td><textarea name="zone_desc" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <button type="submit" class="button button-primary">Add Zone</button>
        </form>
        
        <h2>Existing Zones</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Zone Name</th><th>Description</th><th>Active Cylinders</th></tr></thead>
            <tbody>
                <?php foreach ($zones as $zone): 
                    global $wpdb;
                    $cylinder_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}rpos_gas_cylinders WHERE zone_id = %d AND status = 'active'",
                        $zone->id
                    ));
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($zone->name); ?></strong></td>
                        <td><?php echo esc_html($zone->description); ?></td>
                        <td><?php echo esc_html($cylinder_count); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    
    <!-- Tab 4: Product Mapping -->
    <?php elseif ($tab === 'mapping'): ?>
        <h2>Product Mapping</h2>
        <p style="margin: 20px 0;">Assign products to cylinder types. When an order contains a mapped product, consumption is automatically tracked for that cylinder type.</p>
        
        <?php foreach ($cylinder_types as $type): 
            $mappings = RPOS_Gas_Cylinders::get_product_mappings($type->id);
            $mapped_ids = array_map(function($m) { return $m->product_id; }, $mappings);
            
            // Group products by category
            $products_by_category = array();
            foreach ($products as $product) {
                $category = !empty($product->category_name) ? $product->category_name : 'Uncategorized';
                if (!isset($products_by_category[$category])) {
                    $products_by_category[$category] = array();
                }
                $products_by_category[$category][] = $product;
            }
            ksort($products_by_category);
        ?>
            <div class="rpos-mapping-card" style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0; color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">
                    <?php echo esc_html($type->name); ?>
                </h2>
                <form method="post">
                    <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
                    <input type="hidden" name="action" value="update_mapping">
                    <input type="hidden" name="type_id" value="<?php echo esc_attr($type->id); ?>">
                    
                    <!-- Search and Controls -->
                    <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="text" 
                               class="rpos-product-search regular-text" 
                               placeholder="🔍 Search products..." 
                               data-cylinder-type="<?php echo esc_attr($type->id); ?>"
                               style="flex: 1; min-width: 250px;">
                        <button type="button" 
                                class="button rpos-select-all" 
                                data-cylinder-type="<?php echo esc_attr($type->id); ?>"
                                style="white-space: nowrap;">
                            ✓ Select All
                        </button>
                        <button type="button" 
                                class="button rpos-deselect-all" 
                                data-cylinder-type="<?php echo esc_attr($type->id); ?>"
                                style="white-space: nowrap;">
                            ✗ Deselect All
                        </button>
                        <span class="rpos-selection-counter" 
                              data-cylinder-type="<?php echo esc_attr($type->id); ?>"
                              style="font-weight: bold; color: #2271b1; white-space: nowrap;">
                            <span class="selected-count"><?php echo count($mapped_ids); ?></span> of 
                            <span class="total-count"><?php echo count($products); ?></span> selected
                        </span>
                    </div>
                    
                    <div class="rpos-product-list" 
                         data-cylinder-type="<?php echo esc_attr($type->id); ?>"
                         style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">
                        <?php if (empty($products)): ?>
                            <p style="color: #666; font-style: italic;">No products found. Please add products first.</p>
                        <?php else: ?>
                            <?php foreach ($products_by_category as $category => $category_products): ?>
                                <div class="rpos-product-category" style="margin-bottom: 15px;">
                                    <div style="background: #2271b1; color: white; padding: 8px 12px; margin: 0 -15px 10px -15px; font-weight: bold; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?php echo esc_html($category); ?>
                                    </div>
                                    <?php foreach ($category_products as $product): ?>
                                        <label class="rpos-product-item" 
                                               data-product-name="<?php echo esc_attr(strtolower($product->name)); ?>"
                                               style="display: flex; align-items: center; margin: 0 0 10px 0; padding: 12px; background: white; border-radius: 4px; cursor: pointer; transition: all 0.2s ease; border: 2px solid transparent;">
                                            <input type="checkbox" 
                                                   name="products[]" 
                                                   value="<?php echo esc_attr($product->id); ?>" 
                                                   class="rpos-product-checkbox"
                                                   data-cylinder-type="<?php echo esc_attr($type->id); ?>"
                                                   <?php checked(in_array($product->id, $mapped_ids)); ?>
                                                   style="width: 18px; height: 18px; margin-right: 12px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <strong style="font-size: 14px;"><?php echo esc_html($product->name); ?></strong>
                                                <?php if (!empty($product->price)): ?>
                                                    <span style="color: #46b450; font-size: 13px; margin-left: 10px;">
                                                        <?php echo esc_html(RPOS_Settings::get('currency_symbol', '$')); ?><?php echo number_format($product->price, 2); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="button button-primary button-large" style="margin-top: 15px;">
                        💾 Update Mapping for <?php echo esc_html($type->name); ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    
    <!-- Tab 5: Cylinders -->
    <?php elseif ($tab === 'cylinders'): ?>
        <h2>Add New Cylinder</h2>
        <form method="post" style="max-width: 700px; background: white; padding: 20px; border-radius: 8px;">
            <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
            <input type="hidden" name="action" value="add_cylinder">
            <table class="form-table">
                <tr>
                    <th><label>Cylinder Type *</label></th>
                    <td>
                        <select name="cylinder_type_id" required class="regular-text">
                            <option value="">-- Select Cylinder Type --</option>
                            <?php foreach ($cylinder_types as $type): ?>
                                <option value="<?php echo esc_attr($type->id); ?>"><?php echo esc_html($type->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Need to add a new cylinder type? <a href="?page=restaurant-pos-gas-cylinders&tab=types" style="font-weight: bold;">⚙️ Manage Types →</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label>Zone</label></th>
                    <td>
                        <select name="zone_id" class="regular-text">
                            <option value="">-- Select Zone (Optional) --</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo esc_attr($zone->id); ?>"><?php echo esc_html($zone->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr><th><label>Purchase Date</label></th><td><input type="date" name="purchase_date" class="regular-text"></td></tr>
                <tr><th><label>Cost</label></th><td><input type="number" name="cost" step="0.01" min="0" class="regular-text" placeholder="0.00"></td></tr>
                <tr><th><label>Start Date *</label></th><td><input type="date" name="start_date" required value="<?php echo esc_attr(current_time('Y-m-d')); ?>" class="regular-text"></td></tr>
                <tr><th><label>Vendor</label></th><td><input type="text" name="vendor" class="regular-text" placeholder="Vendor name"></td></tr>
                <tr><th><label>Notes</label></th><td><textarea name="notes" rows="3" class="large-text" placeholder="Optional notes about this cylinder"></textarea></td></tr>
            </table>
            <button type="submit" class="button button-primary">Add Cylinder</button>
        </form>
        
        <h2>Cylinder Records</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Zone</th>
                    <th>Start Date</th>
                    <th>Status</th>
                    <th>Orders Served</th>
                    <th>Remaining</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cylinders)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">No cylinders found. Add a new cylinder using the form above.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cylinders as $cyl): 
                        $zone = $cyl->zone_id ? RPOS_Gas_Cylinders::get_zone($cyl->zone_id) : null;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($cyl->type_name); ?></strong></td>
                            <td><?php echo $zone ? esc_html($zone->name) : '-'; ?></td>
                            <td><?php echo esc_html($cyl->start_date); ?></td>
                            <td>
                                <span class="rpos-status-badge <?php echo $cyl->status === 'active' ? 'rpos-status-active' : 'rpos-status-completed'; ?>">
                                    <?php echo esc_html(strtoupper($cyl->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($cyl->orders_served); ?></td>
                            <td><?php echo esc_html(number_format($cyl->remaining_percentage, 1)); ?>%</td>
                            <td>
                                <?php if ($cyl->status === 'active'): ?>
                                    <a href="?page=restaurant-pos-gas-cylinders&tab=refill&cyl_id=<?php echo esc_attr($cyl->id); ?>" class="button button-small">Refill</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    
    <!-- Tab 6: Lifecycle -->
    <?php elseif ($tab === 'lifecycle'): ?>
        <h2>Cylinder Lifecycle History</h2>
        <?php
        global $wpdb;
        $lifecycles = $wpdb->get_results(
            "SELECT l.*, c.cylinder_type_id, t.name as type_name, z.name as zone_name
             FROM {$wpdb->prefix}zaikon_cylinder_lifecycle l
             LEFT JOIN {$wpdb->prefix}rpos_gas_cylinders c ON l.cylinder_id = c.id
             LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
             LEFT JOIN {$wpdb->prefix}zaikon_cylinder_zones z ON c.zone_id = z.id
             ORDER BY l.start_date DESC
             LIMIT 50"
        );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Cylinder Type</th>
                    <th>Zone</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days Active</th>
                    <th>Orders Served</th>
                    <th>Avg/Day</th>
                    <th>Refill Cost</th>
                    <th>Cost/Order</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lifecycles as $lc): ?>
                    <tr>
                        <td><strong><?php echo esc_html($lc->type_name); ?></strong></td>
                        <td><?php echo esc_html($lc->zone_name ?? '-'); ?></td>
                        <td><?php echo esc_html($lc->start_date); ?></td>
                        <td><?php echo $lc->end_date ? esc_html($lc->end_date) : '<em>Active</em>'; ?></td>
                        <td><?php echo esc_html($lc->total_days); ?></td>
                        <td><?php echo esc_html($lc->orders_served); ?></td>
                        <td><?php echo esc_html(number_format($lc->avg_orders_per_day, 1)); ?></td>
                        <td><?php echo esc_html($currency . number_format($lc->refill_cost, 2)); ?></td>
                        <td><?php echo esc_html($currency . number_format($lc->cost_per_order, 2)); ?></td>
                        <td>
                            <span class="rpos-status-badge <?php echo $lc->status === 'active' ? 'rpos-status-active' : 'rpos-status-completed'; ?>">
                                <?php echo esc_html(strtoupper($lc->status)); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    
    <!-- Tab 7: Consumption -->
    <?php elseif ($tab === 'consumption'): ?>
        <h2>Consumption Logs</h2>
        <?php
        $selected_cyl = isset($_GET['cyl_id']) ? absint($_GET['cyl_id']) : 0;
        ?>
        <form method="get" style="margin: 20px 0;">
            <input type="hidden" name="page" value="restaurant-pos-gas-cylinders">
            <input type="hidden" name="tab" value="consumption">
            <label>Filter by Cylinder: </label>
            <select name="cyl_id" onchange="this.form.submit()">
                <option value="0">All Cylinders</option>
                <?php foreach ($cylinders as $cyl): ?>
                    <option value="<?php echo esc_attr($cyl->id); ?>" <?php selected($selected_cyl, $cyl->id); ?>>
                        <?php echo esc_html($cyl->type_name . ' - ' . $cyl->start_date); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <?php
        global $wpdb;
        $logs_query = "SELECT c.*, p.name as product_name, o.order_number, cyl.cylinder_type_id, t.name as cylinder_type
                       FROM {$wpdb->prefix}zaikon_cylinder_consumption c
                       LEFT JOIN {$wpdb->prefix}rpos_products p ON c.product_id = p.id
                       LEFT JOIN {$wpdb->prefix}rpos_orders o ON c.order_id = o.id
                       LEFT JOIN {$wpdb->prefix}rpos_gas_cylinders cyl ON c.cylinder_id = cyl.id
                       LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON cyl.cylinder_type_id = t.id";
        
        if ($selected_cyl > 0) {
            $logs_query .= $wpdb->prepare(" WHERE c.cylinder_id = %d", $selected_cyl);
        }
        
        $logs_query .= " ORDER BY c.created_at DESC LIMIT 100";
        $logs = $wpdb->get_results($logs_query);
        
        if (!is_array($logs)) {
            $logs = array();
        }
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Order Number</th>
                    <th>Product</th>
                    <th>Cylinder Type</th>
                    <th>Quantity</th>
                    <th>Units Consumed</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">No consumption logs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(RPOS_Timezone::format($log->created_at, 'M d, Y H:i')); ?></td>
                            <td><?php echo esc_html($log->order_number); ?></td>
                            <td><?php echo esc_html($log->product_name); ?></td>
                            <td><?php echo esc_html($log->cylinder_type); ?></td>
                            <td><?php echo esc_html($log->quantity); ?></td>
                            <td><?php echo esc_html(number_format($log->consumption_units, 4)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    
    <!-- Tab 8: Refill -->
    <?php elseif ($tab === 'refill'): ?>
        <h2>Process Cylinder Refill</h2>
        <?php
        $refill_cyl_id = isset($_GET['cyl_id']) ? absint($_GET['cyl_id']) : 0;
        $refill_cylinder = $refill_cyl_id > 0 ? RPOS_Gas_Cylinders::get_cylinder($refill_cyl_id) : null;
        ?>
        <form method="post" style="max-width: 700px; background: white; padding: 20px; border-radius: 8px;">
            <?php wp_nonce_field('rpos_gas_action', 'rpos_gas_nonce'); ?>
            <input type="hidden" name="action" value="process_refill">
            <table class="form-table">
                <tr>
                    <th><label>Select Cylinder *</label></th>
                    <td>
                        <select name="cylinder_id" required class="regular-text">
                            <option value="">-- Select Cylinder --</option>
                            <?php foreach ($cylinders as $cyl): ?>
                                <option value="<?php echo esc_attr($cyl->id); ?>" <?php selected($refill_cyl_id, $cyl->id); ?>>
                                    <?php echo esc_html($cyl->type_name . ' (Started: ' . $cyl->start_date . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr><th><label>Refill Date *</label></th><td><input type="date" name="refill_date" required value="<?php echo date('Y-m-d'); ?>" class="regular-text"></td></tr>
                <tr><th><label>Vendor</label></th><td><input type="text" name="vendor" class="regular-text" placeholder="Vendor name"></td></tr>
                <tr><th><label>Cost *</label></th><td><input type="number" name="cost" step="0.01" min="0" required class="regular-text"></td></tr>
                <tr><th><label>Quantity</label></th><td><input type="number" name="quantity" step="0.01" min="0" value="1" class="regular-text"></td></tr>
                <tr><th><label>Notes</label></th><td><textarea name="notes" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <button type="submit" class="button button-primary">Process Refill</button>
        </form>
        
        <h2>Refill History</h2>
        <?php
        global $wpdb;
        $refills = $wpdb->get_results(
            "SELECT r.*, c.cylinder_type_id, t.name as type_name, u.display_name as created_by_name
             FROM {$wpdb->prefix}zaikon_cylinder_refill r
             LEFT JOIN {$wpdb->prefix}rpos_gas_cylinders c ON r.cylinder_id = c.id
             LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
             LEFT JOIN {$wpdb->users} u ON r.created_by = u.ID
             ORDER BY r.refill_date DESC
             LIMIT 50"
        );
        
        if (!is_array($refills)) {
            $refills = array();
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Refill Date</th>
                    <th>Cylinder Type</th>
                    <th>Vendor</th>
                    <th>Cost</th>
                    <th>Quantity</th>
                    <th>Notes</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($refills)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">No refill history found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($refills as $refill): ?>
                        <tr>
                            <td><?php echo esc_html($refill->refill_date); ?></td>
                            <td><strong><?php echo esc_html($refill->type_name); ?></strong></td>
                            <td><?php echo esc_html($refill->vendor ?? '-'); ?></td>
                            <td><?php echo esc_html($currency . number_format($refill->cost, 2)); ?></td>
                            <td><?php echo esc_html($refill->quantity); ?></td>
                            <td><?php echo esc_html($refill->notes ?? '-'); ?></td>
                            <td><?php echo esc_html($refill->created_by_name ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    
    <!-- Tab 9: Analytics -->
    <?php elseif ($tab === 'analytics'): ?>
        <h2>Cylinder Performance Analytics</h2>
        
        <div class="rpos-chart-container">
            <h3>Efficiency Comparison</h3>
            <?php
            $efficiency = RPOS_Gas_Cylinders::get_efficiency_comparison();
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Cylinder Type</th>
                        <th>Zone</th>
                        <th>Days Active</th>
                        <th>Orders Served</th>
                        <th>Orders/Day</th>
                        <th>Remaining</th>
                        <th>Efficiency Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($efficiency as $eff): 
                        $rating = $eff->orders_per_day > 50 ? '⭐⭐⭐ Excellent' : 
                                 ($eff->orders_per_day > 30 ? '⭐⭐ Good' : '⭐ Fair');
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($eff->type_name); ?></strong></td>
                            <td><?php echo esc_html($eff->zone_name ?? '-'); ?></td>
                            <td><?php echo esc_html($eff->days_active); ?></td>
                            <td><?php echo esc_html($eff->orders_served); ?></td>
                            <td><?php echo esc_html(number_format($eff->orders_per_day, 1)); ?></td>
                            <td><?php echo esc_html(number_format($eff->remaining_percentage, 1)); ?>%</td>
                            <td><?php echo esc_html($rating); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="rpos-chart-container">
            <h3>Monthly Trends</h3>
            <?php
            global $wpdb;
            $monthly_stats = $wpdb->get_results(
                "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as order_count,
                    COUNT(DISTINCT cylinder_id) as unique_cylinders
                 FROM {$wpdb->prefix}zaikon_cylinder_consumption
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY month
                 ORDER BY month DESC"
            );
            
            if (!is_array($monthly_stats)) {
                $monthly_stats = array();
            }
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Orders Served</th>
                        <th>Active Cylinders</th>
                        <th>Avg Orders/Cylinder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monthly_stats)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">No monthly data available.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($monthly_stats as $stat): 
                            $avg_per_cyl = $stat->unique_cylinders > 0 ? $stat->order_count / $stat->unique_cylinders : 0;
                        ?>
                            <tr>
                                <td><?php echo esc_html(RPOS_Timezone::format($stat->month . '-01', 'F Y')); ?></td>
                                <td><?php echo esc_html(number_format($stat->order_count)); ?></td>
                                <td><?php echo esc_html($stat->unique_cylinders); ?></td>
                                <td><?php echo esc_html(number_format($avg_per_cyl, 1)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="rpos-chart-container">
            <h3>Cost Analysis</h3>
            <?php
            global $wpdb;
            $cost_analysis = $wpdb->get_results(
                "SELECT 
                    l.cylinder_id,
                    c.cylinder_type_id,
                    t.name as type_name,
                    COUNT(l.id) as lifecycle_count,
                    SUM(l.orders_served) as total_orders,
                    SUM(l.refill_cost) as total_cost,
                    AVG(l.cost_per_order) as avg_cost_per_order
                 FROM {$wpdb->prefix}zaikon_cylinder_lifecycle l
                 LEFT JOIN {$wpdb->prefix}rpos_gas_cylinders c ON l.cylinder_id = c.id
                 LEFT JOIN {$wpdb->prefix}rpos_gas_cylinder_types t ON c.cylinder_type_id = t.id
                 WHERE l.status = 'completed'
                 GROUP BY l.cylinder_id
                 ORDER BY total_orders DESC
                 LIMIT 20"
            );
            
            if (!is_array($cost_analysis)) {
                $cost_analysis = array();
            }
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Cylinder Type</th>
                        <th>Refill Cycles</th>
                        <th>Total Orders</th>
                        <th>Total Cost</th>
                        <th>Avg Cost/Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cost_analysis)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">No cost analysis data available.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cost_analysis as $cost): ?>
                            <tr>
                                <td><strong><?php echo esc_html($cost->type_name); ?></strong></td>
                                <td><?php echo esc_html($cost->lifecycle_count); ?></td>
                                <td><?php echo esc_html(number_format($cost->total_orders)); ?></td>
                                <td><?php echo esc_html($currency . number_format($cost->total_cost, 2)); ?></td>
                                <td><?php echo esc_html($currency . number_format($cost->avg_cost_per_order, 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
