<?php
class batcache_wp_cache extends batcache {

	function configure_groups() {
		// Remember, $wp_object_cache was clobbered in wp-settings.php so we have to repeat this.
		// Configure the memcached client
		if ( ! $this->remote )
			if ( function_exists('wp_cache_add_no_remote_groups') )
				wp_cache_add_no_remote_groups(array($this->group));
		if ( function_exists('wp_cache_add_global_groups') )
			wp_cache_add_global_groups(array($this->group));
	}

	function cache_init() {
		// Note: wp-settings.php calls wp_cache_init() which clobbers the object made here.
		wp_cache_init();
	}

	function cache_inclusion() {
		if ( ! include_once( WP_CONTENT_DIR . '/object-cache.php' ) )
			return false; // Stop process, no caching

		return true;
	}

	function cache_exists() {
		if ( ! is_object( $wp_object_cache ) )
			return false; // Stop process, no caching

		return true;
	}

	function is_support_increment() {
		if ( ! method_exists( $GLOBALS['wp_object_cache'], 'incr' ) )
			return false;

		return true;
	}

	function get_cache( $key, $group = '', $force = false, &$found = null ) {
		return wp_cache_get($key, $group, $force, $found);
	}

	function set_cache( $key, $data, $group = '', $expire = 0 ) {
		return wp_cache_set($key, $data, $group, $expire);
	}

	function add_cache( $key, $data, $group = '', $expire = 0 ) {
		return wp_cache_add($key, $data, $group, $expire);
	}

	function incr_cache(  $key, $offset = 1, $group = '' ) {
		return wp_cache_incr( $key, $offset, $group );
	}

	function delete_cache($key, $group = '') {
		return wp_cache_delete( $key, $group );
	}
}