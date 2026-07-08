<?php

/**
 * Self-update mechanism — cron-driven, fully automatic.
 *
 * Schedules a daily check at 4 AM Amman time (1 AM UTC). When a
 * newer version is found in the GitHub-hosted version.json, it
 * downloads the ZIP and replaces the plugin files — no user
 * action required. Also hooks into WP's native update UI so the
 * admin panel shows available updates.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.2
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
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
	 * Cron hook name.
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	const CRON_HOOK = 'dukkan_plugin_daily_update_check';

	/**
	 * The plugin basename (dukkan-plugin/dukkan-plugin.php).
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	private $plugin_basename;

	/**
	 * Full filesystem path to the main plugin file.
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	private $plugin_file;

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
		$this->plugin_file     = $plugin_file;
		$this->current_version = $version;

		// Schedule the 4 AM daily check.
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_auto_update' ) );

		// Also inject into WP's native update UI (bonus).
		add_filter( 'site_transient_update_plugins', array( $this, 'check_for_update' ), 10, 1 );
	}

	/**
	 * Schedule the daily cron event at 4 AM Amman time (1 AM UTC).
	 *
	 * @since 1.0.2
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// 1 AM UTC = 4 AM Amman (UTC+3, no DST).
			$midnight_utc = strtotime( 'tomorrow 01:00:00', current_time( 'timestamp' ) );
			wp_schedule_event( $midnight_utc, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback — fetch, download, and apply the update if newer.
	 *
	 * @since 1.0.2
	 */
	public function run_auto_update() {
		// Clear cached version data so we always get a fresh read.
		delete_transient( 'dukkan_plugin_update_check' );

		$data = $this->fetch_version_data();
		if ( ! $data ) {
			return;
		}

		if ( ! version_compare( $data['version'], $this->current_version, '>' ) ) {
			return;
		}

		// Download the ZIP to a temp location.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		$tmp_file = download_url( $data['package'], 300 );
		if ( is_wp_error( $tmp_file ) ) {
			return;
		}

		WP_Filesystem();
		global $wp_filesystem;

		$plugin_dir = plugin_dir_path( $this->plugin_file ); // e.g. .../wp-content/plugins/dukkan-plugin/
		$plugin_dir = untrailingslashit( $plugin_dir );

		// Unzip to a temp directory first so we can verify the structure.
		$tmp_dir = $plugin_dir . '-update-tmp';

		if ( $wp_filesystem->is_dir( $tmp_dir ) ) {
			$wp_filesystem->delete( $tmp_dir, true );
		}

		$result = unzip_file( $tmp_file, $tmp_dir );
		@unlink( $tmp_file ); // Clean up the zip.

		if ( is_wp_error( $result ) ) {
			return;
		}

		// unzip_file puts contents into $tmp_dir/dukkan-plugin/.
		// If the ZIP's root folder matches, source is one level down.
		$source = $tmp_dir;
		if ( $wp_filesystem->is_dir( $source . '/' . self::PLUGIN_SLUG ) ) {
			$source .= '/' . self::PLUGIN_SLUG;
		}

		// Replace the existing plugin directory with the new files.
		// Copy new files first, then delete the old ones so the plugin
		// stays active during the swap.
		copy_dir( $source, $plugin_dir );
		$wp_filesystem->delete( $tmp_dir, true );
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

		set_transient( $cache_key, $body, 12 * HOUR_IN_SECONDS );

		return $body;
	}
}
