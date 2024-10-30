<?php

  namespace Jinx\FastCache;
  
  abstract class Helper
  {
    
    /**
     * Create the tablename for the plugin
     * 
     * @global type $wpdb
     * @param string $table
     * @return string
     */
    public static function getTable(string $table) : string
    {
      
      global $wpdb;
      
      return $wpdb->prefix.'jinx_fast_cache_'.$table;
      
    }
    
    /**
     * Get the disabled meta key
     * 
     * @return string
     */
    public static function getDisabledMetaKey() : string
    {
      return '_jinx-fast-cache-disable';
    }
    
    /**
     * Get all post types which should be cachable
     * 
     * @return array
     */
    public static function getPostTypes() : array
    {
      
      $postTypes = get_post_types([
        'publicly_queryable' => true
      ]);
      
      $postTypes[] = 'page';
      
      return apply_filters('jinx_fast_cache_post_types', array_values($postTypes));
      
    }
    
    /**
     * Get all posts from a single or multiple post types
     * 
     * @param string|array $postTypes
     * @return array
     */
    public static function getPosts($postTypes) : array
    {
      
      $posts = get_posts([
        'post_type' => $postTypes,
        'posts_per_page' => -1
      ]);
      
      return apply_filters('jinx_fast_cache_posts', $posts);
      
    }
    
    /**
     * Get all taxonomies which should be cachable
     * 
     * @return array
     */
    public static function getTaxonomies() : array
    {
      
      $taxonomies = get_taxonomies([
        'publicly_queryable' => true
      ]);
      
      return apply_filters('jinx_fast_cache_taxonomies', array_values($taxonomies));
      
    }
    
    /**
     * Get all terms from a single or multiple taxonomies
     * 
     * @param string|array $taxonomies
     * @return array
     */
    public static function getTerms($taxonomies) : array
    {
      
      $terms = get_terms([
        'taxonomy' => $taxonomies,
        'hide_empty' => false
      ]);
      
      return apply_filters('jinx_fast_cache_terms', $terms);
      
    }
    
    /**
     * Get the cache path of a specific path combination
     * 
     * @param bool $relative
     * @return string
     */
    public static function getCachePath(bool $relative = false) : string
    {
            
      $paths = [self::getUrl()];
      
      parse_str($_SERVER['QUERY_STRING'], $queryParams);
      
      $queryParams = apply_filters('jinx_fast_cache_query_params', $queryParams);
      if (!empty($queryParams)) {
        $paths[] = http_build_query($queryParams);
      }
      
      if ($relative === false) {
        array_unshift($paths, Plugin::$cachePath);
      }
      
      foreach ($paths as &$path) {
        $path = ltrim($path, '/'); 
      }
            
      return implode('/', array_filter($paths));
      
    }
    
    public static function getCacheSize() : int
    {
      
      $size = 0;

      $path = realpath(Plugin::$cachePath);

      if (file_exists($path) && $path !== false && !empty($path)) {

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object) {
          $size += $object->getSize();
        }

      }

      return $size;
      
    }
    
    public static function formatSize(int $bytes) : string
    {

      $s = ['b', 'Kb', 'Mb', 'Gb'];
      
      if ($bytes > 0) {
        
        $e = floor(log($bytes)/log(1024));
        $v = $bytes/pow(1024, floor($e));
        
      }

      return sprintf('%.2f '.$s[$e ?? 0], $v ?? 0);
      
    }
    
    /**
     * Get the cache files (HTML and JSON)
     * 
     * @param string $url
     * @return array
     */
    public static function getCacheFiles(string $url) : array
    {
      
      $path = Url::normalize($url);
      
      return [
        'html' => Plugin::$cachePath.'/'.$path.'/index.html',
        'json' => Plugin::$cachePath.'/'.$path.'/index.json'
      ];
          
    }
    
    /**
     * Minify HTML for the cache
     * 
     * @param string $html
     * @return string
     */
    public static function minify(string $html) : string
    {
      
      $search = [
        '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
        '/[^\S ]+\</s',     // strip whitespaces before tags, except space
        '/(\s)+/s',         // shorten multiple whitespace sequences
        '/<!--(.|\s)*?-->/' // Remove HTML comments
      ];

      $replace = [
        '>',
        '<',
        '\\1',
        ''
      ];
            
      return trim(preg_replace('/>\s+</', '><', preg_replace($search, $replace, $html)));
      
    }
    
    public static function getUrl() : string
    {
      
      global $wp;
      
      return $_SERVER['HTTP_HOST'].'/'.$wp->request;
      
    }
    
  }