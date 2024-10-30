<?php

  namespace Jinx\FastCache;
  
  use WP_Filesystem_Direct;
  
  abstract class Plugin
  {
    
    // begin and end tags for htaccess
    protected static $begin = '# BEGIN Jinx Fast-Cache';
    protected static $end = '# END Jinx Fast-Cache';
    
    // the path where the cache files will be stored
    public static $cachePath = WP_CONTENT_DIR.'/jinx-fast-cache';
    
    // text domain
    protected static $textDomain = 'jinx-fast-cache';
    
    /**
     * Translate a string from the plugin
     * 
     * @param string $string
     * @param array $values
     * @return string
     */
    public static function t(string $string, array $values = []) : string
    {
      
      $translation = __($string, self::$textDomain);
      
      return empty($values) ? $translation : vsprintf($translation, $values);
      
    }
    
    /**
     * Add the rewrite rules on activation
     */
    public static function activate()
    {
      
      $root = parse_url(home_url());
      if (isset($root['path'])) {
        $base = trailingslashit($root['path']);
      } else {
        $base = '/';
      }
      
      $wpContent = str_replace(home_url('/'), '', content_url('jinx-fast-cache'));
      
      $rules = [
        self::$begin,
        'RewriteEngine On',
        "RewriteBase {$base}",
        "RewriteCond %{DOCUMENT_ROOT}{$base}{$wpContent}/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html -s",
        'RewriteCond %{REQUEST_METHOD} GET',
        "RewriteRule .* {$base}{$wpContent}/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html [L]",
        self::$end
      ]; 
      
      $htaccess = self::getHtaccess();
      $htaccess = implode(PHP_EOL, $rules).PHP_EOL.PHP_EOL.$htaccess;
      
      self::setHtaccess($htaccess);
      self::createTables();
      
    }
    
    /**
     * Remove the rewrite rules on deactivation
     */
    public static function deactivate()
    {
      
      $htaccess = self::getHtaccess();

      $begin = strpos($htaccess, self::$begin);
      $end = strpos($htaccess, self::$end);
      
      if ($begin !== false && $end !== false) {
        
        $remove = substr($htaccess, $begin, ($end + strlen(self::$end)) - $begin);
        $htaccess = str_replace($remove, '', $htaccess);
        
        self::setHtaccess($htaccess);
                
      }
      
    }
    
    /**
     * Trigger deactivation and remove the whole cache
     */
    public static function uninstall()
    {
      
      self::deactivate();    
      
      (new WP_Filesystem_Direct(null))->rmdir(self::$cachePath, true);
      
      // delete tables
      
    }
    
    /**
     * Read the content from htaccess
     * 
     * @return string
     */
    protected static function getHtaccess() : string
    {
      return trim(file_get_contents(ABSPATH.'.htaccess'));
    }
    
    /**
     * Write the content to htaccess
     * 
     * @param string $content
     * @return bool
     */
    protected static function setHtaccess(string $content) : bool
    {
      return (bool) file_put_contents(ABSPATH.'.htaccess', trim($content));
    }
    
    /**
     * Create relevant tables
     */
    protected static function createTables()
    {
                  
      $table = Helper::getTable('tags');
      
      $sql = "CREATE TABLE {$table} (
        `url` VARCHAR(255) NOT NULL,
        `tag` VARCHAR(32) NOT NULL,
        PRIMARY KEY (`url`,`tag`))";
      
      \maybe_create_table($table, $sql);
      
      $table = Helper::getTable('expiry');
      
      $sql = "CREATE TABLE {$table} (
        `url` VARCHAR(255) NOT NULL,
        `expire` INT(11) NOT NULL,
        PRIMARY KEY (`url`))";
      
      \maybe_create_table($table, $sql);
      
    }
    
  }