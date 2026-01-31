# POS/KDS/Tracking Synchronization Fix - Architecture Documentation

## Problem Statement

The order tracking page was **not syncing with the Kitchen Display System (KDS)** because order data, status updates, and tracking tokens were handled in **disconnected logic and/or different tables**.

### Root Cause

The plugin had **two separate, independent order tables**:

1. **`wp_rpos_orders`** - Used by KDS for kitchen operations
2. **`wp_zaikon_orders`** - Used by Tracking system for customer order tracking

**Critical Issue:** KDS read orders from `rpos_orders` while Tracking read from `zaikon_orders`. When KDS updated an order status in `rpos_orders`, the Tracking system couldn't see it because it only looked at `zaikon_orders`.

### Architectural Problems Identified

| Issue | Impact | Before Fix |
|-------|--------|------------|
| **No single source of truth** | Data inconsistency | Orders could exist in one table but not the other |
| **Disconnected status updates** | KDS changes invisible to Tracking | `rpos_orders.status` ≠ `zaikon_orders.order_status` |
| **Missing tracking tokens** | Token verification failures | `rpos_orders` had no `tracking_token` field |
| **One-directional sync** | Incomplete data flow | Only `rpos → zaikon` sync existed |
| **Different status enums** | Invalid statuses possible | `rpos_orders.status` VARCHAR vs `zaikon_orders.order_status` ENUM |

## Solution Implemented

### 1. Established Single Source of Truth

**`wp_zaikon_orders`** is now the **primary table** for all order operations.

- All systems (POS, KDS, Tracking) now read from `zaikon_orders`
- `rpos_orders` is maintained for backward compatibility only
- Order creation **always** happens in `zaikon_orders` first
- Status updates **always** modify `zaikon_orders` first

### 2. Unified Data Flow

```
┌─────────────────────────────────────────────────────────┐
│                    Order Creation                        │
│                                                          │
│  POS → Zaikon_Order_Service::create_order()             │
│         ↓                                                │
│    wp_zaikon_orders (PRIMARY)                           │
│         ↓                                                │
│    Generate tracking_token                              │
│         ↓                                                │
│    Sync → wp_rpos_orders (SECONDARY)                    │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                   KDS Status Update                      │
│                                                          │
│  KDS → REST API update_order_status()                   │
│         ↓                                                │
│    Zaikon_Order_Events::dispatch()                      │
│         ↓                                                │
│    Update wp_zaikon_orders.order_status                 │
│    Set timestamp (cooking_started_at, ready_at, etc.)   │
│         ↓                                                │
│    Sync → wp_rpos_orders.status                         │
│         ↓                                                │
│    Tracking sees change immediately ✓                   │
└─────────────────────────────────────────────────────────┘
```

### 3. Key Changes Made

#### A. Enhanced `Zaikon_Orders::get_all()` (class-zaikon-orders.php)

Added support for KDS requirements:
- **Status filtering** (`status` parameter)
- **JOIN with users table** for cashier names
- **Load order items** for each order
- **Field name mapping** for backward compatibility
  - `order_status` → `status`
  - `items_subtotal_rs` → `subtotal`
  - `grand_total_rs` → `total`
  - `discounts_rs` → `discount`

#### B. Updated REST API Endpoints (class-rpos-rest-api.php)

| Endpoint | Before | After |
|----------|--------|-------|
| `GET /orders` | Read from `rpos_orders` | Read from `zaikon_orders` |
| `GET /orders/{id}` | Read from `rpos_orders` | Read from `zaikon_orders` |
| `PUT /orders/{id}` | Update `rpos_orders` → sync to `zaikon_orders` | Update `zaikon_orders` → sync to `rpos_orders` |
| `POST /orders` | Create in `rpos_orders` → sync to `zaikon_orders` | Create in `zaikon_orders` → sync to `rpos_orders` |

**Status Update Flow:**
1. Receives status change from KDS
2. Dispatches lifecycle event (`Zaikon_Order_Events::dispatch()`)
3. Event handler updates `zaikon_orders.order_status` and timestamps
4. Syncs status to `rpos_orders.status` (backward compatibility)

#### C. Updated KDS UI (includes/admin/kds.php, assets/js/admin.js)

**Status Enum Alignment:**

Old KDS statuses: `'new', 'cooking', 'ready', 'completed'`  
New zaikon_orders statuses: `'pending', 'confirmed', 'active', 'cooking', 'ready', 'dispatched', 'delivered', 'completed'`

**Changes Made:**
- Filter buttons updated to use zaikon_orders enum values
- "New" filter now matches: `pending`, `confirmed`, `active`
- Order list shows orders with active kitchen statuses
- Action button logic updated to check for new status values
- Delay reason modal triggers updated

**KDS Display Logic:**
```javascript
// Orders shown in KDS (active kitchen orders)
['pending', 'confirmed', 'active', 'cooking', 'ready']

// Filter: "New" → Shows
['pending', 'confirmed', 'active']

// Filter: "Cooking" → Shows
['cooking']

// Filter: "Ready" → Shows
['ready']
```

#### D. Event-Based Status Management

Status updates now use the event system (`Zaikon_Order_Events`):

| KDS Action | Event Dispatched | Status Set | Timestamp Updated |
|------------|-----------------|------------|-------------------|
| Start Cooking | `EVENT_COOKING_STARTED` | `cooking` | `cooking_started_at` |
| Mark Ready | `EVENT_KITCHEN_COMPLETED` | `ready` | `ready_at` |
| Complete | `EVENT_ORDER_DISPATCHED` | `dispatched` | `dispatched_at` |

This ensures:
- ✅ Atomic status + timestamp updates
- ✅ Audit trail in database
- ✅ No stale or inconsistent data

### 4. Status Mapping Reference

**zaikon_orders.order_status ENUM values:**
```sql
ENUM(
  'pending',      -- Delivery orders awaiting confirmation
  'confirmed',    -- Non-delivery orders ready for kitchen
  'active',       -- Legacy status for some delivery orders
  'cooking',      -- Order being prepared
  'ready',        -- Order ready for pickup/dispatch
  'dispatched',   -- Delivery order on the way
  'delivered',    -- Delivery completed
  'completed',    -- Order finalized
  'cancelled',    -- Order cancelled
  'replacement'   -- Replacement order
)
```

**Lifecycle Flow:**
```
Non-Delivery Orders:
  pending/confirmed → cooking → ready → completed

Delivery Orders:
  pending → confirmed → cooking → ready → dispatched → delivered
```

## Testing & Verification

### Manual Testing Checklist

- [ ] **Order Creation**
  - [ ] Create dine-in order from POS → Verify appears in KDS with 'confirmed' status
  - [ ] Create takeaway order from POS → Verify appears in KDS
  - [ ] Create delivery order from POS → Verify appears in KDS with 'pending' status
  - [ ] Verify all orders have tracking tokens in `zaikon_orders`

- [ ] **KDS Display**
  - [ ] Open KDS → Verify orders load from `zaikon_orders`
  - [ ] Test "All Orders" filter → Shows pending, confirmed, active, cooking, ready
  - [ ] Test "New" filter → Shows only pending, confirmed, active
  - [ ] Test "Cooking" filter → Shows only cooking
  - [ ] Test "Ready" filter → Shows only ready

- [ ] **KDS Status Updates**
  - [ ] Click "Start Cooking" → Verify status changes to 'cooking' in both tables
  - [ ] Click "Mark Ready" → Verify status changes to 'ready' in both tables
  - [ ] Click "Complete" → Verify status changes to 'dispatched'/'completed'
  - [ ] Check timestamps: cooking_started_at, ready_at, dispatched_at are set

- [ ] **Tracking Sync**
  - [ ] Open tracking page for order
  - [ ] Update status in KDS
  - [ ] Refresh tracking page → Verify status change is visible
  - [ ] Verify progress bar reflects new status
  - [ ] Verify timestamps are displayed correctly

- [ ] **Database Verification**
  ```sql
  -- Check order exists in both tables
  SELECT 'zaikon' as tbl, id, order_number, order_status, tracking_token 
  FROM wp_zaikon_orders WHERE order_number = 'ORD-XXXXXXXX'
  UNION ALL
  SELECT 'rpos' as tbl, id, order_number, status, NULL 
  FROM wp_rpos_orders WHERE order_number = 'ORD-XXXXXXXX';
  
  -- Check status consistency
  SELECT 
    z.order_number,
    z.order_status as zaikon_status,
    r.status as rpos_status,
    z.cooking_started_at,
    z.ready_at,
    z.dispatched_at
  FROM wp_zaikon_orders z
  LEFT JOIN wp_rpos_orders r ON z.order_number = r.order_number
  WHERE z.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);
  ```

### Automated Testing Suggestions

If implementing automated tests, verify:
1. `Zaikon_Orders::get_all()` with status filter returns correct orders
2. Order creation creates records in both tables
3. Status update in `zaikon_orders` syncs to `rpos_orders`
4. Tracking token is generated for all orders
5. Event system dispatches correct events for status changes
6. Timestamps are set correctly on status transitions

## Impact & Benefits

### Before Fix
❌ KDS status updates not visible in Tracking  
❌ Orders could be orphaned (exist in only one table)  
❌ Token verification failures  
❌ Data inconsistency between systems  
❌ No single source of truth  

### After Fix
✅ **KDS and Tracking read from same table** (`zaikon_orders`)  
✅ **Status updates in KDS immediately visible in Tracking**  
✅ **All orders have tracking tokens** (generated in `zaikon_orders`)  
✅ **Backward compatibility maintained** (rpos_orders still synced)  
✅ **Single source of truth** established (`zaikon_orders`)  
✅ **Event-based state management** ensures data integrity  
✅ **Audit trail** for all status changes  

## Backward Compatibility

The fix maintains backward compatibility by:
1. **Keeping `rpos_orders` table** - Not dropped, still synced
2. **Field name mapping** - API responses include both old and new field names
3. **Sync mechanisms** - Changes propagate to `rpos_orders` automatically
4. **Legacy status support** - Event system handles old status names

## Future Improvements

1. **Migration Script**: Create a data migration script to consolidate orphaned records
2. **Database Constraints**: Add foreign key constraints linking tables by `order_number`
3. **Status Enum Validation**: Add PHP validation to prevent invalid status values
4. **Automated Tests**: Implement PHPUnit tests for critical data flows
5. **Admin Dashboard**: Add status sync health check to admin dashboard
6. **Deprecation Plan**: Plan eventual removal of `rpos_orders` table (after grace period)

## Rollback Plan

If issues arise, rollback is straightforward:

1. Revert REST API changes to read from `rpos_orders`
2. Revert KDS JavaScript to use old status values
3. Data remains intact in both tables (no data loss)

## Files Modified

1. `includes/class-zaikon-orders.php` - Enhanced query with status support
2. `includes/class-rpos-rest-api.php` - Updated endpoints to use zaikon_orders
3. `includes/admin/kds.php` - Updated filter button statuses
4. `assets/js/admin.js` - Updated KDS JavaScript for new statuses

## Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────┐
│                        ZAIKON POS SYSTEM                          │
│                                                                   │
│  ┌─────────────┐      ┌──────────────────┐      ┌────────────┐  │
│  │     POS     │────▶ │  Order Creation  │────▶ │   KDS UI   │  │
│  │  Interface  │      │    (REST API)    │      │  Display   │  │
│  └─────────────┘      └──────────────────┘      └────────────┘  │
│                               │                         │         │
│                               ▼                         ▼         │
│                    ┌─────────────────────┐   ┌──────────────┐   │
│                    │  zaikon_orders      │◀──│ Status Update│   │
│                    │  (PRIMARY TABLE)    │   │  (REST API)  │   │
│                    │                     │   └──────────────┘   │
│                    │ - order_status      │            │          │
│                    │ - tracking_token    │            │          │
│                    │ - timestamps        │            ▼          │
│                    │ - order items       │   ┌──────────────┐   │
│                    └─────────────────────┘   │ Event System │   │
│                               │               │  (dispatch)  │   │
│                               │               └──────────────┘   │
│                               ▼                                   │
│                    ┌─────────────────────┐                       │
│                    │  rpos_orders        │                       │
│                    │  (SYNC/LEGACY)      │                       │
│                    │ - status            │                       │
│                    │ - (no tracking)     │                       │
│                    └─────────────────────┘                       │
│                               │                                   │
│                               ▼                                   │
│                    ┌─────────────────────┐                       │
│                    │  Tracking Page      │                       │
│                    │  (Customer View)    │                       │
│                    └─────────────────────┘                       │
│                                                                   │
│                    SINGLE SOURCE OF TRUTH                         │
│                    ═══════════════════════                        │
│                    wp_zaikon_orders                              │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

## Conclusion

This fix establishes **enterprise-level data integrity** by:
- Defining a **single source of truth** (`zaikon_orders`)
- Ensuring **real-time synchronization** between POS, KDS, and Tracking
- Implementing **event-based state management** for reliability
- Maintaining **backward compatibility** during transition
- Providing **full traceability** with timestamps and audit logs

The system now behaves like an **enterprise-level application** with reliable, traceable, and recoverable order management.
