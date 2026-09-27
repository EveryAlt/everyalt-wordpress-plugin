<?php

/**
 * Fired during plugin activation
 *
 * @link       https://hdc.net
 * @since      0.0.1
 *
 * @package    EveryAlt
 * @subpackage EveryAlt/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.0.1
 * @package    EveryAlt
 * @subpackage EveryAlt/includes
 * @author     HDC <info@hdc.net>
 */
class Every_Alt_Activator {

	/**
	 * Set one-time flags for the post-activation redirect and the auto-generate default.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		add_option( 'every_alt_do_activation_redirect', true );
		// Auto-generate starts checked until the first API key is saved (see Every_Alt_Admin::every_alt_save_settings()).
		if ( ! get_option( 'every_alt_openai_key' ) ) {
			add_option( 'every_alt_do_auto_default', true );
		}
	}

}
