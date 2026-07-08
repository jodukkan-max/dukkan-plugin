<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
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
 * @since      1.0.0
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Dukkan_Plugin_Loader    $loader    Maintains and registers all hooks for the plugin.
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
		if ( defined( 'DUKKAN_PLUGIN_VERSION' ) ) {
			$this->version = DUKKAN_PLUGIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'dukkan-plugin';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_woo_webhook_hooks();
		$this->define_woo_extended_hooks();
		$this->define_general_api_hooks();
		$this->define_translatepress_api_hooks();
		$this->define_product_addon_api_hooks();
		$this->define_order_status_api_hooks();
		$this->define_dynamic_pricing_api_hooks();
		$this->define_slim_seo_api_hooks();
		$this->define_gtm4wp_api_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Dukkan_Plugin_Loader. Orchestrates the hooks of the plugin.
	 * - Dukkan_Plugin_i18n. Defines internationalization functionality.
	 * - Dukkan_Plugin_Admin. Defines all hooks for the admin area.
	 * - Dukkan_Plugin_Public. Defines all hooks for the public side of the site.
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
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dukkan-plugin-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dukkan-plugin-i18n.php';

		/**
		 * The class responsible for defining woo webhook functionality.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/webhook/woo/class-dukkan-woo-webhook.php';

		/**
		 * The class responsible for defining woo extended functionality.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/woo-extended/class-dukkan-woo-extended-api.php';

		/**
		 * The class responsible for defining general apis.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-dukkan-plugin-general.php';

		/**
		 * The class responsible for defining product addon apis.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-dukkan-plugin-product-addon-api.php';

		/**
		 * The class responsible for defining order status REST API endpoints.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-dukkan-plugin-order-status-api.php';

		/**
		 * The class responsible for defining dynamic pricing REST API endpoints.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-dukkan-plugin-dynamic-pricing-api.php';

		/**
		 * The class responsible for defining Slim SEO REST API endpoints.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-dukkan-plugin-slim-seo-api.php';

		/**
		 * The class responsible for defining GTM4WP REST API endpoints.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-dukkan-plugin-gtm4wp-api.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-dukkan-plugin-admin.php';

		/**
		 * The class responsible for defining WooCommerce-related admin functionality.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-dukkan-plugin-woocommerce.php';

		/**
		 * The class responsible for custom order status management in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-dukkan-plugin-order-status.php';

		/**
		 * The class responsible for defining all actions that occur in the product-addon area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-dukkan-plugin-product-addon.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-dukkan-plugin-public.php';

		/**
		 * The class responsible for defining all actions that occur in the product-addon
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-product-addon.php';

		$this->loader = new Dukkan_Plugin_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Dukkan_Plugin_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Dukkan_Plugin_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the woo webhook functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_woo_webhook_hooks() {

		$plugin_woo_webhook = new Dukkan_Plugin_Woo_Webhook( $this->get_plugin_name(), $this->get_version() );

	}

	/**
	 * Register all of the hooks related to the woo extended functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_woo_extended_hooks() {

		$plugin_woo_extended = new Dukkan_Plugin_Woo_Extended_API( $this->get_plugin_name(), $this->get_version() );

	}

	/**
	 * Register all of the hooks related to the general api functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_general_api_hooks() {

		$plugin_general_api = new Dukkan_Plugin_API_General( $this->get_plugin_name(), $this->get_version() );

	}

	/**
	 * Register all of the hooks related to the translatepress api functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_translatepress_api_hooks() {
		if ( ! class_exists( 'TRP_Translate_Press' ) ) {
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-dukkan-plugin-translatepress.php';

		$plugin_translatepress_api = new Dukkan_Plugin_Translatepress( $this->get_plugin_name(), $this->get_version() );
	}

	/**
	 * Register all of the hooks related to the dynamic pricing api functionality
	 * of the plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 */
	private function define_dynamic_pricing_api_hooks() {
		$dynamic_pricing_api = new Dukkan_Plugin_Dynamic_Pricing_API( $this->get_plugin_name(), $this->get_version() );
	}

	/**
	 * Register all of the hooks related to the Slim SEO API bridge.
	 *
	 * Only loads when Slim SEO is active on the site.
	 *
	 * @since    1.0.5
	 * @access   private
	 */
	private function define_slim_seo_api_hooks() {
		// Defer to init so Slim SEO's Composer autoloader has loaded.
		// Dukkan loads alphabetically before Slim SEO, so class_exists()
		// would return false if checked in the constructor.
		add_action( 'init', function () {
			if ( ! class_exists( 'SlimSEO\Container' ) ) {
				return;
			}
			new Dukkan_Plugin_Slim_SEO_API( $this->get_plugin_name(), $this->get_version() );
		}, 10 );
	}

	/**
	 * Register all of the hooks related to the GTM4WP API functionality.
	 *
	 * Only loads when GTM4WP is active on the site. GTM4WP is procedural,
	 * so we guard with defined('GTM4WP_VERSION') instead of class_exists().
	 *
	 * @since    1.0.8
	 * @access   private
	 */
	private function define_gtm4wp_api_hooks() {
		if ( ! defined( 'GTM4WP_VERSION' ) ) {
			return;
		}
		new Dukkan_Plugin_GTM4WP_API( $this->get_plugin_name(), $this->get_version() );
	}

	/**
	 * Register all of the hooks related to the product addon api functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_product_addon_api_hooks(){
		$plugin_product_addon = new Dukkan_Plugin_Product_Addon_API( $this->get_plugin_name(), $this->get_version() );
	}

	/**
	 * Register all of the hooks related to the order status API functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_order_status_api_hooks() {
		$order_status_api = new Dukkan_Plugin_Order_Status_API( $this->get_plugin_name(), $this->get_version() );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Dukkan_Plugin_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$plugin_woocommerce = new Dukkan_Plugin_WooCommerce( $this->get_plugin_name(), $this->get_version() );

		$plugin_order_status = new Dukkan_Plugin_Order_Status( $this->get_plugin_name(), $this->get_version() );

		$plugin_product_addon = new Dukkan_Plugin_Product_Addon( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_product_addon, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_product_addon, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Dukkan_Plugin_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		$product_addon = new Dukkan_Product_Addon( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $product_addon, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $product_addon, 'enqueue_scripts' );

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
	 * @return    Dukkan_Plugin_Loader    Orchestrates the hooks of the plugin.
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
