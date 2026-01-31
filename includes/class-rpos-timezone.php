<?php
/**
 * Timezone Helper Class for Restaurant POS
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Timezone {
    
    /**
     * Cached UTC timezone instance
     */
    private static $utc_timezone = null;
    
    /**
     * Get UTC timezone (cached)
     * 
     * @return DateTimeZone UTC timezone object
     */
    private static function get_utc_timezone() {
        if (self::$utc_timezone === null) {
            self::$utc_timezone = new DateTimeZone('UTC');
        }
        return self::$utc_timezone;
    }
    
    /**
     * Get the configured timezone string
     */
    public static function get_timezone_string() {
        // 1) Get plugin timezone setting (defaults to Asia/Karachi)
        $tz = RPOS_Settings::get('pos_timezone', 'Asia/Karachi');
        
        // 2) If empty, fall back to WordPress site timezone
        if (empty($tz)) {
            $tz = get_option('timezone_string');
        }
        
        // 3) If still empty (WP using UTC offset), try to get from gmt_offset
        if (empty($tz)) {
            $offset = get_option('gmt_offset');
            if ($offset !== false && $offset !== '') {
                $hours = (int) $offset;
                $minutes = abs(($offset - $hours) * 60);
                $sign = $offset >= 0 ? '+' : '-';
                $tz = sprintf('Etc/GMT%s%d', $sign === '+' ? '-' : '+', abs($hours));
            }
        }
        
        // 4) Final fallback to Asia/Karachi (default timezone for the system)
        if (empty($tz)) {
            $tz = 'Asia/Karachi';
        }
        
        return $tz;
    }
    
    /**
     * Get DateTimeZone object
     */
    public static function get_timezone() {
        return new DateTimeZone(self::get_timezone_string());
    }
    
    /**
     * Get current DateTime in plugin timezone
     */
    public static function now() {
        return new DateTime('now', self::get_timezone());
    }
    
    /**
     * Get current UTC DateTime for database storage
     * 
     * Returns current time in UTC timezone as DateTime object.
     * Use this when storing timestamps in the database to ensure consistency.
     * 
     * @return DateTime DateTime object in UTC timezone
     */
    public static function now_utc() {
        return new DateTime('now', self::get_utc_timezone());
    }
    
    /**
     * Get current UTC time as MySQL datetime string for database storage
     * 
     * Returns a MySQL-compatible datetime string in UTC timezone.
     * Format: YYYY-MM-DD HH:MM:SS (e.g., '2024-01-17 14:30:00')
     * 
     * @return string MySQL datetime string in UTC format
     */
    public static function current_utc_mysql() {
        return self::now_utc()->format('Y-m-d H:i:s');
    }
    
    /**
     * Convert a timestamp or datetime string to plugin timezone
     * 
     * IMPORTANT: This method assumes datetime strings from the database are in UTC.
     * If your database stores timestamps in a different timezone, the display will be incorrect.
     * 
     * @param mixed $datetime Unix timestamp, datetime string, or DateTime object
     * @return DateTime DateTime object in the plugin's configured timezone
     */
    public static function convert($datetime) {
        if ($datetime instanceof DateTime) {
            $dt = clone $datetime;
        } elseif (is_numeric($datetime)) {
            // Unix timestamp - timezone-agnostic
            $dt = new DateTime('@' . $datetime);
        } else {
            // MySQL datetime string - ASSUMES UTC storage
            // This is the standard for WordPress and most web applications
            $dt = new DateTime($datetime, self::get_utc_timezone());
        }
        
        $dt->setTimezone(self::get_timezone());
        return $dt;
    }
    
    /**
     * Format a datetime using plugin timezone and date format setting
     * 
     * @param mixed $datetime Unix timestamp, datetime string, or DateTime object
     * @param string $format Optional format override (uses plugin date_format setting if not provided)
     * @return string
     */
    public static function format($datetime, $format = null) {
        if ($format === null) {
            $format = RPOS_Settings::get('date_format', 'Y-m-d H:i:s');
        }
        
        $dt = self::convert($datetime);
        return $dt->format($format);
    }
    
    /**
     * Get timezone offset in minutes (for JavaScript)
     */
    public static function get_offset_minutes() {
        $tz = self::get_timezone();
        $now = new DateTime('now', $tz);
        return $tz->getOffset($now) / 60;
    }
    
    /**
     * Get timezone offset in hours (for display)
     */
    public static function get_offset_hours() {
        return self::get_offset_minutes() / 60;
    }
    
    /**
     * Debug helper: Log timing information for an order
     * 
     * This is useful for debugging timezone-related issues with order tracking.
     * Call this from order tracking code when investigating timer problems.
     * 
     * @param int $order_id Order ID to debug
     * @param string $context Optional context string to identify where this was called from
     * @return array Debug information array
     */
    public static function debug_order_timing($order_id, $context = 'debug') {
        global $wpdb;
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT id, order_number, order_status, created_at, confirmed_at, 
                    cooking_started_at, cooking_eta_minutes, 
                    ready_at, dispatched_at, delivery_eta_minutes
             FROM {$wpdb->prefix}zaikon_orders 
             WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            error_log("ZAIKON TIMING DEBUG [{$context}]: Order #{$order_id} not found");
            return null;
        }
        
        // Calculate current UTC timestamp using WordPress
        $current_utc = current_time('timestamp', true);
        $current_utc_mysql = gmdate('Y-m-d H:i:s', $current_utc);
        
        // Parse stored timestamps as UTC
        $cooking_start_ts = $order->cooking_started_at ? strtotime($order->cooking_started_at . ' UTC') : null;
        $ready_ts = $order->ready_at ? strtotime($order->ready_at . ' UTC') : null;
        $dispatched_ts = $order->dispatched_at ? strtotime($order->dispatched_at . ' UTC') : null;
        
        $debug = array(
            'context' => $context,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->order_status,
            'configured_timezone' => self::get_timezone_string(),
            'server_utc_now' => $current_utc_mysql,
            'server_utc_timestamp' => $current_utc,
            'timestamps' => array(
                'cooking_started_at' => $order->cooking_started_at,
                'cooking_started_ts' => $cooking_start_ts,
                'ready_at' => $order->ready_at,
                'ready_ts' => $ready_ts,
                'dispatched_at' => $order->dispatched_at,
                'dispatched_ts' => $dispatched_ts
            )
        );
        
        // Calculate cooking timer if in cooking status
        if ($order->order_status === 'cooking' && $cooking_start_ts) {
            $elapsed_seconds = $current_utc - $cooking_start_ts;
            $eta_minutes = $order->cooking_eta_minutes ?: 20;
            $remaining_seconds = ($eta_minutes * 60) - $elapsed_seconds;
            
            $debug['cooking_timer'] = array(
                'elapsed_minutes' => round($elapsed_seconds / 60, 2),
                'eta_minutes' => $eta_minutes,
                'remaining_minutes' => round($remaining_seconds / 60, 2),
                'is_overtime' => $remaining_seconds < 0
            );
        }
        
        // Calculate delivery timer if ready or dispatched
        if (in_array($order->order_status, array('ready', 'dispatched'))) {
            $delivery_start_ts = $dispatched_ts ?: $ready_ts;
            if ($delivery_start_ts) {
                $elapsed_seconds = $current_utc - $delivery_start_ts;
                $eta_minutes = $order->delivery_eta_minutes ?: 10;
                $remaining_seconds = ($eta_minutes * 60) - $elapsed_seconds;
                
                $debug['delivery_timer'] = array(
                    'start_source' => $dispatched_ts ? 'dispatched_at' : 'ready_at',
                    'elapsed_minutes' => round($elapsed_seconds / 60, 2),
                    'eta_minutes' => $eta_minutes,
                    'remaining_minutes' => round($remaining_seconds / 60, 2),
                    'is_overtime' => $remaining_seconds < 0
                );
            }
        }
        
        // Log to PHP error log
        error_log("ZAIKON TIMING DEBUG [{$context}]: " . wp_json_encode($debug, JSON_PRETTY_PRINT));
        
        return $debug;
    }
}
