/**
 * ZAIKON POS - Cashier Session & Order Management
 * Handles session opening/closing, expenses, and order management
 */

(function($) {
    'use strict';
    
    /**
     * Format order time with timezone adjustment
     * Converts UTC timestamp from database to local display time
     */
    function formatOrderTime(dateString) {
        // Database now stores timestamps in UTC format (after fix)
        // Parse the MySQL datetime string as UTC, then JavaScript will
        // display it in the user's local timezone automatically
        var date = new Date(dateString.replace(' ', 'T') + 'Z');
        
        return date.toLocaleTimeString();
    }
    
    var SessionManager = {
        currentSession: null,
        
        init: function() {
            this.bindEvents();
            this.checkActiveSession();
        },
        
        bindEvents: function() {
            // Open shift
            $('#rpos-confirm-open-shift').on('click', this.openShift.bind(this));
            
            // Close shift - handle both button and icon button
            $('#rpos-close-shift-btn, #rpos-close-shift-icon-btn').on('click', this.showCloseShiftModal.bind(this));
            $('#rpos-confirm-close-shift').on('click', this.closeShift.bind(this));
            $('#rpos-cancel-close-shift, #rpos-close-shift-dropdown-close').on('click', function() {
                $('#rpos-close-shift-dropdown').fadeOut(200);
            });
            
            // Calculate cash difference on input
            $('#rpos-closing-cash').on('input', this.calculateDifference.bind(this));
            
            // Expenses
            $('#rpos-expenses-btn').on('click', this.showExpensesModal.bind(this));
            $('#rpos-add-expense').on('click', this.addExpense.bind(this));
            $('#rpos-add-expense-modal').on('click', this.addExpenseModal.bind(this));
            $('#rpos-close-expenses, #rpos-expenses-modal-close, #rpos-expenses-dropdown-close').on('click', function() {
                $('#rpos-expenses-modal').fadeOut(200);
                $('#rpos-expenses-dropdown').fadeOut(200);
            });
            
            // Close dropdown when clicking outside (no longer needed since using modal, but keep for safety)
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#rpos-expenses-btn, #rpos-expenses-dropdown').length) {
                    $('#rpos-expenses-dropdown').fadeOut(200);
                }
                if (!$(e.target).closest('#rpos-close-shift-icon-btn, #rpos-close-shift-btn, #rpos-close-shift-dropdown').length) {
                    $('#rpos-close-shift-dropdown').fadeOut(200);
                }
            });
            
            // Show rider field when rider_payout category is selected (dropdown)
            $('#rpos-expense-category').on('change', function() {
                if ($(this).val() === 'rider_payout') {
                    $('#rpos-expense-rider-field').show();
                } else {
                    $('#rpos-expense-rider-field').hide();
                }
            });
            
            // Show rider field when rider_payout category is selected (modal)
            $('#rpos-expense-category-modal').on('change', function() {
                if ($(this).val() === 'rider_payout') {
                    $('#rpos-expense-rider-field-modal').show();
                } else {
                    $('#rpos-expense-rider-field-modal').hide();
                }
            });
            
            // Orders
            $('#rpos-orders-btn').on('click', this.showOrdersModal.bind(this));
            $('#rpos-refresh-orders').on('click', this.loadOrders.bind(this));
            $('#rpos-close-orders, #rpos-orders-modal-close').on('click', function() {
                $('#rpos-orders-modal').fadeOut(200);
            });
            
            // Event delegation for order action buttons
            $(document).on('click', '.mark-paid-btn', function(e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                SessionManager.markOrderPaid(orderId);
            });
            
            $(document).on('click', '.zaikon-order-actions .cancel-btn', function(e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                SessionManager.cancelOrder(orderId);
            });
            
            $(document).on('click', '.replacement-btn', function(e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                SessionManager.markReplacement(orderId);
            });
            
            $(document).on('click', '.mark-delivered-btn', function(e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                SessionManager.markDelivered(orderId);
            });
            
            $(document).on('click', '.mark-cod-received-btn', function(e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                SessionManager.markCodReceived(orderId);
            });
        },
        
        checkActiveSession: function() {
            var self = this;
            $.ajax({
                url: rposData.zaikonRestUrl + 'sessions/current',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(response) {
                    if (response.has_active_session) {
                        self.currentSession = response.session;
                        console.log('Active session found:', self.currentSession);
                    } else {
                        // No active session, show open shift modal
                        self.showOpenShiftModal();
                    }
                },
                error: function(xhr) {
                    console.error('Error checking session:', xhr);
                    // For now, allow working without a session
                }
            });
        },
        
        showOpenShiftModal: function() {
            $('#rpos-open-shift-modal').fadeIn(200);
            $('#rpos-opening-cash').focus();
        },
        
        openShift: function() {
            var self = this;
            var openingCash = parseFloat($('#rpos-opening-cash').val()) || 0;
            var notes = $('#rpos-opening-notes').val();
            
            if (openingCash < 0) {
                window.ZaikonToast.error('Opening cash cannot be negative');
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'sessions/open',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify({
                    opening_cash_rs: openingCash,
                    notes: notes
                }),
                success: function(response) {
                    self.currentSession = response.session;
                    $('#rpos-open-shift-modal').fadeOut(200);
                    window.ZaikonToast.success('Shift opened successfully');
                    
                    // Clear form
                    $('#rpos-opening-cash').val('0.00');
                    $('#rpos-opening-notes').val('');
                },
                error: function(xhr) {
                    console.error('Error opening shift:', xhr);
                    window.ZaikonToast.error(xhr.responseJSON?.message || 'Failed to open shift');
                }
            });
        },
        
        showCloseShiftModal: function() {
            var self = this;
            
            if (!this.currentSession) {
                window.ZaikonToast.error('No active session to close');
                return;
            }
            
            // Get session totals
            $.ajax({
                url: rposData.zaikonRestUrl + 'sessions/' + this.currentSession.id + '/totals',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(totals) {
                    // Populate summary
                    $('#rpos-summary-opening').text(rposData.currency + parseFloat(self.currentSession.opening_cash_rs).toFixed(2));
                    $('#rpos-summary-cash-sales').text(rposData.currency + parseFloat(totals.cash_sales).toFixed(2));
                    $('#rpos-summary-cod').text(rposData.currency + parseFloat(totals.cod_collected).toFixed(2));
                    $('#rpos-summary-online').text(rposData.currency + parseFloat(totals.online_payments || 0).toFixed(2));
                    $('#rpos-summary-expenses').text(rposData.currency + parseFloat(totals.expenses).toFixed(2));
                    $('#rpos-summary-expected').text(rposData.currency + parseFloat(totals.expected_cash).toFixed(2));
                    
                    // Store expected cash for difference calculation
                    $('#rpos-close-shift-dropdown').data('expected-cash', totals.expected_cash);
                    
                    // Show modal (now centered like other modals)
                    $('#rpos-close-shift-dropdown').fadeIn(200);
                },
                error: function(xhr) {
                    console.error('Error getting session totals:', xhr);
                    window.ZaikonToast.error('Failed to load session data');
                }
            });
        },
        
        calculateDifference: function() {
            var closingCash = parseFloat($('#rpos-closing-cash').val()) || 0;
            var expectedCash = parseFloat($('#rpos-close-shift-dropdown').data('expected-cash')) || 0;
            var difference = closingCash - expectedCash;
            
            var diffDiv = $('#rpos-cash-difference');
            var diffAmount = $('#rpos-difference-amount');
            
            if (closingCash > 0) {
                diffDiv.show();
                diffAmount.text(rposData.currency + Math.abs(difference).toFixed(2));
                
                if (difference > 0) {
                    diffAmount.css('color', 'var(--zaikon-green)');
                    diffAmount.text('+' + rposData.currency + difference.toFixed(2) + ' (Overage)');
                } else if (difference < 0) {
                    diffAmount.css('color', 'var(--zaikon-red)');
                    diffAmount.text('-' + rposData.currency + Math.abs(difference).toFixed(2) + ' (Shortage)');
                } else {
                    diffAmount.css('color', 'var(--zaikon-green)');
                    diffAmount.text(rposData.currency + '0.00 (Exact)');
                }
            } else {
                diffDiv.hide();
            }
        },
        
        closeShift: function() {
            var self = this;
            var closingCash = parseFloat($('#rpos-closing-cash').val()) || 0;
            var notes = $('#rpos-closing-notes').val();
            
            if (closingCash < 0) {
                window.ZaikonToast.error('Closing cash cannot be negative');
                return;
            }
            
            if (!confirm('Are you sure you want to close this shift? This cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'sessions/' + this.currentSession.id + '/close',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify({
                    closing_cash_rs: closingCash,
                    notes: notes
                }),
                success: function(response) {
                    $('#rpos-close-shift-dropdown').fadeOut(200);
                    window.ZaikonToast.success('Shift closed successfully');
                    
                    // Clear current session
                    self.currentSession = null;
                    
                    // Clear form
                    $('#rpos-closing-cash').val('');
                    $('#rpos-closing-notes').val('');
                    $('#rpos-cash-difference').hide();
                    
                    // Show open shift modal for next shift
                    setTimeout(function() {
                        self.showOpenShiftModal();
                    }, 1000);
                },
                error: function(xhr) {
                    console.error('Error closing shift:', xhr);
                    window.ZaikonToast.error(xhr.responseJSON?.message || 'Failed to close shift');
                }
            });
        },
        
        showExpensesModal: function() {
            if (!this.currentSession) {
                window.ZaikonToast.error('Please open a shift first');
                return;
            }
            
            // Use modal instead of dropdown (same as Orders modal behavior)
            var modal = $('#rpos-expenses-modal');
            if (modal.is(':visible')) {
                modal.fadeOut(200);
            } else {
                modal.fadeIn(200);
                this.loadExpenses();
                this.loadRiders();
            }
        },
        
        loadRiders: function() {
            $.ajax({
                url: rposData.restUrl + 'riders/active',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(riders) {
                    // Update both dropdown and modal rider selects
                    var selects = $('#rpos-expense-rider, #rpos-expense-rider-modal');
                    selects.html('<option value="">-- Select Rider --</option>');
                    
                    riders.forEach(function(rider) {
                        selects.append('<option value="' + rider.id + '">' + rider.name + '</option>');
                    });
                }
            });
        },
        
        addExpense: function() {
            var self = this;
            var amount = parseFloat($('#rpos-expense-amount').val()) || 0;
            var category = $('#rpos-expense-category').val();
            var riderId = $('#rpos-expense-rider').val();
            var description = $('#rpos-expense-description').val();
            
            if (amount <= 0) {
                window.ZaikonToast.error('Please enter a valid amount');
                return;
            }
            
            if (!category) {
                window.ZaikonToast.error('Please select a category');
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'expenses',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify({
                    session_id: self.currentSession.id,
                    amount_rs: amount,
                    category: category,
                    rider_id: riderId || null,
                    description: description
                }),
                success: function(response) {
                    window.ZaikonToast.success('Expense added successfully');
                    
                    // Clear form
                    $('#rpos-expense-amount').val('');
                    $('#rpos-expense-category').val('');
                    $('#rpos-expense-rider').val('');
                    $('#rpos-expense-description').val('');
                    $('#rpos-expense-rider-field').hide();
                    
                    // Reload expenses list
                    self.loadExpenses();
                },
                error: function(xhr) {
                    console.error('Error adding expense:', xhr);
                    window.ZaikonToast.error(xhr.responseJSON?.message || 'Failed to add expense');
                }
            });
        },
        
        // Add expense from modal (uses -modal suffixed IDs)
        addExpenseModal: function() {
            var self = this;
            var amount = parseFloat($('#rpos-expense-amount-modal').val()) || 0;
            var category = $('#rpos-expense-category-modal').val();
            var riderId = $('#rpos-expense-rider-modal').val();
            var description = $('#rpos-expense-description-modal').val();
            
            if (amount <= 0) {
                window.ZaikonToast.error('Please enter a valid amount');
                return;
            }
            
            if (!category) {
                window.ZaikonToast.error('Please select a category');
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'expenses',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify({
                    session_id: self.currentSession.id,
                    amount_rs: amount,
                    category: category,
                    rider_id: riderId || null,
                    description: description
                }),
                success: function(response) {
                    window.ZaikonToast.success('Expense added successfully');
                    
                    // Clear form
                    $('#rpos-expense-amount-modal').val('');
                    $('#rpos-expense-category-modal').val('');
                    $('#rpos-expense-rider-modal').val('');
                    $('#rpos-expense-description-modal').val('');
                    $('#rpos-expense-rider-field-modal').hide();
                    
                    // Reload expenses list
                    self.loadExpenses();
                },
                error: function(xhr) {
                    console.error('Error adding expense:', xhr);
                    window.ZaikonToast.error(xhr.responseJSON?.message || 'Failed to add expense');
                }
            });
        },
        
        loadExpenses: function() {
            var self = this;
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'expenses?session_id=' + this.currentSession.id,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(expenses) {
                    // Update both dropdown and modal expense lists
                    var lists = $('#rpos-expenses-list, #rpos-expenses-list-modal');
                    
                    if (expenses.length === 0) {
                        lists.html('<p style="text-align: center; color: var(--zaikon-gray-dark);">No expenses recorded yet</p>');
                        return;
                    }
                    
                    var html = '';
                    expenses.forEach(function(expense) {
                        var categoryLabel = $('#rpos-expense-category option[value="' + expense.category + '"]').text() || expense.category;
                        html += '<div class="zaikon-expenses-list-item">';
                        html += '<div class="zaikon-expense-info">';
                        html += '<div class="zaikon-expense-category">' + categoryLabel + '</div>';
                        if (expense.rider_name) {
                            html += '<div class="zaikon-expense-description">Rider: ' + expense.rider_name + '</div>';
                        }
                        if (expense.description) {
                            html += '<div class="zaikon-expense-description">' + expense.description + '</div>';
                        }
                        html += '</div>';
                        html += '<div class="zaikon-expense-amount">' + rposData.currency + parseFloat(expense.amount_rs).toFixed(2) + '</div>';
                        html += '</div>';
                    });
                    
                    lists.html(html);
                },
                error: function(xhr) {
                    console.error('Error loading expenses:', xhr);
                }
            });
        },
        
        showOrdersModal: function() {
            $('#rpos-orders-modal').fadeIn(200);
            this.loadOrders();
        },
        
        loadOrders: function() {
            var date = $('#rpos-orders-date').val();
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'orders/cashier?date=' + date,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(orders) {
                    var list = $('#rpos-orders-list');
                    
                    if (orders.length === 0) {
                        list.html('<p style="text-align: center; color: var(--zaikon-gray-dark);">No orders found for this date</p>');
                        return;
                    }
                    
                    var html = '<table class="zaikon-orders-table">';
                    html += '<thead><tr>';
                    html += '<th>Order #</th>';
                    html += '<th>Type</th>';
                    html += '<th>Payment Type</th>';
                    html += '<th>Amount</th>';
                    html += '<th>Payment Status</th>';
                    html += '<th>Order Status</th>';
                    html += '<th>Time</th>';
                    html += '<th>Rider</th>';
                    html += '<th>Actions</th>';
                    html += '</tr></thead><tbody>';
                    
                    orders.forEach(function(order) {
                        html += '<tr>';
                        html += '<td><strong>' + order.order_number + '</strong></td>';
                        html += '<td>' + order.order_type.replace('_', ' ').toUpperCase() + '</td>';
                        html += '<td>' + (order.payment_type || 'cash').toUpperCase() + '</td>';
                        html += '<td><strong>' + rposData.currency + parseFloat(order.grand_total_rs).toFixed(2) + '</strong></td>';
                        html += '<td><span class="zaikon-payment-status-badge ' + (order.payment_status || 'paid') + '">' + (order.payment_status || 'paid').toUpperCase() + '</span></td>';
                        html += '<td><span class="zaikon-order-status-badge ' + (order.order_status || 'active') + '">' + (order.order_status || 'active').toUpperCase() + '</span></td>';
                        html += '<td>' + formatOrderTime(order.created_at) + '</td>';
                        html += '<td>' + (order.rider_name || '-') + '</td>';
                        html += '<td><div class="zaikon-order-actions">';
                        
                        // Show "Mark Paid" button only for unpaid COD orders
                        if (order.payment_type === 'cod' && order.payment_status === 'unpaid') {
                            html += '<button class="zaikon-order-action-btn mark-paid-btn" data-order-id="' + order.id + '">Mark Paid</button>';
                        }
                        
                        // Show "Cancel" button only for active orders
                        if ((order.order_status || 'active') === 'active') {
                            html += '<button class="zaikon-order-action-btn cancel-btn" data-order-id="' + order.id + '">Cancel</button>';
                        }
                        
                        // Show "Mark Delivered" button for active/assigned delivery orders
                        if (order.order_type === 'delivery' && 
                            (order.order_status === 'active' || order.delivery_status === 'assigned' || order.delivery_status === 'on_route')) {
                            html += '<button class="zaikon-order-action-btn mark-delivered-btn" data-order-id="' + order.id + '">Mark Delivered</button>';
                        }
                        
                        // Show "Mark COD Received" button for COD orders that are delivered but not yet COD received
                        if (order.payment_type === 'cod' && order.order_status === 'delivered' && 
                            (order.payment_status === 'unpaid' || order.payment_status === 'cod_pending')) {
                            html += '<button class="zaikon-order-action-btn mark-cod-received-btn" data-order-id="' + order.id + '">Mark COD Received</button>';
                        }
                        
                        // Show "Replacement" button
                        html += '<button class="zaikon-order-action-btn replacement-btn" data-order-id="' + order.id + '">Replacement</button>';
                        
                        html += '</div></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    list.html(html);
                },
                error: function(xhr) {
                    console.error('Error loading orders:', xhr);
                    window.ZaikonToast.error('Failed to load orders');
                }
            });
        },
        
        markOrderPaid: function(orderId) {
            var self = this;
            
            // Remove existing modal if any
            $('#zaikon-payment-type-modal').remove();
            
            // Cleanup function for event listeners
            var cleanupModalEvents = function() {
                $(document).off('.paymentTypeModal');
            };
            
            // Create payment type selection modal
            var modalHtml = '<div id="zaikon-payment-type-modal" class="zaikon-payment-type-modal">' +
                '<div class="zaikon-payment-type-content">' +
                    '<h3>How was payment received?</h3>' +
                    '<p>Select the payment method for this COD order:</p>' +
                    '<div class="zaikon-payment-type-buttons">' +
                        '<button class="zaikon-payment-type-btn cash-btn" data-payment-type="cash">' +
                            '<span class="payment-icon">💵</span>' +
                            '<span class="payment-label">Cash</span>' +
                        '</button>' +
                        '<button class="zaikon-payment-type-btn online-btn" data-payment-type="online">' +
                            '<span class="payment-icon">💳</span>' +
                            '<span class="payment-label">Online Payment</span>' +
                        '</button>' +
                    '</div>' +
                    '<button class="zaikon-payment-type-cancel">Cancel</button>' +
                '</div>' +
            '</div>';
            
            // Add modal to body
            $('body').append(modalHtml);
            
            // Handle payment type selection using event delegation
            $(document).on('click.paymentTypeModal', '.zaikon-payment-type-btn', function() {
                var paymentType = $(this).data('payment-type');
                $('#zaikon-payment-type-modal').remove();
                cleanupModalEvents();
                
                // Determine payment_type and payment_status based on selection
                var requestData;
                if (paymentType === 'cash') {
                    // Cash: Keep payment_type as 'cod', set status to 'cod_received'
                    // This maps to "Total COD Collected" in shift summary
                    // Do NOT send payment_type - keep it as 'cod' in the database
                    requestData = {
                        payment_status: 'cod_received'
                    };
                } else if (paymentType === 'online') {
                    // Online: Change payment_type to 'online', set status to 'paid'
                    // This maps to "Total Online Payments" in shift summary
                    requestData = {
                        payment_status: 'paid',
                        payment_type: 'online'
                    };
                } else {
                    console.error('Invalid payment type:', paymentType);
                    window.ZaikonToast.error('Invalid payment type');
                    return;
                }
                
                $.ajax({
                    url: rposData.zaikonRestUrl + 'orders/' + orderId + '/payment-status',
                    method: 'PUT',
                    contentType: 'application/json',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                    },
                    data: JSON.stringify(requestData),
                    success: function(response) {
                        var message = paymentType === 'cash' ? 'COD payment received (Cash)' : 'Payment received (Online)';
                        window.ZaikonToast.success(message);
                        self.loadOrders();
                    },
                    error: function(xhr) {
                        console.error('Error updating payment status:', xhr);
                        window.ZaikonToast.error('Failed to update payment status');
                    }
                });
            });
            
            // Handle cancel using event delegation
            $(document).on('click.paymentTypeModal', '.zaikon-payment-type-cancel', function() {
                $('#zaikon-payment-type-modal').remove();
                cleanupModalEvents();
            });
            
            // Close modal when clicking outside using event delegation
            $(document).on('click.paymentTypeModal', '#zaikon-payment-type-modal', function(e) {
                if ($(e.target).is('#zaikon-payment-type-modal')) {
                    $('#zaikon-payment-type-modal').remove();
                    cleanupModalEvents();
                }
            });
        },
        
        cancelOrder: function(orderId) {
            var self = this;
            
            if (!confirm('Cancel this order? This cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'orders/' + orderId + '/order-status',
                method: 'PUT',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify({
                    order_status: 'cancelled'
                }),
                success: function(response) {
                    window.ZaikonToast.success('Order cancelled');
                    self.loadOrders();
                },
                error: function(xhr) {
                    console.error('Error cancelling order:', xhr);
                    window.ZaikonToast.error('Failed to cancel order');
                }
            });
        },
        
        markReplacement: function(orderId) {
            var self = this;
            
            if (!confirm('Mark this order as a replacement?')) {
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'orders/' + orderId + '/order-status',
                method: 'PUT',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                data: JSON.stringify({
                    order_status: 'replacement'
                }),
                success: function(response) {
                    window.ZaikonToast.success('Order marked as replacement');
                    self.loadOrders();
                },
                error: function(xhr) {
                    console.error('Error marking replacement:', xhr);
                    window.ZaikonToast.error('Failed to mark as replacement');
                }
            });
        },
        
        markDelivered: function(orderId) {
            var self = this;
            
            if (!confirm('Mark this order as delivered?')) {
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'orders/' + orderId + '/mark-delivered',
                method: 'PUT',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(response) {
                    window.ZaikonToast.success('Order marked as delivered');
                    self.loadOrders();
                },
                error: function(xhr) {
                    console.error('Error marking delivered:', xhr);
                    window.ZaikonToast.error('Failed to mark as delivered');
                }
            });
        },
        
        markCodReceived: function(orderId) {
            var self = this;
            
            if (!confirm('Confirm COD payment received for this order?')) {
                return;
            }
            
            $.ajax({
                url: rposData.zaikonRestUrl + 'orders/' + orderId + '/mark-cod-received',
                method: 'PUT',
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rposData.nonce);
                },
                success: function(response) {
                    window.ZaikonToast.success('COD payment received');
                    self.loadOrders();
                },
                error: function(xhr) {
                    console.error('Error marking COD received:', xhr);
                    window.ZaikonToast.error('Failed to mark COD as received');
                }
            });
        }
    };
    
    // Make SessionManager globally accessible
    window.SessionManager = SessionManager;
    
    // Initialize on document ready
    $(document).ready(function() {
        SessionManager.init();
    });
    
})(jQuery);
