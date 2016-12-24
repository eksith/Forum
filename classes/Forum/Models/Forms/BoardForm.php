<?php declare( strict_types = 1 );

namespace Forum\Models\Forms;
use Forum;

/**
 *  @package Forum\Models\Forms\BoardForm
 *  @file BoardForm.php
 *  @brief Discussion board entry editing form
 */
class BoardForm extends Form {
	
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
		),
		'parent'		=> 
		array(
			'filter'	=> \FILTER_SANITIZE_NUMBER_INT,
			'options'	=>
			array(
				'default'	=> 0,
				'min_range'	=> 0
			)
		),
		'body'		=> \FILTER_UNSAFE_RAW
	);
	
	public static function generate(  
		string	$mode, 
		array	$params
	) : string {
		$params['csrf'] = static::getCsrf( 'boardform' );
		switch( $mode ) {
			case 'new' :
				return strtr( \TPL_BOARD_FORM, $params );
				
			case 'edit' :
				return strtr( \TPL_BOARD_EDIT_FORM, $params );
		}
		return '';
	}
	
	public static function process() : array {
		$data	= Forum\Server::getInstance()->getPostedVars( 
				static::$param_filter 
			);
		if ( 
			empty( $data['body'] ) || 
			mb_strlen( $data['body'] ) > \MAX_POST 
		) {
			static::addError( MSG_POST_SIZE );
			return $data;
		}
		
		$filter	= Filter::getInstance();
		
		$filter	= Filter::getInstance();
		
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
		
		$data['body']	= $filter->clean( $data['body'] );
		return $data;
	}
}

