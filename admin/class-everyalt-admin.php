<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://hdc.dev
 * @since      0.0.1
 *
 * @package    Every_Alt
 * @subpackage Every_Alt/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Every_Alt
 * @subpackage Every_Alt/admin
 * @author     HDC <info@hdc.dev>
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
	 * @param string $usage    Optional token usage (e.g. "Prompt: 193, Completion: 12, Total: 205")
	 * @param string $cost     Optional estimated cost in cents (e.g. "0.0123¢" for gpt-5-nano)
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
			__( 'Message / Alt text', 'everyalt' ),
			__( 'Usage', 'everyalt' ),
			__( 'Cost', 'everyalt' ),
			__( 'Details', 'everyalt' ),
		);
		fputcsv( $out, $headers );
		foreach ( $log as $entry ) {
			$status_label = ( isset( $entry['status'] ) && $entry['status'] === 'success' ) ? __( 'Success', 'everyalt' ) : __( 'Error', 'everyalt' );
			$row = array(
				isset( $entry['time'] ) ? $entry['time'] : '',
				isset( $entry['attachment_id'] ) ? $entry['attachment_id'] : '',
				$status_label,
				isset( $entry['message'] ) ? $entry['message'] : '',
				isset( $entry['usage'] ) ? $entry['usage'] : '',
				isset( $entry['cost'] ) ? $entry['cost'] : '',
				isset( $entry['detail'] ) ? $entry['detail'] : '',
			);
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	/**
	 * AJAX: Validate OpenAI API key (key from POST or stored key if empty).
	 */
	public function ajax_validate_key() {
		check_ajax_referer( 'everyalt_validate_key', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'everyalt' ) ) );
		}
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( $key === '' ) {
			$key = $this->get_openai_key();
		}
		if ( $key === '' ) {
			wp_send_json_error( array( 'message' => __( 'Enter an API key in the field above, or save a key first to validate the stored key.', 'everyalt' ) ) );
		}
		if ( Every_Alt_OpenAI::validate_api_key( $key ) ) {
			wp_send_json_success( array( 'message' => __( 'API key is valid.', 'everyalt' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'API key could not be validated. Check that the key is correct and has API access.', 'everyalt' ) ) );
	}

	/**
	 * Get decrypted OpenAI API key (stored encrypted in DB).
	 *
	 * @return string
	 */
	private function get_openai_key() {
		$encrypted = get_option( Every_Alt_Encryption::OPTION_KEY, '' );
		if ( $encrypted === '' ) {
			return '';
		}
		return Every_Alt_Encryption::decrypt( $encrypted );
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


	public $error;

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
		$this->error = false;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// Only load our minimal admin CSS on plugin pages (no wp-components).
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'everyalt' ) {
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/everyalt-admin.css', array(), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area (simple UI, no Vue/React).
	 */
	public function enqueue_scripts() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'everyalt' ) {
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/everyalt-admin-simple.js',
				array( 'jquery' ),
				$this->version,
				true
			);
			wp_localize_script( $this->plugin_name, 'everyaltAdmin', array(
				'restUrl'          => rest_url(),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'validateKeyNonce' => wp_create_nonce( 'everyalt_validate_key' ),
			) );
		}
	}

	//redirect after activation

	// Gutenberg block editor: no longer adding React button (simple UI only).
	public function add_custom_button_to_image_block() {
		// Left empty – use Media Library or Bulk tab instead.
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
	
	
	


	

	//notices
	public function every_alt_add_admin_notice(){
		if(!$secret_key){
			return;
		}
		$notice_message = __( 'EveryAlt Settings', 'everyalt' );
		// Display the notice using WordPress's built-in admin_notice hook
		add_action( 'admin_notices', function() use ( $notice_message ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $notice_message ) . '</p></div>';
		});
	}

	//ajax bulk images
	public function every_alt_generate_alt_image(){
		check_ajax_referer( 'every_alt_nonce', 'nonce' );
		$media_id = absint($_POST['media_id']);
		$alt = $this->every_alt_auto_add_image_alt_text($media_id,true);
		$response = [
			'alt' => $alt,
			'media_id' => $media_id
		];
		wp_send_json_success($response);
	}


	// Custom button on media edit page (simple WordPress UI).
	public function every_alt_custom_button_to_media_edit_page() {
		global $post;
		if ( ! $post || ! $this->every_alt_is_valid_image( $post->ID ) || ! $this->is_user_authorized() ) {
			return;
		}
		wp_enqueue_script(
			$this->plugin_name . '-media',
			plugin_dir_url( __FILE__ ) . 'js/everyalt-media-simple.js',
			array( 'jquery' ),
			$this->version,
			true
		);
		wp_localize_script( $this->plugin_name . '-media', 'everyaltMedia', array(
			'restUrl'   => rest_url(),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'mediaId'   => (int) $post->ID,
		) );
		include_once 'partials/everyalt-custom-media-button.php';
	}

	//add username and password
	private function add_http_auth_to_url($url, $username, $password) {
		// Parse the URL
		$parsed_url = parse_url($url);
	
		// Add the HTTP username and password to the URL
		$parsed_url['user'] = $username;
		$parsed_url['pass'] = $password;
	
		// Rebuild the URL
		$new_url = $parsed_url['scheme'] . '://';
		if (!empty($parsed_url['user'])) {
			$new_url .= urlencode($parsed_url['user']);
			if (!empty($parsed_url['pass'])) {
				$new_url .= ':' . urlencode($parsed_url['pass']);
			}
			$new_url .= '@';
		}
		$new_url .= $parsed_url['host'];
		if (!empty($parsed_url['port'])) {
			$new_url .= ':' . $parsed_url['port'];
		}
		if (!empty($parsed_url['path'])) {
			$new_url .= $parsed_url['path'];
		}
		if (!empty($parsed_url['query'])) {
			$new_url .= '?' . $parsed_url['query'];
		}
		if (!empty($parsed_url['fragment'])) {
			$new_url .= '#' . $parsed_url['fragment'];
		}
	
		return $new_url;
	}

	//auto alt – direct OpenAI Vision API, image sent as base64 (medium size)
	public function every_alt_auto_add_image_alt_text( $attachment_ID, $bulk = null ) {
		$api_key = $this->get_openai_key();
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
		if ( ! $this->every_alt_is_valid_image( $attachment_ID ) ) {
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( 'Skipped: not a valid image or file exceeds 4MB.', 'everyalt' ), '' );
			return null;
		}

		$openai        = new Every_Alt_OpenAI( $api_key );
		$generated_alt = $openai->generate_alt( $attachment_ID );

		if ( ! empty( $generated_alt->error ) ) {
			$usage = isset( $generated_alt->usage ) ? $generated_alt->usage : '';
			$cost  = isset( $generated_alt->cost ) ? $generated_alt->cost : '';
			$this->every_alt_add_generation_log( $attachment_ID, 'error', $generated_alt->error, $generated_alt->error_detail, $usage, $cost );
			return null;
		}
		if ( empty( $generated_alt->alt ) ) {
			$usage = isset( $generated_alt->usage ) ? $generated_alt->usage : '';
			$cost  = isset( $generated_alt->cost ) ? $generated_alt->cost : '';
			$this->every_alt_add_generation_log( $attachment_ID, 'error', __( 'No alt text returned from API.', 'everyalt' ), '', $usage, $cost );
			return null;
		}

		update_post_meta( $attachment_ID, '_wp_attachment_image_alt', $generated_alt->alt );
		$this->every_alt_media_logs( $generated_alt->alt, $attachment_ID );
		$usage = isset( $generated_alt->usage ) ? $generated_alt->usage : '';
		$cost  = isset( $generated_alt->cost ) ? $generated_alt->cost : '';
		$this->every_alt_add_generation_log( $attachment_ID, 'success', $generated_alt->alt, '', $usage, $cost );

		if ( $bulk ) {
			return $generated_alt;
		}
		return $generated_alt->alt;
	}

	private function every_alt_is_valid_image($media_id) {
		$attachment = get_post($media_id);
		if (wp_attachment_is_image($attachment)) {
			$file_path = get_attached_file($media_id);
			$file_size = filesize($file_path);
			$max_size = 4 * 1024 * 1024; // 4MB in bytes
			if ($file_size <= $max_size) {
				return true;
			}
		}
		return false;
	}

	private function every_alt_media_logs($alt,$media_id){
		$date = new DateTime();
		global $wpdb;
		$table_name = $wpdb->prefix . 'every_alt_logs';

		$media = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE media_id = %d ORDER BY id ASC LIMIT 1",
				$media_id
			)
		);
		if ($media) {
    		// Update alt text
			$wpdb->update(
				$table_name,
				array('alt_text' => sanitize_text_field($alt)),
				array('id' => $media->id)
			);
		}else{
			$wpdb->insert(
				$table_name,
				array(
					'media_id' => $media_id,
					'alt_text' => sanitize_text_field($alt),
					'created' => $date->format('Y-m-d H:i:s')
				),
				array(
					'%s',
					'%s',
					'%s'
				)
			);
		} 

		
	}


	public function every_alt_on_media_delete($post_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'every_alt_logs';
		$media = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE media_id = %d ORDER BY id ASC LIMIT 1",
				$post_id
			)
		);
		if ($media) {
    		$wpdb->delete($table_name, array('id' => $media->id));
		} 
		return;
	}

	private function every_alt_validate_token() {
		$api_key = $this->get_openai_key();
		return ! empty( $api_key );
	}


	
	private function every_alt_get_images(){
		global $wpdb;
		// Set the current page number
		$page_number = (isset($_REQUEST['paged'])) ? max(1, intval($_REQUEST['paged'])) : 1;

		// Set the number of items to show per page
		$per_page = 35;
		// Calculate the offset for the query based on the current page and number of items per page
		$offset = ($page_number - 1) * $per_page;
		// Get the total number of items in the table
		$total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}every_alt_logs");
		// Query the database for the items to display on the current page
		$results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}every_alt_logs ORDER BY id DESC LIMIT $per_page OFFSET $offset");
		
		if(!$results){
			$response = [
				'images' => [],
				'pagination' => false,
			];
			return $response;
		}
		$pagination = paginate_links(array(
			'base' => add_query_arg('paged', '%#%'),
			'format' => '',
			'current' => $page_number,
			'total' => ceil($total_items / $per_page),
		));

		//get all the needed data
		$images = [];
		foreach ($results as $image) {
			$array = [
				'id'=>$image->id,
				'media_id'=>$image->media_id,
				'media_link'=>get_edit_post_link($image->media_id),
				'alt_text'=> get_post_meta($image->media_id, '_wp_attachment_image_alt', true),
				'image_url'=>wp_get_attachment_image_url($image->media_id)
			];
			$images[] = $array;

			
		}

		$response = [
			'images' => $images,
			'pagination' => $pagination,
		];

		return $response;

	}
	

	private function every_alt_get_images_without_alt(){
		$images = get_posts( array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'post_status' => 'any',
		) );
		$images_without_alt = array();
		foreach ( $images as $image ) {
			$alt = get_post_meta( $image->ID, '_wp_attachment_image_alt', true );
			if ( empty( $alt ) ) {
				$images_without_alt[] = $image;
			}
		}
		return $images_without_alt;
	}

	
	//settings link on plugin list page
	function every_alt_settings_link( $links ) {
		$url = get_admin_url().'upload.php?page=everyalt';
		$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
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
		// Export logs as CSV (must run before any output).
		if ( isset( $_GET['export_csv'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'everyalt_export_logs' ) ) {
			$this->every_alt_export_logs_csv();
			return;
		}

		$tab = isset($_GET['tab']) && !empty($_GET['tab']) ? $_GET['tab'] : 'settings';
		$active = $tab;
		

		$has_openai_key = ! empty( $this->get_openai_key() );
		$plugin_url     = plugins_url( '/', __DIR__ );
		$logo_url       = $plugin_url . 'assets/everyalt-logo.png';

		if ( $active === 'bulk' ) {
			$images_without_alt = $this->every_alt_get_images_without_alt();
			$bulk_image_ids = wp_list_pluck( $images_without_alt, 'ID' );
			$history = $this->every_alt_get_images();
			$tokens = null;
			$used_tokens = null;
			$progress = 0;
		}

		if ( $active === 'logs' ) {
			$generation_log = $this->every_alt_get_generation_log();
		}

		include_once 'partials/everyalt-options-display.php';
	}

	


	

	public function register_setting() {
		// OpenAI key is stored encrypted via Every_Alt_Encryption::OPTION_KEY, not registered here.
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
			$this->option_name . '_fulltext',
			array(
				'type'         => 'boolean',
				'show_in_rest' => true,
				'default'      => false,
			)
		);


		register_setting(
			$this->plugin_name,
			$this->option_name . '_httpuser',
				array(
				'type'         => 'string',
				'show_in_rest' => true,
				'default'      => '',
			)
		);
		
		register_setting(
			$this->plugin_name,
			$this->option_name . '_httpassword',
				array(
				'type'         => 'string',
				'show_in_rest' => true,
				'default'      => '',
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
		$openai_key = isset( $_POST['every_alt_openai_key'] ) ? sanitize_text_field( wp_unslash( $_POST['every_alt_openai_key'] ) ) : '';
		if ( $openai_key !== '' ) {
			if ( ! Every_Alt_OpenAI::validate_api_key( $openai_key ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'everyalt', 'tab' => 'settings', 'error' => 'everyalt_invalid_key' ), admin_url( 'upload.php' ) ) );
				exit;
			}
			$encrypted = Every_Alt_Encryption::encrypt( $openai_key );
			if ( $encrypted !== '' ) {
				update_option( Every_Alt_Encryption::OPTION_KEY, $encrypted );
			}
		}
		update_option( $this->option_name . '_auto', ! empty( $_POST[ $this->option_name . '_auto' ] ) ? 1 : 0 );
		update_option( $this->option_name . '_fulltext', ! empty( $_POST[ $this->option_name . '_fulltext' ] ) ? 1 : 0 );
		if ( isset( $_POST['every_alt_vision_prompt'] ) ) {
			update_option( 'every_alt_vision_prompt', sanitize_textarea_field( wp_unslash( $_POST['every_alt_vision_prompt'] ) ) );
		}
		if ( isset( $_POST['every_alt_max_completion_tokens'] ) ) {
			$val = sanitize_text_field( wp_unslash( $_POST['every_alt_max_completion_tokens'] ) );
			update_option( 'every_alt_max_completion_tokens', $val === '' ? '' : max( 1, (int) $val ) );
		}
		if ( isset( $_POST[ $this->option_name . '_httpuser' ] ) ) {
			update_option( $this->option_name . '_httpuser', sanitize_text_field( wp_unslash( $_POST[ $this->option_name . '_httpuser' ] ) ) );
		}
		if ( isset( $_POST[ $this->option_name . '_httpassword' ] ) ) {
			update_option( $this->option_name . '_httpassword', sanitize_text_field( wp_unslash( $_POST[ $this->option_name . '_httpassword' ] ) ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'everyalt', 'tab' => 'settings', 'updated' => '1' ), admin_url( 'upload.php' ) ) );
		exit;
	}

	public function every_alt_custom_admin_endpoints() {
        register_rest_route( 'everyalt-api/v1', '/get_tokens', array(
			'methods' => WP_REST_Server::READABLE,
            'callback' => [$this,'every_alt_get_tokens'],
            'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			}
        ));


		register_rest_route( 'everyalt-api/v1', '/save_alt', array(
			'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this,'every_alt_save_alt'],
            'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			}
        ));

		register_rest_route( 'everyalt-api/v1', '/bulk_generate_alt', array(
			'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this,'bulk_generate_alt'],
            'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			}
        ));



		
		
    }

	public function bulk_generate_alt( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$alt_text = $this->every_alt_auto_add_image_alt_text( $media_id, true );

		
		
		if ( $alt_text && isset( $alt_text->alt ) ) {
			$response = array(
				'media_id'     => $media_id,
				'alt_text'     => $alt_text->alt,
				'tokens'       => null,
				'used_tokens'  => null,
			);
		} else {
			$response = array(
				'media_id'     => $media_id,
				'alt_text'     => false,
				'tokens'       => null,
				'used_tokens'  => null,
			);
		}
		return new WP_REST_Response( $response, 200 );
	}

	public function every_alt_save_alt( $request ) {
		$media_id = absint( $request->get_param( 'media_id' ) );
		$log_id = absint( $request->get_param( 'log_id' ) );
		$alt_text = sanitize_text_field( $request->get_param( 'alt_text' ) );
		$update = update_post_meta( $media_id, '_wp_attachment_image_alt', $alt_text );
		$response = [
			'alt' => $alt_text,
			'log_id' => $log_id,
			'media_id' => $media_id,
			'message' => __('Alt Succesfully Updated','everyalt'),
		];
		return new WP_REST_Response($response, 200);
	}

	private function is_user_authorized() {
		return ! empty( $this->get_openai_key() );
	}


	public function every_alt_get_tokens() {
		$has_key = ! empty( $this->get_openai_key() );
		if ( get_option( 'every_alt_do_auto_default', false ) ) {
			delete_option( 'every_alt_do_auto_default' );
			if ( $has_key ) {
				update_option( $this->option_name . '_auto', 1 );
			}
		}
		$response = array(
			'error'        => false,
			'tokens'       => null,
			'used_tokens'  => null,
			'auto'         => (int) get_option( $this->option_name . '_auto', 0 ),
		);
		return new WP_REST_Response( $response, 200 );
	}

	
	

	

	

}
