<?php
/**
 * Shift Reports Page
 * View and manage cashier shift reports with filtering and export
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filters
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
$cashier_filter = isset($_GET['cashier_id']) ? absint($_GET['cashier_id']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$variance_filter = isset($_GET['variance']) ? sanitize_text_field($_GET['variance']) : '';

// Get all cashiers for filter
global $wpdb;
$cashiers = $wpdb->get_results("
    SELECT DISTINCT u.ID, u.display_name 
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->prefix}zaikon_cashier_sessions s ON u.ID = s.cashier_id
    ORDER BY u.display_name ASC
");

// Build query for shifts
$where_conditions = array("1=1");
$where_params = array();

if (!empty($date_from)) {
    $where_conditions[] = "DATE(session_start) >= %s";
    $where_params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(session_start) <= %s";
    $where_params[] = $date_to;
}

if (!empty($cashier_filter)) {
    $where_conditions[] = "cashier_id = %d";
    $where_params[] = $cashier_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = %s";
    $where_params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);
$query = "
    SELECT s.*, u.display_name as cashier_name 
    FROM {$wpdb->prefix}zaikon_cashier_sessions s
    LEFT JOIN {$wpdb->users} u ON s.cashier_id = u.ID
    WHERE {$where_clause}
    ORDER BY s.session_start DESC
";

if (!empty($where_params)) {
    $shifts = $wpdb->get_results($wpdb->prepare($query, $where_params));
} else {
    $shifts = $wpdb->get_results($query);
}

// Filter by variance if needed
if (!empty($variance_filter) && $variance_filter !== 'all') {
    $shifts = array_filter($shifts, function($shift) use ($variance_filter) {
        $variance = floatval($shift->cash_difference_rs);
        
        switch ($variance_filter) {
            case 'exact':
                return $variance == 0;
            case 'overage':
                return $variance > 0;
            case 'shortage':
                return $variance < 0;
            default:
                return true;
        }
    });
}

$currency = RPOS_Settings::get('currency_symbol', 'Rs');
?>

<div class="wrap zaikon-admin-page">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Shift Reports', 'restaurant-pos'); ?></h1>
    
    <div class="zaikon-filters-card" style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="get" class="zaikon-filter-form">
            <input type="hidden" name="page" value="restaurant-pos-shift-reports">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div>
                    <label for="date_from" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html__('From Date:', 'restaurant-pos'); ?></label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div>
                    <label for="date_to" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html__('To Date:', 'restaurant-pos'); ?></label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div>
                    <label for="cashier_id" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html__('Cashier:', 'restaurant-pos'); ?></label>
                    <select name="cashier_id" id="cashier_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="0"><?php echo esc_html__('All Cashiers', 'restaurant-pos'); ?></option>
                        <?php foreach ($cashiers as $cashier): ?>
                            <option value="<?php echo esc_attr($cashier->ID); ?>" <?php selected($cashier_filter, $cashier->ID); ?>>
                                <?php echo esc_html($cashier->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="status" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html__('Status:', 'restaurant-pos'); ?></label>
                    <select name="status" id="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value=""><?php echo esc_html__('All Statuses', 'restaurant-pos'); ?></option>
                        <option value="open" <?php selected($status_filter, 'open'); ?>><?php echo esc_html__('Open', 'restaurant-pos'); ?></option>
                        <option value="closed" <?php selected($status_filter, 'closed'); ?>><?php echo esc_html__('Closed', 'restaurant-pos'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label for="variance" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html__('Variance:', 'restaurant-pos'); ?></label>
                    <select name="variance" id="variance" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="all"><?php echo esc_html__('All', 'restaurant-pos'); ?></option>
                        <option value="exact" <?php selected($variance_filter, 'exact'); ?>><?php echo esc_html__('Exact (0)', 'restaurant-pos'); ?></option>
                        <option value="overage" <?php selected($variance_filter, 'overage'); ?>><?php echo esc_html__('Overage (+)', 'restaurant-pos'); ?></option>
                        <option value="shortage" <?php selected($variance_filter, 'shortage'); ?>><?php echo esc_html__('Shortage (-)', 'restaurant-pos'); ?></option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-search" style="margin-top: 3px;"></span>
                    <?php echo esc_html__('Filter', 'restaurant-pos'); ?>
                </button>
                <button type="button" class="button" onclick="window.location.href='?page=restaurant-pos-shift-reports'">
                    <?php echo esc_html__('Reset', 'restaurant-pos'); ?>
                </button>
                <button type="button" class="button" id="export-csv-btn">
                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                    <?php echo esc_html__('Export CSV', 'restaurant-pos'); ?>
                </button>
            </div>
        </form>
    </div>
    
    <?php if (empty($shifts)): ?>
        <div class="notice notice-info">
            <p><?php echo esc_html__('No shifts found for the selected criteria.', 'restaurant-pos'); ?></p>
        </div>
    <?php else: ?>
        <div style="background: white; padding: 0; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Shift ID', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Cashier', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Start Time', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('End Time', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Opening Cash', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Total Cash Sales', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Total COD Collected', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Total Expenses', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Expected Cash', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actual Cash', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Variance', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts as $shift): ?>
                        <?php
                        $variance = floatval($shift->cash_difference_rs);
                        $variance_class = '';
                        $variance_symbol = '';
                        
                        if ($variance > 0) {
                            $variance_class = 'overage';
                            $variance_symbol = '+';
                        } elseif ($variance < 0) {
                            $variance_class = 'shortage';
                            $variance_symbol = '';
                        } else {
                            $variance_class = 'exact';
                        }
                        ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($shift->id); ?></strong></td>
                            <td><?php echo esc_html($shift->cashier_name); ?></td>
                            <td><?php echo esc_html(RPOS_Timezone::format($shift->session_start, 'Y-m-d H:i')); ?></td>
                            <td><?php echo $shift->session_end ? esc_html(RPOS_Timezone::format($shift->session_end, 'Y-m-d H:i')) : '<span style="color: #46b450;">Active</span>'; ?></td>
                            <td><?php echo esc_html($currency . number_format($shift->opening_cash_rs, 2)); ?></td>
                            <td><?php echo $shift->status === 'closed' ? esc_html($currency . number_format($shift->total_cash_sales_rs, 2)) : '-'; ?></td>
                            <td><?php echo $shift->status === 'closed' ? esc_html($currency . number_format($shift->total_cod_collected_rs, 2)) : '-'; ?></td>
                            <td><?php echo $shift->status === 'closed' ? esc_html($currency . number_format($shift->total_expenses_rs, 2)) : '-'; ?></td>
                            <td><?php echo esc_html($currency . number_format($shift->expected_cash_rs, 2)); ?></td>
                            <td><?php echo $shift->closing_cash_rs ? esc_html($currency . number_format($shift->closing_cash_rs, 2)) : '-'; ?></td>
                            <td>
                                <?php if ($shift->status === 'closed'): ?>
                                    <span class="shift-variance <?php echo esc_attr($variance_class); ?>" style="
                                        padding: 4px 8px;
                                        border-radius: 4px;
                                        font-weight: 600;
                                        <?php if ($variance_class === 'overage'): ?>
                                            background: #d4edda; color: #155724;
                                        <?php elseif ($variance_class === 'shortage'): ?>
                                            background: #f8d7da; color: #721c24;
                                        <?php else: ?>
                                            background: #d1ecf1; color: #0c5460;
                                        <?php endif; ?>
                                    ">
                                        <?php echo esc_html($variance_symbol . $currency . number_format(abs($variance), 2)); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="shift-status-badge" style="
                                    padding: 4px 12px;
                                    border-radius: 4px;
                                    font-weight: 600;
                                    font-size: 12px;
                                    <?php if ($shift->status === 'open'): ?>
                                        background: #d4edda; color: #155724;
                                    <?php else: ?>
                                        background: #d6d8db; color: #383d41;
                                    <?php endif; ?>
                                ">
                                    <?php echo esc_html(strtoupper($shift->status)); ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small view-shift-details" data-shift-id="<?php echo esc_attr($shift->id); ?>">
                                    <?php echo esc_html__('View Details', 'restaurant-pos'); ?>
                                </button>
                                <?php if ($shift->status === 'closed' && current_user_can('manage_options')): ?>
                                    <button class="button button-small reopen-shift" data-shift-id="<?php echo esc_attr($shift->id); ?>" style="margin-left: 5px;">
                                        <?php echo esc_html__('Re-open', 'restaurant-pos'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Shift Details Modal -->
<div id="shift-details-modal" class="zaikon-modal" style="display: none;">
    <div class="zaikon-modal-content" style="max-width: 800px;">
        <div class="zaikon-modal-header">
            <h3><?php echo esc_html__('Shift Details', 'restaurant-pos'); ?></h3>
            <button class="zaikon-modal-close" id="close-shift-details">&times;</button>
        </div>
        <div class="zaikon-modal-body" id="shift-details-content">
            <p style="text-align: center; padding: 40px;"><?php echo esc_html__('Loading...', 'restaurant-pos'); ?></p>
        </div>
    </div>
</div>

<style>
.zaikon-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
}

.zaikon-modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.zaikon-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.zaikon-modal-header h3 {
    margin: 0;
}

.zaikon-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #666;
    line-height: 1;
    padding: 0;
}

.zaikon-modal-close:hover {
    color: #dc3232;
}

.zaikon-modal-body {
    padding: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // View shift details
    $('.view-shift-details').on('click', function() {
        var shiftId = $(this).data('shift-id');
        
        $('#shift-details-modal').fadeIn(200);
        $('#shift-details-content').html('<p style="text-align: center; padding: 40px;">Loading...</p>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'get_shift_details',
                shift_id: shiftId,
                nonce: '<?php echo wp_create_nonce('shift_details_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#shift-details-content').html(response.data.html);
                } else {
                    $('#shift-details-content').html('<p style="color: red;">Error loading shift details.</p>');
                }
            },
            error: function() {
                $('#shift-details-content').html('<p style="color: red;">Error loading shift details.</p>');
            }
        });
    });
    
    // Close modal
    $('#close-shift-details, .zaikon-modal').on('click', function(e) {
        if (e.target === this) {
            $('#shift-details-modal').fadeOut(200);
        }
    });
    
    // Export CSV
    $('#export-csv-btn').on('click', function() {
        var params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        window.location.href = '?' + params.toString();
    });
    
    // Re-open shift (admin only)
    $('.reopen-shift').on('click', function() {
        if (!confirm('Are you sure you want to re-open this shift? This action should only be done for auditing purposes.')) {
            return;
        }
        
        var shiftId = $(this).data('shift-id');
        var reason = prompt('Please enter a reason for re-opening this shift:');
        
        if (!reason) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'reopen_shift',
                shift_id: shiftId,
                reason: reason,
                nonce: '<?php echo wp_create_nonce('reopen_shift_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Shift re-opened successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error re-opening shift');
            }
        });
    });
});
</script>
