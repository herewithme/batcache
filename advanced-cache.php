<?php
// Version: 1.1
// nananananananananananananananana BATCACHE!!!

// Determine a default inteface, use WP Cache API (classic way of original batcache)
if ( ! defined( 'BATCACHE_INTERFACE' ) )
	define('BATCACHE_INTERFACE', 'wp-cache');

// Availables interface : wp-cache, memcached

function batcache_cancel() {
	global $batcache;

	if ( is_object($batcache) )
		$batcache->cancel = true;
}

class batcache {
	// This is the base configuration. You can edit these variables or move them into your wp-config.php file.
	var $max_age =  300; // Expire batcache items aged this many seconds (zero to disable batcache)
	
	var $remote  =    0; // Zero disables sending buffers to remote datacenters (req/sec is never sent)
	
	var $times   =    2; // Only batcache a page after it is accessed this many times... (two or more)
	var $seconds =  120; // ...in this many seconds (zero to ignore this and use batcache immediately)
	
	var $group   = 'batcache'; // Name of memcached group. You can simulate a cache flush by changing this.
	
	var $unique  = array(); // If you conditionally serve different content, put the variable values here.
	
	var $headers = array(); // Add headers here. These will be sent with every response from the cache.

	var $uncached_headers = array('transfer-encoding'); // These headers will never be cached. Apply strtolower.

	var $debug   = true; // Set false to hide the batcache info <!-- comment -->

	var $cache_control = true; // Set false to disable Last-Modified and Cache-Control headers

	var $cancel = false; // Change this to cancel the output buffer. Use batcache_cancel();

	var $do = false; // By default, we do not cache

	function batcache( $settings ) {
		if ( is_array( $settings ) ) foreach ( $settings as $k => $v )
			$this->$k = $v;
	}

	function status_header( $status_header ) {
		$this->status_header = $status_header;

		return $status_header;
	}

	// Defined here because timer_stop() calls number_format_i18n()
	function timer_stop($display = 0, $precision = 3) {
		global $timestart, $timeend;
		$mtime = microtime();
		$mtime = explode(' ',$mtime);
		$mtime = $mtime[1] + $mtime[0];
		$timeend = $mtime;
		$timetotal = $timeend-$timestart;
		$r = number_format($timetotal, $precision);
		if ( $display )
			echo $r;
		return $r;
	}

	function ob($output) {
		if ( $this->cancel !== false )
			return $output;

		// PHP5 and objects disappearing before output buffers?
		$this->cache_init();
		$this->configure_groups();

		// Do not batcache blank pages (usually they are HTTP redirects)
		$output = trim($output);
		if ( empty($output) )
			return false; // Stop process, no caching

		// Construct and save the batcache
		$cache = array(
			'output' => $output,
			'time' => time(),
			'timer' => $this->timer_stop(false, 3),
			'status_header' => $this->status_header,
			'version' => $this->url_version
		);

		if ( function_exists( 'apache_response_headers' ) ) {
			$cache['headers'] = apache_response_headers();
			if ( !empty( $this->uncached_headers ) ) foreach ( $cache['headers'] as $header => $value ) {
				if ( in_array( strtolower( $header ), $this->uncached_headers ) )
					unset( $cache['headers'][$header] );
			}
		}

		$this->set_cache($this->key, $cache, $this->group, $this->max_age + $this->seconds + 30);

		// Unlock regeneration
		$this->delete_cache("{$this->url_key}_genlock", $this->group);

		if ( $this->cache_control ) {
			header('Last-Modified: ' . date('r', $cache['time']), true);
			header("Cache-Control: max-age=$this->max_age, must-revalidate", false);
		}

		if ( !empty($this->headers) ) foreach ( $this->headers as $k => $v ) {
			if ( is_array( $v ) )
				header("{$v[0]}: {$v[1]}", false);
			else
				header("$k: $v", true);
		}

		// Add some debug info just before </head>
		if ( $this->debug ) {
			$tag = "<!--\n\tgenerated in " . $cache['timer'] . " seconds\n\t" . strlen(serialize($cache)) . " bytes batcached for " . $this->max_age . " seconds\n-->\n";
			if ( false !== $tag_position = strpos($output, '</head>') ) {

				$output = substr($output, 0, $tag_position) . $tag . substr($output, $tag_position);
			}
		}

		// Pass output to next ob handler
		return $output;
	}

	function configure_groups() {
	}

	function cache_init() {
	}

	function cache_inclusion() {
	}

	function cache_exists() {
	}

	function is_support_increment() {
	}

	function get_cache( $key, $group = '', $force = false, &$found = null ) {
	}

	function set_cache( $key, $data, $group = '', $expire = 0 ) {
	}

	function add_cache( $key, $data, $group = '', $expire = 0 ) {
	}

	function incr_cache(  $key, $offset = 1, $group = '' ) {
	}

	function delete_cache($key, $group = '') {
	}
}

// Pass in the global variable which may be an array of settings to override defaults.
global $batcache;

switch( BATCACHE_INTERFACE ) {
	case 'memcached':
		break;
	case 'wp-cache':
	default :
		require( dirname(__FILE__) . '/batcache-interfaces/wp-cache-api.php' );
		$batcache = new batcache_wp_cache($batcache);
		break;
}


if ( ! defined( 'WP_CONTENT_DIR' ) )
	return false; // Stop process, no caching

// Never batcache interactive scripts or API endpoints.
if ( in_array(
		basename( $_SERVER['SCRIPT_FILENAME'] ),
		array(
			'wp-app.php',
			'xmlrpc.php',
			'ms-files.php',
		) ) )
	return false; // Stop process, no caching

// Never batcache WP javascript generators
if ( strstr( $_SERVER['SCRIPT_FILENAME'], 'wp-includes/js' ) )
	return false; // Stop process, no caching

// Never batcache when POST data is present.
if ( ! empty( $GLOBALS['HTTP_RAW_POST_DATA'] ) || ! empty( $_POST ) )
	return false; // Stop process, no caching

// Never batcache when cookies indicate a cache-exempt visitor.
if ( is_array( $_COOKIE) && ! empty( $_COOKIE ) )
	foreach ( array_keys( $_COOKIE ) as $batcache->cookie )
		if ( $batcache->cookie != 'wordpress_test_cookie' && ( substr( $batcache->cookie, 0, 2 ) == 'wp' || substr( $batcache->cookie, 0, 9 ) == 'wordpress' || substr( $batcache->cookie, 0, 14 ) == 'comment_author' ) )
			return false; // Stop process, no caching

if ( !$batcache->cache_inclusion() ) 
	return false; // Stop process, no caching

$batcache->cache_init();

if ( !$batcache->cache_exists() ) 
	return false; // Stop process, no caching


// Now that the defaults are set, you might want to use different settings under certain conditions.

/* Example: if your documents have a mobile variant (a different document served by the same URL) you must tell batcache about the variance. Otherwise you might accidentally cache the mobile version and serve it to desktop users, or vice versa.
$batcache->unique['mobile'] = is_mobile_user_agent();
*/

/* Example: never batcache for this host
if ( $_SERVER['HTTP_HOST'] == 'do-not-batcache-me.com' )
	return false; // Stop process, no caching
*/

/* Example: batcache everything on this host regardless of traffic level
if ( $_SERVER['HTTP_HOST'] == 'always-batcache-me.com' )
	return false; // Stop process, no caching
*/

/* Example: If you sometimes serve variants dynamically (e.g. referrer search term highlighting) you probably don't want to batcache those variants. Remember this code is run very early in wp-settings.php so plugins are not yet loaded. You will get a fatal error if you try to call an undefined function. Either include your plugin now or define a test function in this file.
if ( include_once( 'plugins/searchterm-highlighter.php') && referrer_has_search_terms() )
	return false; // Stop process, no caching
*/

// Disabled
if ( $batcache->max_age < 1 )
	return false; // Stop process, no caching

// Make sure we can increment. If not, turn off the traffic sensor.
if ( !$batcache->is_support_increment() )
	$batcache->times = 0;

// Necessary to prevent clients using cached version after login cookies set. If this is a problem, comment it out and remove all Last-Modified headers.
header('Vary: Cookie', false);

// Things that define a unique page.
if ( isset( $_SERVER['QUERY_STRING'] ) )
	parse_str($_SERVER['QUERY_STRING'], $batcache->query);
$batcache->keys = array(
	'host' => $_SERVER['HTTP_HOST'],
	'path' => ( $batcache->pos = strpos($_SERVER['REQUEST_URI'], '?') ) ? substr($_SERVER['REQUEST_URI'], 0, $batcache->pos) : $_SERVER['REQUEST_URI'],
	'query' => $batcache->query,
	'extra' => $batcache->unique
);

$batcache->configure_groups();

// Generate the batcache key
$batcache->key = md5(serialize($batcache->keys));

// Generate the traffic threshold measurement key
$batcache->req_key = $batcache->key . '_req';

// Get the batcache
$batcache->cache = $batcache->get_cache($batcache->key, $batcache->group);

// Are we only caching frequently-requested pages?
if ( $batcache->seconds < 1 || $batcache->times < 2 ) {
	$batcache->do = true;
} else {
	// No batcache item found, or ready to sample traffic again at the end of the batcache life?
	if ( !is_array($batcache->cache) || time() >= $batcache->cache['time'] + $batcache->max_age - $batcache->seconds ) {
		$batcache->add_cache($batcache->req_key, 0, $batcache->group);
		$batcache->requests = $batcache->incr_cache($batcache->req_key, 1, $batcache->group);

		if ( $batcache->requests >= $batcache->times )
			$batcache->do = true;
		else
			$batcache->do = false;
	}
}

// Recreate the permalink from the URL
$batcache->permalink = 'http://' . $batcache->keys['host'] . $batcache->keys['path'] . ( isset($batcache->keys['query']['p']) ? "?p=" . $batcache->keys['query']['p'] : '' );
$batcache->url_key = md5($batcache->permalink);
$batcache->url_version = (int) $batcache->get_cache("{$batcache->url_key}_version", $batcache->group);

// If the document has been updated and we are the first to notice, regenerate it.
if ( $batcache->do !== false && isset($batcache->cache['version']) && $batcache->cache['version'] < $batcache->url_version )
	$batcache->genlock = $batcache->add_cache("{$batcache->url_key}_genlock", 1, $batcache->group);
else $batcache->genlock = 0;

// Did we find a batcached page that hasn't expired?
if ( isset($batcache->cache['time']) && ! $batcache->genlock && time() < $batcache->cache['time'] + $batcache->max_age ) {
	// Issue "304 Not Modified" only if the dates match exactly.
	if ( $batcache->cache_control && isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ) {
		$since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if ( $batcache->cache['time'] == $since ) {
			header('Last-Modified: ' . $_SERVER['HTTP_IF_MODIFIED_SINCE'], true, 304);
			exit;
		}
	}

	// Use the batcache save time for Last-Modified so we can issue "304 Not Modified"
	if ( $batcache->cache_control ) {
		header('Last-Modified: ' . date('r', $batcache->cache['time']), true);
		header('Cache-Control: max-age=' . ($batcache->max_age - time() + $batcache->cache['time']) . ', must-revalidate', true);
	}

	// Add some debug info just before </head>
	if ( $batcache->debug ) {
		if ( false !== $tag_position = strpos($batcache->cache['output'], '</head>') ) {
			$tag = "<!--\n\tgenerated " . (time() - $batcache->cache['time']) . " seconds ago\n\tgenerated in " . $batcache->cache['timer'] . " seconds\n\tserved from batcache in " . $batcache->timer_stop(false, 3) . " seconds\n\texpires in " . ($batcache->max_age - time() + $batcache->cache['time']) . " seconds\n-->\n";
			$batcache->cache['output'] = substr($batcache->cache['output'], 0, $tag_position) . $tag . substr($batcache->cache['output'], $tag_position);
		}
	}

	if ( !empty($batcache->cache['headers']) ) foreach ( $batcache->cache['headers'] as $k => $v )
		header("$k: $v", true);

	if ( !empty($batcache->headers) ) foreach ( $batcache->headers as $k => $v ) {
		if ( is_array( $v ) )
			header("{$v[0]}: {$v[1]}", false);
		else
			header("$k: $v", true);
	}

	if ( !empty($batcache->cache['status_header']) )
		header($batcache->cache['status_header'], true);

	// Have you ever heard a death rattle before?
	die($batcache->cache['output']);
}

// Didn't meet the minimum condition?
if ( !$batcache->do && !$batcache->genlock )
	return false; // Stop process, no caching

$wp_filter['status_header'][10]['batcache'] = array( 'function' => array(&$batcache, 'status_header'), 'accepted_args' => 1 );

ob_start(array(&$batcache, 'ob'));

// It is safer to omit the final PHP closing tag.

