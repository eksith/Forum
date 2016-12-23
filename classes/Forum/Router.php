<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum\Router
 *  @file Router.php
 *  @brief URL Router
 */
class Router extends Singleton {
	
	/**
	 *  @var array List of added routes
	 */
	private $routes		= array();
	
	/**
	 *  @var array List of error display routes
	 */
	private $error_routes	= array();
	
	/**
	 *  @var array Safe request methods
	 */
	private static $safe	= array( 'get', 'head', 'post' );
	
	/**
	 *  @var array Routing placeholders
	 */
	private $markers	= 
	array(
		'*'	=> '(?<all>.+?)',
		':id'	=> '(?<id>[1-9][0-9]*)',
		':tag'	=> '(?<tag>[\pL\pN\s_\,-]{3,30})',
		':cat'	=> '(?<cat>[\pL\pN]{3,20})',
		':user'	=> '(?<user>[\pL\pN\s-]{2,30})',
		':page'	=> '(?<page>[1-9][0-9]{0,5})',
		':year'	=> '(?<year>[2][0-9]{3})',
		':month'=> '(?<month>[0-3][0-9]{1})',
		':day'	=> '(?<day>[0-9][0-9]{1})',
		':slug'	=> '(?<slug>[\pL\-\d]{1,100})',
		':img'	=> '(?<img>[0-9a-z]{20,180})',
		':size'	=> '(?<size>[1-5][0-9]{1,2})',
		':vote'	=> '(?<vote>[\-|\+][0-9]{1,2})',
		':mode'	=> '(?<mode>new|view|edit|delete|drafts|pending)',
		':file'	=> '(?<file>[\pL_\-\d\.\s]{1,120})'
	);
	
	/**
	 *  Paths are sent in bare. Make them suitable for matching.
	 *  
	 *  @param string $route URL path in plain format
	 *  @return string Route in regex format
	 */
	protected function cleanRoute( 
		array	$k, 
		array	$v, 
		string	$route 
	) {
		$route	= str_replace( $k, $v, $route );
		$route	= str_replace( '.', '\.', $route );
		return '@^/' . $route . '/?$@i';
	}
	
	/**
	 *  Safe(er) input handling
	 *  
	 *  @param string $path Request URL
	 *  @param string $method Request method/verb
	 *  @return bool 
	 */
	public static function process( 
		string	&$path		= '', 
		string	&$method	= 'get'
	) {
		$server = Server::getInstance();
		$method	= strtolower( $server->getVar( 'REQUEST_METHOD' ) );
		
		if ( !in_array( $method, static::$safe ) ) {
			die( MSG_DISALLOWED );
		}
		
		$path	= 
		$server->getVar( 'REQUEST_URI', \FILTER_SANITIZE_URL );
	}
	
	/**
	 *  Add a collection of routes at once
	 */
	public function addMap( array $routes = array() ) {
		foreach( $routes as $route ) {
			$this->addRoute( $route[0], $route[1], $route[2] );
		}
	}
	
	/**
	 *  Add a route to list of routes if the method passes filter
	 *  
	 *  @param string $method Request method
	 *  @param string $path Requested URL relative path
	 *  @param callable $handler Route function
	 */
	public function addRoute( 
		string		$method, 
		string		$path, 
		callable	$handler 
	) {
		$method = trim( $method );
		if ( !in_array( $method, static::$safe ) ) {
			return;
		}
		
		$this->routes[] = array( $method, $path, $handler );
	}
	
	/**
	 *  Error code handling routes
	 *  
	 *  @param string $path Requested URL
	 *  @param callable $handler Error route function
	 */
	public function addErrorRoute(
		int		$code,
		callable	$handler
	) {
		$this->error_routes[$code] = $handler;
	}
	
	/**
	 *  Execute current path route
	 */
	public function route() {
		if ( empty( $this->routes ) ) {
			return;
		}
		
		$path	= '';
		$method	= 'get';
		static::process( $path, $method );
		
		ksort( $this->routes );
		$k		= array_keys( $this->markers );
		$v		= array_values( $this->markers );
		$found		= false;
		
		foreach( $this->routes as $map ) {
			if ( $map[0] != $method ) {
				continue;
			}
			
			$rx = $this->cleanRoute( $k, $v, $map[1] );
			if ( preg_match( $rx, $path, $params ) ) {
				$found = true;
				// Send request to handler
				return 
				call_user_func_array( 
					$map[2], 
					array( $path, $params ) 
				);
			}
		}
		
		// Still didn't get a hit? Call in 404
		if ( !$found ) {
			return isset( $this->error_routes[404] ) ? 
				call_user_func(
					$this->error_routes[404],
					$path
				): 
				null;
		}
	}
}
