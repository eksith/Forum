<?php declare( strict_types = 1 );

namespace Forum\Models;

/**
 *  @package Forum\Models\Topic
 *  @file Topic.php
 *  @brief Topic helper class 
 */
final class Topic extends Post {
	
	/**
	 * @var array List of replies to this topic
	 */
	private $replies;
	
	/**
	 *  Total replies count query
	 */
	const COUNT_SQL		= 
	"SELECT COUNT(id) FROM posts WHERE parent_id = :parent_id;";
	
	/**
	 *  Total number of replies
	 */
	public function getReplyCount() : int {
		if ( isset( $this->reply_count ) ) {
			return $this->reply_count;
		}
		return $this->getTotal( self::COUNT_SQL );
	}
	
	/**
	 *  Total number of replies
	 */
	public function getBreadcrumbs() : array {
		if ( isset( $this->breadcrumbs ) ) {
			return $this->breadcrumbs;
		}
		
		parent::getBreadcrumbs();
		
		$this->breadcrumbs[] = 
		array( 'id' => $this->id, 'title' => $this->title );
		
		return $this->breadcrumbs;
	}
	
	/**
	 *  Get the current topic meta info
	 */
	public static function getTopic( int $id ) {
		$subs	= 
		array( 
			'{author}' => static::authorField(),
			'{avatar}' => static::avatarField(),
			'{where}' => "WHERE posts.id = :id LIMIT 1"
		);
		
		// Apply replacements to query
		$sql	= strtr( self::POST_SQL, $subs );
		$db	= parent::getData();
		
		return 
		$db->fromDb( $sql, array( ':id' => $id ), true, __CLASS__ );
	}
	
	/**
	 *  Get this topic's replies with the current index
	 */
	public function getReplies() : array {
		if ( !empty( $this->replies ) ) {
			return $this->replies;
		}
		if ( !isset( $this->id ) ) {
			return array();
		}
		$limit	= \POSTS_PER_PAGE;
		$params = 
		array( 
			':parent_id'	=> $this->id,
			':limit'	=> $limit,
			':offset'	=> ( $this->index - 1 ) * $limit
		);
		
		// Start with author query replacement and avatar
		$subs	= 
		array( 
			'{author}'	=> static::authorField(),
			'{avatar}'	=> static::avatarField(),
			'{where}'	=> 
			" WHERE posts.parent_id = :parent_id 
				LIMIT :limit OFFSETE :offset"
		);
		
		$sql	= strtr( parent::POST_SQL, $subs );
		$db	= parent::getData();
		
		$this->replies = 
		$db->fromDb( $sql, $params, false, Reply::class );
		
		return $this->replies;
	}
}
