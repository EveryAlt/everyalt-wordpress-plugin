<?php
/**
 * Output language for generated alt text and titles.
 *
 * Resolution order for an attachment:
 *  1. The language chosen in Settings (every_alt_language), if not "auto".
 *  2. The attachment's own language from Polylang or WPML, on multilingual sites.
 *  3. The site language (Settings > General).
 * The result can be changed with the everyalt_output_locale filter.
 *
 * @package EveryAlt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_Language {

	/** Option holding a locale to always write in, or '' for automatic. */
	const OPTION = 'every_alt_language';

	/** Filter: change the locale text is generated in. Args: ( string $locale, int $attachment_id ). */
	const FILTER_LOCALE = 'everyalt_output_locale';

	/**
	 * English names for common WordPress locales, keyed by locale. Names are sent to the model (which
	 * understands English language names best) and shown in the Settings dropdown.
	 *
	 * @return array
	 */
	public static function languages() {
		return array(
			'ar'    => 'Arabic',
			'bg_BG' => 'Bulgarian',
			'ca'    => 'Catalan',
			'cs_CZ' => 'Czech',
			'da_DK' => 'Danish',
			'de_DE' => 'German',
			'de_CH' => 'German (Switzerland)',
			'el'    => 'Greek',
			'en_US' => 'English (US)',
			'en_GB' => 'English (UK)',
			'en_AU' => 'English (Australia)',
			'en_CA' => 'English (Canada)',
			'es_ES' => 'Spanish (Spain)',
			'es_MX' => 'Spanish (Mexico)',
			'fa_IR' => 'Persian',
			'fi'    => 'Finnish',
			'fr_FR' => 'French (France)',
			'fr_CA' => 'French (Canada)',
			'he_IL' => 'Hebrew',
			'hi_IN' => 'Hindi',
			'hr'    => 'Croatian',
			'hu_HU' => 'Hungarian',
			'id_ID' => 'Indonesian',
			'it_IT' => 'Italian',
			'ja'    => 'Japanese',
			'ko_KR' => 'Korean',
			'lt_LT' => 'Lithuanian',
			'nb_NO' => 'Norwegian (Bokmål)',
			'nl_NL' => 'Dutch',
			'pl_PL' => 'Polish',
			'pt_BR' => 'Portuguese (Brazil)',
			'pt_PT' => 'Portuguese (Portugal)',
			'ro_RO' => 'Romanian',
			'ru_RU' => 'Russian',
			'sk_SK' => 'Slovak',
			'sl_SI' => 'Slovenian',
			'sr_RS' => 'Serbian',
			'sv_SE' => 'Swedish',
			'th'    => 'Thai',
			'tr_TR' => 'Turkish',
			'uk'    => 'Ukrainian',
			'vi'    => 'Vietnamese',
			'zh_CN' => 'Chinese (Simplified)',
			'zh_TW' => 'Chinese (Traditional)',
		);
	}

	/**
	 * Locale to write in for an attachment.
	 *
	 * @param int $attachment_id
	 * @return string e.g. "de_DE"
	 */
	public static function locale_for_attachment( $attachment_id ) {
		$locale = (string) get_option( self::OPTION, '' );
		if ( $locale === '' ) {
			$locale = self::multilingual_locale( $attachment_id );
		}
		if ( $locale === '' ) {
			$locale = get_locale();
		}
		return (string) apply_filters( self::FILTER_LOCALE, $locale, $attachment_id );
	}

	/**
	 * Attachment's language from Polylang or WPML, or '' if neither is active or it has none.
	 *
	 * @param int $attachment_id
	 * @return string
	 */
	private static function multilingual_locale( $attachment_id ) {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$locale = pll_get_post_language( $attachment_id, 'locale' );
			if ( is_string( $locale ) && $locale !== '' ) {
				return $locale;
			}
		}
		if ( has_filter( 'wpml_post_language_details' ) ) {
			$details = apply_filters( 'wpml_post_language_details', null, $attachment_id );
			if ( is_array( $details ) && ! empty( $details['locale'] ) ) {
				return (string) $details['locale'];
			}
		}
		return '';
	}

	/**
	 * English name for a locale, e.g. "German". Falls back to the base language ("de_AT" -> German),
	 * then to the locale code itself, which models also understand.
	 *
	 * @param string $locale
	 * @return string
	 */
	public static function name( $locale ) {
		$languages = self::languages();
		if ( isset( $languages[ $locale ] ) ) {
			return $languages[ $locale ];
		}
		$base = strtok( $locale, '_' );
		foreach ( $languages as $code => $name ) {
			if ( strtok( $code, '_' ) === $base ) {
				return preg_replace( '/\s*\(.*\)$/', '', $name );
			}
		}
		return $locale;
	}

	/**
	 * Sentence appended to the prompt so the model answers in the right language.
	 *
	 * @param int $attachment_id
	 * @return string
	 */
	public static function prompt_instruction( $attachment_id ) {
		$locale = self::locale_for_attachment( $attachment_id );
		if ( $locale === '' ) {
			return '';
		}
		return sprintf( 'Write your answer in %s.', self::name( $locale ) );
	}
}
