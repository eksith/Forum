<?php declare( strict_types = 1 );
/**
 *  @package Forum\Models\Forms\PostForm
 *  @file PostForm.php
 *  @brief Topic/Reply entry and editing form
 */
namespace Forum\Models\Forms;
use Forum\Models;
use Forum;

class PostForm extends Form {
	
	/**
	 * @var array Form field parameters
	 */
	protected static $param_filter	= 
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
		'board'		=> 
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
		'trip'		=> \FILTER_UNSAFE_RAW,
		'body'		=> \FILTER_UNSAFE_RAW
	);
	
	/**
	 *  Generate HTML form (different for topics and replies)
	 */
	public static function generate(  
		string	$mode, 
		array	$params
	) : string {
		return '';
	}
	
	/**
	 * Filtering from array E.G. $_POST
	 */
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
		
		if ( empty( $data['trip'] ) ) {
			$data['author']	= '';
		} else {
			list( $author, $trip )	= 
				UserForm::validateTrip( $data['trip'] );
			if ( count( static::getErrors() ) ) {
				return $data;
			}
			
			$data['author']	= $author;
			$data['trip']	= $trip;
		}
		
		$data['ip']		= Forum\Auth::getIP();
		$data['avatar']		= Forum\Auth::sessionAvatar();
		$userID			= Forum\Auth::sessionUserID();
		$data['user_id']	= empty( $userID ) ? 
						null : $userID;
		
		$filter = Filter::getInstance();
		if ( empty( $data['title'] ) ) {
			if ( !empty( $data['body'] ) ) {
				$data['title']	= 
				$filter->fillTitle( $data['body'] );
			}
		} else {
			$data['title']  = 
				$filter->smartTrim( $data['title'] );
		}
		
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = 
			$filter->slugify( $data['title'], 'no-title' );
		} else {
			$data['slug'] = 
			$filter->slugify( $data['slug'], 'no-title' );
		}
		
		$data['body']	= $filter->clean( $data['body'] );
		$data['plain']	= strip_tags( $data['body'] );
		
		return $data;
	}
}

