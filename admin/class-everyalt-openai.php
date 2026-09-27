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

	const DEFAULT_PROMPT = 'Describe this image in one short, clear sentence suitable for HTML alt text. If the image contains important text (such as a logo, sign, or heading), include that text. Do not start with "This image shows" or similar. Output only the alt text, nothing else.';

	const DEFAULT_TITLE_PROMPT = 'Write a short, descriptive title for this image, suitable for a WordPress image title (about 3 to 6 words, Title Case). Do not use quotation marks, a trailing period, or phrases like "This image shows". Output only the title, nothing else.';

	// Reasoning models use tokens for internal "thinking"; we need enough for reasoning + actual output.
	const DEFAULT_MAX_COMPLETION_TOKENS = 1024;

	/** Option: ask the model to flag purely decorative images (default on). */
	const DECORATIVE_OPTION = 'every_alt_detect_decorative';

	/** Exact answer the model gives for a decorative image. */
	const DECORATIVE_MARKER = 'DECORATIVE';

	/** Appended to the alt text prompt when decorative detection is on. Conservative on purpose: a wrongly skipped image is worse than a described divider. */
	const DECORATIVE_INSTRUCTION = 'Exception: if the image is purely decorative and conveys no information (for example a divider line, spacer, background texture, or abstract pattern), reply with only the English word DECORATIVE. If in doubt, describe the image.';

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
	 * When decorative-image detection is on (and $allow_decorative is true), the model may answer
	 * DECORATIVE for images that convey no information. The result then has decorative = true and alt = ''.
	 *
	 * @param int        $attachment_id
	 * @param array|null $metadata         Unsaved attachment metadata (during upload), see get_image_path_for_vision().
	 * @param bool       $allow_decorative False to always get a description (e.g. "Describe anyway").
	 * @return object { alt: string|null, decorative: bool, error: string|null, error_detail: string|null } Always returns object; check ->error for failure.
	 */
	public function generate_alt( $attachment_id, $metadata = null, $allow_decorative = true ) {
		$saved_prompt = get_option( 'every_alt_vision_prompt', '' );
		$prompt       = $saved_prompt !== '' ? $saved_prompt : self::DEFAULT_PROMPT;
		$prompt       = self::with_language( $prompt, $attachment_id );
		$detect       = $allow_decorative && self::decorative_detection_enabled();
		if ( $detect ) {
			// After the language instruction, so the marker word is not translated.
			$prompt = rtrim( $prompt ) . ' ' . self::DECORATIVE_INSTRUCTION;
		}
		$prompt = apply_filters( 'everyalt_vision_prompt', $prompt, $attachment_id );

		$result = $this->generate_text( $attachment_id, $prompt, $metadata );

		// Map shared 'text' field onto 'alt' for backward compatibility.
		$result->alt        = isset( $result->text ) ? $result->text : null;
		$result->decorative = false;
		unset( $result->text );
		if ( $detect && is_string( $result->alt ) && self::is_decorative_marker( $result->alt ) ) {
			$result->alt        = '';
			$result->decorative = true;
		}
		return $result;
	}

	/**
	 * Whether decorative-image detection is enabled in Settings (default on).
	 *
	 * @return bool
	 */
	public static function decorative_detection_enabled() {
		return (bool) get_option( self::DECORATIVE_OPTION, 1 );
	}

	/**
	 * Whether the model's answer is the decorative marker (tolerating case, punctuation, and quotes).
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function is_decorative_marker( $text ) {
		return strtoupper( trim( $text, " \t\n\r.!\"'`*" ) ) === self::DECORATIVE_MARKER;
	}

	/**
	 * Append the output-language instruction (see Every_Alt_Language) to a prompt.
	 *
	 * @param string $prompt
	 * @param int    $attachment_id
	 * @return string
	 */
	private static function with_language( $prompt, $attachment_id ) {
		$instruction = Every_Alt_Language::prompt_instruction( $attachment_id );
		return $instruction === '' ? $prompt : rtrim( $prompt ) . ' ' . $instruction;
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
		$prompt       = self::with_language( $prompt, $attachment_id );
		$prompt       = apply_filters( 'everyalt_title_prompt', $prompt, $attachment_id );

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
		$mime = $this->get_mime_type( $path );

		$saved_max   = get_option( 'every_alt_max_completion_tokens', '' );
		$max_tokens  = $saved_max !== '' ? max( 1, (int) $saved_max ) : self::DEFAULT_MAX_COMPLETION_TOKENS;
		$max_tokens  = apply_filters( 'everyalt_max_completion_tokens', $max_tokens );
		if ( ! empty( $this->provider_def['max_tokens_cap'] ) ) {
			$max_tokens = min( (int) $max_tokens, (int) $this->provider_def['max_tokens_cap'] );
		}
		$is_interactions = $this->is_interactions_api();
		if ( $is_interactions ) {
			// Gemini Interactions API. Unlike its OpenAI-compatible endpoint, it lets us set the image
			// resolution: "low" is 280 tokens per image instead of the default 1,120.
			$image = array(
				'type'      => 'image',
				'data'      => $base64,
				'mime_type' => $mime,
			);
			if ( ! empty( $this->provider_def['image_resolution'] ) ) {
				$image['resolution'] = $this->provider_def['image_resolution'];
			}
			$body    = array(
				'model'             => $this->model,
				'input'             => array(
					array(
						'type' => 'text',
						'text' => $prompt,
					),
					$image,
				),
				'generation_config' => array( 'max_output_tokens' => (int) $max_tokens ),
				// Don't keep the request and image on Google's side for later retrieval.
				'store'             => false,
			);
			$headers = array( 'x-goog-api-key' => $this->api_key );
		} else {
			$image_url = array( 'url' => 'data:' . $mime . ';base64,' . $base64 );
			if ( ! empty( $this->provider_def['image_detail'] ) ) {
				// OpenAI-only hint: low detail keeps image input tokens (and cost) small; plenty for alt text.
				$image_url['detail'] = $this->provider_def['image_detail'];
			}
			$body    = array(
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
			$headers = array( 'Authorization' => 'Bearer ' . $this->api_key );
		}

		$body = array_merge( $body, $this->model_def['params'] );

		$response = wp_remote_post(
			$this->provider_def['endpoint'],
			array(
				'timeout' => 60,
				'headers' => $headers + array( 'Content-Type' => 'application/json' ),
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
		$usage_raw = $this->normalize_usage( is_array( $json ) && isset( $json['usage'] ) ? $json['usage'] : null );
		$usage    = self::format_usage( $usage_raw );
		$cost_usd = $this->cost_usd( $usage_raw );
		$cost     = $cost_usd === null ? '' : number_format( $cost_usd * 100, 4 ) . '¢';
		if ( $cost_usd !== null ) {
			// Every billed request counts toward the monthly total, including failed or cut-off ones.
			Every_Alt_Usage::record( $this->model_def['slug'], $cost_usd );
		}

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
		$text      = '';
		$truncated = false;
		if ( $is_interactions ) {
			// { status, steps: [ { type: "model_output", content: [ { type: "text", text } ] } ] }
			$status = isset( $json['status'] ) ? $json['status'] : '';
			if ( $status === 'failed' || $status === 'cancelled' ) {
				return (object) array(
					'text'         => null,
					'error'        => $this->provider_def['label'] . ' request ' . $status . '.',
					'error_detail' => $body_raw,
					'usage'        => $usage,
					'cost'         => $cost,
				);
			}
			$truncated = $status === 'incomplete';
			foreach ( isset( $json['steps'] ) && is_array( $json['steps'] ) ? $json['steps'] : array() as $step ) {
				if ( isset( $step['type'] ) && $step['type'] === 'model_output' && ! empty( $step['content'] ) ) {
					$text .= self::text_from_parts( $step['content'] );
				}
			}
		} else {
			$content   = isset( $json['choices'][0]['message']['content'] ) ? $json['choices'][0]['message']['content'] : null;
			$truncated = isset( $json['choices'][0]['finish_reason'] ) && $json['choices'][0]['finish_reason'] === 'length';
			// Content can be a string or an array of content parts (e.g. [ { "type": "text", "text": "..." } ] ).
			$text = is_string( $content ) ? $content : self::text_from_parts( $content );
		}

		$text = trim( $text );

		if ( $truncated ) {
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
	 * Whether this provider uses the Gemini Interactions API (else OpenAI-style Chat Completions).
	 *
	 * @return bool
	 */
	private function is_interactions_api() {
		return isset( $this->provider_def['api'] ) && $this->provider_def['api'] === 'interactions';
	}

	/**
	 * Map a provider's usage object onto Chat Completions field names (prompt_tokens, completion_tokens,
	 * total_tokens, plus reasoning_tokens when reported separately).
	 *
	 * @param array|null $usage
	 * @return array|null
	 */
	private function normalize_usage( $usage ) {
		if ( ! is_array( $usage ) ) {
			return null;
		}
		if ( ! $this->is_interactions_api() ) {
			return $usage;
		}
		$out = array();
		foreach ( array(
			'prompt_tokens'     => 'total_input_tokens',
			'completion_tokens' => 'total_output_tokens',
			'reasoning_tokens'  => 'total_thought_tokens',
			'total_tokens'      => 'total_tokens',
		) as $to => $from ) {
			if ( isset( $usage[ $from ] ) ) {
				$out[ $to ] = (int) $usage[ $from ];
			}
		}
		return $out;
	}

	/**
	 * Output tokens the provider bills, including thinking/reasoning tokens.
	 *
	 * OpenAI counts reasoning inside completion_tokens; Gemini reports thinking separately and leaves it
	 * out of the output count. Anything in the total beyond the prompt is billed as output either way,
	 * so take the larger of the two views.
	 *
	 * @param array $usage Normalized usage.
	 * @return int
	 */
	private static function billable_output_tokens( $usage ) {
		$p = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
		$c = isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0;
		$r = isset( $usage['reasoning_tokens'] ) ? (int) $usage['reasoning_tokens'] : 0;
		if ( isset( $usage['total_tokens'] ) ) {
			return max( $c, (int) $usage['total_tokens'] - $p );
		}
		return $c + $r;
	}

	/**
	 * Concatenate the text parts of a content array ([ { type: "text", text } ]).
	 *
	 * @param mixed $parts
	 * @return string
	 */
	private static function text_from_parts( $parts ) {
		$text = '';
		foreach ( is_array( $parts ) ? $parts : array() as $part ) {
			if ( isset( $part['type'], $part['text'] ) && $part['type'] === 'text' ) {
				$text .= $part['text'];
			}
		}
		return $text;
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
		$thinking = self::billable_output_tokens( $usage ) - (int) $c;
		if ( $c !== null && $thinking > 0 ) {
			$parts[] = 'Thinking Tokens: ' . $thinking;
		}
		if ( $t !== null ) {
			$parts[] = 'Total: ' . $t;
		}
		return implode( ', ', $parts );
	}

	/**
	 * Estimated cost of a request in USD. Uses filtered input/output price per 1M tokens.
	 *
	 * @param array|null $usage API usage array with prompt_tokens, completion_tokens.
	 * @return float|null Null if the response had no usage.
	 */
	private function cost_usd( $usage ) {
		if ( ! is_array( $usage ) ) {
			return null;
		}
		$p = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
		$c = self::billable_output_tokens( $usage );
		if ( $p === 0 && $c === 0 ) {
			return null;
		}
		$input_price  = (float) apply_filters( self::FILTER_INPUT_PRICE_PER_MILLION, $this->model_def['input_price'], $this->model_def['slug'] );
		$output_price = (float) apply_filters( self::FILTER_OUTPUT_PRICE_PER_MILLION, $this->model_def['output_price'], $this->model_def['slug'] );
		return ( $p * $input_price / 1000000 ) + ( $c * $output_price / 1000000 );
	}
}
