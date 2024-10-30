<?php

  namespace Jinx\FastCache;
  
  abstract class Admin
  {
    
    protected static $actions = ['flush', 'warm', 'refresh'];
    
    /**
     * Init the admin
     */
    public static function init()
    {
      
      // execute the given action
      if (isset($_GET['jinx-fast-cache'])) {
        self::execute(sanitize_text_field($_GET['jinx-fast-cache'])); 
      }
      
      add_action('upgrader_process_complete', function() {
        
        if (apply_filters('jinx_fast_cache_refresh_on_upgrade', true)) {
          self::actionRefresh();
        }
        
      }, PHP_INT_MAX);
      
      add_action('admin_bar_menu', [__CLASS__, 'addAdminBar'], 100);
      
      self::handlePosts();
      self::handleTerms();
      
    }
    
    /**
     * Add the actions to the admin bar
     * 
     * @param $adminBar
     */
    public static function addAdminBar($adminBar)
    {
      
      global $current_screen;

      $postTypes = Helper::getPostTypes();
      $taxonomies = Helper::getTaxonomies();
      
      $size = Helper::getCacheSize();

      $adminBar->add_node([
        'id' => 'jinx-fast-cache',
        'title' => Plugin::t('&#9889; Jinx Fast-Cache (%s)', [
          Helper::formatSize($size)
        ]),
      ]);
      
      $nodes = self::createNodes();
      
      switch ($current_screen->base) {
        case 'edit':

          if (in_array($current_screen->post_type, $postTypes)) {

            $postTypeObject = get_post_type_object($current_screen->post_type);
                        
            $nodes = array_merge($nodes, self::createNodes('post-type', ['%s %s', $postTypeObject->label], [
              'post_type' => $current_screen->post_type
            ]));

          }

        break;
        case 'post':

          if (in_array($current_screen->post_type, $postTypes)) {

            global $post;
            
            if ($post->post_status === 'publish') {

              $nodes = array_merge($nodes, self::createNodes('post', ['%s Post # %d', $post->ID], [
                'post_type' => $post->post_type,
                $post->ID
              ]));
            
            }

          }

        break;
        case 'edit-tags':
          
          if (in_array($current_screen->taxonomy, $taxonomies)) {

            $taxonomy = get_taxonomy($current_screen->taxonomy);

            $nodes = array_merge($nodes, self::createNodes('taxonomy', ['%s %s', $taxonomy->label], [
              'taxonomy' => $current_screen->taxonomy
            ])); 
          
          }

        break;
        case 'term':
          
          if (in_array($current_screen->taxonomy, $taxonomies)) {
            
            $term = get_term(intval($_GET['tag_ID']), $current_screen->taxonomy);
                               
            $nodes = array_merge($nodes, self::createNodes('term', ['%s Term # %d', $term->term_id], [
              'taxonomy' => $term->taxonomy,
              $term->term_id
            ]));
            
          }
          
        break;
      }
      
      foreach ($nodes as $node) {
        $adminBar->add_node($node);
      }
      
    }
    
    /**
     * Create the nodes for the admin bar
     * 
     * @param string $suffix
     * @param string|array $title
     * @param array $args
     * @return array
     */
    protected static function createNodes(string $suffix = '', $title = '%s', array $args = []) : array
    {
      
      $nodes = [];
      
      if (is_array($title)) {
        $values = $title;
        $title = array_shift($values);
      } else {
        $values = [];
      }
      
      foreach (self::$actions as $action) {
        
        $query = $args;
        array_unshift($query, $action);
        
        $nodes[] = [
          'parent' => 'jinx-fast-cache',
          'id' => 'jinx-fast-cache-'.$action.(empty($suffix) ? '' : '-'.$suffix),
          'title' => Plugin::t($title, array_merge([ucfirst($action)], $values)),
          'href' => self::createUrl($query)
        ];

      }
      
      return $nodes;
      
    }
    
    /**
     * Add post columns and handle update behavior
     */
    protected static function handlePosts()
    {
      
      $postTypes = Helper::getPostTypes();
      
      foreach ($postTypes as $postType) {  
        add_filter($postType.'_row_actions', [__CLASS__, 'addPostRowActions'], 10, 2);
        add_filter('manage_'.$postType.'_posts_columns', [__CLASS__, 'addColumn'], PHP_INT_MAX);
        add_filter('manage_'.$postType.'_posts_custom_column', [__CLASS__, 'addPostColumnContent'], 10, 2);
      }
      
      // flush a posts before it will be deleted
      add_action('before_delete_post', function($pid, $post) use($postTypes) {
        
        if (in_array($post->post_type, $postTypes)) {
        
          $urls = [];
          $urls[] = get_permalink($pid);

          Service::flush($urls);
        
        }
        
      }, 10, 2);
      
      // refresh or flush a posts cache after it has been saved
      add_action('save_post', function($pid, $post) use($postTypes) {
        
        if (in_array($post->post_type, $postTypes)) {
          
          $urls = [];
          
          switch ($post->post_status) {
            // refresh on publish
            case 'publish':
              
              $urls[] = get_permalink($pid); 
              Service::refresh($urls);
              
            break;
            // flush on draft or trash
            case 'draft':
            case 'trash':
              
              $_post = clone $post;
              $_post->post_status = 'publish';
              
              $urls[] = get_permalink($_post);
              Service::flush($urls);
              
            break; 
          }  
          
        }
      
      }, 10, 2);
      
    }
    
    /**
     * Add term columns and handle update behavior
     */
    protected static function handleTerms()
    {
      
      $taxonomies = Helper::getTaxonomies();
      
      foreach ($taxonomies as $taxonomy) {
        add_filter($taxonomy.'_row_actions', [__CLASS__, 'addTermRowActions'], 10, 2); 
        add_filter('manage_edit-'.$taxonomy.'_columns', [__CLASS__, 'addColumn']);
        add_filter('manage_'.$taxonomy.'_custom_column', [__CLASS__, 'addTermColumnContent'], 10, 3);
      }
      
      // flush a posts before it will be deleted
      add_action('pre_delete_term', function($termId, $taxonomy) use($taxonomies) {
        
        if (in_array($taxonomy, $taxonomies)) {
        
          $urls = [];
          $urls[] = get_term_link($termId);

          Service::flush($urls);
        
        }
        
      }, 10, 2);
      
      // refresh or flush a posts cache after it has been saved
      add_action('saved_term', function($termId, $taxonomyId, $taxonomy) use($taxonomies) {
             
        if (in_array($taxonomy, $taxonomies)) {
        
          $urls = [];
          $urls[] = get_term_link($termId);

          Service::flush($urls);
                
        }
        
      }, 10, 3);
      
    }
    
    /**
     * Add the admin column
     * 
     * @param array $columns
     * @return array
     */
    public static function addColumn(array $columns) : array
    {
      return array_merge($columns, ['jinx-fast-cache' => '<span title="'.Plugin::t('Jinx Fast-Cache').'">&#9889;</span>']);
    }
    
    /**
     * Add post column content
     * 
     * @param string $key
     * @param int $pid
     */
    public static function addPostColumnContent(string $key, int $pid)
    {
      
      if ($key === 'jinx-fast-cache') {
        
        $post = get_post($pid);
        $url = get_permalink($pid);
        
        $disabled = (bool) get_post_meta($post->ID, Helper::getDisabledMetaKey(), true);
        
        echo self::getColumnContent($url, $disabled || $post->post_status !== 'publish', ['post_type', 'post', $post->ID]);

      }
      
    }
    
    /**
     * Add term column content
     * 
     * @param string $s
     * @param string $key
     * @param int $tid
     */
    public static function addTermColumnContent(string $s, string $key, int $tid)
    {
      
      if ($key === 'jinx-fast-cache') {
        
        $term = get_term($tid);
        
        $url = get_term_link($term);
        
        $disabled = (bool) get_term_meta($term->term_id, Helper::getDisabledMetaKey(), true);
        
        echo self::getColumnContent($url, $disabled, ['taxonomy', $term->taxonomy, $term->term_id]);

      }
      
    }
    
    /**
     * Get column content for an URL
     * 
     * @param string $url
     * @param bool $disabled
     * @param array args
     * @return string
     */
    protected static function getColumnContent(string $url, bool $disabled = false, array $args = []) : string
    {
      
      if ($disabled === true) {
        $status = 'disabled';
      } else {
        $status = Url::getStatus($url); 
      }
      
      $icons = [
        'not cached' => '&#128308',
        'warming' => '&#128993;',
        'cached' => '&#128994;',
        'disabled' => '&#9898;'
      ];
                        
      if ($status === 'cached') {
        
        $timestamp = Url::getTimestamp($url);             
        $size = Url::getSize($url);
                
        $info = Plugin::t(' %s (%s)', [
          date('Y-m-d H:i:s', $timestamp),
          Helper::formatSize($size)
        ]);
                
      }
      
      $url = self::createUrl(array_merge(['toggle'], $args));
      
      return '<a href="'.$url.'" title="'.Plugin::t($status).'">'.($icons[$status] ?? $icons['not cached']).'</a><small>'.($info ?? '').'</small>';
      
    }
      
    /**
     * Add post row actions
     * 
     * @param array $actions
     * @param WP_Post $post
     * @return array
     */
    public static function addPostRowActions(array $actions, \WP_Post $post) : array
    {
      
      if ($post->post_status === 'publish') {
        
        $actions = array_merge($actions, self::createActions([
          'post_type' => $post->post_type,
          $post->ID
        ]));
        
      }
      
      return $actions;
            
    }
    
    /**
     * Add term row actions
     * 
     * @param array $actions
     * @param \WP_Term $term
     * @return array
     */
    public static function addTermRowActions(array $actions, \WP_Term $term) : array
    {
      
      return array_merge($actions, self::createActions([
        'taxonomy' => $term->taxonomy,
        $term->term_id
      ]));
            
    }
    
    /**
     * Create row actions
     * 
     * @param array $args
     * @return array
     */
    protected static function createActions(array $args) : array
    {
      
      $actions = [];
      
      foreach (self::$actions as $action) {
        
        $query = $args;
        array_unshift($query, $action);
                        
        $actions[] = '<a href="'.self::createUrl($query).'">'.Plugin::t(ucfirst($action)).'</a>';

      }
      
      return $actions;
      
    }
    
    /**
     * Create the admin url
     * 
     * @param array $args
     * @return string
     */
    protected static function createUrl(array $args = []) : string
    {
            
      $url = parse_url($_SERVER['REQUEST_URI']);
      
      parse_str($url['query'] ?? '', $query);
      
      $params = [];
      foreach ($args as $key => $value) {
        if (!is_numeric($key)) {
          $params[] = $key;
        }
        $params[] = $value; 
      }
      
      $query['jinx-fast-cache'] = implode('/', $params);
      
      return $url['path'].'?'.build_query($query);
    
    }
    
    /**
     * Execute a given action
     * 
     * @param string $action
     */
    public static function execute(string $action)
    {     
      
      list($action, $element, $type, $id) = array_merge(explode('/', $action), array_fill(0, 3, null));
      
      $method = 'action'.ucfirst($action);
      
      // if action exists
      if (method_exists(__CLASS__, $method)) {      
        self::$method($element, $type, $id);
      } else {
        
        add_action('admin_notices', function() use($action) {
          echo '<div class="notice notice-error is-dismissible"><p>'.Plugin::t("The action '%s' does not exist.", [$action]).'</p></div>';
        });
        
      }
      
    } 
    
    /**
     * Flush the cache
     * 
     * @param string $element
     * @param string $type
     * @param int $id
     */
    protected static function actionFlush(?string $element = null, ?string $type = null, ?int $id = null)
    {
            
      $urls = [];
      
      if (isset($element)) {
                
        if (isset($type, $id)) { 
          $urls[] = Url::getOne($element, $id);
        } elseif (isset($type)) {          
          $urls = Url::getMultiple($element, $type);
        }
        
      }
      
      // when no URLs passed, everything will be flushed
      Service::flush($urls);

      add_action('admin_notices', function() { 
        echo '<div class="notice notice-success is-dismissible"><p>'.Plugin::t('The cache has been flushed.').'</p></div>';
      });
            
    }
    
    /**
     * Warm the cache
     * 
     * @param string $element
     * @param string $type
     * @param int $id
     */
    protected static function actionWarm(?string $element = null, ?string $type = null, ?int $id = null)
    {
      
      $urls = [];
      $queue = true;
      
      if (isset($element)) {
        
        if (isset($type, $id)) {
          $queue = false;
          $urls[] = Url::getOne($element, $id);
        } elseif (isset($type)) {
          $urls = Url::getMultiple($element, $type);
        }
        
      // everything
      } else { 
        $urls = Url::getAll(); 
      } 
      
      Service::warm($urls, $queue);

      add_action('admin_notices', function() { 
        echo '<div class="notice notice-success is-dismissible"><p>'.Plugin::t('The cache has been warmed!').'</p></div>';
      });
      
    }
    
    /**
     * Refresh the cache (flush and warm)
     * 
     * @param string $element
     * @param string $type
     * @param int $id
     */
    protected static function actionRefresh(?string $element = null, ?string $type = null, ?int $id = null)
    {
      self::actionFlush($element, $type, $id);
      self::actionWarm($element, $type, $id); 
    }
    
    public static function actionToggle(string $element, string $type, ?int $id = null)
    {
      
      $metaKey = Helper::getDisabledMetaKey();
      
      switch ($element) {
        case 'post_type':
          
          $metaValue = (bool) get_post_meta($id, $metaKey, true);
          
          update_post_meta($id, $metaKey, !$metaValue);
          
        break;
        case 'taxonomy':
          
          $metaValue = (bool) get_term_meta($id, $metaKey, true);
          
          update_term_meta($id, $metaKey, !$metaValue);
          
        break;
        
      }
      
      self::actionFlush($element, $type, $id);
        
    }
    
  }