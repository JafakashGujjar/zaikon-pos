<?php
/**
 * Rider Deliveries Screen
 * Shows delivery orders assigned to the logged-in rider
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current user
$current_user = wp_get_current_user();
$rider_id = $current_user->ID;

// Get today's date range
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

// Get pending orders (includes today's and any undelivered)
$orders = RPOS_Riders::get_pending_orders($rider_id);

// Get today's stats
$stats = RPOS_Riders::get_rider_stats($rider_id, $today_start, $today_end);
?>

<div class="wrap rpos-rider-deliveries">
    <h1><?php echo esc_html__('My Deliveries', 'restaurant-pos'); ?></h1>
    
    <!-- Stats Cards -->
    <div class="rpos-stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="rpos-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Today', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($stats['total_deliveries']); ?></p>
        </div>
        <div class="rpos-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Delivered', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html($stats['delivered']); ?></p>
        </div>
        <div class="rpos-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Pending', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #dba617;"><?php echo esc_html($stats['pending']); ?></p>
        </div>
        <div class="rpos-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total KM', 'restaurant-pos'); ?></h3>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html(number_format($stats['total_km'], 2)); ?></p>
        </div>
    </div>
    
    <!-- Orders List -->
    <div class="rpos-deliveries-list" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2><?php echo esc_html__('Pending Deliveries', 'restaurant-pos'); ?></h2>
        
        <?php if (empty($orders)): ?>
            <p style="text-align: center; padding: 40px; color: #666;">
                <?php echo esc_html__('No pending deliveries at the moment.', 'restaurant-pos'); ?>
            </p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Order #', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Time', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Customer', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Phone', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Area', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Special Instructions', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Kitchen Status', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Status', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('KM Travelled', 'restaurant-pos'); ?></th>
                        <th><?php echo esc_html__('Actions', 'restaurant-pos'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr data-order-id="<?php echo esc_attr($order->id); ?>">
                            <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                            <td><?php echo esc_html(date('H:i', strtotime($order->created_at))); ?></td>
                            <td><?php echo esc_html($order->customer_name); ?></td>
                            <td><?php echo esc_html($order->customer_phone); ?></td>
                            <td>
                                <?php echo esc_html($order->area_name); ?>
                                <?php if ($order->area_distance): ?>
                                    <br><small>(<?php echo esc_html($order->area_distance); ?> km)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order->special_instructions): ?>
                                    <em><?php echo esc_html($order->special_instructions); ?></em>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order->kitchen_late): ?>
                                    <span style="color: #d63638; font-weight: bold;">⚠ Late from Kitchen</span>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✓ On Time</span>
                                <?php endif; ?>
                                <?php if ($order->ready_at): ?>
                                    <br><small>Ready: <?php echo esc_html(date('H:i', strtotime($order->ready_at))); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="rpos-delivery-status" data-status="<?php echo esc_attr($order->delivery_status ?: 'pending'); ?>">
                                    <?php
                                    switch ($order->delivery_status) {
                                        case 'out_for_delivery':
                                            echo '<span style="color: #dba617;">🚚 Out for Delivery</span>';
                                            break;
                                        case 'delivered':
                                            echo '<span style="color: #00a32a;">✓ Delivered</span>';
                                            break;
                                        default:
                                            echo '<span style="color: #999;">Pending</span>';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <input type="number" 
                                       class="rpos-delivery-km-input" 
                                       value="<?php echo esc_attr($order->delivery_km ?: ''); ?>" 
                                       step="0.1" 
                                       min="0" 
                                       placeholder="0.0"
                                       style="width: 80px;"
                                       data-order-id="<?php echo esc_attr($order->id); ?>"
                                       <?php echo $order->delivery_status === 'delivered' ? 'readonly' : ''; ?>>
                                km
                            </td>
                            <td>
                                <?php if ($order->delivery_status !== 'delivered'): ?>
                                    <?php if ($order->delivery_status === 'out_for_delivery'): ?>
                                        <button class="button button-primary rpos-mark-delivered" 
                                                data-order-id="<?php echo esc_attr($order->id); ?>">
                                            <?php echo esc_html__('Mark Delivered', 'restaurant-pos'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="button button-primary rpos-mark-out-for-delivery" 
                                                data-order-id="<?php echo esc_attr($order->id); ?>">
                                            <?php echo esc_html__('Out for Delivery', 'restaurant-pos'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✓ Complete</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Mark as out for delivery
    $('.rpos-mark-out-for-delivery').on('click', function() {
        var button = $(this);
        var orderId = button.data('order-id');
        
        if (!confirm('Mark this order as out for delivery?')) {
            return;
        }
        
        button.prop('disabled', true);
        
        $.ajax({
            url: '<?php echo esc_url(rest_url('rpos/v1/riders/update-status')); ?>',
            method: 'POST',
            data: JSON.stringify({
                order_id: orderId,
                status: 'out_for_delivery'
            }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function() {
                location.reload();
            },
            error: function() {
                alert('Failed to update status. Please try again.');
                button.prop('disabled', false);
            }
        });
    });
    
    // Mark as delivered
    $('.rpos-mark-delivered').on('click', function() {
        var button = $(this);
        var orderId = button.data('order-id');
        var row = button.closest('tr');
        var kmInput = row.find('.rpos-delivery-km-input');
        var km = parseFloat(kmInput.val()) || 0;
        
        if (km === 0) {
            alert('Please enter the distance travelled (km) before marking as delivered.');
            kmInput.focus();
            return;
        }
        
        if (!confirm('Mark this order as delivered?')) {
            return;
        }
        
        button.prop('disabled', true);
        
        // First update km
        $.ajax({
            url: '<?php echo esc_url(rest_url('rpos/v1/riders/update-km')); ?>',
            method: 'POST',
            data: JSON.stringify({
                order_id: orderId,
                km: km
            }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function() {
                // Then update status
                $.ajax({
                    url: '<?php echo esc_url(rest_url('rpos/v1/riders/update-status')); ?>',
                    method: 'POST',
                    data: JSON.stringify({
                        order_id: orderId,
                        status: 'delivered'
                    }),
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                    },
                    success: function() {
                        location.reload();
                    },
                    error: function() {
                        alert('Failed to mark as delivered. Please try again.');
                        button.prop('disabled', false);
                    }
                });
            },
            error: function() {
                alert('Failed to update distance. Please try again.');
                button.prop('disabled', false);
            }
        });
    });
    
    // Auto-save km on change
    $('.rpos-delivery-km-input').on('change', function() {
        var input = $(this);
        var orderId = input.data('order-id');
        var km = parseFloat(input.val()) || 0;
        
        if (km > 0) {
            $.ajax({
                url: '<?php echo esc_url(rest_url('rpos/v1/riders/update-km')); ?>',
                method: 'POST',
                data: JSON.stringify({
                    order_id: orderId,
                    km: km
                }),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                },
                success: function() {
                    input.css('background-color', '#d4edda');
                    setTimeout(function() {
                        input.css('background-color', '');
                    }, 1000);
                },
                error: function() {
                    input.css('background-color', '#f8d7da');
                }
            });
        }
    });
});
</script>
