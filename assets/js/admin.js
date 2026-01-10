/**
 * ZAIKON POS - Admin JavaScript
 * Enhanced with toast notifications, animations, and micro-interactions
 */

(function($) {
    'use strict';
    
    // Toast Notification System
    var ZAIKON_Toast = {
        container: null,
        
        init: function() {
            if (!this.container) {
                this.container = $('<div class="zaikon-toast-container"></div>');
                $('body').append(this.container);
            }
        },
        
        show: function(message, type, duration) {
            this.init();
            type = type || 'info';
            duration = duration || 3000;
            
            var icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            var toast = $('<div class="zaikon-toast zaikon-toast-' + type + ' zaikon-animate-slideDown">');
            toast.append('<div class="zaikon-toast-icon">' + icons[type] + '</div>');
            
            var content = $('<div class="zaikon-toast-content">');
            if (typeof message === 'object' && message.title) {
                content.append('<div class="zaikon-toast-title">' + message.title + '</div>');
                if (message.message) {
                    content.append('<div class="zaikon-toast-message">' + message.message + '</div>');
                }
            } else {
                content.append('<div class="zaikon-toast-message">' + message + '</div>');
            }
            toast.append(content);
            
            var closeBtn = $('<button class="zaikon-toast-close">&times;</button>');
            closeBtn.on('click', function() {
                toast.fadeOut(200, function() { toast.remove(); });
            });
            toast.append(closeBtn);
            
            this.container.append(toast);
            
            if (duration > 0) {
                setTimeout(function() {
                    toast.fadeOut(200, function() { toast.remove(); });
                }, duration);
            }
        },
        
        success: function(message, duration) {
            this.show(message, 'success', duration);
        },
        
        error: function(message, duration) {
            this.show(message, 'error', duration);
        },
        
        warning: function(message, duration) {
            this.show(message, 'warning', duration);
        },
        
        info: function(message, duration) {
            this.show(message, 'info', duration);
        }
    };
    
    // Make toast available globally
    window.ZaikonToast = ZAIKON_Toast;
    
    // POS Screen Functionality
    var RPOS_POS = {
        cart: [],
        products: [],
        currentCategory: 0,
        
        init: function() {
            if ($('.rpos-pos-screen').length || $('.zaikon-pos-screen').length) {
                this.loadProducts();
                this.bindEvents();
            }
        },
        
        bindEvents: function() {
            var self = this;
            
            // Category filter
            $('.rpos-category-btn').on('click', function() {
                $('.rpos-category-btn').removeClass('active');
                $(this).addClass('active');
                self.currentCategory = parseInt($(this).data('category'));
                self.renderProducts();
            });
            
            // Order Type Pills
            $('.zaikon-order-type-pill').on('click', function() {
                $('.zaikon-order-type-pill').removeClass('active');
                $(this).addClass('active');
                var orderType = $(this).data('order-type');
                $('#rpos-order-type').val(orderType);
            });
            
            // Clear cart
            $('#rpos-clear-cart').on('click', function() {
                if (confirm('Clear the entire cart?')) {
                    self.cart = [];
                    self.renderCart();
                }
            });
            
            // Discount change
            $('#rpos-discount').on('input', function() {
                self.updateTotals();
            });
            
            // Cash received change
            $('#rpos-cash-received').on('input', function() {
                self.calculateChange();
            });
            
            // Complete order
            $('#rpos-complete-order').on('click', function() {
                self.completeOrder();
            });
            
            // New order
            $('#rpos-new-order').on('click', function() {
                self.cart = [];
                self.renderCart();
                $('#rpos-receipt-modal').fadeOut();
                $('#rpos-cash-received').val('');
                $('#rpos-discount').val('0.00');
                $('#rpos-order-type').val('dine-in');
                $('.zaikon-order-type-pill').removeClass('active');
                $('.zaikon-order-type-pill[data-order-type="dine-in"]').addClass('active');
                $('#rpos-special-instructions').val('');
            });
        },
        
        loadProducts: function() {
            var self = this;
            console.log('ZAIKON POS: Loading products...');
            
            $.ajax({
                url: rposData.restUrl + 'products',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(response) {
                    console.log('ZAIKON POS: Products loaded successfully', response.length + ' products');
                    self.products = response;
                    self.renderProducts();
                },
                error: function(xhr, status, error) {
                    console.error('ZAIKON POS: Failed to load products', error);
                    alert('Failed to load products');
                }
            });
        },
        
        renderProducts: function() {
            var self = this;
            var $grid = $('#rpos-products-grid, .zaikon-products-grid');
            var filtered = this.currentCategory === 0 
                ? this.products 
                : this.products.filter(function(p) { return p.category_id == self.currentCategory; });
            
            $grid.empty();
            
            if (filtered.length === 0) {
                $grid.html('<div class="zaikon-no-products">No products found</div>');
                return;
            }
            
            filtered.forEach(function(product) {
                var $item = $('<div class="zaikon-product-card zaikon-animate-fadeIn">')
                    .data('product', product)
                    .on('click', function() {
                        $(this).addClass('zaikon-animate-scaleIn');
                        self.addToCart(product);
                    });
                
                if (product.image_url) {
                    $item.append('<img src="' + product.image_url + '" alt="' + product.name + '" class="zaikon-product-image">');
                } else {
                    $item.append('<div class="zaikon-product-image"><span class="dashicons dashicons-cart"></span></div>');
                }
                
                var $info = $('<div class="zaikon-product-info">');
                $info.append('<div class="zaikon-product-name">' + product.name + '</div>');
                $info.append('<div class="zaikon-product-price">' + rposData.currency + parseFloat(product.selling_price).toFixed(2) + '</div>');
                $item.append($info);
                
                $grid.append($item);
            });
        },
        
        addToCart: function(product) {
            var existing = this.cart.find(function(item) { return item.product.id === product.id; });
            
            if (existing) {
                existing.quantity++;
                ZAIKON_Toast.success('Updated quantity in cart');
            } else {
                this.cart.push({
                    product: product,
                    quantity: 1
                });
                ZAIKON_Toast.success(product.name + ' added to cart');
            }
            
            this.renderCart();
        },
        
        renderCart: function() {
            var self = this;
            var $container = $('#rpos-cart-items, .zaikon-cart-items');
            
            $container.empty();
            
            if (this.cart.length === 0) {
                $container.html('<div class="zaikon-cart-empty">Cart is empty. Add products to start an order.</div>');
                this.updateTotals();
                return;
            }
            
            this.cart.forEach(function(item, index) {
                var lineTotal = item.product.selling_price * item.quantity;
                
                var $item = $('<div class="zaikon-cart-item zaikon-animate-slideDown">');
                
                var $info = $('<div class="zaikon-cart-item-info">');
                $info.append('<div class="zaikon-cart-item-name">' + item.product.name + '</div>');
                $info.append('<div class="zaikon-cart-item-price">' + rposData.currency + parseFloat(item.product.selling_price).toFixed(2) + ' each</div>');
                $item.append($info);
                
                var $controls = $('<div class="zaikon-cart-item-controls">');
                $controls.append('<button class="zaikon-qty-btn rpos-qty-minus" data-index="' + index + '">-</button>');
                $controls.append('<span class="zaikon-qty-display">' + item.quantity + '</span>');
                $controls.append('<button class="zaikon-qty-btn rpos-qty-plus" data-index="' + index + '">+</button>');
                $item.append($controls);
                
                $item.append('<div class="zaikon-cart-item-total">' + rposData.currency + lineTotal.toFixed(2) + '</div>');
                
                $container.append($item);
            });
            
            // Bind cart item events
            $('.rpos-qty-minus').on('click', function() {
                var index = $(this).data('index');
                if (self.cart[index].quantity > 1) {
                    self.cart[index].quantity--;
                    self.renderCart();
                } else {
                    self.cart.splice(index, 1);
                    ZAIKON_Toast.info('Item removed from cart');
                    self.renderCart();
                }
            });
            
            $('.rpos-qty-plus').on('click', function() {
                var index = $(this).data('index');
                self.cart[index].quantity++;
                self.renderCart();
            });
            
            this.updateTotals();
        },
        
        updateTotals: function() {
            var subtotal = 0;
            
            this.cart.forEach(function(item) {
                subtotal += item.product.selling_price * item.quantity;
            });
            
            var discount = parseFloat($('#rpos-discount').val()) || 0;
            var total = subtotal - discount;
            
            $('#rpos-subtotal').text(rposData.currency + subtotal.toFixed(2));
            $('#rpos-total').text(rposData.currency + total.toFixed(2));
            
            this.calculateChange();
        },
        
        calculateChange: function() {
            var total = parseFloat($('#rpos-total').text().replace(rposData.currency, '')) || 0;
            var cashReceived = parseFloat($('#rpos-cash-received').val()) || 0;
            var change = cashReceived - total;
            
            $('#rpos-change-due').text(rposData.currency + (change >= 0 ? change.toFixed(2) : '0.00'));
        },
        
        completeOrder: function() {
            var self = this;
            
            if (this.cart.length === 0) {
                ZAIKON_Toast.error('Cart is empty');
                return;
            }
            
            var orderType = $('#rpos-order-type, #zaikon-order-type').val();
            if (!orderType) {
                ZAIKON_Toast.error('Please select an order type');
                return;
            }
            
            var subtotal = 0;
            this.cart.forEach(function(item) {
                subtotal += item.product.selling_price * item.quantity;
            });
            
            var discount = parseFloat($('#rpos-discount, #zaikon-discount').val()) || 0;
            var total = subtotal - discount;
            var cashReceived = parseFloat($('#rpos-cash-received, #zaikon-cash-received').val()) || 0;
            
            if (cashReceived < total) {
                ZAIKON_Toast.error('Cash received is less than total amount');
                return;
            }
            
            var changeDue = cashReceived - total;
            var specialInstructions = $('#rpos-special-instructions, #zaikon-special-instructions').val().trim();
            
            var orderData = {
                subtotal: subtotal,
                discount: discount,
                total: total,
                cash_received: cashReceived,
                change_due: changeDue,
                status: 'new',
                order_type: orderType,
                special_instructions: specialInstructions,
                items: this.cart.map(function(item) {
                    return {
                        product_id: item.product.id,
                        quantity: item.quantity,
                        unit_price: item.product.selling_price,
                        line_total: item.product.selling_price * item.quantity
                    };
                })
            };
            
            ZAIKON_Toast.info('Processing order...');
            
            $.ajax({
                url: rposData.restUrl + 'orders',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify(orderData),
                success: function(response) {
                    ZAIKON_Toast.success({
                        title: 'Order Completed!',
                        message: 'Order #' + response.order_number + ' created successfully'
                    }, 4000);
                    self.showReceipt(response, orderData);
                },
                error: function() {
                    ZAIKON_Toast.error('Failed to create order');
                }
            });
        },
        
        showReceipt: function(order, orderData) {
            $('#receipt-restaurant-name').text(rposData.restaurantName);
            $('#receipt-order-number').text('Order #' + order.order_number);
            $('#receipt-date-time').text(new Date().toLocaleString());
            
            var $items = $('#receipt-items');
            $items.empty();
            
            order.items.forEach(function(item) {
                $items.append(
                    '<div class="rpos-receipt-item">' +
                    '<span>' + item.product_name + ' x' + item.quantity + '</span>' +
                    '<span>' + rposData.currency + parseFloat(item.line_total).toFixed(2) + '</span>' +
                    '</div>'
                );
            });
            
            $('#receipt-subtotal').text(rposData.currency + parseFloat(orderData.subtotal).toFixed(2));
            $('#receipt-discount').text(rposData.currency + parseFloat(orderData.discount).toFixed(2));
            $('#receipt-total').text(rposData.currency + parseFloat(orderData.total).toFixed(2));
            $('#receipt-cash').text(rposData.currency + parseFloat(orderData.cash_received).toFixed(2));
            $('#receipt-change').text(rposData.currency + parseFloat(orderData.change_due).toFixed(2));
            $('#receipt-cashier').text('Cashier: ' + rposData.currentUser);
            
            $('#rpos-receipt-modal').fadeIn();
        }
    };
    
    // KDS Functionality
    var RPOS_KDS = {
        orders: [],
        currentFilter: 'all',
        autoRefreshInterval: null,
        
        init: function() {
            if ($('.rpos-kds').length || $('.zaikon-kds').length) {
                this.loadOrders();
                this.bindEvents();
                this.startAutoRefresh();
            }
        },
        
        bindEvents: function() {
            var self = this;
            
            // Filter buttons
            $('.rpos-kds-filter-btn').on('click', function() {
                $('.rpos-kds-filter-btn').removeClass('active');
                $(this).addClass('active');
                self.currentFilter = $(this).data('status');
                self.renderOrders();
            });
            
            // Refresh button
            $('#rpos-kds-refresh').on('click', function() {
                self.loadOrders();
            });
            
            // Auto-refresh toggle
            $('#rpos-kds-auto-refresh').on('change', function() {
                if ($(this).is(':checked')) {
                    self.startAutoRefresh();
                } else {
                    self.stopAutoRefresh();
                }
            });
        },
        
        loadOrders: function() {
            var self = this;
            console.log('ZAIKON KDS: Loading orders...');
            
            $.ajax({
                url: rposKdsData.restUrl + 'orders',
                method: 'GET',
                data: { limit: 100 },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposKdsData.nonce);
                },
                success: function(response) {
                    console.log('ZAIKON KDS: Orders loaded successfully', response.length + ' orders');
                    self.orders = response.filter(function(order) {
                        return ['new', 'cooking', 'ready'].includes(order.status);
                    });
                    console.log('ZAIKON KDS: Filtered to', self.orders.length + ' active orders');
                    self.renderOrders();
                },
                error: function(xhr, status, error) {
                    console.error('ZAIKON KDS: Failed to load orders', error);
                }
            });
        },
        
        renderOrders: function() {
            var self = this;
            var $grid = $('#rpos-kds-grid, .zaikon-kds-grid');
            var filtered = this.currentFilter === 'all' 
                ? this.orders 
                : this.orders.filter(function(o) { return o.status === self.currentFilter; });
            
            $grid.empty();
            $('#rpos-kds-empty, .zaikon-kds-empty').hide();
            
            if (filtered.length === 0) {
                $grid.hide();
                $('#rpos-kds-empty, .zaikon-kds-empty').show();
                return;
            }
            
            $grid.show();
            
            filtered.forEach(function(order) {
                var elapsed = self.getElapsedTime(order.created_at);
                var isUrgent = elapsed > 15; // Orders older than 15 minutes
                
                var $card = $('<div class="zaikon-order-card zaikon-animate-scaleIn" data-status="' + order.status + '">');
                
                var $header = $('<div class="zaikon-order-card-header">');
                $header.append('<div class="zaikon-order-number">#' + order.order_number + '</div>');
                
                var $meta = $('<div class="zaikon-order-meta">');
                
                // Order type badge
                if (order.order_type) {
                    var orderTypeLabel = order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1).replace('-', ' ');
                    $meta.append('<span class="zaikon-order-type-badge" data-type="' + order.order_type + '">' + orderTypeLabel + '</span>');
                }
                
                // Time elapsed
                var timeClass = isUrgent ? 'zaikon-order-time-urgent' : 'zaikon-order-time';
                $meta.append('<span class="' + timeClass + '">⏱ ' + elapsed + ' min</span>');
                
                $header.append($meta);
                $card.append($header);
                
                var $body = $('<div class="zaikon-order-card-body">');
                
                // Load order items
                $.ajax({
                    url: rposKdsData.restUrl + 'orders/' + order.id,
                    method: 'GET',
                    async: false,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', rposKdsData.nonce);
                    },
                    success: function(fullOrder) {
                        var $items = $('<ul class="zaikon-order-items">');
                        
                        fullOrder.items.forEach(function(item) {
                            var $item = $('<li class="zaikon-order-item">');
                            $item.append('<span class="zaikon-item-quantity">' + item.quantity + '</span>');
                            $item.append('<span class="zaikon-item-name">' + item.product_name + '</span>');
                            $items.append($item);
                        });
                        
                        $body.append($items);
                        
                        // Display special instructions if present
                        if (fullOrder.special_instructions && fullOrder.special_instructions.trim() !== '') {
                            var $instructions = $('<div class="zaikon-special-instructions">');
                            $instructions.append('<span class="zaikon-special-instructions-label">⚠ Special Instructions</span>');
                            $instructions.append('<p class="zaikon-special-instructions-text">' + fullOrder.special_instructions + '</p>');
                            $body.append($instructions);
                        }
                    }
                });
                
                $card.append($body);
                
                // Action buttons
                var $footer = $('<div class="zaikon-order-card-footer">');
                
                if (order.status === 'new') {
                    $footer.append('<button class="zaikon-status-btn zaikon-status-btn-start rpos-kds-action" data-id="' + order.id + '" data-status="cooking">' + rposKdsData.translations.startCooking + '</button>');
                } else if (order.status === 'cooking') {
                    $footer.append('<button class="zaikon-status-btn zaikon-status-btn-ready rpos-kds-action" data-id="' + order.id + '" data-status="ready">' + rposKdsData.translations.markReady + '</button>');
                } else if (order.status === 'ready') {
                    $footer.append('<button class="zaikon-status-btn zaikon-status-btn-complete rpos-kds-action" data-id="' + order.id + '" data-status="completed">' + rposKdsData.translations.complete + '</button>');
                }
                
                $card.append($footer);
                $grid.append($card);
            });
            
            // Bind action buttons
            $('.rpos-kds-action').on('click', function() {
                var orderId = $(this).data('id');
                var newStatus = $(this).data('status');
                var $card = $(this).closest('.zaikon-order-card');
                
                // Add animation
                $card.addClass('status-changed');
                
                self.updateOrderStatus(orderId, newStatus);
            });
        },
        
        updateOrderStatus: function(orderId, status) {
            var self = this;
            
            ZAIKON_Toast.info('Updating order status...');
            
            $.ajax({
                url: rposKdsData.restUrl + 'orders/' + orderId,
                method: 'PUT',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposKdsData.nonce);
                },
                data: JSON.stringify({ status: status }),
                success: function() {
                    var statusLabels = {
                        'cooking': 'Cooking Started',
                        'ready': 'Order Ready',
                        'completed': 'Order Completed'
                    };
                    ZAIKON_Toast.success(statusLabels[status] || 'Status Updated');
                    self.loadOrders();
                },
                error: function() {
                    ZAIKON_Toast.error('Failed to update order status');
                }
            });
        },
        
        getElapsedTime: function(createdAt) {
            var created = new Date(createdAt);
            var now = new Date();
            var diff = Math.floor((now - created) / 1000 / 60);
            return diff;
        },
        
        startAutoRefresh: function() {
            var self = this;
            this.stopAutoRefresh();
            this.autoRefreshInterval = setInterval(function() {
                self.loadOrders();
            }, 30000); // 30 seconds
        },
        
        stopAutoRefresh: function() {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
                this.autoRefreshInterval = null;
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        RPOS_POS.init();
        RPOS_KDS.init();
    });
    
})(jQuery);
