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
        // Check for null/undefined before parsing
        if (value === null || value === undefined || value === '') {
            value = 0;
        }
        
        var num = parseFloat(value);
        if (isNaN(num)) {
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
        searchTerm: '',
        deliveryData: null,
        notificationInterval: null,
        lastNotificationCheck: null,
        
        /**
         * Configuration constants
         * RIDER_ASSIGNMENT_DELAY_MS: Delay in milliseconds before showing rider assignment popup
         *                           after receipt modal. This gives time for the receipt to render
         *                           and be visible to the user before the rider assignment overlay
         *                           appears, improving UX by avoiding UI clash.
         */
        RIDER_ASSIGNMENT_DELAY_MS: 1000,
        
        init: function() {
            if ($('.rpos-pos-screen').length || $('.zaikon-pos-screen').length) {
                var self = this;
                this.loadProducts();
                this.bindEvents();
                this.initNotifications();
                this.initNotificationSound();
                // Initialize COD option visibility (hide by default since default is "dine-in")
                this.toggleCODOption(false);
                
                // Cleanup intervals on page unload to prevent memory leaks
                $(window).on('beforeunload', function() {
                    if (self.notificationInterval) {
                        clearInterval(self.notificationInterval);
                        self.notificationInterval = null;
                    }
                });
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
            
            // Sidebar Navigation Buttons
            $('.zaikon-sidebar-btn').on('click', function() {
                var buttonId = $(this).attr('id');
                
                // Handle search separately - don't change active state
                if (buttonId === 'zaikon-sidebar-search') {
                    // Toggle search panel
                    $('#zaikon-search-panel').toggleClass('active');
                    if ($('#zaikon-search-panel').hasClass('active')) {
                        $('#zaikon-product-search').focus();
                    }
                    return;
                }
                
                // Handle notification bell
                if (buttonId === 'zaikon-sidebar-notification') {
                    // Trigger the existing notification bell functionality
                    $('#rpos-notification-bell').trigger('click');
                    return;
                }
                
                // Handle expenses button
                if (buttonId === 'zaikon-sidebar-expenses') {
                    // Trigger the existing expenses button functionality
                    $('#rpos-expenses-btn').trigger('click');
                    return;
                }
                
                // Handle orders button
                if (buttonId === 'zaikon-sidebar-orders-btn') {
                    // Trigger the existing orders button functionality
                    $('#rpos-orders-btn').trigger('click');
                    return;
                }
                
                // Handle delivery tracking button
                if (buttonId === 'zaikon-sidebar-delivery-tracking') {
                    // Open delivery tracking modal
                    self.showDeliveryTrackingModal();
                    return;
                }
            });
            
            // Close search panel button
            $('#zaikon-search-panel-close').on('click', function() {
                $('#zaikon-search-panel').removeClass('active');
            });
            
            // Close search panel when clicking outside
            $(document).on('click', function(e) {
                var $target = $(e.target);
                if (!$target.closest('#zaikon-search-panel').length && 
                    !$target.closest('#zaikon-sidebar-search').length &&
                    $('#zaikon-search-panel').hasClass('active')) {
                    $('#zaikon-search-panel').removeClass('active');
                }
            });
            
            // Category scroll arrows
            $('#zaikon-scroll-categories-left').on('click', function() {
                var container = $('.zaikon-categories-wrapper');
                container.animate({
                    scrollLeft: container.scrollLeft() - 200
                }, 300);
            });
            
            $('#zaikon-scroll-categories-right').on('click', function() {
                var container = $('.zaikon-categories-wrapper');
                container.animate({
                    scrollLeft: container.scrollLeft() + 200
                }, 300);
            });
            
            // Update scroll arrows visibility
            function updateScrollArrows() {
                var container = $('.zaikon-categories-wrapper');
                var scrollLeft = container.scrollLeft();
                var maxScroll = container[0].scrollWidth - container[0].clientWidth;
                
                if (scrollLeft > 0) {
                    $('#zaikon-scroll-categories-left').show();
                } else {
                    $('#zaikon-scroll-categories-left').hide();
                }
                
                if (scrollLeft < maxScroll - 1) {
                    $('#zaikon-scroll-categories-right').show();
                } else {
                    $('#zaikon-scroll-categories-right').hide();
                }
            }
            
            $('.zaikon-categories-wrapper').on('scroll', updateScrollArrows);
            $(window).on('resize', updateScrollArrows);
            updateScrollArrows();
            
            // Product search functionality
            $('#zaikon-product-search').on('input', function() {
                self.searchTerm = $(this).val().toLowerCase();
                self.renderProducts();
            });
            
            // Order Type Pills - Changed to dropdown
            $('#rpos-order-type').on('change', function() {
                var orderType = $(this).val();
                
                // Toggle COD option based on order type
                self.toggleCODOption(orderType === 'delivery');
                
                // If delivery is selected, show inline delivery panel
                if (orderType === 'delivery') {
                    // Show the inline delivery panel
                    self.openDeliveryPanel();
                } else {
                    // Clear delivery data if switching away from delivery
                    self.deliveryData = null;
                    $('#zaikon-delivery-panel').fadeOut(200);
                    self.updateTotals();
                }
            });
            
            // Save Delivery Details handler
            $('#zaikon-save-delivery').on('click', function() {
                self.saveDeliveryDetails();
            });
            
            // Cancel Delivery handler
            $('#zaikon-cancel-delivery').on('click', function() {
                self.cancelDelivery();
            });
            
            // Delivery Modal close button handler
            $('#zaikon-delivery-modal-close').on('click', function() {
                self.cancelDelivery();
            });
            
            // Delivery Area change handler
            $('#zaikon-delivery-area').on('change', function() {
                self.onDeliveryAreaChange();
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
                self.deliveryCalculation = null;
                self.renderCart();
                $('#rpos-receipt-modal').fadeOut();
                $('#rpos-cash-received').val('');
                $('#rpos-discount').val('0.00');
                $('#rpos-order-type').val('dine-in');
                $('#rpos-special-instructions').val('');
                
                // Clear and hide delivery panel
                $('#zaikon-delivery-panel').hide();
                $('#zaikon-delivery-phone').val('');
                $('#zaikon-delivery-name').val('');
                $('#zaikon-delivery-area').val('');
                $('#zaikon-delivery-distance').val('');
                $('#zaikon-delivery-charge').val('');
                $('#zaikon-delivery-instructions').val('');
                $('#zaikon-delivery-rider').val('');
                $('#zaikon-free-delivery-badge').hide();
            });
            
            // Print rider slip
            $('#rpos-print-rider-slip').on('click', function() {
                self.printRiderSlip();
            });
            
            // Share receipt button handler
            $('#zaikon-share-receipt').on('click', function() {
                // Get receipt data with validation
                const orderNumber = $('#receipt-order-number').text().trim();
                const total = $('#receipt-total').text().trim();
                const restaurantName = $('#receipt-restaurant-name').text().trim();
                
                // Validate that we have the required data
                if (!orderNumber || !total || !restaurantName) {
                    ZAIKON_Toast.error('Receipt data not available');
                    return;
                }
                
                const shareText = `Receipt from ${restaurantName}\nOrder: ${orderNumber}\nTotal: ${total}`;
                
                // Check if Web Share API is available
                if (navigator.share) {
                    navigator.share({
                        title: 'Receipt - ' + orderNumber,
                        text: shareText
                    }).catch(function(error) {
                        console.error('Error sharing:', error);
                    });
                } else {
                    // Fallback: Copy to clipboard
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareText).then(function() {
                            ZAIKON_Toast.success('Receipt copied to clipboard!');
                        }).catch(function(error) {
                            console.error('Error copying to clipboard:', error);
                            ZAIKON_Toast.error('Failed to copy receipt to clipboard');
                        });
                    } else {
                        // Older browser fallback - create temporary textarea
                        const textArea = document.createElement('textarea');
                        textArea.value = shareText;
                        textArea.style.position = 'absolute';
                        textArea.style.top = '-9999px';
                        textArea.style.left = '-9999px';
                        textArea.style.opacity = '0';
                        document.body.appendChild(textArea);
                        textArea.select();
                        try {
                            const successful = document.execCommand('copy');
                            if (successful) {
                                ZAIKON_Toast.success('Receipt copied to clipboard!');
                            } else {
                                ZAIKON_Toast.error('Failed to copy receipt to clipboard');
                            }
                        } catch (error) {
                            console.error('Error copying to clipboard:', error);
                            ZAIKON_Toast.error('Failed to copy receipt to clipboard');
                        }
                        document.body.removeChild(textArea);
                    }
                }
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
            
            // Filter by category first
            var filtered = this.currentCategory === 0 
                ? this.products 
                : this.products.filter(function(p) { return p.category_id == self.currentCategory; });
            
            // Then filter by search term if present
            if (this.searchTerm && this.searchTerm.length > 0) {
                filtered = filtered.filter(function(p) {
                    return p.name.toLowerCase().includes(self.searchTerm) || 
                           (p.description && p.description.toLowerCase().includes(self.searchTerm));
                });
            }
            
            $grid.empty();
            
            if (filtered.length === 0) {
                $grid.append('<div class="zaikon-no-products">No products found</div>');
                return;
            }
            
            // Batch DOM updates: create all items first, then append once
            var $fragment = $(document.createDocumentFragment());
            
            filtered.forEach(function(product) {
                var $item = $('<div class="zaikon-product-card zaikon-animate-fadeIn">')
                    .data('product', product)
                    .on('click', function() {
                        $(this).addClass('zaikon-animate-scaleIn');
                        self.addToCart(product);
                    });
                
                // Add stock badge if stock info available and positive
                if (product.stock_quantity !== undefined && product.stock_quantity !== null && product.stock_quantity > 0) {
                    var $stockBadge = $('<div class="zaikon-product-stock-badge">').text(product.stock_quantity);
                    $item.append($stockBadge);
                }
                
                // Add product image with circular wrapper
                var $imageWrapper = $('<div class="zaikon-product-image-wrapper">');
                var $imageCircle = $('<div class="zaikon-product-image-circle">');
                
                if (product.image_url) {
                    var $img = $('<img class="zaikon-product-image">')
                        .attr('src', product.image_url)
                        .attr('alt', product.name);
                    $imageCircle.append($img);
                } else {
                    $imageCircle.append('<span class="dashicons dashicons-cart" style="font-size: 40px; color: rgba(255,255,255,0.5);"></span>');
                }
                
                $imageWrapper.append($imageCircle);
                $item.append($imageWrapper);
                
                var $info = $('<div class="zaikon-product-info">');
                
                // Create product name element with safe text insertion
                var $name = $('<div class="zaikon-product-name">').text(product.name);
                $info.append($name);
                
                // Add product description if available (safe text insertion)
                if (product.description && product.description.trim() !== '') {
                    var $description = $('<div class="zaikon-product-description">').text(product.description);
                    $info.append($description);
                }
                
                // Create price element with safe content
                var $price = $('<div class="zaikon-product-price">').text(rposData.currency + parseFloat(product.selling_price).toFixed(2));
                $info.append($price);
                
                $item.append($info);
                
                $fragment.append($item);
            });
            
            // Single DOM update instead of multiple appends
            $grid.append($fragment);
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
            
            // Batch DOM updates: create all items first, then append once
            var $fragment = $(document.createDocumentFragment());
            
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
                
                $fragment.append($item);
            });
            
            // Single DOM update instead of multiple appends
            $container.append($fragment);
            
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
            // More strict regex that only allows digits and single decimal point
            var totalText = $('#rpos-total').text().replace(/[^\d.]/g, '');
            var total = parseFloat(totalText) || 0;
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
            var paymentType = $('#rpos-payment-type').val() || 'cash';
            
            // Only validate cash received for cash payments
            if (paymentType === 'cash' && cashReceived < total) {
                ZAIKON_Toast.error('Cash received is less than total amount');
                return;
            }
            
            // For COD and online, set cash received to 0
            if (paymentType !== 'cash') {
                cashReceived = 0;
            }
            
            var changeDue = paymentType === 'cash' ? (cashReceived - total) : 0;
            // Kitchen special instructions from the right sidebar
            var kitchenInstructions = $('#rpos-special-instructions').val().trim();
            
            // Determine payment status based on payment type
            // - Cash/Online: Paid immediately at POS
            // - COD: Unpaid until delivery rider collects payment
            var paymentStatus = 'paid';  // Default
            if (paymentType === 'cash' || paymentType === 'online') {
                paymentStatus = 'paid';
            } else if (paymentType === 'cod') {
                paymentStatus = 'unpaid';
            }
            
            var orderData = {
                subtotal: subtotal,
                discount: discount,
                total: total,
                cash_received: cashReceived,
                change_due: changeDue,
                status: 'new',
                order_type: orderType,
                payment_type: paymentType,
                payment_status: paymentStatus,
                special_instructions: kitchenInstructions,
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
                orderData.delivery_instructions = this.deliveryData.delivery_instructions || '';
                orderData.rider_id = this.deliveryData.rider_id !== undefined && this.deliveryData.rider_id !== null ? this.deliveryData.rider_id : null;
            }
            
            // Debug logging for delivery orders
            if (orderType === 'delivery') {
                console.log('Creating delivery order with:', {
                    order_type: orderData.order_type,
                    is_delivery: orderData.is_delivery,
                    has_delivery_data: !!this.deliveryData,
                    customer_name: orderData.customer_name ? '***' : 'missing',
                    area_id: orderData.area_id
                });
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
                    
                    // After showing receipt, offer rider assignment for delivery orders ONLY if no rider was assigned
                    if (orderData.order_type === 'delivery' && !orderData.rider_id && window.RiderAssignment) {
                        var deliveryInfo = {
                            customerName: orderData.customer_name || '',
                            customerPhone: orderData.customer_phone || '',
                            locationName: orderData.location_name || '',
                            distanceKm: orderData.distance_km || 0
                        };
                        // Small delay to let receipt modal show first
                        setTimeout(function() {
                            RiderAssignment.showPopup(response.id, response.order_number, deliveryInfo);
                        }, self.RIDER_ASSIGNMENT_DELAY_MS);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Order creation failed:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    var errorMsg = 'Failed to create order';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg += ': ' + xhr.responseJSON.message;
                    }
                    ZAIKON_Toast.error(errorMsg);
                }
            });
        },
        
        initNotificationSound: function() {
            // Create audio element for notification sound
            if (!document.getElementById('pos-notification-sound')) {
                var audio = document.createElement('audio');
                audio.id = 'pos-notification-sound';
                
                // Use custom notification sound if configured, otherwise use default
                var soundUrl = (typeof rposAdmin !== 'undefined' && rposAdmin.notificationSoundUrl) 
                    ? rposAdmin.notificationSoundUrl 
                    : NOTIFICATION_SOUND_DATA;
                    
                // Detect sound type based on URL pattern
                var soundType = 'audio/wav'; // default
                if (soundUrl.indexOf('data:audio/wav') === 0) {
                    soundType = 'audio/wav';
                } else if (soundUrl.match(/\.(mp3|mpeg)$/i) || soundUrl.indexOf('audio/mpeg') !== -1) {
                    soundType = 'audio/mpeg';
                } else if (soundUrl.match(/\.wav$/i) || soundUrl.indexOf('audio/wav') !== -1) {
                    soundType = 'audio/wav';
                }
                
                audio.innerHTML = '<source src="' + soundUrl + '" type="' + soundType + '" />';
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
            
            // Poll every 10 seconds, but only when tab is visible
            this.notificationInterval = setInterval(function() {
                // Skip polling if document is hidden (tab not active)
                if (document.hidden) {
                    return;
                }
                self.loadNotifications();
            }, 10000);
            
            // Bind notification bell click
            $('#rpos-notification-bell').on('click', function() {
                self.toggleNotificationDropdown();
            });
            
            // Bind mark all as read
            $('#rpos-mark-all-read').on('click', function() {
                self.markAllAsRead();
            });
            
            // Bind View Order button
            $(document).on('click', '.rpos-view-order-btn', function(e) {
                e.stopPropagation();
                var orderId = $(this).data('order-id');
                var orderIdNum = parseInt(orderId, 10);
                if (orderIdNum > 0) {
                    self.showOrderDetailModal(orderIdNum);
                } else {
                    console.error('Invalid order ID:', orderId);
                }
            });
            
            // Bind Dismiss button
            $(document).on('click', '.rpos-dismiss-notification-btn', function(e) {
                e.stopPropagation();
                var notificationId = $(this).data('id');
                self.markAsRead(notificationId);
            });
            
            // Bind notification modal close buttons
            $('#rpos-notification-close, #rpos-notification-close-btn').on('click', function() {
                $('#rpos-notification-dropdown').fadeOut(200);
            });
            
            // Bind order detail modal close buttons
            $('#rpos-order-detail-close, #rpos-order-detail-close-btn').on('click', function() {
                $('#rpos-order-detail-modal').fadeOut();
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
                
                // Add action buttons
                var $actions = $('<div class="zaikon-notification-actions">');
                var $viewBtn = $('<button class="zaikon-btn zaikon-btn-sm zaikon-btn-primary rpos-view-order-btn">').text('View Order');
                $viewBtn.attr('data-order-id', notification.order_id);
                var $dismissBtn = $('<button class="zaikon-btn zaikon-btn-sm zaikon-btn-secondary rpos-dismiss-notification-btn">').text('Dismiss');
                $dismissBtn.attr('data-id', notification.id);
                $actions.append($viewBtn, $dismissBtn);
                
                var $markReadBtn = $('<button class="zaikon-notification-mark-read">').text('✕');
                $markReadBtn.attr('data-id', notification.id);
                
                $item.append($content, $actions, $markReadBtn);
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
            var $modal = $('#rpos-notification-dropdown');
            if ($modal.is(':visible')) {
                $modal.fadeOut(200);
            } else {
                $modal.fadeIn(200);
            }
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
        
        showOrderDetailModal: function(orderId) {
            var self = this;
            $('#rpos-order-detail-modal').fadeIn();
            $('#rpos-order-detail-body').html('<div class="zaikon-loading"><div class="zaikon-spinner"></div><p>Loading...</p></div>');
            
            $.ajax({
                url: rposData.restUrl + 'orders/' + orderId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(order) {
                    var $content = $('<div class="order-detail-content">');
                    
                    $content.append($('<p>').append($('<strong>').text('Order Number: ')).append(document.createTextNode(order.order_number)));
                    $content.append($('<p>').append($('<strong>').text('Order Type: ')).append(document.createTextNode((order.order_type || 'Dine-in').replace('-', ' ').toUpperCase())));
                    $content.append($('<p>').append($('<strong>').text('Payment Type: ')).append(document.createTextNode((order.payment_type || 'Cash').toUpperCase())));
                    $content.append($('<p>').append($('<strong>').text('Status: ')).append(document.createTextNode((order.status || '').toUpperCase())));
                    
                    var $itemsSection = $('<div>');
                    $itemsSection.append($('<h4>').text('Items:'));
                    var $itemsList = $('<ul>');
                    if (order.items && order.items.length) {
                        order.items.forEach(function(item) {
                            var $li = $('<li>');
                            $li.text(item.quantity + ' × ' + item.product_name + ' - ' + formatPrice(item.line_total, rposData.currency));
                            $itemsList.append($li);
                        });
                    }
                    $itemsSection.append($itemsList);
                    $content.append($itemsSection);
                    
                    $content.append($('<p>').append($('<strong>').text('Subtotal: ')).append(document.createTextNode(formatPrice(order.subtotal, rposData.currency))));
                    if (order.delivery_charge && parseFloat(order.delivery_charge) > 0) {
                        $content.append($('<p>').append($('<strong>').text('Delivery Charge: ')).append(document.createTextNode(formatPrice(order.delivery_charge, rposData.currency))));
                    }
                    $content.append($('<p>').append($('<strong>').text('Discount: ')).append(document.createTextNode(formatPrice(order.discount, rposData.currency))));
                    $content.append($('<p>').append($('<strong>').text('Total: ')).append(document.createTextNode(formatPrice(order.total, rposData.currency))));
                    
                    $('#order-detail-title').text('Order #' + order.order_number);
                    $('#rpos-order-detail-body').empty().append($content);
                },
                error: function() {
                    $('#rpos-order-detail-body').html('<p style="color: red;">Failed to load order details.</p>');
                }
            });
        },
        
        showDeliveryTrackingModal: function() {
            var self = this;
            
            // Reset modal state
            $('#rpos-tracking-search-input').val('');
            $('#rpos-tracking-result').hide();
            $('#rpos-tracking-phone-results').hide();
            $('#rpos-tracking-error').hide();
            
            // Show modal
            $('#rpos-delivery-tracking-modal').fadeIn();
            $('#rpos-tracking-search-input').focus();
            
            // Bind modal close handlers (unbind first to prevent duplicates)
            $('#rpos-delivery-tracking-close, #rpos-delivery-tracking-close-btn').off('click').on('click', function() {
                $('#rpos-delivery-tracking-modal').fadeOut();
            });
            
            // Helper function to determine if input looks like a phone number
            // Minimum phone length is 7 digits (e.g., local numbers without country code)
            var MIN_PHONE_DIGITS = 7;
            
            function isPhoneNumber(input) {
                // Phone numbers typically start with 0, +, or contain only digits, spaces, and dashes
                // Order numbers typically contain letters like "ORD-"
                var cleaned = input.replace(/[\s\-\+]/g, '');
                // If it's all digits and at least MIN_PHONE_DIGITS characters, treat as phone
                if (new RegExp('^\\d{' + MIN_PHONE_DIGITS + ',}$').test(cleaned)) {
                    return true;
                }
                // If it starts with + followed by digits
                if (/^\+\d+$/.test(input.replace(/[\s\-]/g, ''))) {
                    return true;
                }
                return false;
            }
            
            // Helper function to render phone search results
            function renderPhoneResults(orders) {
                var $list = $('#rpos-phone-orders-list');
                $list.empty();
                
                $('#rpos-phone-results-count').text('(' + orders.length + ' order' + (orders.length > 1 ? 's' : '') + ')');
                
                orders.forEach(function(order) {
                    var statusColor = {
                        'pending': '#f59e0b',
                        'confirmed': '#3b82f6',
                        'cooking': '#8b5cf6',
                        'ready': '#10b981',
                        'dispatched': '#06b6d4',
                        'delivered': '#22c55e',
                        'cancelled': '#ef4444'
                    };
                    
                    var html = '<div class="rpos-phone-order-item" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 12px;">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">';
                    html += '<div>';
                    html += '<strong style="font-size: 16px; color: #1f2937;">#' + order.order_number + '</strong>';
                    html += '<div style="font-size: 13px; color: #6b7280; margin-top: 4px;">' + order.customer_name + ' • ' + order.customer_phone + '</div>';
                    if (order.location_name) {
                        html += '<div style="font-size: 12px; color: #9ca3af; margin-top: 2px;">📍 ' + order.location_name + '</div>';
                    }
                    html += '</div>';
                    html += '<div style="text-align: right;">';
                    html += '<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: ' + (statusColor[order.order_status] || '#6b7280') + '20; color: ' + (statusColor[order.order_status] || '#6b7280') + ';">' + order.order_status + '</span>';
                    html += '<div style="font-size: 14px; font-weight: 600; color: #1f2937; margin-top: 6px;">Rs ' + parseFloat(order.grand_total_rs || 0).toFixed(2) + '</div>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div style="display: flex; gap: 8px; flex-wrap: wrap;">';
                    html += '<button class="zaikon-btn zaikon-btn-info zaikon-btn-sm rpos-copy-phone-link" data-url="' + order.tracking_url + '" style="flex: 1; min-width: 80px; padding: 8px 12px; font-size: 12px;">';
                    html += '<span class="dashicons dashicons-admin-page" style="font-size: 14px; vertical-align: middle;"></span> Copy';
                    html += '</button>';
                    html += '<button class="zaikon-btn zaikon-btn-success zaikon-btn-sm rpos-whatsapp-phone-link" data-url="' + order.tracking_url + '" data-order="' + order.order_number + '" style="flex: 1; min-width: 80px; padding: 8px 12px; font-size: 12px;">';
                    html += '<span class="dashicons dashicons-whatsapp" style="font-size: 14px; vertical-align: middle;"></span> WhatsApp';
                    html += '</button>';
                    html += '<button class="zaikon-btn zaikon-btn-primary zaikon-btn-sm rpos-open-phone-link" data-url="' + order.tracking_url + '" style="flex: 1; min-width: 80px; padding: 8px 12px; font-size: 12px;">';
                    html += '<span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span> Track';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
                    
                    $list.append(html);
                });
                
                // Bind click handlers for dynamically created buttons
                $list.find('.rpos-copy-phone-link').off('click').on('click', function() {
                    var url = $(this).data('url');
                    self.copyToClipboard(url);
                    ZAIKON_Toast.success('Tracking link copied!');
                });
                
                $list.find('.rpos-whatsapp-phone-link').off('click').on('click', function() {
                    var url = $(this).data('url');
                    var orderNum = $(this).data('order');
                    var message = encodeURIComponent('Track your order #' + orderNum + ': ' + url);
                    window.open('https://wa.me/?text=' + message, '_blank');
                });
                
                $list.find('.rpos-open-phone-link').off('click').on('click', function() {
                    var url = $(this).data('url');
                    window.open(url, '_blank');
                });
                
                $('#rpos-tracking-phone-results').show();
            }
            
            // Get tracking link button handler
            $('#rpos-get-tracking-link').off('click').on('click', function() {
                var searchInput = $('#rpos-tracking-search-input').val().trim();
                
                if (!searchInput) {
                    $('#rpos-tracking-error-msg').text('Please enter an order number or phone number');
                    $('#rpos-tracking-error').show();
                    $('#rpos-tracking-result').hide();
                    $('#rpos-tracking-phone-results').hide();
                    return;
                }
                
                // Hide previous results
                $('#rpos-tracking-result').hide();
                $('#rpos-tracking-phone-results').hide();
                $('#rpos-tracking-error').hide();
                
                // Show loading state
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update zaikon-spin"></span> Searching...');
                
                // Determine search type
                var isPhone = isPhoneNumber(searchInput);
                var apiUrl;
                
                if (isPhone) {
                    // Phone number search
                    apiUrl = rposData.zaikonRestUrl + 'orders/by-phone/' + encodeURIComponent(searchInput) + '/tracking';
                } else {
                    // Order number search
                    apiUrl = rposData.zaikonRestUrl + 'orders/by-number/' + encodeURIComponent(searchInput) + '/tracking-url';
                }
                
                // Fetch tracking data from API
                $.ajax({
                    url: apiUrl,
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                    },
                    success: function(response) {
                        if (isPhone) {
                            // Phone search returns multiple orders
                            if (response.success && response.orders && response.orders.length > 0) {
                                renderPhoneResults(response.orders);
                                ZAIKON_Toast.success('Found ' + response.orders.length + ' delivery order(s)!');
                            } else {
                                $('#rpos-tracking-error-msg').text('No delivery orders found for this phone number.');
                                $('#rpos-tracking-error').show();
                            }
                        } else {
                            // Order number search returns single order
                            if (response.success && response.tracking_url) {
                                // Display tracking information
                                $('#rpos-tracking-order-num').text(response.order_number);
                                $('#rpos-tracking-order-type').text((response.order_type || 'N/A').toUpperCase());
                                $('#rpos-tracking-order-status').text((response.order_status || 'pending').toUpperCase());
                                $('#rpos-tracking-url').text(response.tracking_url);
                                
                                // Store tracking URL for button actions
                                $('#rpos-tracking-result').data('tracking-url', response.tracking_url);
                                
                                $('#rpos-tracking-result').show();
                                ZAIKON_Toast.success('Tracking link retrieved successfully!');
                            } else {
                                $('#rpos-tracking-error-msg').text(response.message || 'Failed to get tracking link');
                                $('#rpos-tracking-error').show();
                            }
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Failed to retrieve tracking information';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.status === 404) {
                            errorMsg = isPhone ? 'No delivery orders found for this phone number.' : 'Order not found. Please check the order number.';
                        }
                        $('#rpos-tracking-error-msg').text(errorMsg);
                        $('#rpos-tracking-error').show();
                        ZAIKON_Toast.error(errorMsg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            });
            
            // Copy tracking link button handler
            $('#rpos-copy-tracking-link').off('click').on('click', function() {
                var trackingUrl = $('#rpos-tracking-result').data('tracking-url');
                self.copyToClipboard(trackingUrl);
                ZAIKON_Toast.success('Tracking link copied to clipboard!');
            });
            
            // Share via WhatsApp button handler
            $('#rpos-share-whatsapp').off('click').on('click', function() {
                var trackingUrl = $('#rpos-tracking-result').data('tracking-url');
                var orderNumber = $('#rpos-tracking-order-num').text();
                var message = encodeURIComponent('Track your order #' + orderNumber + ': ' + trackingUrl);
                var whatsappUrl = 'https://wa.me/?text=' + message;
                window.open(whatsappUrl, '_blank');
            });
            
            // Open tracking page button handler
            $('#rpos-open-tracking').off('click').on('click', function() {
                var trackingUrl = $('#rpos-tracking-result').data('tracking-url');
                window.open(trackingUrl, '_blank');
            });
            
            // Allow Enter key to submit
            $('#rpos-tracking-search-input').off('keypress').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#rpos-get-tracking-link').click();
                }
            });
        },
        
        // Helper function to copy text to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).catch(function(error) {
                    console.error('Error copying to clipboard:', error);
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'absolute';
                textArea.style.left = '-9999px';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                } catch (err) {
                    console.error('Fallback: Could not copy text:', err);
                }
                document.body.removeChild(textArea);
            }
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
                
                // Auto-generate tracking link for delivery orders
                if (order && order.tracking_url) {
                    console.log('📍 Tracking Link Generated:', order.tracking_url);
                    console.log('📱 Share this link with customer for order tracking');
                }
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
                if (this.deliveryData && this.deliveryData.rider_name) {
                    deliveryInfo += '<br><strong>Rider:</strong> ' + this.deliveryData.rider_name;
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
                var deliveryLabelText = 'Delivery Charge:';
                if (orderData.is_free_delivery) {
                    deliveryLabelText += ' <span style="color: green;">(FREE)</span>';
                }
                $('#receipt-delivery-charge-label').html(deliveryLabelText);
                $('#receipt-delivery-charge').text(formatPrice(deliveryCharge, rposData.currency));
                $('#receipt-delivery-charge-row').css('display', 'flex');
            } else {
                $('#receipt-delivery-charge-row').hide();
            }
            
            $('#receipt-discount').text(formatPrice(orderData.discount, rposData.currency));
            $('#receipt-total').text(formatPrice(orderData.total, rposData.currency));
            $('#receipt-cash').text(formatPrice(orderData.cash_received, rposData.currency));
            $('#receipt-change').text(formatPrice(orderData.change_due, rposData.currency));
            $('#receipt-footer-message').text(rposData.receiptFooterMessage || 'Thank you for your order!');
            $('#receipt-cashier').text('Cashier: ' + rposData.currentUser);
            
            $('#rpos-receipt-modal').fadeIn();
        },
        
        /**
         * Fetch and copy tracking link to clipboard
         * @param {string} orderNumber - The order number
         * @param {jQuery} $button - Optional button to show loading state
         * @returns {Promise}
         */
        getAndCopyTrackingLink: function(orderNumber, $button) {
            var originalHtml = $button ? $button.html() : null;
            
            if ($button) {
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update zaikon-spin"></span> Getting Link...');
            }
            
            return $.ajax({
                url: rposData.zaikonRestUrl + 'orders/by-number/' + encodeURIComponent(orderNumber) + '/tracking-url',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(response) {
                    if (response.success && response.tracking_url) {
                        var successMsg = 'Tracking link copied to clipboard!\n\n' + response.tracking_url;
                        var warningMsg = 'Tracking link:\n' + response.tracking_url;
                        
                        // Copy tracking URL to clipboard
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(response.tracking_url).then(function() {
                                ZAIKON_Toast.success(successMsg, 5000);
                            }).catch(function(error) {
                                console.error('Error copying to clipboard:', error);
                                ZAIKON_Toast.warning(warningMsg, 8000);
                            });
                        } else {
                            // Fallback for older browsers
                            var textArea = document.createElement('textarea');
                            textArea.value = response.tracking_url;
                            textArea.style.position = 'absolute';
                            textArea.style.left = '-9999px';
                            document.body.appendChild(textArea);
                            textArea.select();
                            try {
                                document.execCommand('copy');
                                ZAIKON_Toast.success(successMsg, 5000);
                            } catch (err) {
                                ZAIKON_Toast.warning(warningMsg, 8000);
                            }
                            document.body.removeChild(textArea);
                        }
                    } else {
                        ZAIKON_Toast.error(response.message || 'Failed to get tracking link');
                    }
                },
                error: function(xhr) {
                    var errorMsg = 'Failed to retrieve tracking link';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMsg = 'Order not found. Please check the order number.';
                    }
                    ZAIKON_Toast.error(errorMsg);
                },
                complete: function() {
                    if ($button && originalHtml) {
                        $button.prop('disabled', false).html(originalHtml);
                    }
                }
            });
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
            if (orderData.delivery_instructions && orderData.delivery_instructions.trim()) {
                slipContent += '<div class="row"><span class="label">Instructions:</span> ' + orderData.delivery_instructions + '</div>';
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
        },
        
        /**
         * Open inline delivery panel
         */
        openDeliveryPanel: function() {
            var self = this;
            
            // Show delivery panel with animation (modal behavior)
            $('#zaikon-delivery-panel').fadeIn(200);
            
            // Load delivery areas
            $.ajax({
                url: rposData.zaikonRestUrl + 'delivery-areas?active_only=true',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(areas) {
                    var $areaSelect = $('#zaikon-delivery-area');
                    $areaSelect.find('option:not(:first)').remove();
                    
                    areas.forEach(function(area) {
                        $areaSelect.append('<option value="' + area.id + '" data-distance="' + area.distance_km + '">' + area.name + '</option>');
                    });
                },
                error: function() {
                    ZAIKON_Toast.error('Failed to load delivery areas');
                }
            });
            
            // Load active riders
            $.ajax({
                url: rposData.restUrl + 'riders/active',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(riders) {
                    var $riderSelect = $('#zaikon-delivery-rider');
                    $riderSelect.find('option:not(:first)').remove();
                    
                    riders.forEach(function(rider) {
                        $riderSelect.append('<option value="' + rider.id + '">' + rider.name + '</option>');
                    });
                },
                error: function() {
                    ZAIKON_Toast.error('Failed to load riders');
                }
            });
        },
        
        /**
         * Handle delivery area change - calculate delivery charges
         */
        onDeliveryAreaChange: function() {
            var self = this;
            var $areaSelect = $('#zaikon-delivery-area');
            var selectedAreaId = $areaSelect.val();
            
            if (!selectedAreaId) {
                // Clear fields if no area selected
                $('#zaikon-delivery-distance').val('');
                $('#zaikon-delivery-charge').val('');
                $('#zaikon-free-delivery-badge').hide();
                return;
            }
            
            var selectedOption = $areaSelect.find('option:selected');
            var distanceKm = selectedOption.data('distance');
            var subtotal = this.calculateSubtotal();
            
            // Call REST API to calculate delivery charges
            $.ajax({
                url: rposData.zaikonRestUrl + 'calc-delivery-charges',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify({
                    location_id: parseInt(selectedAreaId),
                    items_subtotal_rs: subtotal
                }),
                success: function(response) {
                    // Populate distance and charge fields
                    $('#zaikon-delivery-distance').val(response.distance_km + ' km');
                    $('#zaikon-delivery-charge').val(rposData.currency + response.delivery_charges_rs.toFixed(2));
                    
                    // Show/hide free delivery badge
                    if (response.is_free_delivery) {
                        $('#zaikon-free-delivery-badge').show();
                    } else {
                        $('#zaikon-free-delivery-badge').hide();
                    }
                    
                    // Store calculation result for later use
                    self.deliveryCalculation = response;
                },
                error: function() {
                    ZAIKON_Toast.error('Failed to calculate delivery charge');
                }
            });
        },
        
        /**
         * Save delivery details
         */
        saveDeliveryDetails: function() {
            var self = this;
            
            // Validate required fields
            var phone = $('#zaikon-delivery-phone').val().trim();
            var name = $('#zaikon-delivery-name').val().trim();
            var areaId = $('#zaikon-delivery-area').val();
            
            if (!phone) {
                ZAIKON_Toast.error('Please enter customer phone number');
                $('#zaikon-delivery-phone').focus();
                return;
            }
            
            if (!name) {
                ZAIKON_Toast.error('Please enter customer name');
                $('#zaikon-delivery-name').focus();
                return;
            }
            
            if (!areaId) {
                ZAIKON_Toast.error('Please select delivery area');
                $('#zaikon-delivery-area').focus();
                return;
            }
            
            if (!this.deliveryCalculation) {
                ZAIKON_Toast.error('Please wait for delivery charge calculation');
                return;
            }
            
            // Get selected area name and rider
            var areaName = $('#zaikon-delivery-area option:selected').text();
            var riderId = $('#zaikon-delivery-rider').val();
            var riderName = riderId ? $('#zaikon-delivery-rider option:selected').text() : null;
            var instructions = $('#zaikon-delivery-instructions').val().trim();
            
            // Build delivery data object
            this.deliveryData = {
                is_delivery: 1,
                area_id: parseInt(areaId),
                location_name: areaName,
                distance_km: this.deliveryCalculation.distance_km,
                delivery_charge: this.deliveryCalculation.delivery_charges_rs,
                is_free_delivery: this.deliveryCalculation.is_free_delivery ? 1 : 0,
                customer_name: name,
                customer_phone: phone,
                delivery_instructions: instructions,
                rider_id: (riderId && riderId !== '') ? parseInt(riderId) : null,
                rider_name: riderName
            };
            
            // Update order type (already set to delivery)
            $('#rpos-order-type').val('delivery');
            
            // Hide delivery panel
            $('#zaikon-delivery-panel').fadeOut(200);
            
            // Update totals to show delivery charge
            this.updateTotals();
            
            ZAIKON_Toast.success('Delivery details saved');
        },
        
        /**
         * Cancel delivery
         */
        cancelDelivery: function() {
            // Clear delivery data
            this.deliveryData = null;
            this.deliveryCalculation = null;
            
            // Hide delivery panel
            $('#zaikon-delivery-panel').fadeOut(200);
            
            // Clear all fields
            $('#zaikon-delivery-phone').val('');
            $('#zaikon-delivery-name').val('');
            $('#zaikon-delivery-area').val('');
            $('#zaikon-delivery-distance').val('');
            $('#zaikon-delivery-charge').val('');
            $('#zaikon-delivery-instructions').val('');
            $('#zaikon-delivery-rider').val('');
            $('#zaikon-free-delivery-badge').hide();
            
            // Reset order type to previous or dine-in
            var currentType = $('#rpos-order-type').val();
            if (currentType === 'delivery') {
                $('#rpos-order-type').val('dine-in');
                // Hide COD option when switching away from delivery
                this.toggleCODOption(false);
            }
            
            // Update totals
            this.updateTotals();
            
            ZAIKON_Toast.info('Delivery cancelled');
        },
        
        /**
         * Toggle COD (Cash on Delivery) payment option visibility
         * @param {boolean} show - true to show COD option, false to hide it
         */
        toggleCODOption: function(show) {
            var $paymentTypeSelect = $('#rpos-payment-type');
            var $codOption = $paymentTypeSelect.find('option[value="cod"]');
            
            // Validate elements exist before manipulating
            if (!$paymentTypeSelect.length || !$codOption.length) {
                console.warn('Payment type select or COD option not found');
                return;
            }
            
            if (show) {
                // Show COD option
                $codOption.show();
            } else {
                // Hide COD option
                $codOption.hide();
                
                // If COD is currently selected, switch to cash and trigger change event
                if ($paymentTypeSelect.val() === 'cod') {
                    $paymentTypeSelect.val('cash').trigger('change');
                }
            }
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
                var self = this;
                this.loadOrders();
                this.bindEvents();
                this.startAutoRefresh();
                this.startTimers();
                this.initNotificationSound();
                
                // Cleanup intervals on page unload to prevent memory leaks
                $(window).on('beforeunload', function() {
                    if (self.timerInterval) {
                        clearInterval(self.timerInterval);
                        self.timerInterval = null;
                    }
                    if (self.autoRefreshInterval) {
                        clearInterval(self.autoRefreshInterval);
                        self.autoRefreshInterval = null;
                    }
                });
            }
        },
        
        initNotificationSound: function() {
            // Create audio element for new order notification
            if (!document.getElementById('kds-notification-sound')) {
                var audio = document.createElement('audio');
                audio.id = 'kds-notification-sound';
                
                // Use custom notification sound if configured, otherwise use default
                var soundUrl = (typeof rposAdmin !== 'undefined' && rposAdmin.notificationSoundUrl) 
                    ? rposAdmin.notificationSoundUrl 
                    : NOTIFICATION_SOUND_DATA;
                    
                // Detect sound type based on URL pattern
                var soundType = 'audio/wav'; // default
                if (soundUrl.indexOf('data:audio/wav') === 0) {
                    soundType = 'audio/wav';
                } else if (soundUrl.match(/\.(mp3|mpeg)$/i) || soundUrl.indexOf('audio/mpeg') !== -1) {
                    soundType = 'audio/mpeg';
                } else if (soundUrl.match(/\.wav$/i) || soundUrl.indexOf('audio/wav') !== -1) {
                    soundType = 'audio/wav';
                }
                
                audio.innerHTML = '<source src="' + soundUrl + '" type="' + soundType + '" />';
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
            
            // Database now stores timestamps in UTC format (after fix)
            // Parse the MySQL datetime string as UTC and calculate elapsed time
            // The 'Z' suffix tells JavaScript to parse as UTC time
            var created = new Date(createdAt.replace(' ', 'T') + 'Z');
            
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
                    
                    // Filter orders for KDS display
                    // zaikon_orders uses: pending, confirmed, cooking, ready, dispatched, delivered, active, completed, cancelled
                    // KDS should show: pending, confirmed, cooking, ready (orders that need kitchen attention)
                    var newOrders = response.filter(function(order) {
                        return ['pending', 'confirmed', 'active', 'cooking', 'ready'].includes(order.status);
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
            
            // Handle comma-separated status filters (e.g., "pending,confirmed,active")
            var filtered;
            if (this.currentFilter === 'all') {
                filtered = this.orders;
            } else if (this.currentFilter.includes(',')) {
                // Multiple statuses (comma-separated)
                var statuses = this.currentFilter.split(',');
                filtered = this.orders.filter(function(o) { 
                    return statuses.indexOf(o.status) > -1; 
                });
            } else {
                // Single status
                filtered = this.orders.filter(function(o) { 
                    return o.status === self.currentFilter; 
                });
            }
            
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
                
                // Map statuses: pending/confirmed/active → start cooking, cooking → mark ready, ready → complete
                if (['pending', 'confirmed', 'active'].indexOf(order.status) > -1) {
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
                
                // Check if order is delayed (> 5 minutes) and moving from pending/confirmed/active to cooking
                var createdAt = $card.data('created-at');
                var oldStatus = $card.data('status');
                var elapsedMinutes = self.getElapsedMinutes(createdAt);
                
                if (['pending', 'confirmed', 'active'].indexOf(oldStatus) > -1 && newStatus === 'cooking' && elapsedMinutes > 5) {
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
            // Note: 5-second polling is a significant improvement over 30 seconds for restaurant operations
            // Future enhancement: Consider WebSocket/Server-Sent Events for true real-time updates
            this.autoRefreshInterval = setInterval(function() {
                // Skip polling if document is hidden (tab not active)
                if (document.hidden) {
                    return;
                }
                self.loadOrders();
            }, 5000); // 5 seconds - Real-time updates for KDS
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
