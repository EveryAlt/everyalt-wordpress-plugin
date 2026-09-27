<?php
/**
 * Generate alt text via the selected provider's OpenAI-compatible vision API (OpenAI, Gemini, or
 * DeepInfra; see Every_Alt_Providers), sending the image as base64 (medium size).
 * No image URL is fetched by the provider, so this works for localhost and htpasswd-protected sites.
 *
 * @package EveryAlt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_OpenAI {

	const DEFAULT_PROMPT = 'Describe this image in one short, clear sentence suitable for HTML alt text. Do not start with "This image shows" or similar. Output only the alt text, nothing else.';

	const DEFAULT_TITLE_PROMPT = 'Write a short, descriptive title for this image, suitable for a WordPress image title (about 3 to 6 words, Title Case). Do not use quotation marks, a trailing period, or phrases like "This image shows". Output only the title, nothing else.';

	// Reasoning models use tokens for internal "thinking"; we need enough for reasoning + actual output.
	const DEFAULT_MAX_COMPLETION_TOKENS = 1024;

	/** Filter: change the model ID sent to the provider. Args: ( string $model_id, string $model_slug ). */
	const FILTER_MODEL = 'everyalt_model';

	/** Filter: input token price in dollars per 1M tokens. Args: ( float $price, string $model_slug ). */
	const FILTER_INPUT_PRICE_PER_MILLION = 'everyalt_input_token_price_per_million';

	/** Filter: output token price in dollars per 1M tokens. Args: ( float $price, string $model_slug ). */
	const FILTER_OUTPUT_PRICE_PER_MILLION = 'everyalt_output_token_price_per_million';

	/** @var string */
	private $api_key;

	/** @var array Model definition from Every_Alt_Providers::models(), with 'slug'. */
	private $model_def;

	/** @var array Provider definition from Every_Alt_Providers::providers(). */
	private $provider_def;

	/** @var string Model ID sent in the request (e.g. gpt-5.4-nano). */
	private $model;

	/**
	 * Validate an OpenAI API key. Kept for backward compatibility; see Every_Alt_Providers::validate_key().
	 *
	 * @param string $api_key The key to validate.
	 * @return bool True if the key is valid and has API access.
	 */
	public static function validate_api_key( $api_key ) {
		return Every_Alt_Providers::validate_key( 'openai', $api_key );
	}

	/**
	 * @param string     $api_key   Key for the model's provider.
	 * @param array|null $model_def Model definition (with 'slug'); defaults to the model selected in Settings.
	 */
	public function __construct( $api_key, $model_def = null ) {
		$this->api_key      = $api_key;
		$this->model_def    = is_array( $model_def ) ? $model_def : Every_Alt_Providers::selected_model();
		$this->provider_def = Every_Alt_Providers::provider( $this->model_def['provider'] );
		$this->model        = apply_filters( self::FILTER_MODEL, $this->model_def['model'], $this->model_def['slug'] );
	}

	/**
	 * Display name of the model in use, e.g. "GPT-5.4 nano (OpenAI)", for logs.
	 *
	 * @return string
	 */
	public function get_model_label() {
		return $this->model_def['label'] . ' (' . $this->provider_def['label'] . ')';
	}

	/**
	 * Get the file path for the best available image size (prefer medium to save tokens).
	 *
	 * @param int        $attachment_id
	 * @param array|null $metadata Attachment metadata to use instead of the saved copy (during upload it is not saved yet).
	 * @return string|null Full path or null.
	 */
	public static function get_image_path_for_vision( $attachment_id, $metadata = null ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! is_readable( $file ) ) {
			return null;
		}
		$meta = is_array( $metadata ) ? $metadata : wp_get_attachment_metadata( $attachment_id );
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
	 * Generate alt text for an attachment using the selected vision model. Image is sent as base64.
	 *
	 * @param int        $attachment_id
	 * @param array|null $metadata Unsaved attachment metadata (during upload), see get_image_path_for_vision().
	 * @return object { alt: string|null, error: string|null, error_detail: string|null } Always returns object; check ->error for failure.
	 */
	public function generate_alt( $attachment_id, $metadata = null ) {
		$saved_prompt = get_option( 'every_alt_vision_prompt', '' );
		$prompt       = $saved_prompt !== '' ? $saved_prompt : self::DEFAULT_PROMPT;
		$prompt       = apply_filters( 'everyalt_vision_prompt', $prompt );

		$result = $this->generate_text( $attachment_id, $prompt, $metadata );

		// Map shared 'text' field onto 'alt' for backward compatibility.
		$result->alt = isset( $result->text ) ? $result->text : null;
		unset( $result->text );
		return $result;
	}

	/**
	 * Generate a short image title for an attachment using the selected vision model. Image is sent as base64.
	 *
	 * @param int        $attachment_id
	 * @param array|null $metadata Unsaved attachment metadata (during upload), see get_image_path_for_vision().
	 * @return object { title: string|null, error: string|null, error_detail: string|null, usage: string, cost: string } Always returns object; check ->error for failure.
	 */
	public function generate_title( $attachment_id, $metadata = null ) {
		$saved_prompt = get_option( 'every_alt_title_prompt', '' );
		$prompt       = $saved_prompt !== '' ? $saved_prompt : self::DEFAULT_TITLE_PROMPT;
		$prompt       = apply_filters( 'everyalt_title_prompt', $prompt );

		$result = $this->generate_text( $attachment_id, $prompt, $metadata );

		$result->title = isset( $result->text ) ? $result->text : null;
		unset( $result->text );
		return $result;
	}

	/**
	 * Shared core: send the image plus a text prompt to the selected vision model and return the text response.
	 *
	 * @param int    $attachment_id
	 * @param string     $prompt        Instruction sent with the image.
	 * @param array|null $metadata      Unsaved attachment metadata (during upload), see get_image_path_for_vision().
	 * @return object { text: string|null, error: string|null, error_detail: string|null, usage: string, cost: string }
	 */
	private function generate_text( $attachment_id, $prompt, $metadata = null ) {
		$path = self::get_image_path_for_vision( $attachment_id, $metadata );
		if ( ! $path ) {
			return (object) array( 'text' => null, 'error' => 'Could not get image path for attachment.', 'error_detail' => '', 'usage' => '', 'cost' => '' );
		}
		$bytes = file_get_contents( $path );
		if ( $bytes === false ) {
			return (object) array( 'text' => null, 'error' => 'Could not read image file.', 'error_detail' => $path, 'usage' => '', 'cost' => '' );
		}
		$base64_encode_error = __( 'Image could not be encoded for the API. On some servers, base64 encoding fails for large images or due to PHP limits. Try a smaller image, or increase your server\'s PHP memory limit.', 'everyalt' );
		try {
			$base64 = base64_encode( $bytes );
		} catch ( \Throwable $e ) {
			return (object) array(
				'text'         => null,
				'error'        => $base64_encode_error,
				'error_detail' => $e->getMessage(),
				'usage'        => '',
				'cost'         => '',
			);
		}
		if ( $bytes !== '' && $base64 === '' ) {
			return (object) array(
				'text'         => null,
				'error'        => $base64_encode_error,
				'error_detail' => $path,
				'usage'        => '',
				'cost'         => '',
			);
		}
		$mime     = $this->get_mime_type( $path );
		$data_url = 'data:' . $mime . ';base64,' . $base64;

		$saved_max   = get_option( 'every_alt_max_completion_tokens', '' );
		$max_tokens  = $saved_max !== '' ? max( 1, (int) $saved_max ) : self::DEFAULT_MAX_COMPLETION_TOKENS;
		$max_tokens  = apply_filters( 'everyalt_max_completion_tokens', $max_tokens );
		if ( ! empty( $this->provider_def['max_tokens_cap'] ) ) {
			$max_tokens = min( (int) $max_tokens, (int) $this->provider_def['max_tokens_cap'] );
		}
		$image_url = array( 'url' => $data_url );
		if ( ! empty( $this->provider_def['image_detail'] ) ) {
			// OpenAI-only hint: low detail keeps image input tokens (and cost) small; plenty for alt text.
			$image_url['detail'] = $this->provider_def['image_detail'];
		}
		$body = array(
			'model'                            => $this->model,
			$this->provider_def['token_param'] => (int) $max_tokens,
			'messages'                         => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $prompt,
						),
						array(
							'type'      => 'image_url',
							'image_url' => $image_url,
						),
					),
				),
			),
		);

		$body = array_merge( $body, $this->model_def['params'] );

		$response = wp_remote_post(
			$this->provider_def['endpoint'],
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
				'text'         => null,
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
				'text'         => null,
				'error'        => $this->provider_def['label'] . ' API returned ' . $code,
				'error_detail' => $detail,
				'usage'        => $usage,
				'cost'         => $cost,
			);
		}
		$content       = isset( $json['choices'][0]['message']['content'] ) ? $json['choices'][0]['message']['content'] : null;
		$finish_reason = isset( $json['choices'][0]['finish_reason'] ) ? $json['choices'][0]['finish_reason'] : '';

		// Content can be a string or an array of content parts (e.g. [ { "type": "text", "text": "..." } ] ).
		$text = '';
		if ( is_string( $content ) ) {
			$text = $content;
		} elseif ( is_array( $content ) ) {
			foreach ( $content as $part ) {
				if ( isset( $part['type'] ) && $part['type'] === 'text' && isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
		}

		$text = trim( $text );

		if ( $finish_reason === 'length' ) {
			$length_message = __( 'Response was cut off (max tokens reached). Increase Max completion tokens in Settings to resolve this.', 'everyalt' );
			return (object) array(
				'text'         => null,
				'error'        => $length_message,
				'error_detail' => $text !== '' ? $text : $body_raw,
				'usage'        => $usage,
				'cost'         => $cost,
			);
		}

		if ( $text === '' ) {
			return (object) array(
				'text'         => null,
				'error'        => $this->provider_def['label'] . ' response had no content.',
				'error_detail' => $body_raw,
				'usage'        => $usage,
				'cost'         => $cost,
			);
		}
		$text = sanitize_text_field( $text );
		return (object) array( 'text' => $text, 'error' => null, 'error_detail' => null, 'usage' => $usage, 'cost' => $cost );
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
		$input_price  = (float) apply_filters( self::FILTER_INPUT_PRICE_PER_MILLION, $this->model_def['input_price'], $this->model_def['slug'] );
		$output_price = (float) apply_filters( self::FILTER_OUTPUT_PRICE_PER_MILLION, $this->model_def['output_price'], $this->model_def['slug'] );
		$cost_dollars = ( $p * $input_price / 1000000 ) + ( $c * $output_price / 1000000 );
		$cost_cents   = $cost_dollars * 100;
		return number_format( $cost_cents, 4 ) . '¢';
	}
}
