# Gas Cylinders Usage Report - Testing Guide

## Changes Made

### 1. Enhanced Logging in `get_cylinder_usage_report()`

The method now logs comprehensive diagnostic information at each stage with **performance-based logging** to reduce noise.

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

#### Stage 4: Order Counts (Debug Queries - WP_DEBUG Only)
```
RPOS Gas Cylinders: Total orders in date range: 10
RPOS Gas Cylinders: Completed orders in date range: 8
RPOS Gas Cylinders: Completed orders with mapped products: 5
```
**Note:** These debug queries only run when `WP_DEBUG` is enabled to avoid production overhead.

#### Stage 5: SQL Query Execution
```
RPOS Gas Cylinders: Executing query for 3 products in date range
RPOS Gas Cylinders: Query returned 3 product results
```

#### Stage 6: Individual Product Results (Aggregated)
```
RPOS Gas Cylinders: Found 3 distinct products with total sales: 4,300.00
```

#### Stage 7: Performance Summary
```
RPOS Gas Cylinders: Report generated in 145.23ms
```
OR for slow queries (>1 second):
```
RPOS Gas Cylinders: WARNING - Report generated in 1245.50ms (slow query)
```

**Note:** Queries under 100ms are considered fast and not logged to reduce log noise.

### 2. Enhanced Admin UI

The usage report page now shows a **Debug Information** panel (for administrators only) displaying:

- **Date Range:** The exact date range being queried
- **Mapped Products:** Number of products mapped to the cylinder type
- **Total Orders in Range:** All orders created within the date range
- **Completed Orders in Range:** Orders with status = 'completed'
- **Orders with Mapped Products:** Completed orders containing mapped products
- **Product Results Returned:** Number of distinct products in the final report
- **Execution Time:** Query execution time in milliseconds

**Security:** SQL queries are NOT displayed to prevent database structure exposure.

### 3. Empty State Handling

When no sales data is found, the table shows:
```
No sales data found for the selected period.
```

## Security Features

- ✅ All logged data is sanitized (IDs, names, values)
- ✅ SQL queries never exposed in logs or UI
- ✅ Debug panel only visible to administrators
- ✅ Business data aggregated, not individual records
- ✅ Log injection attacks prevented

## Testing Instructions

### 0. Enable Debug Mode (for full diagnostics)

To enable comprehensive debug queries, add to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**In production:** Leave WP_DEBUG disabled to avoid debug query overhead.

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
- Performance metrics

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

#### Scenario E: Performance Issues
```
WARNING - Report generated in 1245.50ms (slow query)
```
**Solution:** Query is slow. Check database indexes or the number of orders/products.

## Expected Behavior

After creating new POS sales for mapped products and completing the orders:

1. The report should automatically reflect the new sales on next page load
2. Debug logs will show the new order counts
3. Performance metrics will indicate query speed
4. Individual product sales will be aggregated in logs

## No Caching Issues

The implementation:
- ✅ Does NOT use WordPress transients
- ✅ Does NOT use any caching mechanism
- ✅ Queries the database fresh on every page load
- ✅ Uses `created_at` field for date filtering
- ✅ Filters by `status = 'completed'`

## Performance Optimization

- ✅ Debug queries **only run when WP_DEBUG is enabled**
- ✅ In production (WP_DEBUG off): Zero debug query overhead
- ✅ Basic logging always available
- ✅ Execution time tracking always enabled
- ✅ Smart logging (only logs >100ms queries)

## Benefits

1. **Transparency:** See exactly what the query is doing
2. **Debugging:** Identify the exact stage where data is being filtered out
3. **Performance:** Track query execution time with warnings for slow queries
4. **Security:** All data sanitized, no SQL exposure
5. **Log Efficiency:** Only logs meaningful information (slow queries, errors)

