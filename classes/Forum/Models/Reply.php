<?php declare( strict_types = 1 );

namespace Forum\Models;

/**
 *  @package Forum\Models\Reply
 *  @file Reply.php
 *  @brief Reply helper class 
 */
final class Reply extends Post {
	
	/**
	 * @var int Sorting (uses quality and sort_order )
	 */
	public $rank;
	
	/**
	 * @var int Count of upvotes ( 1 or above )
	 */
	public $upvotes;
	
	/**
	 * @var int Count of downvotes ( 0 or above )
	 */
	public $downvotes;
	
	/**
	 *  TODO: Hopefully before Jesus comes back
	 */
	public function vote( int $value ) {
		
	}
	
	/**
	 *  TODO: Ditto ^
	 */
	public function flag() : bool {
		if ( !isset( $this->id ) ) {
			return false;
		}
		
		return true;
	}
	
}
