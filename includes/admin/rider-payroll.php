<?php
/**
 * Rider Payroll Admin Screen
 * Comprehensive payroll management for riders with date range filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get date range from query params or default to current month
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-t');

// Add time components for proper querying
$date_from_dt = $date_from . ' 00:00:00';
$date_to_dt = $date_to . ' 23:59:59';

// Get all riders
$riders = Zaikon_Riders::get_all(true); // Active riders only

// Prepare payroll data for each rider
$payroll_data = array();
$totals = array(
    'total_deliveries' => 0,
    'total_distance' => 0,
    'total_payout' => 0,
    'total_fuel' => 0,
    'net_total' => 0
);

foreach ($riders as $rider) {
    $performance = Zaikon_Reports::get_rider_performance($rider->id, $date_from_dt, $date_to_dt);
    
    if ($performance && $performance['deliveries_count'] > 0) {
        $payroll_data[] = $performance;
        $totals['total_deliveries'] += $performance['deliveries_count'];
        $totals['total_distance'] += $performance['total_distance_km'];
        $totals['total_payout'] += $performance['total_rider_pay'];
        $totals['total_fuel'] += $performance['total_fuel_cost'];
        $totals['net_total'] += ($performance['total_rider_pay'] - $performance['total_fuel_cost']);
    }
}
?>

<div class="wrap rpos-rider-payroll">
    <h1><?php echo esc_html__('Rider Payroll', 'restaurant-pos'); ?></h1>
    
    <!-- Date Range Filter -->
    <div class="rpos-payroll-filters" style="background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <form method="GET" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            <div style="display: flex; gap: 15px; align-items: center;">
                <label>
                    <strong><?php echo esc_html__('From:', 'restaurant-pos'); ?></strong>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" required>
                </label>
                <label>
                    <strong><?php echo esc_html__('To:', 'restaurant-pos'); ?></strong>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" required>
                </label>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Filter', 'restaurant-pos'); ?></button>
                <button type="button" class="button" onclick="window.print();"><?php echo esc_html__('Print', 'restaurant-pos'); ?></button>
                <button type="button" class="button rpos-export-csv"><?php echo esc_html__('Export CSV', 'restaurant-pos'); ?></button>
            </div>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div class="rpos-payroll-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Deliveries', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($totals['total_deliveries']); ?></p>
        </div>
        <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Distance (km)', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html(number_format($totals['total_distance'], 2)); ?></p>
        </div>
        <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Payout (Rs)', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html(number_format($totals['total_payout'], 2)); ?></p>
        </div>
        <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Fuel (Rs)', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html(number_format($totals['total_fuel'], 2)); ?></p>
        </div>
        <div class="rpos-summary-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Net Payout (Rs)', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html(number_format($totals['net_total'], 2)); ?></p>
        </div>
    </div>
    
    <!-- Rider Payroll Table -->
    <div class="rpos-payroll-table" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2><?php echo esc_html__('Rider Breakdown', 'restaurant-pos'); ?></h2>
        <p><strong><?php echo esc_html__('Period:', 'restaurant-pos'); ?></strong> <?php echo esc_html(RPOS_Timezone::format($date_from, 'M d, Y') . ' - ' . RPOS_Timezone::format($date_to, 'M d, Y')); ?></p>
        
        <?php if (empty($payroll_data)): ?>
            <p style="text-align: center; padding: 40px; color: #666;">
                <?php echo esc_html__('No deliveries found for the selected period.', 'restaurant-pos'); ?>
            </p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped rpos-payroll-data-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Rider Name', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Payout Model', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Deliveries', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Distance (km)', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Gross Payout (Rs)', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Fuel Cost (Rs)', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Net Payout (Rs)', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Avg per Delivery', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payroll_data as $data): ?>
                        <tr>
                            <td><strong><?php echo esc_html($data['rider']->name); ?></strong></td>
                            <td>
                                <?php
                                $payout_type = isset($data['rider']->payout_type) ? $data['rider']->payout_type : 'per_km';
                                $payout_labels = array(
                                    'per_delivery' => 'Per Delivery',
                                    'per_km' => 'Per KM',
                                    'hybrid' => 'Hybrid'
                                );
                                echo esc_html($payout_labels[$payout_type] ?? 'Per KM');
                                ?>
                            </td>
                            <td><?php echo esc_html($data['deliveries_count']); ?></td>
                            <td><?php echo esc_html(number_format($data['total_distance_km'], 2)); ?></td>
                            <td><strong>Rs <?php echo esc_html(number_format($data['total_rider_pay'], 2)); ?></strong></td>
                            <td>Rs <?php echo esc_html(number_format($data['total_fuel_cost'], 2)); ?></td>
                            <td><strong style="color: #00a32a;">Rs <?php echo esc_html(number_format($data['total_rider_pay'] - $data['total_fuel_cost'], 2)); ?></strong></td>
                            <td>Rs <?php echo esc_html(number_format($data['avg_delivery_charge'], 2)); ?></td>
                            <td>
                                <button class="button button-small rpos-view-details" 
                                        data-rider-id="<?php echo esc_attr($data['rider']->id); ?>"
                                        data-rider-name="<?php echo esc_attr($data['rider']->name); ?>">
                                    <?php echo esc_html__('View Details', 'restaurant-pos'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f0f0f1; font-weight: bold;">
                        <td colspan="2"><?php echo esc_html__('TOTALS', 'restaurant-pos'); ?></td>
                        <td><?php echo esc_html($totals['total_deliveries']); ?></td>
                        <td><?php echo esc_html(number_format($totals['total_distance'], 2)); ?></td>
                        <td><strong>Rs <?php echo esc_html(number_format($totals['total_payout'], 2)); ?></strong></td>
                        <td>Rs <?php echo esc_html(number_format($totals['total_fuel'], 2)); ?></td>
                        <td><strong style="color: #00a32a;">Rs <?php echo esc_html(number_format($totals['net_total'], 2)); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Print Styles -->
<style media="print">
    .wrap > h1:first-child,
    .rpos-payroll-filters,
    .button,
    #adminmenumain,
    #wpadminbar,
    #wpfooter {
        display: none !important;
    }
    
    .rpos-payroll-table {
        box-shadow: none !important;
    }
    
    table {
        border-collapse: collapse;
    }
    
    table th,
    table td {
        border: 1px solid #ddd;
        padding: 8px;
    }
</style>

<script>
jQuery(document).ready(function($) {
    // Export to CSV
    $('.rpos-export-csv').on('click', function() {
        var csv = [];
        var rows = $('.rpos-payroll-data-table tr');
        
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = $(rows[i]).find('td, th');
            
            for (var j = 0; j < cols.length - 1; j++) { // Exclude actions column
                var text = $(cols[j]).text().trim().replace(/"/g, '""');
                row.push('"' + text + '"');
            }
            
            csv.push(row.join(','));
        }
        
        var csvContent = csv.join('\n');
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'rider-payroll-<?php echo esc_js($date_from . '_' . $date_to); ?>.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // View rider details (can be expanded to show modal with detailed breakdown)
    $('.rpos-view-details').on('click', function() {
        var riderId = $(this).data('rider-id');
        var riderName = $(this).data('rider-name');
        
        alert('Detailed view for ' + riderName + ' (Rider ID: ' + riderId + ') coming soon!');
    });
});
</script>
