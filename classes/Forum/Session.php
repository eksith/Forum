<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum\Session
 *  @file Session.php
 *  @brief Session functionality
 */
class Session extends Singleton {
	
	/**
	 * Session owner and staleness marker
	 * 
	 * @link https://paragonie.com/blog/2015/04/fast-track-safe-and-secure-php-sessions
	 */
	public function sessionCanary( string $visit = '' ) : bool {
		$browser		= Browser::getInstance();
		$_SESSION['canary']	= 
		array(
			'ip'	=> $browser->getIP(),
			'sig'	=> $browser->getIPsig(),
			'exp'	=> time()  + SESSION_EXP,
			'visit'	=> 
			empty( $visit ) ? 
				\bin2hex( \random_bytes( 12 ) ) : $visit
		);
		
		return true;
	}
	
	/**
	 * Set session default values
	 */
	public function sessionCheck( bool $reset = false ) : bool {
		$this->session( $reset );
		
		if ( empty( $_SESSION['canary'] ) ) {
			$this->sessionCanary();
			return true;
		}
		
		// Check session staleness
		if ( time() > ( int ) $_SESSION['canary']['exp'] ) {
			$visit = $_SESSION['canary']['visit'];
			\session_regenerate_id( true );
			$this->sessionCanary( $visit );
			return true;
		}
		
		return false;
	}
	
	/**
	 *  Match the canary marker to the current user's IP address
	 *  
	 *  @return bool True if IP address matches. Defaults to false.
	 */
	public function canaryIP() : bool {
		$this->sessionCheck();
		
		if ( 0 === strcmp( 
			$_SESSION['canary']['ip'], 
			Browser::getInstance()->getIP() 
		) ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Scrub globals
	 */
	public function cleanGlobals() : bool {
		if ( !isset( $GLOBALS ) ) {
			return true;
		}
		foreach ( $GLOBALS as $k => $v ) {
			if ( 0 != strcasecmp( $k, 'GLOBALS' ) ) {
				unset( $GLOBALS[$k] );
			}
		}
		
		return false;
	}
	
	/**
	 * End current session activity
	 */
	public function cleanSession() : bool {
		if ( \session_status() === \PHP_SESSION_ACTIVE ) {
			\session_unset();
			\session_destroy();
			\session_write_close();
			return true;
		}
		
		return false;
	}
	
	/**
	 * Initiate a session if it doesn't already exist
	 * Optionally reset and destroy session data
	 */
	public function session( bool $reset = false ) : bool {
		if ( 
			\session_status() === \PHP_SESSION_ACTIVE && 
			!$reset 
		) {
			return true;
		}
		
		if ( \session_status() != \PHP_SESSION_ACTIVE ) {
			\session_name( 'is' );
			\session_start();
			return true;
		}
		
		if ( $reset ) {
			\session_regenerate_id( true );
			foreach ( array_keys( $_SESSION ) as $k ) {
				unset( $_SESSION[$k] );
			}
			return true;
		}
		
		return false;
	}
}


