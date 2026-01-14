<?php
/**
 * Admin Notices Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Admin_Notices {
    
    protected static $_instance = null;
    
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function __construct() {
        add_action('admin_notices', array($this, 'check_database_schema'));
        add_action('admin_init', array($this, 'handle_migration_request'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts for admin notices
     */
    public function enqueue_scripts() {
        // Add inline script for AJAX migration
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.rpos-run-migration-btn').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $notice = $btn.closest('.notice');
                
                $btn.prop('disabled', true).text('Running Migration...');
                
                $.post(ajaxurl, {
                    action: 'rpos_run_database_migration',
                    nonce: '<?php echo wp_create_nonce('rpos_migration_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $notice.removeClass('notice-warning').addClass('notice-success');
                        $notice.html('<p><strong>Success!</strong> Database migration completed. Refresh the page to remove this notice.</p>');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $notice.removeClass('notice-warning').addClass('notice-error');
                        $notice.html('<p><strong>Error:</strong> ' + (response.data || 'Migration failed. Please check error logs.') + '</p>');
                        $btn.prop('disabled', false).text('Retry Migration');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Check if database schema is up to date
     */
    public function check_database_schema() {
        global $wpdb;
        
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Check if migration has been dismissed
        if (get_transient('rpos_migration_notice_dismissed')) {
            return;
        }
        
        // Check if delivery_instructions column exists
        $table_name = $wpdb->prefix . 'zaikon_deliveries';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return; // Table doesn't exist yet, likely fresh install
        }
        
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
            'delivery_instructions'
        ));
        
        if (empty($column_exists)) {
            // Column is missing, show warning
            ?>
            <div class="notice notice-warning is-dismissible rpos-migration-notice">
                <p>
                    <strong>Restaurant POS - Database Update Required</strong><br>
                    Your database schema is outdated. This may cause delivery orders to fail with the error "Failed to create delivery record".<br>
                    <button type="button" class="button button-primary rpos-run-migration-btn" style="margin-top: 10px;">
                        Run Database Migration Now
                    </button>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Handle manual migration request via admin_init (for non-AJAX)
     */
    public function handle_migration_request() {
        // Handle AJAX migration request
        add_action('wp_ajax_rpos_run_database_migration', array($this, 'ajax_run_migration'));
        
        // Handle non-AJAX migration request (if button clicked without JS)
        if (isset($_GET['rpos_run_migration']) && check_admin_referer('rpos_migration_nonce')) {
            RPOS_Install::migrate_rider_system();
            
            // Set success transient
            set_transient('rpos_migration_success', true, 10);
            
            // Redirect to remove query param
            wp_safe_redirect(remove_query_arg('rpos_run_migration'));
            exit;
        }
        
        // Show success notice if migration just completed
        if (get_transient('rpos_migration_success')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Success!</strong> Database migration completed successfully.</p>
                </div>
                <?php
            });
            delete_transient('rpos_migration_success');
        }
    }
    
    /**
     * Handle AJAX migration request
     */
    public function ajax_run_migration() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rpos_migration_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Run migration
        try {
            RPOS_Install::migrate_rider_system();
            
            // Mark migration as done
            update_option('rpos_rider_system_migration_done', true);
            
            // Dismiss notice
            set_transient('rpos_migration_notice_dismissed', true, YEAR_IN_SECONDS);
            
            wp_send_json_success('Migration completed successfully');
        } catch (Exception $e) {
            error_log('RPOS Migration Error: ' . $e->getMessage());
            wp_send_json_error('Migration failed: ' . $e->getMessage());
        }
    }
}
