<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://hdc.dev
 * @since      0.0.1
 *
 * @package    Every_Alt
 * @subpackage Every_Alt/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.0.1
 * @package    Every_Alt
 * @subpackage Every_Alt/includes
 * @author     HDC <info@hdc.dev>
 */
class Every_Alt {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Every_Alt_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'EVERY_ALT_VERSION' ) ) {
			$this->version = EVERY_ALT_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'everyalt';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Every_Alt_Loader. Orchestrates the hooks of the plugin.
	 * - Every_Alt_i18n. Defines internationalization functionality.
	 * - Every_Alt_Admin. Defines all hooks for the admin area.
	 * - Every_Alt_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-everyalt-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-everyalt-public.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-encryption.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-everyalt-openai.php';


		
		$this->loader = new Every_Alt_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Every_Alt_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Every_Alt_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Every_Alt_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		//settings
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_options_page' );


		
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_setting' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'every_alt_save_settings' );
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'register_setting' );
		$this->loader->add_action( 'rest_api_init', $plugin_admin,'every_alt_custom_admin_endpoints' );


		//ajax
		// $this->loader->add_action( 'wp_ajax_every_alt_generate_alt_image', $plugin_admin, 'every_alt_generate_alt_image' );

		
		//auto
		$this->loader->add_action( 'add_attachment', $plugin_admin, 'every_alt_auto_add_image_alt_text' );

		//delete logs
		$this->loader->add_action('delete_attachment', $plugin_admin, 'every_alt_on_media_delete');

		//add alt from media page
		// $this->loader->add_filter('media_row_actions', 'every_alt_custom_button_to_media_edit_page', 10, 2);
		$this->loader->add_action('attachment_submitbox_misc_actions', $plugin_admin, 'every_alt_custom_button_to_media_edit_page');



		//settings link
		$this->loader->add_filter( 'plugin_action_links_'.$this->plugin_name.'/everyalt.php', $plugin_admin,'every_alt_settings_link' );



		//redirect after activation
		$this->loader->add_action('admin_init', $plugin_admin, 'every_alt_plugin_redirect');

		//notice
		// $this->loader->add_action( 'admin_init', $plugin_admin,'every_alt_add_admin_notice' );


		$this->loader->add_action( 'enqueue_block_editor_assets', $plugin_admin,'add_custom_button_to_image_block' );


	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		// $plugin_public = new Every_Alt_Public( $this->get_plugin_name(), $this->get_version() );

		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Every_Alt_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
