<?php
/**
 * ZAIKON POS - Kitchen Display System (KDS)
 * Bold typography, readable from distance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap zaikon-kds">
    <div class="zaikon-kds-header">
        <h1><?php echo esc_html__('Kitchen Display System', 'restaurant-pos'); ?></h1>
        <div class="zaikon-kds-controls">
            <button class="zaikon-kds-refresh-btn" id="rpos-kds-refresh">
                <span class="dashicons dashicons-update"></span>
                <?php echo esc_html__('Refresh', 'restaurant-pos'); ?>
            </button>
            <label class="zaikon-kds-auto-refresh-label">
                <input type="checkbox" id="rpos-kds-auto-refresh" checked>
                <?php echo esc_html__('Auto-refresh (30s)', 'restaurant-pos'); ?>
            </label>
        </div>
    </div>
    
    <div class="zaikon-kds-filters">
        <button class="zaikon-kds-filter-btn active rpos-kds-filter-btn" data-status="all">
            📋 <?php echo esc_html__('All Orders', 'restaurant-pos'); ?>
        </button>
        <button class="zaikon-kds-filter-btn rpos-kds-filter-btn" data-status="pending,confirmed,active">
            🆕 <?php echo esc_html__('New', 'restaurant-pos'); ?>
        </button>
        <button class="zaikon-kds-filter-btn rpos-kds-filter-btn" data-status="cooking">
            🍳 <?php echo esc_html__('Cooking', 'restaurant-pos'); ?>
        </button>
        <button class="zaikon-kds-filter-btn rpos-kds-filter-btn" data-status="ready">
            ✅ <?php echo esc_html__('Ready', 'restaurant-pos'); ?>
        </button>
    </div>
    
    <div class="zaikon-kds-grid" id="rpos-kds-grid">
        <div class="zaikon-kds-loading">
            <div class="zaikon-spinner zaikon-spinner-lg"></div>
            <p><?php echo esc_html__('Loading orders...', 'restaurant-pos'); ?></p>
        </div>
    </div>
    
    <div class="zaikon-kds-empty" id="rpos-kds-empty" style="display:none;">
        <p><?php echo esc_html__('No orders to display.', 'restaurant-pos'); ?></p>
    </div>
</div>

<script>
var rposKdsData = {
    restUrl: '<?php echo esc_js(rest_url('restaurant-pos/v1/')); ?>',
    nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    timezoneOffset: <?php echo RPOS_Timezone::get_offset_minutes(); ?>,
    timezoneString: '<?php echo esc_js(RPOS_Timezone::get_timezone_string()); ?>',
    translations: {
        new: '<?php echo esc_js(__('New', 'restaurant-pos')); ?>',
        cooking: '<?php echo esc_js(__('Cooking', 'restaurant-pos')); ?>',
        ready: '<?php echo esc_js(__('Ready', 'restaurant-pos')); ?>',
        completed: '<?php echo esc_js(__('Completed', 'restaurant-pos')); ?>',
        orderNumber: '<?php echo esc_js(__('Order #', 'restaurant-pos')); ?>',
        elapsed: '<?php echo esc_js(__('Elapsed', 'restaurant-pos')); ?>',
        items: '<?php echo esc_js(__('Items', 'restaurant-pos')); ?>',
        startCooking: '<?php echo esc_js(__('🔥 Start Cooking', 'restaurant-pos')); ?>',
        markReady: '<?php echo esc_js(__('✅ Mark Ready', 'restaurant-pos')); ?>',
        complete: '<?php echo esc_js(__('✔ Complete', 'restaurant-pos')); ?>',
        minutes: '<?php echo esc_js(__('min', 'restaurant-pos')); ?>'
    }
};
</script>
