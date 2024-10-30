=== Jinx Fast-Cache ===
Contributors: Lukas Rydygel
Tags: cache, html, files, fullpage, pagecache, filecache, rewrite, htaccess
Requires at least: 5.0
Tested up to: 6.4.2
Requires PHP: 8.0
Stable tag: 0.3.6
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Jinx Fast-Cache is a blazing fast full page cache for WordPress, written for developers.
The goal was to create a caching plugin without the overhead in the WordPress backend. It will work with URLs, not PIDs.

== Description ==

Jinx Fast-Cache provides a very simple but efficient way of full page caching to WordPress.
It will generate static HTML files which will be called using your servers rewrite rules.
This feature will bypass the whole PHP process and render only a simple HTML file without the whole overhead.

== Installation ==

1. Unzip the downloaded package
2. Upload `jinx-fast-cache` to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Optional: You may need to modify the rewrite rules

== Usage ==

After activating the plugin, it will modify your .htaccess file. If this is not possible, make sure to enter the rules by yourself:

    # BEGIN Jinx Fast-Cache
    RewriteEngine On
    RewriteBase /
    RewriteCond %{DOCUMENT_ROOT}/wp-content/jinx-fast-cache/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html -s
    RewriteCond %{REQUEST_METHOD} GET
    RewriteRule .* /wp-content/jinx-fast-cache/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html [L]
    # END Jinx Fast-Cache

When using nginx, make sure to add the following rules:

    set $cache_path false;
    if ($request_method = GET) {
      set $cache_path /wp-content/jinx-fast-cache/$host/$uri/$args/index.html;
    }
    location / {
      try_files $cache_path $uri $uri/ /index.php?$query_string;
    }

You may flush, warm or refresh (flush & warm) single or multiple URLs using the buttons in the admin bar.

By default all posts will be automatically warmed after they have been saved and flushed after they have been deleted or put on draft.

The warm process will create a queue, which will be handled in a scheduled task (cron). When warming up a single post, it will skip the queue.

The plugin will automatically flush and warm the cache after an update has been completed.

== Developers ==

## Filters

Jinx Fast-Cache is made for developers. So far no admin panel is available, but you may modify a lot of it's behaviors using filters.

- **jinx_fast_cache_active**: Control if an URL should be cached (default true) or not.
- **jinx_fast_cache_post_types**: Control the post types which should be cached. By default all post types which are "publicly_queryable" and "page" will be cached.
- **jinx_fast_cache_posts**: Filter the posts which should be cached.
- **jinx_fast_cache_taxonomies**: Control the taxonomies which should be cached. By default all taxonomies which are "publicly_queryable" will be cached.
- **jinx_fast_cache_terms**: Filter the terms which should be cached.
- **jinx_fast_cache_output**: Use this to modify the HTML content written to your cache file.
- **jinx_fast_cache_minify**: Control if the output should be minified (default true) or not.
- **jinx_fast_cache_flush**: Control which URLs should be flushed. This may be used to flush related URLs eg. your front page.
- **jinx_fast_cache_warm**: Control which URLs should be warmed. This may be used to warm related URLs eg. your front page.
- **jinx_fast_cache_queue_interval**: Change the interval of the queues cron task (default 60) to warm URLs.
- **jinx_fast_cache_queue_size**: Change the number of URLs which should be handled durring a cron task (default 10). When setting it to <= 0, all URLs will be handled. This may cause a huge load when you have a lot of URLs.
- **jinx_fast_cache_gc_interval**: Change the interval of the GCs cron task (default 60) to flush invalid URLs.
- **jinx_fast_cache_ignore_404**: Control if 404 errors should be cached (default false) or not. Not that a lot of 404 errors will also create a lot of cache files on your server.
- **jinx_fast_cache_query_params**: Control if and which query params will be accepted. You may pass '__return_empty_array' to allow no query params at all.
- **jinx_fast_cache_refresh_on_upgrade**: Control if the cache should be refreshed on upgrade (default true).
- **jinx_fast_cache_duration**: Change the caches duration (default null). The cache of URLs without a duration will always be valid. You may use a numeric value eg. 3600 or something like '12 hours' or '1 week' etc.

## Injections

Jinx Fast-Cache also provides the feature to inject dynamic content into your pages. If you eg. want to print the users name on the page, you may inject it via ajax.
You may also use a placeholder to let your users know, that the content will be loading eg. "loading ...".

Inject a template:

    do_action('jinx_fast_cache_inject_template', 'user');
    do_action('jinx_fast_cache_inject_template', 'user', 'loading ...');

This has the same effect as:

    do_action('jinx_fast_cache_inject', 'get_template_part', ['user']);
    do_action('jinx_fast_cache_inject', 'get_template_part', ['user'], 'loading ...');

You may call every public function of PHP, your theme or any plugin:

    do_action('jinx_fast_cache_inject', 'date', ['Y']);
    do_action('jinx_fast_cache_inject', 'my_function', ['param1', 'param2']);
    do_action('jinx_fast_cache_inject', 'namespace\MyClass::myMethod', ['param1', 'param2']);
    do_action('jinx_fast_cache_inject', ['namespace\MyClass', 'myMethod'], ['param1', 'param2']);

The first parameter is the function call, the second parameter is an array or arguments passed to this function and the third parameter is the placeholder.

Inside the editor, you may also use shortcodes to inject content.

    [jinx_fast_cache_inject]My dynamic content or other shortcodes can be used here[/jinx_fast_cache_inject]
    [jinx_fast_cache_inject placeholder="loading..."]My dynamic content or other shortcodes can be used here[/jinx_fast_cache_inject]

Every shortcode or block between "jinx_fast_cache_inject" will be parsed and injected via ajax. Note that this may cause problems when working with JS events.

## Injection Callbacks

You may trigger custom JS after dynamic content has been injected.

Using jQuery:

    $('.element').on('jinx-fast-cache-inject', function(e) {
      // so smth. with e.target or this
    });

Using VanillaJS:
    
    element.addEventListener('jinx-fast-cache-inject' (e) => {
      // so smth. with e.target
    }, false);

## Tags

Even there are some filters to build a relation between URLs, tags are an easier way to do this.
You may connect multiple URLs with tags, so if one URL gets flushed, it will also flush URLs with the same tag.

Tags can be used inside the editor by using the shortcode:

    [jinx_fast_cache tags="foo,bar"]

Or you can use it inside your templates by calling the action:

    do_action('jinx_fast_cache', ['tags' => 'foo,bar']);
    do_action('jinx_fast_cache', ['tags' => ['foo', 'bar']]);

A usecase for tags might be to connect single posts with your page for posts. So if a single post will be flushed, the page for posts and all other posts will also be flushed.

You may also add multiple tags by calling the shortcode or action multiple times. This will work very well when using blocks or other shortcodes.

## Cache duration

As you have seen already, you may set a cache duration globally by using the filter 'jinx_fast_cache_duration'.
However, if there is a specific URL eg. the front page, you may want to change the duration.

You may use a shortcode like this:

    [jinx_fast_cache duration="3600"]
    [jinx_fast_cache duration="12 hours"]

Or you can use it inside your templates by calling the action:

    do_action('jinx_fast_cache', ['duration' => 3600]);

## Hits

Be aware that you can set tags and the cache duration in just one call.

    [jinx_fast_cache duration="3600" tags="foo,bar"]

Or you can use it inside your templates by calling the action:

    do_action('jinx_fast_cache', ['duration' => 3600, 'tags' => 'foo,bar']);
    do_action('jinx_fast_cache', ['duration' => 3600, 'tags' => ['foo', 'bar']]);

Note that tags will accept a string or an array.

## Roadmap

- [x] Release the plugin
- [x] Add HTML minification for output
- [x] Allow injection of dynamic rendered templates using ajax requests
- [x] Add taxonomies
- [x] Provide scheduled tasks
- [x] Add admin columns for cache status
- [x] Provide exclude option for posts and terms in backend
- [x] Add multisite support
- [x] Flush and warm after update complete
- [x] Add possibility to ignore 404
- [x] Allow query params to be excluded or totally ignored
- [x] Provide cache duration
- [ ] Provide admin panel to change options
- [x] Add tags to flush related pages
- [x] Add shortcode for injects
- [x] Add JS events for injects

== Upgrade Notice ==

When updating, by default the plugin will flush the cache. Anyway, it may be a good idea to deactivate and activate the plugin again, if there are any problems after an upgrade.