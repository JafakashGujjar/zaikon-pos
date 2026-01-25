<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#6366f1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - Track Your Order</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .header .order-number {
            font-size: 16px;
            opacity: 0.95;
        }
        
        .content {
            padding: 24px;
        }
        
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        
        .loading {
            text-align: center;
            padding: 40px 20px;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6366f1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Status Timeline */
        .status-timeline {
            margin: 24px 0;
            position: relative;
        }
        
        .status-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 24px;
            position: relative;
        }
        
        .status-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 19px;
            top: 40px;
            width: 2px;
            height: calc(100% + 24px);
            background: #e5e7eb;
        }
        
        .status-step.active:not(:last-child)::after {
            background: #6366f1;
        }
        
        .status-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }
        
        .status-step.active .status-icon {
            background: #6366f1;
            color: white;
        }
        
        .status-step.completed .status-icon {
            background: #10b981;
            color: white;
        }
        
        .status-info {
            flex: 1;
            padding-top: 4px;
        }
        
        .status-title {
            font-weight: 600;
            font-size: 16px;
            color: #111827;
            margin-bottom: 4px;
        }
        
        .status-description {
            font-size: 14px;
            color: #6b7280;
        }
        
        .status-time {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }
        
        /* ETA Card */
        .eta-card {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 24px 0;
            text-align: center;
        }
        
        .eta-timer {
            font-size: 48px;
            font-weight: 700;
            margin: 8px 0;
        }
        
        .eta-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .eta-message {
            margin-top: 12px;
            font-size: 14px;
            opacity: 0.95;
        }
        
        /* Rider Info */
        .rider-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
        }
        
        .rider-header {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 12px;
            color: #111827;
        }
        
        .rider-details {
            display: flex;
            align-items: center;
        }
        
        .rider-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #6366f1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            margin-right: 16px;
        }
        
        .rider-name {
            font-weight: 500;
            font-size: 16px;
            color: #111827;
        }
        
        .rider-phone {
            font-size: 14px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        /* Order Details */
        .order-details {
            margin: 24px 0;
        }
        
        .section-title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 16px;
            color: #111827;
        }
        
        .order-item {
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
            color: #111827;
            margin-bottom: 4px;
        }
        
        .item-quantity {
            font-size: 14px;
            color: #6b7280;
        }
        
        .item-price {
            font-weight: 500;
            color: #111827;
        }
        
        .customer-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            margin: 16px 0;
        }
        
        .customer-field {
            margin-bottom: 12px;
        }
        
        .customer-field:last-child {
            margin-bottom: 0;
        }
        
        .customer-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .customer-value {
            font-size: 16px;
            color: #111827;
            font-weight: 500;
        }
        
        .total-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid #e5e7eb;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .total-row.grand {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin-top: 8px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        
        @media (max-width: 640px) {
            .container {
                border-radius: 0;
            }
            
            body {
                padding: 0;
            }
            
            .eta-timer {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
            <div class="order-number" id="order-number-header">Track Your Order</div>
        </div>
        
        <div class="content">
            <div id="loading-state" class="loading">
                <div class="loading-spinner"></div>
                <p>Loading your order...</p>
            </div>
            
            <div id="error-state" class="error-message" style="display: none;"></div>
            
            <div id="order-tracking" style="display: none;">
                <!-- ETA Card (shown during cooking or delivery) -->
                <div id="eta-card" class="eta-card" style="display: none;">
                    <div class="eta-label" id="eta-label">Estimated Time</div>
                    <div class="eta-timer" id="eta-timer">--:--</div>
                    <div class="eta-message" id="eta-message"></div>
                </div>
                
                <!-- Status Timeline -->
                <div class="status-timeline" id="status-timeline">
                    <!-- Will be populated by JavaScript -->
                </div>
                
                <!-- Rider Info (shown when dispatched) -->
                <div id="rider-info" class="rider-info" style="display: none;">
                    <div class="rider-header">🏍️ Your Delivery Rider</div>
                    <div class="rider-details">
                        <div class="rider-avatar" id="rider-avatar"></div>
                        <div>
                            <div class="rider-name" id="rider-name"></div>
                            <div class="rider-phone" id="rider-phone"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Info -->
                <div class="customer-info" id="customer-info">
                    <!-- Will be populated by JavaScript -->
                </div>
                
                <!-- Order Items -->
                <div class="order-details">
                    <div class="section-title">Order Items</div>
                    <div id="order-items">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <div class="total-section" id="order-totals">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            Thank you for your order! 🎉
        </div>
    </div>

    <script>
        // Get tracking token from URL
        const trackingToken = '<?php echo esc_js(get_query_var("zaikon_tracking_token")); ?>';
        const apiBaseUrl = '<?php echo esc_js(rest_url("zaikon/v1/")); ?>';
        
        let currentOrderData = null;
        let pollInterval = null;
        
        // Status configuration
        const statusConfig = {
            'pending': { icon: '🔔', title: 'Order Received', description: 'We have received your order' },
            'confirmed': { icon: '✅', title: 'Order Confirmed', description: 'Your order has been confirmed' },
            'cooking': { icon: '👨‍🍳', title: 'Preparing', description: 'Your order is being prepared' },
            'ready': { icon: '✔️', title: 'Ready', description: 'Your order is ready for dispatch' },
            'dispatched': { icon: '🏍️', title: 'On the Way', description: 'Your order is on the way' },
            'delivered': { icon: '🎉', title: 'Delivered', description: 'Your order has been delivered' }
        };
        
        const statusOrder = ['pending', 'confirmed', 'cooking', 'ready', 'dispatched', 'delivered'];
        
        // Fetch order data
        async function fetchOrderData() {
            try {
                const response = await fetch(`${apiBaseUrl}track/${trackingToken}`);
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Order not found');
                }
                
                currentOrderData = data;
                renderOrderTracking(data);
                
            } catch (error) {
                console.error('Error fetching order:', error);
                showError(error.message || 'Unable to load order. Please check your tracking link.');
            }
        }
        
        // Render order tracking UI
        function renderOrderTracking(data) {
            const order = data.order;
            const eta = data.eta;
            
            // Hide loading, show content
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('order-tracking').style.display = 'block';
            
            // Update header
            document.getElementById('order-number-header').textContent = `Order #${order.order_number}`;
            
            // Render status timeline
            renderStatusTimeline(order);
            
            // Render ETA
            renderETA(order, eta);
            
            // Render rider info
            if (order.order_status === 'dispatched' && order.rider_name) {
                renderRiderInfo(order);
            }
            
            // Render customer info
            renderCustomerInfo(order);
            
            // Render order items
            renderOrderItems(order);
        }
        
        // Render status timeline
        function renderStatusTimeline(order) {
            const timeline = document.getElementById('status-timeline');
            const currentStatus = order.order_status;
            const currentStatusIndex = statusOrder.indexOf(currentStatus);
            
            timeline.innerHTML = statusOrder.map((status, index) => {
                const config = statusConfig[status];
                const isCompleted = index < currentStatusIndex;
                const isActive = index === currentStatusIndex;
                const statusClass = isCompleted ? 'completed' : isActive ? 'active' : '';
                
                // Get timestamp if available
                let timestamp = '';
                if (status === 'confirmed' && order.confirmed_at) {
                    timestamp = formatTime(order.confirmed_at);
                } else if (status === 'cooking' && order.cooking_started_at) {
                    timestamp = formatTime(order.cooking_started_at);
                } else if (status === 'ready' && order.ready_at) {
                    timestamp = formatTime(order.ready_at);
                } else if (status === 'dispatched' && order.dispatched_at) {
                    timestamp = formatTime(order.dispatched_at);
                } else if (status === 'delivered' && order.delivered_at) {
                    timestamp = formatTime(order.delivered_at);
                }
                
                return `
                    <div class="status-step ${statusClass}">
                        <div class="status-icon">${config.icon}</div>
                        <div class="status-info">
                            <div class="status-title">${config.title}</div>
                            <div class="status-description">${config.description}</div>
                            ${timestamp ? `<div class="status-time">${timestamp}</div>` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        // Render ETA countdown
        function renderETA(order, eta) {
            const etaCard = document.getElementById('eta-card');
            const status = order.order_status;
            
            if (status === 'cooking' && eta.cooking_eta_remaining !== null) {
                etaCard.style.display = 'block';
                document.getElementById('eta-label').textContent = 'Cooking Time Remaining';
                updateETATimer(eta.cooking_eta_remaining);
                document.getElementById('eta-message').textContent = 'Your delicious food is being prepared!';
            } else if (status === 'dispatched' && eta.delivery_eta_remaining !== null) {
                etaCard.style.display = 'block';
                document.getElementById('eta-label').textContent = 'Delivery Time Remaining';
                updateETATimer(eta.delivery_eta_remaining);
                
                if (eta.delivery_eta_remaining <= 5) {
                    document.getElementById('eta-message').textContent = '🎉 Your order will arrive in about 5 minutes!';
                } else {
                    document.getElementById('eta-message').textContent = 'Your order is on the way!';
                }
            } else {
                etaCard.style.display = 'none';
            }
        }
        
        // Update ETA timer display
        function updateETATimer(minutes) {
            const mins = Math.floor(minutes);
            const secs = Math.floor((minutes - mins) * 60);
            document.getElementById('eta-timer').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        
        // Render rider info
        function renderRiderInfo(order) {
            const riderInfo = document.getElementById('rider-info');
            riderInfo.style.display = 'block';
            
            const initials = order.rider_name.split(' ').map(n => n[0]).join('').toUpperCase();
            document.getElementById('rider-avatar').textContent = initials;
            document.getElementById('rider-name').textContent = order.rider_name;
            document.getElementById('rider-phone').textContent = order.rider_phone;
        }
        
        // Render customer info
        function renderCustomerInfo(order) {
            const customerInfo = document.getElementById('customer-info');
            
            let html = '';
            if (order.customer_name) {
                html += `
                    <div class="customer-field">
                        <div class="customer-label">Customer Name</div>
                        <div class="customer-value">${escapeHtml(order.customer_name)}</div>
                    </div>
                `;
            }
            
            if (order.customer_phone) {
                html += `
                    <div class="customer-field">
                        <div class="customer-label">Phone</div>
                        <div class="customer-value">${escapeHtml(order.customer_phone)}</div>
                    </div>
                `;
            }
            
            if (order.location_name) {
                html += `
                    <div class="customer-field">
                        <div class="customer-label">Delivery Location</div>
                        <div class="customer-value">${escapeHtml(order.location_name)}</div>
                    </div>
                `;
            }
            
            if (order.special_instruction) {
                html += `
                    <div class="customer-field">
                        <div class="customer-label">Special Instructions</div>
                        <div class="customer-value">${escapeHtml(order.special_instruction)}</div>
                    </div>
                `;
            }
            
            customerInfo.innerHTML = html;
        }
        
        // Render order items
        function renderOrderItems(order) {
            const itemsContainer = document.getElementById('order-items');
            const totalsContainer = document.getElementById('order-totals');
            
            let itemsHTML = '';
            if (order.items && order.items.length > 0) {
                itemsHTML = order.items.map(item => `
                    <div class="order-item">
                        <div class="item-info">
                            <div class="item-name">${escapeHtml(item.product_name)}</div>
                            <div class="item-quantity">Qty: ${item.qty}</div>
                        </div>
                        <div class="item-price">Rs ${parseFloat(item.line_total_rs).toFixed(2)}</div>
                    </div>
                `).join('');
            }
            
            itemsContainer.innerHTML = itemsHTML;
            
            // Render totals
            let totalsHTML = `
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>Rs ${parseFloat(order.items_subtotal_rs || order.subtotal_rs || 0).toFixed(2)}</span>
                </div>
            `;
            
            if (parseFloat(order.delivery_charges_rs || order.delivery_charge_rs || 0) > 0) {
                totalsHTML += `
                    <div class="total-row">
                        <span>Delivery Fee</span>
                        <span>Rs ${parseFloat(order.delivery_charges_rs || order.delivery_charge_rs || 0).toFixed(2)}</span>
                    </div>
                `;
            }
            
            if (parseFloat(order.discounts_rs || order.discount_rs || 0) > 0) {
                totalsHTML += `
                    <div class="total-row">
                        <span>Discount</span>
                        <span>-Rs ${parseFloat(order.discounts_rs || order.discount_rs || 0).toFixed(2)}</span>
                    </div>
                `;
            }
            
            totalsHTML += `
                <div class="total-row grand">
                    <span>Total</span>
                    <span>Rs ${parseFloat(order.grand_total_rs).toFixed(2)}</span>
                </div>
            `;
            
            totalsContainer.innerHTML = totalsHTML;
        }
        
        // Show error message
        function showError(message) {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('error-state').style.display = 'block';
            document.getElementById('error-state').textContent = message;
        }
        
        // Format timestamp
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Start polling for updates
        function startPolling() {
            // Poll every 10 seconds
            pollInterval = setInterval(async () => {
                try {
                    await fetchOrderData();
                } catch (error) {
                    console.error('Polling error:', error);
                }
            }, 10000);
        }
        
        // Initialize
        if (trackingToken) {
            fetchOrderData();
            startPolling();
        } else {
            showError('Invalid tracking link');
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
        });
    </script>
</body>
</html>
