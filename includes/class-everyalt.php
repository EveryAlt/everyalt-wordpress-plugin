<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://hdc.net
 * @since      0.0.1
 *
 * @package    EveryAlt
 * @subpackage EveryAlt/includes
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
 * @package    EveryAlt
 * @subpackage EveryAlt/includes
 * @author     HDC <info@hdc.net>
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
			$this->version = '1.0';
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

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-encryption.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-providers.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-language.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-usage.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-everyalt-queue.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-everyalt-openai.php';

		/**
		 * The class responsible for front-end behavior (filling missing alt text in post content).
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-everyalt-public.php';

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
		$queue        = new Every_Alt_Queue( $plugin_admin );
		$plugin_admin->set_queue( $queue );

		//background queue: WP-Cron runner (the browser runner is the /queue/process REST route)
		$this->loader->add_filter( 'cron_schedules', 'Every_Alt_Queue', 'add_cron_schedule' );
		$this->loader->add_action( Every_Alt_Queue::CRON_HOOK, $queue, 'process_from_cron' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'every_alt_budget_notice' );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		//settings
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_options_page' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_setting' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'every_alt_save_settings' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'every_alt_maybe_export_logs_csv', 5 );
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'register_setting' );
		$this->loader->add_action( 'rest_api_init', $plugin_admin,'every_alt_custom_admin_endpoints' );
		$this->loader->add_action( 'wp_ajax_everyalt_validate_key', $plugin_admin, 'ajax_validate_key' );

		//auto: run once per upload, after all sub-sizes (incl. medium) are generated. Late priority so other plugins' image processing is done.
		$this->loader->add_filter( 'wp_generate_attachment_metadata', $plugin_admin, 'every_alt_maybe_auto_on_generate_metadata', 99, 2 );

		//upgrades and key health
		$this->loader->add_action( 'admin_init', $plugin_admin, 'every_alt_maybe_upgrade' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'every_alt_key_decrypt_notice' );

		//add alt from media page (button at end of right sidebar)
		$this->loader->add_action('attachment_submitbox_misc_actions', $plugin_admin, 'every_alt_custom_button_to_media_edit_page');



		//settings link
		$this->loader->add_filter( 'plugin_action_links_'.$this->plugin_name.'/everyalt.php', $plugin_admin,'every_alt_settings_link' );



		//redirect after activation
		$this->loader->add_action('admin_init', $plugin_admin, 'every_alt_plugin_redirect');

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
		$plugin_public = new Every_Alt_Public();

		// Fill empty alt attributes in post content from the Media Library (WordPress 6.0+).
		$this->loader->add_filter( 'wp_content_img_tag', $plugin_public, 'fill_missing_alt', 10, 3 );
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
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
