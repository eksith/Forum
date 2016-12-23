<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum\Config
 *  @file Config.php
 *  @brief Configuration settings loader and modifier
 */
final class Config {
	
	private $name;
	
	/**
	 *  @var string File name (relative to \BASE)
	 */
	private $file;
	
	/**
	 *  @var string Definition prefix E.G. 'MSG_' or 'LANG_'
	 */
	private $prefix	= '';
	
	/**
	 *  @var string Optional key for encrypted configurations
	 */
	private $key;
	
	/**
	 *  @var bool Settings have changed since loading
	 */
	private $changed	= false;
	
	/**
	 *  @var array Configuration parameters including labels etc...
	 */
	private $params		= array();
	
	/**
	 *  Settings file name extension
	 */
	const EXT		= 'json';
	
	/**
	 *   Create or load configuration
	 *  
	 *  @param string $name Definition file name
	 *  @param string $key Encryption key (optional, keep data small)
	 *  @param string $prefix Definition prefix (optional)
	 */
	public function __construct( 
		string	$name, 
		string	$prefix		= '',
		string	$key		= ''
	) {
		$this->name	= $name;
		$this->file	= $name . '.' . self::EXT;
		$this->prefix	= $prefix;
		$this->key	= $key;
		
		$this->loadConf();
	}
	
	/**
	 *  Save configuration upon destruct
	 */
	public function __destruct() {
		if ( $this->changed ) {
			$this->saveConf();
		}
	}
	
	/**
	 *  Load configuration file ( JSON formatted ) in base directory
	 */
	private function loadConf() {
		$data	= File::load( \BASE . $this->file, \BASE );
		if ( empty( $data ) ) {
			return;
		}
		
		if ( !empty( $this->key ) ) {
			$crypto	= Crypto::getInstance();
			$data	= $crypto->decrypt( $data, $this->key );
		}
		
		$params	= json_decode( utf8_encode( $data ), true, 7 );
		if ( empty( $params ) ) {
			$params = array();
		}
		$this->params = $params;
	}
	
	public function newValues( array $values ) {
		$this->changed		= true;
		$this->params = Page::merge( $this->params, $values );
	}
	
	/**
	 *  Add or complete change a parameter definition
	 *  
	 *  @param string $def Definition key (unique to this file)
	 *  @param string $label Description label
	 *  @param string $data Content to save
	 *  @param string $type Data type to save as
	 *  @param string $mask Display mask
	 *  @return bool
	 */
	public function addParam(
		string $def,
		string $label,
		string $data,
		string $type	= 'text',
		string $mask	= ''
	) : bool {
		$def			= $this->prefix . $def;
		$this->changed		= true;
		$this->params[$def]	= 
			array( $label, $data, $type, $type, $mask );
		
		return true;
	}
	
	/**
	 *  Change existing parameter data
	 *  
	 *  @param string $def Definition key
	 */
	public function saveParam( string $def, string $data ) {
		$def			= $this->prefix . $def;
		if ( array_key_exists( $def, $this->params ) ) {
			return;
		}
		$this->changed		= true;
		
		$save			= $this->params[$def];
		$save[1]		= $data;
		$this->params[$def]	= $save;
	}
	
	/**
	 *  Get a parameter by definition key if it exists
	 *  
	 *  @param string $def Definition key
	 *  @return array
	 */
	public function getParam( string $def ) : array {
		$def	= $this->prefix . $def;
		return isset( $this->params[$def] ) ? 
			$this->params[$def] : array();
	}
	
	/**
	 *  Get all parameters
	 *  @return array
	 */
	public function getAllParams() : array {
		return $this->params;
	}
	
	/**
	 *  Set configuration parameters as defined vars
	 *  This will not overwrite existing definitions
	 *  
	 *  @param array $params Specific subset of parameters to define
	 *  		If this is empty, all parameters will be defined
	 */
	public function defineParams( array $params = array() ) {
		if ( empty( $params ) ) {
			$params = $this->params;
		}
		foreach( $params as $def => $data ) {
			if ( !defined( $def ) ) {
				define( $def, $data[1] );
			}
		}
	}
	
	/**
	 *  Save configuration file as JSON
	 */
	private function saveConf() {
		$data	= Text::toJSON( $this->params );
		
		if ( !empty( $this->key ) ) {
			$crypto	= Crypto::getInstance();
			$data	= $crypto->encrypt( $data, $this->key );
		}
		
		File::save( \BASE . $this->file, $data, \BASE );
	}
}

