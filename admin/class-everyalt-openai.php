<?php
/**
 * Generate alt text via OpenAI Vision API using image as base64 (medium size).
 * No third-party server; works for localhost and htpasswd-protected sites.
 *
 * @package EveryAlt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_OpenAI {

	const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';
	const OPENAI_MODELS_URL = 'https://api.openai.com/v1/models';

	const DEFAULT_PROMPT = 'Describe this image in one short, clear sentence suitable for HTML alt text. Do not start with "This image shows" or similar. Output only the alt text, nothing else.';

	// Reasoning models (e.g. gpt-5-nano) use tokens for internal "thinking"; we need enough for reasoning + actual output.
	const DEFAULT_MAX_COMPLETION_TOKENS = 1024;

	/** Filter: change the model used in the API call. Default 'gpt-5-nano'. */
	const FILTER_MODEL = 'everyalt_openai_model';

	/** Filter: input token price in dollars per 1M tokens. Default 0.05 (gpt-5-nano). */
	const FILTER_INPUT_PRICE_PER_MILLION = 'everyalt_input_token_price_per_million';

	/** Filter: output token price in dollars per 1M tokens. Default 0.40 (gpt-5-nano). */
	const FILTER_OUTPUT_PRICE_PER_MILLION = 'everyalt_output_token_price_per_million';

	const DEFAULT_INPUT_PRICE_PER_MILLION  = 0.05;
	const DEFAULT_OUTPUT_PRICE_PER_MILLION = 0.40;

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
		$this->model   = apply_filters( self::FILTER_MODEL, 'gpt-5-nano' );
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
		$base64_encode_error = __( 'Image could not be encoded for the API. On some servers, base64 encoding fails for large images or due to PHP limits. Try a smaller image, or increase your server\'s PHP memory limit.', 'everyalt' );
		try {
			$base64 = base64_encode( $bytes );
		} catch ( \Throwable $e ) {
			return (object) array(
				'alt'          => null,
				'error'        => $base64_encode_error,
				'error_detail' => $e->getMessage(),
			);
		}
		if ( $bytes !== '' && $base64 === '' ) {
			return (object) array(
				'alt'          => null,
				'error'        => $base64_encode_error,
				'error_detail' => $path,
			);
		}
		$mime     = $this->get_mime_type( $path );
		$data_url = 'data:' . $mime . ';base64,' . $base64;

		$saved_prompt = get_option( 'every_alt_vision_prompt', '' );
		$prompt       = $saved_prompt !== '' ? $saved_prompt : self::DEFAULT_PROMPT;
		$prompt       = apply_filters( 'everyalt_vision_prompt', $prompt );

		$saved_max   = get_option( 'every_alt_max_completion_tokens', '' );
		$max_tokens  = $saved_max !== '' ? max( 1, (int) $saved_max ) : self::DEFAULT_MAX_COMPLETION_TOKENS;
		$max_tokens  = apply_filters( 'everyalt_max_completion_tokens', $max_tokens );
		$body = array(
			'model'                   => $this->model,
			'max_completion_tokens'   => (int) $max_tokens,
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
				'usage'        => '',
				'cost'         => '',
			);
		}
		$code     = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		$json     = json_decode( $body_raw, true );
		$usage_raw = isset( $json['usage'] ) ? $json['usage'] : null;
		$usage    = self::format_usage( $usage_raw );
		$cost     = $this->format_cost( $usage_raw );

		if ( $code < 200 || $code >= 300 ) {
			$detail = $body_raw;
			if ( isset( $json['error']['message'] ) ) {
				$detail = $json['error']['message'];
			}
			return (object) array(
				'alt'          => null,
				'error'        => 'OpenAI API returned ' . $code,
				'error_detail' => $detail,
				'usage'        => $usage,
				'cost'         => $cost,
			);
		}
		$content       = isset( $json['choices'][0]['message']['content'] ) ? $json['choices'][0]['message']['content'] : null;
		$finish_reason = isset( $json['choices'][0]['finish_reason'] ) ? $json['choices'][0]['finish_reason'] : '';

		// Content can be a string or an array of content parts (e.g. [ { "type": "text", "text": "..." } ] ).
		$alt = '';
		if ( is_string( $content ) ) {
			$alt = $content;
		} elseif ( is_array( $content ) ) {
			foreach ( $content as $part ) {
				if ( isset( $part['type'] ) && $part['type'] === 'text' && isset( $part['text'] ) ) {
					$alt .= $part['text'];
				}
			}
		}

		$alt = trim( $alt );

		if ( $finish_reason === 'length' ) {
			$length_message = __( 'Response was cut off (max tokens reached). Increase Max completion tokens in Settings to resolve this.', 'everyalt' );
			return (object) array(
				'alt'          => null,
				'error'        => $length_message,
				'error_detail' => $alt !== '' ? $alt : $body_raw,
				'usage'        => $usage,
				'cost'         => $cost,
			);
		}

		if ( $alt === '' ) {
			return (object) array(
				'alt'          => null,
				'error'        => 'OpenAI response had no content.',
				'error_detail' => $body_raw,
				'usage'        => $usage,
				'cost'         => $cost,
			);
		}
		$alt = sanitize_text_field( $alt );
		return (object) array( 'alt' => $alt, 'error' => null, 'error_detail' => null, 'usage' => $usage, 'cost' => $cost );
	}

	/**
	 * Format usage array from API response for display.
	 *
	 * @param array|null $usage
	 * @return string
	 */
	private static function format_usage( $usage ) {
		if ( ! is_array( $usage ) ) {
			return '';
		}
		$p = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : null;
		$c = isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : null;
		$t = isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : null;
		if ( $p === null && $c === null && $t === null ) {
			return '';
		}
		$parts = array();
		if ( $p !== null ) {
			$parts[] = 'Input Tokens: ' . $p;
		}
		if ( $c !== null ) {
			$parts[] = 'Output Tokens: ' . $c;
		}
		if ( $t !== null ) {
			$parts[] = 'Total: ' . $t;
		}
		return implode( ', ', $parts );
	}

	/**
	 * Format estimated cost for display in cents. Uses filtered input/output price per 1M tokens.
	 *
	 * @param array|null $usage API usage array with prompt_tokens, completion_tokens.
	 * @return string Formatted cost e.g. "0.0123¢" or empty string if no usage.
	 */
	private function format_cost( $usage ) {
		if ( ! is_array( $usage ) ) {
			return '';
		}
		$p = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
		$c = isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0;
		if ( $p === 0 && $c === 0 ) {
			return '';
		}
		$input_price  = (float) apply_filters( self::FILTER_INPUT_PRICE_PER_MILLION, self::DEFAULT_INPUT_PRICE_PER_MILLION );
		$output_price = (float) apply_filters( self::FILTER_OUTPUT_PRICE_PER_MILLION, self::DEFAULT_OUTPUT_PRICE_PER_MILLION );
		$cost_dollars = ( $p * $input_price / 1000000 ) + ( $c * $output_price / 1000000 );
		$cost_cents   = $cost_dollars * 100;
		return number_format( $cost_cents, 4 ) . '¢';
	}
}
