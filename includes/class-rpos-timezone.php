<?php
/**
 * Timezone Helper Class for Restaurant POS
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Timezone {
    
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
            $dt = new DateTime($datetime, new DateTimeZone('UTC'));
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
}
