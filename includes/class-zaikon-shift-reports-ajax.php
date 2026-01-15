<?php
/**
 * Shift Reports AJAX Handler
 * Handles AJAX requests for shift reports functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Zaikon_Shift_Reports_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_get_shift_details', array($this, 'ajax_get_shift_details'));
        add_action('wp_ajax_reopen_shift', array($this, 'ajax_reopen_shift'));
    }
    
    /**
     * Get shift details via AJAX
     */
    public function ajax_get_shift_details() {
        check_ajax_referer('shift_details_nonce', 'nonce');
        
        if (!current_user_can('rpos_view_reports')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $shift_id = isset($_POST['shift_id']) ? absint($_POST['shift_id']) : 0;
        
        if (!$shift_id) {
            wp_send_json_error(array('message' => 'Invalid shift ID'));
        }
        
        global $wpdb;
        $shift = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, u.display_name as cashier_name 
             FROM {$wpdb->prefix}zaikon_cashier_sessions s
             LEFT JOIN {$wpdb->users} u ON s.cashier_id = u.ID
             WHERE s.id = %d",
            $shift_id
        ));
        
        if (!$shift) {
            wp_send_json_error(array('message' => 'Shift not found'));
        }
        
        // Calculate totals if not already stored
        if ($shift->status === 'closed' && !$shift->total_cash_sales_rs) {
            $totals = Zaikon_Cashier_Sessions::calculate_session_totals($shift_id);
            if ($totals) {
                $shift->total_cash_sales_rs = $totals['cash_sales'];
                $shift->total_cod_collected_rs = $totals['cod_collected'];
                $shift->total_expenses_rs = $totals['expenses'];
            }
        }
        
        // Get orders for this shift
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT order_number, order_type, payment_type, payment_status, grand_total_rs, created_at
             FROM {$wpdb->prefix}zaikon_orders
             WHERE cashier_id = %d
             AND created_at >= %s
             AND created_at <= %s
             ORDER BY created_at DESC",
            $shift->cashier_id,
            $shift->session_start,
            $shift->session_end ?? current_time('mysql')
        ));
        
        // Get expenses for this shift
        $expenses = $wpdb->get_results($wpdb->prepare(
            "SELECT amount_rs, category, description, expense_date
             FROM {$wpdb->prefix}zaikon_expenses
             WHERE session_id = %d
             ORDER BY expense_date DESC",
            $shift_id
        ));
        
        $currency = RPOS_Settings::get('currency_symbol', 'Rs');
        
        // Build HTML
        ob_start();
        ?>
        <div class="shift-details">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                <div>
                    <h4 style="margin-bottom: 15px; color: #23282d; border-bottom: 2px solid #FFD700; padding-bottom: 8px;">
                        <?php echo esc_html__('Shift Information', 'restaurant-pos'); ?>
                    </h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Shift ID:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0;">#<?php echo esc_html($shift->id); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Cashier:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0;"><?php echo esc_html($shift->cashier_name); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Start Time:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0;"><?php echo esc_html(date('Y-m-d H:i:s', strtotime($shift->session_start))); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('End Time:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0;">
                                <?php echo $shift->session_end ? esc_html(date('Y-m-d H:i:s', strtotime($shift->session_end))) : '<span style="color: #46b450;">Active</span>'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Status:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0;">
                                <span style="padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 12px; 
                                    <?php echo $shift->status === 'open' ? 'background: #d4edda; color: #155724;' : 'background: #d6d8db; color: #383d41;'; ?>">
                                    <?php echo esc_html(strtoupper($shift->status)); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 15px; color: #23282d; border-bottom: 2px solid #FFD700; padding-bottom: 8px;">
                        <?php echo esc_html__('Cash Summary', 'restaurant-pos'); ?>
                    </h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Opening Cash:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0; text-align: right;"><?php echo esc_html($currency . number_format($shift->opening_cash_rs, 2)); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Cash Sales:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0; text-align: right; color: #46b450;"><?php echo esc_html($currency . number_format($shift->total_cash_sales_rs, 2)); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('COD Collected:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0; text-align: right; color: #46b450;"><?php echo esc_html($currency . number_format($shift->total_cod_collected_rs, 2)); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Expenses:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0; text-align: right; color: #dc3232;"><?php echo esc_html($currency . number_format($shift->total_expenses_rs, 2)); ?></td>
                        </tr>
                        <tr style="border-top: 2px solid #ddd;">
                            <td style="padding: 12px 0 8px 0; font-weight: 700; font-size: 16px;"><?php echo esc_html__('Expected Cash:', 'restaurant-pos'); ?></td>
                            <td style="padding: 12px 0 8px 0; text-align: right; font-weight: 700; font-size: 16px;">
                                <?php echo esc_html($currency . number_format($shift->expected_cash_rs, 2)); ?>
                            </td>
                        </tr>
                        <?php if ($shift->status === 'closed'): ?>
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;"><?php echo esc_html__('Actual Cash:', 'restaurant-pos'); ?></td>
                            <td style="padding: 8px 0; text-align: right;"><?php echo esc_html($currency . number_format($shift->closing_cash_rs, 2)); ?></td>
                        </tr>
                        <tr style="border-top: 2px solid #ddd;">
                            <td style="padding: 12px 0 8px 0; font-weight: 700; font-size: 16px;"><?php echo esc_html__('Variance:', 'restaurant-pos'); ?></td>
                            <td style="padding: 12px 0 8px 0; text-align: right; font-weight: 700; font-size: 16px; 
                                <?php 
                                $variance = floatval($shift->cash_difference_rs);
                                if ($variance > 0) echo 'color: #46b450;';
                                elseif ($variance < 0) echo 'color: #dc3232;';
                                else echo 'color: #0073aa;';
                                ?>">
                                <?php 
                                $symbol = $variance > 0 ? '+' : '';
                                echo esc_html($symbol . $currency . number_format($variance, 2)); 
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($orders)): ?>
            <div style="margin-bottom: 30px;">
                <h4 style="margin-bottom: 15px; color: #23282d; border-bottom: 2px solid #FFD700; padding-bottom: 8px;">
                    <?php echo esc_html__('Orders (' . count($orders) . ')', 'restaurant-pos'); ?>
                </h4>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Order #', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Type', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Payment', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Amount', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Time', 'restaurant-pos'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo esc_html($order->order_number); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->order_type))); ?></td>
                                <td><?php echo esc_html(strtoupper($order->payment_type)); ?></td>
                                <td><?php echo esc_html(ucfirst($order->payment_status)); ?></td>
                                <td><?php echo esc_html($currency . number_format($order->grand_total_rs, 2)); ?></td>
                                <td><?php echo esc_html(date('H:i', strtotime($order->created_at))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($expenses)): ?>
            <div>
                <h4 style="margin-bottom: 15px; color: #23282d; border-bottom: 2px solid #FFD700; padding-bottom: 8px;">
                    <?php echo esc_html__('Expenses (' . count($expenses) . ')', 'restaurant-pos'); ?>
                </h4>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Category', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Description', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Amount', 'restaurant-pos'); ?></th>
                                <th><?php echo esc_html__('Time', 'restaurant-pos'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $expense->category))); ?></td>
                                <td><?php echo esc_html($expense->description ?: '-'); ?></td>
                                <td style="color: #dc3232; font-weight: 600;"><?php echo esc_html($currency . number_format($expense->amount_rs, 2)); ?></td>
                                <td><?php echo esc_html(date('H:i', strtotime($expense->expense_date))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($shift->notes): ?>
            <div style="margin-top: 30px; padding: 15px; background: #f7f7f7; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0;"><?php echo esc_html__('Notes:', 'restaurant-pos'); ?></h4>
                <p style="margin: 0;"><?php echo esc_html($shift->notes); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Re-open shift via AJAX (admin only)
     */
    public function ajax_reopen_shift() {
        check_ajax_referer('reopen_shift_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized - Admin access required'));
        }
        
        $shift_id = isset($_POST['shift_id']) ? absint($_POST['shift_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
        
        if (!$shift_id) {
            wp_send_json_error(array('message' => 'Invalid shift ID'));
        }
        
        if (empty($reason)) {
            wp_send_json_error(array('message' => 'Reason is required'));
        }
        
        global $wpdb;
        
        // Get current shift
        $shift = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaikon_cashier_sessions WHERE id = %d",
            $shift_id
        ));
        
        if (!$shift) {
            wp_send_json_error(array('message' => 'Shift not found'));
        }
        
        if ($shift->status !== 'closed') {
            wp_send_json_error(array('message' => 'Shift is not closed'));
        }
        
        // Re-open the shift
        $result = $wpdb->update(
            $wpdb->prefix . 'zaikon_cashier_sessions',
            array(
                'status' => 'open',
                'session_end' => null,
                'closing_cash_rs' => null,
                'cash_difference_rs' => null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $shift_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Failed to re-open shift'));
        }
        
        // Log the admin action
        $admin_user = wp_get_current_user();
        $log_note = sprintf(
            'Shift re-opened by admin: %s (ID: %d) at %s. Reason: %s',
            $admin_user->display_name,
            $admin_user->ID,
            current_time('mysql'),
            $reason
        );
        
        // Append to notes
        $current_notes = $shift->notes ? $shift->notes . "\n\n" : '';
        $wpdb->update(
            $wpdb->prefix . 'zaikon_cashier_sessions',
            array('notes' => $current_notes . $log_note),
            array('id' => $shift_id),
            array('%s'),
            array('%d')
        );
        
        wp_send_json_success(array('message' => 'Shift re-opened successfully'));
    }
}

// Initialize
new Zaikon_Shift_Reports_Ajax();
