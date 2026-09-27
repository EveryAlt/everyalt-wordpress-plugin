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
	 * Clear one-time activation state. Settings are intentionally preserved.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Settings and the API key are kept so the plugin works again on reactivation.
		// Everything is removed on uninstall (see uninstall.php).
		delete_option( 'every_alt_do_activation_redirect' );
		// Stop the background queue runner. Queued jobs stay and resume on reactivation.
		wp_clear_scheduled_hook( 'everyalt_process_queue' );
	}

}
