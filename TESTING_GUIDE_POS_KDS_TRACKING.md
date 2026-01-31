# POS/KDS/Tracking Synchronization - Testing Guide

## Overview
This guide provides step-by-step instructions to verify that the POS, KDS, and Tracking systems are now properly synchronized through the unified `zaikon_orders` table.

---

## Pre-Testing Checklist

### 1. Database Backup
**CRITICAL:** Back up your database before testing.

```bash
# WordPress CLI backup
wp db export backup-before-testing.sql

# Or via phpMyAdmin
# Export wp_rpos_orders and wp_zaikon_orders tables
```

### 2. Clear WordPress Caches
```bash
# Clear WordPress object cache
wp cache flush

# Clear all transients
wp transient delete --all
```

### 3. Browser Setup
- Open in **private/incognito window** (avoid cache issues)
- Open **browser console** (F12) to monitor for errors
- Have **two browser windows** ready:
  - Window 1: WordPress Admin (KDS)
  - Window 2: Customer Tracking Page

---

## Test Scenario 1: Dine-In Order Flow

### Step 1: Create Dine-In Order from POS
1. Navigate to **POS** (wp-admin → Restaurant POS → POS)
2. Add items to cart (at least 2 items)
3. Set order type: **Dine-In**
4. Click **Place Order**
5. **Expected:** Order confirmation message appears

### Step 2: Verify Order in Database
```sql
-- Check zaikon_orders (PRIMARY)
SELECT id, order_number, order_status, tracking_token, created_at
FROM wp_zaikon_orders
ORDER BY created_at DESC
LIMIT 1;

-- Check rpos_orders (SYNC)
SELECT id, order_number, status, created_at
FROM wp_rpos_orders
ORDER BY created_at DESC
LIMIT 1;

-- Verify they match
SELECT 
  z.order_number,
  z.order_status as zaikon_status,
  r.status as rpos_status,
  z.tracking_token,
  z.id as zaikon_id,
  r.id as rpos_id
FROM wp_zaikon_orders z
LEFT JOIN wp_rpos_orders r ON z.order_number = r.order_number
ORDER BY z.created_at DESC
LIMIT 1;
```

**Expected Results:**
- ✅ Order exists in **both** tables
- ✅ `zaikon_orders.order_status` = `'confirmed'`
- ✅ `rpos_orders.status` = `'confirmed'`
- ✅ `tracking_token` is set (32-character hex string)
- ✅ Same `order_number` in both tables

### Step 3: Verify Order in KDS
1. Navigate to **KDS** (wp-admin → Restaurant POS → KDS)
2. Look for the order in the list
3. Click **"All Orders"** filter
4. Click **"New"** filter

**Expected Results:**
- ✅ Order appears in KDS
- ✅ Order visible in both "All Orders" and "New" filters
- ✅ Order shows correct items
- ✅ Order shows "Start Cooking" button

### Step 4: Update Status in KDS (Cooking)
1. Click **"🔥 Start Cooking"** button
2. Wait for confirmation

**Expected Results:**
- ✅ Button changes to "✅ Mark Ready"
- ✅ Order card updates visually
- ✅ No JavaScript errors in console

### Step 5: Verify Database After Cooking
```sql
SELECT 
  order_number,
  order_status,
  cooking_started_at,
  ready_at,
  dispatched_at
FROM wp_zaikon_orders
WHERE order_number = 'ORD-XXXXXXXX'; -- Replace with actual order number
```

**Expected Results:**
- ✅ `order_status` = `'cooking'`
- ✅ `cooking_started_at` is set (NOT NULL)
- ✅ `ready_at` is NULL
- ✅ `dispatched_at` is NULL

### Step 6: Update Status in KDS (Ready)
1. Click **"✅ Mark Ready"** button
2. Wait for confirmation

**Expected Results:**
- ✅ Button changes to "✔ Complete"
- ✅ Order card updates
- ✅ Order moves to "Ready" filter

### Step 7: Verify Database After Ready
```sql
SELECT 
  order_number,
  order_status,
  cooking_started_at,
  ready_at,
  dispatched_at
FROM wp_zaikon_orders
WHERE order_number = 'ORD-XXXXXXXX';
```

**Expected Results:**
- ✅ `order_status` = `'ready'`
- ✅ `cooking_started_at` is set
- ✅ `ready_at` is set (NOT NULL)
- ✅ `dispatched_at` is NULL

### Step 8: Complete Order
1. Click **"✔ Complete"** button
2. Wait for confirmation

**Expected Results:**
- ✅ Order disappears from KDS (moved to completed)
- ✅ Order no longer in "All Orders" filter

---

## Test Scenario 2: Delivery Order with Tracking

### Step 1: Create Delivery Order
1. Navigate to **POS**
2. Add items to cart
3. Set order type: **Delivery**
4. Fill in customer details:
   - Customer Name
   - Customer Phone
   - Delivery Location
5. Click **Place Order**

### Step 2: Verify Order Created
```sql
SELECT 
  z.id,
  z.order_number,
  z.order_status,
  z.tracking_token,
  d.customer_name,
  d.customer_phone,
  d.delivery_status
FROM wp_zaikon_orders z
LEFT JOIN wp_zaikon_deliveries d ON z.id = d.order_id
ORDER BY z.created_at DESC
LIMIT 1;
```

**Expected Results:**
- ✅ Order in `zaikon_orders` with `order_status` = `'pending'` or `'active'`
- ✅ `tracking_token` is set
- ✅ Delivery record exists in `zaikon_deliveries`
- ✅ Customer details populated

### Step 3: Open Tracking Page
1. Get the tracking token from database
2. Navigate to: `https://yoursite.com/track-order/{tracking_token}`
3. OR search by order number

**Expected Results:**
- ✅ Tracking page loads successfully
- ✅ Order details displayed
- ✅ Progress bar shows current status
- ✅ Items list is visible

### Step 4: Update Order in KDS
1. Open KDS in separate window
2. Find the delivery order
3. Click **"🔥 Start Cooking"**

### Step 5: Verify Tracking Updates
1. **Refresh** the tracking page (F5)
2. OR wait for auto-refresh (if implemented)

**Expected Results:**
- ✅ Status changed to "Cooking" or equivalent
- ✅ Progress bar advanced
- ✅ Timestamp for cooking updated

### Step 6: Mark Order Ready
1. In KDS, click **"✅ Mark Ready"**
2. Refresh tracking page

**Expected Results:**
- ✅ Tracking shows "Ready" status
- ✅ Progress bar updated
- ✅ Ready timestamp displayed

### Step 7: Complete Order
1. In KDS, click **"✔ Complete"**
2. Refresh tracking page

**Expected Results:**
- ✅ Tracking shows "Dispatched" or "Completed"
- ✅ Progress bar at final stage
- ✅ All timestamps visible

---

## Test Scenario 3: Status Sync Verification

### Verify Real-Time Sync
1. Create an order
2. Open KDS
3. Open Tracking Page (side by side)
4. Update status in KDS
5. **Immediately** refresh tracking page

**Expected Results:**
- ✅ Status change appears in Tracking **within 1 second** of refresh
- ✅ No delay or lag
- ✅ Timestamps match between systems

### Verify Backward Sync
```sql
-- Check if rpos_orders is synced
SELECT 
  z.order_number,
  z.order_status as zaikon_status,
  r.status as rpos_status,
  CASE 
    WHEN z.order_status = r.status THEN 'SYNCED ✓'
    ELSE 'OUT OF SYNC ✗'
  END as sync_status
FROM wp_zaikon_orders z
LEFT JOIN wp_rpos_orders r ON z.order_number = r.order_number
WHERE z.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY z.created_at DESC;
```

**Expected Results:**
- ✅ All recent orders show "SYNCED ✓"
- ✅ zaikon_status matches rpos_status

---

## Test Scenario 4: Performance Testing

### Test KDS Load Time
1. Create 20+ orders (can use test data)
2. Navigate to KDS
3. Measure page load time
4. Check browser console for query count

**Expected Results:**
- ✅ Page loads in < 3 seconds
- ✅ No N+1 query warnings in console
- ✅ Smooth scrolling, no lag

### Monitor Database Queries
```sql
-- Enable query logging in WordPress (wp-config.php)
define('SAVEQUERIES', true);

-- After page load, check query count
// In KDS page, add to footer:
<?php
if (defined('SAVEQUERIES') && SAVEQUERIES) {
    global $wpdb;
    echo "Total Queries: " . count($wpdb->queries);
}
?>
```

**Expected Results:**
- ✅ Total queries < 10 for KDS page load
- ✅ No duplicate queries for same data

---

## Test Scenario 5: Edge Cases

### Test 1: Order Without Items
1. Create order with no items (if allowed)
2. Verify it appears in KDS
3. Verify tracking works

### Test 2: Multiple Status Updates
1. Create order
2. Rapidly click status buttons
3. Verify only final status persists

### Test 3: Token Verification
1. Use invalid token: `https://yoursite.com/track-order/invalid123`
2. **Expected:** Error message, not order details

### Test 4: Concurrent KDS Users
1. Open KDS in 2 browser sessions (different users)
2. Update order status in Session 1
3. Refresh KDS in Session 2
4. **Expected:** Both see same status

---

## Debugging Checklist

### If Orders Don't Appear in KDS

1. **Check Database:**
   ```sql
   SELECT COUNT(*) FROM wp_zaikon_orders WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
   ```
   - If count is 0, order creation is failing

2. **Check JavaScript Console:**
   - Look for 404 errors on REST API calls
   - Check for permission errors

3. **Check REST API:**
   ```bash
   # Test REST endpoint
   curl -X GET "https://yoursite.com/wp-json/restaurant-pos/v1/orders" \
     -H "X-WP-Nonce: YOUR_NONCE"
   ```

### If Tracking Page Doesn't Update

1. **Verify Token:**
   ```sql
   SELECT tracking_token FROM wp_zaikon_orders WHERE order_number = 'ORD-XXXXXXXX';
   ```
   - Token should be 32-character hex string

2. **Check Tracking Page Logic:**
   - Ensure it's reading from `zaikon_orders`
   - Verify it's using `tracking_token` or `order_number`

3. **Clear All Caches:**
   - WordPress object cache
   - Browser cache
   - Server cache (if any)

### If Statuses Don't Sync

1. **Check Event System:**
   ```sql
   -- Look for recent events in logs
   SELECT * FROM wp_zaikon_system_events 
   WHERE event_type LIKE '%order%' 
   ORDER BY created_at DESC 
   LIMIT 20;
   ```

2. **Verify Sync Function:**
   - Check error logs for sync failures
   - Ensure both tables exist

---

## Success Criteria

### ✅ All Tests Pass If:
1. Orders created in POS appear in both `zaikon_orders` and `rpos_orders`
2. KDS reads orders from `zaikon_orders`
3. KDS status updates immediately update `zaikon_orders.order_status`
4. Tracking page shows real-time status from `zaikon_orders`
5. Status changes in KDS are visible in Tracking within 1 second
6. All timestamps (cooking_started_at, ready_at, etc.) are set correctly
7. Tracking tokens work for all orders
8. Performance is acceptable (KDS loads < 3 seconds with 50 orders)
9. No JavaScript errors in console
10. Database queries are optimized (< 10 queries per page)

---

## Rollback Instructions

If critical issues are found:

```bash
# 1. Checkout previous commit
git checkout ca61acb  # Or the commit before changes

# 2. Restore database from backup
wp db import backup-before-testing.sql

# 3. Clear caches
wp cache flush
wp transient delete --all
```

---

## Reporting Issues

If you find issues, collect:
1. **Screenshots** of the problem
2. **Browser console logs** (F12 → Console)
3. **Database query results** showing the issue
4. **Steps to reproduce** the problem
5. **Expected vs. Actual behavior**

Submit to development team with all above information.

---

## Conclusion

This testing guide ensures that the POS/KDS/Tracking synchronization fix is working correctly. Complete all test scenarios to verify the enterprise-level reliability of the system.

**Remember:** The goal is real-time, reliable, traceable order management across all systems.
