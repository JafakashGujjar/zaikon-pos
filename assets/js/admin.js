/**
 * Restaurant POS Admin JavaScript
 */

(function($) {
    'use strict';
    
    // POS Screen Functionality
    var RPOS_POS = {
        cart: [],
        products: [],
        currentCategory: 0,
        
        init: function() {
            if ($('.rpos-pos-screen').length) {
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
            });
        },
        
        loadProducts: function() {
            var self = this;
            
            $.ajax({
                url: rposData.restUrl + 'products',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(response) {
                    self.products = response;
                    self.renderProducts();
                },
                error: function() {
                    alert('Failed to load products');
                }
            });
        },
        
        renderProducts: function() {
            var self = this;
            var $grid = $('#rpos-products-grid');
            var filtered = this.currentCategory === 0 
                ? this.products 
                : this.products.filter(function(p) { return p.category_id == self.currentCategory; });
            
            $grid.empty();
            
            if (filtered.length === 0) {
                $grid.html('<div class="rpos-no-products">No products found</div>');
                return;
            }
            
            filtered.forEach(function(product) {
                var $item = $('<div class="rpos-product-item">')
                    .data('product', product)
                    .on('click', function() {
                        self.addToCart(product);
                    });
                
                if (product.image_url) {
                    $item.append('<div class="rpos-product-image"><img src="' + product.image_url + '" alt="' + product.name + '"></div>');
                } else {
                    $item.append('<div class="rpos-product-image rpos-no-image"><span class="dashicons dashicons-cart"></span></div>');
                }
                
                $item.append('<div class="rpos-product-name">' + product.name + '</div>');
                $item.append('<div class="rpos-product-price">' + rposData.currency + parseFloat(product.selling_price).toFixed(2) + '</div>');
                
                $grid.append($item);
            });
        },
        
        addToCart: function(product) {
            var existing = this.cart.find(function(item) { return item.product.id === product.id; });
            
            if (existing) {
                existing.quantity++;
            } else {
                this.cart.push({
                    product: product,
                    quantity: 1
                });
            }
            
            this.renderCart();
        },
        
        renderCart: function() {
            var self = this;
            var $container = $('#rpos-cart-items');
            
            $container.empty();
            
            if (this.cart.length === 0) {
                $container.html('<div class="rpos-cart-empty">Cart is empty. Add products to start an order.</div>');
                this.updateTotals();
                return;
            }
            
            this.cart.forEach(function(item, index) {
                var lineTotal = item.product.selling_price * item.quantity;
                
                var $item = $('<div class="rpos-cart-item">');
                $item.append('<div class="rpos-cart-item-name">' + item.product.name + '</div>');
                
                var $qty = $('<div class="rpos-cart-item-qty">');
                $qty.append('<button class="rpos-qty-btn rpos-qty-minus" data-index="' + index + '">-</button>');
                $qty.append('<input type="number" class="rpos-qty-input" data-index="' + index + '" value="' + item.quantity + '" min="1">');
                $qty.append('<button class="rpos-qty-btn rpos-qty-plus" data-index="' + index + '">+</button>');
                $item.append($qty);
                
                $item.append('<div class="rpos-cart-item-price">' + rposData.currency + lineTotal.toFixed(2) + '</div>');
                $item.append('<button class="rpos-cart-item-remove" data-index="' + index + '">×</button>');
                
                $container.append($item);
            });
            
            // Bind cart item events
            $('.rpos-qty-minus').on('click', function() {
                var index = $(this).data('index');
                if (self.cart[index].quantity > 1) {
                    self.cart[index].quantity--;
                    self.renderCart();
                }
            });
            
            $('.rpos-qty-plus').on('click', function() {
                var index = $(this).data('index');
                self.cart[index].quantity++;
                self.renderCart();
            });
            
            $('.rpos-qty-input').on('change', function() {
                var index = $(this).data('index');
                var qty = parseInt($(this).val()) || 1;
                self.cart[index].quantity = qty;
                self.renderCart();
            });
            
            $('.rpos-cart-item-remove').on('click', function() {
                var index = $(this).data('index');
                self.cart.splice(index, 1);
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
            
            $('#rpos-change-due').val(rposData.currency + (change >= 0 ? change.toFixed(2) : '0.00'));
        },
        
        completeOrder: function() {
            var self = this;
            
            if (this.cart.length === 0) {
                alert('Cart is empty');
                return;
            }
            
            var subtotal = 0;
            this.cart.forEach(function(item) {
                subtotal += item.product.selling_price * item.quantity;
            });
            
            var discount = parseFloat($('#rpos-discount').val()) || 0;
            var total = subtotal - discount;
            var cashReceived = parseFloat($('#rpos-cash-received').val()) || 0;
            
            if (cashReceived < total) {
                alert('Cash received is less than total amount');
                return;
            }
            
            var changeDue = cashReceived - total;
            
            var orderData = {
                subtotal: subtotal,
                discount: discount,
                total: total,
                cash_received: cashReceived,
                change_due: changeDue,
                status: 'completed',
                items: this.cart.map(function(item) {
                    return {
                        product_id: item.product.id,
                        quantity: item.quantity,
                        unit_price: item.product.selling_price,
                        line_total: item.product.selling_price * item.quantity
                    };
                })
            };
            
            $.ajax({
                url: rposData.restUrl + 'orders',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify(orderData),
                success: function(response) {
                    self.showReceipt(response, orderData);
                },
                error: function() {
                    alert('Failed to create order');
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
            if ($('.rpos-kds').length) {
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
            
            $.ajax({
                url: rposKdsData.restUrl + 'orders',
                method: 'GET',
                data: { limit: 100 },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposKdsData.nonce);
                },
                success: function(response) {
                    self.orders = response.filter(function(order) {
                        return ['new', 'cooking', 'ready'].includes(order.status);
                    });
                    self.renderOrders();
                }
            });
        },
        
        renderOrders: function() {
            var self = this;
            var $grid = $('#rpos-kds-grid');
            var filtered = this.currentFilter === 'all' 
                ? this.orders 
                : this.orders.filter(function(o) { return o.status === self.currentFilter; });
            
            $grid.empty();
            $('#rpos-kds-empty').hide();
            
            if (filtered.length === 0) {
                $grid.hide();
                $('#rpos-kds-empty').show();
                return;
            }
            
            $grid.show();
            
            filtered.forEach(function(order) {
                var elapsed = self.getElapsedTime(order.created_at);
                var statusClass = 'rpos-kds-order-' + order.status;
                
                var $card = $('<div class="rpos-kds-order ' + statusClass + '">');
                
                var $header = $('<div class="rpos-kds-order-header">');
                $header.append('<div class="rpos-kds-order-number">' + rposKdsData.translations.orderNumber + order.order_number + '</div>');
                $header.append('<div class="rpos-kds-order-time">' + elapsed + ' ' + rposKdsData.translations.minutes + '</div>');
                $card.append($header);
                
                var $items = $('<div class="rpos-kds-order-items">');
                $items.append('<h4>' + rposKdsData.translations.items + ':</h4>');
                
                $.ajax({
                    url: rposKdsData.restUrl + 'orders/' + order.id,
                    method: 'GET',
                    async: false,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', rposKdsData.nonce);
                    },
                    success: function(fullOrder) {
                        fullOrder.items.forEach(function(item) {
                            $items.append('<div class="rpos-kds-item">' + item.quantity + 'x ' + item.product_name + '</div>');
                        });
                    }
                });
                
                $card.append($items);
                
                var $actions = $('<div class="rpos-kds-order-actions">');
                
                if (order.status === 'new') {
                    $actions.append('<button class="button button-primary rpos-kds-action" data-id="' + order.id + '" data-status="cooking">' + rposKdsData.translations.startCooking + '</button>');
                } else if (order.status === 'cooking') {
                    $actions.append('<button class="button button-primary rpos-kds-action" data-id="' + order.id + '" data-status="ready">' + rposKdsData.translations.markReady + '</button>');
                } else if (order.status === 'ready') {
                    $actions.append('<button class="button button-primary rpos-kds-action" data-id="' + order.id + '" data-status="completed">' + rposKdsData.translations.complete + '</button>');
                }
                
                $card.append($actions);
                $grid.append($card);
            });
            
            // Bind action buttons
            $('.rpos-kds-action').on('click', function() {
                var orderId = $(this).data('id');
                var newStatus = $(this).data('status');
                self.updateOrderStatus(orderId, newStatus);
            });
        },
        
        updateOrderStatus: function(orderId, status) {
            var self = this;
            
            $.ajax({
                url: rposKdsData.restUrl + 'orders/' + orderId,
                method: 'PUT',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposKdsData.nonce);
                },
                data: JSON.stringify({ status: status }),
                success: function() {
                    self.loadOrders();
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
