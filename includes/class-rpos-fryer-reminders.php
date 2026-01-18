<?php
/**
 * Fryer Reminders Class
 * Handles reminder/notification logic for oil changes
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPOS_Fryer_Reminders {
    
    /**
     * Check if batch should trigger a reminder
     */
    public static function should_remind($batch_id) {
        $batch = RPOS_Fryer_Oil_Batches::get($batch_id);
        
        if (!$batch || $batch->status !== 'active') {
            return false;
        }
        
        // Check count-based reminder
        if ($batch->current_usage_units >= $batch->target_usage_units) {
            return array(
                'type' => 'usage',
                'message' => 'Oil usage limit reached',
                'severity' => 'high'
            );
        }
        
        // Check time-based reminder
        $start = new DateTime($batch->oil_added_at);
        $now = RPOS_Timezone::now();
        $hours_elapsed = ($now->getTimestamp() - $start->getTimestamp()) / 3600;
        
        if ($hours_elapsed >= $batch->time_threshold_hours) {
            return array(
                'type' => 'time',
                'message' => 'Oil time threshold exceeded',
                'severity' => 'medium'
            );
        }
        
        // Check warning threshold (80% of target)
        $warning_threshold = $batch->target_usage_units * 0.8;
        if ($batch->current_usage_units >= $warning_threshold) {
            return array(
                'type' => 'warning',
                'message' => 'Approaching oil usage limit',
                'severity' => 'low'
            );
        }
        
        return false;
    }
    
    /**
     * Check batch status
     */
    public static function check_batch_status($batch_id) {
        return self::should_remind($batch_id);
    }
    
    /**
     * Get all active alerts
     */
    public static function get_active_alerts() {
        global $wpdb;
        
        $active_batches = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}rpos_fryer_oil_batches
             WHERE status = 'active'
             ORDER BY created_at DESC"
        );
        
        $alerts = array();
        
        foreach ($active_batches as $batch) {
            $reminder = self::should_remind($batch->id);
            
            if ($reminder) {
                $stats = RPOS_Fryer_Oil_Batches::get_usage_stats($batch->id);
                
                $alerts[] = array(
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->batch_name,
                    'fryer_id' => $batch->fryer_id,
                    'type' => $reminder['type'],
                    'severity' => $reminder['severity'],
                    'message' => $reminder['message'],
                    'current_usage' => $stats['total_units'],
                    'target_usage' => $batch->target_usage_units,
                    'usage_percentage' => $stats['usage_percentage'],
                    'time_elapsed_hours' => $stats['time_elapsed_hours'],
                    'time_threshold_hours' => $batch->time_threshold_hours,
                    'remaining_units' => $stats['remaining_units']
                );
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get alert message for display
     */
    public static function get_alert_message($alert) {
        $fryer_name = $alert['fryer_id'] ? 'Fryer #' . $alert['fryer_id'] : 'Default Fryer';
        
        switch ($alert['type']) {
            case 'usage':
                return sprintf(
                    '⚠️ %s: Oil has reached usage limit (%s/%s units). Please replace the oil.',
                    $fryer_name,
                    number_format($alert['current_usage'], 1),
                    number_format($alert['target_usage'], 1)
                );
            
            case 'time':
                return sprintf(
                    '⚠️ %s: Oil has exceeded time threshold (%s hours). Please replace the oil.',
                    $fryer_name,
                    number_format($alert['time_elapsed_hours'], 1)
                );
            
            case 'warning':
                return sprintf(
                    '⚠ %s: Oil usage is at %s%%. Consider replacing soon.',
                    $fryer_name,
                    number_format($alert['usage_percentage'], 1)
                );
            
            default:
                return '⚠️ Fryer oil needs attention.';
        }
    }
    
    /**
     * Get severity class for CSS styling
     */
    public static function get_severity_class($severity) {
        $classes = array(
            'high' => 'alert-danger',
            'medium' => 'alert-warning',
            'low' => 'alert-info'
        );
        
        return isset($classes[$severity]) ? $classes[$severity] : 'alert-info';
    }
}
