# Gas Cylinders Usage Report - Testing Guide

## Changes Made

### 1. Enhanced Logging in `get_cylinder_usage_report()`

The method now logs comprehensive diagnostic information at each stage:

#### Stage 1: Cylinder Lookup
```
RPOS Gas Cylinders: Generating usage report for cylinder #X (Type: Counter Cylinder)
```

#### Stage 2: Product Mapping
```
RPOS Gas Cylinders: Found X mapped products: [1, 2, 3]
```

#### Stage 3: Date Range
```
RPOS Gas Cylinders: Date range: 2026-01-11 00:00:00 to 2026-01-11 23:59:59
```

#### Stage 4: Order Counts (Debug Queries)
```
RPOS Gas Cylinders: Total orders in date range: 10
RPOS Gas Cylinders: Completed orders in date range: 8
RPOS Gas Cylinders: Completed orders with mapped products: 5
```

#### Stage 5: SQL Query Execution
```
RPOS Gas Cylinders: Executing SQL query: SELECT p.name as product_name, ...
RPOS Gas Cylinders: Query returned 3 product results
```

#### Stage 6: Individual Product Results
```
RPOS Gas Cylinders: Product: Zinger Burger, Qty: 10, Sales: 2800
RPOS Gas Cylinders: Product: Chicken Wings, Qty: 5, Sales: 1500
```

#### Stage 7: Summary
```
RPOS Gas Cylinders: Report generated in 45.23ms. Total sales: 4300
```

### 2. Enhanced Admin UI

The usage report page now shows a **Debug Information** panel (for administrators only) displaying:

- **Date Range:** The exact date range being queried
- **Mapped Products:** Number of products mapped to the cylinder type
- **Total Orders in Range:** All orders created within the date range
- **Completed Orders in Range:** Orders with status = 'completed'
- **Orders with Mapped Products:** Completed orders containing mapped products
- **Product Results Returned:** Number of distinct products in the final report
- **Execution Time:** Query execution time in milliseconds
- **SQL Query:** Expandable section showing the exact SQL query executed

### 3. Empty State Handling

When no sales data is found, the table shows:
```
No sales data found for the selected period.
```

## Testing Instructions

### 1. Check Server Error Logs

After accessing the Usage Report page, check your WordPress debug log (usually `wp-content/debug.log`):

```bash
tail -f wp-content/debug.log | grep "RPOS Gas Cylinders"
```

### 2. Access the Usage Report

1. Navigate to **Restaurant POS → Gas Cylinders**
2. Click the **Usage Report** tab
3. Select a cylinder from the list
4. View the debug information panel (if you're an administrator)

### 3. Verify the Diagnostic Output

The debug panel will show you:

- If mapped products exist
- If orders exist in the date range
- If completed orders exist
- If completed orders contain the mapped products
- The actual SQL query being executed

### 4. Diagnose Issues

#### Scenario A: No Products Mapped
```
Mapped Products: 0
```
**Solution:** Go to the "Product Mapping" tab and map products to the cylinder type.

#### Scenario B: Orders Exist but Not Completed
```
Total Orders in Range: 10
Completed Orders in Range: 0
```
**Solution:** Orders need to be marked as 'completed' status to appear in the report.

#### Scenario C: Completed Orders Exist but Wrong Products
```
Completed Orders in Range: 8
Orders with Mapped Products: 0
```
**Solution:** The orders don't contain products mapped to this cylinder type. Check product mappings.

#### Scenario D: Orders Created Outside Date Range
```
Total Orders in Range: 0
```
**Solution:** Orders are being created with `created_at` timestamps outside the cylinder's date range. Check the cylinder's start_date and end_date.

## Expected Behavior

After creating new POS sales for mapped products and completing the orders:

1. The report should automatically reflect the new sales on next page load
2. Debug logs will show the new order counts
3. The SQL query will be logged for verification
4. Individual product sales will be logged

## No Caching Issues

The implementation:
- ✅ Does NOT use WordPress transients
- ✅ Does NOT use any caching mechanism
- ✅ Queries the database fresh on every page load
- ✅ Uses `created_at` field for date filtering
- ✅ Filters by `status = 'completed'`

## Benefits

1. **Transparency:** See exactly what the query is doing
2. **Debugging:** Identify the exact stage where data is being filtered out
3. **Performance:** Track query execution time
4. **Verification:** Confirm the SQL query matches expectations
