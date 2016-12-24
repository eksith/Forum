<?php declare( strict_types = 1 );

namespace Forum\Models\Forms;
use Forum;

/**
 *  @package Forum\Models\Form\CategoryForm
 *  @file CategoryForm.php
 *  @brief Forum category entry form
 */
class CategoryForm extends Form {
	
	private static $param_filter	= 
	array(
		'csrf'		=> \FILTER_SANITIZE_FULL_SPECIAL_CHARS,
		'title'		=> \FILTER_SANITIZE_FULL_SPECIAL_CHARS,
		'slug'		=> \FILTER_SANITIZE_FULL_SPECIAL_CHARS,
		'id'		=> 
		array(
			'filter'	=> \FILTER_SANITIZE_NUMBER_INT,
			'options'	=>
			array(
				'default'	=> 0,
				'min_range'	=> 0
			)
		)
	);
	
	public static function generate(  
		string	$mode, 
		array	$params
	) : string {
		$params['csrf'] = static::getCsrf( 'categoryform' );
		switch( $mode ) {
			case 'new' :
				return strtr( \TPL_CAT_FORM, $params );
				
			case 'edit' :
				return strtr( \TPL_CAT_EDIT_FORM, $params );
		}
		return '';
	}
	
	public static function process() : array {
		$data	= Forum\Server::getInstance()->getPostedVars( 
				static::$param_filter 
			);
		
		$filter		= Filter::getInstance();
		$data['title']  = 
		$filter->smartTrim( $data['title'] );
		
		if ( empty( $data['title'] ) ) {
			static::addError( MSG_TITLE_SIZE );
			return $data;
		}
		
		$data['title']  = $filter->smartTrim( $data['title'] );
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = 
			$filter->slugify( $data['title'], 'no-title' );
		} else {
			$data['slug'] = 
			$filter->slugify( $data['slug'], 'no-title' );
		}
		
		return $data;
	}
}

