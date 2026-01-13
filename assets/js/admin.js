/**
 * ZAIKON POS - Admin JavaScript
 * Enhanced with toast notifications, animations, and micro-interactions
 */

(function($) {
    'use strict';
    
    // Notification Sound Data (base64 encoded WAV)
    var NOTIFICATION_SOUND_DATA = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSyBzvLZiTYIGWi78OShTwsNUrDm77BZFApIoemMvGojAwAA';
    
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
    
    /**
     * Safe price formatting helper
     * Prevents NaN, null, undefined formatting issues
     */
    function formatPrice(value, currency) {
        var num = parseFloat(value);
        if (isNaN(num) || num === null || num === undefined) {
            num = 0;
        }
        
        var currencySymbol = currency || (typeof rposData !== 'undefined' ? rposData.currency : 'Rs');
        return currencySymbol + num.toFixed(2);
    }
    
    // Make formatPrice available globally
    window.formatPrice = formatPrice;
    
    // POS Screen Functionality
    var RPOS_POS = {
        cart: [],
        products: [],
        currentCategory: 0,
        deliveryData: null,
        notificationInterval: null,
        lastNotificationCheck: null,
        
        init: function() {
            if ($('.rpos-pos-screen').length || $('.zaikon-pos-screen').length) {
                this.loadProducts();
                this.bindEvents();
                this.initNotifications();
                this.initNotificationSound();
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
                var orderType = $(this).data('order-type');
                
                // If delivery is selected, open delivery modal
                if (orderType === 'delivery') {
                    // Get current subtotal
                    var subtotal = self.calculateSubtotal();
                    
                    // Open delivery modal
                    if (window.RPOS_Delivery) {
                        window.RPOS_Delivery.open(subtotal, function(deliveryData) {
                            if (deliveryData) {
                                // User confirmed delivery details
                                $('.zaikon-order-type-pill').removeClass('active');
                                $('.zaikon-order-type-pill[data-order-type="delivery"]').addClass('active');
                                $('#rpos-order-type').val('delivery');
                                
                                // Store delivery data
                                self.deliveryData = deliveryData;
                                
                                // Update totals with delivery charge
                                self.updateTotals();
                                
                                ZAIKON_Toast.success('Delivery details added');
                            } else {
                                // User cancelled - revert to previous order type
                                var currentType = $('#rpos-order-type').val() || 'dine-in';
                                $('.zaikon-order-type-pill').removeClass('active');
                                $('.zaikon-order-type-pill[data-order-type="' + currentType + '"]').addClass('active');
                            }
                        });
                    }
                } else {
                    // Regular order type change
                    $('.zaikon-order-type-pill').removeClass('active');
                    $(this).addClass('active');
                    $('#rpos-order-type').val(orderType);
                    
                    // Clear delivery data if switching away from delivery
                    self.deliveryData = null;
                    self.updateTotals();
                }
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
                self.deliveryData = null;
                self.renderCart();
                $('#rpos-receipt-modal').fadeOut();
                $('#rpos-cash-received').val('');
                $('#rpos-discount').val('0.00');
                $('#rpos-order-type').val('dine-in');
                $('.zaikon-order-type-pill').removeClass('active');
                $('.zaikon-order-type-pill[data-order-type="dine-in"]').addClass('active');
                $('#rpos-special-instructions').val('');
            });
            
            // Print rider slip
            $('#rpos-print-rider-slip').on('click', function() {
                self.printRiderSlip();
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
            var subtotal = this.calculateSubtotal();
            var discount = parseFloat($('#rpos-discount').val()) || 0;
            
            // Add delivery charge if delivery order
            var deliveryCharge = 0;
            if (this.deliveryData && this.deliveryData.delivery_charge) {
                deliveryCharge = parseFloat(this.deliveryData.delivery_charge) || 0;
                $('#rpos-delivery-charge-row').show();
                $('#rpos-delivery-charge-display').text(formatPrice(deliveryCharge, rposData.currency));
            } else {
                $('#rpos-delivery-charge-row').hide();
            }
            
            var total = subtotal + deliveryCharge - discount;
            
            $('#rpos-subtotal').text(formatPrice(subtotal, rposData.currency));
            $('#rpos-total').text(formatPrice(total, rposData.currency));
            
            this.calculateChange();
        },
        
        calculateSubtotal: function() {
            var subtotal = 0;
            this.cart.forEach(function(item) {
                subtotal += item.product.selling_price * item.quantity;
            });
            return subtotal;
        },
        
        calculateChange: function() {
            var total = parseFloat($('#rpos-total').text().replace(/[^\d.-]/g, '')) || 0;
            var cashReceived = parseFloat($('#rpos-cash-received').val()) || 0;
            var change = cashReceived - total;
            
            $('#rpos-change-due').text(formatPrice(change >= 0 ? change : 0, rposData.currency));
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
            
            // Validate delivery details if delivery order
            if (orderType === 'delivery' && !this.deliveryData) {
                ZAIKON_Toast.error('Please provide delivery details');
                return;
            }
            
            var subtotal = this.calculateSubtotal();
            var discount = parseFloat($('#rpos-discount, #zaikon-discount').val()) || 0;
            
            // Add delivery charge if delivery order
            var deliveryCharge = 0;
            if (this.deliveryData && this.deliveryData.delivery_charge) {
                deliveryCharge = parseFloat(this.deliveryData.delivery_charge);
            }
            
            var total = subtotal + deliveryCharge - discount;
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
            
            // Add delivery fields if delivery order
            if (orderType === 'delivery' && this.deliveryData) {
                orderData.is_delivery = 1;
                orderData.delivery_charge = deliveryCharge;
                orderData.area_id = this.deliveryData.area_id;
                orderData.customer_name = this.deliveryData.customer_name;
                orderData.customer_phone = this.deliveryData.customer_phone;
                orderData.distance_km = this.deliveryData.distance_km || 0;
                orderData.is_free_delivery = this.deliveryData.is_free_delivery || 0;
                orderData.location_name = this.deliveryData.location_name || '';
                orderData.special_instructions = this.deliveryData.special_instructions || '';
            }
            
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
        
        initNotificationSound: function() {
            // Create audio element for notification sound
            if (!document.getElementById('pos-notification-sound')) {
                var audio = document.createElement('audio');
                audio.id = 'pos-notification-sound';
                audio.innerHTML = '<source src="' + NOTIFICATION_SOUND_DATA + '" type="audio/wav" />';
                document.body.appendChild(audio);
            }
        },
        
        playNotificationSound: function() {
            var audio = document.getElementById('pos-notification-sound');
            if (audio) {
                audio.play().catch(function(e) {
                    console.log('Audio play failed:', e);
                });
            }
        },
        
        initNotifications: function() {
            var self = this;
            
            // Initial load
            this.loadNotifications();
            
            // Poll every 20 seconds
            this.notificationInterval = setInterval(function() {
                self.loadNotifications();
            }, 20000);
            
            // Bind notification bell click
            $('#rpos-notification-bell').on('click', function() {
                self.toggleNotificationDropdown();
            });
            
            // Bind mark all as read
            $('#rpos-mark-all-read').on('click', function() {
                self.markAllAsRead();
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#rpos-notification-bell, #rpos-notification-dropdown').length) {
                    $('#rpos-notification-dropdown').hide();
                }
            });
        },
        
        loadNotifications: function() {
            var self = this;
            
            $.ajax({
                url: rposData.restUrl + 'notifications/unread',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(response) {
                    var unreadCount = response.unread_count;
                    var notifications = response.notifications;
                    
                    // Update badge
                    if (unreadCount > 0) {
                        $('#rpos-notification-badge').text(unreadCount).show();
                    } else {
                        $('#rpos-notification-badge').hide();
                    }
                    
                    // Check for new notifications
                    if (notifications.length > 0 && self.lastNotificationCheck) {
                        var newNotifications = notifications.filter(function(n) {
                            return new Date(n.created_at) > new Date(self.lastNotificationCheck);
                        });
                        
                        if (newNotifications.length > 0) {
                            // Show toast for the most recent one
                            var latest = newNotifications[0];
                            ZAIKON_Toast.info({
                                title: 'Order Update',
                                message: latest.message
                            }, 5000);
                            
                            // Play sound if it's a ready order
                            if (latest.type === 'order_ready') {
                                self.playNotificationSound();
                            }
                        }
                    }
                    
                    self.lastNotificationCheck = new Date().toISOString();
                    self.renderNotifications(notifications);
                },
                error: function() {
                    console.log('Failed to load notifications');
                }
            });
        },
        
        renderNotifications: function(notifications) {
            var self = this;
            var $list = $('#rpos-notification-list');
            $list.empty();
            
            if (notifications.length === 0) {
                $list.append('<div class="zaikon-notification-empty">No new notifications</div>');
                return;
            }
            
            notifications.forEach(function(notification) {
                var isHighlighted = notification.type === 'order_ready' || notification.type === 'order_completed';
                var icon = '🔔';
                
                if (notification.type === 'order_ready') {
                    icon = '✅';
                } else if (notification.type === 'order_completed') {
                    icon = '✓';
                } else if (notification.type === 'order_cooking') {
                    icon = '🍳';
                } else if (notification.type === 'order_accepted') {
                    icon = '👍';
                }
                
                var timeAgo = self.getTimeAgo(notification.created_at);
                var highlightClass = isHighlighted ? ' highlighted' : '';
                
                // Create elements safely using jQuery
                var $item = $('<div class="zaikon-notification-item unread' + highlightClass + '">');
                $item.attr('data-id', notification.id);
                
                var $content = $('<div class="zaikon-notification-content">');
                var $icon = $('<div class="zaikon-notification-icon">').text(icon);
                var $details = $('<div class="zaikon-notification-details">');
                
                var $order = $('<div class="zaikon-notification-order">').text('Order #' + notification.order_number);
                var $message = $('<div class="zaikon-notification-message">').text(notification.message);
                var $time = $('<div class="zaikon-notification-time">').text(timeAgo);
                
                $details.append($order, $message, $time);
                $content.append($icon, $details);
                
                var $markReadBtn = $('<button class="zaikon-notification-mark-read">').text('✕');
                $markReadBtn.attr('data-id', notification.id);
                
                $item.append($content, $markReadBtn);
                $list.append($item);
            });
            
            // Bind mark as read buttons
            $('.zaikon-notification-mark-read').on('click', function(e) {
                e.stopPropagation();
                var notificationId = $(this).data('id');
                self.markAsRead(notificationId);
            });
        },
        
        getTimeAgo: function(dateString) {
            var now = new Date();
            var past = new Date(dateString);
            var diff = Math.floor((now - past) / 1000); // seconds
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return Math.floor(diff / 86400) + ' days ago';
        },
        
        toggleNotificationDropdown: function() {
            $('#rpos-notification-dropdown').toggle();
        },
        
        markAsRead: function(notificationId) {
            var self = this;
            
            $.ajax({
                url: rposData.restUrl + 'notifications/mark-read/' + notificationId,
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function() {
                    // Reload notifications
                    self.loadNotifications();
                },
                error: function() {
                    ZAIKON_Toast.error('Failed to mark notification as read');
                }
            });
        },
        
        markAllAsRead: function() {
            var self = this;
            
            $.ajax({
                url: rposData.restUrl + 'notifications/mark-all-read',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function() {
                    ZAIKON_Toast.success('All notifications marked as read');
                    // Reload notifications
                    self.loadNotifications();
                },
                error: function() {
                    ZAIKON_Toast.error('Failed to mark notifications as read');
                }
            });
        },
        
        showReceipt: function(order, orderData) {
            // Store order data for rider slip
            this.lastOrderData = orderData;
            this.lastOrder = order;
            
            $('#receipt-restaurant-name').text(rposData.restaurantName);
            $('#receipt-restaurant-phone').text(rposData.restaurantPhone || '');
            $('#receipt-restaurant-address').text(rposData.restaurantAddress || '');
            $('#receipt-order-number').text('Order #' + order.order_number);
            
            // Add order type
            if (orderData.order_type) {
                var orderTypeText = orderData.order_type.charAt(0).toUpperCase() + orderData.order_type.slice(1).replace('-', ' ');
                $('#receipt-order-type').text('Order Type: ' + orderTypeText);
            } else {
                $('#receipt-order-type').text('Order Type: Dine-in');
            }
            
            $('#receipt-date-time').text(new Date().toLocaleString());
            
            // Show/hide rider slip button based on order type
            if (orderData.order_type === 'delivery') {
                $('#rpos-print-rider-slip').show();
            } else {
                $('#rpos-print-rider-slip').hide();
            }
            
            // Add delivery details if delivery order
            if (orderData.order_type === 'delivery' && orderData.customer_name) {
                var deliveryInfo = '<div style="margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 5px;">';
                deliveryInfo += '<strong>Delivery To:</strong><br>';
                deliveryInfo += orderData.customer_name + '<br>';
                deliveryInfo += orderData.customer_phone + '<br>';
                if (orderData.location_name) {
                    deliveryInfo += 'Location: ' + orderData.location_name;
                    if (orderData.distance_km) {
                        deliveryInfo += ' (' + orderData.distance_km + ' km)';
                    }
                }
                deliveryInfo += '</div>';
                $('#receipt-order-type').after(deliveryInfo);
            }
            
            // Add special instructions if any
            if (orderData.special_instructions && orderData.special_instructions.trim() !== '') {
                $('#receipt-special-instructions').text('Instructions: ' + orderData.special_instructions).show();
            } else {
                $('#receipt-special-instructions').hide();
            }
            
            var $items = $('#receipt-items');
            $items.empty();
            
            // Create a proper table for items using jQuery for safe DOM manipulation
            var $itemTable = $('<table class="zaikon-receipt-item-table">');
            var $thead = $('<thead>').append(
                $('<tr>').append(
                    $('<th>').text('Item'),
                    $('<th>').attr('style', 'text-align: center;').text('Qty'),
                    $('<th>').attr('style', 'text-align: right;').text('Price'),
                    $('<th>').attr('style', 'text-align: right;').text('Total')
                )
            );
            
            var $tbody = $('<tbody>');
            order.items.forEach(function(item) {
                var $row = $('<tr>');
                $row.append(
                    $('<td>').text(item.product_name),
                    $('<td>').attr('style', 'text-align: center;').text(item.quantity),
                    $('<td>').attr('style', 'text-align: right;').text(formatPrice(item.price, rposData.currency)),
                    $('<td>').attr('style', 'text-align: right;').text(formatPrice(item.line_total, rposData.currency))
                );
                $tbody.append($row);
            });
            
            $itemTable.append($thead, $tbody);
            $items.append($itemTable);
            
            $('#receipt-subtotal').text(formatPrice(orderData.subtotal, rposData.currency));
            
            // Show delivery charge if present
            var deliveryCharge = parseFloat(orderData.delivery_charge || 0);
            if (deliveryCharge > 0 || orderData.order_type === 'delivery') {
                var deliveryLabel = 'Delivery Charge:';
                if (orderData.is_free_delivery) {
                    deliveryLabel += ' <span style="color: green;">(FREE)</span>';
                }
                var $deliveryRow = $('<div class="rpos-receipt-totals-row">' +
                    '<span>' + deliveryLabel + '</span>' +
                    '<span id="receipt-delivery-charge">' + formatPrice(deliveryCharge, rposData.currency) + '</span>' +
                    '</div>');
                $('#receipt-subtotal').parent().after($deliveryRow);
            }
            
            $('#receipt-discount').text(formatPrice(orderData.discount, rposData.currency));
            $('#receipt-total').text(formatPrice(orderData.total, rposData.currency));
            $('#receipt-cash').text(formatPrice(orderData.cash_received, rposData.currency));
            $('#receipt-change').text(formatPrice(orderData.change_due, rposData.currency));
            $('#receipt-footer-message').text(rposData.receiptFooterMessage || 'Thank you for your order!');
            $('#receipt-cashier').text('Cashier: ' + rposData.currentUser);
            
            $('#rpos-receipt-modal').fadeIn();
        },
        
        printRiderSlip: function() {
            if (!this.lastOrder || !this.lastOrderData || this.lastOrderData.order_type !== 'delivery') {
                ZAIKON_Toast.error('No delivery order available to print rider slip');
                return;
            }
            
            var order = this.lastOrder;
            var orderData = this.lastOrderData;
            
            // Create a new window for printing
            var printWindow = window.open('', '_blank', 'width=300,height=600');
            
            var slipContent = '<!DOCTYPE html><html><head>';
            slipContent += '<title>Rider Slip - Order #' + order.order_number + '</title>';
            slipContent += '<style>';
            slipContent += 'body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }';
            slipContent += 'h2, h3 { margin: 10px 0; text-align: center; }';
            slipContent += 'h2 { font-size: 18px; border-bottom: 2px solid #000; padding-bottom: 5px; }';
            slipContent += 'h3 { font-size: 16px; }';
            slipContent += '.section { margin: 15px 0; padding: 10px; border: 1px solid #ccc; background: #f9f9f9; }';
            slipContent += '.label { font-weight: bold; }';
            slipContent += '.row { margin: 5px 0; }';
            slipContent += 'table { width: 100%; border-collapse: collapse; margin: 10px 0; }';
            slipContent += 'table td { padding: 5px; border-bottom: 1px dashed #ccc; }';
            slipContent += '.total-row { font-weight: bold; font-size: 14px; border-top: 2px solid #000; padding-top: 5px; }';
            slipContent += '@media print { button { display: none; } }';
            slipContent += '</style>';
            slipContent += '</head><body>';
            
            slipContent += '<h2>🛵 RIDER DELIVERY SLIP</h2>';
            slipContent += '<h3>Order #' + order.order_number + '</h3>';
            slipContent += '<div style="text-align: center; margin-bottom: 15px;">' + new Date().toLocaleString() + '</div>';
            
            slipContent += '<div class="section">';
            slipContent += '<div class="row"><span class="label">Customer:</span> ' + (orderData.customer_name || 'N/A') + '</div>';
            slipContent += '<div class="row"><span class="label">Phone:</span> ' + (orderData.customer_phone || 'N/A') + '</div>';
            slipContent += '<div class="row"><span class="label">Location:</span> ' + (orderData.location_name || 'N/A');
            if (orderData.distance_km) {
                slipContent += ' (' + orderData.distance_km + ' km)';
            }
            slipContent += '</div>';
            if (orderData.special_instructions && orderData.special_instructions.trim()) {
                slipContent += '<div class="row"><span class="label">Instructions:</span> ' + orderData.special_instructions + '</div>';
            }
            slipContent += '</div>';
            
            slipContent += '<h3>Order Items</h3>';
            slipContent += '<table>';
            order.items.forEach(function(item) {
                slipContent += '<tr>';
                slipContent += '<td>' + item.product_name + '</td>';
                slipContent += '<td style="text-align: center;">x' + item.quantity + '</td>';
                slipContent += '<td style="text-align: right;">' + formatPrice(item.line_total, rposData.currency) + '</td>';
                slipContent += '</tr>';
            });
            slipContent += '</table>';
            
            slipContent += '<div style="margin-top: 20px;">';
            slipContent += '<div class="row">Subtotal: <span style="float: right;">' + formatPrice(orderData.subtotal, rposData.currency) + '</span></div>';
            
            var deliveryCharge = parseFloat(orderData.delivery_charge || 0);
            slipContent += '<div class="row">Delivery Charge: <span style="float: right;">' + formatPrice(deliveryCharge, rposData.currency);
            if (orderData.is_free_delivery) {
                slipContent += ' <strong>(FREE)</strong>';
            }
            slipContent += '</span></div>';
            
            slipContent += '<div class="row total-row">Total to Collect: <span style="float: right;">' + formatPrice(orderData.total, rposData.currency) + '</span></div>';
            slipContent += '</div>';
            
            slipContent += '<div style="margin-top: 30px; padding-top: 20px; border-top: 2px dashed #000; text-align: center;">';
            slipContent += '<p><strong>Rider Signature:</strong></p>';
            slipContent += '<p>_________________________</p>';
            slipContent += '<p style="margin-top: 20px; font-size: 10px;">' + rposData.restaurantName + '</p>';
            slipContent += '</div>';
            
            slipContent += '<div style="text-align: center; margin-top: 20px;">';
            slipContent += '<button onclick="window.print();" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">Print Slip</button>';
            slipContent += '</div>';
            
            slipContent += '</body></html>';
            
            printWindow.document.write(slipContent);
            printWindow.document.close();
        }
    };
    
    // KDS Functionality
    var RPOS_KDS = {
        orders: [],
        previousOrderIds: [],
        currentFilter: 'all',
        autoRefreshInterval: null,
        timerInterval: null,
        
        init: function() {
            if ($('.rpos-kds').length || $('.zaikon-kds').length) {
                this.loadOrders();
                this.bindEvents();
                this.startAutoRefresh();
                this.startTimers();
                this.initNotificationSound();
            }
        },
        
        initNotificationSound: function() {
            // Create audio element for new order notification
            if (!document.getElementById('kds-notification-sound')) {
                var audio = document.createElement('audio');
                audio.id = 'kds-notification-sound';
                audio.innerHTML = '<source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSyBzvLZiTYIGWi78OShTwsNUrDm77BZFApIoemMvGojAwAA" type="audio/wav" />';
                document.body.appendChild(audio);
            }
        },
        
        playNotificationSound: function() {
            var audio = document.getElementById('kds-notification-sound');
            if (audio) {
                audio.play().catch(function(e) {
                    console.log('Audio play failed:', e);
                });
            }
        },
        
        startTimers: function() {
            var self = this;
            // Update all timers every second
            this.timerInterval = setInterval(function() {
                self.updateAllTimers();
            }, 1000);
        },
        
        updateAllTimers: function() {
            var self = this;
            $('.zaikon-order-card').each(function() {
                var $card = $(this);
                var createdAt = $card.data('created-at');
                if (createdAt) {
                    var elapsed = self.getElapsedMinutes(createdAt);
                    var $timer = $card.find('.zaikon-order-timer');
                    if ($timer.length) {
                        $timer.text(self.formatTime(elapsed));
                        
                        // Update urgency class
                        if (elapsed > 15) {
                            $timer.addClass('zaikon-order-time-urgent');
                        }
                    }
                }
            });
        },
        
        formatTime: function(minutes) {
            var mins = Math.floor(minutes);
            var secs = Math.floor((minutes - mins) * 60);
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        },
        
        getElapsedMinutes: function(createdAt) {
            var now = new Date();
            var created = new Date(createdAt);
            var diff = (now - created) / 1000 / 60; // minutes
            return diff;
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
                    
                    var newOrders = response.filter(function(order) {
                        return ['new', 'cooking', 'ready'].includes(order.status);
                    });
                    
                    // Detect new orders
                    var currentOrderIds = newOrders.map(function(o) { return o.id; });
                    var newlyAdded = [];
                    
                    if (self.previousOrderIds.length > 0) {
                        newlyAdded = currentOrderIds.filter(function(id) {
                            return self.previousOrderIds.indexOf(id) === -1;
                        });
                    }
                    
                    // Store current order IDs for next comparison
                    self.previousOrderIds = currentOrderIds;
                    self.orders = newOrders;
                    
                    console.log('ZAIKON KDS: Filtered to', self.orders.length + ' active orders');
                    self.renderOrders(newlyAdded);
                    
                    // Play sound and show notification for new orders
                    if (newlyAdded.length > 0) {
                        self.playNotificationSound();
                        ZaikonToast.info('🔔 ' + newlyAdded.length + ' new order(s) received!', 5000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('ZAIKON KDS: Failed to load orders', error);
                }
            });
        },
        
        renderOrders: function(newlyAddedIds) {
            var self = this;
            newlyAddedIds = newlyAddedIds || [];
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
                var elapsed = self.getElapsedMinutes(order.created_at);
                var elapsedInt = Math.floor(elapsed);
                var isUrgent = elapsedInt > 15; // Orders older than 15 minutes
                var isNewOrder = newlyAddedIds.indexOf(order.id) !== -1;
                
                var $card = $('<div class="zaikon-order-card zaikon-animate-scaleIn" data-status="' + order.status + '" data-created-at="' + order.created_at + '" data-order-id="' + order.id + '">');
                
                // Add new order alert class
                if (isNewOrder) {
                    $card.addClass('new-order-alert');
                }
                
                var $header = $('<div class="zaikon-order-card-header">');
                $header.append('<div class="zaikon-order-number">#' + order.order_number + '</div>');
                
                var $meta = $('<div class="zaikon-order-meta">');
                
                // Order type badge
                if (order.order_type) {
                    var orderTypeLabel = order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1).replace('-', ' ');
                    $meta.append('<span class="zaikon-order-type-badge" data-type="' + order.order_type + '">' + orderTypeLabel + '</span>');
                }
                
                // Time elapsed with countdown timer
                var timeClass = isUrgent ? 'zaikon-order-time-urgent' : 'zaikon-order-time';
                $meta.append('<span class="' + timeClass + ' zaikon-order-timer">⏱ ' + self.formatTime(elapsed) + '</span>');
                
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
                var $btn = $(this);
                var orderId = $btn.data('id');
                var newStatus = $btn.data('status');
                var $card = $btn.closest('.zaikon-order-card');
                
                // Disable button to prevent double-click
                if ($btn.prop('disabled')) {
                    return;
                }
                $btn.prop('disabled', true);
                
                // Check if order is delayed (> 5 minutes) and moving from 'new' to 'cooking'
                var createdAt = $card.data('created-at');
                var oldStatus = $card.data('status');
                var elapsedMinutes = self.getElapsedMinutes(createdAt);
                
                if (oldStatus === 'new' && newStatus === 'cooking' && elapsedMinutes > 5) {
                    // Show delay reason modal
                    self.showDelayReasonModal(orderId, newStatus, $btn, $card);
                } else {
                    // Add animation
                    $card.addClass('status-changed');
                    self.updateOrderStatus(orderId, newStatus, $btn);
                }
            });
        },
        
        showDelayReasonModal: function(orderId, newStatus, $btn, $card) {
            var self = this;
            
            // Create modal HTML
            var modalHtml = '<div id="delay-reason-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">' +
                '<div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%;">' +
                '<h2 style="margin: 0 0 20px 0; color: #FF7F00;">Order Delayed - Reason Required</h2>' +
                '<p style="margin: 0 0 20px 0; color: #666;">This order has been waiting for more than 5 minutes. Please select a reason:</p>' +
                '<select id="delay-reason-select" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px;">' +
                '<option value="">-- Select a reason --</option>' +
                '<option value="High Volume">High Volume</option>' +
                '<option value="Ingredient Issue">Ingredient Issue</option>' +
                '<option value="Staff Shortage">Staff Shortage</option>' +
                '<option value="Other">Other (specify below)</option>' +
                '</select>' +
                '<textarea id="delay-reason-other" placeholder="Additional details..." style="width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 6px; min-height: 60px; font-size: 14px;"></textarea>' +
                '<div style="display: flex; gap: 10px; justify-content: flex-end;">' +
                '<button id="delay-reason-cancel" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>' +
                '<button id="delay-reason-submit" style="padding: 10px 20px; border: none; background: #FF7F00; color: white; border-radius: 6px; cursor: pointer; font-weight: bold;">Continue</button>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(modalHtml);
            
            // Handle cancel
            $('#delay-reason-cancel').on('click', function() {
                $('#delay-reason-modal').remove();
                $btn.prop('disabled', false);
            });
            
            // Handle submit
            $('#delay-reason-submit').on('click', function() {
                var reason = $('#delay-reason-select').val();
                var otherText = $('#delay-reason-other').val().trim();
                
                if (!reason) {
                    alert('Please select a reason.');
                    return;
                }
                
                var fullReason = reason;
                if (otherText) {
                    fullReason += ': ' + otherText;
                }
                
                $('#delay-reason-modal').remove();
                $card.addClass('status-changed');
                self.updateOrderStatus(orderId, newStatus, $btn, fullReason);
            });
        },
        
        updateOrderStatus: function(orderId, status, $btn, delayReason) {
            var self = this;
            
            ZAIKON_Toast.info('Updating order status...');
            
            var requestData = { status: status };
            if (delayReason) {
                requestData.delay_reason = delayReason;
            }
            
            $.ajax({
                url: rposKdsData.restUrl + 'orders/' + orderId,
                method: 'PUT',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposKdsData.nonce);
                },
                data: JSON.stringify(requestData),
                success: function() {
                    var statusLabels = {
                        'cooking': 'Cooking Started',
                        'ready': 'Order Ready',
                        'completed': 'Order Completed'
                    };
                    ZAIKON_Toast.success(statusLabels[status] || 'Status Updated');
                    
                    // Immediately refresh orders to show changes
                    self.loadOrders();
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Failed to update order status';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage += ': ' + xhr.responseJSON.message;
                    }
                    ZAIKON_Toast.error(errorMessage);
                },
                complete: function() {
                    // Re-enable button
                    if ($btn) {
                        $btn.prop('disabled', false);
                    }
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
