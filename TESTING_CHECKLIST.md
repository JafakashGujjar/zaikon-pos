# Testing Checklist for Ingredients Management System

## Pre-Testing Setup
- [ ] Activate/Reactivate the plugin to ensure database tables are created
- [ ] Verify new tables exist:
  - `wp_rpos_ingredients`
  - `wp_rpos_ingredient_movements`
  - `wp_rpos_product_recipes` has `ingredient_id` column

## 1. Ingredients CRUD Operations

### Create Ingredient
- [ ] Navigate to Restaurant POS → Ingredients
- [ ] Click "Add New Ingredient"
- [ ] Test with valid data:
  - Name: "Chicken Fillet 120g"
  - Unit: pcs
  - Current Stock: 50
  - Cost per Unit: 2.50
- [ ] Verify ingredient appears in list
- [ ] Test with missing required fields (should show error)
- [ ] Test with decimal stock quantity (e.g., 0.500 kg)

### Edit Ingredient
- [ ] Click "Edit" on an existing ingredient
- [ ] Change values (name, unit, stock, cost)
- [ ] Save and verify changes are reflected
- [ ] Test changing stock quantity directly

### Delete Ingredient
- [ ] Try to delete ingredient not used in any recipe
  - Should succeed
- [ ] Create a product with recipe using an ingredient
- [ ] Try to delete that ingredient
  - Should fail with error message

## 2. Inventory → Add Purchase Flow

### Purchase for Existing Ingredient
- [ ] Navigate to Restaurant POS → Inventory
- [ ] Click "Add Purchase"
- [ ] Select an existing ingredient from dropdown
- [ ] Enter:
  - Quantity: 100
  - Unit: (should match ingredient)
  - Cost per Unit: 2.45
  - Supplier: ABC Foods
  - Date: Today's date
- [ ] Submit and verify:
  - Success message appears
  - Ingredient stock increased by 100
  - Movement record created in database

### Purchase with New Ingredient
- [ ] Click "Add Purchase"
- [ ] Select "+ Add New Ingredient"
- [ ] Verify inline form appears
- [ ] Enter:
  - New Ingredient Name: "Burger Bun"
  - Quantity: 200
  - Unit: pcs
  - Cost per Unit: 0.50
  - Supplier: XYZ Bakery
  - Date: Today
- [ ] Submit and verify:
  - New ingredient created
  - Stock set to 200
  - Movement record created

### Edge Cases
- [ ] Try to submit without required fields
- [ ] Try with quantity 0 (should fail)
- [ ] Try with negative quantity (should fail)
- [ ] Try with very small decimal (0.001)
- [ ] Try with very large quantity (10000)

## 3. Products → Recipe Management

### Add Recipe to Product
- [ ] Navigate to Restaurant POS → Products
- [ ] Create or edit a product (e.g., "Zinger Burger")
- [ ] In Recipe section, click "Add Ingredient"
- [ ] Add multiple ingredients:
  - Chicken Fillet 120g: 1 pcs
  - Burger Bun: 1 pcs
  - Lettuce: 2 pcs
  - Special Sauce: 0.030 kg
- [ ] Verify unit is auto-filled for each
- [ ] Verify cost per unit is displayed
- [ ] Verify ingredient cost is calculated
- [ ] Save product
- [ ] Reload page and verify recipe is saved

### Edit Recipe
- [ ] Edit the product
- [ ] Change quantities in recipe
- [ ] Remove an ingredient
- [ ] Add a new ingredient
- [ ] Save and verify changes

### Edge Cases
- [ ] Try to save recipe with 0 quantity (should be ignored)
- [ ] Try with decimal quantities (0.001, 0.5, 1.250)
- [ ] Try removing all ingredients (product should still save)
- [ ] Try adding same ingredient twice (should work)

## 4. POS Sale → Ingredient Deduction

### Standard Sale
- [ ] Create a product with recipe (if not already done)
- [ ] Navigate to Restaurant POS → POS Screen
- [ ] Add product to cart (e.g., 5 Zinger Burgers)
- [ ] Complete the order
- [ ] Verify:
  - Order status is "completed"
  - Check ingredients table - stock decreased correctly:
    * Chicken Fillet: -5 pcs
    * Burger Bun: -5 pcs
    * Lettuce: -10 pcs
    * Special Sauce: -0.150 kg
  - Movement records created with:
    * Type: "Consumption"
    * Reference: order_id
    * Negative quantities

### Multiple Products
- [ ] Create order with multiple different products that have recipes
- [ ] Complete order
- [ ] Verify all ingredients deducted correctly

### No Double Deduction
- [ ] Check `ingredients_deducted` flag is set to 1 on order
- [ ] Manually try to change order status again
- [ ] Verify ingredients are NOT deducted again

### Product Without Recipe
- [ ] Add product that has NO recipe to cart
- [ ] Complete order
- [ ] Verify no errors occur
- [ ] Verify order completes successfully

### Mixed Order
- [ ] Create order with:
  - Products with recipes
  - Products without recipes
- [ ] Complete order
- [ ] Verify only recipe products trigger deductions

## 5. Ingredients Usage Report

### Basic Report
- [ ] Navigate to Restaurant POS → Ingredients → Usage Report
- [ ] Default dates should be today
- [ ] Verify report displays all ingredients
- [ ] Check columns:
  - Ingredient Name
  - Unit
  - Purchased (in date range)
  - Consumed (in date range)
  - Current Balance

### Date Filtering
- [ ] Set From Date: 7 days ago
- [ ] Set To Date: today
- [ ] Click Filter
- [ ] Verify calculations are correct:
  - Sum of Purchase movements in date range
  - Sum of Consumption movements in date range
  - Current balance matches ingredient table

### Edge Cases
- [ ] Try invalid date format (should default to today)
- [ ] Try date with only From Date set
- [ ] Try date with only To Date set
- [ ] Try future dates
- [ ] Try very old dates (before any data)

### Low Stock Indicator
- [ ] Manually set an ingredient to < 10 stock
- [ ] View report
- [ ] Verify "Low Stock" indicator appears

## 6. Backward Compatibility

### Existing Recipes
If you have existing recipes using the old inventory_item_id system:
- [ ] View product with old recipe
- [ ] Verify recipe displays (even if not using new ingredient system)
- [ ] Edit and save product
- [ ] Verify recipe still works

### Migration
- [ ] Update old recipe to use new ingredients
- [ ] Save and verify deduction works with new system

## 7. Data Integrity

### Stock Accuracy
- [ ] Record initial stock for an ingredient
- [ ] Make 3 purchases (note quantities)
- [ ] Make 2 sales with products using that ingredient
- [ ] Calculate expected stock:
  - Initial + Sum(purchases) - Sum(consumption)
- [ ] Check ingredients table
- [ ] Check usage report
- [ ] Verify all match expected value

### Movement History
- [ ] For any ingredient, check movement records
- [ ] Verify:
  - All purchases logged with positive quantity
  - All consumption logged with negative quantity
  - Timestamps are correct
  - User IDs are correct
  - Order references are correct

## 8. Performance

### Large Data
- [ ] Create 50+ ingredients
- [ ] Verify list page loads quickly
- [ ] Verify dropdowns populate quickly
- [ ] Create 100+ movement records
- [ ] Verify report generates quickly

## 9. UI/UX

### Navigation
- [ ] Verify "Ingredients" menu item appears correctly
- [ ] Verify "Usage Report" submenu works
- [ ] Test back buttons work
- [ ] Test breadcrumbs if present

### Forms
- [ ] All labels are clear
- [ ] Required fields marked with *
- [ ] Validation messages are helpful
- [ ] Success messages appear
- [ ] Error messages are descriptive

### Tables
- [ ] Column headers are clear
- [ ] Data is properly formatted
- [ ] Numbers align right
- [ ] Actions buttons are visible

## 10. Security

### Permissions
- [ ] Login as user without `rpos_manage_inventory` capability
- [ ] Verify cannot access Ingredients pages
- [ ] Login as admin
- [ ] Verify full access

### Input Validation
- [ ] Try SQL injection in ingredient name
- [ ] Try XSS in ingredient name
- [ ] Try very long strings
- [ ] Try special characters
- [ ] Verify all are sanitized/escaped

### Nonce Verification
- [ ] Use browser dev tools to submit form without nonce
- [ ] Verify rejected with security error

## Test Results

### Summary
- Total Tests: ___
- Passed: ___
- Failed: ___
- Blocked: ___

### Issues Found
1. [Issue Description]
   - Severity: High/Medium/Low
   - Steps to Reproduce:
   - Expected:
   - Actual:

### Notes
- 
- 
- 

## Sign-off

Tested by: _______________
Date: _______________
Version: _______________
Status: ☐ Pass ☐ Fail ☐ Conditional Pass
