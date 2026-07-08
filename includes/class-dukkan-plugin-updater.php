<?php

/**
 * Self-update mechanism — cron-driven, fully automatic, zero visitor delay.
 *
 * A daily WP-Cron event at 4 AM Amman time checks version.json on
 * GitHub. If a newer version is found, it fires an async (non-blocking)
 * request to this plugin's own REST endpoint. That endpoint runs the
 * actual download/unzip in a separate PHP process, so the visitor who
 * triggered the cron never waits.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.4
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
	 * Cron hook name for the daily version check.
	 *
	 * @since 1.0.2
	 * @var   string
	 */
	const CRON_HOOK = 'dukkan_plugin_daily_update_check';

	/**
	 * REST API namespace.
	 *
	 * @since 1.0.4
	 * @var   string
	 */
	const REST_NAMESPACE = 'dukkan/v1';

	/**
	 * Option key for the background-update auth token.
	 *
	 * @since 1.0.4
	 * @var   string
	 */
	const TOKEN_OPTION = 'dukkan_update_token';

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
		add_action( self::CRON_HOOK, array( $this, 'on_cron_ping' ) );

		// Register the background-update REST endpoint.
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		// Also inject into WP's native update UI (bonus).
		add_filter( 'site_transient_update_plugins', array( $this, 'check_for_update' ), 10, 1 );
	}

	// -----------------------------------------------------------------
	// WP-Cron scheduling
	// -----------------------------------------------------------------

	/**
	 * Schedule the daily cron at 4 AM Amman time (1 AM UTC).
	 *
	 * @since 1.0.2
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event(
				strtotime( 'tomorrow 01:00:00', current_time( 'timestamp' ) ),
				'daily',
				self::CRON_HOOK
			);
		}
	}

	// -----------------------------------------------------------------
	// Fast cron callback — fires an async background job, no blocking.
	// -----------------------------------------------------------------

	/**
	 * WP-Cron callback.
	 *
	 * Checks version.json (fast). If a newer version exists, sends a
	 * non-blocking async request to the REST endpoint that performs the
	 * actual download + install in a separate PHP process. The visitor
	 * who triggered this cron waits milliseconds — not seconds.
	 *
	 * @since 1.0.4
	 */
	public function on_cron_ping() {
		// Clear cached version data so we get a fresh read.
		delete_transient( 'dukkan_plugin_update_check' );

		$data = $this->fetch_version_data();
		if ( ! $data ) {
			return;
		}

		if ( ! version_compare( $data['version'], $this->current_version, '>' ) ) {
			return;
		}

		// Store the package URL so the background handler can fetch it.
		update_option( 'dukkan_update_package', $data['package'], 'no' );
		update_option( 'dukkan_update_version', $data['version'], 'no' );

		// Fire async — blocking=false means WordPress doesn't wait.
		wp_remote_post( rest_url( self::REST_NAMESPACE . '/update' ), array(
			'timeout'   => 1,
			'blocking'  => false,
			'headers'   => array(
				'X-Dukkan-Token' => $this->get_update_token(),
			),
		) );
	}

	// -----------------------------------------------------------------
	// REST endpoint — does the heavy work in a separate PHP process.
	// -----------------------------------------------------------------

	/**
	 * Register the /dukkan/v1/update REST route.
	 *
	 * @since 1.0.4
	 */
	public function register_rest_route() {
		register_rest_route( self::REST_NAMESPACE, '/update', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_background_update' ),
			'permission_callback' => array( $this, 'verify_update_token' ),
		) );
	}

	/**
	 * Verify the bearer token sent by our own cron callback.
	 *
	 * @since  1.0.4
	 * @param  WP_REST_Request $request  Incoming request.
	 * @return bool
	 */
	public function verify_update_token( $request ) {
		$sent = $request->get_header( 'x_dukkan_token' );
		return $sent && hash_equals( $this->get_update_token(), $sent );
	}

	/**
	 * Background update handler — download and install.
	 *
	 * Runs in a REST request spawned by wp_remote_post, completely
	 * separate from the visitor's original page load.
	 *
	 * @since  1.0.4
	 * @param  WP_REST_Request $request  Incoming request (unused).
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_background_update( $request ) {
		$package = get_option( 'dukkan_update_package', '' );
		$version = get_option( 'dukkan_update_version', '' );

		if ( ! $package || ! $version ) {
			return new WP_REST_Response(
				array( 'status' => 'error', 'message' => 'No update data stored.' ),
				400
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		$tmp_file = download_url( $package, 300 );
		if ( is_wp_error( $tmp_file ) ) {
			return new WP_REST_Response(
				array( 'status' => 'error', 'message' => $tmp_file->get_error_message() ),
				500
			);
		}

		WP_Filesystem();
		global $wp_filesystem;

		$plugin_dir = untrailingslashit( plugin_dir_path( $this->plugin_file ) );

		$tmp_dir = $plugin_dir . '-update-tmp';
		if ( $wp_filesystem->is_dir( $tmp_dir ) ) {
			$wp_filesystem->delete( $tmp_dir, true );
		}

		$result = unzip_file( $tmp_file, $tmp_dir );
		@unlink( $tmp_file );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'status' => 'error', 'message' => $result->get_error_message() ),
				500
			);
		}

		$source = $tmp_dir;
		if ( $wp_filesystem->is_dir( $source . '/' . self::PLUGIN_SLUG ) ) {
			$source .= '/' . self::PLUGIN_SLUG;
		}

		copy_dir( $source, $plugin_dir );
		$wp_filesystem->delete( $tmp_dir, true );

		// Clean up stored update data.
		delete_option( 'dukkan_update_package' );
		delete_option( 'dukkan_update_version' );

		return new WP_REST_Response(
			array( 'status' => 'ok', 'version' => $version ),
			200
		);
	}

	// -----------------------------------------------------------------
	// WP native update UI injection (bonus).
	// -----------------------------------------------------------------

	/**
	 * Inject the latest release into WordPress's update transient.
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

	// -----------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------

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

		$response = wp_remote_get( self::VERSION_URL, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			set_transient( $cache_key, null, HOUR_IN_SECONDS );
			return null;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
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

	/**
	 * Get or generate a unique token for authenticating the
	 * background update REST request.
	 *
	 * @since  1.0.4
	 * @return string
	 */
	private function get_update_token() {
		$token = get_option( self::TOKEN_OPTION, '' );
		if ( ! $token ) {
			$token = wp_generate_password( 32, false, false );
			update_option( self::TOKEN_OPTION, $token, 'no' );
		}
		return $token;
	}
}
