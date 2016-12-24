<?php declare( strict_types = 1 );
/**
 *  @package Forum\Models\Treatment
 *  @file Treatment.php
 *  @brief Dictates how each post should be treated Determines styling, 
 *  	features, options etc...
 */

namespace Forum\Models;

class Treatment {
	
	/**
	 *  @var array Defaults treatment flags
	 */
	public $data = 
	array(
		'flag'		=> true, 	// Allow flagging by users
		'flagged'	=> false,	// Mark as flagged by mod
		'mod'		=> false,	// Post made by moderator
		'msg'		=> array(),	// Moderator comments
		'ad'		=> false,	// Advertisement/promoted
		'spam'		=> false,	// Unsolicited ad
		'nsfw'		=> false,	// Mark not safe for work
		'hide_media'	=> false,	// Obscure media with a warning
		'hide_links'	=> false,	// Obscure links with a warning
		'code'		=> false,	// Contains code
		'long'		=> false,	// Post is long (than average)
		'links'		=> 0,		// Number of links
		'images'	=> 0,		// Number of images
		'media'		=> 0,		// Number of videos/media
		'files'		=> 0		// Number of attachments
	);
	
	public function __construct( string $data ) {
		if ( !empty( $data ) ) {
			$this->data	= json_decode( $data, true, 4 );
		}
	}
	
	/**
	 * Enable/Disable flags
	 */
	public function setBoolean( string $name, bool $state ) {
		switch ( $name ) {
			case 'flag':
			case 'flagged':
			case 'mod':
			case 'ad':
			case 'spam':
			case 'nsfw':
			case 'hide_media':
			case 'hide_links':
			case 'code':
			case 'long':
				$this->data[$name]	= $state;
		}
	}
	
	/**
	 *  Set number values
	 */
	public function setNumber( string $name, int $state ) {
		switch ( $name ) {
			case 'links':
			case 'images':
			case 'media':
			case 'files':
				$this->data[$name]	= $state;
		}
	}
	
	/**
	 *  Add a moderator message
	 */
	public function addMessage( string $username, string $msg ) {
		$this->data['msg'][]	= 
			array( $username, utc(), $msg );
	}
	
	/**
	 *  Edit a moderator message. Appends user and utc time
	 *  
	 *  @param int $id Message id (position)
	 *  @param string $username Editor's username
	 *  @param string $msg New message
	 */
	public function editMessage( 
		int	$id, 
		string	$username, 
		string	$msg 
	) {
		$idx = count( $this->data['msg'] );
		if ( $id <= 0 || $id > $idx ) {
			return;
		}
		$i = $id-1; 
		if ( !isset( $this->data['msg'][$i] ) ) {
			return;
		}
		
		$data		= $this->data['msg'][$i];
		$data[2]	= $msg . "\n\nEdited by: " . 
					$username . ' on ' . utc();
	}
	
	/**
	 *  Format and return properties for storage
	 */
	public function save() : string {
		return 
		json_encode( 
			$this->data, 
			JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | 
			JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | 
			JSON_PRESERVE_ZERO_FRACTION
		);
	}
}

