<?php declare( strict_types = 1 );
/**
 *  @package Forum\Models\Rank
 *  @file Rank.php
 *  @brief Post sorting and quality ranking
 */
namespace Forum\Models;

class Rank extends Model {
	
	/**
	 * @var int Post this rank belongs to
	 */
	public $post_id;
	
	/**
	 * @var int Total downvotes
	 */
	public $downvotes		= 0;
	
	/**
	 * @var int Total upvotes
	 */
	public $upvotes			= 0;
	
	/**
	 * @var int Moderator notification flags
	 */
	public $flags			= 0;
	
	/**
	 * @var string JSON for certain flags 
	 * E.G. Ignore future flagging
	 */
	public $treatment;
	
	/**
	 * @var array Modifiable database fields
	 */
	private static $fields = array(
		'downvotes',
		'upvotes',
		'sort_order',
		'id'
	);
	
	/**
	 *  Rank modification timeout
	 */
	const FREEZE			= 10000;
	
	const RANK_SQL			= 
	"SELECT id, post_id, upvotes, downvotes, flags, treatment 
		FROM post_rank 
		JOIN posts ON post_rank.post_id = posts.id";
	
	const SELECT_RANK		= 
	"SELECT {fields}, sort_order FROM post_rank
		JOIN ( SELECT {pfields} )";
	
	public function setId( int $id ) {
		$this->id	= $id;
	}
	
	public function save() {
		$chk = 
		$this->missingProps( 
			$this, 
			array( 'id', 'post_id', 'upvotes', 'downvotes', 
				'sort_order', 'created_at' )
		);
		if ( $chk ) {
			return;
		}
		
		$time	= strtotime( $this->created_at );
		
		// We're ignoring voting on old posts
		if ( ( time() - $time ) > self::FREEZE ) {
			return;
		}
		
		$this->sort_order = $this->calculate( $time );
		parent::edit( self::$fields, $this, 'post_rank' );
	}
	
	/**
	 *  Apply rank to this post
	 */
	public static function setRank( 
		Post &$model, 
		string $name, 
		string $value 
	) {
		if ( !isset( $model->rank ) ) {
			$model->rank = new Rank();
		}
		
		switch( true ) {
			case $name == 'rank_id':
				$model->rank->setId( ( int ) $value );
				break;
				
			case $name == 'rank_treatment':
				if ( !empty( $value ) ) {
					$model->rank->treatment = 
					new Treatment( json_decode( 
						$value, true, 4 
					) );
				}
				break;
			
			case $name == 'rank_upvotes':
				$model->rank->upvotes = ( int ) $value;
				break;
			
			case $name == 'rank_downvotes':
				$model->rank->downvotes = ( int ) $value;
				break;
			
			case $name == 'rank_flags':
				$model->rank->flags = ( int ) $value;
				break;
			
			case $name == 'rank_sort':
				$model->rank->sort_order = ( int ) $value;
				break;
		}
	}
	
	/**
	 *  Set voting difference
	 */
	public function vote( int $value ) {
		$amt = abs( $value );
		if ( $value < 0 ) {
			$this->downvotes	+= $amt;
		} else {
			$this->upvotes		+= $amt;
		}
		$this->save();
	}
	
	/**
	 *  TODO
	 */
	public function flag() {
		if ( isset( $this->treatment ) ) {
			
		}
		$this->save();
	}
	
	/**
	 *  Position algorithm
	 *  (Borrowed form Hacker News)
	 */
	public function calculate( int $time, float $gravity = 1.8 ) : int {
		$age	= abs( time() - $time );
		$votes	= max( 1, $this->upvotes + $this->downvotes ) ;
		return ( $votes - 1 ) / pow( ( $age + 2 ), $gravity );
	}
}

