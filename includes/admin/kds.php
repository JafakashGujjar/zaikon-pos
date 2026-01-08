<?php
/**
 * Kitchen Display System (KDS)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap rpos-kds">
    <div class="rpos-kds-header">
        <h1><?php echo esc_html__('Kitchen Display System', 'restaurant-pos'); ?></h1>
        <div class="rpos-kds-controls">
            <button class="button" id="rpos-kds-refresh">
                <span class="dashicons dashicons-update"></span>
                <?php echo esc_html__('Refresh', 'restaurant-pos'); ?>
            </button>
            <label>
                <input type="checkbox" id="rpos-kds-auto-refresh" checked>
                <?php echo esc_html__('Auto-refresh (30s)', 'restaurant-pos'); ?>
            </label>
        </div>
    </div>
    
    <div class="rpos-kds-filters">
        <button class="rpos-kds-filter-btn active" data-status="all">
            <?php echo esc_html__('All Orders', 'restaurant-pos'); ?>
        </button>
        <button class="rpos-kds-filter-btn" data-status="new">
            <?php echo esc_html__('New', 'restaurant-pos'); ?>
        </button>
        <button class="rpos-kds-filter-btn" data-status="cooking">
            <?php echo esc_html__('Cooking', 'restaurant-pos'); ?>
        </button>
        <button class="rpos-kds-filter-btn" data-status="ready">
            <?php echo esc_html__('Ready', 'restaurant-pos'); ?>
        </button>
    </div>
    
    <div class="rpos-kds-grid" id="rpos-kds-grid">
        <div class="rpos-kds-loading">
            <?php echo esc_html__('Loading orders...', 'restaurant-pos'); ?>
        </div>
    </div>
    
    <div class="rpos-kds-empty" id="rpos-kds-empty" style="display:none;">
        <p><?php echo esc_html__('No orders to display.', 'restaurant-pos'); ?></p>
    </div>
</div>

<script>
var rposKdsData = {
    restUrl: '<?php echo esc_js(rest_url('restaurant-pos/v1/')); ?>',
    nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    translations: {
        new: '<?php echo esc_js(__('New', 'restaurant-pos')); ?>',
        cooking: '<?php echo esc_js(__('Cooking', 'restaurant-pos')); ?>',
        ready: '<?php echo esc_js(__('Ready', 'restaurant-pos')); ?>',
        completed: '<?php echo esc_js(__('Completed', 'restaurant-pos')); ?>',
        orderNumber: '<?php echo esc_js(__('Order #', 'restaurant-pos')); ?>',
        elapsed: '<?php echo esc_js(__('Elapsed', 'restaurant-pos')); ?>',
        items: '<?php echo esc_js(__('Items', 'restaurant-pos')); ?>',
        startCooking: '<?php echo esc_js(__('Start Cooking', 'restaurant-pos')); ?>',
        markReady: '<?php echo esc_js(__('Mark Ready', 'restaurant-pos')); ?>',
        complete: '<?php echo esc_js(__('Complete', 'restaurant-pos')); ?>',
        minutes: '<?php echo esc_js(__('min', 'restaurant-pos')); ?>'
    }
};
</script>
