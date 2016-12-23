<?php declare( strict_types = 1 );

namespace Forum\Models;

/**
 *  @package Forum\Models\Board
 *  @file Board.php
 *  @brief Discussion board
 */
use Forum;

final class Board extends Model {
	
	/**
	 *  @var string Full board title
	 */
	public $title;
	
	/**
	 *  @var string URL friendly board path
	 */
	public $slug;
	
	/**
	 *  @var string Plain text board description
	 */
	public $description;
	
	/**
	 *  @var int Number of topics this board has
	 */
	public $topic_count;
	
	/**
	 *  @var int Number of posts in reply to topics
	 */
	public $post_count;
	
	/**
	 *  @var int Parent board id this one belongs to
	 */
	public $parent_id;
	
	/**
	 *  @var int ID of last post
	 */
	public $last_id;
	
	/**
	 *  @var int Last author coalesced users.username, posts.author, or 'anonymous'
	 */
	public $last_author;
	
	/**
	 *  @var string Last author's avatar
	 */
	public $avatar;
	
	/**
	 *  @var object Category this board belongs to
	 */
	public $category;
	
	/**
	 *  @var object Parent board this board belongs to
	 */
	public $board;
	
	/**
	 *  @var array List of topics on this board
	 */
	public $topics			= array();
	
	/**
	 *  @var string General search SQL
	 */
	const BOARD_SQL		= 
	"SELECT boards.id AS id, boards.title AS title, 
		boards.slug AS slug, boards.description AS description, 
		boards.category_id AS category_id, 
		topic_count, post_count, last_id, 
		categories.title AS category_title, 
		categories.slug AS category_slug, 
		categories.status AS category_status, 
		{avatar}, {author}, 
		post_rank.id AS rank_id, 
		post_rank.upvotes AS rank_upvotes,
		post_rank.downvotes AS rank_downvotes,
		post_rank.treatment AS rank_treatment,
		post_rank.flags AS rank_flags 
		
		FROM boards 
		INNER JOIN categories ON 
			boards.category_id = categories.id 
		LEFT JOIN posts ON boards.last_id = posts.id 
		LEFT JOIN post_rank ON posts.id = post_rank.post_id 
		LEFT JOIN users ON posts.user_id = users.id 
		{where} ORDER BY boards.sort_order ASC;";
	
	/**
	 *  @var array Modifiable database fields
	 */
	private static $fields = array(
		'title',
		'slug',
		'description',
		'sort_order',
		'status',
		'id'
	);
	
	/**
	 *  Set the parent board (if any) and category
	 */
	public function __set( $name, $value ) {
		switch ( true ) {
			case Forum\Text::startsWith( $name, 'board_' ):
				static::setParentProp( 
					$name, $value, 'board', 'board', $this
				);
				break;
			
			case Forum\Text::startsWith( $name, 'category_' ):
				static::setParentProp( 
					$name, $value, 'category', 'category', $this
				);
				break;
		}
	}
	
	/**
	 *  Search by id, slug, category
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
						'WHERE boards.id = :search';
					break;
					
				case 'slug':
					$subs['{where}']	= 
						'WHERE boards.slug = :search';
					break;
					
				case 'category_id':
					$subs['{where}']	= 
						'WHERE categories.id = :search';
					break;
					
				case 'category_slug':
					$subs['{where}']	= 
						'WHERE categories.slug = :search';
					break;
					
				default:
					$subs['{where}']	= 
						'WHERE boards.title = :search';
				
			}
			
			$params[':search']	= $search['param'];
		} else {
			$subs['{where}']	= '';
		}
		
		$sql	= strtr( self::BOARD_SQL, $subs );
		$db	= parent::getData();
		
		return $db->fromDb( $sql, $params, false, __CLASS__ );
	}
	
	/**
	 *  Get breadcrumbs for any parent boards for this one
	 */
	public function getBreadcrumbs() : array {
		if ( !isset( $this->id ) ) {
			return array();
		}
		if ( isset( $this->breadcrumbs ) ) {
			return $this->breadcrumbs;
		}
		
		$this->breadcrumbs	= array();
		
		$this->breadcrumbs['categories']	= 
		$this->category->getBreadcrumbs();
		
		$this->breadcrumbs['boards']		= 
		parent::getCrumb( 
			array( 
				'id', 'parent_id', 'title', 'slug', 
				'description', 'status' 
			),
			'boards',
			( int ) $this->id
		);
		
		return $this->breadcrumbs;
	}
	
	/**
	 * Insert or edit this board
	 */
	public function save() {
		if ( isset( $this->id ) ) {
			parent::edit( self::$fields, $this, 'boards' );
		} else {
			$this->id = 
			parent::put( self::$fields, $this, 'boards' );
		}
	}
	
	/**
	 * Delete this board by ID
	 */
	public function delete() : bool {
		if ( !isset( $this->id ) ) {
			return false;
		}
		return parent::del( $this, 'boards' );
	}
}

