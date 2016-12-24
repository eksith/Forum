<?php declare( strict_types = 1 );

namespace Forum\Models\Forms;
use Forum;
use Forum\Models;

/**
 *  @package Forum\Models\Forms\Form
 *  @file Form.php
 *  @brief Base form for all content processing
 */
abstract class Form extends Models\Model {
	
	private static $lang = array();
	
	protected static $errors		= array();
	
	public function save() {}
	
	public static function setLang( string $lang ) {
		self::$lang = loadLang( $lang );
	}
	
	public static function labelLang( string $label ) : string {
		// No language set? Load the default definitions
		if ( empty( self::$lang ) ) {
			self::setLang( LANGUAGE );
		}
		
		return array_key_exists( $label, self::$lang ) ? 
			self::$lang[$label] : $label;
	}
	
	/**
	 *  Generate HTML form sent to the User
	 */
	public static function generate(  
		string	$mode, 
		array	$params
	) : string {
		return array();
	}
	
	/**
	 *  Process returned form (different for each inheriting class)
	 */
	public static function process() : array {
		return array();
	}
	
	/**
	 *  Append to list of errors
	 */
	protected static function addError( string $msg ) {
		static::$errors[]	= $msg;
	}
	
	/**
	 *  Get all current errors (recommend exit after finding errors)
	 */
	public static function getErrors() : array {
		return static::$errors;
	}
	
	/**
	 * Generate a form-specific anti-cross-site-request forgery token
	 * 
	 * @param string $form Form name
	 * @param string $critical Certain values that cannot change
	 * 		Use this to prevent id traversal (eg board_id)
	 */
	public static function getCsrf(
		string	$form, 
		string	$critical	= ''
	) : string {
		$session	= static::getSession();
		$session->sessionCheck();
		
		$salt		= \bin2hex( random_bytes( 4 ) );
		
		// Generate a validating salt unique to this form
		$_SESSION['form_' . $form]	= $salt;
		
		return 
		Forum\Crypto::pbk( 
			$salt . $form . $critical . 
			$_SESSION['canary']['ip'] 
		);
	}
	
	/**
	 * Validate anti-cross-site-request forgery token for this form
	 */
	public static function validateCsrf( 
		string	$form,
		string	$hash,
		string	$critical	= ''
	) : bool {
		$session = static::getSession();
		$session->sessionCheck();
		if ( !isset( $_SESSION['form_' . $form] ) ) {
			return false;
		}
		
		$salt	= $_SESSION['form_' . $form];
		
		return 
		Forum\Crypto::verifyPbk( 
			$salt . $form . $critical . 
			$_SESSION['canary']['ip'], $hash 
		);
	}
	
	/**
	 * Applies a filter to a form input array and validates the CSRF token
	 */
	public static function validateForm( 
		string	$name, 
		array	$input, 
		string	$critical	= '' 
	) : bool {
		
		// CSRF token missing
		if ( empty( $data['csrf'] ) ) {
			static::addError( MSG_FORM_EXP );
			return false;
		}
		
		if ( static::validateCsrf( 
			$name, $data['csrf'], $critical 
		) ) {
			return true;
		}
		
		// CSRF failed
		static::addError( MSG_FORM_EXP );
		return false;
	}
	
	/**
	 *  Format given input values according to display mask
	 */
	public static function mask( 
		array		$data, 
		Filter	$filter 
	) {
		switch( $data[3] ) {
			case 'title':
				$mask	= $filter->smartTrim( $data[1] );
				$mask	= $filter->entities( $data[1] );
				break;
				
			case 'days':
				$mask	= ceil( ( int ) $data[1] / 86400 );
				break;
				
			case 'date':
				$mask	= date( 'Y, M d', ( int ) $data[1] );
				break;
				
			case 'datetime':
				$mask	= Forum\Text::dateTimeFormat( $data[1] );
				break;
				
			case 'slug':
				$mask	= $filter->slugify( $data[1] );
				break;
				
			case 'html':
				$mask	= $filter->clean( $data[1] );
				break;
				
			default:
				$mask = $data[1];
		}
		
		return $mask;
	}
	
	/**
	 *  Format a multiple choice selection
	 */
	public static function dropdownMask( array $data ) : string {
		if ( 'bool' == $data[2] ) {
			return $data[1] ? 
			'<option value="1" selected>true</option>' .
				'<option value="0">false</option>' : 
			
			'<option value="0" selected>false</option>' . 
				'<option value="1">true</option>';
		} 
		
		// Multiple selection
		$out ='';
		
		// Process $data[1] into array
		if ( is_array( $data[1] ) ) {
			foreach ( $data[1] as $k => $v ) {
				if ( !is_array( $v ) ) { // Selected
					$out	.= 
					"<option value=\"{$k}\">{$v}</option>";
					continue;
				}
				
				$out	.= 
				"<option value=\"{$k}\" selected>{$v[0]}</option>"; 
			}
		}
		
		return $out;
	}
	
	/**
	 *  Create an input field and label based on input type
	 */
	public static function field( 
		string		$name,
		array		$data, 
		Filter	$filter
	) : string {
		
		switch ( $data[2] ) {
		case 'num' :
			$mask	= static::mask( $data, $filter );
			return
			"<input type=\"number\" name=\"{$name}\" value=\"{$mask}\" />";
		
		// Textarea
		case 'area' :
			$mask	= static::mask( $data, $filter );
			if ( 'html' == $data[3] ) {
				return
				'<textarea data-content="html" rows="4" ' . 
					"cols=\"30\" name=\"{$name}\">{$mask}</textarea>";
			}
			return
			"<textarea rows=\"4\" cols=\"30\" name=\"{$name}\">{$mask}</textarea>";
		
		case 'bool' :
			$mask	= static::dropdownMask( $data );
			return
			"<span class=\"select\"><select name=\"{$name}\">
				{$mask}
			</select></span>";
		
		case 'datetime' :
			$mask	= static::mask( $data );
			return 
			"<input type=\"datetime-local\" name=\"{$name}\" " . 
				"value=\"{$mask}\" />";
				
		case 'password' : // Never reprint passwords
			return 
			"<input type=\"password\" name=\"{$name}\" />";
		
		// Text type input
		default :
			$mask	= static::mask( $data );
			return
			"<input type=\"text\" name=\"{$name}\" " . 
				"value=\"{$mask}\" class=\"full\" />";
		}
	}
	
	public static function form( 
		array	$params, 
		string	$title,
		string	$action
	) : string {
		
		$out	= 
		"<form action=\"{$action}\" method=\"post\">" . 
			"<legend>{$title}</legend>";
		
		$filter = Filter::getInstance();
		
		foreach( $params as $name => $data ) {
			$input	= static::field( $name, $data, $filter );
			$label	= static::labelLang( $data[0] );
			
			$out .= 
			"<p><label for=\"{$name}\">{$label}</label> " . 
				$input . "</p>\n";
			
			if ( 'html' == $data[3] ) {
				$out .= "<div class=\"wysiwyg\">{$mask}</div>\n";
			}
		}
		
		return 
		$out . 
		"<p><input type=\"submit\" value=\"Save\" /></p></form>\n";
	}
}

