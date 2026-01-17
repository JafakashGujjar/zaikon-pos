<?php
/**
 * Cashier Shifts & Expenses Admin Page
 * Displays cashier sessions with cash tracking and expenses
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filters
$cashier_id = isset($_GET['cashier_id']) ? absint($_GET['cashier_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Get all cashiers for dropdown
$cashiers = get_users(array('role__in' => array('administrator', 'restaurant_admin', 'cashier')));

// Get sessions
$sessions = Zaikon_Cashier_Sessions::get_sessions($cashier_id > 0 ? $cashier_id : null, 100);

// Filter by date range and status
$filtered_sessions = array();
foreach ($sessions as $session) {
    $session_date = date('Y-m-d', strtotime($session->session_start));
    
    // Date filter
    if ($session_date < $date_from || $session_date > $date_to) {
        continue;
    }
    
    // Status filter
    if ($status && $session->status !== $status) {
        continue;
    }
    
    $filtered_sessions[] = $session;
}

$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap">
    <h1><?php _e('Cashier Shifts & Expenses', 'restaurant-pos'); ?></h1>
    
    <!-- Filters -->
    <div class="rpos-filters" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccc;">
        <form method="get" style="display: flex; gap: 15px; align-items: end;">
            <input type="hidden" name="page" value="restaurant-pos-cashier-shifts">
            
            <div>
                <label for="date_from"><?php _e('From:', 'restaurant-pos'); ?></label><br>
                <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
            </div>
            
            <div>
                <label for="date_to"><?php _e('To:', 'restaurant-pos'); ?></label><br>
                <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
            </div>
            
            <div>
                <label for="cashier_id"><?php _e('Cashier:', 'restaurant-pos'); ?></label><br>
                <select name="cashier_id" id="cashier_id">
                    <option value="0"><?php _e('All Cashiers', 'restaurant-pos'); ?></option>
                    <?php foreach ($cashiers as $cashier): ?>
                        <option value="<?php echo $cashier->ID; ?>" <?php selected($cashier_id, $cashier->ID); ?>>
                            <?php echo esc_html($cashier->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="status"><?php _e('Status:', 'restaurant-pos'); ?></label><br>
                <select name="status" id="status">
                    <option value=""><?php _e('All', 'restaurant-pos'); ?></option>
                    <option value="open" <?php selected($status, 'open'); ?>><?php _e('Open', 'restaurant-pos'); ?></option>
                    <option value="closed" <?php selected($status, 'closed'); ?>><?php _e('Closed', 'restaurant-pos'); ?></option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary"><?php _e('Filter', 'restaurant-pos'); ?></button>
            </div>
        </form>
    </div>
    
    <!-- Sessions Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Session ID', 'restaurant-pos'); ?></th>
                <th><?php _e('Cashier', 'restaurant-pos'); ?></th>
                <th><?php _e('Open Time', 'restaurant-pos'); ?></th>
                <th><?php _e('Close Time', 'restaurant-pos'); ?></th>
                <th><?php _e('Opening Cash (Rs)', 'restaurant-pos'); ?></th>
                <th><?php _e('Cash Sales (Rs)', 'restaurant-pos'); ?></th>
                <th><?php _e('COD Collected (Rs)', 'restaurant-pos'); ?></th>
                <th><?php _e('Expenses (Rs)', 'restaurant-pos'); ?></th>
                <th><?php _e('Expected Cash', 'restaurant-pos'); ?></th>
                <th><?php _e('Closing Cash', 'restaurant-pos'); ?></th>
                <th><?php _e('Difference', 'restaurant-pos'); ?></th>
                <th><?php _e('Status', 'restaurant-pos'); ?></th>
                <th><?php _e('Actions', 'restaurant-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filtered_sessions)): ?>
                <tr>
                    <td colspan="13"><?php _e('No sessions found.', 'restaurant-pos'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($filtered_sessions as $session): ?>
                    <?php
                    $cashier = get_userdata($session->cashier_id);
                    $cashier_name = $cashier ? $cashier->display_name : __('Unknown', 'restaurant-pos');
                    
                    // Calculate totals if session is open or if not already calculated
                    if ($session->status === 'open' || (!$session->total_cash_sales_rs && !$session->total_cod_collected_rs)) {
                        $totals = Zaikon_Cashier_Sessions::calculate_session_totals($session->id);
                    } else {
                        $totals = array(
                            'cash_sales' => floatval($session->total_cash_sales_rs),
                            'cod_collected' => floatval($session->total_cod_collected_rs),
                            'expenses' => floatval($session->total_expenses_rs),
                            'expected_cash' => floatval($session->expected_cash_rs)
                        );
                    }
                    
                    $difference = floatval($session->closing_cash_rs) - floatval($totals['expected_cash']);
                    ?>
                    <tr class="session-row" data-session-id="<?php echo $session->id; ?>">
                        <td><?php echo $session->id; ?></td>
                        <td><?php echo esc_html($cashier_name); ?></td>
                        <td><?php echo RPOS_Timezone::format($session->session_start, 'Y-m-d H:i'); ?></td>
                        <td><?php echo $session->session_end ? RPOS_Timezone::format($session->session_end, 'Y-m-d H:i') : '-'; ?></td>
                        <td><?php echo number_format($session->opening_cash_rs, 2); ?></td>
                        <td><?php echo number_format($totals['cash_sales'], 2); ?></td>
                        <td><?php echo number_format($totals['cod_collected'], 2); ?></td>
                        <td><?php echo number_format($totals['expenses'], 2); ?></td>
                        <td><?php echo number_format($totals['expected_cash'], 2); ?></td>
                        <td><?php echo $session->closing_cash_rs ? number_format($session->closing_cash_rs, 2) : '-'; ?></td>
                        <td>
                            <?php if ($session->status === 'closed'): ?>
                                <span style="color: <?php echo $difference != 0 ? 'red' : 'green'; ?>; font-weight: bold;">
                                    <?php echo number_format($difference, 2); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($session->status === 'open'): ?>
                                <span class="dashicons dashicons-unlock" style="color: green;"></span> <?php _e('Open', 'restaurant-pos'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-lock" style="color: gray;"></span> <?php _e('Closed', 'restaurant-pos'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small view-details" data-session-id="<?php echo $session->id; ?>">
                                <?php _e('View Details', 'restaurant-pos'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr class="session-details" id="details-<?php echo $session->id; ?>" style="display: none;">
                        <td colspan="13" style="background: #f9f9f9; padding: 20px;">
                            <h3><?php _e('Session Details', 'restaurant-pos'); ?></h3>
                            
                            <h4><?php _e('Expenses:', 'restaurant-pos'); ?></h4>
                            <?php
                            $expenses = Zaikon_Expenses::get_by_session($session->id);
                            if (empty($expenses)): ?>
                                <p><?php _e('No expenses recorded for this session.', 'restaurant-pos'); ?></p>
                            <?php else: ?>
                                <table class="widefat" style="margin-bottom: 20px;">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Date', 'restaurant-pos'); ?></th>
                                            <th><?php _e('Category', 'restaurant-pos'); ?></th>
                                            <th><?php _e('Amount (Rs)', 'restaurant-pos'); ?></th>
                                            <th><?php _e('Description', 'restaurant-pos'); ?></th>
                                            <th><?php _e('Rider', 'restaurant-pos'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td><?php echo RPOS_Timezone::format($expense->expense_date, 'Y-m-d H:i'); ?></td>
                                                <td><?php echo esc_html($expense->category); ?></td>
                                                <td><?php echo number_format($expense->amount_rs, 2); ?></td>
                                                <td><?php echo esc_html($expense->description); ?></td>
                                                <td><?php echo $expense->rider_name ? esc_html($expense->rider_name) : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            
                            <?php if ($session->notes): ?>
                                <h4><?php _e('Notes:', 'restaurant-pos'); ?></h4>
                                <p><?php echo nl2br(esc_html($session->notes)); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    $('.view-details').on('click', function() {
        var sessionId = $(this).data('session-id');
        var detailsRow = $('#details-' + sessionId);
        
        if (detailsRow.is(':visible')) {
            detailsRow.hide();
            $(this).text('<?php _e('View Details', 'restaurant-pos'); ?>');
        } else {
            detailsRow.show();
            $(this).text('<?php _e('Hide Details', 'restaurant-pos'); ?>');
        }
    });
});
</script>
