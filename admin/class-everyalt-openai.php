<?php
/**
 * Generate alt text via OpenAI Vision API using image as base64 (medium size).
 * No third-party server; works for localhost and htpasswd-protected sites.
 *
 * @package Every_Alt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_OpenAI {

	const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
	const OPENAI_MODELS_URL = 'https://api.openai.com/v1/models';

	const DEFAULT_PROMPT = 'Describe this image in one short, clear sentence suitable for HTML alt text. Do not start with "This image shows" or similar. Output only the alt text, nothing else.';

	/** @var string */
	private $api_key;

	/** @var string Model name (e.g. gpt-4o-mini or gpt-5-nano). */
	private $model;

	/**
	 * Validate an OpenAI API key by making a lightweight request.
	 *
	 * @param string $api_key The key to validate.
	 * @return bool True if the key is valid and has API access.
	 */
	public static function validate_api_key( $api_key ) {
		if ( ! is_string( $api_key ) || trim( $api_key ) === '' ) {
			return false;
		}
		$response = wp_remote_get(
			self::OPENAI_MODELS_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . trim( $api_key ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
		$this->model   = apply_filters( 'everyalt_openai_model', 'gpt-5-nano' );
	}

	/**
	 * Get the file path for the best available image size (prefer medium to save tokens).
	 *
	 * @param int $attachment_id
	 * @return string|null Full path or null.
	 */
	private function get_image_path_for_vision( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $meta['sizes'] ) ) {
			return $file;
		}
		// Prefer 'medium'; fallback to 'large', then 'thumbnail', then full.
		$order = array( 'medium', 'large', 'thumbnail' );
		$dir   = dirname( $file );
		foreach ( $order as $size ) {
			if ( ! empty( $meta['sizes'][ $size ]['file'] ) ) {
				$path = $dir . '/' . $meta['sizes'][ $size ]['file'];
				if ( is_readable( $path ) ) {
					return $path;
				}
			}
		}
		return $file;
	}

	/**
	 * Get mime type for the image (for data URL).
	 *
	 * @param string $path
	 * @return string
	 */
	private function get_mime_type( $path ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$map = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);
		return isset( $map[ $ext ] ) ? $map[ $ext ] : 'image/jpeg';
	}

	/**
	 * Generate alt text for an attachment using OpenAI Vision. Image is sent as base64.
	 *
	 * @param int $attachment_id
	 * @return object { alt: string|null, error: string|null, error_detail: string|null } Always returns object; check ->error for failure.
	 */
	public function generate_alt( $attachment_id ) {
		$path = $this->get_image_path_for_vision( $attachment_id );
		if ( ! $path ) {
			return (object) array( 'alt' => null, 'error' => 'Could not get image path for attachment.', 'error_detail' => '' );
		}
		$bytes = file_get_contents( $path );
		if ( $bytes === false ) {
			return (object) array( 'alt' => null, 'error' => 'Could not read image file.', 'error_detail' => $path );
		}
		$base64   = base64_encode( $bytes );
		$mime     = $this->get_mime_type( $path );
		$data_url = 'data:' . $mime . ';base64,' . $base64;

		$saved_prompt = get_option( 'every_alt_vision_prompt', '' );
		$prompt       = $saved_prompt !== '' ? $saved_prompt : self::DEFAULT_PROMPT;
		$prompt       = apply_filters( 'everyalt_vision_prompt', $prompt );

		$body = array(
			'model'      => $this->model,
			'max_completion_tokens' => 300,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $prompt,
						),
						array(
							'type'      => 'image_url',
							'image_url' => array(
								'url'    => $data_url,
								'detail' => 'low',
							),
						),
					),
				),
			),
		);

		$response = wp_remote_post(
			self::OPENAI_URL,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return (object) array(
				'alt'          => null,
				'error'        => 'Request failed: ' . $response->get_error_message(),
				'error_detail' => '',
			);
		}
		$code     = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			$detail = $body_raw;
			$json   = json_decode( $body_raw, true );
			if ( isset( $json['error']['message'] ) ) {
				$detail = $json['error']['message'];
			}
			return (object) array(
				'alt'          => null,
				'error'        => 'OpenAI API returned ' . $code,
				'error_detail' => $detail,
			);
		}
		$json = json_decode( $body_raw, true );
		if ( empty( $json['choices'][0]['message']['content'] ) ) {
			return (object) array(
				'alt'          => null,
				'error'        => 'OpenAI response had no content.',
				'error_detail' => substr( $body_raw, 0, 500 ),
			);
		}
		$alt = trim( $json['choices'][0]['message']['content'] );
		$alt = sanitize_text_field( $alt );
		return (object) array( 'alt' => $alt, 'error' => null, 'error_detail' => null );
	}
}
