<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum/Firewall
 *  @file Firewall.php 
 *  @brief Security and blocking related functions
 *  	Checks text lists for blocked IPs, hosts, user agents etc...
 */
class Firewall extends Singleton {
	
	/**
	 *  Apply the requested search filter against a type database (text)
	 *  and quickly end the request if a match is found
	 *  
	 *  @param string $type The parameter type
	 *  @param string $search The search parameter
	 *  Leave empty to use the current visitor's info (ip, ua, and uri)
	 */
	public function scan( 
		string	$type,
		string	$search	= ''
	) {
		$len = mb_strlen( $search );
		
		switch ( $type ) {
		
		// IP addresses
		case 'ip':
			if ( empty( $search ) ) {
				$search	= Browser::getInstance()->getIP();
			}
			
			// Still empty? Probably a Martian
			if ( empty( $search ) ) {
				kill( 'Forbidden', 403 );
			}
			
			$this->fireIP( $search, $len );
			break;
		
		// User agent strings
		case 'ua':
			if ( empty( $search ) ) {
				// No user agent? Definitely a bot
				if ( !isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
					kill( 'Forbidden', 403 );
				}
				
				// Raw user agent
				$search	= trim( $_SERVER['HTTP_USER_AGENT'] );
			}
			
			$this->fireUA( $search );
			break;
		
		// Path and query string fragments
		case 'uri':
			if ( empty( $search ) ) {
				// Check the raw request
				$search	= trim( $_SERVER['REQUEST_URI'] );
			}
			
			$this->fireURI( $search );
			break;
		
		// Domains and host names
		case 'host':
			$this->fireHost( $search, $len );
			break;
		}
	}
	
	
	/**#@+
	 *  Firewall scan helper functions
	 */
	
	/**
	 *  Check if an IP address starts with a given blocked fragment
	 *  Ends execution with 403 if match is found
	 *  
	 *  @param string $search Parameter_Description
	 *  @param int $len This avoids having to call mb_strlen every time
	 */
	private function fireIP( string $search, int $len ) {
		File::scan( 
			\STORAGE . 'badip.db', 
			function( string $parse ) use ( $search, $len ) {
				// Not matching an IP fragment
				if ( mb_strlen( $parse ) > $len ) {
					return;
				}
				if ( Text::startsWith( 
					$search, $parse, false 
				) ) {
					kill( 'Forbidden', 403 );
				}
			}
		);
	}
	
	/**
	 *  Check user agent against blocked set of string fragments
	 *  Also checks whether UA string includes junk text 
	 *  
	 *  @param string $search Full user agent
	 */
	private function fireUA( string $search ) {
		// If user agent is empty, it's usually a bot
		if ( empty( $search ) ) {
			kill( 'Forbidden', 403 );
		}
		
		// Clean characters that should never be in a UA string
		$scrub	= Text::pacify( $search );
		
		// Different after scrubbing?
		if ( 0 !== strcmp( $search, $scrub ) ) {
			// Legitimate user agents don't include junk
			kill( 'Forbidden', 403 );
		}
		File::scan( 
			\STORAGE . 'badua.db', 
			function( string $parse ) use ( $search ) {
				if ( false !== stripos( $search, $parse ) ) {
					kill( 'Forbidden', 403 );
				}
			}
		);
	}
	
	/**
	 *  Checks a page request against known exploit attempt fragments
	 *  
	 *  @param string $search Request URI
	 */
	private function fireURI( string $search ) {
		File::scan( 
			\STORAGE . 'baduri.db', 
			function( string $parse ) use ( $search ) {
				if ( false !== stripos( 
					$search, $parse 
				) ) {
					kill( 'Forbidden', 403 );
				}
			}
		);
	}
	
	/**
	 *  Checks a domain name or host name against known bad actors
	 *  Allows partial matches just as in the IP check
	 *  
	 *  @param string $search Host or domain name
	 *  @param int $len This avoids having to call mb_strlen every time
	 */
	private function fireHost( string $search, int $len ) {
		File::scan( 
			\STORAGE . 'badhost.db', 
			function( string $parse ) use ( $search, $len ) {
				// Not matching a host fragment
				if ( mb_strlen( $parse ) > $len ) {
					return;
				}
				if ( Text::endsWith( 
					$search, $parse, false 
				) ) {
					kill( 'Forbidden', 403 );
				}
			}
		);
	}
	
	/**#@-*/
}
