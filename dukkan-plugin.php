<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://dukkanjo.com
 * @since             1.0.0
 * @package           Dukkan_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Dukkan
 * Plugin URI:        https://dukkanjo.com
 * Description:       WooCommerce companion plugin — REST APIs, product add-ons, dynamic pricing bridge, TranslatePress integration.
 * Version:           1.0.15
 * Author:            Dukkan Ecommerce LLC
 * Author URI:        https://dukkanjo.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dukkan-plugin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'DUKKAN_PLUGIN_VERSION', '1.0.15' );

define( 'DUKKAN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DUKKAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DUKKAN_WOO_EXTENDED_STATIC_API_KEY', 'yuwqeq436473h4h3rh557448384' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-dukkan-plugin-activator.php
 */
function activate_dukkan_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dukkan-plugin-activator.php';
	Dukkan_Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-dukkan-plugin-deactivator.php
 */
function deactivate_dukkan_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dukkan-plugin-deactivator.php';
	Dukkan_Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_dukkan_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_dukkan_plugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-dukkan-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_dukkan_plugin() {

	$plugin = new Dukkan_Plugin();
	$plugin->run();

}
add_action( 'plugins_loaded', 'run_dukkan_plugin' );

/**
 * Register the self-update mechanism powered by a GitHub-
 * hosted version.json file. No wordpress.org listing needed.
 *
 * @since 1.0.2
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dukkan-plugin-updater.php';
new Dukkan_Plugin_Updater( __FILE__, DUKKAN_PLUGIN_VERSION );
