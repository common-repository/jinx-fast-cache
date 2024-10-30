<?php

  namespace Jinx\FastCache;
  
  use WP_Filesystem_Direct;
  
  abstract class Service
  {
    
    /**
     * Flush a set of URLs
     * 
     * @param array $urls
     */
    public static function flush(array $urls = [])
    {
            
      $urls = apply_filters('jinx_fast_cache_flush', $urls);
      if (empty($urls)) {
                        
        (new WP_Filesystem_Direct(null))->rmdir(Plugin::$cachePath, true);
                
      } else {
        
        $urls = self::getTaggedUrls($urls);
        foreach ($urls as $url) {
          
          $files = Helper::getCacheFiles($url);
          foreach ($files as $file) {
            
            if (file_exists($file)) {
              unlink($file);
            } 
            
          }
          
        }
        
      }
      
      self::cleanupFilesystem(Plugin::$cachePath);
      self::cleanupTables($urls);
      
      wp_mkdir_p(Plugin::$cachePath);
      
    }
    
    protected static function getTaggedUrls(array $urls) : array
    {
      
      global $wpdb;
            
      $taggedUrls = $urls;
      
      $table = Helper::getTable('tags');
      
      foreach ($urls as $url) {
        
        $url = Url::normalize($url);
        
        $tags = $wpdb->get_col("SELECT `tag` FROM {$table} WHERE `url` = '{$url}';");      
        foreach ($tags as $tag) {
          
          $results = $wpdb->get_col("SELECT `url` FROM {$table} WHERE `tag` = '{$tag}';");
                    
          $taggedUrls = array_merge($taggedUrls, $results);
          
        }
        
      }
      
      return array_unique($taggedUrls);
      
    }
    
    /**
     * Warm a set of URLs, put them into the queue or do it immediately
     * 
     * @param array $urls
     * @param bool $queue
     */
    public static function warm(array $urls = [], $queue = true)
    {
      
      $urls = apply_filters('jinx_fast_cache_warm', $urls);
      
      if ($queue === true) {
        Queue::add($urls);
      } else {
        Url::load($urls);
      }
      
    }
    
    /**
     * Refresh a set of URLs. They will be flushed and warmed
     * 
     * @param array $urls
     */
    public static function refresh(array $urls = [])
    { 
      self::flush($urls);
      self::warm($urls);
    }
    
    /**
     * Removes all empty directory in the cache directory
     * 
     * @param string $path
     * @return bool
     */
    protected static function cleanupFilesystem(string $path) : bool
    {

      $empty = true;
      
      foreach (glob($path.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) as $dir) {
        $empty &= self::cleanupFilesystem($dir);
      }
      
      return $empty && @rmdir($path);
      
    }
    
    /**
     * Removes all tags from the database
     * 
     * @param array $urls
     */
    protected static function cleanupTables(array $urls)
    {
      
      global $wpdb;
      
      $tagsTable = Helper::getTable('tags');
      $expiryTable = Helper::getTable('expiry');
      
      if (empty($urls)) {
        
        $wpdb->query("TRUNCATE TABLE {$tagsTable}");
        $wpdb->query("TRUNCATE TABLE {$expiryTable}");
        
      } else {
        
        foreach ($urls as $url) {
          
          $url = Url::normalize($url);
                    
          $wpdb->delete($tagsTable, [
            'url' => $url
          ]);
          
          $wpdb->delete($expiryTable, [
            'url' => $url
          ]);
          
        }
        
      }
      
    }
    
  }