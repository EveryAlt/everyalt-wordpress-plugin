<?php
/**
 * Simple encryption for storing the OpenAI API key in the database.
 * Uses AES-256-CBC with a key derived from AUTH_KEY.
 *
 * @package Every_Alt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_Encryption {

	const OPTION_KEY = 'every_alt_openai_key';

	/**
	 * Get a 32-byte key derived from AUTH_KEY for AES-256.
	 *
	 * @return string
	 */
	private static function get_encryption_key() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'everyalt-fallback-key-change-in-wp-config';
		return hash( 'sha256', $auth_key, true );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plaintext
	 * @return string Empty on failure, base64-encoded ciphertext on success.
	 */
	public static function encrypt( $plaintext ) {
		if ( ! is_string( $plaintext ) || $plaintext === '' ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $plaintext;
		}
		$key = self::get_encryption_key();
		$iv = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a string stored by encrypt().
	 *
	 * @param string $ciphertext Base64-encoded (iv + cipher).
	 * @return string Plaintext or empty on failure.
	 */
	public static function decrypt( $ciphertext ) {
		if ( ! is_string( $ciphertext ) || $ciphertext === '' ) {
			return '';
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return $ciphertext;
		}
		$raw = base64_decode( $ciphertext, true );
		if ( $raw === false || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$key = self::get_encryption_key();
		$plain = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return $plain !== false ? $plain : '';
	}
}
