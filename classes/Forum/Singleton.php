<?php declare( strict_types = 1 );
/**
 *  @package Forum\Singleton
 *  @file Singleton.php
 *  @brief Inheritable Singleton
 */
namespace Forum;

class Singleton {
	
	/**
	 *  @var array Instances of inherited classes of this singleton
	 */
	private static $classes = array();
	
	protected function __construct() {}
	
	/**
	 *  Prevent cloning and unserializing this singleton
	 */
	private function __clone() {}	
	public function __wakeup() { die(); }
	
	public static function getInstance() {
		$class = get_called_class();
		if ( !isset( self::$classes[$class] ) ) {
			self::$classes[$class] = new static;
		}
		
		return self::$classes[$class];
	}
}
