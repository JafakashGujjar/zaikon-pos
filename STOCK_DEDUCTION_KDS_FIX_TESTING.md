# Testing Guide: Stock Deduction Centralization & KDS Fix

## Overview
This guide provides manual testing steps for the stock deduction centralization and KDS Complete button fix implemented in this PR.

## Changes Made

### 1. Centralized Stock Deduction
- **New Method**: `RPOS_Orders::deduct_stock_for_order($order_id, $order_items = null)`
  - Prevents double deduction via `ingredients_deducted` flag check
  - Loads order items automatically if not provided
  - Calls both `RPOS_Inventory::deduct_for_order()` and `RPOS_Recipes::deduct_ingredients_for_order()`
  - Marks order as deducted
  
- **Updated Methods**:
  - `RPOS_Orders::create()`: Now calls centralized helper (line 80)
  - `RPOS_Orders::update_status()`: Now calls centralized helper (line 203)

### 2. KDS Complete Button Fix
- **File**: `assets/js/admin.js`
- **Changes**:
  - Disable button on click to prevent double-clicks (lines 569-573)
  - Pass button reference to `updateOrderStatus()` (line 578)
  - Add `processData: false` to AJAX call (line 591)
  - Enhanced error handling with server message display (lines 607-612)
  - Re-enable button in `complete` callback (lines 614-619)
  - Already calls `loadOrders()` on success for immediate UI refresh (line 605)

## Manual Testing Checklist

### Test 1: Standard Sale with Recipe Product - Single Deduction
**Objective**: Verify stock and ingredients are deducted only once when an order is completed

**Prerequisites**:
1. Create a product with a recipe (e.g., "Burger" with ingredients: Bun x1, Patty x1)
2. Note initial stock levels for both product and ingredients

**Steps**:
1. Navigate to Restaurant POS → POS Screen
2. Add the product to cart (quantity: 2)
3. Complete the order
4. Verify order status = "completed"
5. Check product inventory: should be reduced by 2
6. Check ingredient stock:
   - Bun: should be reduced by 2
   - Patty: should be reduced by 2
7. Check `wp_rpos_orders` table: `ingredients_deducted` = 1 for this order
8. Check `wp_rpos_stock_movements`: movement records created for product
9. Check `wp_rpos_ingredient_movements`: movement records created for ingredients

**Expected Result**: ✅ Stock deducted once, movements logged correctly

---

### Test 2: Mixed Order (With and Without Recipes)
**Objective**: Verify only recipe products trigger ingredient deductions; products without recipes work correctly

**Prerequisites**:
1. Product A with recipe (e.g., "Pizza" with Cheese, Dough)
2. Product B without recipe (e.g., "Soda")

**Steps**:
1. Add both products to cart
2. Complete the order
3. Verify:
   - Product A: product stock AND ingredients deducted
   - Product B: only product stock deducted (no ingredients error)
   - No errors in browser console or PHP logs
   - Order completes successfully

**Expected Result**: ✅ Mixed orders complete without errors, correct deductions

---

### Test 3: No Double Deduction Prevention
**Objective**: Verify the flag blocks duplicate stock deductions

**Prerequisites**:
1. Create and complete an order with recipe products

**Steps**:
1. Note the order ID and current stock levels
2. Via database or code, attempt to trigger deduction again:
   ```php
   RPOS_Orders::deduct_stock_for_order($order_id);
   ```
3. Check stock levels before and after
4. Verify method returns `false` (blocked by flag)
5. Verify stock levels unchanged

**Alternative Manual Test**:
1. Complete an order via POS (status = "completed")
2. Use API or admin to change status back to "new"
3. Change status to "completed" again
4. Verify stock is NOT deducted a second time

**Expected Result**: ✅ Flag prevents double deduction

---

### Test 4: KDS Complete - First Click Works
**Objective**: Verify KDS Complete button works on first click and UI refreshes immediately

**Prerequisites**:
1. At least one order in "ready" status
2. Access to KDS screen

**Steps**:
1. Navigate to Restaurant POS → Kitchen Display
2. Locate an order with status = "ready"
3. Click the "Complete" button **once**
4. Observe:
   - Button becomes disabled immediately
   - Toast notification appears: "Updating order status..."
   - On success: Toast shows "Order Completed"
   - Order disappears from KDS grid immediately (or refreshes)
   - Button re-enables (though card may be gone)
5. Verify order status in database = "completed"
6. Verify stock was deducted (if it has recipes)

**Expected Result**: ✅ First click completes order, UI refreshes, no need for second click

---

### Test 5: KDS Button - Prevent Double Click
**Objective**: Verify button is disabled during request to prevent duplicate submissions

**Steps**:
1. Navigate to KDS with an order in "new" status
2. Open browser DevTools → Network tab
3. Click "Start Cooking" button
4. Immediately try to click it again (rapid double-click)
5. Observe Network tab: only 1 PUT request sent
6. Verify button is disabled during request
7. Verify button re-enables after response

**Expected Result**: ✅ Only one request sent, double-click prevented

---

### Test 6: KDS Error Handling
**Objective**: Verify proper error message display

**Steps**:
1. Disconnect from network or simulate API failure
2. Try to update order status via KDS
3. Verify error toast appears with descriptive message
4. Button re-enables so user can retry

**Expected Result**: ✅ Error message shown, button re-enabled for retry

---

## Edge Cases

### EC1: Order Created with status="completed"
**Steps**:
1. Create order via API/code with `status: "completed"` from the start
2. Verify stock deducted immediately
3. Verify flag set to 1

**Expected**: ✅ Works correctly

### EC2: Product Without Recipe
**Steps**:
1. Complete order with product that has no recipe defined
2. Verify no errors occur
3. Verify order completes successfully

**Expected**: ✅ No errors, product stock deducted, no ingredient errors

### EC3: Recipe with Invalid Ingredient ID
**Steps**:
1. Manually create recipe with non-existent ingredient_id in database
2. Complete order
3. Check error logs

**Expected**: ✅ Error logged but order completes (graceful degradation)

---

## Performance Verification

### Database Queries
**Before Centralization**: 
- Multiple calls to check flag and deduct stock scattered across codebase

**After Centralization**:
- Single entry point ensures flag checked once
- Clean separation of concerns

**Test**: Enable WordPress query logging and verify no duplicate deduction queries

---

## Regression Testing

### Existing Functionality
- [ ] POS order creation still works
- [ ] Order status updates via admin still work  
- [ ] Reports still calculate correctly
- [ ] Inventory movements still logged
- [ ] Ingredient movements still logged

---

## Browser Compatibility (KDS JS Changes)

Test KDS in:
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if available)

Verify:
- Button disable/enable works
- AJAX requests sent correctly
- Toast notifications appear
- UI refreshes

---

## Code Quality Checks

✅ PHP Syntax: `php -l includes/class-rpos-orders.php`
✅ JavaScript Syntax: `node -c assets/js/admin.js`

---

## Rollback Plan

If issues occur:
1. Revert commit: `git revert <commit-hash>`
2. The old inline deduction code was:
   - In `create()`: direct calls to `RPOS_Inventory::deduct_for_order()` and `RPOS_Recipes::deduct_ingredients_for_order()`
   - In `update_status()`: flag check + direct calls

---

## Sign-Off

- [ ] All tests passed
- [ ] No regressions found
- [ ] Performance acceptable
- [ ] Code reviewed
- [ ] Ready for production

Tested by: _______________
Date: _______________
Environment: _______________
