<?php

/**
 * Filesystem health check for self-updates.
 *
 * WordPress installs plugin updates by writing over the existing plugin files.
 * If those files are owned by another user (typically `root`, after a manual
 * `wp plugin install` run over SSH as root), the web user cannot overwrite them
 * and WordPress aborts with:
 *
 *   "The update cannot be installed because some files could not be copied.
 *    This is usually due to inconsistent file permissions."
 *
 * That message does not say WHAT to fix, and PHP cannot repair it on its own —
 * a process cannot chown files it does not own. So this class detects the
 * condition up front and tells the site owner the exact shell command to run,
 * instead of leaving them with a bare failure.
 *
 * Deliberately conservative: it only reads permissions and renders a notice.
 * It never changes ownership or permissions itself.
 *
 * @since      1.0.67
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 */
class Dukkan_Plugin_Health {

	/**
	 * Plugin folder name.
	 *
	 * @since 1.0.67
	 */
	const SLUG = 'dukkan-plugin';

	/**
	 * User meta key recording when the notice was dismissed.
	 *
	 * @since 1.0.67
	 */
	const DISMISS_META = 'dukkan_perm_notice_dismissed_at';

	/**
	 * How long a dismissal lasts, in seconds (7 days).
	 *
	 * @since 1.0.67
	 */
	const DISMISS_TTL = 604800;

	/**
	 * Register hooks.
	 *
	 * @since 1.0.67
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Paths that must be writable for the plugin to update itself.
	 *
	 * @since  1.0.67
	 * @return array  Map of label => absolute path.
	 */
	private function required_paths() {
		return array(
			'plugin'  => trailingslashit( WP_PLUGIN_DIR ) . self::SLUG,
			'plugins' => WP_PLUGIN_DIR,
		);
	}

	/**
	 * Collect the paths the web user cannot write to.
	 *
	 * @since  1.0.67
	 * @return array  Map of path => owner name (may be empty).
	 */
	private function unwritable_paths() {
		$bad = array();

		foreach ( $this->required_paths() as $path ) {
			if ( ! is_dir( $path ) || is_writable( $path ) ) {
				continue;
			}

			$owner = '';
			if ( function_exists( 'posix_getpwuid' ) ) {
				$uid   = @fileowner( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$info  = ( false !== $uid ) ? posix_getpwuid( $uid ) : false;
				$owner = ( is_array( $info ) && isset( $info['name'] ) ) ? $info['name'] : '';
			}

			$bad[ $path ] = $owner;
		}

		return $bad;
	}

	/**
	 * Print the admin notice when a permission problem is detected.
	 *
	 * @since 1.0.67
	 */
	public function render_notice() {
		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_dismissed() ) {
			return;
		}

		$bad = $this->unwritable_paths();
		if ( empty( $bad ) ) {
			return;
		}

		$paths  = array_keys( $bad );
		$owners = array_filter( array_values( $bad ) );
		$owner  = $owners ? implode( ', ', array_unique( $owners ) ) : __( 'another user', 'dukkan-plugin' );

		// Use the owner of the offending directory in the fix, because
		// `www-data` is only correct on Debian/Ubuntu. Derive it from the
		// running user when possible so the command is directly runnable.
		$target_user = $this->current_user_name();

		$commands = array();
		foreach ( $paths as $path ) {
			$commands[] = sprintf( 'chown -R %s:%s %s', $target_user, $target_user, $path );
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><p>%s</p><pre style="background:#f6f7f7;padding:10px;overflow:auto">%s</pre><p>%s</p><p><a href="%s" class="button">%s</a></p></div>',
			esc_html__( 'Dukkan: automatic updates are blocked by file permissions', 'dukkan-plugin' ),
			esc_html(
				sprintf(
					/* translators: 1: list of paths, 2: owner user name */
					__( 'These files are owned by %2$s, so WordPress (running as the web server user) cannot overwrite them: %1$s', 'dukkan-plugin' ),
					esc_html( implode( ', ', $paths ) ),
					esc_html( $owner )
				)
			),
			esc_html( implode( "\n", $commands ) ),
			esc_html__( 'Run the command above over SSH, then the update will install normally.', 'dukkan-plugin' ),
			esc_url( $this->dismiss_url() ),
			esc_html__( 'Dismiss for 7 days', 'dukkan-plugin' )
		);
	}

	/**
	 * Name of the user the web server runs as, for the chown command.
	 *
	 * @since  1.0.67
	 * @return string
	 */
	private function current_user_name() {
		if ( function_exists( 'posix_geteuid' ) && function_exists( 'posix_getpwuid' ) ) {
			$info = posix_getpwuid( posix_geteuid() );
			if ( is_array( $info ) && ! empty( $info['name'] ) ) {
				return $info['name'];
			}
		}

		return 'www-data';
	}

	/**
	 * URL that dismisses the notice.
	 *
	 * @since  1.0.67
	 * @return string
	 */
	private function dismiss_url() {
		return wp_nonce_url(
			add_query_arg( 'dukkan_dismiss_perm', 1, admin_url( 'index.php' ) ),
			'dukkan_dismiss_perm'
		);
	}

	/**
	 * Was the notice dismissed recently by this user?
	 *
	 * @since  1.0.67
	 * @return bool
	 */
	private function is_dismissed() {
		$at = (int) get_user_meta( get_current_user_id(), self::DISMISS_META, true );

		if ( ! $at ) {
			return false;
		}

		return ( time() - $at ) < self::DISMISS_TTL;
	}

	/**
	 * Persist a dismissal.
	 *
	 * @since 1.0.67
	 */
	public function handle_dismiss() {
		if ( empty( $_GET['dukkan_dismiss_perm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dukkan_dismiss_perm' ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::DISMISS_META, time() );

		wp_safe_redirect( remove_query_arg( array( 'dukkan_dismiss_perm', '_wpnonce' ) ) );
		exit;
	}
}
