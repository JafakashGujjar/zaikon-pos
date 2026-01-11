<?php
/**
 * Delivery Reports Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
$rider_id = isset($_GET['rider_id']) ? absint($_GET['rider_id']) : '';
$bike_id = isset($_GET['bike_id']) ? sanitize_text_field($_GET['bike_id']) : '';

// Get report data
$report_data = RPOS_Delivery_Logs::get_daily_report($date_from, $date_to, $rider_id, $bike_id);

// Get unique riders and bikes for filters
global $wpdb;
$riders = $wpdb->get_results("SELECT DISTINCT rider_id, rider_name FROM {$wpdb->prefix}rpos_delivery_logs WHERE rider_name IS NOT NULL AND rider_name != '' ORDER BY rider_name");
$bikes = $wpdb->get_results("SELECT DISTINCT bike_id FROM {$wpdb->prefix}rpos_delivery_logs WHERE bike_id IS NOT NULL AND bike_id != '' ORDER BY bike_id");

// Get currency
$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap rpos-delivery-reports">
    <h1><?php echo esc_html__('Delivery Reports', 'restaurant-pos'); ?></h1>
    
    <div class="rpos-reports-filters">
        <form method="get" class="rpos-filter-form">
            <input type="hidden" name="page" value="restaurant-pos-delivery-reports">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="date_from"><?php echo esc_html__('Date From', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                    </td>
                    
                    <th scope="row">
                        <label for="date_to"><?php echo esc_html__('Date To', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="rider_id"><?php echo esc_html__('Rider', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <select id="rider_id" name="rider_id">
                            <option value=""><?php echo esc_html__('All Riders', 'restaurant-pos'); ?></option>
                            <?php foreach ($riders as $rider): ?>
                                <option value="<?php echo esc_attr($rider->rider_id); ?>" <?php selected($rider_id, $rider->rider_id); ?>>
                                    <?php echo esc_html($rider->rider_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    
                    <th scope="row">
                        <label for="bike_id"><?php echo esc_html__('Bike', 'restaurant-pos'); ?></label>
                    </th>
                    <td>
                        <select id="bike_id" name="bike_id">
                            <option value=""><?php echo esc_html__('All Bikes', 'restaurant-pos'); ?></option>
                            <?php foreach ($bikes as $bike): ?>
                                <option value="<?php echo esc_attr($bike->bike_id); ?>" <?php selected($bike_id, $bike->bike_id); ?>>
                                    <?php echo esc_html($bike->bike_id); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Apply Filters', 'restaurant-pos'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=restaurant-pos-delivery-reports')); ?>" class="button"><?php echo esc_html__('Reset', 'restaurant-pos'); ?></a>
            </p>
        </form>
    </div>
    
    <div class="rpos-reports-summary">
        <?php
        $total_fuel = 0;
        $total_deliveries = 0;
        $total_km = 0;
        $total_charges = 0;
        $actual_deliveries = 0;
        $actual_km = 0;
        
        foreach ($report_data as $row) {
            $total_fuel += floatval($row->fuel_amount);
            $total_deliveries += intval($row->deliveries_count);
            $total_km += floatval($row->total_km);
            $total_charges += floatval($row->total_delivery_charges ?? 0);
            
            // Get actual delivery data from orders if rider_id is available
            if ($row->rider_id) {
                $actual_deliveries += RPOS_Delivery_Logs::get_delivery_count_for_rider($row->rider_id, $row->date);
                $actual_km += RPOS_Delivery_Logs::get_total_km_for_rider($row->rider_id, $row->date);
            }
        }
        
        // Calculate averages
        $avg_km_per_delivery = ($actual_deliveries > 0) ? ($actual_km / $actual_deliveries) : 0;
        ?>
        
        <h2><?php echo esc_html__('Summary', 'restaurant-pos'); ?></h2>
        <div class="rpos-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Deliveries', 'restaurant-pos'); ?></h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($actual_deliveries); ?></p>
                <?php if ($total_deliveries != $actual_deliveries): ?>
                    <small style="color: #999;">(Logged: <?php echo esc_html($total_deliveries); ?>)</small>
                <?php endif; ?>
            </div>
            
            <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total KM', 'restaurant-pos'); ?></h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html(number_format($actual_km, 2)); ?></p>
                <?php if (abs($total_km - $actual_km) > 0.1): ?>
                    <small style="color: #999;">(Logged: <?php echo esc_html(number_format($total_km, 2)); ?>)</small>
                <?php endif; ?>
            </div>
            
            <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Fuel Filled', 'restaurant-pos'); ?></h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html($currency . number_format($total_fuel, 2)); ?></p>
            </div>
            
            <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Avg KM per Delivery', 'restaurant-pos'); ?></h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html(number_format($avg_km_per_delivery, 2)); ?></p>
            </div>
            
            <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Delivery Charges Collected', 'restaurant-pos'); ?></h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html($currency . number_format($total_charges, 2)); ?></p>
            </div>
        </div>
    </div>
            $total_charges += floatval($row->total_delivery_charges);
        }
        
        $avg_km_per_delivery = $total_deliveries > 0 ? ($total_km / $total_deliveries) : 0;
        ?>
        
        <h2><?php echo esc_html__('Summary', 'restaurant-pos'); ?></h2>
        <div class="rpos-summary-boxes">
            <div class="rpos-summary-box">
                <div class="rpos-summary-label"><?php echo esc_html__('Total Deliveries', 'restaurant-pos'); ?></div>
                <div class="rpos-summary-value"><?php echo esc_html(number_format($total_deliveries)); ?></div>
            </div>
            
            <div class="rpos-summary-box">
                <div class="rpos-summary-label"><?php echo esc_html__('Total KM', 'restaurant-pos'); ?></div>
                <div class="rpos-summary-value"><?php echo esc_html(number_format($total_km, 2)); ?></div>
            </div>
            
            <div class="rpos-summary-box">
                <div class="rpos-summary-label"><?php echo esc_html__('Avg KM per Delivery', 'restaurant-pos'); ?></div>
                <div class="rpos-summary-value"><?php echo esc_html(number_format($avg_km_per_delivery, 2)); ?></div>
            </div>
            
            <div class="rpos-summary-box">
                <div class="rpos-summary-label"><?php echo esc_html__('Total Delivery Charges', 'restaurant-pos'); ?></div>
                <div class="rpos-summary-value"><?php echo esc_html($currency . number_format($total_charges, 2)); ?></div>
            </div>
        </div>
    </div>
    
    <div class="rpos-reports-table">
        <h2><?php echo esc_html__('Detailed Report', 'restaurant-pos'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Date', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Rider', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Bike', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Fuel Filled', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Deliveries', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Total KM', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Avg KM/Delivery', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Delivery Charges', 'restaurant-pos'); ?></th>
                    <th><?php echo esc_html__('Notes', 'restaurant-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($report_data)): ?>
                    <tr>
                        <td colspan="9"><?php echo esc_html__('No data found for the selected filters.', 'restaurant-pos'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($report_data as $row): ?>
                        <?php
                        $avg_km = $row->deliveries_count > 0 ? ($row->total_km / $row->deliveries_count) : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($row->date))); ?></td>
                            <td><?php echo esc_html($row->rider_name); ?></td>
                            <td><?php echo esc_html($row->bike_id); ?></td>
                            <td><?php echo esc_html(number_format($row->fuel_amount, 2) . ' ' . $row->fuel_unit); ?></td>
                            <td><?php echo esc_html($row->deliveries_count); ?></td>
                            <td><?php echo esc_html(number_format($row->total_km, 2)); ?></td>
                            <td><?php echo esc_html(number_format($avg_km, 2)); ?></td>
                            <td><?php echo esc_html($currency . number_format($row->total_delivery_charges, 2)); ?></td>
                            <td><?php echo esc_html($row->notes); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.rpos-reports-filters {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.rpos-reports-summary {
    margin: 20px 0;
}

.rpos-summary-boxes {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.rpos-summary-box {
    flex: 1;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    text-align: center;
}

.rpos-summary-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 10px;
}

.rpos-summary-value {
    font-size: 28px;
    font-weight: bold;
    color: #0073aa;
}

.rpos-reports-table {
    margin: 20px 0;
}
</style>
