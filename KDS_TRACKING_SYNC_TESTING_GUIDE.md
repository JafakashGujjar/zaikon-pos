# KDS to Tracking Page Real-Time Sync - Testing Guide

## Overview
This guide provides step-by-step instructions for testing the real-time synchronization between the Kitchen Display System (KDS) and the Order Tracking Page.

## Implementation Summary

### What Was Changed
1. **Added Change Detection** - The tracking page now tracks previous order status and timestamps to detect when KDS makes changes
2. **Visual Notifications** - Users see toast notifications when order status changes (e.g., "🔥 Your order is now being prepared!")
3. **Enhanced Logging** - Console logs show when KDS updates are detected for debugging
4. **Timer Sync** - Timers always restart when status changes to ensure real-time accuracy
5. **Status Coverage** - All KDS statuses are handled: cooking, ready, completed/dispatched

### How It Works
- **Polling Interval**: 5 seconds (same as KDS auto-refresh)
- **Change Detection**: Compares previous vs current order status and timestamps
- **Timer Restart**: Automatically restarts countdown timers when status transitions occur
- **Visual Feedback**: Shows animated toast notifications sliding in from the right

## Pre-Testing Setup

### Prerequisites
1. WordPress installation with Restaurant POS plugin active
2. At least one test order with a tracking token
3. Access to both KDS and tracking page URLs
4. Browser developer console access (for viewing logs)

### Get Tracking URL
1. Create a test order via POS
2. Note the order number (e.g., #1234)
3. Get tracking token from database or API:
   ```sql
   SELECT tracking_token, order_number FROM wp_zaikon_orders WHERE order_number = '1234';
   ```
4. Tracking URL format: `https://yoursite.com/track-order/{token}`

## Test Scenarios

### Test 1: Start Cooking Action

**Objective**: Verify tracking page updates when KDS starts cooking

**Steps**:
1. Open tracking page for a new order (status: pending/confirmed)
   - Should show Step 1 active: "Order Confirmed"
   
2. Open KDS page in another browser tab/window
   - Navigate to: Admin → Restaurant POS → Kitchen Display
   
3. Open browser console on tracking page (F12)
   - Look for "ZAIKON TRACKING: Order data received" logs
   
4. In KDS, click "🔥 Start Cooking" button for the test order
   
5. **Expected Results on Tracking Page** (within 5 seconds):
   - ✅ Console shows: `🔄 KDS UPDATE DETECTED: {statusChanged: "pending → cooking", ...}`
   - ✅ Visual notification appears: "🔥 Your order is now being prepared!"
   - ✅ Step 2 becomes active: "Preparing Your Order"
   - ✅ Cooking countdown timer appears showing "20:00" and starts counting down
   - ✅ Step 1 changes to "completed" with checkmark
   
6. **Verify Timer Accuracy**:
   - Timer should count down: 19:59, 19:58, etc.
   - After 1 minute, should show approximately 19:00

---

### Test 2: Mark Ready Action

**Objective**: Verify tracking page updates when KDS marks order ready

**Steps**:
1. Start with an order in "cooking" state (from Test 1)
   - Tracking page shows Step 2 active with cooking timer
   
2. In KDS, click "✅ Mark Ready" button
   
3. **Expected Results on Tracking Page** (within 5 seconds):
   - ✅ Console shows: `🔄 KDS UPDATE DETECTED: {statusChanged: "cooking → ready", orderReady: true, ...}`
   - ✅ Visual notification appears: "✅ Your order is ready!"
   - ✅ Step 3 becomes active: "Rider On The Way"
   - ✅ Cooking countdown timer disappears
   - ✅ Delivery countdown timer appears showing "10:00" and starts counting down
   - ✅ Step 2 changes to "completed" with checkmark
   - ✅ Animated rider SVG appears

---

### Test 3: Complete Order Action

**Objective**: Verify tracking page updates when KDS completes order

**Steps**:
1. Start with an order in "ready" state (from Test 2)
   - Tracking page shows Step 3 active with delivery timer
   
2. In KDS, click "✔ Complete" button
   
3. **Expected Results on Tracking Page** (within 5 seconds):
   - ✅ Console shows: `🔄 KDS UPDATE DETECTED: {statusChanged: "ready → completed" OR "ready → dispatched", ...}`
   - ✅ Visual notification appears: "🚚 Your order is on the way!"
   - ✅ Step 3 remains active (or transitions based on order type)
   - ✅ Delivery countdown continues (if order has delivery)
   - ✅ Timestamp updates to show completion time

---

### Test 4: Rapid Status Changes

**Objective**: Verify tracking page handles rapid KDS updates

**Steps**:
1. Create a new test order
2. Open tracking page
3. Quickly perform KDS actions in sequence:
   - Start Cooking
   - Wait 2-3 seconds
   - Mark Ready
   - Wait 2-3 seconds
   - Complete Order
   
4. **Expected Results**:
   - ✅ All status transitions are detected and logged
   - ✅ Visual notifications appear for each transition (may overlap)
   - ✅ Timers restart correctly at each transition
   - ✅ No JavaScript errors in console
   - ✅ UI remains responsive

---

### Test 5: Timer Accuracy Over Time

**Objective**: Verify timers remain accurate during long sessions

**Steps**:
1. Start an order in "cooking" state
2. Open tracking page with `?debug=time` parameter
   - URL: `https://yoursite.com/track-order/{token}?debug=time`
   
3. Let page run for 5-10 minutes
   
4. Monitor console logs for timing debug info
   
5. **Expected Results**:
   - ✅ Timer counts down accurately (verify against actual clock)
   - ✅ "Server-Client offset" remains stable in debug logs
   - ✅ Timer continues working if browser tab loses focus
   - ✅ When tab regains focus, timer shows correct remaining time

---

### Test 6: Multiple Simultaneous Orders

**Objective**: Verify tracking pages for different orders don't interfere

**Steps**:
1. Create 2-3 test orders
2. Open tracking page for each in separate browser tabs
3. In KDS, update different orders' statuses
   
4. **Expected Results**:
   - ✅ Each tracking page only updates for its own order
   - ✅ No cross-contamination between order updates
   - ✅ Notifications appear only in relevant tabs

---

### Test 7: Edge Cases

#### 7a. Order Already Completed
1. Open tracking page for a delivered order
2. **Expected**: 
   - Shows Step 3 complete
   - No timers running
   - Polling stops (no more API calls)

#### 7b. Invalid/Expired Token
1. Access tracking page with invalid token
2. **Expected**: 
   - Shows error message
   - No infinite polling loop

#### 7c. Network Interruption
1. Open tracking page
2. Disconnect network/turn off WiFi
3. Reconnect after 30 seconds
4. **Expected**: 
   - Page resumes polling
   - Catches up with latest order status

---

## Debugging Tips

### Console Logs to Monitor

1. **Every 5 seconds (polling)**:
   ```
   ZAIKON TRACKING: Fetching order...
   ZAIKON TRACKING: Order data received {order_number: "1234", status: "cooking", ...}
   ```

2. **When status changes**:
   ```
   🔄 KDS UPDATE DETECTED: {
     statusChanged: "pending → cooking",
     cookingStarted: true,
     timestamp: "2024-01-31T15:30:00.000Z"
   }
   ```

3. **With ?debug=time parameter**:
   ```
   🕐 ZAIKON Tracking Timer Debug Info
   === Time Synchronization ===
   Server UTC: ...
   Client time: ...
   Server-Client offset: ...
   ```

### Common Issues & Solutions

**Issue**: No visual notifications appear
- Check browser console for JavaScript errors
- Verify notification styles are being injected
- Check if notification element is being created in DOM

**Issue**: Timer doesn't restart on status change
- Verify order timestamps are set in database (`cooking_started_at`, `ready_at`)
- Check console for change detection logs
- Ensure polling is active (every 5 seconds)

**Issue**: Status changes not detected
- Verify polling interval is running (check network tab)
- Confirm KDS is updating the database (check MySQL)
- Verify API endpoint returns correct data

---

## Browser Compatibility Testing

Test in the following browsers:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

---

## Performance Considerations

### Expected Behavior
- **API Calls**: 1 every 5 seconds (12 per minute)
- **CPU Usage**: Minimal (timers update every 1 second)
- **Memory**: Stable (no memory leaks from intervals)
- **Network**: ~1-2 KB per polling request

### Monitor For
- Memory leaks from notification elements not being cleaned up
- Interval timers not being cleared properly
- Excessive API calls if polling doesn't pause when tab is hidden

---

## Success Criteria

The implementation is successful if:

1. ✅ All status changes from KDS appear on tracking page within 5 seconds
2. ✅ Visual notifications appear for each status transition
3. ✅ Timers display accurate countdown and restart on status changes
4. ✅ Console logs confirm change detection is working
5. ✅ No JavaScript errors in console during normal operation
6. ✅ UI remains responsive during all transitions
7. ✅ Works correctly across different browsers
8. ✅ Memory and CPU usage remain stable over extended periods

---

## Rollback Plan

If issues are found:

1. **Minor Issues**: Can be fixed with additional commits
2. **Major Issues**: Revert commits:
   ```bash
   git revert HEAD~2..HEAD
   git push origin copilot/sync-tracking-page-with-kds
   ```

---

## Next Steps After Testing

1. Document any bugs found
2. Create follow-up issues for enhancements
3. Consider adding automated tests
4. Potential future improvements:
   - WebSocket/Server-Sent Events for true push notifications (no polling)
   - Sound notifications for status changes
   - Push notifications via Service Worker
   - Reduce polling interval to 3 seconds for even faster updates
