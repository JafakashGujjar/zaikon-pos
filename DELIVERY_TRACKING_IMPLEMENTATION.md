# Enterprise Delivery Tracking System - Implementation Guide

## Overview

This document describes the complete implementation of the Enterprise Delivery Tracking System for Zaikon POS. The system provides real-time order tracking with a mobile-first customer interface similar to Careem/Uber.

## ✅ Implemented Features

### 1. Database Schema

**New Fields in `wp_zaikon_orders`:**
- `tracking_token` - Unique 32-character hex token for public tracking
- `order_status` - Extended enum: pending, confirmed, cooking, ready, dispatched, delivered
- `cooking_eta_minutes` - Default 20 minutes, auto-extends by 5 min
- `delivery_eta_minutes` - Default 15 minutes countdown
- `confirmed_at` - Timestamp when order confirmed
- `cooking_started_at` - Timestamp when cooking begins
- `ready_at` - Timestamp when order ready
- `dispatched_at` - Timestamp when rider dispatches

**New Fields in `wp_zaikon_deliveries`:**
- `rider_name` - Rider's full name for display
- `rider_phone` - Rider's contact number
- `rider_avatar` - Optional rider photo URL

### 2. Backend API Endpoints

All endpoints under `/wp-json/zaikon/v1/`:

**Public (No Authentication):**
- `GET /track/{token}` - Get order details by tracking token
- `GET /orders/{id}/eta` - Get remaining ETA for order

**Authenticated (KDS/POS):**
- `PUT /orders/{id}/tracking-status` - Update order status
- `PUT /orders/{id}/assign-rider-info` - Assign rider with contact info
- `GET /orders/{id}/tracking-url` - Generate/get tracking URL
- `POST /orders/{id}/extend-eta` - Manually extend cooking ETA

### 3. Order Tracking Service (`Zaikon_Order_Tracking`)

**Core Methods:**
- `generate_tracking_token($order_id)` - Creates secure 32-char token
- `get_tracking_url($token)` - Returns `/track-order/{token}` URL
- `get_order_by_token($token)` - Public method to fetch order by token
- `update_status($order_id, $new_status)` - Updates status with timestamps
- `extend_cooking_eta($order_id, $minutes)` - Extends ETA (default +5 min)
- `check_and_extend_cooking_eta($order_id)` - Auto-extends if exceeded
- `assign_rider($order_id, $rider_data)` - Assigns rider info
- `get_remaining_eta($order_id)` - Calculates live countdown

### 4. Public Tracking Page (`/track-order/{token}`)

**Features:**
✅ Mobile-first responsive design
✅ Status timeline with 6 stages (Pending → Confirmed → Cooking → Ready → Dispatched → Delivered)
✅ Real-time countdown timer for cooking (20 min) and delivery (15 min)
✅ Rider information display when dispatched
✅ Order items with quantities and prices
✅ Customer information (name, phone, location)
✅ Auto-refresh via polling (10-second intervals)
✅ No authentication required - public access

**UI Components:**
- Gradient header with brand name and order number
- Interactive status timeline with icons
- ETA countdown card with live timer
- Rider info card with avatar/initials
- Order summary with line items
- Financial breakdown (subtotal, delivery fee, total)

### 5. Order Creation Integration

When an order is created via `Zaikon_Order_Service::create_order()`:
1. ✅ Tracking token is automatically generated
2. ✅ Tracking URL is included in response
3. ✅ Initial status set to 'pending' (delivery) or 'confirmed' (dine-in/takeaway)
4. ✅ System event logged for audit trail

## 📋 Status Flow

```
Delivery Orders:
pending → confirmed → cooking → ready → dispatched → delivered

Dine-in/Takeaway Orders:
confirmed → cooking → ready
```

### Status Responsibility Matrix

| Status      | Updated By          | Trigger                    |
|-------------|---------------------|----------------------------|
| pending     | System              | Order created (delivery)   |
| confirmed   | Kitchen Display     | Kitchen confirms order     |
| cooking     | Kitchen Display     | Kitchen starts cooking     |
| ready       | Kitchen Display     | Food is ready              |
| dispatched  | Cashier/POS         | Rider picks up order       |
| delivered   | Cashier/POS/Rider   | Order delivered to customer|

## ⏱️ ETA Logic

### Cooking ETA (Default: 20 minutes)

**When cooking starts:**
1. System sets `cooking_eta_minutes = 20`
2. Countdown begins on tracking page, KDS, POS
3. Customer sees live countdown

**Auto-Extension Logic:**
- When cooking time exceeds ETA
- System automatically extends by +5 minutes
- Event logged to `zaikon_system_events`
- Customer notification: "Your order is taking slightly longer. New estimated time: 25 minutes."

**Manual Extension:**
- KDS can call `POST /zaikon/v1/orders/{id}/extend-eta`
- Adds specified minutes to current ETA

### Delivery ETA (Default: 15 minutes)

**When dispatched:**
1. System sets `delivery_eta_minutes = 15`
2. Countdown begins: 15 → 14 → ... → 0
3. When ≤ 5 minutes: Highlighted message shown

## 🔐 Security

**Tracking Token:**
- 32-character cryptographically secure random token
- Generated using `bin2hex(random_bytes(16))`
- Unique constraint in database
- No PII exposed in URL
- Tokens don't expire (can be configured)

**Public Endpoint Protection:**
- Read-only access to order data
- No sensitive payment information exposed
- No ability to modify orders
- Rate limiting recommended (not implemented)

## 🚀 Integration Points

### To Add Share Button to POS:

```javascript
// In POS order screen JavaScript
async function shareTrackingLink(orderId) {
    const response = await fetch(`/wp-json/zaikon/v1/orders/${orderId}/tracking-url`, {
        headers: {
            'X-WP-Nonce': wpApiSettings.nonce
        }
    });
    
    const data = await response.json();
    
    if (data.success) {
        // Copy to clipboard or share via SMS/WhatsApp
        navigator.clipboard.writeText(data.tracking_url);
        
        // Or open WhatsApp
        window.open(`https://wa.me/?text=Track your order: ${data.tracking_url}`);
    }
}
```

### To Update Status from KDS:

```javascript
// In KDS JavaScript
async function updateOrderStatus(orderId, newStatus) {
    const response = await fetch(`/wp-json/zaikon/v1/orders/${orderId}/tracking-status`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ status: newStatus })
    });
    
    return await response.json();
}

// Usage: When kitchen confirms order
updateOrderStatus(123, 'confirmed');

// When cooking starts
updateOrderStatus(123, 'cooking');

// When ready for pickup
updateOrderStatus(123, 'ready');
```

### To Assign Rider from POS:

```javascript
// In POS delivery management
async function assignRider(orderId, riderData) {
    const response = await fetch(`/wp-json/zaikon/v1/orders/${orderId}/assign-rider-info`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({
            rider_name: riderData.name,
            rider_phone: riderData.phone,
            rider_avatar: riderData.avatar, // optional
            rider_id: riderData.id // optional
        })
    });
    
    return await response.json();
}
```

## 📱 Testing the Tracking Page

### Step 1: Activate Plugin & Run Migrations

```bash
# In WordPress admin, navigate to:
Plugins → Activate Restaurant POS

# Or via WP-CLI:
wp plugin activate restaurant-pos
```

The migration will automatically add all required database fields.

### Step 2: Flush Rewrite Rules

```bash
# Via WP-CLI:
wp rewrite flush

# Or in WordPress admin:
Settings → Permalinks → Click "Save Changes"
```

This activates the `/track-order/{token}` route.

### Step 3: Create Test Order

```bash
# Via WP-CLI (if available):
wp eval 'require_once "wp-load.php"; $result = Zaikon_Order_Service::create_order([
    "order_number" => "TEST-" . time(),
    "order_type" => "delivery",
    "items_subtotal_rs" => 500,
    "delivery_charges_rs" => 50,
    "discounts_rs" => 0,
    "taxes_rs" => 0,
    "grand_total_rs" => 550,
    "payment_status" => "unpaid",
    "payment_type" => "cod"
], [
    ["product_name" => "Test Burger", "qty" => 2, "unit_price_rs" => 250, "line_total_rs" => 500]
], [
    "customer_name" => "John Doe",
    "customer_phone" => "+1234567890",
    "location_name" => "Test Area",
    "distance_km" => 5,
    "delivery_charges_rs" => 50,
    "is_free_delivery" => 0
]); print_r($result);'
```

### Step 4: Get Tracking URL

```bash
# Via REST API:
curl -X GET "https://yoursite.com/wp-json/zaikon/v1/orders/123/tracking-url" \
  -H "X-WP-Nonce: YOUR_NONCE"

# Response:
{
  "success": true,
  "tracking_url": "https://yoursite.com/track-order/abc123def456...",
  "tracking_token": "abc123def456...",
  "order_number": "TEST-1234567890"
}
```

### Step 5: Open Tracking Page

Visit: `https://yoursite.com/track-order/{token}`

You should see:
- Order header with order number
- Status timeline showing current status
- Order items
- Customer information
- Real-time countdown (if in cooking/delivery status)

### Step 6: Test Status Updates

```bash
# Confirm order
curl -X PUT "https://yoursite.com/wp-json/zaikon/v1/orders/123/tracking-status" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"status": "confirmed"}'

# Start cooking
curl -X PUT "https://yoursite.com/wp-json/zaikon/v1/orders/123/tracking-status" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"status": "cooking"}'

# Mark ready
curl -X PUT "https://yoursite.com/wp-json/zaikon/v1/orders/123/tracking-status" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"status": "ready"}'

# Assign rider and dispatch
curl -X PUT "https://yoursite.com/wp-json/zaikon/v1/orders/123/assign-rider-info" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"rider_name": "Ali Khan", "rider_phone": "+92 300 1234567"}'

curl -X PUT "https://yoursite.com/wp-json/zaikon/v1/orders/123/tracking-status" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"status": "dispatched"}'

# Mark delivered
curl -X PUT "https://yoursite.com/wp-json/zaikon/v1/orders/123/tracking-status" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"status": "delivered"}'
```

The tracking page will automatically update every 10 seconds via polling.

## 🎨 Customization

### Change Polling Interval

Edit `templates/tracking-page.php`, line ~685:

```javascript
// Change from 10 seconds to 15 seconds
pollInterval = setInterval(async () => {
    await fetchOrderData();
}, 15000); // 15 seconds
```

### Change Default ETAs

Edit `includes/class-zaikon-order-tracking.php`:

```php
// Change cooking ETA from 20 to 30 minutes (line ~125)
if ($current_eta === null) {
    $update_data['cooking_eta_minutes'] = 30; // Changed from 20
}

// Change delivery ETA from 15 to 20 minutes (line ~135)
if ($current_eta === null) {
    $update_data['delivery_eta_minutes'] = 20; // Changed from 15
}
```

### Customize UI Colors

Edit `templates/tracking-page.php` CSS:

```css
/* Change primary gradient */
background: linear-gradient(135deg, #YOUR_COLOR_1 0%, #YOUR_COLOR_2 100%);

/* Change status icon active color */
.status-step.active .status-icon {
    background: #YOUR_PRIMARY_COLOR;
}
```

## 📊 Database Events Log

All tracking events are logged to `wp_zaikon_system_events`:

```sql
SELECT * FROM wp_zaikon_system_events 
WHERE entity_type = 'order' 
AND entity_id = 123
ORDER BY created_at DESC;
```

**Event Types:**
- `create` - Order created
- `status_changed` - Status updated
- `cooking_eta_extended` - ETA extended
- `rider_assigned` - Rider assigned

## 🔧 Troubleshooting

### Tracking Page Shows 404

**Solution:** Flush rewrite rules
```bash
wp rewrite flush
# Or visit Settings → Permalinks → Save
```

### Order Not Found

**Check:**
1. Token is correct (32 hex characters)
2. Order exists in database
3. Token is set in `tracking_token` column

### ETA Not Showing

**Check:**
1. Order status is 'cooking' or 'dispatched'
2. `cooking_started_at` or `dispatched_at` timestamp is set
3. Browser console for JavaScript errors

### Status Not Updating

**Check:**
1. Polling is active (check browser console)
2. REST API is accessible
3. User permissions for status update endpoints

## 🚀 Next Steps (Not Yet Implemented)

### Phase 4: Kitchen Display Integration
- [ ] Add cooking timer countdown to KDS UI
- [ ] Add status transition buttons
- [ ] Auto-extend ETA when cooking exceeds time
- [ ] Real-time sync with tracking page

### Phase 5: POS Integration
- [ ] Add "Share Tracking Link" button to order view
- [ ] Add rider assignment UI
- [ ] Show ETA countdown on POS
- [ ] Dispatched/Delivered action buttons

### Future Enhancements
- [ ] SMS/WhatsApp auto-send tracking link
- [ ] OTP delivery confirmation
- [ ] Customer rating/feedback
- [ ] Push notifications
- [ ] WebSocket for instant updates
- [ ] Analytics dashboard

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review database migration logs
3. Check browser console for JavaScript errors
4. Check PHP error logs for backend issues
5. Contact development team

---

**Last Updated:** 2026-01-25
**Version:** 1.0.0
**Status:** Core Implementation Complete ✅
