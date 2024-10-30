<?php

  namespace Jinx\FastCache;

  abstract class Url
  {
    
    /**
     * Get single URL of specific element
     * 
     * @param string $element
     * @param int $id
     * @return ?string
     */
    public static function getOne(string $element, int $id) : ?string
    {
      
      switch ($element) {
        // single post
        case 'post_type':
          return get_permalink($id);
        // single term
        case 'taxonomy':
          return get_term_link(get_term($id)); 
      }
      
      return null;
      
    }
    
    /**
     * Get multiple URLs of specific element and type
     * 
     * @param string $element
     * @param string $type
     * @return array
     */
    public static function getMultiple(string $element, string $type) : array
    {
      
      $urls = [];
      
      switch ($element) {
        // all posts of post type
        case 'post_type':

          $posts = Helper::getPosts($type);
          foreach ($posts as $post) {
            $urls[] = get_permalink($post);
          }

        break;
        // all terms of taxonomy
        case 'taxonomy':

          $terms = Helper::getTerms($type);
          foreach ($terms as $term) {
            $urls[] = get_term_link($term);
          }

        break;
      }
          
      return $urls;
      
    }
    
    /**
     * Get all relevant URLs
     * 
     * @return array
     */
    public static function getAll() : array
    {
      
      $urls = [];
      
      $urls[] = home_url();
      
      $postTypes = Helper::getPostTypes();
      $posts = Helper::getPosts($postTypes);

      foreach ($posts as $post) {
        $urls[] = get_permalink($post);
      }

      $taxonomies = Helper::getTaxonomies();
      $terms = Helper::getTerms($taxonomies);

      foreach ($terms as $term) {
        $urls[] = get_term_link($term);
      }
      
      return $urls;
      
    }
    
    /**
     * Get the status of an URL
     * 
     * @param string $url
     * @return string
     */
    public static function getStatus(string $url) : string
    {
      
      if (Queue::has($url)) {
        return 'warming';
      }
            
      if (self::isCached($url)) {
        return 'cached';
      }
      
      return 'not cached';
      
    }
    
    /**
     * Get the timestamp of a cached file
     * 
     * @param string $url
     * @return int|null
     */
    public static function getTimestamp(string $url) : ?int
    {
      $files = Helper::getCacheFiles($url);
      return file_exists($files['html']) ? filemtime($files['html']) : null;
    }
    
    /**
     * Get the size of a cached file
     * 
     * @param string $url
     * @return int|null
     */
    public static function getSize(string $url) : ?int
    {
      $files = Helper::getCacheFiles($url);
      return file_exists($files['html']) ? filesize($files['html'])+filesize($files['json']) : null;
    }
    
    /**
     * Check if an URL is already cached
     * 
     * @param string $url
     * @return bool
     */
    public static function isCached(string $url) : bool
    {
      $files = Helper::getCacheFiles($url);
      return file_exists($files['html']);
    }
    
    /**
     * Send requests to a set of URLs
     * 
     * @param array $urls
     */
    public static function load(array $urls)
    {

      foreach ($urls as $url) {
        wp_remote_get($url);
      }
      
    }
    
    /**
     * Normale a URL by removing the protocol
     * 
     * @param string $url
     * @return string
     */
    public static function normalize(string $url) : string
    {
      
      $host = parse_url($url, PHP_URL_HOST); 
      $i = empty($host) ? false : strpos($url, $host);
      
      if ($i !== false) {
        $url = trim(substr($url, $i), '/');
      }
      
      return $url;
      
    }
    
  }