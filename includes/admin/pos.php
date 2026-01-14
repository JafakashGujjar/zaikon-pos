<?php
/**
 * ZAIKON POS - Point of Sale Cashier Screen
 * Modern, touch-friendly interface
 */

if (!defined('ABSPATH')) {
    exit;
}

$categories = RPOS_Categories::get_all();
$currency = RPOS_Settings::get('currency_symbol', '$');
$restaurant_name = RPOS_Settings::get('restaurant_name', get_bloginfo('name'));
?>

<div class="wrap zaikon-pos-screen">
    <div class="zaikon-pos-container">
        <!-- Left Side: Product Grid -->
        <div class="zaikon-pos-left">
            <div class="zaikon-pos-header">
                <div class="zaikon-pos-header-top">
                    <h2><?php echo esc_html($restaurant_name); ?> <span style="color: var(--zaikon-yellow);">POS</span></h2>
                    
                    <!-- Notification Bell -->
                    <div class="zaikon-notification-bell" id="rpos-notification-bell">
                        <span class="dashicons dashicons-bell"></span>
                        <span class="zaikon-notification-badge" id="rpos-notification-badge" style="display: none;">0</span>
                    </div>
                </div>
                
                <input type="search" class="zaikon-pos-search" placeholder="<?php echo esc_attr__('Search products...', 'restaurant-pos'); ?>" id="zaikon-product-search">
                
                <div class="zaikon-pos-categories">
                    <button class="zaikon-category-btn active rpos-category-btn" data-category="0">
                        <span class="dashicons dashicons-menu"></span>
                        <?php echo esc_html__('All', 'restaurant-pos'); ?>
                    </button>
                    <?php foreach ($categories as $category): ?>
                    <button class="zaikon-category-btn rpos-category-btn" data-category="<?php echo esc_attr($category->id); ?>">
                        <span class="dashicons dashicons-category"></span>
                        <?php echo esc_html($category->name); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="zaikon-products-grid" id="rpos-products-grid">
                <div class="zaikon-loading">
                    <div class="zaikon-spinner"></div>
                    <p><?php echo esc_html__('Loading products...', 'restaurant-pos'); ?></p>
                </div>
            </div>
            
            <!-- Inline Delivery Panel -->
            <div id="zaikon-delivery-panel" class="zaikon-delivery-panel" style="display:none;">
                <div class="zaikon-delivery-panel-header">
                    <h4><?php echo esc_html__('Delivery Details', 'restaurant-pos'); ?></h4>
                </div>
                <div class="zaikon-delivery-panel-body">
                    <div class="zaikon-delivery-fields-grid zaikon-delivery-compact">
                        <!-- Row 1: Customer Name | Customer Phone | Delivery Area (3 columns) -->
                        <div class="zaikon-delivery-field">
                            <label><?php echo esc_html__('Customer Name', 'restaurant-pos'); ?> <span class="required">*</span></label>
                            <input type="text" id="zaikon-delivery-name" placeholder="<?php echo esc_attr__('Enter customer name', 'restaurant-pos'); ?>" required>
                        </div>
                        <div class="zaikon-delivery-field">
                            <label><?php echo esc_html__('Customer Phone', 'restaurant-pos'); ?> <span class="required">*</span></label>
                            <input type="tel" id="zaikon-delivery-phone" placeholder="<?php echo esc_attr__('Enter phone number', 'restaurant-pos'); ?>" required>
                        </div>
                        <div class="zaikon-delivery-field">
                            <label><?php echo esc_html__('Delivery Area', 'restaurant-pos'); ?> <span class="required">*</span></label>
                            <select id="zaikon-delivery-area">
                                <option value=""><?php echo esc_html__('-- Select Area --', 'restaurant-pos'); ?></option>
                            </select>
                        </div>
                        
                        <!-- Row 2: Distance (KM) | Delivery Charge (Rs) | Assign Rider (3 columns) -->
                        <div class="zaikon-delivery-field">
                            <label><?php echo esc_html__('Distance (KM)', 'restaurant-pos'); ?></label>
                            <input type="text" id="zaikon-delivery-distance" readonly>
                        </div>
                        <div class="zaikon-delivery-field">
                            <label><?php echo esc_html__('Delivery Charge (Rs)', 'restaurant-pos'); ?></label>
                            <input type="text" id="zaikon-delivery-charge" readonly>
                            <span id="zaikon-free-delivery-badge" class="zaikon-free-badge" style="display:none;"><?php echo esc_html__('FREE', 'restaurant-pos'); ?></span>
                        </div>
                        <div class="zaikon-delivery-field">
                            <label><?php echo esc_html__('Assign Rider', 'restaurant-pos'); ?></label>
                            <select id="zaikon-delivery-rider">
                                <option value=""><?php echo esc_html__('-- Select Rider --', 'restaurant-pos'); ?></option>
                            </select>
                        </div>
                        
                        <!-- Row 3: Delivery Instructions (full width, single-line) -->
                        <div class="zaikon-delivery-field full-width">
                            <label><?php echo esc_html__('Delivery Instructions', 'restaurant-pos'); ?></label>
                            <input type="text" id="zaikon-delivery-instructions" placeholder="<?php echo esc_attr__('e.g., Near mosque, Call on arrival', 'restaurant-pos'); ?>">
                        </div>
                    </div>
                    
                    <div class="zaikon-delivery-actions">
                        <button type="button" class="zaikon-btn zaikon-btn-primary" id="zaikon-save-delivery"><?php echo esc_html__('Save Delivery Details', 'restaurant-pos'); ?></button>
                        <button type="button" class="zaikon-btn zaikon-btn-secondary" id="zaikon-cancel-delivery"><?php echo esc_html__('Cancel Delivery', 'restaurant-pos'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Side: Cart & Checkout -->
        <div class="zaikon-pos-right">
            <div class="zaikon-cart-header">
                <h3><?php echo esc_html__('Current Order', 'restaurant-pos'); ?></h3>
                <button class="zaikon-clear-cart-btn" id="rpos-clear-cart">
                    <?php echo esc_html__('Clear', 'restaurant-pos'); ?>
                </button>
            </div>
            
            <div class="zaikon-cart-items" id="rpos-cart-items">
                <div class="zaikon-cart-empty">
                    <?php echo esc_html__('Cart is empty. Add products to start an order.', 'restaurant-pos'); ?>
                </div>
            </div>
            
            <div class="zaikon-cart-totals">
                <div class="zaikon-total-row">
                    <span><?php echo esc_html__('Subtotal:', 'restaurant-pos'); ?></span>
                    <span id="rpos-subtotal"><?php echo esc_html($currency); ?>0.00</span>
                </div>
                <div class="zaikon-total-row" id="rpos-delivery-charge-row" style="display: none;">
                    <span><?php echo esc_html__('Delivery Charge:', 'restaurant-pos'); ?></span>
                    <span id="rpos-delivery-charge-display"><?php echo esc_html($currency); ?>0.00</span>
                </div>
                <div class="zaikon-total-row">
                    <span><?php echo esc_html__('Discount:', 'restaurant-pos'); ?></span>
                    <input type="number" id="rpos-discount" step="0.01" min="0" value="0.00" placeholder="0.00">
                </div>
                <div class="zaikon-total-row zaikon-grand-total">
                    <span><?php echo esc_html__('Total:', 'restaurant-pos'); ?></span>
                    <span id="rpos-total"><?php echo esc_html($currency); ?>0.00</span>
                </div>
            </div>
            
            <div class="zaikon-order-details">
                <h4><?php echo esc_html__('Order Details', 'restaurant-pos'); ?></h4>
                <div class="zaikon-order-field">
                    <label><?php echo esc_html__('Order Type:', 'restaurant-pos'); ?> <span style="color: var(--zaikon-red);">*</span></label>
                    <div class="zaikon-order-type-pills">
                        <button type="button" class="zaikon-order-type-pill active" data-order-type="dine-in">
                            <?php echo esc_html__('Dine-in', 'restaurant-pos'); ?>
                        </button>
                        <button type="button" class="zaikon-order-type-pill" data-order-type="takeaway">
                            <?php echo esc_html__('Takeaway', 'restaurant-pos'); ?>
                        </button>
                        <button type="button" class="zaikon-order-type-pill" data-order-type="delivery">
                            <?php echo esc_html__('Delivery', 'restaurant-pos'); ?>
                        </button>
                    </div>
                    <input type="hidden" id="rpos-order-type" value="dine-in">
                </div>
                <div class="zaikon-order-field">
                    <label><?php echo esc_html__('Special Instructions:', 'restaurant-pos'); ?></label>
                    <textarea id="rpos-special-instructions" rows="2" placeholder="<?php echo esc_attr__('e.g., No mayo, Extra spicy, Table 5, etc.', 'restaurant-pos'); ?>"></textarea>
                </div>
            </div>
            
            <div class="zaikon-payment-section">
                <h4><?php echo esc_html__('Cash Payment', 'restaurant-pos'); ?></h4>
                <div class="zaikon-payment-fields-grid">
                    <div class="zaikon-payment-field">
                        <label><?php echo esc_html__('Cash Received:', 'restaurant-pos'); ?></label>
                        <input type="number" id="rpos-cash-received" step="0.01" min="0" placeholder="0.00" inputmode="numeric">
                    </div>
                    <div class="zaikon-payment-field zaikon-change-due-field">
                        <label><?php echo esc_html__('Change Due:', 'restaurant-pos'); ?></label>
                        <div id="rpos-change-due" class="zaikon-change-due-display"><?php echo esc_html($currency); ?>0.00</div>
                    </div>
                </div>
            </div>
            
            <div class="zaikon-checkout-actions">
                <button class="zaikon-complete-order-btn" id="rpos-complete-order">
                    <?php echo esc_html__('Complete Order', 'restaurant-pos'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div id="rpos-receipt-modal" class="zaikon-receipt-modal">
    <div class="zaikon-receipt zaikon-animate-scaleIn">
        <div class="zaikon-receipt-header">
            <h2 id="receipt-restaurant-name"></h2>
            <p id="receipt-restaurant-phone" style="font-size: var(--text-sm); color: var(--zaikon-dark-secondary);"></p>
            <p id="receipt-restaurant-address" style="font-size: var(--text-sm); color: var(--zaikon-dark-secondary); white-space: pre-line;"></p>
            <p id="receipt-order-number" style="font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--zaikon-dark); margin-top: var(--space-3);"></p>
            <p id="receipt-order-type" style="color: var(--zaikon-dark-secondary); font-weight: var(--font-semibold);"></p>
            <p id="receipt-date-time" style="color: var(--zaikon-dark-secondary);"></p>
            <p id="receipt-special-instructions" style="color: var(--zaikon-dark-secondary); font-style: italic; margin-top: var(--space-2);"></p>
        </div>
        
        <div class="zaikon-receipt-body">
            <div id="receipt-items" style="margin-bottom: var(--space-6);"></div>
            
            <div style="padding-top: var(--space-4); border-top: 2px dashed var(--zaikon-gray-medium);">
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                    <span><?php echo esc_html__('Subtotal:', 'restaurant-pos'); ?></span>
                    <span id="receipt-subtotal" style="font-weight: var(--font-semibold);"></span>
                </div>
                <div id="receipt-delivery-charge-row" style="display: none; justify-content: space-between; margin-bottom: var(--space-2);">
                    <span id="receipt-delivery-charge-label"><?php echo esc_html__('Delivery Charge:', 'restaurant-pos'); ?></span>
                    <span id="receipt-delivery-charge" style="font-weight: var(--font-semibold);"></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                    <span><?php echo esc_html__('Discount:', 'restaurant-pos'); ?></span>
                    <span id="receipt-discount" style="font-weight: var(--font-semibold);"></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-4); padding-top: var(--space-2); border-top: 1px solid var(--zaikon-gray-light); font-size: var(--text-xl);">
                    <strong><?php echo esc_html__('Total:', 'restaurant-pos'); ?></strong>
                    <strong id="receipt-total" style="color: var(--zaikon-orange);"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-2);">
                    <span><?php echo esc_html__('Cash:', 'restaurant-pos'); ?></span>
                    <span id="receipt-cash" style="font-weight: var(--font-semibold);"></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span><?php echo esc_html__('Change:', 'restaurant-pos'); ?></span>
                    <span id="receipt-change" style="font-weight: var(--font-semibold);"></span>
                </div>
            </div>
            
            <div style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 2px dashed var(--zaikon-gray-medium); text-align: center;">
                <p id="receipt-footer-message" style="font-weight: var(--font-bold); color: var(--zaikon-orange); margin-bottom: var(--space-2);"></p>
                <p id="receipt-cashier" style="font-size: var(--text-sm); color: var(--zaikon-gray-dark);"></p>
            </div>
        </div>
        
        <div class="zaikon-receipt-footer">
            <button class="zaikon-btn zaikon-btn-primary zaikon-btn-lg" onclick="window.print();">
                <?php echo esc_html__('Print Receipt', 'restaurant-pos'); ?>
            </button>
            <button class="zaikon-btn zaikon-btn-secondary zaikon-btn-lg" id="rpos-print-rider-slip" style="display: none;">
                <?php echo esc_html__('Print Rider Slip', 'restaurant-pos'); ?>
            </button>
            <button class="zaikon-btn zaikon-btn-yellow zaikon-btn-lg" id="rpos-new-order">
                <?php echo esc_html__('New Order', 'restaurant-pos'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Notification Dropdown -->
<div id="rpos-notification-dropdown" class="zaikon-notification-dropdown" style="display: none;">
    <div class="zaikon-notification-header">
        <h3><?php echo esc_html__('Notifications', 'restaurant-pos'); ?></h3>
        <button class="zaikon-btn-link" id="rpos-mark-all-read">
            <?php echo esc_html__('Mark all as read', 'restaurant-pos'); ?>
        </button>
    </div>
    <div class="zaikon-notification-list" id="rpos-notification-list">
        <div class="zaikon-notification-empty">
            <?php echo esc_html__('No new notifications', 'restaurant-pos'); ?>
        </div>
    </div>
</div>

<script>
var rposData = {
    currency: '<?php echo esc_js($currency); ?>',
    restaurantName: '<?php echo esc_js($restaurant_name); ?>',
    restaurantPhone: '<?php echo esc_js(RPOS_Settings::get('restaurant_phone', '')); ?>',
    restaurantAddress: '<?php echo esc_js(RPOS_Settings::get('restaurant_address', '')); ?>',
    receiptFooterMessage: '<?php echo esc_js(RPOS_Settings::get('receipt_footer_message', 'Thank you for your order!')); ?>',
    restUrl: '<?php echo esc_js(rest_url('restaurant-pos/v1/')); ?>',
    zaikonRestUrl: '<?php echo esc_js(rest_url('zaikon/v1/')); ?>',
    nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    currentUser: '<?php echo esc_js(wp_get_current_user()->display_name); ?>'
};
</script>
