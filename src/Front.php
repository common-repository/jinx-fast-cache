<?php

  namespace Jinx\FastCache;
  
  abstract class Front
  {
    
    // List of injections for the called URL
    public static $injections = [];
    
    // List of tags
    public static $tags = [];
    
    // Duration of cache
    public static $duration = null;
    
    /**
     * Init the front
     */
    public static function init()
    {
      
      add_action('template_redirect', [__CLASS__, 'capture'], PHP_INT_MAX);
      
      add_filter('jinx_fast_cache_active', function($cache) {

        if ($cache === true) {
          
          $disabled = null;
          
          // disable caching if
          // * in maintenance mode
          // * admin bar is showing
          // * is preview
          // * is user logged in
          if (wp_is_maintenance_mode() || is_admin_bar_showing() || is_preview() || is_user_logged_in()) {
            $disabled = true;
          } elseif (is_404()) {
            $disabled = apply_filters('jinx_fast_cache_ignore_404', true);
          } else {
          
            $metaKey = Helper::getDisabledMetaKey();

            $object = get_queried_object();

            if (is_a($object, 'WP_Post')) {
              $disabled = (bool) get_post_meta($object->ID, $metaKey, true);
            } elseif (is_a($object, 'WP_Term')) {
              $disabled = (bool) get_term_meta($object->term_id, $metaKey, true); 
            }
          
          }
          
          if (is_bool($disabled)) {   
            return !$disabled;
          }
          
        }

        return $cache;

      });
      
      // enqueue inject script only if injects exist
      add_action('wp_footer', function() {
        
        if (!empty(self::$injections)) {

          wp_enqueue_script('jinx-fast-cache', plugin_dir_url(__FILE__).'../assets/js/jinx-fast-cache.js', [], null, true);
          wp_localize_script('jinx-fast-cache', 'jinx_fast_cache', [
            'ajax_url' => admin_url('admin-ajax.php')
          ]);
          
        }
              
      }, PHP_INT_MIN); 
      
      add_action('wp_ajax_jinx-fast-cache-inject', [__CLASS__, 'ajaxInject']);
      add_action('wp_ajax_nopriv_jinx-fast-cache-inject', [__CLASS__, 'ajaxInject']);
      
      add_action('jinx_fast_cache_inject', function($function, array $args = [], $placeholder = null) {
        echo self::inject($function, $args, $placeholder);
      }, 10, 3);

      add_action('jinx_fast_cache_inject_template', function(string $template, $placeholder = null) {
        echo self::inject('get_template_part', [$template], $placeholder);
      }, 10, 2);
      
      add_filter('jinx_fast_cache_output', function($html) {
        
        if (apply_filters('jinx_fast_cache_minify', true)) {
          $html = Helper::minify($html);
        }
        
        $date = (new \DateTime)->format('c');
        
        $html .= PHP_EOL."<!-- Cached by Jinx Fast-Cache - https://jinx-digital.com - Last modified: {$date} -->";
        
        return $html;
        
      }); 
      
      add_shortcode('jinx_fast_cache_inject', function($attr, $content) {
        return self::inject('do_shortcode', [$content], $attr['placeholder'] ?? null);
      });
      
      add_action('jinx_fast_cache', function($attr) {
        
        $attr = shortcode_atts([
          'tags' => [],
          'duration' => null
        ], $attr);
                  
        if (is_string($attr['tags'])) {
          $attr['tags'] = explode(',', $attr['tags']);
          $attr['tags'] = array_map('trim', $attr['tags']);
        }
        
        self::$tags = array_merge(self::$tags, array_filter($attr['tags']));
        self::$duration = $attr['duration'];
        
      });
      
      add_shortcode('jinx_fast_cache', function($attr, $content) {
        do_action('jinx_fast_cache', $attr); 
        return null;          
      });
      
    }
    
    /**
     * Call an injection
     */
    public static function ajaxInject()
    {
      
      if (isset($_GET['path']) && isset($_GET['id'])) {

        $path = strip_tags(sanitize_textarea_field($_GET['path']));
        $id = intval($_GET['id'])-1;

        $files = Helper::getCacheFiles($path);
        $inject = json_decode(file_get_contents($files['json']), true);

        if (isset($inject[$id])) {
          
          $function = array_shift($inject[$id]);
          
          echo self::callInject($function, $inject[$id]);
          
        }
      
      }

      wp_die();
      
    }
    
    /**
     * Call the inject and return the result
     * 
     * @param string $function
     * @param array $args
     * @return string|null
     */
    protected static function callInject(string $function, array $args) : ?string
    {
      
      // check if function is a static method
      if (is_string($function) && strpos($function, '::') !== false) {
        $function = explode('::', $function);
      }

      // if function is an array, use method_exists
      if (is_array($function)) {

        list($object, $method) = $function;
        $exists = method_exists($object, $method);

      // otherwise use function_exists
      } else {
        $exists = function_exists($function);
      }

      if ($exists) {
        return call_user_func_array($function, $args);
      }
      
      return null;
      
    }
    
    /**
     * Create an injection and return the injection wrapper
     * 
     * @param string|array $function
     * @param array $args
     * @param string $placeholder
     * @return string
     */
    protected static function inject($function, array $args = [], string $placeholder = null) : string
    {
      
      if (apply_filters('jinx_fast_cache_active', true)) {
      
        array_unshift($args, $function);

        self::$injections[] = $args;

        $path = Helper::getCachePath(true);

        $id = count(self::$injections);

        return '<span class="jinx-fast-cache-inject" data-id="'.$id.'" data-path="'.$path.'">'.$placeholder.'</span>';
      
      } else {
        
        return self::callInject($function, $args);
        
      }

    }
    
    /**
     * Capture the output of WordPress
     */
    public static function capture()
    {
                        
      // check if the request should be cached
      if (apply_filters('jinx_fast_cache_active', true)) {
        
        // start output buffer
        ob_start(function($html) {
          
          // modify the output for the cache
          $html = apply_filters('jinx_fast_cache_output', $html);
          if (!empty($html)) {
            
            $path = Helper::getCachePath();

            // create path if it does not exist
            wp_mkdir_p($path);
            
            // write HTML into index.html
            file_put_contents($path.'/index.html', $html);
            
            // write injections into index.json
            file_put_contents($path.'/index.json', json_encode(self::$injections));
            
            self::insertTags();
            self::setExpiry();
            
            return $html;
          
          }

        });

      }
         
    }
    
    /**
     * Insert tags for URL into the DB table
     * 
     * @global type $wpdb
     */
    protected static function insertTags()
    {
      
      global $wpdb;
      
      $url = Helper::getUrl();
      
      $tags = array_unique(self::$tags);
      foreach ($tags as $tag) {
        
        $wpdb->insert(Helper::getTable('tags'), [
          'url' => $url,
          'tag' => $tag
        ]);
        
      }
      
    }
    
    /**
     * Set the URLs cache expiry date
     * 
     * @global \Jinx\FastCache\type $wpdb
     */
    protected static function setExpiry()
    {
      
      global $wpdb;
      
      $duration = apply_filters('jinx_fast_cache_duration', self::$duration);
      if (isset($duration)) {
        
        $url = Helper::getUrl();
             
        $expire = new \DateTime('now');

        if (is_numeric($duration)) {
          $duration .= ' seconds';
        }
                
        $expire->modify("+ {$duration}");
        
        $wpdb->insert(Helper::getTable('expiry'), [
          'url' => $url,
          'expire' => $expire->getTimestamp()
        ]); 
        
      }
      
    }
    
  }