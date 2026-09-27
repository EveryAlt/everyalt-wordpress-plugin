<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * Deletes plugin settings, the stored API key, and logs on every site.
 *
 * @link       https://hdc.net
 * @since      0.0.1
 *
 * @package    EveryAlt
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all EveryAlt options and the legacy logs table for one site.
 * Generated alt text and titles are left in place: they belong to the user's media.
 */
function every_alt_uninstall_site() {
	global $wpdb;

	$options = array(
		'every_alt_openai_key',
		'every_alt_gemini_key',
		'every_alt_deepinfra_key',
		'every_alt_model',
		'every_alt_auto',
		'every_alt_auto_title',
		'every_alt_vision_prompt',
		'every_alt_title_prompt',
		'every_alt_max_completion_tokens',
		'every_alt_generation_log',
		'every_alt_version',
		'every_alt_do_activation_redirect',
		'every_alt_do_auto_default',
		// Legacy options from earlier versions.
		'every_alt_secret',
		'every_alt_fulltext',
		'every_alt_httpuser',
		'every_alt_httpassword',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}every_alt_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $every_alt_site_id ) {
		switch_to_blog( $every_alt_site_id );
		every_alt_uninstall_site();
		restore_current_blog();
	}
} else {
	every_alt_uninstall_site();
}
