<?php
class batcache_memcache extends batcache {
	var $memcache = null;

	function configure_groups() {
	}

	function cache_init() {
		global $memcached_servers;

		if ( $this->memcache !== null ) {
			return false;
		}
		
		$this->memcache = new Memcache;
		foreach( $memcached_servers as $server ) {
			@list ( $node, $port ) = explode(':', $server);
				if ( !$port )
					$port = ini_get('memcache.default_port');
				$port = intval($port);
				if ( !$port )
					$port = 11211;
				
			$this->memcache->addServer($node, $port, true, 1, 1, 15, true, array($this, 'failure_callback'));
			$this->memcache->setCompressThreshold(20000, 0.2);
		}

		return true;
	}

	function failure_callback($host, $port) {
		//error_log("Connection failure for $host:$port\n", 3, '/tmp/memcached.txt');
	}

	// No php dependances, juste PHP extension and global memcache configuration
	function cache_inclusion() {
		global $memcached_servers;

		// Test if memcached servers are defined
		if ( !isset($memcached_servers) || !is_array($memcached_servers) ) {
			return false;
		}

		// Test if extensions is loaded
		if ( !class_exists('Memcache') ) {
			return false;
		}

		return true;
	}

	function cache_exists() {
		$servers_stats = $this->memcache->getExtendedStats();
		foreach( $servers_stats as $server_stats ) {
			if ( $server_stats !== 0 ) {
				return true;
			}
		}
		
		return false;
	}

	function is_support_increment() {
		if ( ! method_exists( $this->memcache, 'increment' ) )
			return false;

		return true;
	} 

	function get_cache( $key, $group = '', $force = false, &$found = null ) {
		return $this->memcache->get( $group.':'.$key );
	}

	function set_cache( $key, $data, $group = '', $expire = 0 ) {
		// TODO: Expire variable ?
		return $this->memcache->set( $group.':'.$key, $data );
	}

	function add_cache( $key, $data, $group = '', $expire = 0 ) {
		// TODO: Expire variable ?
		return $this->memcache->add( $group.':'.$key, $data );
	}

	function incr_cache(  $key, $offset = 1, $group = '' ) {
		return $this->memcache->increment( $group.':'.$key, $offset );
	}

	function delete_cache($key, $group = '') {
		return $this->memcache->delete( $group.':'.$key );
	}
}