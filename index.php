<?php
  
  /**
   * Plugin Name: Jinx Fast-Cache
   * Plugin URI: https://wordpress.org/plugins/jixn-fast-cache/
   * Description: Blazing fast full page cache.
   * Version: 0.8.0
   * Author: Jinx Digital <hello@jinx-digital.com>
   * Author URI: http://jinx-digital.com
   * License: GPL2+
   * Text Domain: lazy-cache
   */

  require_once __DIR__.'/src/Helper.php';
  require_once __DIR__.'/src/Url.php';
  require_once __DIR__.'/src/Plugin.php';
  require_once __DIR__.'/src/Queue.php';
  require_once __DIR__.'/src/Service.php';
  require_once __DIR__.'/src/Admin.php';
  require_once __DIR__.'/src/Front.php';
  require_once __DIR__.'/src/GarbageCollector.php';
  
  require_once ABSPATH.'wp-admin/includes/upgrade.php';
  require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php';
  require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php';
  
  register_activation_hook(__FILE__, ['Jinx\FastCache\Plugin', 'activate']);
  register_deactivation_hook( __FILE__, ['Jinx\FastCache\Plugin', 'deactivate']);
  register_uninstall_hook(__FILE__, ['Jinx\FastCache\Plugin', 'uninstall']);
  
  add_action('admin_init', ['Jinx\FastCache\Admin', 'init']);
  add_action('init', ['Jinx\FastCache\Front', 'init']);
  add_action('init', ['Jinx\FastCache\Queue', 'init']);
  add_action('init', ['Jinx\FastCache\GarbageCollector', 'init']);