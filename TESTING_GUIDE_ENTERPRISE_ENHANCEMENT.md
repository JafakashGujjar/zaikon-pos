# Enterprise Enhancement Testing Guide

## Prerequisites

Before testing, ensure you have:
- ✅ WordPress installation with Zaikon POS plugin active
- ✅ Database backup (in case migration needs to be rolled back)
- ✅ At least one cashier user account
- ✅ At least one delivery rider
- ✅ Some products in the system
- ✅ Delivery areas configured

---

## Part 1: Database Migration Testing

### Test 1.1: Fresh Installation
**Goal**: Verify migration works on new installations

1. Deactivate and delete plugin (if installed)
2. Reinstall plugin
3. Activate plugin
4. Check database:
   ```sql
   SHOW COLUMNS FROM wp_zaikon_orders LIKE 'payment_status';
   -- Should show: enum('unpaid','paid','cod_pending','cod_received','refunded','void')
   
   SHOW COLUMNS FROM wp_zaikon_orders LIKE 'order_status';
   -- Should show: enum('active','delivered','completed','cancelled','replacement')
   ```

**Expected Result**: ✅ Both ENUM fields contain new values

### Test 1.2: Existing Installation Upgrade
**Goal**: Verify migration doesn't break existing data

1. Before upgrade: Note count of existing orders
   ```sql
   SELECT COUNT(*) FROM wp_zaikon_orders;
   ```
2. Update plugin files
3. Plugin auto-runs migration
4. After upgrade: Verify count matches
5. Check that old orders still have valid statuses

**Expected Result**: ✅ All existing data intact, new ENUM values available

---

## Part 2: REST API Endpoint Testing

### Test 2.1: Mark Order as Delivered
**Goal**: Test new `/orders/{id}/mark-delivered` endpoint

**Setup:**
1. Create a delivery order (COD payment type)
2. Note the order ID

**Test Steps:**
```bash
# Using curl or Postman
curl -X PUT \
  'https://your-site.com/wp-json/zaikon/v1/orders/123/mark-delivered' \
  -H 'X-WP-Nonce: YOUR_NONCE_HERE' \
  -H 'Cookie: wordpress_logged_in_xxx=...'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Order marked as delivered"
}
```

**Database Verification:**
```sql
-- Check order status updated
SELECT order_status FROM wp_zaikon_orders WHERE id = 123;
-- Should return: 'delivered'

-- Check delivery status updated
SELECT delivery_status FROM wp_zaikon_deliveries WHERE order_id = 123;
-- Should return: 'delivered'

-- Check rider order status updated
SELECT status FROM wp_zaikon_rider_orders WHERE order_id = 123;
-- Should return: 'delivered'
```

### Test 2.2: Mark COD as Received
**Goal**: Test new `/orders/{id}/mark-cod-received` endpoint

**Setup:**
1. Use same order from Test 2.1 (now delivered)

**Test Steps:**
```bash
curl -X PUT \
  'https://your-site.com/wp-json/zaikon/v1/orders/123/mark-cod-received' \
  -H 'X-WP-Nonce: YOUR_NONCE_HERE' \
  -H 'Cookie: wordpress_logged_in_xxx=...'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "COD marked as received"
}
```

**Database Verification:**
```sql
-- Check payment status updated
SELECT payment_status, order_status 
FROM wp_zaikon_orders 
WHERE id = 123;
-- Should return: payment_status='cod_received', order_status='completed'
```

### Test 2.3: Error Handling - Non-COD Order
**Goal**: Verify validation prevents marking non-COD as COD received

**Setup:**
1. Create a cash payment order

**Test Steps:**
```bash
curl -X PUT \
  'https://your-site.com/wp-json/zaikon/v1/orders/456/mark-cod-received' \
  -H 'X-WP-Nonce: YOUR_NONCE_HERE'
```

**Expected Response:**
```json
{
  "code": "invalid_payment_type",
  "message": "This order is not COD",
  "data": { "status": 400 }
}
```

---

## Part 3: Frontend UI Testing

### Test 3.1: Orders Button Gradient
**Goal**: Verify button styling updated

**Test Steps:**
1. Log in as cashier
2. Open POS screen (Restaurant POS → POS Screen)
3. Locate "Orders" button in header

**Visual Check:**
- ✅ Button has gradient background (purple to red/orange)
- ✅ Hover effect shows darker gradient
- ✅ Button is clearly visible and styled differently from other buttons

**Screenshot:** Take a screenshot showing the Orders button

### Test 3.2: My Orders Modal - Mark Delivered Button
**Goal**: Test "Mark Delivered" button appears and works

**Test Steps:**
1. Log in as cashier
2. Open shift (if not already open)
3. Create a delivery order:
   - Select products
   - Choose "Delivery" order type
   - Select "COD" payment type
   - Fill delivery details
   - Complete order
4. Click "Orders" button
5. Find the order in the modal

**Visual Check:**
- ✅ Order shows with "ACTIVE" status badge (green)
- ✅ Payment shows "COD_PENDING" or "UNPAID" badge (orange)
- ✅ "Mark Delivered" button appears (blue button)
- ✅ Modal is centered on screen

**Action:**
1. Click "Mark Delivered" button
2. Confirm in dialog

**Expected Result:**
- ✅ Toast notification: "Order marked as delivered"
- ✅ Order list refreshes
- ✅ Order status badge changes to "DELIVERED" (blue)
- ✅ "Mark Delivered" button disappears
- ✅ "Mark COD Received" button appears (purple)

**Screenshot:** Take screenshots before and after clicking

### Test 3.3: My Orders Modal - Mark COD Received Button
**Goal**: Test "Mark COD Received" button appears and works

**Test Steps:**
1. Continue from Test 3.2 (order should be delivered)
2. Order should show:
   - Status: "DELIVERED" (blue badge)
   - Payment: "COD_PENDING" or "UNPAID" (orange badge)
   - "Mark COD Received" button visible (purple)

**Action:**
1. Click "Mark COD Received" button
2. Confirm in dialog

**Expected Result:**
- ✅ Toast notification: "COD payment received"
- ✅ Order list refreshes
- ✅ Order status changes to "COMPLETED" (green)
- ✅ Payment status changes to "COD_RECEIVED" (purple)
- ✅ "Mark COD Received" button disappears

**Screenshot:** Take screenshot of final state

### Test 3.4: Status Badge Colors
**Goal**: Verify all status badges display correct colors

**Test Steps:**
Create orders with different states and verify colors:

1. **Active Order** → Green badge
2. **Delivered Order** → Blue badge
3. **Completed Order** → Green badge
4. **Cancelled Order** → Red badge
5. **COD Pending** → Orange badge
6. **COD Received** → Purple badge
7. **Paid** → Green badge
8. **Unpaid** → Orange badge

**Screenshot:** Capture multiple orders showing different badge colors

---

## Part 4: COD Workflow Testing

### Test 4.1: Complete COD Order Lifecycle
**Goal**: Test entire workflow from creation to completion

**Test Steps:**

**Step 1: Create COD Order**
1. Create delivery order with COD payment
2. Verify initial state:
   - Order Status: "ACTIVE"
   - Payment Status: "UNPAID" or "COD_PENDING"

**Step 2: Mark as Delivered**
1. Click "Mark Delivered"
2. Verify state:
   - Order Status: "DELIVERED"
   - Payment Status: "COD_PENDING"
   - "Mark COD Received" button appears

**Step 3: Mark COD Received**
1. Click "Mark COD Received"
2. Verify final state:
   - Order Status: "COMPLETED"
   - Payment Status: "COD_RECEIVED"

**Step 4: Verify in Database**
```sql
SELECT order_status, payment_status, grand_total_rs
FROM wp_zaikon_orders
WHERE id = [order_id];
```

Expected: `order_status='completed'`, `payment_status='cod_received'`

---

## Part 5: Shift Closing Testing

### Test 5.1: COD Collection Calculation
**Goal**: Verify COD received orders are included in shift totals

**Test Steps:**

**Setup:**
1. Open new shift
2. Record opening cash amount
3. Create 3 COD delivery orders:
   - Order A: Rs 500
   - Order B: Rs 750
   - Order C: Rs 1000
   - Total: Rs 2250

**Processing:**
1. Mark Order A as delivered, then COD received
2. Mark Order B as delivered, then COD received
3. Leave Order C as active (not delivered)

**Close Shift:**
1. Click close shift button
2. Check "COD Collected" field

**Expected Result:**
- ✅ COD Collected = Rs 1250 (Order A + Order B only)
- ✅ Order C not included (not marked as COD received)

**Database Verification:**
```sql
-- Orders with cod_received status
SELECT SUM(grand_total_rs) 
FROM wp_zaikon_orders 
WHERE payment_type = 'cod' 
  AND payment_status = 'cod_received';
-- Should match COD Collected amount
```

### Test 5.2: Multiple Payment Types
**Goal**: Verify cash, online, and COD are calculated separately

**Test Steps:**

**Setup:**
1. Open shift with Rs 1000 opening cash
2. Create orders:
   - 2 cash orders: Rs 500 each = Rs 1000
   - 1 online order: Rs 300
   - 2 COD orders: Rs 400 each = Rs 800
3. Mark all as delivered
4. Mark COD orders as received

**Close Shift:**
Expected totals:
- Opening Cash: Rs 1000
- Cash Sales: Rs 1000
- Expected Cash: Rs 2000
- COD Collected: Rs 800
- Online/Card: Rs 300

**Verification:**
Enter actual cash = Rs 2000
- ✅ Variance should be Rs 0

---

## Part 6: Edge Cases & Error Handling

### Test 6.1: Non-Delivery Orders
**Goal**: Verify buttons don't appear for dine-in/takeaway

**Test Steps:**
1. Create dine-in order
2. View in Orders modal

**Expected Result:**
- ✅ No "Mark Delivered" button
- ✅ Only standard buttons shown (Cancel, Replacement)

### Test 6.2: Already Completed Order
**Goal**: Verify no action buttons for completed orders

**Test Steps:**
1. Complete an order fully
2. View in Orders modal

**Expected Result:**
- ✅ Status shows "COMPLETED"
- ✅ No "Mark Delivered" or "Mark COD Received" buttons
- ✅ Only "Replacement" button available

### Test 6.3: Permission Testing
**Goal**: Verify only authorized users can access

**Test Steps:**
1. Log in as user without POS permissions
2. Try to access POS screen

**Expected Result:**
- ✅ Access denied or redirected
- ✅ Cannot execute REST API calls

### Test 6.4: Network Error Handling
**Goal**: Test behavior when API calls fail

**Test Steps:**
1. Disconnect internet (or block API endpoint)
2. Try to mark order as delivered
3. Observe behavior

**Expected Result:**
- ✅ Error toast appears: "Failed to mark as delivered"
- ✅ Order status doesn't change
- ✅ No partial updates

---

## Part 7: Backend Admin Pages

### Test 7.1: Rider Payroll Page
**Goal**: Verify rider payout calculations include new statuses

**Test Steps:**
1. Go to Restaurant POS → Rider Payroll
2. Select date range with test orders
3. Verify riders with delivered orders show correct:
   - Delivery count
   - Total distance
   - Payout amount

**Expected Result:**
- ✅ Delivered orders counted
- ✅ Payouts calculated correctly

### Test 7.2: Rider Deliveries Admin
**Goal**: Verify delivery logs show all statuses

**Test Steps:**
1. Go to Restaurant POS → Rider Deliveries (Admin)
2. Check filter options
3. Filter by status "delivered"

**Expected Result:**
- ✅ All delivered orders shown
- ✅ Status displays correctly
- ✅ Filters work properly

### Test 7.3: Shift Reports
**Goal**: Verify shift reports show COD correctly

**Test Steps:**
1. Go to Restaurant POS → Shift Reports
2. Find shift from Test 5.1
3. Check shift details

**Expected Result:**
- ✅ COD collected matches test amount
- ✅ All payment types shown separately
- ✅ Variance calculated correctly

---

## Part 8: Browser Compatibility

Test on multiple browsers:

### Test 8.1: Chrome/Edge
- [ ] Orders button gradient displays
- [ ] Modal centers properly
- [ ] All buttons functional
- [ ] Status badges show colors

### Test 8.2: Firefox
- [ ] Orders button gradient displays
- [ ] Modal centers properly
- [ ] All buttons functional
- [ ] Status badges show colors

### Test 8.3: Safari (if available)
- [ ] Orders button gradient displays
- [ ] Modal centers properly
- [ ] All buttons functional
- [ ] Status badges show colors

---

## Part 9: Mobile Responsiveness

### Test 9.1: Mobile View
**Test on mobile device or use browser dev tools**

**Test Steps:**
1. Open POS on mobile
2. Click Orders button
3. Check modal appearance
4. Test button clicks

**Expected Result:**
- ✅ Modal adapts to screen size
- ✅ Buttons are touch-friendly
- ✅ Text is readable
- ✅ All functionality works

---

## Testing Checklist Summary

Use this checklist to track testing progress:

### Database & API
- [ ] Migration works on fresh install
- [ ] Migration works on existing install
- [ ] Mark delivered endpoint works
- [ ] Mark COD received endpoint works
- [ ] Validation prevents invalid operations
- [ ] Error handling works correctly

### Frontend UI
- [ ] Orders button has gradient
- [ ] Modal is centered
- [ ] Mark Delivered button appears correctly
- [ ] Mark COD Received button appears correctly
- [ ] Status badges show correct colors
- [ ] Action buttons work as expected

### Workflows
- [ ] COD order complete lifecycle works
- [ ] Non-delivery orders work correctly
- [ ] Shift closing includes COD correctly
- [ ] Multiple payment types handled correctly

### Edge Cases
- [ ] Non-delivery orders handled
- [ ] Completed orders handled
- [ ] Permissions enforced
- [ ] Network errors handled

### Admin Pages
- [ ] Rider Payroll accurate
- [ ] Delivery logs show all data
- [ ] Shift reports accurate

### Compatibility
- [ ] Chrome/Edge compatible
- [ ] Firefox compatible
- [ ] Safari compatible (if available)
- [ ] Mobile responsive

---

## Issue Reporting Template

If you find any issues during testing, report using this template:

```
**Issue Title**: Brief description

**Test Section**: Which test revealed the issue

**Steps to Reproduce**:
1. Step one
2. Step two
3. Step three

**Expected Result**: What should happen

**Actual Result**: What actually happened

**Environment**:
- WordPress Version: 
- PHP Version: 
- Browser: 
- Device: 

**Screenshots**: Attach relevant screenshots

**Database State**: Include relevant SQL queries/results if applicable
```

---

## Test Sign-off

Once all tests pass, complete this section:

**Tested By**: _________________
**Date**: _________________
**WordPress Version**: _________________
**PHP Version**: _________________
**Database**: MySQL ___ / MariaDB ___

**Overall Result**: ✅ PASS / ❌ FAIL

**Notes**:
_____________________________________________________________
_____________________________________________________________
_____________________________________________________________

---

**End of Testing Guide**
