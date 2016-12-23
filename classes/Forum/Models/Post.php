<?php declare( strict_types = 1 );
namespace Forum\Models;

/**
 *  @package Forum\Models\Post
 *  @file Post.php
 *  @brief Main content entry
 */
use Forum;

class Post extends Model {
	
	/**
	 *  @var string Post title, also used for URL link text
	 */
	public $title;
	
	/**
	 *  @var string URL friendly path
	 */
	public $slug;
	
	/**
	 *  @var string HTML formatted post
	 */
	public $body;
	
	/**
	 *  @var string Unformatted text of the post stripped of HTML tags
	 */
	public $plain;
	
	/**
	 *  @var string IP address of the user posting
	 */
	public $ip;
	
	/**
	 *  @var string Tripcode/ID hash for anonymous users
	 */
	public $trip;
	
	/**
	 *  @var string Browser signature (not forum signature)
	 */
	public $signature;
	
	/**
	 *  @var array List of parsed references to and from this post
	 */
	public $refs;
	
	/**
	 *  @var string JSON encoded list of posts this post references
	 */
	public $refs_to;
	
	/**
	 *  @var string JSON encoded list of posts referencing this one
	 */
	public $refs_from;
	
	/**
	 *  @var int User id if from a registered user
	 */
	public $user_id;
	
	/**
	 *  @var string Coalesced users.username, posts.author, 'anonymous'
	 */
	public $author_name;
	
	/**
	 *  @var string User avatar E.G. Monster ID
	 */
	public $avatar;
	
	/**
	 *  @var int Number of replies to this post
	 */
	public $reply_count;
	
	/**
	 *  @var int Post this thread has been moved to
	 */
	public $moved_id;
	
	/**
	 *  @var object Message board this post belongs to
	 */
	public $board;
	
	/**
	 *  @var object Category of the board this post belongs to
	 */
	public $category;
	
	/**
	 *  @var object Sorting rank including treatment E.G. ignore flags
	 */
	public $rank;
	
	/**
	 *  @var string General search SQL (E.G. For index page)
	 */
	const POST_SQL		= 
	"SELECT posts.id AS id, posts.title AS title, 
		posts.body AS body, posts.parent_id AS parent_id, 
		posts.ip AS ip, posts.trip AS trip, 
		posts.created_at AS created_at, 
		posts.updated_at AS updated_at,
		posts.signature AS signature, 
		posts.status AS status, posts.slug AS slug, 
		posts.moved_id AS moved_id, 
		posts.reply_count AS reply_count,
		posts.board_id AS board_id, boards.title AS board_title, 
		boards.slug AS board_slug, users.id AS user_id, 
		{author}, {avatar}, categories.id AS category_id, 
		categories.title AS category_title, 
		categories.slug AS category_slug, 
		post_rank.id AS rank_id, 
		post_rank.upvotes AS rank_upvotes,
		post_rank.downvotes AS rank_downvotes,
		post_rank.treatment AS rank_treatment,
		post_rank.flags AS rank_flags
		
		FROM posts 
		INNER JOIN boards ON posts.board_id = boards.id 
		INNER JOIN categories ON boards.category_id = categories.id 
		INNER JOIN post_rank ON posts.id = post_rank.post_id 
		LEFT JOIN users ON posts.user_id = users.id {where} 
		ORDER BY post_rank.sort_order ASC, posts.id DESC {limits};";
	
	/**
	 *  @var array Modifiable database fields
	 */
	private static $fields = array(
		'title',
		'slug',
		'body',
		'plain',
		'refs_to',
		'refs_from',
		'ip',
		array( 'author' => 'author_name' ),
		'moved_id',
		'user_id',
		'avatar',
		'id'
	);
	
	/**
	 *  Set the board, category, and rank for this post
	 */
	public function __set( $name, $value ) {
		switch ( true ) {
			case Forum\Text::startsWith( $name, 'board_' ) :
				static::setParentProp( 
					$name, $value, 'board', 'board', $this 
				);
				break;
				
			case Forum\Text::startsWith( $name, 'category_' ) :
				static::setParentProp( 
					$name, $value, 'category', 'category', $this 
				);
				break;
				
			case Forum\Text::startsWith( $name, 'rank_' ) :
				Rank::setRank( $this, $name, $value );
				break;
		}
	}
	
	/**
	 *  Search by id, parent_id, title, body
	 */
	public static function find( 
		array	$search		= array() 
	) : array {
		$params			= array();
		$subs			= 
		array( 
			'{author}' => Post::authorField( 'last_author' ),
			'{avatar}' => Post::avatarField() 
		);
		
		if ( 
			isset( $search['search'] ) && 
			isset( $search['param'] ) 
		) {
			switch ( $search['search'] ) {
				case 'id':
					$subs['{where}']	= 
						'WHERE posts.id = :search';
					break;
					
				case 'parent':
					$subs['{where}']	= 
						'WHERE posts.parent_id = :search';
					break;
					
				case 'board':
					$subs['{where}']	= 
						'WHERE posts.board_id = :search';
					break;
					
				default:
					$subs['{where}']	= 
						'WHERE posts.title = :search';
					break;
				
			}
			
			$params[':search']	= $search['param'];
		} else {
			$subs['{where}']	= '';
		}
		
		if ( !isset( $search['index'] ) ) {
			$search['index']	= 1;
		}
		
		if ( !isset( $search['limit'] ) ) {
			$search['limit'] = \POSTS_PER_PAGE;
		}
		
		if ( !isset( $search['offset'] ) ) {
			$search['offset'] = 
				( $search['index'] - 1 ) * $search['limit'];
		}
		$subs['{limits}'] = 'LIMIT :limit OFFSET :offset';
		$params[':limit']	= $search['limit'];
		$params[':offset']	= $search['offset'];
		
		$sql	= strtr( self::POST_SQL, $subs );
		
		$db	= parent::getData();
		
		return $db->fromDb( $sql, $params, false, __CLASS__ );
	}
	
	/**
	 *  Author name selected from author field or username table
	 *  Defaults to the anonymous label
	 */
	public static function authorField(
		string	$field	= 'last_author'
	) : string {
		return 
		"\nCOALESCE( posts.author, users.username ) AS author_name";
	}
	
	/**
	 *  Avatar field 
	 */
	public static function avatarField() : string {
		return 
		"\nCOALESCE( posts.avatar, users.avatar ) AS avatar";
	}
	
	/**
	 *  Get number of posts in this topic (for pagination etc...)
	 *  
	 *  @param string $sql Counting SQL query
	 *  @param string $parent General parent ID field (filtered)
	 */
	protected function getTotal( 
		string	$sql, 
		string	$parent	= 'parent_id'
	) : int {
		if ( !empty( $this->total ) ) {
			return $this->total;
		}
		if ( !isset( $this->id ) ) {
			return 0;
		}
		$parent		= 
		preg_replace( parent::FIELD_FILTER, '_', $parent );
		
		$db		= parent::getData();
		$result		= 
		$db->fromDb( $sql, array( $parent => $this->id ) );
		
		$this->total	= isset( $result[0] )? $result[0] : 0;
		return $this->total;
	}
	
	/**
	 *  Get the category and board breadcrumbs for this post
	 */
	public function getBreadcrumbs() : array {
		if ( isset( $this->breadcrumbs ) ) {
			return $this->breadcrumbs;
		}
		if ( isset( $this->breadcrumbs ) ) {
			return $this->breadcrumbs;
		}
		if ( 
			!isset( $this->category ) || 
			!isset( $this->board ) 
		) {
			return array();
		}
		
		$this->breadcrumbs[]	= 
		$this->category->getBreadcrumbs();
		
		$this->breadcrumbs[]	= 
		$this->boards->getBreadcrumbs();
		
		return $this->breadcrumbs;
	}
	
	/**
	 *  Insert or edit this posts
	 */
	public function save() {
		if ( isset( $this->id ) ) {
			parent::edit( self::$fields, $this, 'posts' );
		} else {
			$this->id = 
			parent::put( self::$fields, $this, 'posts' );
		}
		
		if ( isset( $this->rank->treatment ) ) {
			//$t = $this->rank->save();
		}
	}
	
	/**
	 *  Delete this post by ID
	 */
	public function delete() : bool {
		if ( !isset( $this->id ) ) {
			return false;
		}
		return parent::del( $this, 'posts' );
	}

	/**
	 *  Check if this post needs the long version template
	 */
	public function isLong() : bool {
		if ( empty( $this->body ) ) {
			return false;
		}
		if ( mb_strlen( $this->body ) > \LONG_LIMIT ) {
			return true;
		}
		
		// Tags that tend to take up more vertical space
		if ( preg_match( 
			'#<img|code|pre|ol|ul|blockquote|table[^>]+>#ims', 
			$this->body 
		) ) {
			return true;
		}
		
		return false;
	}
}

