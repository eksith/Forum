<?php declare( strict_types = 1 );

namespace Forum;

/**
 *  @package Forum\Crypto
 *  @file Crypto.php
 *  @brief Encryption/Decryption, and hashing functionality. Encryption 
 *  	requires OpenSSL to be installed and enabled. On Windows, you 
 *  	may need to uncomment extension=php_openssl.dll in php.ini
 */
class Crypto extends Singleton {
	
	/**
	 *  Encryption key size
	 */
	private $key_size	= 32;
	
	/**
	 *  Initialization vector size
	 */
	private $iv_size	= 16;
	
	/**
	 *  Maximum amount of data to be encrypted by this class
	 */
	const ENC_MAX_DATA	= 2048;
	
	/**
	 *  Maximum amount of raw data to be decrypted by this class
	 */
	const DEC_MAX_DATA	= 4096;
	
	/**
	 *  Data segment separator
	 */
	const SEPARATOR		= ':::';
	
	/**
	 *  Integrity check hash algorithm
	 */
	const SIG_ALGO		= 'tiger160,4';
	
	/**
	 *  Signature key hash algorithm
	 */
	const KEY_ALGO		= 'sha384';
	
	/**
	 *  Encryption algorithm
	 */
	const ENC_ALGO		= 'AES-256-CTR';
	
	/**
	 *  PBKDF2 Hash algorithm
	 */
	const PBK_ALGO		= 'tiger160,4';
	
	/**
	 *  PBKDF2 Number of iterations
	 */
	const PBK_ROUNDS	= 10000;
	
	/**
	 *  Encrypt, sign, and package data for transport
	 *  
	 *  @param string $data Content to be encrypted
	 *  @param string $key Encryption key
	 *  @return string
	 */
	public function encrypt( string $data, string $key ) : string {
		if ( mb_strlen( $data ) > self::ENC_MAX_DATA ) {
			die( 'Crypto: Max size exceeded' );
		}
		$iv	= \random_bytes( $this->iv_size );
		$padded	= $this->pkcsPad( $data, $this->iv_size );
		$data	= 
		\openssl_encrypt( $padded, self::ENC_ALGO, $key, 0, $iv );
		
		return $this->sign( $this->package( $data, $iv ), $key );
	} 
	
	/**
	 *  Decrypt a transport package with a given key
	 *  
	 *  @param string $raw Encrypted transport package (IV and all)
	 *  @param string $key Decryption key
	 *  @return string
	 */
	public function decrypt( string $raw, string $key ) : string {
		if ( mb_strlen( $raw ) > self::DEC_MAX_DATA ) {
			die( 'Crypto: Max size exceeded' );
		}
		if ( !$this->verify( $raw, $key ) ) {
			return '';
		}
		$parts = $this->unpackage( $raw );
		if ( empty( $parts ) ) {
			return '';
		}
		$data	=
		\openssl_decrypt( 
			$parts[1], self::ENC_ALGO, $key, 0, $parts[0] 
		);
		
		return $this->pkcsUnpad( $data );
	}
	
	/**
	 *  Create a post-encryption data signature with the key
	 */
	private function sign( string $data, string $key ) : string {
		$sig	= hash( self::KEY_ALGO, $key );
		$hash	= hash_hmac( self::SIG_ALGO, $data, $sig );
		return $hash . self::SEPARATOR . $data;
	}
	
	/**
	 *  Integrity check pre-decryption
	 */
	private function verify( 
		string &$data, 
		string $key 
	) : bool {
		if ( false === strpos( $data, self::SEPARATOR ) ) {
			return false;
		}
		
		// Split into signature and data segment
		$parts	= explode( self::SEPARATOR, $data, 3 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}
		
		// Sign given data segment with original key
		$hash = $this->sign( $parts[1], $key );
		
		// If sent data has the same signature
		if ( \hash_equals( $data, $hash ) ) {
			// Send back data part
			$data = $parts[1];
			return true;
		}
		
		return false;
	}
	
	/**
	 *  Extract packaged encrypted data and IV
	 */
	private function unpackage( string $raw ) : array {
		$check	= base64_decode( $raw, true );
		if ( false === $check ) {
			return array();
		}
		
		$parts	= explode( self::SEPARATOR, $check, 3 );
		if ( 2 !== count( $parts ) ) {
			return array();
		}
		
		$iv	= base64_decode( $parts[0], true );
		if ( false === $iv) {
			return array();
		}
		$data	= base64_decode( $parts[1], true );
		if ( false === $data ) {
			return array();
		}
		return array( $iv, $data );
	}
	
	/**
	 *  Create a storage/transfer safe package for the data and IV
	 */
	private function package( string $data, string $iv ) : string {
		return 
		base64_encode( base64_encode( $iv ) . 
			self::SEPARATOR . 
			base64_encode( $data ) );
	}
	
	/**
	 *  Remove padding
	 */
	private function pkcsUnpad( string $data ) : string {
		$i	= mb_strlen( $data ) - 1;
		$pad	= -ord( $data[$i] );
		return substr( $data, 0, $pad );
	}
	
	/**
	 *  Pad data prior to encryption to match algorithm
	 */
	private function pkcsPad( string $data, int $bsize ) : string {
		// Find the pad size for this block size and string length
		$pad = $bsize - ( mb_strlen( $data ) % $bsize );
		
		// Repeat the equivalent character up to the pad size
		return $data .  str_repeat( chr( $pad ), $pad );
	}
	
	
	/**
	 *  Password key derivation functions
	 */
	 
	/**
	 *  Packaged hash using key derivation function
	 *  
	 *  @param string $txt Parameter_Description
	 *  @param string $salt Optional random salt
	 *  @param string $algo Hashing algorithm
	 *  @param int $rounds Number of rounds to hash
	 *  @param int $kl Key length
	 *  @return string
	 */
	public static function pbk( 
		string	$txt, 
		string 	$salt		= '', 
		string	$algo		= self::PBK_ALGO, 
		int	$rounds		= self::PBK_ROUNDS, 
		int	$kl		= 128
	) : string {
		$salt	= empty( $salt ) ? 
				bin2hex( \random_bytes( 16 ) ) : $salt;
		
		$hash	= 
		\hash_pbkdf2( $algo, $txt, $salt, $rounds, $kl );
		$out	= array( $algo, $salt, $rounds, $kl, $hash );
		
		return base64_encode( implode( '$', $out ) );
	}
	
	/**
	 *  Verify derived key against plain text
	 *  
	 *  @param string $txt Raw text to check
	 *  @param string $hash Checking hash
	 *  @return bool
	 */
	public static function verifyPbk( 
		string	$txt, 
		string	$hash 
	) : bool {
		// Empty or excessively large hash? Reject
		if ( empty( $hash ) || mb_strlen( $hash, '8bit' ) > 600 ) {
			return false;
		}
		
		// Invalid base64 encoding
		$key	= base64_decode( $hash, true );
		if ( false === $key ) {
			return false;
		}
		
		// Check PBK components
		$key	= static::cleanPbk( $key );
		$k	= explode( '$', $key );
		
		if ( empty( $k ) || empty( $txt ) ) {
			return false;
		}
		if ( count( $k ) != 5 ) {
			return false;
		}
		if ( !in_array( $k[0], \hash_algos() , true ) ) {
			return false;
		}
		
		$pbk	= \hash_pbkdf2( $k[0], $txt, $k[1], 
				( int ) $k[2], ( int ) $k[3] );
		
		return \hash_equals( $k[4],  $pbk );
	}
	
	/**
	 *  Scrub the derived key of any invalid characters
	 *  
	 *  @param string $hash 
	 *  @return string Cleaned up hash string
	 */
	public static function cleanPbk( string $hash ) : string {
		return preg_replace( '/[^a-f0-9\$]+$/i', '', $hash );
	}
}

