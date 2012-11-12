<?php
class batcache_apc extends batcache {
	function configure_groups() {
	}

	function cache_init() {
	}

	function cache_inclusion() {
		// Test if extensions is loaded
		if ( !function_exists('apc_store') ) {
			return false;
		}

		return true;
	}

	function cache_exists() {
		$servers_stats = apc_cache_info();
		if ( $servers_stats === false )
			return false;
		
		return true;
	}

	function is_support_increment() {
		if ( ! function_exists( 'apc_inc' ) )
			return false;

		return true;
	} 

	function get_cache( $key, $group = '', $force = false, &$found = null ) {
		return apc_fetch( $group.':'.$key );
	}

	function set_cache( $key, $data, $group = '', $expire = 0 ) {
		return apc_store( $group.':'.$key, $data, $expire );
	}

	function add_cache( $key, $data, $group = '', $expire = 0 ) {
		return apc_add( $group.':'.$key, $data, $expire );
	}

	function incr_cache(  $key, $offset = 1, $group = '' ) {
		return apc_inc( $group.':'.$key, $offset );
	}

	function delete_cache($key, $group = '') {
		return apc_delete( $group.':'.$key );
	}
}