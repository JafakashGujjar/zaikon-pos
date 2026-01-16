<?php
/**
 * Zaikon Delivery Management Admin Page
 * Manages locations, charge slabs, free delivery rules, and riders
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
$message = '';
$message_type = '';

// Handle Location Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zaikon_location_nonce'])) {
    if (!wp_verify_nonce($_POST['zaikon_location_nonce'], 'zaikon_location_action')) {
        wp_die('Security check failed');
    }
    
    $location_data = array(
        'name' => sanitize_text_field($_POST['location_name']),
        'distance_km' => floatval($_POST['distance_km']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    );
    
    if (isset($_POST['location_id']) && $_POST['location_id']) {
        Zaikon_Delivery_Locations::update($_POST['location_id'], $location_data);
        $message = __('Location updated successfully!', 'restaurant-pos');
    } else {
        Zaikon_Delivery_Locations::create($location_data);
        $message = __('Location created successfully!', 'restaurant-pos');
    }
    $message_type = 'success';
}

// Handle Charge Slab Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zaikon_slab_nonce'])) {
    if (!wp_verify_nonce($_POST['zaikon_slab_nonce'], 'zaikon_slab_action')) {
        wp_die('Security check failed');
    }
    
    $slab_data = array(
        'min_km' => floatval($_POST['min_km']),
        'max_km' => floatval($_POST['max_km']),
        'charge_rs' => floatval($_POST['charge_rs']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    );
    
    if (isset($_POST['slab_id']) && $_POST['slab_id']) {
        Zaikon_Delivery_Charge_Slabs::update($_POST['slab_id'], $slab_data);
        $message = __('Charge slab updated successfully!', 'restaurant-pos');
    } else {
        Zaikon_Delivery_Charge_Slabs::create($slab_data);
        $message = __('Charge slab created successfully!', 'restaurant-pos');
    }
    $message_type = 'success';
}

// Handle Free Delivery Rule Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zaikon_free_rule_nonce'])) {
    if (!wp_verify_nonce($_POST['zaikon_free_rule_nonce'], 'zaikon_free_rule_action')) {
        wp_die('Security check failed');
    }
    
    $rule_data = array(
        'max_km' => floatval($_POST['max_km']),
        'min_order_amount_rs' => floatval($_POST['min_order_amount_rs']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    );
    
    if (isset($_POST['rule_id']) && $_POST['rule_id']) {
        Zaikon_Free_Delivery_Rules::update($_POST['rule_id'], $rule_data);
        $message = __('Free delivery rule updated successfully!', 'restaurant-pos');
    } else {
        Zaikon_Free_Delivery_Rules::create($rule_data);
        $message = __('Free delivery rule created successfully!', 'restaurant-pos');
    }
    $message_type = 'success';
}

// Handle Rider Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zaikon_rider_nonce'])) {
    if (!wp_verify_nonce($_POST['zaikon_rider_nonce'], 'zaikon_rider_action')) {
        wp_die('Security check failed');
    }
    
    $rider_data = array(
        'name' => sanitize_text_field($_POST['rider_name']),
        'phone' => sanitize_text_field($_POST['rider_phone']),
        'status' => sanitize_text_field($_POST['status'])
    );
    
    // Add payout fields if provided
    if (isset($_POST['payout_type'])) {
        $rider_data['payout_type'] = sanitize_text_field($_POST['payout_type']);
    }
    if (isset($_POST['per_delivery_rate'])) {
        $rider_data['per_delivery_rate'] = floatval($_POST['per_delivery_rate']);
    }
    if (isset($_POST['per_km_rate'])) {
        $rider_data['per_km_rate'] = floatval($_POST['per_km_rate']);
    }
    if (isset($_POST['base_rate'])) {
        $rider_data['base_rate'] = floatval($_POST['base_rate']);
    }
    
    if (isset($_POST['rider_id']) && $_POST['rider_id']) {
        Zaikon_Riders::update($_POST['rider_id'], $rider_data);
        $message = __('Rider updated successfully!', 'restaurant-pos');
    } else {
        Zaikon_Riders::create($rider_data);
        $message = __('Rider created successfully!', 'restaurant-pos');
    }
    $message_type = 'success';
}

// Handle Delete Actions
if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
    if ($_GET['action'] === 'delete_location' && wp_verify_nonce($_GET['_wpnonce'], 'delete_location_' . $_GET['id'])) {
        Zaikon_Delivery_Locations::delete($_GET['id']);
        $message = __('Location deleted successfully!', 'restaurant-pos');
        $message_type = 'success';
    } elseif ($_GET['action'] === 'delete_slab' && wp_verify_nonce($_GET['_wpnonce'], 'delete_slab_' . $_GET['id'])) {
        Zaikon_Delivery_Charge_Slabs::delete($_GET['id']);
        $message = __('Charge slab deleted successfully!', 'restaurant-pos');
        $message_type = 'success';
    } elseif ($_GET['action'] === 'delete_rule' && wp_verify_nonce($_GET['_wpnonce'], 'delete_rule_' . $_GET['id'])) {
        Zaikon_Free_Delivery_Rules::delete($_GET['id']);
        $message = __('Free delivery rule deleted successfully!', 'restaurant-pos');
        $message_type = 'success';
    } elseif ($_GET['action'] === 'delete_rider' && wp_verify_nonce($_GET['_wpnonce'], 'delete_rider_' . $_GET['id'])) {
        Zaikon_Riders::delete($_GET['id']);
        $message = __('Rider deleted successfully!', 'restaurant-pos');
        $message_type = 'success';
    }
}

// Get current data
$locations = Zaikon_Delivery_Locations::get_all();
$slabs = Zaikon_Delivery_Charge_Slabs::get_all();
$free_rules = Zaikon_Free_Delivery_Rules::get_all();
$riders = Zaikon_Riders::get_all();
$active_rule = Zaikon_Free_Delivery_Rules::get_active_rule();
$tab = $_GET['tab'] ?? 'locations';
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
.rpos-chart-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
</style>

<div class="wrap zaikon-admin">
    <h1><?php _e('Zaikon Delivery Management', 'restaurant-pos'); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=zaikon-delivery-management&tab=locations" class="nav-tab <?php echo $tab === 'locations' ? 'nav-tab-active' : ''; ?>">
            📍 Locations
        </a>
        <a href="?page=zaikon-delivery-management&tab=slabs" class="nav-tab <?php echo $tab === 'slabs' ? 'nav-tab-active' : ''; ?>">
            💵 Charge Slabs
        </a>
        <a href="?page=zaikon-delivery-management&tab=free-rules" class="nav-tab <?php echo $tab === 'free-rules' ? 'nav-tab-active' : ''; ?>">
            🎁 Free Delivery Rules
        </a>
        <a href="?page=zaikon-delivery-management&tab=riders" class="nav-tab <?php echo $tab === 'riders' ? 'nav-tab-active' : ''; ?>">
            🏍️ Riders
        </a>
    </h2>
    
    <!-- Locations Tab -->
    <?php if ($tab === 'locations'): ?>
    <div class="rpos-chart-container">
        <h2 style="margin-top: 0;"><?php _e('Delivery Locations (Villages/Areas)', 'restaurant-pos'); ?></h2>
        
        <form method="post" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <?php wp_nonce_field('zaikon_location_action', 'zaikon_location_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="location_name"><?php _e('Location Name', 'restaurant-pos'); ?></label></th>
                    <td><input type="text" name="location_name" id="location_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="distance_km"><?php _e('Distance (km)', 'restaurant-pos'); ?></label></th>
                    <td><input type="number" name="distance_km" id="distance_km" step="0.01" min="0" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="is_active"><?php _e('Active', 'restaurant-pos'); ?></label></th>
                    <td><input type="checkbox" name="is_active" id="is_active" value="1" checked /></td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Add Location', 'restaurant-pos'); ?></button>
            </p>
        </form>
        
        <h3><?php _e('Existing Locations', 'restaurant-pos'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'restaurant-pos'); ?></th>
                    <th><?php _e('Distance (km)', 'restaurant-pos'); ?></th>
                    <th><?php _e('Status', 'restaurant-pos'); ?></th>
                    <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locations)): ?>
                    <tr>
                        <td colspan="4"><?php _e('No locations found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($locations as $location): ?>
                        <tr>
                            <td><?php echo esc_html($location->name); ?></td>
                            <td><?php echo number_format($location->distance_km, 2); ?> km</td>
                            <td>
                                <span class="rpos-status-badge <?php echo $location->is_active ? 'rpos-status-active' : ''; ?>">
                                    <?php echo $location->is_active ? __('Active', 'restaurant-pos') : __('Inactive', 'restaurant-pos'); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete_location', 'id' => $location->id, 'tab' => 'locations')), 'delete_location_' . $location->id); ?>" 
                                   class="button button-small"
                                   onclick="return confirm('<?php _e('Are you sure?', 'restaurant-pos'); ?>')">
                                    <?php _e('Delete', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Charge Slabs Tab -->
    <?php elseif ($tab === 'slabs'): ?>
    <div class="rpos-chart-container">
        <h2 style="margin-top: 0;"><?php _e('Delivery Charge Slabs (Km-based)', 'restaurant-pos'); ?></h2>
        
        <form method="post" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <?php wp_nonce_field('zaikon_slab_action', 'zaikon_slab_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="min_km"><?php _e('Min Km', 'restaurant-pos'); ?></label></th>
                    <td><input type="number" name="min_km" id="min_km" step="0.01" min="0" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="max_km"><?php _e('Max Km', 'restaurant-pos'); ?></label></th>
                    <td><input type="number" name="max_km" id="max_km" step="0.01" min="0" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="charge_rs"><?php _e('Charge (Rs)', 'restaurant-pos'); ?></label></th>
                    <td><input type="number" name="charge_rs" id="charge_rs" step="0.01" min="0" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="is_active"><?php _e('Active', 'restaurant-pos'); ?></label></th>
                    <td><input type="checkbox" name="is_active" value="1" checked /></td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Add Charge Slab', 'restaurant-pos'); ?></button>
            </p>
        </form>
        
        <h3><?php _e('Existing Charge Slabs', 'restaurant-pos'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Min Km', 'restaurant-pos'); ?></th>
                    <th><?php _e('Max Km', 'restaurant-pos'); ?></th>
                    <th><?php _e('Charge (Rs)', 'restaurant-pos'); ?></th>
                    <th><?php _e('Status', 'restaurant-pos'); ?></th>
                    <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($slabs)): ?>
                    <tr>
                        <td colspan="5"><?php _e('No charge slabs found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($slabs as $slab): ?>
                        <tr>
                            <td><?php echo number_format($slab->min_km, 2); ?> km</td>
                            <td><?php echo number_format($slab->max_km, 2); ?> km</td>
                            <td>Rs <?php echo number_format($slab->charge_rs, 2); ?></td>
                            <td><?php echo $slab->is_active ? __('Active', 'restaurant-pos') : __('Inactive', 'restaurant-pos'); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete_slab', 'id' => $slab->id)), 'delete_slab_' . $slab->id); ?>" 
                                   class="button button-small"
                                   onclick="return confirm('<?php _e('Are you sure?', 'restaurant-pos'); ?>')">
                                    <?php _e('Delete', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Free Delivery Rules Tab -->
    <?php elseif ($tab === 'free-rules'): ?>
    <div class="rpos-chart-container">
        <h2 style="margin-top: 0;"><?php _e('Free Delivery Rules', 'restaurant-pos'); ?></h2>
        <p class="description"><?php _e('Only ONE rule can be active at a time. If order meets criteria, delivery is free.', 'restaurant-pos'); ?></p>
        
        <?php if ($active_rule): ?>
            <div class="notice notice-info" style="margin: 15px 0;">
                <p><strong><?php _e('Active Rule:', 'restaurant-pos'); ?></strong> 
                   <?php printf(__('Free delivery for orders up to %s km with minimum order amount of Rs %s', 'restaurant-pos'), 
                      number_format($active_rule->max_km, 2), 
                      number_format($active_rule->min_order_amount_rs, 2)); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="post" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <?php wp_nonce_field('zaikon_free_rule_action', 'zaikon_free_rule_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="max_km"><?php _e('Max Distance (km)', 'restaurant-pos'); ?></label></th>
                    <td><input type="number" name="max_km" id="max_km" step="0.01" min="0" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="min_order_amount_rs"><?php _e('Min Order Amount (Rs)', 'restaurant-pos'); ?></label></th>
                    <td><input type="number" name="min_order_amount_rs" id="min_order_amount_rs" step="0.01" min="0" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="is_active"><?php _e('Active', 'restaurant-pos'); ?></label></th>
                    <td><input type="checkbox" name="is_active" value="1" checked /></td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Add Free Delivery Rule', 'restaurant-pos'); ?></button>
            </p>
        </form>
        
        <h3><?php _e('Existing Rules', 'restaurant-pos'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Max Km', 'restaurant-pos'); ?></th>
                    <th><?php _e('Min Order Amount', 'restaurant-pos'); ?></th>
                    <th><?php _e('Status', 'restaurant-pos'); ?></th>
                    <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($free_rules)): ?>
                    <tr>
                        <td colspan="4"><?php _e('No free delivery rules found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($free_rules as $rule): ?>
                        <tr>
                            <td><?php echo number_format($rule->max_km, 2); ?> km</td>
                            <td>Rs <?php echo number_format($rule->min_order_amount_rs, 2); ?></td>
                            <td>
                                <?php if ($rule->is_active): ?>
                                    <span class="dashicons dashicons-yes" style="color: green;"></span> <?php _e('Active', 'restaurant-pos'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-no" style="color: red;"></span> <?php _e('Inactive', 'restaurant-pos'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete_rule', 'id' => $rule->id)), 'delete_rule_' . $rule->id); ?>" 
                                   class="button button-small"
                                   onclick="return confirm('<?php _e('Are you sure?', 'restaurant-pos'); ?>')">
                                    <?php _e('Delete', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Riders Tab -->
    <?php elseif ($tab === 'riders'): ?>
    <div class="rpos-chart-container">
        <h2 style="margin-top: 0;"><?php _e('Delivery Riders', 'restaurant-pos'); ?></h2>
        
        <form method="post" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <?php wp_nonce_field('zaikon_rider_action', 'zaikon_rider_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="rider_name"><?php _e('Rider Name', 'restaurant-pos'); ?></label></th>
                    <td><input type="text" name="rider_name" id="rider_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="rider_phone"><?php _e('Phone', 'restaurant-pos'); ?></label></th>
                    <td><input type="text" name="rider_phone" id="rider_phone" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="status"><?php _e('Status', 'restaurant-pos'); ?></label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="active"><?php _e('Active', 'restaurant-pos'); ?></option>
                            <option value="inactive"><?php _e('Inactive', 'restaurant-pos'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th colspan="2"><hr style="margin: 20px 0;"><h3 style="margin: 10px 0;"><?php _e('Payout Settings', 'restaurant-pos'); ?></h3></th>
                </tr>
                <tr>
                    <th><label for="payout_type"><?php _e('Payout Type', 'restaurant-pos'); ?></label></th>
                    <td>
                        <select name="payout_type" id="payout_type" class="regular-text">
                            <option value=""><?php _e('-- Select Payout Type --', 'restaurant-pos'); ?></option>
                            <option value="per_delivery"><?php _e('Per Delivery', 'restaurant-pos'); ?></option>
                            <option value="per_km"><?php _e('Per Kilometer', 'restaurant-pos'); ?></option>
                            <option value="hybrid"><?php _e('Hybrid (Per Delivery + Per Km)', 'restaurant-pos'); ?></option>
                        </select>
                        <p class="description"><?php _e('How the rider is paid for deliveries', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="per_delivery_rate"><?php _e('Per Delivery Rate (Rs)', 'restaurant-pos'); ?></label></th>
                    <td>
                        <input type="number" name="per_delivery_rate" id="per_delivery_rate" step="0.01" min="0" class="regular-text" />
                        <p class="description"><?php _e('Fixed amount per delivery (used for Per Delivery type)', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="per_km_rate"><?php _e('Per Km Rate (Rs)', 'restaurant-pos'); ?></label></th>
                    <td>
                        <input type="number" name="per_km_rate" id="per_km_rate" step="0.01" min="0" class="regular-text" />
                        <p class="description"><?php _e('Amount per kilometer (used for Per Km and Hybrid types)', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="base_rate"><?php _e('Base Rate (Rs)', 'restaurant-pos'); ?></label></th>
                    <td>
                        <input type="number" name="base_rate" id="base_rate" step="0.01" min="0" class="regular-text" />
                        <p class="description"><?php _e('Base amount per delivery in Hybrid type (base + per_km * distance)', 'restaurant-pos'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Add Rider', 'restaurant-pos'); ?></button>
            </p>
        </form>
        
        <h3><?php _e('Existing Riders', 'restaurant-pos'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'restaurant-pos'); ?></th>
                    <th><?php _e('Phone', 'restaurant-pos'); ?></th>
                    <th><?php _e('Payout Type', 'restaurant-pos'); ?></th>
                    <th><?php _e('Rates (Rs)', 'restaurant-pos'); ?></th>
                    <th><?php _e('Status', 'restaurant-pos'); ?></th>
                    <th><?php _e('Actions', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riders)): ?>
                    <tr>
                        <td colspan="6"><?php _e('No riders found.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riders as $rider): ?>
                        <tr>
                            <td><?php echo esc_html($rider->name); ?></td>
                            <td><?php echo esc_html($rider->phone); ?></td>
                            <td>
                                <?php 
                                if (!empty($rider->payout_type)) {
                                    $payout_labels = array(
                                        'per_delivery' => __('Per Delivery', 'restaurant-pos'),
                                        'per_km' => __('Per Km', 'restaurant-pos'),
                                        'hybrid' => __('Hybrid', 'restaurant-pos')
                                    );
                                    echo '<span class="rpos-status-badge">';
                                    echo esc_html($payout_labels[$rider->payout_type] ?? $rider->payout_type);
                                    echo '</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $rates = array();
                                if (!empty($rider->per_delivery_rate) && $rider->per_delivery_rate > 0) {
                                    $rates[] = 'Per Delivery: Rs ' . number_format($rider->per_delivery_rate, 2);
                                }
                                if (!empty($rider->per_km_rate) && $rider->per_km_rate > 0) {
                                    $rates[] = 'Per Km: Rs ' . number_format($rider->per_km_rate, 2);
                                }
                                if (!empty($rider->base_rate) && $rider->base_rate > 0) {
                                    $rates[] = 'Base: Rs ' . number_format($rider->base_rate, 2);
                                }
                                echo $rates ? esc_html(implode(', ', $rates)) : '-';
                                ?>
                            </td>
                            <td>
                                <span class="rpos-status-badge <?php echo $rider->status === 'active' ? 'rpos-status-active' : ''; ?>">
                                    <?php echo $rider->status === 'active' ? __('Active', 'restaurant-pos') : __('Inactive', 'restaurant-pos'); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete_rider', 'id' => $rider->id, 'tab' => 'riders')), 'delete_rider_' . $rider->id); ?>" 
                                   class="button button-small"
                                   onclick="return confirm('<?php _e('Are you sure?', 'restaurant-pos'); ?>')">
                                    <?php _e('Delete', 'restaurant-pos'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

