# Ingredients Management System - Implementation Summary

## Overview

This document describes the implementation of a dedicated Ingredients Management System for the Restaurant POS WordPress plugin. The system provides a clean, reliable way to track ingredient stock and automatically deduct ingredients when products are sold through the POS.

## Key Features Implemented

### 1. New Database Tables

#### `wp_rpos_ingredients`
- **Purpose**: Central table for all ingredients
- **Fields**:
  - `id`: Primary key
  - `name`: Ingredient name (e.g., "Chicken Fillet", "Burger Bun")
  - `unit`: Unit of measurement (pcs, kg, g, l, ml)
  - `current_stock_quantity`: Current available stock (DECIMAL 10,3)
  - `cost_per_unit`: Average cost per unit (DECIMAL 10,2)
  - `created_at`, `updated_at`: Timestamps

#### `wp_rpos_ingredient_movements`
- **Purpose**: Track all ingredient stock movements
- **Fields**:
  - `id`: Primary key
  - `ingredient_id`: FK to ingredients table
  - `change_amount`: Quantity changed (positive for additions, negative for deductions)
  - `movement_type`: Type of movement (Purchase, Consumption, Sale, Adjustment)
  - `reference_id`: Optional reference to order_id or other entities
  - `notes`: Description of the movement
  - `user_id`: User who made the change
  - `created_at`: Timestamp

#### Updated: `wp_rpos_product_recipes`
- **Added**: `ingredient_id` column to link directly to ingredients table
- **Maintains**: `inventory_item_id` for backward compatibility
- The system now prefers `ingredient_id` over `inventory_item_id`

### 2. New PHP Class: RPOS_Ingredients

Located in: `/includes/class-rpos-ingredients.php`

**Key Methods**:
- `get_all()` - Get all ingredients with sorting
- `get($id)` - Get single ingredient by ID
- `create($data)` - Create new ingredient
- `update($id, $data)` - Update ingredient
- `delete($id)` - Delete ingredient (checks if used in recipes first)
- `adjust_stock()` - Adjust ingredient stock and log movement
- `get_movements()` - Get movement history with filters
- `get_usage_report()` - Generate usage report for date range

### 3. Admin Pages

#### A. Ingredients List (`/wp-admin/admin.php?page=restaurant-pos-ingredients`)
- Shows all ingredients in a table
- Displays: Name, Unit, Current Stock, Cost per Unit
- Actions: Edit, Delete
- "Add New Ingredient" button

#### B. Add/Edit Ingredient Form
- Fields:
  - Ingredient Name (required)
  - Unit (required): pcs, kg, g, l, ml
  - Current Stock Quantity (allows manual entry)
  - Cost per Unit (optional)
- Validation on both client and server side

#### C. Ingredients Usage Report (`/wp-admin/admin.php?page=restaurant-pos-ingredients-report`)
- Date range filter (From/To)
- Shows for each ingredient:
  - Total Purchased in date range
  - Total Consumed in date range
  - Current Balance
- Highlights low stock items

### 4. Updated: Inventory → Add Purchase

**Location**: `/includes/admin/inventory.php`

**Changes**:
- "Inventory Item" dropdown now lists **Ingredients** from the ingredients table
- "+ Add New Ingredient" option opens inline form
- When a purchase is recorded:
  - Increases `current_stock_quantity` in ingredients table
  - Creates movement record with type "Purchase"
  - Updates cost_per_unit if provided
  
**Form Fields**:
- Inventory Item (ingredient dropdown)
- New Ingredient Name (shown when "+ Add New" selected)
- Quantity Purchased (supports decimals: 0.001 precision)
- Unit (dropdown)
- Cost per Unit (optional)
- Supplier Name (required)
- Date Purchased

### 5. Updated: Products → Recipe Section

**Location**: `/includes/admin/products.php`

**Changes**:
- Ingredient dropdown now pulls from **ingredients table**
- Each ingredient shows: "Name (unit)" format
- Supports both new (ingredient_id) and old (inventory_item_id) recipes for backward compatibility
- Unit is read-only and automatically filled from ingredient data

**Recipe Form**:
- Add/Remove ingredient rows dynamically
- Each row:
  - Ingredient (dropdown from ingredients table)
  - Quantity Required (decimal: supports 0.001)
  - Unit (auto-filled, read-only)
  - Cost per Unit (auto-filled from ingredient)
  - Ingredient Cost (calculated: qty × cost)

### 6. Updated: Recipe Deduction Logic

**Location**: `/includes/class-rpos-recipes.php`

**Changes**:

```php
deduct_ingredients_for_order($order_id, $order_items) {
    foreach (order_items) {
        Get product recipe
        foreach (ingredient in recipe) {
            calculate: deduct_qty = sold_qty × quantity_required
            RPOS_Ingredients::adjust_stock(
                ingredient_id,
                -deduct_qty,
                'Consumption',
                order_id,
                notes
            )
        }
    }
}
```

**When Triggered**:
- When order status changes to "completed" in Orders class
- Checks `ingredients_deducted` flag to prevent double deduction
- Automatically called from POS when order is completed

### 7. Admin Menu Updates

**New Menu Structure**:
```
Restaurant POS
├── Dashboard
├── POS Screen
├── Kitchen Display
├── Products
├── Categories
├── Inventory
├── Ingredients                 ← NEW
│   └── Usage Report           ← NEW SUBMENU
├── Orders
├── Reports
└── Settings
```

## Data Flow

### Purchase Flow
```
1. Admin clicks "Add Purchase" on Inventory page
2. Selects ingredient OR creates new one
3. Enters quantity, unit, cost, supplier, date
4. Submit →
   - RPOS_Ingredients::adjust_stock(ingredient_id, +quantity)
   - Creates movement record (type: Purchase)
   - Updates cost_per_unit if provided
5. Stock increases in ingredients table
```

### Sale Flow (POS → Ingredient Deduction)
```
1. Cashier completes order on POS
2. Order status set to "completed"
3. RPOS_Orders::create() or update_status() called
4. Check: if ingredients_deducted = 0
5. For each product in order:
   - Get recipe (product_id → ingredients list)
   - For each ingredient:
     * Calculate: qty_to_deduct = product_qty × ingredient_qty
     * RPOS_Ingredients::adjust_stock(ingredient_id, -qty_to_deduct)
     * Create movement (type: Consumption, reference: order_id)
6. Mark order: ingredients_deducted = 1
7. Ingredient stock decreases in ingredients table
```

### Usage Report Flow
```
1. Admin visits "Ingredients Usage Report"
2. Selects date range (default: today)
3. Query:
   - SUM(movements WHERE type='Purchase') = Total Purchased
   - SUM(movements WHERE type='Consumption') = Total Consumed
   - current_stock_quantity = Current Balance
4. Display table with all ingredients
```

## Backward Compatibility

The system maintains backward compatibility with existing data:

1. **Recipes Table**: Both `ingredient_id` and `inventory_item_id` columns exist
2. **Recipe Display**: When loading existing recipes, checks for `ingredient_id` first, falls back to `inventory_item_id`
3. **No Data Loss**: Existing recipes continue to work (though they won't deduct from new ingredients table until updated)

## Security Features

1. **Nonce Verification**: All forms use WordPress nonces
2. **Capability Checks**: Uses `rpos_manage_inventory` capability
3. **Input Sanitization**: All inputs sanitized with WordPress functions
4. **SQL Injection Prevention**: All queries use `$wpdb->prepare()`
5. **XSS Prevention**: All outputs escaped with `esc_html()`, `esc_attr()`, etc.

## File Changes Summary

### New Files (3)
1. `/includes/class-rpos-ingredients.php` - Ingredients management class
2. `/includes/admin/ingredients.php` - Ingredients list/edit page
3. `/includes/admin/ingredients-report.php` - Usage report page

### Modified Files (6)
1. `/restaurant-pos.php` - Added ingredients class include
2. `/includes/class-rpos-install.php` - Added new tables, migration logic
3. `/includes/class-rpos-admin-menu.php` - Added ingredients menu items
4. `/includes/admin/inventory.php` - Updated purchase handling to use ingredients
5. `/includes/admin/products.php` - Updated recipe UI to use ingredients
6. `/includes/class-rpos-recipes.php` - Updated deduction logic for ingredients

## Usage Examples

### Example 1: Add New Ingredient
```
1. Go to: Restaurant POS → Ingredients
2. Click "Add New Ingredient"
3. Enter:
   - Name: Chicken Fillet 120g
   - Unit: pcs
   - Current Stock: 50
   - Cost per Unit: 2.50
4. Click "Add Ingredient"
Result: New ingredient created with 50 pcs in stock
```

### Example 2: Record Purchase
```
1. Go to: Restaurant POS → Inventory
2. Click "Add Purchase"
3. Select ingredient: Chicken Fillet 120g (pcs)
4. Enter:
   - Quantity: 100
   - Cost per Unit: 2.45
   - Supplier: ABC Foods
   - Date: 2026-01-09
5. Click "Record Purchase"
Result:
- Stock increases from 50 to 150 pcs
- Movement record: +100 pcs, Purchase, "Purchase from ABC Foods on 2026-01-09"
- Cost updated to 2.45
```

### Example 3: Create Product with Recipe
```
1. Go to: Restaurant POS → Products
2. Add/Edit product: "Zinger Burger"
3. In Recipe section, add ingredients:
   - Chicken Fillet 120g: 1 pcs
   - Burger Bun: 1 pcs
   - Lettuce: 2 pcs
   - Special Sauce: 30 g (0.030 kg)
4. Save product
Result: Recipe saved linking to ingredients
```

### Example 4: Sell Product (Auto Deduction)
```
1. Go to: Restaurant POS → POS Screen
2. Add "Zinger Burger" × 5 to cart
3. Complete order
Result:
- Order created with status "completed"
- Deductions:
  * Chicken Fillet: -5 pcs
  * Burger Bun: -5 pcs
  * Lettuce: -10 pcs
  * Special Sauce: -0.150 kg
- Movement records created for each with order reference
- ingredients_deducted flag set on order
```

### Example 5: View Usage Report
```
1. Go to: Restaurant POS → Ingredients → Usage Report
2. Select: From 2026-01-01 to 2026-01-09
3. View table showing:
   | Ingredient          | Unit | Purchased | Consumed | Balance |
   |---------------------|------|-----------|----------|---------|
   | Chicken Fillet 120g | pcs  | 100       | 45       | 105     |
   | Burger Bun          | pcs  | 200       | 87       | 163     |
   | Special Sauce       | kg   | 5.000     | 1.350    | 3.650   |
```

## Testing Checklist

- [x] Syntax check all PHP files
- [ ] Test ingredient CRUD operations
  - [ ] Create ingredient
  - [ ] Edit ingredient
  - [ ] Delete ingredient (verify recipe check)
- [ ] Test purchase flow
  - [ ] Add purchase for existing ingredient
  - [ ] Add purchase with new ingredient
  - [ ] Verify stock increase
  - [ ] Verify movement record
- [ ] Test product recipe
  - [ ] Add ingredient to product recipe
  - [ ] Edit ingredient in recipe
  - [ ] Remove ingredient from recipe
  - [ ] Save and verify
- [ ] Test POS sale deduction
  - [ ] Create order with product that has recipe
  - [ ] Complete order
  - [ ] Verify ingredients deducted
  - [ ] Verify movement records
  - [ ] Verify no double deduction
- [ ] Test usage report
  - [ ] View report with date filter
  - [ ] Verify calculations
  - [ ] Check low stock indicators

## Migration Notes

For existing installations:

1. **Database Update**: Reactivate plugin to run migrations
2. **New Tables Created**: ingredients, ingredient_movements
3. **Recipes Table Updated**: ingredient_id column added
4. **Existing Recipes**: Continue to work but won't use new system until updated
5. **No Data Loss**: All existing data preserved

## Future Enhancements (Not in Scope)

- Bulk import ingredients
- Ingredient expiry tracking
- Multiple supplier management per ingredient
- Cost averaging options
- Ingredient transfers between locations
- Recipe versioning
- Waste tracking

## Conclusion

The Ingredients Management System provides a clean, dedicated solution for tracking ingredients as the single source of truth. It simplifies the flow from purchases through to POS sales, with automatic deduction and comprehensive reporting. The system is designed for reliability, ease of use, and scalability.
