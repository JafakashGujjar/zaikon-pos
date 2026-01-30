<?php
/**
 * Zaikon POS Frontend Template
 * Clean template without WordPress admin bar/menu
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get the requested page
$page = get_query_var('zaikon_pos_page');
$current_user = wp_get_current_user();
$user_name = $current_user->display_name;
$user_roles = (array) $current_user->roles;

// Check if this is an operational screen (POS or KDS) - these need clean, focused UI without header
$is_operational_screen = in_array($page, array('pos', 'kds'));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - <?php echo esc_html(ucfirst($page)); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f7;
            overflow-x: hidden;
        }
        
        /* Hide WordPress admin bar */
        #wpadminbar {
            display: none !important;
        }
        
        html {
            margin-top: 0 !important;
        }
        
        /* Header Navigation - only for dashboard/non-operational pages */
        .zaikon-frontend-header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .zaikon-frontend-logo {
            font-size: 20px;
            font-weight: 700;
            color: #f97316;
            text-decoration: none;
        }
        
        .zaikon-frontend-nav {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .zaikon-nav-item {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: #6b7280;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .zaikon-nav-item:hover {
            background: #f3f4f6;
            color: #1f2937;
        }
        
        .zaikon-nav-item.active {
            background: #fef3c7;
            color: #f97316;
        }
        
        .zaikon-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .zaikon-user-name {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .zaikon-logout-btn {
            padding: 6px 12px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .zaikon-logout-btn:hover {
            background: #b91c1c;
        }
        
        /* Main Content */
        .zaikon-frontend-content {
            width: 100%;
            min-height: calc(100vh - 60px);
        }
        
        /* POS and KDS need full viewport height (no header) */
        .zaikon-frontend-content.full-height {
            height: 100vh;
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .zaikon-frontend-nav {
                display: none; /* Hide nav on mobile, show hamburger menu instead */
            }
        }
    </style>
</head>
<body>
    <?php if (!$is_operational_screen): ?>
    <!-- Header - only shown for dashboard/non-operational pages -->
    <header class="zaikon-frontend-header">
        <a href="<?php echo home_url('/zaikon-pos/'); ?>" class="zaikon-frontend-logo">
            <?php echo esc_html(get_bloginfo('name')); ?> POS
        </a>
        
        <nav class="zaikon-frontend-nav">
            <?php
            // Show navigation based on user role (using pre-fetched roles)
            if (in_array('administrator', $user_roles) || in_array('restaurant_admin', $user_roles) || in_array('cashier', $user_roles)) {
                ?>
                <a href="<?php echo home_url('/zaikon-pos/'); ?>" 
                   class="zaikon-nav-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                    Dashboard
                </a>
                <a href="<?php echo home_url('/pos/'); ?>" 
                   class="zaikon-nav-item <?php echo $page === 'pos' ? 'active' : ''; ?>">
                    POS
                </a>
                <?php
            }
            
            if (in_array('administrator', $user_roles) || in_array('restaurant_admin', $user_roles) || in_array('kitchen_staff', $user_roles)) {
                ?>
                <a href="<?php echo home_url('/kitchen/'); ?>" 
                   class="zaikon-nav-item <?php echo $page === 'kds' ? 'active' : ''; ?>">
                    Kitchen Display
                </a>
                <?php
            }
            
            if (in_array('delivery_rider', $user_roles)) {
                ?>
                <a href="<?php echo home_url('/zaikon-pos/deliveries/'); ?>" 
                   class="zaikon-nav-item <?php echo $page === 'deliveries' ? 'active' : ''; ?>">
                    My Deliveries
                </a>
                <?php
            }
            ?>
        </nav>
        
        <div style="display: flex; gap: 12px; align-items: center;">
            <div class="zaikon-user-info">
                <span class="zaikon-user-name"><?php echo esc_html($user_name); ?></span>
            </div>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="zaikon-logout-btn">
                Logout
            </a>
        </div>
    </header>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="zaikon-frontend-content <?php echo $is_operational_screen ? 'full-height' : ''; ?>">
        <?php
        // Load the appropriate screen based on page
        switch ($page) {
            case 'pos':
                include RPOS_PLUGIN_DIR . 'includes/admin/pos.php';
                break;
                
            case 'kds':
                include RPOS_PLUGIN_DIR . 'includes/admin/kds.php';
                break;
                
            case 'deliveries':
                include RPOS_PLUGIN_DIR . 'includes/admin/rider-deliveries.php';
                break;
                
            case 'dashboard':
            default:
                include RPOS_PLUGIN_DIR . 'includes/admin/dashboard.php';
                break;
        }
        ?>
    </main>
    
    <?php wp_footer(); ?>
</body>
</html>
