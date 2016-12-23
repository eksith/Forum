<?php declare( strict_types = 1 );

namespace Forum\Models;

/**
 *  @package Forum\Models\Category
 *  @file Category.php
 *  @brief Board grouping category
 */
final class Category extends Model {
	
	public $title;
	
	public $slug;
	
	public $boards			= array();
	
	/**
	 *  @var string General search SQL
	 */
	const CATEGORY_SQL		= 
		"SELECT id, title, slug FROM categories {where}";
	
	
	/**
	 *  @var array Modifiable database fields
	 */
	private static $fields = array(
		'title',
		'slug',
		'sort_order',
		'status',
		'id'
	);
	
	
	/**
	 *  Get breadcrumbs for any parent boards for this one
	 */
	public function getBreadcrumbs() : array {
		if ( !isset( $this->id ) ) {
			return array();
		}
		if ( isset( $this->breadcrumbs ) ) {
			return $this->breadcrumbs;
		}
		
		// Categories don't nest
		$this->breadcrumbs = 
		array(
			array( 
				'id'		=> ( int ) $this->id,
				'title'		=> $this->title,
				'slug'		=> $this->slug,
				'status'	=> ( int ) $this->status
			)
		);
		
		return $this->breadcrumbs;
	}
	
	/**
	 *  Insert or edit this category
	 */
	public function save() {
		if ( isset( $this->id ) ) {
			parent::edit( self::$fields, $this, 'categories' );
		} else {
			$this->id = 
			parent::put( self::$fields, $this, 'categories' );
		}
	}
	
	/**
	 *  Delete this board by ID
	 */
	public function delete() : bool {
		if ( !isset( $this->id ) ) {
			return false;
		}
		return parent::del( $this, 'categories' );
	}
	
}

