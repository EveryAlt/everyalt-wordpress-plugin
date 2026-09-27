<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://hdc.net
 * @since      0.0.1
 *
 * @package    EveryAlt
 * @subpackage EveryAlt/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    EveryAlt
 * @subpackage EveryAlt/admin
 * @author     HDC <info@hdc.net>
 */
class Every_Alt_Admin {


	/**
	 * The options name to be used in this plugin
	 *
	 * @since  	1.0.0
	 * @access 	private
	 * @var  	string 		$option_name 	Option name of this plugin
	 */

	 private $option_name = 'every_alt';

	const GENERATION_LOG_OPTION = 'every_alt_generation_log';
	const GENERATION_LOG_MAX    = 100;

	/**
	 * Append an entry to the generation log (last 100 entries).
	 *
	 * @param int    $attachment_id
	 * @param string $status   'success' or 'error'
	 * @param string $message  Short message
	 * @param string $detail   Optional longer detail (e.g. API error body)
	 * @param string $usage    Optional token usage (e.g. "Input Tokens: 193, Output Tokens: 12, Total: 205")
	 * @param string $cost     Optional estimated cost in cents (e.g. "0.0123¢")
	 */
	private function every_alt_add_generation_log( $attachment_id, $status, $message, $detail = '', $usage = '', $cost = '' ) {
		$log   = get_option( self::GENERATION_LOG_OPTION, array() );
		$entry = array(
			'time'          => current_time( 'mysql' ),
			'attachment_id' => (int) $attachment_id,
			'status'        => $status,
			'message'       => $message,
			'detail'        => $detail,
			'usage'         => $usage,
			'cost'          => $cost,
			'model'         => $this->every_alt_selected_model_label(),
		);
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::GENERATION_LOG_MAX );
		update_option( self::GENERATION_LOG_OPTION, $log );
	}

	/**
	 * Get the last 100 generation log entries.
	 *
	 * @return array
	 */
	public function every_alt_get_generation_log() {
		$log = get_option( self::GENERATION_LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * If CSV export was requested, send it and exit. Run on admin_init so headers are not yet sent.
	 */
	public function every_alt_maybe_export_logs_csv() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== $this->plugin_name ) {
			return;
		}
		if ( ! isset( $_GET['export_csv'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'everyalt_export_logs' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->every_alt_export_logs_csv();
	}

	/**
	 * Send generation log as a CSV download. Exits after output.
	 */
	public function every_alt_export_logs_csv() {
		$log = $this->every_alt_get_generation_log();
		$filename = 'everyalt-generation-log-' . gmdate( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		$out = fopen( 'php://output', 'w' );
		if ( $out === false ) {
			return;
		}
		// UTF-8 BOM so Excel opens the file with correct encoding.
		fprintf( $out, "\xEF\xBB\xBF" );
		$headers = array(
			__( 'Time', 'everyalt' ),
			__( 'Attachment ID', 'everyalt' ),
			__( 'Status', 'everyalt' ),
			__( 'Model', 'everyalt' ),
			__( 'Message / Alt text', 'everyalt' ),
			__( 'Cost (USD)', 'everyalt' ),
			__( 'Details', 'everyalt' ),
		);
		fputcsv( $out, $headers );
		foreach ( $log as $entry ) {
			$status_label = ( isset( $entry['status'] ) && $entry['status'] === 'success' ) ? __( 'Success', 'everyalt' ) : __( 'Error', 'everyalt' );
			$detail       = isset( $entry['detail'] ) ? $entry['detail'] : '';
			$usage        = isset( $entry['usage'] ) ? $entry['usage'] : '';
			if ( isset( $entry['status'] ) && $entry['status'] === 'success' && $usage !== '' ) {
				$detail = ( $detail !== '' ? $usage . "\n\n" . $detail : $usage );
			}
			$cost_raw = isset( $entry['cost'] ) ? $entry['cost'] : '';
			$cost_usd = '';
			if ( $cost_raw !== '' && preg_match( '/^([\d.]+)\s*¢?$/', trim( $cost_raw ), $m ) ) {
				$cents = (float) $m[1];
				$cost_usd = '$' . number_format( $cents / 100, 8 );
			}
			$row = array(
				isset( $entry['time'] ) ? $entry['time'] : '',
				isset( $entry['attachment_id'] ) ? $entry['attachment_id'] : '',
				$status_label,
				isset( $entry['model'] ) ? $entry['model'] : '',
				isset( $entry['message'] ) ? $entry['message'] : '',
				$cost_usd,
				$detail,
			);
			fputcsv( $out, array_map( array( $this, 'every_alt_csv_safe_cell' ), $row ) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Neutralize spreadsheet formulas: model output or API errors starting with =, +, -, @ (or a tab/CR)
	 * would otherwise be evaluated when the CSV is opened in Excel or Sheets.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function every_alt_csv_safe_cell( $value ) {
		if ( is_string( $value ) && $value !== '' && strpos( "=+-@\t\r", $value[0] ) !== false ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * One-time cleanup when the plugin version changes (updates do not re-run the activation hook).
	 */
	public function every_alt_maybe_upgrade() {
		if ( get_option( 'every_alt_version' ) === $this->version ) {
			return;
		}
		// Settings from earlier versions that are no longer used. The HTTP auth password was stored in plain text.
		delete_option( 'every_alt_secret' );
		delete_option( 'every_alt_fulltext' );
		delete_option( 'every_alt_httpuser' );
		delete_option( 'every_alt_httpassword' );
		// Sites that already have a key have already chosen their auto-generate setting.
		foreach ( Every_Alt_Providers::providers() as $provider ) {
			if ( get_option( $provider['key_option'], '' ) !== '' ) {
				delete_option( 'every_alt_do_auto_default' );
				break;
			}
		}
		// gpt-5-nano is being retired; installs from before model selection move to GPT-5.4 nano.
		add_option( Every_Alt_Providers::MODEL_OPTION, Every_Alt_Providers::DEFAULT_MODEL );
		update_option( 'every_alt_version', $this->version );
	}

	/**
	 * Warn admins when a key is stored but can no longer be decrypted (e.g. AUTH_KEY in wp-config.php changed).
	 */
	public function every_alt_key_decrypt_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$unreadable = array();
		foreach ( Every_Alt_Providers::providers() as $slug => $provider ) {
			if ( get_option( $provider['key_option'], '' ) !== '' && Every_Alt_Providers::get_key( $slug ) === '' ) {
				$unreadable[] = $provider['label'];
			}
		}
		if ( ! $unreadable ) {
			return;
		}
		$url = admin_url( 'upload.php?page=everyalt&tab=settings' );
		echo '<div class="notice notice-error"><p>' . wp_kses(
			sprintf(
				/* translators: 1: provider name(s), e.g. "OpenAI", 2: URL of the EveryAlt settings tab */
				__( 'EveryAlt can no longer read your saved %1$s API key, usually because the security keys in wp-config.php changed. Generation with that provider is paused until you <a href="%2$s">re-enter the key</a>.', 'everyalt' ),
				esc_html( implode( ', ', $unreadable ) ),
				esc_url( $url )
			),
			array( 'a' => array( 'href' => true ) )
		) . '</p></div>';
	}

	/**
	 * AJAX: Validate a provider's API key (key from POST, or that provider's stored key if empty).
	 */
	public function ajax_validate_key() {
		check_ajax_referer( 'everyalt_validate_key', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'everyalt' ) ) );
		}
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'openai';
		if ( ! Every_Alt_Providers::provider( $provider ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'everyalt' ) ) );
		}
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( $key === '' ) {
			$key = Every_Alt_Providers::get_key( $provider );
		}
		if ( $key === '' ) {
			wp_send_json_error( array( 'message' => __( 'Enter an API key in the field above, or save a key first to validate the stored key.', 'everyalt' ) ) );
		}
		if ( Every_Alt_Providers::validate_key( $provider, $key ) ) {
			wp_send_json_success( array( 'message' => __( 'API key is valid.', 'everyalt' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'API key could not be validated. Check that the key is correct and has API access.', 'everyalt' ) ) );
	}

	/**
	 * Decrypted API key for the selected model's provider ('' if none is saved).
	 *
	 * @return string
	 */
	private function get_api_key() {
		$model = Every_Alt_Providers::selected_model();
		return Every_Alt_Providers::get_key( $model['provider'] );
	}

	/**
	 * Display name of the selected model, e.g. "GPT-5.4 nano (OpenAI)".
	 *
	 * @return string
	 */
	private function every_alt_selected_model_label() {
		$model    = Every_Alt_Providers::selected_model();
		$provider = Every_Alt_Providers::provider( $model['provider'] );
		return $model['label'] . ' (' . $provider['label'] . ')';
	}

	
	 

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The hook suffix for the plugin's options page.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      string|false    $plugin_screen_hook_suffix    The screen hook suffix returned by add_media_page().
	 */
	public $plugin_screen_hook_suffix;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Build a cache-busting asset version: plugin version plus the file's last-modified time.
	 * Ensures browsers fetch updated CSS/JS even when the plugin version is unchanged.
	 *
	 * @param string $relative_path Path to the asset relative to the admin directory (e.g. 'js/everyalt-admin-simple.js').
	 * @return string
	 */
	private function every_alt_asset_version( $relative_path ) {
		$full = plugin_dir_path( __FILE__ ) . ltrim( $relative_path, '/' );
		$mtime = file_exists( $full ) ? filemtime( $full ) : false;
		return $mtime ? $this->version . '.' . $mtime : $this->version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// Only load our minimal admin CSS on plugin pages (no wp-components).
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page === 'everyalt' ) {
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/everyalt-admin.css', array(), $this->every_alt_asset_version( 'css/everyalt-admin.css' ), 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area (simple UI, no Vue/React).
	 */
	public function enqueue_scripts() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page === 'everyalt' ) {
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/everyalt-admin-simple.js',
				array( 'jquery' ),
				$this->every_alt_asset_version( 'js/everyalt-admin-simple.js' ),
				true
			);
			wp_localize_script( $this->plugin_name, 'everyaltAdmin', array(
				'restUrl'          => rest_url(),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'validateKeyNonce' => wp_create_nonce( 'everyalt_validate_key' ),
				'i18n'             => array(
					'error'            => __( 'Error', 'everyalt' ),
					'errorPrefix'      => __( 'Error:', 'everyalt' ),
					'requestFailed'    => __( 'Request failed', 'everyalt' ),
					'saved'            => __( 'Saved!', 'everyalt' ),
					'saveFailed'       => __( 'Save failed', 'everyalt' ),
					'regenerated'      => __( 'Regenerated!', 'everyalt' ),
					'regenerateFailed' => __( 'Regenerate failed', 'everyalt' ),
				),
			) );
		}
	}

	/**
	 * UI strings shared by the media-screen and block-editor "Generate alt text" buttons.
	 *
	 * @return array
	 */
	private function every_alt_button_i18n() {
		return array(
			'panelTitle'  => __( 'EveryAlt', 'everyalt' ),
			'button'      => __( 'Generate alt text with EveryAlt', 'everyalt' ),
			'selectImage' => __( 'Select or upload an image to generate alt text.', 'everyalt' ),
			'generating'  => __( 'Generating…', 'everyalt' ),
			'generated'   => __( 'Alt text generated.', 'everyalt' ),
			'failed'      => __( 'Could not generate alt text.', 'everyalt' ),
			'errorPrefix' => __( 'Error:', 'everyalt' ),
		);
	}

	//redirect after activation

	/**
	 * Enqueue Block Editor script: "Generate alt text with EveryAlt" for core/image block.
	 */
	public function add_custom_button_to_image_block() {
		$asset_file = plugin_dir_path( __FILE__ ) . 'js/everyalt-gutenberg-button.js';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		wp_enqueue_script(
			$this->plugin_name . '-gutenberg-image',
			plugin_dir_url( __FILE__ ) . 'js/everyalt-gutenberg-button.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-api-fetch', 'wp-data', 'wp-notices' ),
			$this->every_alt_asset_version( 'js/everyalt-gutenberg-button.js' ),
			true
		);
		wp_localize_script( $this->plugin_name . '-gutenberg-image', 'everyaltBlock', array(
			'i18n' => $this->every_alt_button_i18n(),
		) );
	}


	public function every_alt_plugin_redirect() {
		if (get_option('every_alt_do_activation_redirect', false)) {
			delete_option('every_alt_do_activation_redirect');
			if(!isset($_GET['activate-multi']))
			{
				wp_safe_redirect(admin_url( 'upload.php?page=everyalt&tab=settings' ));
				exit();
			}
		}
	}
	
	
	


	

	// Custom button on media edit page (simple WordPress UI), at end of right sidebar (submit box).
	public function every_alt_custom_button_to_media_edit_page() {
		global $post;
		if ( ! $post || ! $this->every_alt_is_valid_image( $post->ID ) || ! $this->is_user_authorized() ) {
			return;
		}
		wp_enqueue_script(
			$this->plugin_name . '-media',
			plugin_dir_url( __FILE__ ) . 'js/everyalt-media-simple.js',
			array( 'jquery' ),
			$this->every_alt_asset_version( 'js/everyalt-media-simple.js' ),
			true
		);
		wp_localize_script( $this->plugin_name . '-media', 'everyaltMedia', array(
			'restUrl'   => rest_url(),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'mediaId'   => (int) $post->ID,
			'i18n'      => $this->every_alt_button_i18n(),
		) );
		include_once 'partials/everyalt-custom-media-button.php';
	}

	/**
	 * Filter: wp_generate_attachment_metadata. Runs once per upload, after every sub-size has been
	 * written to disk, so the medium size is available. Runs auto alt/title if enabled.
	 *
	 * The metadata has not been saved yet at this point, so it is passed through to the generator.
	 *
	 * @param array $metadata
	 * @param int   $attachment_id
	 * @return array Unchanged metadata.
	 */
	public function every_alt_maybe_auto_on_generate_metadata( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return $metadata;
		}
		if ( ! $this->get_api_key() ) {
			return $metadata;
		}

		// Auto alt text: only when alt is currently empty.
		if ( get_option( $this->option_name . '_auto' ) && get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) === '' ) {
			$this->every_alt_auto_add_image_alt_text( $attachment_id, false, $metadata );
		}

		// Auto title: only when the current title still looks like the raw filename.
		if ( get_option( $this->option_name . '_auto_title' ) && $this->every_alt_title_is_filename_like( $attachment_id ) ) {
			$this->every_alt_auto_add_image_title( $attachment_id, false, $metadata );
		}

		return $metadata;
	}

	//auto alt – direct OpenAI Vision API, image sent as base64 (medium size)
	public function every_alt_auto_add_image_alt_text( $attachment_ID, $bulk = null, $metadata = null ) {
		$api_key = $this->get_api_key();
		$auto    = get_option( $this->option_name . '_auto' );
		if ( $bulk ) {
			$auto = 1;
		}
		if ( ! $api_key || ! $auto ) {
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( 'Skipped: no API key or auto-generate disabled.', 'everyalt' ), '' );
			return null;
		}
		if ( ! $this->every_alt_validate_token() ) {
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( 'Skipped: API key validation failed.', 'everyalt' ), '' );
			return null;
		}
		if ( ! $this->every_alt_is_valid_image( $attachment_ID, $metadata ) ) {
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( 'Skipped: not a valid image or file exceeds 4MB.', 'everyalt' ), '' );
			return null;
		}

		$openai        = new Every_Alt_OpenAI( $api_key );
		$generated_alt = $openai->generate_alt( $attachment_ID, $metadata );

		if ( ! empty( $generated_alt->error ) ) {
			$usage = isset( $generated_alt->usage ) ? $generated_alt->usage : '';
			$cost  = isset( $generated_alt->cost ) ? $generated_alt->cost : '';
			// Do not pass error_detail to log if it contains a server path (information disclosure).
			$detail_for_log = $generated_alt->error_detail;
			if ( $detail_for_log !== '' && ( strpos( $detail_for_log, '/' ) !== false || strpos( $detail_for_log, '\\' ) !== false ) ) {
				$detail_for_log = '';
			}
			$this->every_alt_add_generation_log( $attachment_ID, 'error', $generated_alt->error, $detail_for_log, $usage, $cost );
			return null;
		}
		if ( empty( $generated_alt->alt ) ) {
			$usage = isset( $generated_alt->usage ) ? $generated_alt->usage : '';
			$cost  = isset( $generated_alt->cost ) ? $generated_alt->cost : '';
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( 'No alt text returned from API.', 'everyalt' ), '', $usage, $cost );
			return null;
		}

		update_post_meta( $attachment_ID, '_wp_attachment_image_alt', $generated_alt->alt );
		$usage = isset( $generated_alt->usage ) ? $generated_alt->usage : '';
		$cost  = isset( $generated_alt->cost ) ? $generated_alt->cost : '';
		$this->every_alt_add_generation_log( $attachment_ID, 'success', $generated_alt->alt, '', $usage, $cost );

		if ( $bulk ) {
			return $generated_alt;
		}
		return $generated_alt->alt;
	}

	//auto title – direct OpenAI Vision API, image sent as base64 (medium size)
	public function every_alt_auto_add_image_title( $attachment_ID, $bulk = null, $metadata = null ) {
		$api_key = $this->get_api_key();
		$auto    = get_option( $this->option_name . '_auto_title' );
		if ( $bulk ) {
			$auto = 1;
		}
		if ( ! $api_key || ! $auto ) {
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( '[Title] Skipped: no API key or auto-generate disabled.', 'everyalt' ), '' );
			return null;
		}
		if ( ! $this->every_alt_validate_token() ) {
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( '[Title] Skipped: API key validation failed.', 'everyalt' ), '' );
			return null;
		}
		if ( ! $this->every_alt_is_valid_image( $attachment_ID, $metadata ) ) {
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( '[Title] Skipped: not a valid image or file exceeds 4MB.', 'everyalt' ), '' );
			return null;
		}

		$openai          = new Every_Alt_OpenAI( $api_key );
		$generated_title = $openai->generate_title( $attachment_ID, $metadata );

		if ( ! empty( $generated_title->error ) ) {
			$usage = isset( $generated_title->usage ) ? $generated_title->usage : '';
			$cost  = isset( $generated_title->cost ) ? $generated_title->cost : '';
			// Do not pass error_detail to log if it contains a server path (information disclosure).
			$detail_for_log = $generated_title->error_detail;
			if ( $detail_for_log !== '' && ( strpos( $detail_for_log, '/' ) !== false || strpos( $detail_for_log, '\\' ) !== false ) ) {
				$detail_for_log = '';
			}
			$this->every_alt_add_generation_log( $attachment_ID, 'error', '[Title] ' . $generated_title->error, $detail_for_log, $usage, $cost );
			return null;
		}
		if ( empty( $generated_title->title ) ) {
			$usage = isset( $generated_title->usage ) ? $generated_title->usage : '';
			$cost  = isset( $generated_title->cost ) ? $generated_title->cost : '';
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( '[Title] No title returned from API.', 'everyalt' ), '', $usage, $cost );
			return null;
		}

		wp_update_post( array(
			'ID'         => $attachment_ID,
			'post_title' => $generated_title->title,
		) );
		$usage = isset( $generated_title->usage ) ? $generated_title->usage : '';
		$cost  = isset( $generated_title->cost ) ? $generated_title->cost : '';
		$this->every_alt_add_generation_log( $attachment_ID, 'success', '[Title] ' . $generated_title->title, '', $usage, $cost );

		if ( $bulk ) {
			return $generated_title;
		}
		return $generated_title->title;
	}

	/**
	 * Whether an attachment's current title still looks like the raw upload filename
	 * (so it would benefit from a generated title). Empty titles also qualify.
	 *
	 * @param int $attachment_id
	 * @return bool
	 */
	private function every_alt_title_is_filename_like( $attachment_id ) {
		$post = get_post( $attachment_id );
		if ( ! $post ) {
			return false;
		}
		return $this->every_alt_title_looks_like_filename( $post->post_title, get_attached_file( $attachment_id ) );
	}

	/**
	 * Core of every_alt_title_is_filename_like(), working on raw values so it can run over query rows.
	 *
	 * @param string       $title Attachment post_title.
	 * @param string|false $file  Attached file path (or the relative _wp_attached_file value); only its basename is used.
	 * @return bool
	 */
	private function every_alt_title_looks_like_filename( $title, $file ) {
		$title = trim( (string) $title );
		if ( $title === '' ) {
			return true;
		}

		// A WordPress-appended duplicate counter, e.g. "name (1)", is a strong signal of an untouched upload.
		if ( preg_match( '/\s*\(\d+\)$/', $title ) ) {
			return true;
		}
		// Strip that suffix before further analysis.
		$work = trim( preg_replace( '/\s*\(\d+\)$/', '', $title ) );

		$normalize = function ( $s ) {
			$s = strtolower( (string) $s );
			$s = preg_replace( '/[^a-z0-9]+/', ' ', $s );
			return trim( preg_replace( '/\s+/', ' ', $s ) );
		};

		// Primary signal: title matches the uploaded filename (without extension), ignoring punctuation.
		if ( $file ) {
			$base = pathinfo( $file, PATHINFO_FILENAME );
			if ( $normalize( $work ) === $normalize( $base ) ) {
				return true;
			}
		}

		// Underscores almost never appear in human-written titles (but are common in filenames,
		// e.g. "08_43_43" or "DhC5sDlzRlewkGMANuI5_consultancy").
		if ( strpos( $work, '_' ) !== false ) {
			return true;
		}

		// Timestamp pattern like 08_43_43 / 08-43-43 / 08:43:43.
		if ( preg_match( '/\d{1,2}[_:\-]\d{2}[_:\-]\d{2}/', $title ) ) {
			return true;
		}

		$has_whitespace = preg_match( '/\s/', $work );

		// Slug-style: hyphen-joined word characters with no spaces at all
		// (e.g. "consultancy-course-card", "christina-wocintechchat-com-...-unsplash").
		if ( ! $has_whitespace && preg_match( '/[A-Za-z0-9]-[A-Za-z0-9]/', $work ) ) {
			return true;
		}

		// Camera/phone default filenames where the prefix runs straight into digits (e.g. "DSC0099", "IMG2345").
		if ( preg_match( '/^(img|dsc|dscn|dscf|pxl|gopr|mvimg|p|dji)[ _-]?\d{3,}/i', $work ) ) {
			return true;
		}

		// Common machine/stock/AI/camera title prefixes.
		if ( preg_match( '/^(chatgpt image|dall[\s\-]?e|midjourney|stable diffusion|screenshot|screen[ _-]?shot|img|image|dsc|dscn|dscf|pxl|gopr|mvimg|scan|capture|untitled|photo|pexels|unsplash|istock|shutterstock|getty)\b/i', $work ) ) {
			return true;
		}

		// Random-looking alphanumeric token, e.g. stock-photo IDs ("RMweULmCYxM") or upload hashes ("DhC5sDlzRlewkGMANuI5").
		foreach ( preg_split( '/\s+/', $work ) as $token ) {
			if ( $this->every_alt_token_looks_random( $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Heuristic: does a token look like a random ID/hash rather than a real word?
	 *
	 * @param string $token
	 * @return bool
	 */
	private function every_alt_token_looks_random( $token ) {
		$len = strlen( $token );
		if ( $len < 8 ) {
			return false;
		}
		if ( ! preg_match( '/^[A-Za-z0-9]+$/', $token ) ) {
			return false;
		}
		$has_upper = preg_match( '/[A-Z]/', $token );
		$has_lower = preg_match( '/[a-z]/', $token );
		$has_digit = preg_match( '/[0-9]/', $token );

		// A long token mixing digits with letters is almost always an ID/hash.
		if ( $has_digit && ( $has_upper || $has_lower ) ) {
			return true;
		}

		// Many case flips within one token (e.g. "RMweULmCYxM") indicate gibberish, not a word.
		$transitions = 0;
		for ( $i = 1; $i < $len; $i++ ) {
			$a = $token[ $i - 1 ];
			$b = $token[ $i ];
			if ( ctype_alpha( $a ) && ctype_alpha( $b ) && ( ctype_upper( $a ) !== ctype_upper( $b ) ) ) {
				$transitions++;
			}
		}
		return $transitions >= 4;
	}

	/**
	 * Whether an attachment can be sent for generation: an image whose file we would actually send
	 * (medium size when available, see Every_Alt_OpenAI::get_image_path_for_vision()) is at most 4MB.
	 *
	 * @param int        $media_id
	 * @param array|null $metadata Attachment metadata, when not yet saved (during upload).
	 * @return bool
	 */
	private function every_alt_is_valid_image( $media_id, $metadata = null ) {
		if ( ! wp_attachment_is_image( $media_id ) ) {
			return false;
		}
		$path = Every_Alt_OpenAI::get_image_path_for_vision( $media_id, $metadata );
		if ( ! $path ) {
			return false;
		}
		$file_size = filesize( $path );
		return $file_size !== false && $file_size <= 4 * 1024 * 1024;
	}

	private function every_alt_validate_token() {
		$api_key = $this->get_api_key();
		return ! empty( $api_key );
	}

	/** Images shown per page on the Bulk and Review tabs. */
	const IMAGES_PER_PAGE = 60;

	/**
	 * Current page number for the Bulk and Review tabs.
	 *
	 * @return int
	 */
	private function every_alt_current_page() {
		return isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	}

	/**
	 * One page of image attachments with or without alt text, newest first.
	 *
	 * @param bool $with_alt True for images that have alt text, false for those missing it.
	 * @return array { images: WP_Post[], total: int, pages: int, current: int }
	 */
	private function every_alt_get_images_by_alt( $with_alt ) {
		if ( $with_alt ) {
			$meta_query = array(
				array(
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '!=',
				),
			);
		} else {
			$meta_query = array(
				'relation' => 'OR',
				array(
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_wp_attachment_image_alt',
					'value' => '',
				),
			);
		}
		$current = $this->every_alt_current_page();
		$query   = new WP_Query( array(
			'post_type'              => 'attachment',
			'post_mime_type'         => 'image',
			'post_status'            => 'any',
			'posts_per_page'         => self::IMAGES_PER_PAGE,
			'paged'                  => $current,
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
			'update_post_term_cache' => false,
		) );
		return array(
			'images'  => $query->posts,
			'total'   => (int) $query->found_posts,
			'pages'   => (int) $query->max_num_pages,
			'current' => $current,
		);
	}

	/**
	 * One page of image attachments whose title does (or does not) look like the raw upload filename.
	 *
	 * The filename check can't be expressed in SQL, so this scans a lightweight (ID, title, file) row per
	 * image in one query instead of loading every attachment post.
	 *
	 * @param bool $filename_like True for images needing a title, false for images with a custom title.
	 * @return array { images: WP_Post[], total: int, pages: int, current: int }
	 */
	private function every_alt_get_images_by_title( $filename_like ) {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT p.ID, p.post_title, pm.meta_value AS file
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
			WHERE p.post_type = 'attachment'
				AND p.post_mime_type LIKE 'image/%'
				AND p.post_status NOT IN ( 'trash', 'auto-draft' )
			ORDER BY p.post_date DESC, p.ID DESC"
		);
		$ids = array();
		foreach ( $rows as $row ) {
			if ( $this->every_alt_title_looks_like_filename( $row->post_title, $row->file ) === $filename_like ) {
				$ids[] = (int) $row->ID;
			}
		}

		$total   = count( $ids );
		$pages   = (int) ceil( $total / self::IMAGES_PER_PAGE );
		$current = min( $this->every_alt_current_page(), max( 1, $pages ) );
		$page_ids = array_slice( $ids, ( $current - 1 ) * self::IMAGES_PER_PAGE, self::IMAGES_PER_PAGE );
		$images  = $page_ids ? get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'post__in'       => $page_ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $page_ids ),
		) ) : array();

		return array(
			'images'  => $images,
			'total'   => $total,
			'pages'   => $pages,
			'current' => $current,
		);
	}

	/**
	 * Pagination links for a Bulk/Review tab result from every_alt_get_images_by_*().
	 *
	 * @param array  $result
	 * @param string $tab
	 * @return string HTML, empty when there is only one page.
	 */
	private function every_alt_pagination_links( $result, $tab ) {
		if ( $result['pages'] < 2 ) {
			return '';
		}
		return (string) paginate_links( array(
			'base'    => add_query_arg( array( 'page' => $this->plugin_name, 'tab' => $tab, 'paged' => '%#%' ), admin_url( 'upload.php' ) ),
			'format'  => '',
			'current' => $result['current'],
			'total'   => $result['pages'],
		) );
	}

	
	//settings link on plugin list page
	function every_alt_settings_link( $links ) {
		$url = get_admin_url().'upload.php?page=everyalt';
		$settings_link = "<a href='$url'>" . __( 'Settings', 'everyalt' ) . '</a>';
		array_push(
			$links,
			$settings_link
		);
		return $links;
	}


	//options page
	/**
	 * Add an options page under the Settings submenu
	 *
	 * @since  1.0.0
	 */
	public function add_options_page() {
		
		
		$this->plugin_screen_hook_suffix = add_media_page(
			__( 'EveryAlt', 'everyalt' ),
			__( 'EveryAlt', 'everyalt' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_options_page' )
		);



		add_action( "admin_print_scripts-{$this->plugin_screen_hook_suffix}", [$this,'enqueue_scripts'] );
	}



	

	/**
	 * Render the options page for plugin
	 *
	 * @since  1.0.0
	 */
	public function display_options_page() {
		$allowed_tabs = array( 'settings', 'bulk', 'review', 'bulk_title', 'review_title', 'logs' );
		$tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], $allowed_tabs, true ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$active = $tab;


		$has_api_key = ! empty( $this->get_api_key() );

		$image_page       = null;
		$pagination_links = '';
		if ( $active === 'bulk' || $active === 'review' ) {
			$image_page = $this->every_alt_get_images_by_alt( $active === 'review' );
		} elseif ( $active === 'bulk_title' || $active === 'review_title' ) {
			$image_page = $this->every_alt_get_images_by_title( $active === 'bulk_title' );
		}
		if ( $image_page ) {
			$pagination_links = $this->every_alt_pagination_links( $image_page, $active );
		}

		if ( $active === 'logs' ) {
			$generation_log = $this->every_alt_get_generation_log();
		}

		include_once 'partials/everyalt-options-display.php';
	}

	


	

	public function register_setting() {
		// API keys are stored encrypted (see Every_Alt_Providers::save_key()), not registered here.
		register_setting(
			$this->plugin_name,
			$this->option_name . '_auto',
			array(
				'type'         => 'boolean',
				'show_in_rest' => true,
				'default'      => false,
			)
		);

		register_setting(
			$this->plugin_name,
			$this->option_name . '_auto_title',
			array(
				'type'         => 'boolean',
				'show_in_rest' => true,
				'default'      => false,
			)
		);


	}

	/**
	 * Save settings form (traditional POST).
	 */
	public function every_alt_save_settings() {
		if ( ! isset( $_POST['everyalt_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['everyalt_settings_nonce'] ) ), 'everyalt_save_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Validate every newly entered key before saving anything, so a typo doesn't half-save the form.
		$new_keys = array();
		foreach ( Every_Alt_Providers::providers() as $slug => $provider ) {
			$field = $provider['key_option'];
			$key   = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			if ( $key === '' ) {
				continue;
			}
			if ( ! Every_Alt_Providers::validate_key( $slug, $key ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'everyalt', 'tab' => 'settings', 'error' => 'everyalt_invalid_key', 'provider' => $slug ), admin_url( 'upload.php' ) ) );
				exit;
			}
			$new_keys[ $slug ] = $key;
		}
		foreach ( $new_keys as $slug => $key ) {
			Every_Alt_Providers::save_key( $slug, $key );
		}
		if ( isset( $_POST[ Every_Alt_Providers::MODEL_OPTION ] ) ) {
			$model  = sanitize_text_field( wp_unslash( $_POST[ Every_Alt_Providers::MODEL_OPTION ] ) );
			$models = Every_Alt_Providers::models();
			if ( isset( $models[ $model ] ) ) {
				update_option( Every_Alt_Providers::MODEL_OPTION, $model );
			}
		}
		update_option( $this->option_name . '_auto', ! empty( $_POST[ $this->option_name . '_auto' ] ) ? 1 : 0 );
		update_option( $this->option_name . '_auto_title', ! empty( $_POST[ $this->option_name . '_auto_title' ] ) ? 1 : 0 );
		if ( isset( $_POST['every_alt_vision_prompt'] ) ) {
			update_option( 'every_alt_vision_prompt', sanitize_textarea_field( wp_unslash( $_POST['every_alt_vision_prompt'] ) ) );
		}
		if ( isset( $_POST['every_alt_title_prompt'] ) ) {
			update_option( 'every_alt_title_prompt', sanitize_textarea_field( wp_unslash( $_POST['every_alt_title_prompt'] ) ) );
		}
		if ( isset( $_POST['every_alt_max_completion_tokens'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['every_alt_max_completion_tokens'] ) );
			update_option( 'every_alt_max_completion_tokens', $val === '' ? '' : max( 1, (int) $val ) );
		}
		// The form showed the auto-generate default (checked) until now; from here on the saved value applies.
		delete_option( 'every_alt_do_auto_default' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'everyalt', 'tab' => 'settings', 'updated' => '1' ), admin_url( 'upload.php' ) ) );
		exit;
	}

	public function every_alt_custom_admin_endpoints() {
		register_rest_route( 'everyalt-api/v1', '/save_alt', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'every_alt_save_alt' ),
			'permission_callback' => array( $this, 'every_alt_rest_can_edit_media' ),
			'args'                => array(
				'media_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				),
				'alt_text' => array(
					'required' => true,
					'type'     => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( 'everyalt-api/v1', '/bulk_generate_alt', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_generate_alt' ),
			'permission_callback' => array( $this, 'every_alt_rest_can_edit_media' ),
			'args'                => array(
				'media_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( 'everyalt-api/v1', '/save_title', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'every_alt_save_title' ),
			'permission_callback' => array( $this, 'every_alt_rest_can_edit_media' ),
			'args'                => array(
				'media_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				),
				'title'    => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( 'everyalt-api/v1', '/bulk_generate_title', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_generate_title' ),
			'permission_callback' => array( $this, 'every_alt_rest_can_edit_media' ),
			'args'                => array(
				'media_id' => array(
					'required'          => true,
					'type'              => 'integer',
					'minimum'           => 1,
					'sanitize_callback' => 'absint',
				),
			),
		) );





    }

	/**
	 * REST permission: the user can edit this image attachment. Lets Editors and Authors use the
	 * block-editor and media-screen buttons on media they are allowed to edit, not just admins.
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function every_alt_rest_can_edit_media( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		return $media_id > 0
			&& wp_attachment_is_image( $media_id )
			&& current_user_can( 'edit_post', $media_id );
	}

	public function bulk_generate_alt( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$result   = $this->every_alt_auto_add_image_alt_text( $media_id, true );

		if ( $result && isset( $result->alt ) ) {
			$response = array(
				'media_id' => $media_id,
				'success'  => true,
				'alt_text' => $result->alt,
			);
		} else {
			$last_error = $this->every_alt_get_last_log_message_for_attachment( $media_id );
			$response   = array(
				'media_id' => $media_id,
				'success'  => false,
				'alt_text' => null,
				'message'  => $last_error ? $last_error : __( 'Generation failed.', 'everyalt' ),
			);
		}
		return new WP_REST_Response( $response, 200 );
	}

	public function bulk_generate_title( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$result   = $this->every_alt_auto_add_image_title( $media_id, true );

		if ( $result && isset( $result->title ) ) {
			$response = array(
				'media_id' => $media_id,
				'success'  => true,
				'title'    => $result->title,
			);
		} else {
			$last_error = $this->every_alt_get_last_log_message_for_attachment( $media_id );
			$response   = array(
				'media_id' => $media_id,
				'success'  => false,
				'title'    => null,
				'message'  => $last_error ? $last_error : __( 'Generation failed.', 'everyalt' ),
			);
		}
		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get the message from the most recent generation log entry for an attachment.
	 *
	 * @param int $attachment_id
	 * @return string Empty string if none.
	 */
	private function every_alt_get_last_log_message_for_attachment( $attachment_id ) {
		$log = get_option( self::GENERATION_LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			return '';
		}
		foreach ( $log as $entry ) {
			if ( isset( $entry['attachment_id'] ) && (int) $entry['attachment_id'] === (int) $attachment_id ) {
				return isset( $entry['message'] ) ? $entry['message'] : '';
			}
		}
		return '';
	}

	public function every_alt_save_alt( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$alt_text = sanitize_text_field( $request->get_param( 'alt_text' ) );
		$update = update_post_meta( $media_id, '_wp_attachment_image_alt', $alt_text );
		$response = [
			'alt' => $alt_text,
			'media_id' => $media_id,
			'message' => __( 'Alt successfully updated.', 'everyalt' ),
		];
		return new WP_REST_Response($response, 200);
	}

	public function every_alt_save_title( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$title    = sanitize_text_field( $request->get_param( 'title' ) );
		wp_update_post( array(
			'ID'         => $media_id,
			'post_title' => $title,
		) );
		$response = array(
			'title'    => $title,
			'media_id' => $media_id,
			'message'  => __( 'Title successfully updated.', 'everyalt' ),
		);
		return new WP_REST_Response( $response, 200 );
	}

	private function is_user_authorized() {
		return ! empty( $this->get_api_key() );
	}

}
