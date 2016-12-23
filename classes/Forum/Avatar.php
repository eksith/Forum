<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum\Avatar
 *  @file Avatar.php
 *  @brief User profile image generator
 */
class Avatar extends Singleton {
	
	private static $starter	= 0;
	
	const DEFAULT_SEED	= '';
	
	const DEFAULT_SIZE	= 40;

	/**
	 *  Generate monster avatar
	 *  Special thanks 
	 *  	Andreas Gohr 
	 *  	Katherine Garner
	 *	Knut Aldrin Wikström
	 *  @link https://www.splitbrain.org/projects/monsterid
	 *  @link http://kathgarner.com/
	 *  
	 *  @param string $src Avatar save location
	 *  @param int $size Width in pixels
	 */
	public static function generate(  
		string	$src,
		string	$seed	= self::DEFAULT_SEED, 
		int	$size	= self::DEFAULT_SIZE
	) {
		// Seed random number
		static::seed( $seed );
		$parts = array(
			'legs' => static::rnd( 1, 5 ),
			'hair' => static::rnd( 1, 5 ),
			'arms' => static::rnd( 1, 5 ),
			'body' => static::rnd( 1, 15 ),
			'eyes' => static::rnd( 1, 15 ),
			'mouth'=> static::rnd( 1, 10 )
		);
		
		// Blank monster
		$monster	= \imagecreatetruecolor( 120, 120 );
		$white		= \imagecolorallocate( $monster, 255, 255, 255 );
		
		// Start with white background
		\imagefill( $monster, 0, 0, $white );
		
		// Assemble body parts
		foreach( $parts as $part => $num ){
			$seg	= $part . '_' . $num . '.png';
			$file	= AVATAR_PARTS . $seg;
			$im	= \imagecreatefrompng( $file );
			
			\imageSaveAlpha( $im, true );
			\imagecopy( $monster, $im, 0, 0, 0, 0, 120, 120 );
			\imagedestroy( $im );
			
			// Body color
			if ( $part == 'body' ){
				$color = 
				\imagecolorallocate(
					$monster, 
					static::rnd( 20,235 ), 
					static::rnd( 20,235 ), 
					static::rnd( 20,235 )
				);
				\imagefill( $monster, 60, 60, $color );
			}
		}
	
		// Resize image
		$out = \imagecreatetruecolor( $size, $size );
		\imagecopyresampled( 
			$out, $monster, 0, 0, 0, 0, 
			$size, $size, 120, 120 
		);
		
		\imagepng( $out, $src );
		\imagedestroy( $out );
		\imagedestroy( $monster );
	}
	
	/**
	 *  A simple seed for random avatars
	 */
	protected static function seed( string $val ) {
		$seed = hexdec( substr( md5( $val ), 0, 6 ) );
		static::$starter = 
		abs( intval( $seed ) ) % 9999999 + 1;
	}
	
	/**
	 *  Seeded random number (this should NOT be reused elsewhere)
	 */
	private static function rnd( 
		int	$min = 0, 
		int	$max = 9999999
	) : int {
		static::$starter = ( static::$starter * 125 ) % 2796203;
		return static::$starter % ( $max - $min + 1 ) + $min;
	}
	
	/**
	 *  Send the requested avatar (or blank one)
	 */
	public function send( string $avatar, int $size ) {
		$cache = Cache::getInstance();
		if ( $size <= 0 ) {
			$size = 40;
		}
		
		if ( empty( $avatar ) ) {
			$avatar = '';
		}
		// Create avatar file path
		$file	= $cache->buildPath( AVATAR_GEN, $avatar ) . 
				$avatar . '_x' . $size . '.png';
		
		// If it's not cached, generate it now
		if ( !file_exists( $file ) ) {
			static::generate( $file, $avatar, $size );
		}
		
		$cache->sendFile( $file, 'image' );
	}
	
	/**
	 *  Browse the avatars gallery and print current page
	 */
	public static function gallery( int $gal ) {
		$dir	= new \DirectoryIterator( AVATAR_FOLDER );
		$i	= 0;
		$p	= ( $gal - 1 ) * AVATAR_LIMIT; // Offset
		$e	= $p + AVATAR_LIMIT;
		$files	= array();
		
		foreach ( $dir as $f ) {
			if ( $f->isFile() ) {
				$n	= $f->getFileName();
				$info	= pathinfo( $n );
				
				// Skip non-images
				switch( $info['extension'] ) {
					case 'jpeg':
					case 'jpg':
					case 'bmp':
					case 'gif':
						break;
					default:
						continue;
				}
				
				// Skip this offset
				if ( $p > $i && $e < $i ) {
					$files[] = $n;
				}
				$i++;
			}
		}
		Server::getInstance()->preamble();
		echo '<ul><li>' . 
			implode( '</li><li>', $files ) . '</li></ul>';
		exit();
	}
}

