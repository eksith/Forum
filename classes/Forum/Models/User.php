<?php declare( strict_types = 1 );
/**
 *  @package Forum\Models\User
 *  @file User.php
 *  @brief Profile and registration
 */
namespace Forum\Models;

class User extends Model {
	
	/**
	 * @var string Filtered login name
	 */
	public $username;
	
	/**
	 * @var string Hashed password
	 */
	public $password;
	
	/**
	 * @var string Password exactly as entered (never stored)
	 */
	public $raw_password;
	
	/**
	 * @var string Filtered user bio
	 */
	public $bio;
	
	/**
	 * @var string Contact address
	 */
	public $email;
	
	/**
	 * @var string Current login token (Never store this!)
	 */
	public $token;
	
	/**
	 * @var string Combined hash of the lookup hash and login token
	 */
	public $hash;
	
	/**
	 * @var string Cookie search hash
	 */
	public $lookup;
	
	/**
	 * @var string User selected avatar or generated E.G. Monster ID
	 */
	public $avatar;
	
	/**
	 * @var string General search SQL
	 */
	const USER_SQL		= 
	"SELECT users.id AS id, users.username AS username, 
		users.avatar AS avatar, users.bio AS bio, 
		users.email AS email, 
		users.created_at AS created_at, 
		users.updated_at AS updated_at, 
		users.status AS status, logins.hash AS hash, 
		logins.lookup AS lookup 
		
		FROM users 
		JOIN logins ON users.id = logins.user_id {where};";
	
	/**
	 * @var string User login by password
	 */
	const PASS_LOGIN_SQL	= 
	"SELECT users.id AS id, logins.hash AS hash,
		users.password AS password, users.avatar AS avatar, 
		users.status AS status, logins.lookup AS lookup 
		
		FROM users 
		JOIN logins ON users.id = logins.user_id 
			WHERE users.username = :username LIMIT 1;";
	
	/**
	 * @var string User login by cookie
	 */
	const COOKIE_LOGIN_SQL	= 
	"SELECT users.id AS id, logins.hash AS hash, 
		users.avatar AS avatar, users.status AS status, 
		logins.lookup AS lookup 
		
		FROM logins 
		JOIN users ON logins.user_id ON users.id
			WHERE logins.lookup = :lookup LIMIT 1;";
	
	const TOKEN_SQL		= 
	"UPDATE logins SET hash = :hash WHERE user_id IN ( 
		SELECT id FROM users WHERE username = :username LIMIT 1
	);";
	
	/**
	 * @var string Login hash algorithm
	 */
	const HASH_ALGO		= 'tiger160,4';
	
	/**
	 * @var array Modifiable database fields
	 */
	private static $fields = array(
		'username',
		'password',
		'bio',
		'email',
		'avatar',
		'id'
	);
	
	/**
	 * Query users table
	 */
	public static function find( 
		string	$mode,
		array	$params = array(), 
		bool	$single	= false, 
		string	$where	= ''
	) : array {
		$db	= parent::getData();
		switch( $mode ) {
			case 'lookup':
				$sql = self::COOKIE_LOGIN_SQL;
				break;
				
			case 'username':
				$sql = self::PASS_LOGIN_SQL;
				break;
				
			case 'profile':
				$sql = 
				strtr( self::USER_SQL, array( '{where}' => $where ) );
				break;
		}
		
		return 
		$db->fromDb( $sql, $params, $single, __CLASS__ );
	}
	
	public static function updateToken( 
		string $username, 
		string $hash
	) {
		$db	= parent::getData();
		$db->editDb( 
			self::TOKEN_SQL, 
			array( ':hash' => $hash, ':username' => $username )
		);
	}
	
	public static function updateHash( int $id, string $hash ) {
		$db	= parent::getData();
		$sql	= 'UPDATE logins SET hash = :hash WHERE id = :id;';
		
		$db->editDb( $sql, array( ':hash' => $hash, ':id' => $id ) );
	}
	
	public static function updateAvatar( int $id, string $avatar ) {
		$db	= parent::getData();
		$sql	= 'UPDATE users SET avatar = :avatar WHERE id = :id;';
		
		$db->editDb( $sql, array( ':avatar' => $avatar, ':id' => $id ) );
	}
	
	/**
	 * Insert or edit this user
	 */
	public function save() {
		if ( isset( $this->raw_password ) ) {
			$this->password	= 
			static::password( $this->raw_password );
		}
		
		if ( isset( $this->id ) ) {
			parent::edit( self::$fields, $this, 'users' );
		} else {
			$this->id = 
			parent::put( self::$fields, $this, 'users' );
		}
	}
	
	/**
	 * Delete this user by ID
	 */
	public function delete() : bool {
		if ( !isset( $this->id ) ) {
			return false;
		}
		return parent::del( $this, 'users' );
	}
	
	/**
	 * Hash password securely and into a storage safe format
	 * 
	 * @link https://paragonie.com/blog/2015/04/secure-authentication-php-with-long-term-persistence
	 */
	public static function password( 
		string	$password 
	) : string {
		return base64_encode(
		\password_hash(
			base64_encode(
				hash( 'sha384', $password, true )
			),
			\PASSWORD_DEFAULT
		) );
	}
	
	/**
	 * Verify user provided password against stored one
	 */
	public static function verifyPassword( 
		string	$password, 
		string	$stored 
	) : bool {
		$stored = base64_decode( $stored, true );
		if ( false === $stored ) {
			return false;
		}
		
		return 
		\password_verify(
			base64_encode( 
				hash( 'sha384', $password, true )
			),
			$stored
		);
	}
	
	/**
	 * Checks if the current password needs to be rehashed
	 */
	public static function passNeedsRehash( 
		string	$stored 
	) : bool {
		$stored = base64_decode( $stored, true );
		if ( false === $stored ) {
			return false;
		}
		
		return 
		\password_needs_rehash( $stored, \PASSWORD_DEFAULT );
	}
}

