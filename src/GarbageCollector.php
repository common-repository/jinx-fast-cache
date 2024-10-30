<?php

  namespace Jinx\FastCache;
  
  abstract class GarbageCollector
  {
        
    protected static $recurrence = 'jinx-fast-cache-gc';
    
    /**
     * Create the cronjob
     */
    public static function init()
    {
  
      add_filter('cron_schedules', function($schedules) {

        $schedules[self::$recurrence] = [
          'interval' => apply_filters('jinx_fast_cache_gc_interval', 60),
          'display' => Plugin::t('Jinx Fast-Cache - GC')
        ];

        return $schedules;

      });
      
      add_action('jinx_fast_cache_gc', [__CLASS__, 'process']);

      if (!wp_next_scheduled('jinx_fast_cache_gc')) {
        wp_schedule_event(time(), self::$recurrence, 'jinx_fast_cache_gc');
      }
      
    }
    
    /**
     * Process the GC
     */
    public static function process()
    {
      
      global $wpdb;
        
      $table = Helper::getTable('expiry');
                      
      $urls = $wpdb->get_col("SELECT `url` FROM {$table} WHERE `expire` <= NOW();");
      if (!empty($urls)) {
        Service::flush($urls);
      }
      
    }
    
  }