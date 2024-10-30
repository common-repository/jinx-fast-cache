<?php

  namespace Jinx\FastCache;
  
  abstract class Queue
  {
    
    protected static $option = 'jinx_fast_cache_queue';
    
    protected static $recurrence = 'jinx-fast-cache-queue';
    
    /**
     * Create the cronjob
     */
    public static function init()
    {
  
      add_filter('cron_schedules', function($schedules) {

        $schedules[self::$recurrence] = [
          'interval' => apply_filters('jinx_fast_cache_queue_interval', 60),
          'display' => Plugin::t('Jinx Fast-Cache - Queue')
        ];

        return $schedules;

      });
      
      add_action('jinx_fast_cache_queue', [__CLASS__, 'process']);

      if (!wp_next_scheduled('jinx_fast_cache_queue')) {
        wp_schedule_event(time(), self::$recurrence, 'jinx_fast_cache_queue');
      }
      
    }
    
    /**
     * Checks if an URL is already in the queue
     * 
     * @param string $url
     * @return bool
     */
    public static function has(string $url) : bool
    {
      return in_array($url, self::get());
    }
    
    /**
     * Gets all URLs from the queue
     * 
     * @return type
     */
    public static function get() : array
    {
      return get_option(self::$option, []);
    }
    
    /**
     * Adds URLs to the queue
     * 
     * @param array $urls
     * @return bool
     */
    public static function add(array $urls) : bool
    { 
      return self::set(array_merge(self::get(), $urls)); 
    }
    
    /**
     * Set all URLs in the queue
     * 
     * @param array $urls
     * @return bool
     */
    public static function set(array $urls) : bool
    {
      return update_option(self::$option, array_unique($urls));
    }
    
    /**
     * Process the queue
     */
    public static function process()
    {
      
      $urls = self::get();
      
      $_urls = [];
      
      $size = apply_filters('jinx_fast_cache_queue_size', 10);
      if ($size > 0) {
      
        $_urls = array_slice($urls, $size);
        $urls = array_slice($urls, 0, $size);

      }
      
      self::set($_urls);
            
      Url::load($urls);
      
    }
    
  }