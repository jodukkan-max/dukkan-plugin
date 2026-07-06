<?php

/**
 * The general-api functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */

/**
 * The general-api functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the general-api stylesheet and JavaScript.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_API_General {

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('rest_api_init', array($this, 'dukkan_plugin_general_api'));

	}

    public function dukkan_plugin_general_api(){
        register_rest_route('dukkan-general-api/v1', '/plugin-status', array(
            'methods'  => 'GET',
            'callback' => array($this, 'dukkan_check_plugin_status_api'),
            'permission_callback' => '__return_true',
        ));
    }

    public function dukkan_check_plugin_status_api($request) {

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin = $request->get_param('plugin');

        $slug = sanitize_text_field($plugin);

        if (empty($plugin)) {
            return new WP_Error('invalid_data','Plugin parameter is required', ['status'=>400]);
        }

        // Example: woocommerce/woocommerce.php
        // $is_active = is_plugin_active($plugin);

        // return new WP_REST_Response([
        //     'success' => true,
        //     'plugin'  => $plugin,
        //     'active'  => $is_active,
        // ], 200);
        // Get all installed plugins
        $all_plugins = get_plugins();

        $matched_plugin_file = null;

        // Try to find plugin by slug
        foreach ($all_plugins as $plugin_file => $plugin_data) {

            // Example: woocommerce/woocommerce.php → slug = woocommerce
            $plugin_slug = dirname($plugin_file);

            // Handle single-file plugins (hello.php)
            if ($plugin_slug === '.') {
                $plugin_slug = basename($plugin_file, '.php');
            }

            if ($plugin_slug === $slug) {
                $matched_plugin_file = $plugin_file;
                break;
            }
        }

        $is_installed = !is_null($matched_plugin_file);
        $is_active    = $is_installed ? is_plugin_active($matched_plugin_file) : false;

        return new WP_REST_Response([
            'success'   => true,
            'slug'      => $slug,
            'plugin'    => $matched_plugin_file, // actual file path
            'installed' => $is_installed,
            'active'    => $is_active,
        ], 200);

        // $is_installed = array_key_exists($plugin, $all_plugins);
        // $is_active    = $is_installed ? is_plugin_active($plugin) : false;

        // return new WP_REST_Response([
        //     'success'   => true,
        //     'plugin'    => $plugin,
        //     'installed' => $is_installed,
        //     'active'    => $is_active,
        // ], 200);
    }
}