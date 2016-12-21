<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum\Server
 *  @file Server.php
 *  @brief Headers, redirection, and other server functions
 */
class Server extends Singleton {
	
	/**
	 *  Get a specific $_GET variable parameter
	 */
	public function getQuery( 
		string	$param, 
		int	$filter		= \FILTER_SANITIZE_SPECIAL_CHARS 
	) : string {
		$data = \filter_input( \INPUT_GET, $param, $filter );
		return empty( $data ) ? '' : trim( $data );
	}
	
	/**
	 *  Get filtered $_GET variables
	 */
	public function getQueryVars( array $filters ) : array {
		$data = $_GET;
		return $this->filteredArray( $data, $filters );
	}
	
	/**
	 *  Get a specific $_POST variable parameter
	 */
	public function getPosted( 
		string	$param, 
		int	$filter		= \FILTER_SANITIZE_SPECIAL_CHARS 
	) : string {
		$data = \filter_input( \INPUT_POST, $param, $filter );
		return empty( $data ) ? '' : trim( $data );
	}
	
	/**
	 *  Get filtered $_POST variables
	 */
	public function getPostedVars( array $filters ) : array {
		$data = $_POST;
		return $this->filteredArray( $data, $filters );
	}
	
	/**
	 *  Get a specific $_SERVER parameter
	 */
	public function getVar(
		string	$param, 
		int	$filter		= \FILTER_SANITIZE_SPECIAL_CHARS 
	) : string {
		$data = \filter_input( \INPUT_SERVER, $param, $filter );
		return empty( $data ) ? '' : trim( $data );
	}
	
	/**
	 *  Get filtered $_SERVER parameters
	 */
	public function getVars( array $filters ) : array {
		$data = $_SERVER;
		return $this->filteredArray( $data, $filters );
	}
	
	/**
	 *  Get a specific $_COOKIE variable
	 */
	public function getCookie( 
		string	$param, 
		int	$filter	= \FILTER_SANITIZE_SPECIAL_CHARS 
	) : string {
		$data = \filter_input( \INPUT_COOKIE, $param, $filter );
		return empty( $data ) ? '' : Text::pacify( $data );
	}
	
	/**
	 *  Get filtered $_COOKIE variables
	 */
	public function getCookieVars( array $filters ) : array {
		$data = $_COOKIE;
		return $this->filteredArray( $data, $filters, true );
	}
	
	/**
	 *  Generic filter function
	 */
	protected function filteredArray( 
		array	$data, 
		array	$filters, 
		bool	$pacify		= false
	) : array {
		Text::trimValues( $data, $pacify );
		return \filter_var_array( $data, $filters );
	}
	
	/**
	 *  Safety headers
	 *  
	 *  @param string $chk Content checksum
	 *  @param bool $send_csp Send Content Security Policy header
	 *  @param bool $type Send content type (html)
	 *  @return Return_Description
	 *  
	 *  @details Details
	 */
	public function preamble(
		string	$chk		= '', 
		bool	$send_csp	= true,
		bool	$send_type	= true
	) : bool {
		header_remove('X-Powered-By');
		if ( headers_sent() ) {
			return false;
		}
		
		if ( $send_type ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			// header( 'X-UA-Compatible: IE=Edge,chrome=1' );
		}
		header( 'X-XSS-Protection: 1; mode=block' );
		header( 'X-Content-Type-Options: nosniff' );
		
		// Frames should be handled via the CSP
		header( 'X-Frame-Options: deny' );
		
		// Served since check
		if ( ( time() - SERVED_LAST ) < SERVED_EXP ) {
			header( 'X-Since: ' . SERVED_LAST );
		}
		
		if ( FORCE_TLS ) {
			header( 'Strict-Transport-Security: ' . 
				'max-age=31536000; includeSubdomains;' 
			);
		}
		
		if ( $send_csp ) {
			$cjp = json_decode( CSP, true );
			$csp = 'Content-Security-Policy: ';
			foreach ( $cjp as $k => $v ) {
				$csp .= "$k $v;";
			}
			
			header( rtrim( $csp, ';' ) );
		} elseif ( !empty( $chk ) ) {
			header( 
				"Content-Security-Policy: default-src " .
				"'self' '{$chk}'" 
			);
		}
		return true;
	}
	
	/**
	 *  Generate content security policy compatible hash for 
	 *  inline content
	 *  
	 *  @param string $data Information to hash
	 *  @param string $is_file Information is a file path
	 *  @return string Base64 encoded sha256 hash
	 */
	public function cspHash( 
		string	$data, 
		bool	$is_file	= false 
	) : string {
		if ( $is_file ) {
			$hash = hash_file( 'sha256', $file, true );
		} else {
			$hash = hash( 'sha256', $file, true );
		}
		return 'sha256-' . base64_encode( $hash );
	}
	
	/**
	 *  Send the visitor to another path
	 */
	public function redirect( string $path, int $code = 302 ) {
		if ( headers_sent() ) {
			die();
		}
		
		ob_end_clean();
		$info	= pathinfo( $path );
		if ( empty( $info['dirname'] ) ) {
			die();
		}
		
		if ( Text::safeDirectory( $info['dirname'], '/' ) ) {
			$this->headerWithCode( 
				'Location: ' . $path, true, $code 
			);
		} 
		
		die();
	}
	
	/**
	 *  Send header with response code and end execution
	 *  
	 *  @param string $header Text content header
	 *  @param bool $scrub Replace previous similar headers
	 *  @param int $code HTTP Response status code
	 */
	public function headerWithCode( 
		string	$header, 
		bool	$scrub		= true, 
		int	$code		= 302
	) {
		$whitelist	= 
		array(
			// Here and good
			200, 202, 201, 
			
			// Maybe elsewhere, but still good
			300, 301, 302, 303, 304,
			
			// "Somewhere" and bad
			400, 401, 403, 404, 405, 406, 409, 412, 415, 
			
			// SON, I AM DISAPPOINT
			500, 501, 502, 503
		);
		
		if ( !in_array( $code, $whitelist ) ) {
			$code = 302;
		}
		
		exit( header( $header, $scrub, $code ) );
	}
}

