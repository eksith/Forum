<?php declare( strict_types = 1 );
namespace Forum\Models;

/**
 *  @package Forum\Models\Model
 *  @file Model.php
 *  @brief Database object root class
 */
use Forum;

abstract class Model {
	
	/**
	 *  @var int Unique identifier
	 */
	public $id;
	
	/**
	 *  @var string Created date
	 */
	public $created_at;
	
	/**
	 *  @var string Last updated date
	 */
	public $updated_at;
	
	/**
	 *  @var int Object status (interpreted differently per object)
	 */
	public $status			= 0;
	
	/**
	 *  @var int Manual sort order
	 */
	public $sort_order		= 0;
	
	/**
	 *  @var int Current page index
	 */
	public $index			= 1;
	
	/**
	 *  @var int Number of sub items for this object
	 */
	public $total			= 0;
	
	/**
	 *  @var array List of page links
	 */
	public $pagination;
	
	/**
	 *  @var array Parent hierarchy to this object
	 */
	public $breadcrumbs;
	
	/**
	 *  @var array Collection of metadata
	 */
	public $meta			= array();
	
	/**
	 *  @var object Database connection
	 */
	private static $db;
	
	/**
	 *  @var object Content filter
	 */
	private static $filter;
	
	/**
	 *  @var object Session function class
	 */
	private static $session;
	
	/**
	 *  @var object Browser profile class
	 */
	private static $browser;
	
	/**
	 *  Insert object into storage or update existing
	 */
	public abstract function save();
	
	const PAGE_SKIP			= 5;
	
	// http://stackoverflow.com/a/17637420
	// https://stackoverflow.com/questions/31471611/t-sql-hierarchy-get-breadcrumbs-using-query
	/**
	 *  Generic breadcrumb query for a given ID, table, and fields
	 *  Any model using this must have an id and parent_id field in 
	 *  the table
	 */
	const CRUMB_SQL			= 
	"WITH crumb AS (
		SELECT {fields} FROM {table} WHERE id = :id
		UNION ALL
		SELECT {pfields} FROM {table} AS p
			INNER JOIN crumb AS C ON p.id = c.parent_id 
	) SELECT {fields} FROM crumb;";
	
	const META_FIELDS		= 
	"meta_data.input_view AS meta_input, 
	meta_data.edit_view AS meta_edit, 
	meta_data.render_view AS meta_render";
	
	const META_SQL			= 
	"INNER JOIN {model}_meta ON {table}.id = {model}_meta.{model}_id
	INNER JOIN meta_data ON {model}_meta.meta_id = meta_data.id";
	
	/**
	 *  Field/table name regular expression filter
	 */
	const FIELD_FILTER		= '/[^\w_\.]/';
	
	/**
	 *  Get session handler
	 */
	protected static function getSession() {
		if ( !isset( self::$session ) ) {
			self::$session	= 
				Forum\Session::getInstance();
		}
		
		return self::$session;
	}
	
	/**
	 *  Get session handler
	 */
	protected static function getBrowser() {
		if ( !isset( self::$browser ) ) {
			self::$browser	= 
				Forum\Browser::getInstance();
		}
		
		return self::$browser;
	}
	
	/**
	 *  Initiate and return the database connection
	 */
	protected static function getData() {
		if ( !isset( self::$db ) ) {
			self::$db = new Data();
		}
		
		return self::$db;
	}
	
	/**
	 *  Generic parent property helper (for category, board etc...)
	 */
	protected static function setParentProp( 
		string $name, 
		string $value, 
		string $prop,
		string $obj,
		&$model
	) {
		if ( !isset( $model->{$prop} ) ) {
			$class	= '\\Forum\\Models\\' . ucfirst( $obj );
			$model->{$prop}	= new $class();
		}
		
		switch( true ) {
			case $name == $prop . '_id':
				$model->{$prop}->id		= $value;
				break;
				
			case $name == $prop . '_title':
				$model->{$prop}->title		= $value;
				break;
				
			case $name == $prop . '_slug':
				$model->{$prop}->slug		= $value;
				break;
				
			case $name == $prop . '_status':
				$model->{$prop}->status		= $value;
				break;
				
			case $name == $prop . '_updated':
				$model->{$prop}->updated_at	= $value;
				break;
				
			case $name == $prop . '_created':
				$model->{$prop}->created_at	= $value;
				break;
				
			case $name == $prop . '_sort':
				$model->{$prop}->sort_order	= $value;
				break;
		}
	}
	
	/**
	 *  Insert new item
	 */
	protected function put( 
		array	$props,
		string	$table,
		object	$model
	) : int {
		// Check table name for funny characters
		if ( !preg_match( '/[a-zA-Z_]/', $table ) ) {
			return 0;
		}
		
		$db	= static::getData();
		$params	= static::apply( $props, $model );
		$values	= static::parameters( $params );
		$sql	= "INSERT INTO $table $values;";
		
		return $db->toDb( $sql, $params );
	}
	
	/**
	 *  Update existing item
	 */
	protected function edit( 
		array	$props,
		string	$table,
		object	$model 
	) : bool {
		if ( !preg_match( '/[a-zA-Z_]/', $table ) ) {
			return false;
		}
		
		$db	= static::getData();
		$params	= static::apply( $props, $model );
		$values	= static::parameters( $params, true );
		$sql	= "UPDATE $table $values WHERE id = :id;";
		
		return $db->editDb( $sql, $params );
	}
	
	/**
	 *  Delete existing item by ID
	 */
	protected function del( 
		object	$model, 
		string	$table 
	) : bool {
		if ( !preg_match( '/[a-zA-Z_]/', $table ) ) {
			return false;
		}
		
		$db	= static::getData();
		$sql	= "DELETE FROM $table WHERE id = :id;";
		
		return 
		$db->editDb( $sql, array( ':id' => $model->id ) );
	}
	
	/**
	 *  Prepare breadcrumb query for the given fields and table
	 */
	protected static function prepCrumbs( 
		array	$fields,
		string	$table 
	) {
		$pf	= 
		array_map( function( $v ) {
			return "p.$v AS $v";
		}, $fields );
		
		$subs	= 
		array(
			'{fields}'	=> implode( ', ', $fields ),
			'{pfields}'	=> implode( ', ', $pf ),
			'{table}'	=> $table
		);
		
		return strtr( self::CRUMB_SQL, $subs );
	}
	
	/**
	 *  Get a bunch of crumbs for an array of IDs
	 */
	protected static function getCrumbs(
		array	$fields,
		string	$table,
		array	$ids
	) : array {
		$sql	= static::prepCrumbs( $fields, $table );
		$db	= static::getData();
		$i	= array();
		foreach( $ids as $id ) {
			$i[] = array( ':id' => $id );
		}
		
		return $db->fromDbArray( $sql, $i );
	}
	
	/**
	 *  Return fields recursively matching any parents for a given ID
	 */
	protected static function getCrumb( 
		array	$fields,
		string	$table,
		int	$id
	) : array {
		$sql	= static::prepCrumbs( $fields, $table );
		$db	= static::getData();
		
		return $db->fromDb( $sql, array( ':id' => $id ) );
	}
	
	/**
	 *  Build pagination links
	 */
	protected function getPagination() : array {
		if ( empty( $this->total ) || empty( $this->index ) ) {
			return array();
		}
		
		return array();
	}
	
	/**
	 *  Returns false if any of the properties are not set
	 *  
	 *  @return bool True if a property was missing
	 */
	protected function missingProps( 
		object $model, 
		array $params 
	) : bool {
		foreach ( $params as $p ) {
			if ( !isset( $model->{$p} ) ) {
				return true;
			}
		}
	
		return false;
	}
	
	/**
	 *  Format parameters for insert or update
	 */
	private static function parameters( 
		array	$props,
		bool	$update	= false 
	) : string {
		
		// Skip the id key as that will be treated differently
		$keys	=
		array_filter( 
			array_keys( $props ), 
			function( $k ) {
				return 
				preg_match( '/[a-zA-Z_]/', $k ) && 
				$k != 'id';
			}
		);
		
		// key = :key
		if ( $update ) {
			$u =	array_map( function( $k ) { 
					return "$k = :$k";
				}, $keys );
			
			return 'SET ' . implode( ',', $u );
		}
		
		// (key1, key2) VALUES (:key1, :key2)
		return 
		'(' . implode( ',', $keys ) . ') VALUES ('.
		':' . implode( ',:', $keys ) . ')';
	}
	
	/**
	 *  Apply parameters if model has it defined
	 */
	private static function apply( 
		array	$props, 
		object	$model 
	) : array {
		$params = array();
		
		foreach( $props as $k => $v ) {
			if ( is_array( $v ) ) {
				if ( isset( $model->{$v[1]} ) ) {
					$params[":$v[0]"] = 
						$model->{$v[1]};
				}
				continue;
			}
			
			if ( isset( $model->{$v} ) ) {
				$params[":$k"] = $model->{$v};
			}
		}
		
		return $params;
	}
}

