<?php declare( strict_types = 1 );
/**
 *  @package Forum\Models\Forms\UserForm
 *  @file UserForm.php
 *  @brief Profile and registration form
 */
namespace Forum\Models\Forms;
use Forum;

class UserForm extends Form {
	
	/**
	 * @var array Form field parameters
	 */
	private static $param_filter	= 
	array(
		'csrf'		=> \FILTER_SANITIZE_FULL_SPECIAL_CHARS,
		'id'		=> 
		array(
			'filter'	=> \FILTER_SANITIZE_NUMBER_INT,
			'options'	=>
			array(
				'default'	=> 0,
				'min_range'	=> 0
			)
		),
		'username'	=> \FILTER_SANITIZE_ENCODED,
		'password'	=> \FILTER_UNSAFE_RAW,
		'password2'	=> \FILTER_UNSAFE_RAW,
		'email'		=> \FILTER_SANITIZE_EMAIL,
		'rem'		=> 
		array(
			'filter'	=> \FILTER_SANITIZE_NUMBER_INT,
			'options'	=>
			array(
				'default'	=> 0,
				'min_range'	=> 0,
				'max_range'	=> 1
			)
		),
		'terms'		=> 
		array(
			'filter'	=> \FILTER_SANITIZE_NUMBER_INT,
			'options'	=>
			array(
				'default'	=> 0,
				'min_range'	=> 0
			)
		),
		'bio'		=> \FILTER_UNSAFE_RAW,
		'deluser'	=> 
		array(
			'filter'	=> \FILTER_SANITIZE_NUMBER_INT,
			'options'	=>
			array(
				'default'	=> 0,
				'min_range'	=> 0,
				'min_range'	=> 1
			)
		)
	);
	
	/**
	 * Process a sent user into the object
	 */
	public static function getUser() {
		$data = static::process( $_POST );
		$user = new Models\User();
		
		if ( !empty( $data['id'] ) ) {
			// Find user by id
		}
	}
	
	/**
	 *  Generate HTML form
	 */
	public static function generate(  
		string	$mode, 
		array	$params
	) : string {
		$params['csrf'] = static::getCsrf( 'userform' );
		switch( $mode ) {
			case 'login':
				return strtr( \TPL_LOGIN_FORM, $params );
				
			case 'register':
				return strtr( \TPL_REGISTER_FORM, $params );
			
			case 'profile':
				return strtr( \TPL_PROFILE_FORM, $params );
			
			case 'changepass':
				return strtr( \TPL_PASSWORD_FORM, $params );
		}
		
		return '';
	}
	
	/**
	 * Process a sent user into the object
	 */
	public static function process() : array {
		$data	= Forum\Server::getInstance()->getPostedVars( 
				static::$param_filter 
			);
		
		if ( !empty( $data['username'] ) ) {
			$ulen = mb_strlen( $data['username'] );
			
			if ( $ulen < MIN_USER || $ulen > MAX_USER ) {
				static::addError( MSG_USER_INV );
				return $data;
			}
		}
		
		if ( !empty( $data['password'] ) ) {
			$plen = mb_strlen( $data['password'] );
			
			if ( $plen < MIN_PASS || $plen > MAX_PASS ) {
				static::addError( MSG_PASS_INV );
				return $data;
			}
		}
		
		if ( !empty( $data['password2'] ) ) {
			$p2len = mb_strlen( $data['password2'] );
			if ( $p2len < MIN_PASS || $p2len > MAX_PASS ) {
				static::addError( MSG_PASS_INV );
				return $data;
			}
			
			if ( empty( $data['password'] ) ) {
				die( MSG_PASS_INV );
			} elseif ( 0 !== strcmp( 	
				base64_encode( $data['password'] ), 
				base64_encode( $data['password2'] )
			) ) {
				static::addError( MSG_PASS_MATCH );
				return $data;
			}
		}
		
		$data['rem']	= empty( $data['rem'] ) ? false : true;
		
		if ( !empty( $data['bio'] ) ) {
			if ( mb_strlen( $data['bio'] ) > MAX_POST ) {
				static::addError( MSG_BIO_SIZE );
				return $data;
			}
			$filter		= Filter::getInstance();
			$data['bio']	= $filter->clean( $data['bio'] );
		}
		
		$data['avatar']	= Forum\Auth::sessionAvatar();
		return $data;
	}
	
	/**
	 *  Validate sent tripcode data
	 */
	public static function validateTrip( string $trip ) : array {
		if ( mb_strlen( $trip ) > ( MAX_USER + MAX_TRIP ) ) {
			static::addError( MSG_USER_INV );
			return array();
		}
		$trip	= static::tripcode( $trip );
		
		if ( count( $trip ) != 2 ) {
			static::addError( MSG_USER_INV );
			return array();
		}
		
		return $trip;
	}
	
	/**
	 *  Pick out the username and tripcode
	 */
	public static function tripcode( 
		string $trip 
	) : array {
		$pattern = 
		'/(.{1,' . MAX_USER . '})(#|\x{ff03}){1}(.*){1,' . 
			MAX_TRIP . '}/';
		
		if ( preg_match( $pattern, $trip, $matches ) ) {
			$filter = static::getFilter();
			$t	= static::genTrip( $matches[2] );
			if ( empty( $t ) ) {
				return array();
			}
			
			return 
			array( $filter->entities( $matches[0] ), '!' . $t );
		}
		return array();
	}
	
	/**
	 * Generate tripcode (DO NOT use for logins. Signatures only)
	 */
	public static function genTrip( 
		string $value 
	) : string {
		$filter = parent::getFilter();
		$value	= $filter->pacify( $value );
		
		// Strip special characters (diacritics etc...)
		$value	= preg_replace( '/[\x{202a}-\x{202e}]/', '', $value );
		
		// Strip Unicode surrogate pairs
		$value	= preg_replace( '/[\x{d800}-\x{dfff}]/', '', $value );
		
		// We need at least 3 surviving chars
		if ( mb_strlen( $value ) < 3 ) {
			return '';
		}
		
		// Convert to Shift-JIS
		$value	= mb_convert_encoding( $value, 'SHIFT_JIS', 'UTF-8' );
		
		// Generate 2 char salt
		$salt	= substr( $value . 'H.', 1, 2 );
		$salt	= preg_replace( '/[^\.-z]/', '.', $salt );
		$salt	= strtr( $salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef' );
		
		// We're only using 10 chars (hence not for logins)
		return substr( crypt( $value, $salt ), -10 );
	}
}

