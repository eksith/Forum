<?php declare( strict_types = 1 );
/**
 *  @file Data.php Database connection helper 
 */
namespace Forum\Models;

final class Data {
	
	private static $db_types	= array();
	private static $db		= array();
	
	public function __construct() { }
	
	/**
	 * Create PDO object
	 */
	public function getDb( string $cxn = \DATA ) : \PDO {
		if ( isset( static::$db[$cxn] ) ) {
			return static::$db[$cxn];
		}
		
		try {
			$options		= 
			array(
				\PDO::ATTR_TIMEOUT		=> 
					DATA_TIMEOUT,
				\PDO::ATTR_DEFAULT_FETCH_MODE	=> 
					\PDO::FETCH_ASSOC,
				
				// Persistent connections may cause issues
				\PDO::ATTR_PERSISTENT		=> false,
				
				// Never emulate prepared statements
				\PDO::ATTR_EMULATE_PREPARES	=> false,
				\PDO::ATTR_ERRMODE		=> 
					\PDO::ERRMODE_EXCEPTION
			);
			
			// Parse out connection credentials from DSN
			$username		= '';
			$password		= '';
			$dsn			= 
			static::parseDSN( $cxn, $username, $password );
			
			$pdo			= 
			new \PDO( $dsn, $username, $password, $options );
			
			static::setDbType( $cxn );
			
			// Enable write-ahead-logging and foreign keys
			// for SQLite (the default database)
			if ( 'sqlite' == $this->getDbType( $cxn ) ) {
				$pdo->exec( 'PRAGMA foreign_keys = ON;' );
				$pdo->exec( 'PRAGMA journal_mode = WAL;' );
			}
			
			static::$db[$cxn]	= $pdo;
			return $pdo;
			
		} catch( \PDOException $e ) {
			die( MSG_DATA_ERROR );
		}
	}
	
	/**
	 *  Get the specified connection's database type
	 */
	public function getDbType( string $cxn ) : string {
		return isset( static::$db_types[$cxn] ) ? 
			static::$db_types[$cxn] : 'other';
	}
	
	/**
	 * Fetch all available records if query was successful 
	 * or an empty array on failure
	 */
	public function fromDb( 
		string	$sql, 
		array	$params		= array(),
		bool	$single		= false,
		string	$class		= '',
		string	$cxn		= \DATA
	) : array {
		$db	= $this->getDb( $cxn );
		$sql	= static::sqlFilter( $sql );
		$stm	= $db->prepare( $sql );
		$result	= empty( $params ) ? 
				$stm->execute() : 
				$stm->execute( $params );
		
		if ( $result ) {
			if ( $single ) {
				return 
				empty( $class ) ? 
					$stm->fetch() : 
					array( $stm->fetchObject( 
						$class, 
						\PDO::FETCH_PROPS_LATE 
					) );
			}
			
			return 
			empty( $class ) ? 
				$stm->fetchAll() :
				$stm->fetchAll( 
					\PDO::FETCH_CLASS | 
					\PDO::FETCH_PROPS_LATE, $class 
				);
		}
		
		return array();
	}
	
	/**
	 * Execute an array of searches for the same query
	 */
	public function fromDbArray(
		string	$sql, 
		array	$params,
		string	$class		= '',
		string	$cxn		= \DATA
	) : array {
		$db	= $this->getDb( $cxn );
		$sql	= static::sqlFilter( $sql );
		$stm	= $db->prepare( $sql );
		$c	= empty( $class );
		$result = array();
		
		foreach( $params as $param ) {
			if ( $stm->execute( $param ) ) {
				$result[] = $c ? 
					$stm->fetch() : 
					$stm->fetchObject( $class );
			}
		}
		
		return $result;
	}
	
	/**
	 * Insert record(s) into the database
	 */
	public function toDb( 
		string	$sql, 
		array	$params		= array(), 
		bool	$atomic		= false,
		string	$cxn		= \DATA
	) : int {
		$db	= $this->getDb( $cxn );
		$sql	= static::sqlFilter( $sql );
		
		$stm	= $db->prepare( $sql );
		$result	= false;
		
		if ( $atomic ) {
			try {
				$db->beginTransaction();
				$result =  $stm->execute( $params );
				$db->commit();
			} catch( \PDOException $e ) {
				$db->rollBack();
			}
		} else {
			try {
				$result =  $stm->execute( $params );
			} catch( \PDOException $e ) { }
		}
		
		if ( $result ) {
			return ( int ) $db->lastInsertId();
		}
		return 0;
	}
	
	/**
	 * Update row(s)
	 */
	public function editDb( 
		string	$sql, 
		array	$params		= array(), 
		bool	$atomic		= false,
		string	$cxn		= \DATA
	) : bool {
		$db	= $this->getDb( $cxn );
		$sql	= static::sqlFilter( $sql );
		$stm	= $db->prepare( $sql );
		$result	= false;
		
		if ( $atomic ) {
			try {
				$db->beginTransaction();
				$result = $stm->execute( $params );
				$db->commit();
			} catch( \PDOException $e ) {
				$db->rollBack();
			}
		} else {
			try {
				$result =  $stm->execute( $params );
			} catch( \PDOException $e ) { }
		}
		
		return $result;
	}
	
	/**
	 * Rudimentary cleaning for raw queries
	 */
	public static function sqlFilter( string $sql ) {
		return 
		preg_replace( '/[^\s\(\)\,\.\=\:\;_a-zA-Z0-9\>\<]+/', '', $sql );
	}
	
	/**
	 *  Extract login credentials from DSN
	 */
	private static function parseDSN( 
		string $dsn, 
		string &$username,
		string &$password
	) {
		/**
		 * Some people use spaces to separate parameters in DSN strings 
		 * and this is NOT standard
		 */
		$d = explode( ';', $dsn );
		$m = count( $d );
		$s = '';
		
		for ( $i = 0; $i < $m; $i++ ) {
			$n = explode( '=', $d[$i] );
			
			/**
			 * Empty parameter? Continue
			 */
			if ( count( $n ) <= 1 ) {
				$s .= implode( '', $n ) . ';';
				continue;
			}
			
			/**
			 * Username or password?
			 */
			switch( trim( $n[0] ) ) {
				case 'uid':
				case 'user':
				case 'username':
					$username = trim( $n[1] );
					break;
				
				case 'pwd':
				case 'pass':
				case 'password':
					$password = trim( $n[1] );
					break;
				
				/**
				 * Some other parameter? Leave as-is
				 */
				default:
					$s .= implode( '=', $n ) . ';';
			}
		}
		return rtrim( $s, ';' );
	}
	
	/**
	 *  Try to identify the database type
	 */
	private static function setDbType( string $cxn = \DATA ) {
		switch( true ) {
			case false !== stripos( $cxn, 'sqlite' ):
				static::$db_types[$cxn] = 'sqlite';
				break;
				
			case false !== stripos( $cxn, 'mysql' ):
				static::$db_types[$cxn] = 'mysql';
				break;
				
			case false !== stripos( $cxn, 'pgsql' ):
				static::$db_types[$cxn] = 'postgres';
				break;
				
			case false !== stripos( $cxn, 'firebird' ):
				static::$db_types[$cxn] = 'firebird';
				break;
		}
	}
}

