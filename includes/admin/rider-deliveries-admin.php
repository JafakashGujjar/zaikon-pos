<?php
/**
 * Rider Deliveries Admin View
 * Shows all riders' deliveries for admin/manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filters using plugin timezone
$rider_id = isset($_GET['rider_id']) ? absint($_GET['rider_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : RPOS_Timezone::now()->modify('-7 days')->format('Y-m-d');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : RPOS_Timezone::now()->format('Y-m-d');
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$tab = $_GET['tab'] ?? 'deliveries';

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

// Pre-fetch all riders to avoid N+1 queries
$all_riders = Zaikon_Riders::get_all();
$riders_by_id = array();
foreach ($all_riders as $r) {
    $riders_by_id[$r->id] = $r;
}

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
        $rider = $riders_by_id[$ro->rider_id] ?? null;
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
.rpos-kpi-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}
.rpos-kpi-card p {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
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
.rpos-status-completed {
    background: #cce5ff;
    color: #004085;
}
.rpos-status-low {
    background: #fff3cd;
    color: #856404;
}
</style>

<div class="wrap">
    <h1><?php _e('Rider Deliveries (Admin View)', 'restaurant-pos'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=restaurant-pos-rider-deliveries-admin&tab=deliveries<?php echo $rider_id ? '&rider_id=' . $rider_id : ''; ?><?php echo $status ? '&status=' . $status : ''; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="nav-tab <?php echo $tab === 'deliveries' ? 'nav-tab-active' : ''; ?>">
            🚚 Deliveries
        </a>
        <a href="?page=restaurant-pos-rider-deliveries-admin&tab=summary<?php echo $rider_id ? '&rider_id=' . $rider_id : ''; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="nav-tab <?php echo $tab === 'summary' ? 'nav-tab-active' : ''; ?>">
            📊 Summary
        </a>
    </h2>
    
    <?php if ($tab === 'deliveries'): ?>
        <!-- Filters -->
        <div class="rpos-chart-container">
            <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="restaurant-pos-rider-deliveries-admin">
                <input type="hidden" name="tab" value="deliveries">
                
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
        
        <!-- Deliveries Table -->
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php _e('Deliveries', 'restaurant-pos'); ?></h2>
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
                            $rider = $riders_by_id[$ro->rider_id] ?? null;
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
                                <td><?php echo $ro->assigned_at ? RPOS_Timezone::format($ro->assigned_at, 'Y-m-d H:i') : '-'; ?></td>
                                <td><?php echo $ro->delivered_at ? RPOS_Timezone::format($ro->delivered_at, 'Y-m-d H:i') : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    
    <?php elseif ($tab === 'summary'): ?>
        <!-- Summary Tab -->
        <div class="rpos-kpi-grid">
            <div class="rpos-kpi-card">
                <h3><?php _e('Total Deliveries', 'restaurant-pos'); ?></h3>
                <p><?php echo $total_deliveries; ?></p>
            </div>
            
            <div class="rpos-kpi-card" style="border-left-color: #46b450;">
                <h3><?php _e('Delivered', 'restaurant-pos'); ?></h3>
                <p style="color: #46b450;"><?php echo $status_counts['delivered']; ?></p>
            </div>
            
            <div class="rpos-kpi-card" style="border-left-color: #fbbf24;">
                <h3><?php _e('In Progress', 'restaurant-pos'); ?></h3>
                <p style="color: #fbbf24;"><?php echo $status_counts['picked'] + $status_counts['assigned']; ?></p>
            </div>
            
            <div class="rpos-kpi-card" style="border-left-color: #f97316;">
                <h3><?php _e('Total Payout', 'restaurant-pos'); ?></h3>
                <p style="color: #f97316;">Rs <?php echo number_format($total_payout, 2); ?></p>
            </div>
        </div>
        
        <div class="rpos-chart-container">
            <h2 style="margin-top: 0;"><?php _e('Status Breakdown', 'restaurant-pos'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Status', 'restaurant-pos'); ?></th>
                        <th><?php _e('Count', 'restaurant-pos'); ?></th>
                        <th><?php _e('Percentage', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_counts as $status_key => $count): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucfirst($status_key)); ?></strong></td>
                            <td><?php echo $count; ?></td>
                            <td><?php echo $total_deliveries > 0 ? number_format(($count / $total_deliveries) * 100, 1) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($rider_id > 0): ?>
            <?php
            $selected_rider = $riders_by_id[$rider_id] ?? null;
            if ($selected_rider):
                // Calculate rider-specific totals
                $rider_deliveries = count($rider_orders);
                $rider_delivered = $status_counts['delivered'];
                $rider_payout = $total_payout;
            ?>
            <div class="rpos-chart-container">
                <h2 style="margin-top: 0;"><?php _e('Rider Details: ', 'restaurant-pos'); echo esc_html($selected_rider->name); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Rider Name:', 'restaurant-pos'); ?></th>
                        <td><strong><?php echo esc_html($selected_rider->name); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php _e('Phone:', 'restaurant-pos'); ?></th>
                        <td><?php echo esc_html($selected_rider->phone); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Total Deliveries:', 'restaurant-pos'); ?></th>
                        <td><?php echo $rider_deliveries; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Delivered:', 'restaurant-pos'); ?></th>
                        <td><?php echo $rider_delivered; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Success Rate:', 'restaurant-pos'); ?></th>
                        <td><?php echo $rider_deliveries > 0 ? number_format(($rider_delivered / $rider_deliveries) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <tr>
                        <th><?php _e('Total Payout:', 'restaurant-pos'); ?></th>
                        <td><strong>Rs <?php echo number_format($rider_payout, 2); ?></strong></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
