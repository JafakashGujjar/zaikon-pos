# Ingredients Module Upgrade - Testing Guide

## Pre-Testing Setup

Before testing, ensure:
1. You have a working WordPress installation with the Restaurant POS plugin active
2. You have admin access to the WordPress backend
3. The database has been updated (plugin reactivation may be needed)

---

## Test 1: Verify New Database Schema

### Steps:
1. Access phpMyAdmin or database console
2. Check `wp_rpos_ingredients` table structure
3. Verify new columns exist:
   - `supplier_phone` (VARCHAR 50)
   - `supplier_location` (TEXT)
   - `reorder_level` (DECIMAL 10,3)

4. Check if `wp_rpos_ingredient_waste` table exists
5. Verify waste table has columns: id, ingredient_id, quantity, reason, notes, user_id, created_at

**Expected Result:** ✅ All new columns and table exist

---

## Test 2: Create Ingredient with New Fields

### Steps:
1. Navigate to Restaurant POS > Ingredients
2. Click "Add New Ingredient"
3. You should see a selector screen first
4. Click "+ Create New Ingredient" button
5. Fill in the form with:
   - **Name:** Test Tomatoes
   - **Unit:** kg
   - **Current Stock:** 50
   - **Cost per Unit:** 2.50
   - **Reorder Level:** 10
   - **Purchasing Date:** Today's date
   - **Expiry Date:** 7 days from today
   - **Supplier Name:** Fresh Foods Inc
   - **Supplier Phone:** +1234567890
   - **Supplier Location:** 123 Market Street, City
   - **Supplier Rating:** 4 Stars

6. Click "Add Ingredient"

**Expected Result:** ✅ Ingredient created successfully with all fields saved

---

## Test 3: Verify Summary Cards

### Steps:
1. Navigate to Restaurant POS > Ingredients (list view)
2. Check the summary cards at the top:
   - Total Ingredients (should show count)
   - Total Inventory Value (should calculate value)
   - Low Stock Items (should be 0 or show count)
   - Expired Items (should be 0)
   - Expiring Soon (should show 1 if Test Tomatoes expiry is within 7 days)

**Expected Result:** ✅ All cards display correct values

---

## Test 4: Verify Color Indicators

### Steps:
1. In ingredients list, find "Test Tomatoes"
2. Row should have orange/yellow background (expiring soon)
3. Status text should show "⏰ Expiring Soon"

### Create Low Stock Item:
1. Add another ingredient with:
   - Name: Test Flour
   - Stock: 5
   - Reorder Level: 10
2. This row should have orange background
3. Status text should show "📦 Low Stock"

### Create Expired Item:
1. Add another ingredient with expiry date in the past
2. This row should have red background
3. Status text should show "⚠️ Expired"

**Expected Result:** ✅ Color indicators work correctly

---

## Test 5: Test Stock Dashboard

### Steps:
1. Navigate to Restaurant POS > Ingredients > Stock Dashboard
2. Verify three sections appear:
   - 🗓️ Ingredients Near Expiry
   - 📦 Low Stock Alerts
   - 🚀 Fast-Moving Ingredients

3. Check "Ingredients Near Expiry" section:
   - Should show "Test Tomatoes" if expiry is within 7 days
   - Badge should be orange or red depending on days remaining
   - Days until expiry should be calculated correctly

4. Check "Low Stock Alerts" section:
   - Should show "Test Flour" (stock 5, reorder level 10)
   - Shortage amount should show 5
   - Supplier phone should display

5. Fast-Moving section may be empty (requires consumption history)

**Expected Result:** ✅ Dashboard displays correct data in all sections

---

## Test 6: Test Waste Logging

### Steps:
1. Navigate to Restaurant POS > Ingredients > Waste / Spoilage
2. Fill in waste form:
   - **Ingredient:** Test Tomatoes
   - **Quantity:** 5
   - **Reason:** Spoiled
   - **Notes:** Found mold on batch
3. Click "Log Waste"

4. Check waste history table:
   - Should show new entry with details
   - Reason badge should be color-coded

5. Return to ingredients list:
   - Test Tomatoes stock should be reduced by 5 (from 50 to 45)

6. Check ingredient movements:
   - Navigate to ingredient edit page or movements
   - Should see a "Waste" movement entry for -5 quantity

**Expected Result:** ✅ Waste logged and stock automatically deducted

---

## Test 7: Test Duplicate Prevention Workflow

### Steps:
1. Navigate to Restaurant POS > Ingredients
2. Click "Add New Ingredient"
3. In the selector, type "Test" in the dropdown
4. Should see existing "Test Tomatoes", "Test Flour" in dropdown
5. Select "Test Tomatoes" from dropdown
6. Should redirect to edit page for Test Tomatoes

### Create New:
1. Click "Add New Ingredient" again
2. Click "+ Create New Ingredient" button
3. Form should appear
4. Click "← Back to Selector" button
5. Should return to selector screen

**Expected Result:** ✅ Workflow prevents duplicates and allows navigation

---

## Test 8: Test Form Sections and UI

### Steps:
1. Navigate to edit any ingredient
2. Verify form is divided into sections:
   - 📝 Basic Information (Name, Unit, Stock, Cost, Reorder Level)
   - 📅 Dates (Purchasing Date, Expiry Date)
   - 🏪 Supplier Details (Name, Phone, Location, Rating)

3. Check that:
   - Sections have card-style background
   - Headers have icons and borders
   - Fields are grouped logically
   - Form is easy to read and navigate

**Expected Result:** ✅ Form is well-organized and visually appealing

---

## Test 9: CRITICAL - Verify Existing Sales Deduction

This is the most important test to ensure backward compatibility.

### Setup:
1. Create a product (e.g., "Tomato Soup")
2. Add a recipe for the product:
   - Product: Tomato Soup
   - Ingredient: Test Tomatoes
   - Quantity Required: 0.5 kg

### Test:
1. Note current stock of Test Tomatoes (should be 45 from previous tests)
2. Navigate to POS Screen
3. Create an order with 2 units of Tomato Soup
4. Complete the order

### Verify:
1. Check Test Tomatoes stock:
   - Should be reduced by 1 kg (2 units × 0.5 kg = 1 kg)
   - New stock should be 44 kg

2. Check ingredient movements:
   - Should have a "Consumption" entry for -1 kg
   - Reference should be the order ID

**Expected Result:** ✅ Ingredient stock correctly deducted during sale

---

## Test 10: Edit Existing Ingredient

### Steps:
1. Navigate to ingredients list
2. Click "Edit" on Test Tomatoes
3. Update fields:
   - Supplier Phone: Change to different number
   - Supplier Location: Update address
   - Reorder Level: Change to 15
4. Click "Update Ingredient"
5. Verify changes are saved
6. Check list view shows updated information

**Expected Result:** ✅ All fields update correctly

---

## Test 11: Icons Display Test

### Steps:
1. Navigate to ingredients list
2. Verify icons appear for:
   - 📦 next to stock quantity
   - 💰 next to cost per unit
   - 📞 next to supplier phone
   - 📍 next to supplier location
   - ⭐ with supplier rating
   - 📅 next to expiry date

**Expected Result:** ✅ All icons display correctly

---

## Test 12: Mobile Responsiveness (Optional)

### Steps:
1. Access ingredients pages on mobile device or resize browser
2. Check:
   - Summary cards stack vertically
   - Tables are scrollable
   - Form sections remain readable
   - Buttons are accessible

**Expected Result:** ✅ UI adapts to smaller screens

---

## Test 13: Permissions Test (Optional)

### Steps:
1. Create a user with limited permissions
2. Test access to:
   - Ingredients list (should require rpos_manage_inventory)
   - Waste logging (should require rpos_manage_inventory)
   - Stock Dashboard (should require rpos_manage_inventory)

**Expected Result:** ✅ Only authorized users can access pages

---

## Test 14: Data Integrity Test

### Steps:
1. Try to delete Test Tomatoes (which is used in Tomato Soup recipe)
2. Should show error: "Failed to delete ingredient. It may be used in product recipes."

3. Remove ingredient from recipe first
4. Try deleting again
5. Should delete successfully

**Expected Result:** ✅ Cannot delete ingredients used in recipes

---

## Known Limitations

1. **Fast-Moving Ingredients:** Requires 30 days of consumption data to show meaningful results
2. **Summary Cards:** Values recalculate on page load (no caching)
3. **Searchable Dropdown:** Uses basic HTML select (could be enhanced with Select2 in future)

---

## Troubleshooting

### Issue: New columns don't appear
**Solution:** Deactivate and reactivate the plugin to trigger database migrations

### Issue: Waste logging doesn't deduct stock
**Solution:** Check user permissions and verify ingredient exists

### Issue: Dashboard shows wrong data
**Solution:** Verify database queries are returning results, check date calculations

### Issue: Color indicators not working
**Solution:** Clear browser cache, check CSS is loading

---

## Security Checks Completed ✅

- All input is sanitized (sanitize_text_field, sanitize_textarea_field)
- All database queries use prepared statements
- Nonce verification on all form submissions
- Permission checks on all admin pages
- No SQL injection vulnerabilities
- No XSS vulnerabilities

---

## Performance Notes

- Dashboard queries are optimized with indexes on:
  - ingredient_id
  - created_at
  - expiry_date
  - reorder_level

- All queries use appropriate JOINs and WHERE clauses
- Pagination available where needed

---

## Conclusion

Complete all tests above to verify the implementation is working correctly. All tests should pass with ✅ for a successful deployment.

Report any issues found during testing for immediate resolution.
