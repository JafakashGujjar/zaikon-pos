<?php
/**
 * Rider Deliveries Admin View
 * Shows all riders' deliveries for admin/manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filters
$rider_id = isset($_GET['rider_id']) ? absint($_GET['rider_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Get all riders for dropdown
$riders = Zaikon_Riders::get_all();

// Build filters array
$filters = array();
if ($rider_id > 0) {
    $filters['rider_id'] = $rider_id;
}
if ($status) {
    $filters['status'] = $status;
}
if ($date_from) {
    $filters['date_from'] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $filters['date_to'] = $date_to . ' 23:59:59';
}

// Get rider orders
$rider_orders = Zaikon_Rider_Orders::get_all($filters);

// Calculate totals
$total_deliveries = count($rider_orders);
$total_payout = 0;
$status_counts = array(
    'assigned' => 0,
    'picked' => 0,
    'delivered' => 0,
    'failed' => 0
);

foreach ($rider_orders as $ro) {
    $status_counts[$ro->status] = ($status_counts[$ro->status] ?? 0) + 1;
    
    // Calculate payout if delivered
    if ($ro->status === 'delivered') {
        // Get rider details for payout calculation
        $rider = Zaikon_Riders::get($ro->rider_id);
        if ($rider && isset($ro->distance_km)) {
            $distance = floatval($ro->distance_km);
            $payout = 0;
            
            switch ($rider->payout_type) {
                case 'per_delivery':
                    $payout = floatval($rider->per_delivery_rate);
                    break;
                case 'per_km':
                    $payout = floatval($rider->per_km_rate) * $distance;
                    break;
                case 'hybrid':
                    $payout = floatval($rider->base_rate) + (floatval($rider->per_km_rate) * $distance);
                    break;
            }
            
            $total_payout += $payout;
        }
    }
}

$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap">
    <h1><?php _e('Rider Deliveries (Admin View)', 'restaurant-pos'); ?></h1>
    
    <!-- Filters -->
    <div class="rpos-filters" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccc;">
        <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="restaurant-pos-rider-deliveries-admin">
            
            <div>
                <label for="date_from"><?php _e('From:', 'restaurant-pos'); ?></label><br>
                <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
            </div>
            
            <div>
                <label for="date_to"><?php _e('To:', 'restaurant-pos'); ?></label><br>
                <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
            </div>
            
            <div>
                <label for="rider_id"><?php _e('Rider:', 'restaurant-pos'); ?></label><br>
                <select name="rider_id" id="rider_id">
                    <option value="0"><?php _e('All Riders', 'restaurant-pos'); ?></option>
                    <?php foreach ($riders as $rider): ?>
                        <option value="<?php echo $rider->id; ?>" <?php selected($rider_id, $rider->id); ?>>
                            <?php echo esc_html($rider->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="status"><?php _e('Status:', 'restaurant-pos'); ?></label><br>
                <select name="status" id="status">
                    <option value=""><?php _e('All Statuses', 'restaurant-pos'); ?></option>
                    <option value="assigned" <?php selected($status, 'assigned'); ?>><?php _e('Assigned', 'restaurant-pos'); ?></option>
                    <option value="picked" <?php selected($status, 'picked'); ?>><?php _e('Picked', 'restaurant-pos'); ?></option>
                    <option value="delivered" <?php selected($status, 'delivered'); ?>><?php _e('Delivered', 'restaurant-pos'); ?></option>
                    <option value="failed" <?php selected($status, 'failed'); ?>><?php _e('Failed', 'restaurant-pos'); ?></option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary"><?php _e('Filter', 'restaurant-pos'); ?></button>
            </div>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background: #fff; padding: 15px; border: 1px solid #ccc; border-left: 4px solid #2271b1;">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php _e('Total Deliveries', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 28px; font-weight: bold;"><?php echo $total_deliveries; ?></p>
        </div>
        
        <div style="background: #fff; padding: 15px; border: 1px solid #ccc; border-left: 4px solid #46b450;">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php _e('Delivered', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 28px; font-weight: bold; color: #46b450;"><?php echo $status_counts['delivered']; ?></p>
        </div>
        
        <div style="background: #fff; padding: 15px; border: 1px solid #ccc; border-left: 4px solid #fbbf24;">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php _e('In Progress', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 28px; font-weight: bold; color: #fbbf24;"><?php echo $status_counts['picked'] + $status_counts['assigned']; ?></p>
        </div>
        
        <div style="background: #fff; padding: 15px; border: 1px solid #ccc; border-left: 4px solid #f97316;">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><?php _e('Total Payout', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 28px; font-weight: bold; color: #f97316;">Rs <?php echo number_format($total_payout, 2); ?></p>
        </div>
    </div>
    
    <!-- Deliveries Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Order ID', 'restaurant-pos'); ?></th>
                <th><?php _e('Order Number', 'restaurant-pos'); ?></th>
                <th><?php _e('Rider', 'restaurant-pos'); ?></th>
                <th><?php _e('Customer', 'restaurant-pos'); ?></th>
                <th><?php _e('Location', 'restaurant-pos'); ?></th>
                <th><?php _e('Distance (km)', 'restaurant-pos'); ?></th>
                <th><?php _e('Status', 'restaurant-pos'); ?></th>
                <th><?php _e('Rider Pay (Rs)', 'restaurant-pos'); ?></th>
                <th><?php _e('Assigned At', 'restaurant-pos'); ?></th>
                <th><?php _e('Delivered At', 'restaurant-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rider_orders)): ?>
                <tr>
                    <td colspan="10"><?php _e('No deliveries found.', 'restaurant-pos'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($rider_orders as $ro): ?>
                    <?php
                    // Calculate rider payout
                    $rider = Zaikon_Riders::get($ro->rider_id);
                    $payout = 0;
                    
                    if ($rider && isset($ro->distance_km) && $ro->status === 'delivered') {
                        $distance = floatval($ro->distance_km);
                        
                        switch ($rider->payout_type) {
                            case 'per_delivery':
                                $payout = floatval($rider->per_delivery_rate);
                                break;
                            case 'per_km':
                                $payout = floatval($rider->per_km_rate) * $distance;
                                break;
                            case 'hybrid':
                                $payout = floatval($rider->base_rate) + (floatval($rider->per_km_rate) * $distance);
                                break;
                        }
                    }
                    
                    // Status badge colors
                    $status_colors = array(
                        'assigned' => '#2271b1',
                        'picked' => '#fbbf24',
                        'delivered' => '#46b450',
                        'failed' => '#dc3232'
                    );
                    $status_color = $status_colors[$ro->status] ?? '#666';
                    ?>
                    <tr>
                        <td><?php echo $ro->order_id; ?></td>
                        <td><?php echo esc_html($ro->order_number); ?></td>
                        <td><?php echo esc_html($ro->rider_name); ?></td>
                        <td><?php echo esc_html($ro->customer_name); ?></td>
                        <td><?php echo esc_html($ro->location_name); ?></td>
                        <td><?php echo $ro->distance_km ? number_format($ro->distance_km, 2) : '-'; ?></td>
                        <td>
                            <span style="background: <?php echo $status_color; ?>; color: white; padding: 3px 10px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                                <?php echo esc_html($ro->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($payout > 0): ?>
                                Rs <?php echo number_format($payout, 2); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo $ro->assigned_at ? date('Y-m-d H:i', strtotime($ro->assigned_at)) : '-'; ?></td>
                        <td><?php echo $ro->delivered_at ? date('Y-m-d H:i', strtotime($ro->delivered_at)) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
