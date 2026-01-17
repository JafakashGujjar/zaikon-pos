<?php
/**
 * Expenses History Admin Page
 * Displays all expenses with advanced filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filters using plugin timezone
$cashier_id = isset($_GET['cashier_id']) ? absint($_GET['cashier_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : RPOS_Timezone::now()->modify('-30 days')->format('Y-m-d');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : RPOS_Timezone::now()->format('Y-m-d');
$category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$rider_id = isset($_GET['rider_id']) ? absint($_GET['rider_id']) : 0;

// Pagination
$per_page = 100;
$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($paged - 1) * $per_page;

// Get all cashiers for dropdown
$cashiers = get_users(array('role__in' => array('administrator', 'restaurant_admin', 'cashier')));

// Get all riders for dropdown
$riders = Zaikon_Riders::get_all();

// Get expense categories
$categories = Zaikon_Expenses::get_categories();

// Get expenses
$expenses = array();
$total_count = 0;

if ($cashier_id > 0) {
    $expenses = Zaikon_Expenses::get_by_cashier($cashier_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59');
} else {
    // Get all expenses with pagination
    global $wpdb;
    
    $where = array("e.expense_date >= %s", "e.expense_date <= %s");
    $params = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
    
    // Count total for pagination
    $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}zaikon_expenses e WHERE " . implode(' AND ', $where);
    $total_count = $wpdb->get_var($wpdb->prepare($count_sql, $params));
    
    // Get paginated results
    $expenses = $wpdb->get_results($wpdb->prepare(
        "SELECT e.*, r.name as rider_name, u.display_name as cashier_name
         FROM {$wpdb->prefix}zaikon_expenses e
         LEFT JOIN {$wpdb->prefix}zaikon_riders r ON e.rider_id = r.id
         LEFT JOIN {$wpdb->users} u ON e.cashier_id = u.ID
         WHERE " . implode(' AND ', $where) . "
         ORDER BY e.expense_date DESC
         LIMIT %d OFFSET %d",
        array_merge($params, array($per_page, $offset))
    ));
}

// Apply additional filters
$filtered_expenses = array();
foreach ($expenses as $expense) {
    // Category filter
    if ($category && $expense->category !== $category) {
        continue;
    }
    
    // Rider filter
    if ($rider_id > 0 && $expense->rider_id != $rider_id) {
        continue;
    }
    
    $filtered_expenses[] = $expense;
}

// Calculate totals
$total_amount = 0;
foreach ($filtered_expenses as $expense) {
    $total_amount += floatval($expense->amount_rs);
}

$currency = RPOS_Settings::get('currency_symbol', '$');
?>

<div class="wrap">
    <h1><?php _e('Expenses History', 'restaurant-pos'); ?></h1>
    
    <!-- Filters -->
    <div class="rpos-filters" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccc;">
        <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="restaurant-pos-expenses-history">
            
            <div>
                <label for="date_from"><?php _e('From:', 'restaurant-pos'); ?></label><br>
                <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
            </div>
            
            <div>
                <label for="date_to"><?php _e('To:', 'restaurant-pos'); ?></label><br>
                <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
            </div>
            
            <div>
                <label for="cashier_id"><?php _e('Cashier:', 'restaurant-pos'); ?></label><br>
                <select name="cashier_id" id="cashier_id">
                    <option value="0"><?php _e('All Cashiers', 'restaurant-pos'); ?></option>
                    <?php foreach ($cashiers as $cashier): ?>
                        <option value="<?php echo $cashier->ID; ?>" <?php selected($cashier_id, $cashier->ID); ?>>
                            <?php echo esc_html($cashier->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="category"><?php _e('Category:', 'restaurant-pos'); ?></label><br>
                <select name="category" id="category">
                    <option value=""><?php _e('All Categories', 'restaurant-pos'); ?></option>
                    <?php foreach ($categories as $cat_key => $cat_label): ?>
                        <option value="<?php echo esc_attr($cat_key); ?>" <?php selected($category, $cat_key); ?>>
                            <?php echo esc_html($cat_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="rider_id"><?php _e('Rider:', 'restaurant-pos'); ?></label><br>
                <select name="rider_id" id="rider_id">
                    <option value="0"><?php _e('All Riders', 'restaurant-pos'); ?></option>
                    <?php foreach ($riders as $rider): ?>
                        <option value="<?php echo $rider->id; ?>" <?php selected($rider_id, $rider->id); ?>>
                            <?php echo esc_html($rider->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary"><?php _e('Filter', 'restaurant-pos'); ?></button>
            </div>
        </form>
    </div>
    
    <!-- Summary Card -->
    <div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccc;">
        <h3><?php _e('Summary', 'restaurant-pos'); ?></h3>
        <p style="font-size: 24px; margin: 0;">
            <strong><?php _e('Total Expenses:', 'restaurant-pos'); ?></strong> 
            <span style="color: #f97316;">Rs <?php echo number_format($total_amount, 2); ?></span>
        </p>
        <p style="margin: 5px 0 0 0; color: #666;">
            <?php printf(__('%d expense records found', 'restaurant-pos'), count($filtered_expenses)); ?>
        </p>
    </div>
    
    <!-- Expenses Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'restaurant-pos'); ?></th>
                <th><?php _e('Date', 'restaurant-pos'); ?></th>
                <th><?php _e('Cashier', 'restaurant-pos'); ?></th>
                <th><?php _e('Category', 'restaurant-pos'); ?></th>
                <th><?php _e('Amount (Rs)', 'restaurant-pos'); ?></th>
                <th><?php _e('Description', 'restaurant-pos'); ?></th>
                <th><?php _e('Rider', 'restaurant-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filtered_expenses)): ?>
                <tr>
                    <td colspan="7"><?php _e('No expenses found.', 'restaurant-pos'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($filtered_expenses as $expense): ?>
                    <?php
                    // Get cashier name if not already present
                    if (!isset($expense->cashier_name)) {
                        $cashier = get_userdata($expense->cashier_id);
                        $expense->cashier_name = $cashier ? $cashier->display_name : __('Unknown', 'restaurant-pos');
                    }
                    
                    // Get category label
                    $category_label = isset($categories[$expense->category]) ? $categories[$expense->category] : $expense->category;
                    ?>
                    <tr>
                        <td><?php echo $expense->id; ?></td>
                        <td><?php echo RPOS_Timezone::format($expense->expense_date, 'Y-m-d H:i'); ?></td>
                        <td><?php echo esc_html($expense->cashier_name); ?></td>
                        <td>
                            <span style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html($category_label); ?>
                            </span>
                        </td>
                        <td style="font-weight: bold;">Rs <?php echo number_format($expense->amount_rs, 2); ?></td>
                        <td><?php echo esc_html($expense->description); ?></td>
                        <td><?php echo $expense->rider_name ? esc_html($expense->rider_name) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f9f9f9; font-weight: bold;">
                <td colspan="4" style="text-align: right;"><?php _e('Total:', 'restaurant-pos'); ?></td>
                <td>Rs <?php echo number_format($total_amount, 2); ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    
    <?php if ($total_count > $per_page): ?>
        <!-- Pagination -->
        <div style="margin-top: 20px; text-align: center;">
            <?php
            $total_pages = ceil($total_count / $per_page);
            $base_url = remove_query_arg('paged');
            
            if ($paged > 1) {
                echo '<a href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '" class="button">« ' . __('Previous', 'restaurant-pos') . '</a> ';
            }
            
            echo '<span style="margin: 0 10px;">' . sprintf(__('Page %d of %d', 'restaurant-pos'), $paged, $total_pages) . '</span>';
            
            if ($paged < $total_pages) {
                echo ' <a href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '" class="button">' . __('Next', 'restaurant-pos') . ' »</a>';
            }
            ?>
        </div>
    <?php endif; ?>
</div>
