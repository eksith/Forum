<?php
/**
 *  @package Forum\SHandler
 *  @file SHandler.php 
 *  @brief Fallback session handler for when Suhosin isn't available
 *  @link https://secure.php.net/manual/en/class.sessionhandlerinterface.php
 */

namespace Forum;
use Forum\Models;

class SHandler implements \SessionHandlerInterface {
	
	private $id;
	protected $db;
	
	private function getDb() {
		if ( isset( $this->db ) ) {
			return $this->db;
		}
		$this->db	= new Models\Data();
		return $this->db;
	}
	
	public function open( $save_path, $session_name ) {
		return true;
	}
	
	public function close() : bool {
		return true;
	}
	
	/**
	 *  Read the stored session from the database (optionally decrypt)
	 */
	public function read( $id ) {
		$sql	= 'SELECT id, skey, sdata FROM sessions 
				WHERE id = :id LIMIT 1;';
		$db	= $this->getDb();
		$query	= $db->fromDb( 
				$sql, array( ':id' => $id ), 
				false, '', \SESSION_DATA 
			);
		
		if ( empty( $query ) ) {
			return '';
		}
		$info		= $query[0];
		$this->id	= $info['id'];
		if ( useCrypto() ) {
			return 
			$this->decrypt( $info['sdata'], $info['skey'] );
		}
		
		return $info['sdata'];
	}
	
	/**
	 *  Save the current session data (optionally, encrypt it)
	 */
	public function write( $id, $data ) {
		if ( useCrypto() ) {
			$skey	= base64_encode( \random_bytes( 8 ) );
			$data	= $this->encrypt( $data, $skey );
		} else {
			$skey	= '';
		}
		
		$params = array( 
				':skey'	=> $skey, 
				':sdata'=> $data,
				':id'	=> $id
			);
		
		$db	= $this->getDb();
		if ( isset( $this->id ) ) {
			$sql	= 
			"UPDATE sessions SET skey = :skey, 
				sdata = :sdata WHERE id = :id";
			$db->editDb( $sql, $params, false, \SESSION_DATA );
		} else {
			$sql	=
			"INSERT INTO sessions (skey, sdata, id) 
				VALUES (:skey, :sdata, :id);";
			$db->toDb( $sql, $params, false, \SESSION_DATA );
		}
		
		return true;
	}
	
	/**
	 *  Delete the current session from the database
	 */
	public function destroy( $id ) {
		$sql =	'DELETE FROM sessions WHERE id = :id;';
		
		$db	= $this->getDb();
		$db->editDb( 
			$sql, 
			array( ':id' => $id ), 
			false, 
			\SESSION_DATA 
		);
		return true;
	}
	
	/**
	 *  Garbage collection is handled off to the database
	 */
	public function gc( $age ) {
		return true;
	}
	
	private function decrypt( $data, $skey ) {
		$key		= $this->getKey( $skey );
		$crypto		= Crypto::getInstance();
		return $crypto->decrypt( $data, $key );
	}
	
	private function encrypt( $data, $skey ) {
		$key		= $this->getKey( $skey );
		$crypto		= Crypto::getInstance();
		return $crypto->encrypt( $data, $key );
	}
	
	/**
	 *  The encryption key is a composite of a random stored part 
	 *  and the user's browser signature. Both parts are needed to
	 *  decrypt the data.
	 *  
	 *  The composite key isn't stored anywhere
	 */
	private function getKey( $skey ) {
		$browser	= Browser::getInstance();
		return 
		hash_hmac( 'sha256', $skey,  $browser->getIPsig() );
	}
}
