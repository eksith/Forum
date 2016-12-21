<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum/Text
 *  @file Text.php
 *  @brief String manipulation, text formatting, and matching helpers
 */
final class Text {
	
	/**
	 *  Format datetime into datetime-local input format
	 *  
	 *  @param string $pub UTC Timezone date
	 *  @return string
	 */
	public static function dateTimeFormat( $pub ) : string {
		if ( is_numeric( $pub ) ) {
			$t = intval( $pub );
		} else {
			$t = strtotime( $pub );
		}
		return date( 'Y-m-d', $t ) . 'T' . date( 'H:i', $t );
	}
	
	/**
	 *  Format datetime into a user friendly format
	 *  
	 *  @param string $date Plain string in integer convertible format
	 *  @return string
	 */
	public static function dateNice( string $pub ) : string {
		if ( is_numeric( $pub ) ) {
			$t = intval( $pub );
		} else {
			$t = strtotime( $pub );
		}
		return date( \DATE_NICE, $t );
	}
	
	/**
	 *  Current UTC Timestamp
	 */
	public static function utc() : string {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}
	
	/**
	 *  Time elapsed helper
	 */
	public static function elapsed( int $secs ) : string {
		$scale	= 
		array(
			'y' => $secs / 31556926 % 12,
			'w' => $secs / 604800 % 52,
			'd' => $secs / 86400 % 7,
			'h' => $secs / 3600 % 24,
			'm' => $secs / 60 % 60,
			's' => $secs % 60
		);
		
		foreach ( $scale as $k => $v ) {
			if ( $v > 0 ) {
				return $v . $k;
			}
		}
	}
	
	/**
	 *  Format parameters for saving complex documents
	 *  
	 *  @param array $params Document parameters
	 *  @return string
	 */
	public static function toJSON( array $params ) : string {
		return 
		json_encode( $params, 
			\JSON_HEX_QUOT | \JSON_HEX_TAG | 
			\JSON_UNESCAPED_SLASHES | 
			\JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT
		);
	}
	
	/**
	 *  Check if a string starts with a given text segment
	 *  
	 *  @param string $stack Searching in this text
	 *  @param string $needle Search for this segment
	 *  @return bool
	 */
	public static function startsWith( 
		string	$stack, 
		string	$needle, 
		bool	$exact		= true 
	) : bool {
		if ( $exact ) {
			return ( substr( 
				$stack, 0, strlen( $needle ) 
			) === $needle );
		}
		return ( substr( 
			$stack, 0, strlen( $needle ) 
		) == $needle );
	}
	
	/**
	 *  Check if a string ends with a given text segment
	 *  
	 *  @param string $stack Searching in this text
	 *  @param string $needle Search for this segment
	 *  @return bool
	 */
	public static function endsWith(
		string	$stack,
		string	$needle, 
		bool	$exact		= true 
	) : bool {
		if ( $exact ) {
			return ( substr( 
				$stack, -strlen( $needle ) 
			) === $needle );
		}
		return ( substr( 
			$stack, -strlen( $needle ) 
		) == $needle );
	}
	
	/**
	 *  Prevent directory traversal by limiting search
	 *  
	 *  @param string $dir Directory path to check
	 *  @param string $base Starting directory directory
	 *  
	 *  @return bool True if the folder is safe to read from
	 */
	public static function safeDirectory( 
		string	$dir, 
		string	$base 	= \STORAGE 
	) {
		if ( false !== strpos( $dir, '..' ) ) {
			return false;
		}
		if ( static::startsWith( $dir, $base ) ) {
			return true;
		}
		return false;
	}
	
	/**
	 *  General content filter
	 *  Strip unusable characters from raw text/html and conform to UTF-8
	 *  
	 *  @param string $html Raw HTML sent by the user
	 *  @param bool $entities Convert to HTML entities if true
	 *  
	 *  @return string
	 */
	public static function pacify( 
		string	$html, 
		bool	$entities = false 
	) : string {
		$html	= \iconv( 'UTF-8', 'UTF-8//IGNORE', $html );
		
		// Remove control chars except linebreaks/tabs etc...
		$html	= 
		preg_replace(
			'/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', 
			'', $html
		);
		
		// Non-characters
		$html	= 
		preg_replace(
			'/[\x{fdd0}-\x{fdef}]/u', '', $html
		);
		
		// UTF unassigned, formatting, and half surrogate pairs
		$html	= 
		preg_replace(
			'/[\p{Cs}\p{Cf}\p{Cn}]/u', '', $html
		);
		
		// Convert Unicode character entities?
		if ( $entities ) {
			$html = \mb_convert_encoding( 
					$html, 'HTML-ENTITIES', "UTF-8" 
				);
		}
		
		return trim( $html );
	}
	
	/**
	 *  Clean beginning and trailing spaces from parameter values 
	 */
	public static function trimValues( 
		array	&$values, 
		bool	$pacify		= false 
	) {
		if ( $pacify ) {
			array_filter( 
				$values, 
				function( string &$v ) { 
					$v = static::pacify( $v ); 
				} 
			);
			return;
		}
		array_filter( 
			$values, 
			function( string &$v ) { $v = trim( $v ); } 
		);
	}
}

