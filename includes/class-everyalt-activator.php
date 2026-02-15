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
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'every_alt_logs';
		$charset_collate = $wpdb->get_charset_collate();
		// Check if the table already exists
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				media_id mediumint(9) NOT NULL,
				alt_text text NOT NULL,
				created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

		add_option('every_alt_do_activation_redirect', true);
		add_option('every_alt_do_auto_default', true);

		
	}

}
