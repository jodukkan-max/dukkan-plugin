<?php

/**
 * Self-update mechanism using GitHub-hosted version metadata.
 *
 * Hooks into WordPress's native plugin update system. No external
 * services needed — just a version.json file in the GitHub repo.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.2
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 */

/**
 * Checks for plugin updates via a static version.json file hosted on GitHub.
 *
 * WordPress polls for updates roughly every 12 hours. This class injects
 * the latest release into that check and enables one-click auto-updates
 * in the WordPress admin.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Updater {

	/**
	 * URL of the version.json file in the GitHub repository.
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	const VERSION_URL = 'https://raw.githubusercontent.com/jodukkan-max/dukkan-plugin/main/version.json';

	/**
	 * The plugin slug (folder name).
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	const PLUGIN_SLUG = 'dukkan-plugin';

	/**
	 * The plugin basename (dukkan-plugin/dukkan-plugin.php).
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	private $plugin_basename;

	/**
	 * The current plugin version.
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	private $current_version;

	/**
	 * Initialize the updater and register hooks.
	 *
	 * @since 1.0.2
	 * @param string $plugin_file  Full path to the main plugin file.
	 * @param string $version      Current plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->current_version = $version;

		add_filter( 'site_transient_update_plugins', array( $this, 'check_for_update' ), 10, 1 );
		add_filter( 'auto_update_plugin', array( $this, 'enable_auto_update' ), 10, 2 );
	}

	/**
	 * Inject the latest release into WordPress's update transient.
	 *
	 * Called by WP roughly every 12 hours. Results are cached in a
	 * transient so we don't hit GitHub on every admin page load.
	 *
	 * @since  1.0.2
	 * @param  object $transient  WordPress update transient.
	 * @return object  Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$data = $this->fetch_version_data();
		if ( ! $data ) {
			return $transient;
		}

		if ( version_compare( $data['version'], $this->current_version, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => $this->plugin_basename,
				'new_version' => $data['version'],
				'package'     => $data['package'],
				'url'         => 'https://dukkanjo.com',
				'requires'    => $data['requires'] ?? '5.0',
				'tested'      => $data['tested'] ?? '',
			);
		}

		return $transient;
	}

	/**
	 * Enable automatic plugin updates for Dukkan.
	 *
	 * @since  1.0.2
	 * @param  bool|null $update  Whether to auto-update.
	 * @param  object    $item    The update offer object.
	 * @return bool|null
	 */
	public function enable_auto_update( $update, $item ) {
		if ( isset( $item->slug ) && self::PLUGIN_SLUG === $item->slug ) {
			return true;
		}
		return $update;
	}

	/**
	 * Fetch the latest version.json from GitHub, with 12-hour caching.
	 *
	 * @since  1.0.2
	 * @return array|null  Decoded version data, or null on failure.
	 */
	private function fetch_version_data() {
		$cache_key = 'dukkan_plugin_update_check';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( self::VERSION_URL, array(
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			// Cache errors briefly so we don't hammer GitHub.
			set_transient( $cache_key, null, HOUR_IN_SECONDS );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			set_transient( $cache_key, null, HOUR_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['version'] ) || empty( $body['package'] ) ) {
			set_transient( $cache_key, null, HOUR_IN_SECONDS );
			return null;
		}

		// Cache successful response for 12 hours.
		set_transient( $cache_key, $body, 12 * HOUR_IN_SECONDS );

		return $body;
	}
}
