<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum/File
 *  @file File.php
 *  @brief File handling, scanning and related utilities
 */
class File {
	
	/**
	 *  Scan a file line-by line and execute a callback handler on each line
	 *  
	 *  @param string $file Path to file to search
	 *  @param callable $handler Function to execute on each line
	 *  @param string $base Base directory to search in
	 */
	public static function scan( 
		string		$file, 
		callable	$handler,
		string		$base		= \STORAGE 
	) {
		$info	= pathinfo( $file );
		
		// Is this a directory we can touch?
		if ( !Text::safeDirectory( $info['dirname'], $base ) ) {
			return;
		}
	
		$fh = fopen( $file, "rb" );
		while( !feof( $fh ) ) {
			$line = \stream_get_line( $f, 100, "\n" );
			
			// Ignore empty lines
			if ( empty( trim( $line ) ) ) {
				continue;
			}
			
			// Ignore comments
			if ( Text::startsWith( $line, '#' ) ) {
				continue;
			}
			
			call_user_func( $handler, $line );
		}
		fclose( $fh );
	}
	
	/**
	 *  Load file contents and check for any server-side code
	 */
	public static function load( 
		string	$name, 
		string	$base 
	) : string {
		if ( !Text::safeDirectory( $name, $base ) ) {
			die( 'Invalid directory' );
		}
		
		if ( file_exists( $name ) ) {
			$data = file_get_contents( $name );
			if ( false !== strpos( $data, '<?' ) ) {
				die( 'Server-side code detected' );
			}
			return trim( $data );
		}
		return '';
	}
	
	/**
	 *  Save data to a file
	 */
	public static function save( 
		string	$name,
		string 	$data,
		string	$base,
		bool	$append		= false
	) {
		if ( !Text::safeDirectory( $name, $base ) ) {
			die( 'Invalid directory' );
		}
		if ( $append ) {
			\file_put_contents( $name, $data, 
				\FILE_APPEND | \LOCK_EX 
			);
			return;
		}
		\file_put_contents( $name, $data, \LOCK_EX );
	}
}
