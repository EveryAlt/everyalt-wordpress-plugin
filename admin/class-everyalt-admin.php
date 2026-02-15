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
				'restUrl'  => rest_url(),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
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

	//auto alt
	public function every_alt_auto_add_image_alt_text($attachment_ID, $bulk = null){
		// check if option is enanable and key is set
		$secret_key = get_option( $this->option_name . '_secret' );
		$auto = get_option( $this->option_name . '_auto' );
		$add_full_text = get_option( $this->option_name . '_fulltext' );
		if($bulk){
			$auto = 1;
		}
		if(!$secret_key || !$auto){
			return;
		}
		//validate the token
		$valid_token = $this->every_alt_validate_token();
		if(!$valid_token){
			return;
		}

		//lastly we check if is a valid image and less than 4mb
		if(!$this->every_alt_is_valid_image($attachment_ID)){
			return;
		}

		$url = wp_get_attachment_image_url($attachment_ID,'large');

		//check password
		$username = get_option( $this->option_name . '_httpuser' );
		$password = get_option( $this->option_name . '_httpassword' );
		if($username && $password){
			$url = $this->add_http_auth_to_url($url, $username, $password);
		}


		
		


		// $url = 'https://media-cldnry.s-nbcnews.com/image/upload/newscms/2020_04/3198231/200122-dinner-table-tacos-ac-831p.jpg';
		
		//check file size
		$every_alt_curls = new Every_Alt_Curls($secret_key);
		$generated_alt = $every_alt_curls->every_alt_generate_alt($url);
		
		if(!isset($generated_alt->alt) || !$generated_alt->alt){
			return;
		}

		//add full text
		if(isset($generated_alt->full_text) && $add_full_text){
			$generated_alt->alt .=' Full Text: ' . $generated_alt->full_text;
		}

		update_post_meta( $attachment_ID, '_wp_attachment_image_alt', sanitize_text_field($generated_alt->alt) );
		//handle logs
		$log = $this->every_alt_media_logs($generated_alt->alt,$attachment_ID);
		
		// handle bulk return
		if($bulk){
			return $generated_alt;
		}else{
			return $generated_alt->alt;
		}
		
		
		
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

	private function every_alt_validate_token(){
		$secret_key = get_option( $this->option_name . '_secret' );
		if(!$secret_key){
			return false;
		}
		$every_alt_curls = new Every_Alt_Curls($secret_key);
		$available_response = $every_alt_curls->every_alt_get_available_tokens();
		if(isset($available_response->data->status) && $available_response->data->status > 200 ){
			return false;
		}
		if(!isset($available_response->tokens) || $available_response->tokens < 0 ){
			return false;
		}
		//tken his valid
		return true;
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

		$tab = isset($_GET['tab']) && !empty($_GET['tab']) ? $_GET['tab'] : 'settings';
		$active = $tab;
		

		$secret_key = get_option( $this->option_name . '_secret' );
		$plugin_url = plugins_url('/', __DIR__);
		$logo_url = $plugin_url . 'assets/everyalt-logo.png';

		
		if($active == 'settings'){
			if($secret_key){
				$every_alt_curls = new Every_Alt_Curls($secret_key);
				$available_response = $every_alt_curls->every_alt_get_available_tokens();
				$error = false;
				if(isset($available_response->data->status) && $available_response->data->status > 200 ){
					$this->error = $available_response->message;
					$error = true;
				}
				if(!$error ){
					$tokens = $available_response->tokens;
					$used_tokens = $available_response->used_tokens;
				}
			}
		}

		if($active == 'history'){
			$images = $this->every_alt_get_images();
		}


		if($active == 'bulk'){
			$images_without_alt = $this->every_alt_get_images_without_alt();
			$bulk_image_ids = wp_list_pluck($images_without_alt,'ID');
			$history = $this->every_alt_get_images();

			if($secret_key){
				$every_alt_curls = new Every_Alt_Curls($secret_key);
				$available_response = $every_alt_curls->every_alt_get_available_tokens();
				$error = false;
				if(isset($available_response->data->status) && $available_response->data->status > 200 ){
					$error = $available_response->message;
				}
				if(!$error ){
					$tokens = $available_response->tokens;
					$used_tokens = $available_response->used_tokens;
				}
			}
			$progress = isset($tokens) && $tokens ? floor( ( $used_tokens * 100 ) / $tokens ) : 0;
		}



		


		include_once 'partials/everyalt-options-display.php';
	}

	


	

	public function register_setting() {
		register_setting(
			$this->plugin_name,
			$this->option_name . '_secret',
				array(
				'type'         => 'string',
				'show_in_rest' => true,
				'default'      => '',
			)
		);
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
		if ( isset( $_POST[ $this->option_name . '_secret' ] ) ) {
			update_option( $this->option_name . '_secret', sanitize_text_field( wp_unslash( $_POST[ $this->option_name . '_secret' ] ) ) );
		}
		update_option( $this->option_name . '_auto', ! empty( $_POST[ $this->option_name . '_auto' ] ) ? 1 : 0 );
		update_option( $this->option_name . '_fulltext', ! empty( $_POST[ $this->option_name . '_fulltext' ] ) ? 1 : 0 );
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

		
		
		if(isset($alt_text->alt)){
			$response = [
				'media_id' => $media_id,
				'alt_text' =>  $alt_text->alt,
				'tokens' => $alt_text->tokens,
				'used_tokens' => $alt_text->used_tokens,
			];
		}else{
			$response = [
				'media_id' => $media_id,
				'alt_text' =>  false,
				'tokens' => false,
				'used_tokens' => false,
			];
		}
		
		

		return new WP_REST_Response($response, 200);
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

	private function is_user_authorized(){
		$secret_key = get_option( $this->option_name . '_secret' );
		//retutn
		if(!$secret_key || empty($secret_key)){
			return false;
		}

		$every_alt_curls = new Every_Alt_Curls($secret_key);
		$available_response = $every_alt_curls->every_alt_get_available_tokens();
		if(isset($available_response->data->status) && $available_response->data->status > 200 ){
			return false;
		}
		if($available_response->tokens > $available_response->used_tokens){
			return true;
		}
		return false;
	}


	public function every_alt_get_tokens(){

		$secret_key = get_option( $this->option_name . '_secret' );
		//retutn
		if(!$secret_key || empty($secret_key)){
			$response = [
				'error' =>false,
				'tokens' => false,
				'used_tokens' => false,
				'auto' => 0
			];
			return new WP_REST_Response($response, 200);
		}
		
		if($secret_key && !empty($secret_key)){
			$every_alt_curls = new Every_Alt_Curls($secret_key);
			$available_response = $every_alt_curls->every_alt_get_available_tokens();
			$error = false;
			
			if(isset($available_response->data->status) && $available_response->data->status > 200 ){
				$error = $available_response->message;
				update_option( $this->option_name . '_auto', 0 );
			}

			if(!$error ){
				$tokens = $available_response->tokens;
				$used_tokens = $available_response->used_tokens;
				$progress = floor(($used_tokens*100)/$tokens);
				//set auto to 1 only first time
				if (get_option('every_alt_do_auto_default', false)) {
					delete_option('every_alt_do_auto_default');
					update_option( $this->option_name . '_auto', 1 );
				}
			}
		}

		

		


		$response = [
			'error' => $error,
			'tokens' => isset($tokens) ? $tokens : false,
			'used_tokens' => isset($used_tokens) ? $used_tokens : false,
			'auto' => get_option( $this->option_name . '_auto' )
		];
		return new WP_REST_Response($response, 200);
	}

	
	

	

	

}
