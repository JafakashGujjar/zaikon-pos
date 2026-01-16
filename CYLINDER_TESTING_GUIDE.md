# Enterprise Cylinder Management - Testing Guide

## Overview
This document provides step-by-step testing procedures for the new enterprise cylinder management features.

## Prerequisites
- WordPress site with Restaurant POS plugin installed
- Admin access with `rpos_manage_inventory` capability
- At least one product configured
- At least one cylinder type configured

## Test Suite

### 1. Database Schema Verification

**Objective:** Verify all new tables and columns were created successfully

**Steps:**
1. Access WordPress admin dashboard
2. Navigate to any page to trigger plugin activation and migrations
3. Check database for new tables (using phpMyAdmin or SQL query):
   - `wp_zaikon_cylinder_zones`
   - `wp_zaikon_cylinder_lifecycle`
   - `wp_zaikon_cylinder_consumption`
   - `wp_zaikon_cylinder_refill`
   - `wp_zaikon_cylinder_forecast_cache`
4. Check `wp_rpos_gas_cylinders` table for new columns:
   - `zone_id`
   - `orders_served`
   - `remaining_percentage`
   - `vendor`

**Expected Result:** All tables and columns exist with correct structure

---

### 2. Zone Management

**Objective:** Test creating and managing cylinder zones

**Steps:**
1. Navigate to Restaurant POS → Gas Cylinders
2. Click on "Zones" tab
3. Fill in the form:
   - Zone Name: "Oven"
   - Description: "Main oven cooking zone"
4. Click "Add Zone"
5. Verify zone appears in the list
6. Repeat for additional zones: "Counter", "Grill"

**Expected Result:** 
- Success message displayed
- Zones listed in table with descriptions
- Active cylinder count shows 0 for new zones

---

### 3. Product Mapping to Cylinder Types

**Objective:** Test mapping products to cylinder types (existing feature verification)

**Steps:**
1. Navigate to Restaurant POS → Gas Cylinders (original interface)
2. Go to "Product Mapping" tab
3. For each cylinder type:
   - Select 2-3 products that would use that cylinder
   - Click "Update Mapping"
4. Verify success message

**Expected Result:** Products are mapped to cylinder types for consumption tracking

---

### 4. Cylinder Creation with Zone Assignment

**Objective:** Test creating cylinders with zone assignment

**Steps:**
1. Navigate to Restaurant POS → Gas Cylinders (enterprise interface)
2. You should see the dashboard with KPI cards showing:
   - Active Cylinders: 0
   - Avg Orders/Day: 0
   - Avg Days Remaining: 0
   - Monthly Refill Cost: $0
3. Create a new cylinder (use legacy interface if needed):
   - Cylinder Type: [Select existing type]
   - Start Date: Today
   - Cost: $2200
   - Notes: "Initial installation"

**Expected Result:** Cylinder created and lifecycle automatically started

---

### 5. Automatic Consumption Tracking

**Objective:** Test automatic cylinder consumption on order completion

**Steps:**
1. Navigate to POS Screen (Restaurant POS → POS Screen)
2. Add products to cart that are mapped to a cylinder type
3. Complete the order (mark as completed)
4. Navigate to Restaurant POS → Gas Cylinders → Consumption tab
5. Verify the consumption log shows:
   - Order number
   - Products sold
   - Cylinder type
   - Quantity
   - Consumption units

**Expected Result:** 
- Consumption automatically recorded for the order
- Cylinder orders_served counter incremented
- Consumption log visible in admin

---

### 6. Dashboard Analytics

**Objective:** Test dashboard KPI cards and analytics

**Steps:**
1. Navigate to Restaurant POS → Gas Cylinders (Dashboard tab should be default)
2. Verify KPI cards display:
   - Active Cylinders count
   - Average burn rate (orders/day)
   - Average days remaining
   - Monthly refill cost
   - Total orders served
3. Check "Active Cylinders Overview" table shows:
   - Cylinder type
   - Zone
   - Start date
   - Orders served
   - Remaining percentage
   - Burn rate
   - Estimated days left
   - Status badge (LOW if < 3 days remaining)
4. Check "Recent Activity" section shows latest consumption logs

**Expected Result:** All metrics calculated and displayed correctly

---

### 7. Lifecycle Tracking

**Objective:** Test lifecycle history and metrics

**Steps:**
1. Navigate to Restaurant POS → Gas Cylinders → Lifecycle tab
2. Verify lifecycle records show:
   - Cylinder type and zone
   - Start date (current date)
   - End date (empty for active)
   - Days active
   - Orders served
   - Average orders per day
   - Refill cost
   - Cost per order
   - Status (active/completed)

**Expected Result:** Active lifecycle shown with real-time metrics

---

### 8. Refill Workflow

**Objective:** Test cylinder refill process

**Steps:**
1. Navigate to Restaurant POS → Gas Cylinders → Refill tab
2. Fill in refill form:
   - Select Cylinder: [Active cylinder]
   - Refill Date: Today
   - Vendor: "ABC Gas Supply"
   - Cost: $2200
   - Quantity: 1
   - Notes: "Monthly refill"
3. Click "Process Refill"
4. Verify:
   - Success message displayed
   - Refill appears in "Refill History" table
   - Lifecycle tab shows old lifecycle as "completed"
   - New lifecycle started with today's date
   - Cylinder orders_served reset to 0
   - Remaining percentage reset to 100%

**Expected Result:** 
- Old lifecycle closed with calculated metrics
- New lifecycle started
- Cylinder counters reset
- Refill logged in history

---

### 9. Analytics & Forecasting

**Objective:** Test performance analytics and forecasting

**Steps:**
1. Navigate to Restaurant POS → Gas Cylinders → Analytics tab
2. Check "Efficiency Comparison" section:
   - Shows all active cylinders
   - Orders per day calculated
   - Efficiency rating assigned (⭐⭐⭐ Excellent if >50/day)
3. Check "Monthly Trends" section:
   - Shows last 6 months
   - Order count per month
   - Active cylinders per month
   - Average orders per cylinder
4. Check "Cost Analysis" section:
   - Shows completed lifecycle data
   - Total refill costs
   - Average cost per order

**Expected Result:** All analytics display with accurate calculations

---

### 10. REST API Testing

**Objective:** Test REST API endpoints

**Steps:**
1. Use a REST client (Postman, cURL, or browser console)
2. Test GET `/wp-json/zaikon/v1/cylinders/consumption`:
   ```bash
   curl -X GET "http://your-site/wp-json/zaikon/v1/cylinders/consumption?limit=10" \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```
3. Test GET `/wp-json/zaikon/v1/cylinders/analytics`:
   ```bash
   curl -X GET "http://your-site/wp-json/zaikon/v1/cylinders/analytics" \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```
4. Test GET `/wp-json/zaikon/v1/cylinders/{id}/forecast`:
   ```bash
   curl -X GET "http://your-site/wp-json/zaikon/v1/cylinders/1/forecast" \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```
5. Test POST `/wp-json/zaikon/v1/cylinders/{id}/refill`:
   ```bash
   curl -X POST "http://your-site/wp-json/zaikon/v1/cylinders/1/refill" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -d '{
       "refill_date": "2026-01-15",
       "vendor": "Test Vendor",
       "cost": 2200,
       "quantity": 1,
       "notes": "API test refill"
     }'
   ```

**Expected Result:** All endpoints return success with expected data structure

---

### 11. POS Integration Non-Impact Test

**Objective:** Verify cylinder tracking doesn't affect POS core functionality

**Steps:**
1. Create multiple orders through POS
2. Verify:
   - Orders process normally
   - Payment processing works
   - Order completion succeeds
   - Kitchen tickets generated (if applicable)
   - Inventory deducted correctly
   - No errors in browser console
   - No PHP errors in error logs
3. Create orders with products NOT mapped to cylinders
4. Verify:
   - Orders still complete successfully
   - No errors related to cylinder tracking

**Expected Result:** 
- POS continues to function normally
- Cylinder tracking is transparent to POS operations
- Orders with non-mapped products work fine

---

### 12. Multi-Cylinder Zone Support

**Objective:** Test multiple cylinders running in parallel

**Steps:**
1. Create cylinder for "Oven" zone
2. Create cylinder for "Counter" zone
3. Map different products to each cylinder type
4. Create orders with mixed products (some from Oven, some from Counter)
5. Verify:
   - Both cylinders track consumption independently
   - Each cylinder's orders_served increments only for its products
   - Dashboard shows both cylinders
   - Burn rates calculated separately

**Expected Result:** Independent tracking for each cylinder/zone

---

### 13. Burn Rate Accuracy Test

**Objective:** Verify burn rate and forecasting calculations

**Steps:**
1. Note current cylinder orders_served and start_date
2. Create 10 test orders over a period (can backdate in database for testing)
3. Navigate to Dashboard
4. Verify burn rate calculation:
   - Orders per day = orders_served / days_since_start
5. Verify remaining days calculation:
   - Based on current remaining_percentage and burn rate

**Expected Result:** Calculations are mathematically correct

---

### 14. Lifecycle Completion Metrics

**Objective:** Test lifecycle completion and metric calculation

**Steps:**
1. Create a cylinder with start date 10 days ago (modify in database)
2. Add consumption records for various dates (modify in database)
3. Process refill to close lifecycle
4. Navigate to Lifecycle tab
5. Verify completed lifecycle shows:
   - Correct total days
   - Correct orders served count
   - Correct average orders per day
   - Correct cost per order

**Expected Result:** All metrics calculated correctly for completed lifecycle

---

### 15. Data Export Verification

**Objective:** Verify data can be exported

**Steps:**
1. Navigate to various report tabs
2. Use browser functionality to:
   - Print to PDF (Ctrl+P → Save as PDF)
   - Copy table data to Excel
   - Use browser developer tools to export JSON from API
3. Verify exported data is complete and formatted correctly

**Expected Result:** Data exportable through standard browser tools

---

## Performance Testing

### Load Test
1. Create 100 consumption records
2. Navigate to Consumption tab
3. Verify page loads within 2 seconds
4. Check database query performance in WordPress Debug Bar or Query Monitor

**Expected Result:** Acceptable performance with large datasets

---

## Security Testing

### Permission Checks
1. Test as different user roles:
   - Admin: Should have full access
   - Manager: Should have access (if rpos_manage_inventory capability)
   - Cashier: Should NOT have access to cylinder management
   - Kitchen Staff: Should NOT have access
2. Try accessing REST API endpoints without authentication
3. Try SQL injection in form fields

**Expected Result:** 
- Proper permission checks enforced
- No unauthorized access
- No SQL injection vulnerabilities

---

## Edge Cases

### Test Cases:
1. **No products mapped:** Create order with unmapped product - should not create cylinder consumption
2. **No active cylinder:** Create order when no cylinder is active - should handle gracefully
3. **Multiple active cylinders of same type:** System should prevent this
4. **Refill with zero cost:** Should accept and calculate cost per order as 0
5. **Negative values:** Form validation should prevent negative costs/quantities
6. **Very old start dates:** Burn rate should calculate correctly even for old cylinders

**Expected Result:** All edge cases handled gracefully without errors

---

## Regression Testing

Verify existing features still work:
1. Product management
2. Category management
3. Inventory management
4. Order creation and management
5. Kitchen display system
6. Reports and analytics (non-cylinder)
7. Delivery management
8. Ingredient tracking

**Expected Result:** No regressions in existing functionality

---

## Final Verification Checklist

- [ ] All database tables created successfully
- [ ] All columns added to existing tables
- [ ] Zone management working
- [ ] Automatic consumption tracking functional
- [ ] Dashboard displays correct metrics
- [ ] Lifecycle tracking and completion working
- [ ] Refill workflow functional
- [ ] Analytics and forecasting accurate
- [ ] REST API endpoints operational
- [ ] No impact on POS core functionality
- [ ] Multi-cylinder support verified
- [ ] Performance acceptable
- [ ] Security checks passed
- [ ] Edge cases handled
- [ ] No regressions detected
- [ ] Documentation complete

---

## Troubleshooting

### Common Issues:

**Issue:** Consumption not being recorded
- **Check:** Verify products are mapped to cylinder types
- **Check:** Verify active cylinder exists for that type
- **Check:** Verify order status is "completed"
- **Check:** Check PHP error logs

**Issue:** Dashboard shows zero metrics
- **Check:** Verify at least one active cylinder exists
- **Check:** Verify consumption records exist in database
- **Check:** Check for JavaScript console errors

**Issue:** Refill fails
- **Check:** Verify cylinder ID is valid
- **Check:** Verify user has rpos_manage_inventory capability
- **Check:** Check PHP error logs for database errors

**Issue:** REST API returns 401 Unauthorized
- **Check:** Verify authentication token is valid
- **Check:** Verify user has required permissions
- **Check:** Check WordPress REST API is enabled

---

## Test Data Cleanup

After testing, clean up test data:
```sql
-- Delete test consumption records
DELETE FROM wp_zaikon_cylinder_consumption WHERE created_at >= 'TEST_START_DATE';

-- Delete test lifecycles
DELETE FROM wp_zaikon_cylinder_lifecycle WHERE created_at >= 'TEST_START_DATE';

-- Delete test refills
DELETE FROM wp_zaikon_cylinder_refill WHERE created_at >= 'TEST_START_DATE';

-- Reset cylinder counters
UPDATE wp_rpos_gas_cylinders SET orders_served = 0, remaining_percentage = 100 WHERE id = TEST_CYLINDER_ID;
```

---

## Success Criteria

Implementation is considered successful when:
1. All test cases pass
2. No critical bugs found
3. Performance is acceptable
4. Security checks pass
5. No regressions in existing features
6. Documentation is complete and accurate
7. Code review approves changes
8. User acceptance testing confirms functionality
