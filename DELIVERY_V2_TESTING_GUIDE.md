# Delivery v2 Manual Testing Guide

## Prerequisites
1. Ensure the plugin is activated
2. Have at least one delivery location configured in Zaikon Delivery Management
3. Have at least one delivery charge slab configured
4. Optionally configure a free delivery rule

## Test Scenarios

### Scenario 1: Basic Delivery Order (Slab-based Charge)
**Objective**: Test that delivery charges are calculated correctly based on distance slabs

**Steps**:
1. Go to POS Screen (Restaurant POS → POS Screen)
2. Add at least one product to the cart
3. Note the subtotal amount
4. Click on "Delivery" order type
5. In the Delivery Details popup:
   - Select a delivery area/location from the dropdown
   - Enter customer name: "John Doe"
   - Enter customer phone: "1234567890"
   - Optionally add special instructions
6. Verify that delivery charge is calculated and displayed
7. Click "Confirm Delivery"
8. Verify that:
   - The billing panel shows the delivery charge
   - Grand total = Subtotal + Delivery Charge - Discount
9. Enter cash received and complete the order
10. Verify receipt shows:
    - Delivery details (customer name, phone, location)
    - Delivery charge line item
    - Correct grand total

**Expected Results**:
- Delivery charge calculated based on configured slabs
- Order saved to `zaikon_orders` table
- Delivery record created in `zaikon_deliveries` table
- Receipt displays all delivery information correctly

---

### Scenario 2: Free Delivery Order
**Objective**: Test that free delivery rule is applied correctly

**Prerequisites**:
- Configure a free delivery rule (e.g., orders > $50 within 5km are free)

**Steps**:
1. Go to POS Screen
2. Add products totaling more than the free delivery threshold
3. Note the subtotal amount
4. Click on "Delivery" order type
5. In the Delivery Details popup:
   - Select a location within the free delivery distance
   - Enter customer details
6. Verify that:
   - Delivery charge shows as Rs 0.00
   - "FREE" badge is displayed
7. Complete the order
8. Verify receipt shows:
   - Delivery charge: Rs 0.00 (FREE)
   - Correct grand total (subtotal - discount, no delivery charge)

**Expected Results**:
- Free delivery rule applied correctly
- `is_free_delivery` = 1 in database
- Receipt shows FREE indicator

---

### Scenario 3: Delivery Customers Analytics
**Objective**: Verify that the Delivery Customers dashboard shows correct analytics

**Steps**:
1. Complete at least 2-3 delivery orders for the same customer phone number
2. Complete at least 1-2 delivery orders for a different customer
3. Go to Restaurant POS → Delivery Customers
4. Set date range to include the test orders
5. Verify the dashboard shows:
   - Total customers count
   - Total deliveries count
   - Total revenue
6. Verify each customer row shows:
   - Customer phone
   - Customer name (latest)
   - Primary location (most used)
   - Number of deliveries
   - First and last order dates
   - Total delivery charges
   - Total order amount
   - Average order amount

**Test Filters**:
1. Filter by date range - verify results update correctly
2. Set minimum deliveries (e.g., 2) - verify only customers with >= 2 orders show
3. Sort by "Total Deliveries" - verify order is correct
4. Sort by "Total Amount" - verify order is correct

**Expected Results**:
- Analytics calculated correctly from database
- Filters work as expected
- Sorting works correctly
- SQL grouping by customer_phone works properly

---

### Scenario 4: Legacy Pages Deprecation
**Objective**: Verify that old delivery pages show deprecation notices

**Steps**:
1. Go to Restaurant POS → Delivery Settings
   - Verify deprecation notice is shown
   - Verify "Go to Zaikon Delivery Management" link works
2. Go to Restaurant POS → Daily Rider Log
   - Verify deprecation notice is shown
   - Verify redirect link works
3. Go to Restaurant POS → Delivery Reports
   - Verify deprecation notice is shown
   - Verify links to new pages work

**Expected Results**:
- All legacy pages show clear deprecation warnings
- Users are directed to new Zaikon system
- No errors or functionality loss

---

### Scenario 5: Order Atomicity (Error Handling)
**Objective**: Verify that delivery orders are created atomically

**Steps**:
1. Create a delivery order with valid data
2. Verify both records exist:
   - Check `zaikon_orders` table for order record
   - Check `zaikon_deliveries` table for delivery record
3. Verify both records have matching:
   - `delivery_charges_rs` values
   - Related IDs (delivery.order_id = order.id)

**Expected Results**:
- Both tables updated successfully
- Transaction commits only if both inserts succeed
- Data consistency maintained

---

## Database Verification Queries

### Check Order and Delivery Data
```sql
SELECT 
    o.id, 
    o.order_number, 
    o.order_type,
    o.items_subtotal_rs,
    o.delivery_charges_rs,
    o.grand_total_rs,
    d.customer_name,
    d.customer_phone,
    d.location_name,
    d.distance_km,
    d.is_free_delivery
FROM wp_zaikon_orders o
LEFT JOIN wp_zaikon_deliveries d ON o.id = d.order_id
WHERE o.order_type = 'delivery'
ORDER BY o.created_at DESC
LIMIT 10;
```

### Check Delivery Customer Analytics
```sql
SELECT 
    customer_phone,
    COUNT(*) as deliveries_count,
    SUM(delivery_charges_rs) as total_delivery_charges,
    SUM(o.grand_total_rs) as total_spent
FROM wp_zaikon_deliveries d
INNER JOIN wp_zaikon_orders o ON d.order_id = o.id
GROUP BY customer_phone
ORDER BY deliveries_count DESC;
```

---

## Known Issues / Limitations
- Legacy delivery tables (`rpos_orders`, `rpos_delivery_areas`, etc.) are still present but deprecated
- Old delivery data in `rpos_orders` table is not migrated to Zaikon tables (future enhancement)

## Success Criteria
✓ Delivery charges calculated correctly (slab-based and free delivery)
✓ Orders and deliveries saved atomically to database
✓ Billing panel shows correct delivery charges
✓ Receipts include delivery details and charges
✓ Delivery Customers dashboard shows accurate analytics
✓ Legacy pages show deprecation notices
✓ No errors in browser console or PHP logs
