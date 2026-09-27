<?php
/**
 * Registry of supported AI providers and models.
 *
 * Every provider exposes an OpenAI-compatible Chat Completions endpoint that accepts a base64 image
 * (data URL), so one request builder (Every_Alt_OpenAI) serves all of them.
 *
 * Prices are the providers' published regular (non-promotional) rates in USD per 1M tokens, as of September 2026.
 * They are used for the cost estimates shown in Settings and Logs, and can be overridden with the
 * everyalt_input_token_price_per_million / everyalt_output_token_price_per_million filters.
 *
 * @package EveryAlt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_Providers {

	/** Option holding the selected model slug (a key of models()). */
	const MODEL_OPTION = 'every_alt_model';

	/** Model used when none has been chosen (and for installs upgrading from gpt-5-nano). */
	const DEFAULT_MODEL = 'openai-gpt-5.4-nano';

	/**
	 * Providers, keyed by provider slug.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'openai'    => array(
				'label'        => 'OpenAI',
				'key_option'   => 'every_alt_openai_key',
				'endpoint'     => 'https://api.openai.com/v1/chat/completions',
				'models_url'   => 'https://api.openai.com/v1/models',
				'token_param'  => 'max_completion_tokens',
				'image_detail' => 'low',
				'key_url'      => 'https://platform.openai.com/api-keys',
				'pricing_url'  => 'https://openai.com/api/pricing/',
				'privacy_urls' => array(
					'OpenAI API data usage' => 'https://openai.com/enterprise-privacy/',
				),
			),
			'gemini'    => array(
				'label'        => 'Google Gemini',
				'key_option'   => 'every_alt_gemini_key',
				'endpoint'     => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
				'models_url'   => 'https://generativelanguage.googleapis.com/v1beta/openai/models',
				'token_param'  => 'max_completion_tokens',
				'key_url'      => 'https://aistudio.google.com/apikey',
				'pricing_url'  => 'https://ai.google.dev/gemini-api/docs/pricing',
				'privacy_urls' => array(
					'Gemini API terms' => 'https://ai.google.dev/gemini-api/terms',
				),
			),
			'deepinfra' => array(
				'label'        => 'DeepInfra',
				'key_option'   => 'every_alt_deepinfra_key',
				'endpoint'     => 'https://api.deepinfra.com/v1/openai/chat/completions',
				'models_url'   => 'https://api.deepinfra.com/v1/openai/models',
				'token_param'  => 'max_tokens',
				// DeepInfra rejects requests above this output limit.
				'max_tokens_cap' => 16384,
				'key_url'      => 'https://deepinfra.com/dash/api_keys',
				'pricing_url'  => 'https://deepinfra.com/pricing',
				'privacy_urls' => array(
					'Data privacy'   => 'https://docs.deepinfra.com/account/data-privacy',
					'Privacy policy' => 'https://deepinfra.com/privacy',
					'Trust center'   => 'https://trust.deepinfra.com/',
				),
			),
		);
	}

	/**
	 * Selectable models, keyed by model slug (stored in MODEL_OPTION).
	 *
	 * 'params' are extra request body fields. Reasoning is kept as low as each model allows: alt text
	 * is one sentence, and reasoning tokens are billed as output.
	 *
	 * @return array
	 */
	public static function models() {
		return array(
			'openai-gpt-5.4-nano'           => array(
				'provider'     => 'openai',
				'model'        => 'gpt-5.4-nano',
				'label'        => 'GPT-5.4 nano',
				'input_price'  => 0.20,
				'output_price' => 1.25,
				'params'       => array(),
			),
			'gemini-3.1-flash-lite'         => array(
				'provider'     => 'gemini',
				'model'        => 'gemini-3.1-flash-lite',
				'label'        => 'Gemini 3.1 Flash-Lite',
				'input_price'  => 0.25,
				'output_price' => 1.50,
				// Gemini 3 thinking cannot be turned off; "low" is the cheapest supported level.
				'params'       => array( 'reasoning_effort' => 'low' ),
			),
			'deepinfra-deepseek-v4.1-flash' => array(
				'provider'     => 'deepinfra',
				'model'        => 'deepseek-ai/DeepSeek-V4.1-Flash',
				'label'        => 'DeepSeek V4.1 Flash',
				'input_price'  => 0.20,
				'output_price' => 0.60,
				'params'       => array( 'reasoning_effort' => 'none' ),
			),
			'deepinfra-glm-5.3-flash'       => array(
				'provider'     => 'deepinfra',
				'model'        => 'zai-org/GLM-5.3-Flash',
				'label'        => 'GLM-5.3-Flash',
				'input_price'  => 0.15,
				'output_price' => 0.50,
				'params'       => array(),
			),
		);
	}

	/**
	 * Format a per-1M-token price with 2 decimals, or 3 when needed (0.20, 1.50, 0.075).
	 *
	 * @param float $price
	 * @return string
	 */
	public static function format_price( $price ) {
		$formatted = number_format( (float) $price, 3, '.', '' );
		return substr( $formatted, -1 ) === '0' ? substr( $formatted, 0, -1 ) : $formatted;
	}

	/**
	 * Slug of the selected model, falling back to the default if unset or no longer offered.
	 *
	 * @return string
	 */
	public static function selected_model_slug() {
		$slug   = get_option( self::MODEL_OPTION, self::DEFAULT_MODEL );
		$models = self::models();
		return isset( $models[ $slug ] ) ? $slug : self::DEFAULT_MODEL;
	}

	/**
	 * Selected model definition, with 'slug' added.
	 *
	 * @return array
	 */
	public static function selected_model() {
		$slug   = self::selected_model_slug();
		$models = self::models();
		return array( 'slug' => $slug ) + $models[ $slug ];
	}

	/**
	 * Provider definition by slug.
	 *
	 * @param string $provider
	 * @return array|null
	 */
	public static function provider( $provider ) {
		$providers = self::providers();
		return isset( $providers[ $provider ] ) ? $providers[ $provider ] : null;
	}

	/**
	 * Decrypted API key for a provider, or '' if none is stored (or it can't be decrypted).
	 *
	 * @param string $provider
	 * @return string
	 */
	public static function get_key( $provider ) {
		$def = self::provider( $provider );
		if ( ! $def ) {
			return '';
		}
		$encrypted = get_option( $def['key_option'], '' );
		return $encrypted === '' ? '' : Every_Alt_Encryption::decrypt( $encrypted );
	}

	/**
	 * Encrypt and store an API key for a provider.
	 *
	 * @param string $provider
	 * @param string $key
	 * @return bool
	 */
	public static function save_key( $provider, $key ) {
		$def       = self::provider( $provider );
		$encrypted = $def ? Every_Alt_Encryption::encrypt( $key ) : '';
		if ( $encrypted === '' ) {
			return false;
		}
		update_option( $def['key_option'], $encrypted );
		return true;
	}

	/**
	 * Check a key against the provider's model-list endpoint (a free, read-only request).
	 *
	 * @param string $provider
	 * @param string $key
	 * @return bool
	 */
	public static function validate_key( $provider, $key ) {
		$def = self::provider( $provider );
		if ( ! $def || ! is_string( $key ) || trim( $key ) === '' ) {
			return false;
		}
		$response = wp_remote_get(
			$def['models_url'],
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . trim( $key ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}
