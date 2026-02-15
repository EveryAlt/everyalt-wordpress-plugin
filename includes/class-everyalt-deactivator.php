<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://hdc.net
 * @since      0.0.1
 *
 * @package    EveryAlt
 * @subpackage EveryAlt/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.0.1
 * @package    EveryAlt
 * @subpackage EveryAlt/includes
 * @author     HDC <info@hdc.net>
 */
class Every_Alt_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		delete_option( 'every_alt_secret' );
		delete_option( 'every_alt_openai_key' );
		delete_option( 'every_alt_auto' );
		delete_option( 'every_alt_fulltext' );
		delete_option( 'every_alt_httpuser' );
		delete_option( 'every_alt_httpassword' );
		delete_option( 'every_alt_do_auto_default' );
	}

}
