<?php
/**
 * Front-end: fill in missing alt text on images already placed in posts.
 *
 * When an image is inserted into a post, WordPress copies its alt text into the post content at that
 * moment. Alt text generated later (in bulk, or after editing the Media Library) never reaches those
 * existing posts, so visitors and search engines still see alt="". This fills an empty or missing alt
 * attribute from the attachment's current alt text as the content is displayed. Post content in the
 * database is not changed, and alt text an author wrote into the post is never overwritten.
 *
 * Uses the wp_content_img_tag filter (WordPress 6.0+), which runs for images in post content that
 * WordPress can match to a Media Library attachment (the wp-image-{ID} class). WordPress primes the
 * attachments' meta cache for the whole post before filtering, so this adds no per-image queries.
 *
 * @package    EveryAlt
 * @subpackage EveryAlt/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_Public {

	/** Option: fill missing alt text in post content on display (default on). */
	const FILL_OPTION = 'every_alt_fill_content_alt';

	/**
	 * Whether the feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::FILL_OPTION, 1 );
	}

	/**
	 * Filter: wp_content_img_tag.
	 *
	 * @param string $filtered_image Full <img> tag.
	 * @param string $context        Filter context, e.g. 'the_content'.
	 * @param int    $attachment_id  Attachment ID, or 0 if WordPress couldn't identify one.
	 * @return string
	 */
	public function fill_missing_alt( $filtered_image, $context, $attachment_id ) {
		if ( ! $attachment_id || ! self::is_enabled() ) {
			return $filtered_image;
		}
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( $alt === '' ) {
			return $filtered_image;
		}
		return self::set_alt_if_empty( $filtered_image, $alt );
	}

	/**
	 * Set the alt attribute on an <img> tag only if it is missing or blank.
	 *
	 * @param string $img_tag
	 * @param string $alt     Unescaped alt text.
	 * @return string
	 */
	public static function set_alt_if_empty( $img_tag, $alt ) {
		// WordPress 6.2+: parse the tag properly.
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $img_tag );
			if ( ! $processor->next_tag( 'img' ) ) {
				return $img_tag;
			}
			$current = $processor->get_attribute( 'alt' );
			if ( is_string( $current ) && trim( $current ) !== '' ) {
				return $img_tag;
			}
			$processor->set_attribute( 'alt', $alt );
			return $processor->get_updated_html();
		}

		// WordPress 6.0–6.1 fallback.
		if ( preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $img_tag, $match ) ) {
			if ( trim( $match[2] ) !== '' ) {
				return $img_tag;
			}
			return str_replace( $match[0], ' alt="' . esc_attr( $alt ) . '"', $img_tag );
		}
		return preg_replace( '/^<img\b/i', '<img alt="' . esc_attr( $alt ) . '"', $img_tag, 1 );
	}
}
