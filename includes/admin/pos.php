<?php
/**
 * POS (Point of Sale) Cashier Screen
 */

if (!defined('ABSPATH')) {
    exit;
}

$categories = RPOS_Categories::get_all();
$currency = RPOS_Settings::get('currency_symbol', '$');
$restaurant_name = RPOS_Settings::get('restaurant_name', get_bloginfo('name'));
?>

<div class="wrap rpos-pos-screen">
    <div class="rpos-pos-container">
        <!-- Left Side: Product Grid -->
        <div class="rpos-pos-left">
            <div class="rpos-pos-header">
                <h2><?php echo esc_html__('Point of Sale', 'restaurant-pos'); ?></h2>
                <div class="rpos-pos-categories">
                    <button class="rpos-category-btn active" data-category="0">
                        <?php echo esc_html__('All', 'restaurant-pos'); ?>
                    </button>
                    <?php foreach ($categories as $category): ?>
                    <button class="rpos-category-btn" data-category="<?php echo esc_attr($category->id); ?>">
                        <?php echo esc_html($category->name); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="rpos-products-grid" id="rpos-products-grid">
                <div class="rpos-loading"><?php echo esc_html__('Loading products...', 'restaurant-pos'); ?></div>
            </div>
        </div>
        
        <!-- Right Side: Cart & Checkout -->
        <div class="rpos-pos-right">
            <div class="rpos-cart-header">
                <h3><?php echo esc_html__('Current Order', 'restaurant-pos'); ?></h3>
                <button class="button button-secondary" id="rpos-clear-cart">
                    <?php echo esc_html__('Clear', 'restaurant-pos'); ?>
                </button>
            </div>
            
            <div class="rpos-cart-items" id="rpos-cart-items">
                <div class="rpos-cart-empty">
                    <?php echo esc_html__('Cart is empty. Add products to start an order.', 'restaurant-pos'); ?>
                </div>
            </div>
            
            <div class="rpos-cart-totals">
                <div class="rpos-total-row">
                    <span><?php echo esc_html__('Subtotal:', 'restaurant-pos'); ?></span>
                    <span id="rpos-subtotal"><?php echo esc_html($currency); ?>0.00</span>
                </div>
                <div class="rpos-total-row">
                    <span><?php echo esc_html__('Discount:', 'restaurant-pos'); ?></span>
                    <input type="number" id="rpos-discount" step="0.01" min="0" value="0.00" placeholder="0.00">
                </div>
                <div class="rpos-total-row rpos-grand-total">
                    <span><?php echo esc_html__('Total:', 'restaurant-pos'); ?></span>
                    <span id="rpos-total"><?php echo esc_html($currency); ?>0.00</span>
                </div>
            </div>
            
            <div class="rpos-order-details">
                <h4><?php echo esc_html__('Order Details', 'restaurant-pos'); ?></h4>
                <div class="rpos-order-field">
                    <label><?php echo esc_html__('Order Type:', 'restaurant-pos'); ?> <span style="color:red;">*</span></label>
                    <select id="rpos-order-type" required>
                        <option value="dine-in"><?php echo esc_html__('Dine-in', 'restaurant-pos'); ?></option>
                        <option value="takeaway"><?php echo esc_html__('Takeaway', 'restaurant-pos'); ?></option>
                        <option value="delivery"><?php echo esc_html__('Delivery', 'restaurant-pos'); ?></option>
                    </select>
                </div>
                <div class="rpos-order-field">
                    <label><?php echo esc_html__('Special Instructions:', 'restaurant-pos'); ?></label>
                    <textarea id="rpos-special-instructions" rows="3" placeholder="<?php echo esc_attr__('e.g., No mayo, Extra spicy, Table 5, etc.', 'restaurant-pos'); ?>"></textarea>
                </div>
            </div>
            
            <div class="rpos-payment-section">
                <h4><?php echo esc_html__('Cash Payment', 'restaurant-pos'); ?></h4>
                <div class="rpos-payment-field">
                    <label><?php echo esc_html__('Cash Received:', 'restaurant-pos'); ?></label>
                    <input type="number" id="rpos-cash-received" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="rpos-payment-field">
                    <label><?php echo esc_html__('Change Due:', 'restaurant-pos'); ?></label>
                    <input type="text" id="rpos-change-due" readonly value="<?php echo esc_attr($currency); ?>0.00">
                </div>
            </div>
            
            <div class="rpos-checkout-actions">
                <button class="button button-primary button-large" id="rpos-complete-order">
                    <?php echo esc_html__('Complete Order', 'restaurant-pos'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div id="rpos-receipt-modal" class="rpos-modal" style="display:none;">
    <div class="rpos-modal-overlay"></div>
    <div class="rpos-modal-content rpos-receipt-content">
        <div id="rpos-receipt-print" class="rpos-receipt">
            <div class="rpos-receipt-header">
                <h2 id="receipt-restaurant-name"></h2>
                <p id="receipt-order-number"></p>
                <p id="receipt-date-time"></p>
            </div>
            
            <div class="rpos-receipt-items" id="receipt-items"></div>
            
            <div class="rpos-receipt-totals">
                <div class="rpos-receipt-row">
                    <span><?php echo esc_html__('Subtotal:', 'restaurant-pos'); ?></span>
                    <span id="receipt-subtotal"></span>
                </div>
                <div class="rpos-receipt-row">
                    <span><?php echo esc_html__('Discount:', 'restaurant-pos'); ?></span>
                    <span id="receipt-discount"></span>
                </div>
                <div class="rpos-receipt-row rpos-receipt-total">
                    <span><?php echo esc_html__('Total:', 'restaurant-pos'); ?></span>
                    <span id="receipt-total"></span>
                </div>
                <div class="rpos-receipt-row">
                    <span><?php echo esc_html__('Cash:', 'restaurant-pos'); ?></span>
                    <span id="receipt-cash"></span>
                </div>
                <div class="rpos-receipt-row">
                    <span><?php echo esc_html__('Change:', 'restaurant-pos'); ?></span>
                    <span id="receipt-change"></span>
                </div>
            </div>
            
            <div class="rpos-receipt-footer">
                <p><?php echo esc_html__('Thank you for your order!', 'restaurant-pos'); ?></p>
                <p id="receipt-cashier"></p>
            </div>
        </div>
        
        <div class="rpos-receipt-actions">
            <button class="button button-primary button-large" onclick="window.print();">
                <?php echo esc_html__('Print Receipt', 'restaurant-pos'); ?>
            </button>
            <button class="button button-large" id="rpos-new-order">
                <?php echo esc_html__('New Order', 'restaurant-pos'); ?>
            </button>
        </div>
    </div>
</div>

<script>
var rposData = {
    currency: '<?php echo esc_js($currency); ?>',
    restaurantName: '<?php echo esc_js($restaurant_name); ?>',
    restUrl: '<?php echo esc_js(rest_url('restaurant-pos/v1/')); ?>',
    nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    currentUser: '<?php echo esc_js(wp_get_current_user()->display_name); ?>'
};
</script>
