<?php declare( strict_types = 1 );

namespace Forum;
use Forums\Models;
use Forum;

/**
 *  @package Forum\Auth
 *  @file Auth.php
 *  @brief Login and authentication helper
 */
class Auth {
	
	const TOKEN_HASH	= 'tiger160,4';	// Cookie token hash
	const TOKEN_SIZE	= 16;		// Cookie token bits
	const COOKIE_MIN	= 24;		// Minimum cookie size
	const COOKIE_MAX	= 1024;		// Maximum (never exceed)
	
	const USER_SUCCESS	= 1;		// User action was successful
	const ERROR_USER_EXISTS	= -1;		// Searched username already exists
	
	/**
	 *  Login a user via credentials
	 */
	public static function login( 
		string $username, 
		string $password 
	) : bool {
		$user	= new Models\User();
		
		// Login token sent to the user
		$token	= static::cookieToken();
		$hash	= static::cookieHash( $token );
		
		// If credentials match, set the user session and cookie
		if ( static::passAuth( 
			$username, $password, $hash, $user 
		) ) {
			static::applySession( $user );
			static::applyCookie( $user, $token );
			return true;
		}
		
		return false;
	}
	
	/**
	 *  Login user via cookie if one is set
	 */
	public static function cookieLogin() : bool {
		$user	= new Models\User();
		if ( static::viaCookie( $user ) ) {
			static::applySession( $user );
			static::refreshCookie();
			return true;
		}
		
		return false;
	}
	
	/**
	 *  Destroy the session and unset user cookie
	 */
	public static function logout( string $username ) : bool {
		$session = Session::getInstance();
		$session->sessionCheck( true );
		\setCookie( 
			'user', '', time() - COOKIE_EXP, COOKIE_PATH 
		);
		
		return true;
	}
	
	/**
	 *  Register a new user
	 */
	public static function register(
		string $username, 
		string $password 
	) : int {
		$params	= array( ':username' => $username );
		$find	= Models\User::find( 'username', $params, true );
		if ( !empty( $find ) ) {
			return self::ERROR_USER_EXISTS;
		}
		
		$user			= new Models\User();
		$user->username		= $username;
		$user->raw_password	= $password;
		$user->avatar		= static::sessionAvatar();	
		$user->save();
		
		return self::USER_SUCCESS;
	}
	
	protected static function viaCookie( Models\User &$user ) : bool {
		$lookup	= Server::getInstance()->getCookie( 'user' );
		if ( empty( $lookup ) ) {
			return false;
		}
		$len	= mb_strlen( $lookup );
		if ( 
			$len > self::COOKIE_MAX || 
			$len < self::COOKIE_MIN 
		) {
			return false;
		}
		
		$parts	= explode( '$', $lookup );
		if ( count( $parts ) != 2 ) {
			return false;
		}
		
		$hash	= static::cookieHash( $parts[1] );
		return static::cookieAuth( $parts[0], $hash, $user );
	}
	
	/**
	 *  Generate a random token to sent to the cookie
	 */
	protected static function cookieToken() : string {
		return bin2hex( \random_bytes( self::TOKEN_SIZE ) );
	}
	
	/**
	 *  Hash current token with the visitor's browser signature
	 */
	protected static function cookieHash( string $token ) : string {
		$browser	= Browser::getInstance();
		return 
		hash( 
			self::TOKEN_HASH, 
			$token . $browser->getIPsig() 
		);
	}
	
	/**
	 *  Create or retrieve the avatar seed for this user
	 *  
	 *  @return string Avatar generator seed
	 */
	public static function sessionAvatar() : string {
		// User already has a set avatar
		if ( static::isSession() ) {
			return $_SESSION['user']['avatar'];
		}
		if ( isset( $_SESSION['avatar'] ) ) {
			return $_SESSION['avatar'];
		}
		
		$browser	= Browser::getInstance();
		$ip		= $browser->getIPsig();
		
		$_SESSION['avatar'] = $ip;
		return $ip;
	}
	
	/**
	 *  Check session state for this user if logged in
	 *  
	 *  @param bool $ip Rematch IP address to session
	 *  @return true If authenticated. Defaults to false
	 */
	public static function isSession( bool $ip = false ) : bool {
		$session = Session::getInstance();
		$session->sessionCheck();
		if ( isset( $_SESSION['user'] ) ) {
			if ( !is_array( $_SESSION['user'] ) ) {
				return false;
			}
			
			if ( count( $_SESSION['user'] ) !== 5 ) { 
				return false;
			}
			
			// IP staleness or true
			return $ip ? $session->canaryIP() : true;
		}
		return false;
	}
	
	/**
	 *  Get currently logged in user's ID
	 *  
	 *  @return int User ID
	 */
	public static function sessionUserID() : int {
		$user = static::fromSession();
		if ( empty( $user ) ) {
			return 0;
		}
		
		return $user['id'];
	}
	
	/**
	 *  IP Address helper
	 */
	public static function getIP() : string {
		return Browser::getInstance()->getIP();
	}
	
	/**
	 *  Get the session array, if it exists
	 *  
	 *  @param bool $ip Rematch IP address to session
	 *  @return array
	 */
	public static function fromSession( bool $ip = false ) : array {
		if ( static::isSession( $ip ) ) {
			return $_SESSION['user'];
		}
		
		return array();
	}
	
	protected static function applySession( Models\User $user ) {
		$session = Session::getInstance();
		$session->sessionCheck();
		$_SESSION['user']	= 
		array( 
			'id'		=> $user->id,
			'username'	=> $user->username, 
			'avatar'	=> $user->avatar,
			'hash'		=> $user->hash,
			'status'	=> $user->status
		);
	}
	
	protected static function applyCookie( 
		Models\User	$user, 
		string		$token 
	) {
		$cookie	= $user->lookup . '$' . $token;
		static::refreshCookie( $cookie );
	}
	
	
	/**
	 *  Login authorization helpers
	 */
	
	/**
	 *  Refresh the current user cookie before expiration
	 */
	protected static function refreshCookie( string $cookie = '' ) {
		// Request to refresh current cookie
		if ( empty( $cookie ) ) {
			$cookie	= 
			Server::getInstance()->getCookie( 'user' );
			if ( empty( $cookie ) ) {
				return;
			}
		}
		$exp	= time() + COOKIE_EXP;
		\setCookie( 'user', $cookie, $exp, COOKIE_PATH );
	}
	
	/**
	 *  Find by cookie and browser signature (token)
	 */
	protected static function cookieAuth( 
		string $lookup, 
		string $token,
		Models\User &$user
	) : bool {
		$params	= array( ':lookup' => $lookup );
		$find	= Models\User::find( 'lookup', $params, true );
		if ( empty( $find ) ) {
			return false;
		}
		
		$user	= $find[0];
		if ( Crypto::verifyPbk( 
			$user-lookup . $token, $user->hash 
		) ) {
			return $user->status >= 0;
		}
		
		return false;
	}
	
	/**
	 *  Find by username and verify login
	 */
	protected static function passAuth( 
		string		$username, 
		string		$password, 
		string		$token,
		Models\User	&$user
	) : bool {
		$params	= array( ':username' => $username );
		$find	= Models\User::find( 'username', $params, true );
		if ( empty( $find ) ) {
			return false;
		}
		
		$user	= $find[0];
		$check	= 
		Models\User::verifyPassword( 
			$password, $user->password 
		);
		
		if ( $check ) {
			if ( Models\User::passNeedsRehash( 
				$user->password 
			) ) {
				$user->raw_password	= $password;
				$user->save();
			}
			$hash		= 
				Crypto::pbk( $user->lookup . $token );
			
			Models\User::updateHash( $user->id, $hash );
			$user->hash	= $hash;
			
			return ( $user->status >= 0 );
		}
		
		return false;
	}
}
