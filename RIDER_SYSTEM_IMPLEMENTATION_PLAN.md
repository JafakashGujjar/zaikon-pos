# Zaikon Rider Management System - Implementation Plan

## Executive Summary
This document outlines a comprehensive plan to build a complete rider management system for the Zaikon POS delivery subsystem. The system will enable rider assignment, delivery lifecycle tracking, payout calculation, and rider performance analytics.

## Current State Analysis

### ✅ Existing Infrastructure (Already Implemented)
1. **Database Tables**
   - `wp_zaikon_riders` - Rider profiles (id, name, phone, status)
   - `wp_zaikon_rider_payouts` - Payout records per delivery
   - `wp_zaikon_rider_fuel_logs` - Fuel consumption tracking
   - `wp_zaikon_deliveries` - Has `assigned_rider_id` and `delivery_status` fields

2. **Model Classes**
   - `Zaikon_Riders` - CRUD operations for riders
   - `Zaikon_Rider_Payouts` - Payout management
   - `Zaikon_Rider_Fuel_Logs` - Fuel logging
   - `Zaikon_Deliveries` - Delivery management with rider assignment support
   - `Zaikon_Order_Service` - Atomic order/delivery creation

3. **Basic UI**
   - `zaikon-delivery-management.php` - Admin page with rider CRUD (basic)
   - `rider-deliveries.php` - Basic rider deliveries page
   - Rider slip printing already implemented

### ❌ Missing Components (To Be Implemented)
1. **POS UI** - No rider assignment interface after order completion
2. **Admin Screens** - Limited functionality for rider deliveries and payroll
3. **Lifecycle Management** - No state machine for delivery progression
4. **Payout Models** - Simple calculation, no configurable models
5. **Analytics Integration** - Rider data not integrated into reporting
6. **Real-time Updates** - No live tracking or status updates

---

## Implementation Phases

### Phase 1: POS Rider Assignment UI (Week 1)
**Goal**: Add rider selection interface to POS after delivery order completion

#### 1.1 Backend API Endpoints
**File**: `includes/class-rpos-rest-api.php`

Add new REST endpoints:
```php
// GET /restaurant-pos/v1/riders/available
// Returns list of active riders for assignment
public function get_available_riders($request) {
    $riders = Zaikon_Riders::get_all(true); // active only
    return rest_ensure_response($riders);
}

// POST /restaurant-pos/v1/deliveries/{id}/assign-rider
// Assigns rider to delivery and creates payout record
public function assign_rider_to_delivery($request) {
    $delivery_id = $request['id'];
    $rider_id = $request['rider_id'];
    
    // Use existing Zaikon_Order_Service::assign_rider()
    $result = Zaikon_Order_Service::assign_rider($delivery_id, $rider_id);
    
    if ($result) {
        return rest_ensure_response(['success' => true, 'delivery_id' => $delivery_id]);
    }
    return new WP_Error('assignment_failed', 'Failed to assign rider');
}
```

#### 1.2 Frontend Modal Component
**File**: `assets/js/rider-assignment.js` (NEW)

Create rider assignment modal:
```javascript
var RPOS_RiderAssignment = {
    deliveryId: null,
    orderNumber: null,
    
    init: function() {
        this.createModal();
        this.bindEvents();
    },
    
    open: function(deliveryId, orderNumber) {
        this.deliveryId = deliveryId;
        this.orderNumber = orderNumber;
        this.loadRiders();
        $('#rpos-rider-assignment-modal').fadeIn();
    },
    
    loadRiders: function() {
        // GET /restaurant-pos/v1/riders/available
        // Populate rider list with radio buttons
    },
    
    assignRider: function(riderId) {
        // POST /restaurant-pos/v1/deliveries/{id}/assign-rider
        // Show success message
        // Close modal
    }
};
```

#### 1.3 Integration Point
**File**: `assets/js/admin.js`

Modify `showReceipt()` to trigger rider assignment for delivery orders:
```javascript
showReceipt: function(order, orderData) {
    // ... existing receipt code ...
    
    // Show rider assignment button if delivery order
    if (orderData.order_type === 'delivery' && order.delivery && order.delivery.id) {
        $('#rpos-assign-rider-btn').show().data('delivery-id', order.delivery.id);
    }
}
```

**File**: `includes/admin/pos.php`

Add assignment button to receipt modal:
```html
<button id="rpos-assign-rider-btn" class="zaikon-btn zaikon-btn-secondary" style="display:none;">
    Assign Rider
</button>
```

#### 1.4 Deliverables
- [ ] REST API endpoints for rider listing and assignment
- [ ] Rider assignment modal UI
- [ ] Integration with receipt screen
- [ ] Success/error notifications
- [ ] Unit tests for assignment logic

---

### Phase 2: Delivery Lifecycle State Machine (Week 2)
**Goal**: Implement proper state transitions for delivery tracking

#### 2.1 Enhanced Delivery Status Management
**File**: `includes/class-zaikon-deliveries.php`

Add state transition validation:
```php
class Zaikon_Deliveries {
    
    const STATUS_PENDING = 'pending';
    const STATUS_ASSIGNED = 'assigned';      // NEW
    const STATUS_PICKED = 'picked';          // NEW
    const STATUS_ON_ROUTE = 'on_route';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    
    private static $valid_transitions = [
        'pending' => ['assigned', 'failed'],
        'assigned' => ['picked', 'failed'],
        'picked' => ['on_route', 'failed'],
        'on_route' => ['delivered', 'failed'],
        'delivered' => [],
        'failed' => []
    ];
    
    public static function update_status($delivery_id, $new_status, $metadata = []) {
        $delivery = self::get($delivery_id);
        
        if (!$delivery) {
            return false;
        }
        
        // Validate transition
        if (!self::is_valid_transition($delivery->delivery_status, $new_status)) {
            return new WP_Error('invalid_transition', 
                "Cannot transition from {$delivery->delivery_status} to {$new_status}");
        }
        
        // Update status
        $update_data = ['delivery_status' => $new_status];
        
        // Set timestamp fields
        if ($new_status === 'picked') {
            $update_data['picked_at'] = current_time('mysql');
        } elseif ($new_status === 'delivered') {
            $update_data['delivered_at'] = current_time('mysql');
        }
        
        $result = self::update($delivery_id, $update_data);
        
        // Log state transition
        Zaikon_System_Events::log('delivery', $delivery_id, 'status_change', [
            'from' => $delivery->delivery_status,
            'to' => $new_status,
            'metadata' => $metadata
        ]);
        
        return $result;
    }
    
    private static function is_valid_transition($current, $new) {
        return in_array($new, self::$valid_transitions[$current] ?? []);
    }
}
```

#### 2.2 Database Schema Update
**File**: `includes/class-rpos-install.php`

Add migration to update delivery_status enum and add timestamps:
```php
// In maybe_upgrade() method
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}` LIKE 'picked_at'");
if (empty($column_exists)) {
    $wpdb->query("ALTER TABLE `{$table_name}` 
        MODIFY COLUMN delivery_status ENUM('pending','assigned','picked','on_route','delivered','failed') DEFAULT 'pending',
        ADD COLUMN picked_at datetime DEFAULT NULL AFTER assigned_rider_id,
        ADD COLUMN assigned_at datetime DEFAULT NULL AFTER assigned_rider_id");
}
```

#### 2.3 REST API Endpoints
**File**: `includes/class-rpos-rest-api.php`

```php
// POST /restaurant-pos/v1/deliveries/{id}/update-status
public function update_delivery_status($request) {
    $delivery_id = $request['id'];
    $new_status = $request['status'];
    $metadata = $request->get_json_params();
    
    $result = Zaikon_Deliveries::update_status($delivery_id, $new_status, $metadata);
    
    if (is_wp_error($result)) {
        return $result;
    }
    
    return rest_ensure_response(['success' => true]);
}
```

#### 2.4 Deliverables
- [ ] State machine with validation
- [ ] Database schema updates (new statuses, timestamps)
- [ ] Status update API endpoint
- [ ] Event logging for transitions
- [ ] State transition diagram documentation

---

### Phase 3: Rider Deliveries Admin Screen (Week 3)
**Goal**: Comprehensive rider deliveries management interface

#### 3.1 Enhanced Rider Deliveries Page
**File**: `includes/admin/rider-deliveries-enhanced.php` (NEW)

Features:
1. **Delivery List View**
   - Filter by: rider, status, date range, location
   - Sort by: created_at, status, distance
   - Columns: Order#, Customer, Location, Distance, Status, Rider, Payout, Actions
   
2. **Status Update Actions**
   - Quick status change buttons (Assign, Pick Up, On Route, Delivered, Failed)
   - Confirmation dialogs with optional notes
   
3. **Delivery Details Panel**
   - Expandable row showing full delivery info
   - Customer contact, special instructions
   - Timeline of status changes
   - Rider payout calculation breakdown

4. **Bulk Operations**
   - Bulk assign to rider
   - Bulk status updates
   - Export to CSV

```php
<?php
// Rider Deliveries Enhanced Admin Page
global $wpdb;

// Handle status update
if (isset($_POST['update_delivery_status'])) {
    $delivery_id = absint($_POST['delivery_id']);
    $new_status = sanitize_text_field($_POST['new_status']);
    Zaikon_Deliveries::update_status($delivery_id, $new_status);
}

// Get filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$rider_filter = isset($_GET['rider_id']) ? absint($_GET['rider_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Build query
$query = "SELECT d.*, o.order_number, o.grand_total_rs, r.name as rider_name, p.rider_pay_rs
          FROM {$wpdb->prefix}zaikon_deliveries d
          INNER JOIN {$wpdb->prefix}zaikon_orders o ON d.order_id = o.id
          LEFT JOIN {$wpdb->prefix}zaikon_riders r ON d.assigned_rider_id = r.id
          LEFT JOIN {$wpdb->prefix}zaikon_rider_payouts p ON p.delivery_id = d.id
          WHERE d.created_at >= %s AND d.created_at <= %s";

$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

if ($status_filter) {
    $query .= " AND d.delivery_status = %s";
    $params[] = $status_filter;
}

if ($rider_filter) {
    $query .= " AND d.assigned_rider_id = %d";
    $params[] = $rider_filter;
}

$query .= " ORDER BY d.created_at DESC";

$deliveries = $wpdb->get_results($wpdb->prepare($query, $params));
?>

<div class="wrap">
    <h1><?php _e('Rider Deliveries Management', 'restaurant-pos'); ?></h1>
    
    <!-- Filters Section -->
    <div class="zaikon-filters-card">
        <!-- Date range, status, rider filters -->
    </div>
    
    <!-- Stats Cards -->
    <div class="zaikon-stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($deliveries); ?></div>
            <div class="stat-label">Total Deliveries</div>
        </div>
        <!-- More stats: pending, in progress, completed, failed -->
    </div>
    
    <!-- Deliveries Table -->
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><input type="checkbox" class="select-all"></th>
                <th>Order#</th>
                <th>Customer</th>
                <th>Location</th>
                <th>Distance</th>
                <th>Status</th>
                <th>Rider</th>
                <th>Payout</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deliveries as $delivery): ?>
                <tr data-delivery-id="<?php echo $delivery->id; ?>">
                    <!-- Table cells -->
                    <td>
                        <div class="row-actions">
                            <!-- Status change buttons based on current status -->
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

#### 3.2 Menu Integration
**File**: `includes/class-rpos-admin-menu.php`

Replace basic rider deliveries page:
```php
add_submenu_page(
    'restaurant-pos',
    __('Rider Deliveries', 'restaurant-pos'),
    __('Rider Deliveries', 'restaurant-pos'),
    'rpos_manage_inventory',
    'restaurant-pos-rider-deliveries',
    array($this, 'rider_deliveries_enhanced_page')
);
```

#### 3.3 JavaScript for Interactive Features
**File**: `assets/js/rider-deliveries.js` (NEW)

```javascript
jQuery(document).ready(function($) {
    // Quick status updates
    $('.status-action-btn').on('click', function() {
        var deliveryId = $(this).data('delivery-id');
        var newStatus = $(this).data('new-status');
        updateDeliveryStatus(deliveryId, newStatus);
    });
    
    // Bulk operations
    $('#bulk-assign-rider').on('click', function() {
        var selectedDeliveries = $('.delivery-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        showBulkAssignModal(selectedDeliveries);
    });
    
    // Real-time search/filter
    $('#delivery-search').on('input', debounce(function() {
        filterDeliveries();
    }, 300));
});
```

#### 3.4 Deliverables
- [ ] Enhanced rider deliveries page with filters
- [ ] Status update actions (all transitions)
- [ ] Bulk operations support
- [ ] Delivery timeline view
- [ ] Export functionality
- [ ] Responsive design

---

### Phase 4: Rider Payroll System (Week 4)
**Goal**: Comprehensive payroll management and calculation

#### 4.1 Configurable Payout Models
**File**: `includes/class-zaikon-payout-calculator.php` (NEW)

```php
<?php
class Zaikon_Payout_Calculator {
    
    const MODEL_PER_DELIVERY = 'per_delivery';
    const MODEL_PER_KM = 'per_km';
    const MODEL_HYBRID = 'hybrid';
    
    /**
     * Calculate rider payout based on configured model
     */
    public static function calculate($delivery_id, $model = null) {
        global $wpdb;
        
        $delivery = Zaikon_Deliveries::get($delivery_id);
        
        if (!$delivery) {
            return 0;
        }
        
        // Get model from settings if not specified
        if (!$model) {
            $model = get_option('zaikon_payout_model', self::MODEL_HYBRID);
        }
        
        $payout = 0;
        
        switch ($model) {
            case self::MODEL_PER_DELIVERY:
                $payout = self::calculate_per_delivery($delivery);
                break;
                
            case self::MODEL_PER_KM:
                $payout = self::calculate_per_km($delivery);
                break;
                
            case self::MODEL_HYBRID:
                $payout = self::calculate_hybrid($delivery);
                break;
        }
        
        // Apply minimum payout rule
        $min_payout = floatval(get_option('zaikon_min_rider_payout', 20));
        return max($payout, $min_payout);
    }
    
    private static function calculate_per_delivery($delivery) {
        return floatval(get_option('zaikon_payout_per_delivery', 50));
    }
    
    private static function calculate_per_km($delivery) {
        $rate = floatval(get_option('zaikon_payout_per_km', 10));
        return $delivery->distance_km * $rate;
    }
    
    private static function calculate_hybrid($delivery) {
        $base = floatval(get_option('zaikon_payout_base', 20));
        $per_km = floatval(get_option('zaikon_payout_per_km', 10));
        return $base + ($delivery->distance_km * $per_km);
    }
    
    /**
     * Calculate payout with fuel deduction
     */
    public static function calculate_net_payout($delivery_id, $fuel_cost = null) {
        $gross_payout = self::calculate($delivery_id);
        
        if ($fuel_cost === null) {
            $delivery = Zaikon_Deliveries::get($delivery_id);
            $fuel_rate = floatval(get_option('zaikon_fuel_cost_per_km', 5));
            $fuel_cost = $delivery->distance_km * $fuel_rate;
        }
        
        return max($gross_payout - $fuel_cost, 0);
    }
}
```

#### 4.2 Payout Settings Page
**File**: `includes/admin/zaikon-payout-settings.php` (NEW)

Settings to configure:
- Payout model selection (per_delivery, per_km, hybrid)
- Base payout amount
- Per KM rate
- Per delivery flat rate
- Minimum payout
- Fuel cost per KM
- Deduction rules

#### 4.3 Rider Payroll Report
**File**: `includes/admin/rider-payroll.php` (NEW)

Features:
1. **Period Selection**
   - Daily, Weekly, Biweekly, Monthly
   - Custom date range

2. **Rider Payroll Summary**
   - Total deliveries completed
   - Total distance covered
   - Gross payout (before deductions)
   - Fuel costs (estimated)
   - Net payout (after deductions)
   - Average payout per delivery
   - Average payout per km

3. **Detailed Breakdown**
   - List all deliveries in period
   - Per-delivery payout calculation
   - Export to CSV/PDF

4. **Batch Payment Processing**
   - Mark payouts as paid
   - Generate payment vouchers
   - Track payment history

```php
<?php
// Rider Payroll Admin Page
$rider_id = isset($_GET['rider_id']) ? absint($_GET['rider_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Get rider info
$rider = Zaikon_Riders::get($rider_id);

// Get deliveries with payouts
$query = "SELECT d.*, o.order_number, p.rider_pay_rs
          FROM {$wpdb->prefix}zaikon_deliveries d
          INNER JOIN {$wpdb->prefix}zaikon_orders o ON d.order_id = o.id
          LEFT JOIN {$wpdb->prefix}zaikon_rider_payouts p ON p.delivery_id = d.id
          WHERE d.assigned_rider_id = %d
          AND d.delivered_at >= %s AND d.delivered_at <= %s
          AND d.delivery_status = 'delivered'
          ORDER BY d.delivered_at DESC";

$deliveries = $wpdb->get_results($wpdb->prepare($query, $rider_id, $date_from, $date_to));

// Calculate totals
$total_deliveries = count($deliveries);
$total_distance = array_sum(array_column($deliveries, 'distance_km'));
$gross_payout = array_sum(array_column($deliveries, 'rider_pay_rs'));
$fuel_cost = $total_distance * floatval(get_option('zaikon_fuel_cost_per_km', 5));
$net_payout = $gross_payout - $fuel_cost;
?>

<div class="wrap">
    <h1><?php printf(__('Payroll for %s', 'restaurant-pos'), $rider->name); ?></h1>
    
    <!-- Period Selector -->
    <div class="period-selector">
        <!-- Date range picker -->
    </div>
    
    <!-- Summary Cards -->
    <div class="payroll-summary">
        <div class="summary-card">
            <h3>Total Deliveries</h3>
            <div class="amount"><?php echo $total_deliveries; ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Distance</h3>
            <div class="amount"><?php echo number_format($total_distance, 2); ?> km</div>
        </div>
        <div class="summary-card">
            <h3>Gross Payout</h3>
            <div class="amount">Rs <?php echo number_format($gross_payout, 2); ?></div>
        </div>
        <div class="summary-card">
            <h3>Fuel Cost</h3>
            <div class="amount">Rs <?php echo number_format($fuel_cost, 2); ?></div>
        </div>
        <div class="summary-card primary">
            <h3>Net Payout</h3>
            <div class="amount">Rs <?php echo number_format($net_payout, 2); ?></div>
        </div>
    </div>
    
    <!-- Detailed Deliveries Table -->
    <h2>Delivery Breakdown</h2>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Order#</th>
                <th>Customer</th>
                <th>Location</th>
                <th>Distance (km)</th>
                <th>Payout (Rs)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deliveries as $delivery): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i', strtotime($delivery->delivered_at)); ?></td>
                    <td><?php echo $delivery->order_number; ?></td>
                    <td><?php echo $delivery->customer_name; ?></td>
                    <td><?php echo $delivery->location_name; ?></td>
                    <td><?php echo $delivery->distance_km; ?></td>
                    <td>Rs <?php echo number_format($delivery->rider_pay_rs, 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Actions -->
    <div class="payroll-actions">
        <button class="button button-primary" onclick="exportPayroll()">Export to CSV</button>
        <button class="button" onclick="printPayroll()">Print Report</button>
        <button class="button button-secondary" onclick="markAsPaid()">Mark as Paid</button>
    </div>
</div>
```

#### 4.4 Menu Integration
**File**: `includes/class-rpos-admin-menu.php`

```php
add_submenu_page(
    'restaurant-pos',
    __('Rider Payroll', 'restaurant-pos'),
    __('Rider Payroll', 'restaurant-pos'),
    'rpos_manage_settings',
    'restaurant-pos-rider-payroll',
    array($this, 'rider_payroll_page')
);

add_submenu_page(
    'restaurant-pos',
    __('Payout Settings', 'restaurant-pos'),
    __('Payout Settings', 'restaurant-pos'),
    'rpos_manage_settings',
    'restaurant-pos-payout-settings',
    array($this, 'payout_settings_page')
);
```

#### 4.5 Deliverables
- [ ] Configurable payout calculator
- [ ] Payout settings admin page
- [ ] Rider payroll report page
- [ ] Fuel cost deduction support
- [ ] Payment tracking system
- [ ] Export/print functionality

---

### Phase 5: Analytics Integration (Week 5)
**Goal**: Integrate rider metrics into delivery analytics

#### 5.1 Enhanced Delivery Analytics
**File**: `includes/admin/delivery-customers.php`

Add rider performance section:
```php
// Rider Performance Analytics
$rider_stats_query = "
    SELECT 
        r.id,
        r.name as rider_name,
        COUNT(d.id) as total_deliveries,
        SUM(d.distance_km) as total_distance_km,
        AVG(d.distance_km) as avg_distance_km,
        SUM(p.rider_pay_rs) as total_payout,
        AVG(p.rider_pay_rs) as avg_payout,
        AVG(TIMESTAMPDIFF(MINUTE, d.created_at, d.delivered_at)) as avg_delivery_time_mins,
        SUM(CASE WHEN d.delivery_status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries,
        SUM(CASE WHEN d.delivery_status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries
    FROM {$wpdb->prefix}zaikon_riders r
    LEFT JOIN {$wpdb->prefix}zaikon_deliveries d ON r.id = d.assigned_rider_id
    LEFT JOIN {$wpdb->prefix}zaikon_rider_payouts p ON p.delivery_id = d.id
    WHERE d.created_at >= %s AND d.created_at <= %s
    GROUP BY r.id
    ORDER BY total_deliveries DESC
";

$rider_stats = $wpdb->get_results($wpdb->prepare($rider_stats_query, $date_from, $date_to));
```

Display rider performance table:
- Deliveries completed
- Success rate
- Total distance
- Average delivery time
- Total earnings
- Average payout per delivery

#### 5.2 Rider Dashboard Widget
**File**: `includes/admin/dashboard.php`

Add rider metrics widget to main dashboard:
- Active riders count
- Deliveries in progress
- Top performing rider (by deliveries)
- Today's total payouts

#### 5.3 Delivery Reports Enhancement
**File**: `includes/admin/delivery-reports.php`

Add tabs:
1. **Customer Analytics** (existing)
2. **Rider Performance** (new)
   - Rider comparison charts
   - Efficiency metrics
   - Earnings trends
3. **Location Analytics** (enhanced with rider data)
4. **Time-based Analytics** (peak hours by rider)

#### 5.4 Deliverables
- [ ] Rider performance metrics
- [ ] Dashboard widget
- [ ] Enhanced delivery reports
- [ ] Comparison charts
- [ ] Export functionality

---

### Phase 6: Mobile Rider App Integration (Future/Optional)

#### 6.1 REST API for Mobile
New endpoints for rider mobile app:
- Login/authentication
- Get assigned deliveries
- Update delivery status (with GPS)
- Upload delivery proof (photo)
- View earnings

#### 6.2 Real-time Updates
- WebSocket or polling for live order updates
- Push notifications for new assignments
- GPS tracking integration

---

## Technical Requirements

### Database Migrations
**File**: `includes/class-rpos-install.php`

Add version checking and migrations:
```php
private static function maybe_upgrade() {
    $current_version = get_option('zaikon_rider_system_version', '1.0.0');
    
    if (version_compare($current_version, '2.0.0', '<')) {
        self::upgrade_to_2_0_0();
    }
}

private static function upgrade_to_2_0_0() {
    global $wpdb;
    
    // Add new columns to deliveries table
    $table = $wpdb->prefix . 'zaikon_deliveries';
    
    $wpdb->query("ALTER TABLE `{$table}` 
        MODIFY COLUMN delivery_status ENUM('pending','assigned','picked','on_route','delivered','failed') DEFAULT 'pending',
        ADD COLUMN assigned_at datetime DEFAULT NULL AFTER assigned_rider_id,
        ADD COLUMN picked_at datetime DEFAULT NULL AFTER assigned_at");
    
    // Add payout status tracking
    $table = $wpdb->prefix . 'zaikon_rider_payouts';
    $wpdb->query("ALTER TABLE `{$table}`
        ADD COLUMN payment_status ENUM('pending','paid','cancelled') DEFAULT 'pending',
        ADD COLUMN paid_at datetime DEFAULT NULL");
    
    update_option('zaikon_rider_system_version', '2.0.0');
}
```

### New Settings Options
Add to WordPress options table:
```php
// Payout Model Settings
add_option('zaikon_payout_model', 'hybrid');
add_option('zaikon_payout_base', 20);
add_option('zaikon_payout_per_km', 10);
add_option('zaikon_payout_per_delivery', 50);
add_option('zaikon_min_rider_payout', 20);
add_option('zaikon_fuel_cost_per_km', 5);

// Notification Settings
add_option('zaikon_notify_rider_assignment', true);
add_option('zaikon_notify_delivery_completed', true);
```

### CSS Styling
**File**: `assets/css/rider-management.css` (NEW)

Styles for:
- Rider assignment modal
- Status badges and buttons
- Payroll report layout
- Analytics charts
- Mobile responsive design

### JavaScript Dependencies
- Chart.js for analytics visualization
- DataTables for sortable/filterable tables
- Select2 for enhanced dropdowns
- Moment.js for date handling

---

## Testing Strategy

### Unit Tests
- Payout calculation logic (all models)
- State transition validation
- Database queries

### Integration Tests
- Order creation → rider assignment flow
- Status update → payout calculation
- Analytics queries with sample data

### User Acceptance Testing
1. **POS Flow**
   - Create delivery order
   - Assign rider from receipt
   - Verify payout created

2. **Rider Management**
   - Update delivery status through all states
   - View rider deliveries with filters
   - Calculate payroll for period

3. **Analytics**
   - Verify rider metrics accuracy
   - Compare customer vs rider analytics
   - Export reports

### Performance Testing
- Analytics queries with 10k+ deliveries
- Concurrent rider assignments
- Report generation time

---

## Implementation Timeline

| Phase | Duration | Dependencies | Deliverables |
|-------|----------|--------------|--------------|
| 1. POS Rider Assignment | 1 week | None | Modal UI, API endpoints |
| 2. Lifecycle Management | 1 week | Phase 1 | State machine, status updates |
| 3. Rider Deliveries Page | 1 week | Phase 2 | Enhanced admin screen |
| 4. Payroll System | 1 week | Phase 3 | Payout calculator, reports |
| 5. Analytics Integration | 1 week | Phase 4 | Metrics, dashboards |
| 6. Testing & Refinement | 1 week | All | Bug fixes, optimization |

**Total: 6 weeks**

---

## Success Metrics

### Operational Metrics
- Time to assign rider: < 30 seconds
- Delivery status update latency: < 5 seconds
- Payroll calculation accuracy: 100%
- Report generation time: < 3 seconds

### Business Metrics
- Rider utilization rate: > 70%
- Delivery completion rate: > 95%
- Average delivery time reduction: 20%
- Payout transparency increase: 100%

---

## Risk Mitigation

### Technical Risks
1. **Database Performance**: Add indexes on frequently queried columns
2. **State Conflicts**: Use database transactions for status updates
3. **Data Integrity**: Implement validation at all layers

### Operational Risks
1. **Training**: Provide user documentation and video tutorials
2. **Migration**: Implement gradual rollout with feature flags
3. **Rollback**: Maintain database backups before migrations

---

## Maintenance & Support

### Documentation
- User manual for POS operators
- Admin guide for rider management
- API documentation for future integrations
- Database schema documentation

### Monitoring
- Track rider assignment rates
- Monitor payout calculation accuracy
- Log state transition errors
- Alert on failed deliveries

### Future Enhancements
- AI-based rider assignment optimization
- Predictive delivery time estimates
- Customer rating system for riders
- Gamification (badges, leaderboards)
- Integration with third-party delivery platforms

---

## Conclusion

This implementation plan provides a comprehensive roadmap for building a production-ready rider management system. The phased approach allows for iterative development and testing, ensuring each component works correctly before moving to the next phase.

The system will enable:
✅ Complete delivery lifecycle tracking
✅ Fair and transparent rider payouts
✅ Data-driven rider performance analysis
✅ Efficient delivery operations management
✅ Scalable foundation for future enhancements

**Next Steps**: Approve plan → Begin Phase 1 implementation → Iterate based on feedback
