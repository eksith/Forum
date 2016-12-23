<?php declare( strict_types = 1 );
/**
 *  @package Forum\Cache
 *  @file Cache.php
 *  @brief Generated content caching
 */

namespace Forum;

class Cache extends Singleton {
	
	const CACHE_ALGO	= 'tiger160,4';
	
	/**
	 *  Send cache aware last modified stamp
	 *  @link https://php.net/manual/en/function.header.php#61903
	 */
	private function fileModTime( 
		Server $server,
		string $file, 
		int $ftime, 
		string $chk 
	) : bool {
		if ( false == $ftime ) {
			return true;
		}
		$mod	= $server->getVar( 'HTTP_IF_MODIFIED_SINCE' );
		
		$mtime	= empty( $mod ) ? $ftime : strtotime( $mod );
		
		
		$stamp	= 'Last-Modified: ' . 
				gmdate( DATE_GMT, $ftime ) . ' GMT';
		
		header( 'ETag: ' . $chk );
		header( 'Expires: ' . 
				gmdate( DATE_GMT, time() + CACHE_TIME ) . 
				' GMT' );
		
		// Modified mismatch or file times don't match
		// Send fresh response
		if ( empty( $mod ) || $mtime != $ftime ) {
			header( $stamp, true, 200 );
			return true;
		}
		
		// Not modified
		header( $stamp, true, 304 );
		return false;
	}
	
	
	/**
	 *  Create a storage path for generated content
	 *  
	 *  @param string $root Base directory to store the files (ends in /)
	 *  @param string $file Unique file name
	 *  @param bool $add Add last part of the original hash to path
	 *  @param bool $create Create the directories if they don't exist already
	 *  @param int $seg_size Segment path size E.G. /eaf/
	 *  
	 *  @return string Complete file path
	 */
	public function buildPath( 
		string	$root, 
		string	$file, 
		bool	$add		= true,
		bool	$create		= true, 
		int	$seg_size	= 4 
	) : string {
		$path	= hash( self::CACHE_ALGO, $file );
		$parts	= str_split( $path, $seg_size );
		$segs	= rtrim( $root, '/' );
		
		// If we just want the path
		if ( !$create ) {
			return 
			$segs . '/' . implode( '/', $parts ) . 
			'/' . $path . '/';
		}
		
		foreach( $parts as $part ) {
			$segs .= '/' . $part;
			if ( !is_dir( $segs ) ) {
				// Owner: read/write
				// Everyone else: read
				mkdir( $segs, 0644 );
			}
		}
		
		if ( $add ) {
			$segs .= '/' . $path;
		}
		
		if ( !is_dir( $segs ) ) {
			mkdir( $segs, 0644 );
		}
		
		return $segs . '/';
	}
	
	/**
	 *  General content cache saving
	 */
	public function save( string $key, string $data ) {
		$file = $this->buildPath( CACHE, $key, false );
		File::save( $file, $data, \CACHE );
	}
	
	/**
	 *  Get content from general cache
	 */
	public function get( string $key ) : string {
		$file = $this->buildPath( CACHE, $key, false, false );
		if ( !file_exists( $file ) ) {
			return '';
		}
		
		$data = \file_get_contents( $file );
		if ( false === $data ) {
			return '';
		}
		
		return $data;
	}
	
	/**
	 *  Send a file from the current cache by key (if it exists)
	 *  This method ends the request
	 */
	public function send( string $key, string $type ) {
		$file = $this->buildPath( CACHE, $key, false, true );
		if ( !file_exists( $file ) ) {
			kill( 'Not found', 404 );
		}
		$this->sendFile( $file, $type );
	}
	
	/**
	 *  Send a file directly to the user without further processing
	 *  This method ends the request
	 *  
	 *  @param string $file Location of the file (in a safe directory)
	 *  @param string $type General file type "image" etc...
	 */
	public function sendFile( string $file, string $type ) {
		$info = pathinfo( $file );
		
		// Check if we're allowed to prowl this directory
		if ( !Text::safeDirectory( $info['dirname'], \STORAGE ) ) {
			kill( 'Forbidden', 403 );
		}
		
		$size	= filesize( $file );
		$ftime	= filemtime( $file );
		$chk	= hash( 'tiger160,4', $file . $ftime . $size );
		
		$server	= Server::getInstance();
		$server->preamble( '', false, false );
		header( 'Cache-Control: max-age=' . \CACHE_TIME );
		
		// If we need to create it fresh
		if ( $this->fileModTime( 
			$server, $file, $ftime, $chk 
		) ) {
			switch ( $type ) {
				case 'image':
					$this->imageHeaders( $info );
					break;
					
				case 'doc':
					$this->docHeaders( $info );
					break;
				
				default:
					$this->genericHeader( $info );
			}
			
			header( 'Content-length: ' . $size );
			\ob_end_flush();
			\readfile( $file );
		}
		exit(); 
	}
	
	/**
	 *  Send an image file type headers
	 *  
	 *  @param array $info Path info
	 */
	public function imageHeaders( array $info ) {
		switch( strtolower( $info['extension'] ) ) {
			case 'png':
				header( "Content-type: image/png" );
				break;
				
			case 'jpeg':
			case 'jpg':
				header( "Content-type: image/jpeg" );
				break;
				
			case 'gif':
				header( "Content-type: image/gif" );
				break;
				
			case 'bmp':
				header( "Content-type: image/bmp" );
				break;
				
			default:
				header( "Content-type: application/octet-stream" );
		}
		
		$file = $this->cleanFilename( $info['basename'] );
		header( "Content-Disposition: inline; filename='{$file}'" );
	}
	
	/**
	 *  Send document type headers
	 *  
	 *  @param array $info Path info
	 */
	public function docHeaders( array $info ) {
		switch( strtolower( $info['extension'] ) ) {
			case 'pdf':
				header( "Content-type: application/pdf" );
				break;
				
			case 'csv':
				header( "Content-type: text/csv" );
				break;
				
			case 'text':
				header( "Content-type: text/plain" );
				break;
				
			case 'html':
				header( 'Content-Type: text/html; charset=utf-8' );
				break;
				
			case 'json':
				header( "Content-type: text/json" );
				break;
				
			default:
				header( "Content-type: application/octet-stream" );
		}
		
		$file = $this->cleanFilename( $info['basename'] );
		
		// Anything that isn't plain text should be sent as a download
		if ( 'text' == $info['extension'] ) {
			header( "Content-Disposition: inline; filename='{$file}'" );
		} else {
			header( "Content-Disposition: attachment; filename='{$file}'" );
		}
	}
	
	public function genericHeader( array $info ) {
		header( "Content-type: application/octet-stream" );
		$file = $this->cleanFilename( $info['basename'] );
		header( "Content-Disposition: attachment; filename='{$file}'" );
	}
	
	public function cleanFilename( string $name ) : string {
		$name = 
		preg_replace( 
			'([^\w\s\d\.\-_~,;:\[\]\(\)]|[\.]{2,})', 
			'', $name 
		);
		
		return preg_replace( '/\s{2,}/', ' ', $name );
	}
}

