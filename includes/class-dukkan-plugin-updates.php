<?php
/**
 * Update control: which WordPress, plugin, theme and translation updates a
 * store offers or installs automatically, plus an update log.
 *
 * Replaces the "Easy Updates Manager" plugin and uses the same storage, so a
 * store keeps its rules and history when it switches:
 *   - site option MPSUM: core / plugins / plugins_automatic / themes /
 *     themes_automatic / advanced
 *   - table {base_prefix}eum_logs
 *
 * Idle while Easy Updates Manager is active, or when DUKKAN_UPDATES is defined
 * as false in wp-config.php.
 *
 * WP-CLI: wp dukkan-updates status | list | set <plugin> <allowed|auto|blocked> | log
 *
 * @since 1.0.71
 */

defined( 'ABSPATH' ) || exit;

class Dukkan_Updates {

	const OLD_PLUGIN = 'stops-core-theme-and-plugin-updates/main.php';
	const OPTION     = 'MPSUM';
	const LATE       = PHP_INT_MAX - 10;

	/** @var self|null */
	private static $instance = null;

	/** @var bool Decision for core auto-updates (see core_should_update). */
	private $core_allowed = false;

	public static function boot(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_start' ), 10 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'dukkan-updates', 'Dukkan_Updates_CLI' );
		}
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	/** Why the module is idle, or '' when it runs. */
	public static function idle_reason(): string {
		if ( defined( 'DUKKAN_UPDATES' ) && ! DUKKAN_UPDATES ) {
			return 'switched off by DUKKAN_UPDATES';
		}
		if ( in_array( self::OLD_PLUGIN, (array) get_option( 'active_plugins', array() ), true ) ) {
			return 'Easy Updates Manager is still active';
		}
		return '';
	}

	public static function maybe_start(): void {
		if ( '' !== self::idle_reason() ) {
			return;
		}
		Dukkan_Updates_Log::run();
		// Users listed under advanced > excluded_users see WordPress's own behaviour.
		if ( current_user_can( 'update_plugins' ) && in_array( get_current_user_id(), (array) ( self::options( 'advanced' )['excluded_users'] ?? array() ) ) ) { // phpcs:ignore WordPress.PHP.StrictInArray
			return;
		}
		self::$instance = new self();
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	/** All settings, or one section of them. */
	public static function options( string $context = '' ): array {
		$o = get_site_option( self::OPTION, array() );
		$o = is_array( $o ) ? $o : array();
		if ( '' === $context ) {
			return $o;
		}
		return isset( $o[ $context ] ) ? (array) $o[ $context ] : array();
	}

	public static function save_section( string $context, array $value ): void {
		$o             = self::options();
		$o[ $context ] = $value;
		update_site_option( self::OPTION, $o );
	}

	/** off | on (manual) | automatic | automatic_off | individual … for a core key. */
	public static function core( string $key ): ?string {
		$core = self::options( 'core' );
		return isset( $core[ $key ] ) ? (string) $core[ $key ] : null;
	}

	/* ------------------------------------------------------------------ */
	/* Rules                                                               */
	/* ------------------------------------------------------------------ */

	private function __construct() {
		$core = self::options( 'core' );

		// Let WordPress run plugin/theme auto-updates when the update check runs in cron.
		add_action( 'wp_update_plugins', array( $this, 'maybe_auto_update' ), 20 );

		if ( 'off' === self::core( 'all_updates' ) ) {
			$this->block_everything();
			return;
		}
		add_filter( 'automatic_updater_disabled', '__return_false', self::LATE );
		add_filter( 'file_mod_allowed', array( $this, 'allow_automatic_updater' ), self::LATE, 2 );

		// WordPress core.
		if ( 'off' === self::core( 'core_updates' ) ) {
			$this->block_core();
		} else {
			add_filter( 'allow_dev_auto_core_updates', array( $this, 'core_should_update' ), self::LATE );
			add_filter( 'allow_major_auto_core_updates', array( $this, 'core_should_update' ), self::LATE );
			add_filter( 'allow_minor_auto_core_updates', array( $this, 'core_should_update' ), self::LATE );
			add_filter( 'auto_update_core', array( $this, 'core_allowed' ), self::LATE );
		}

		if ( 'off' === self::core( 'plugin_updates' ) ) {
			$this->block_kind( 'plugins' );
		}
		if ( 'off' === self::core( 'theme_updates' ) ) {
			$this->block_kind( 'themes' );
		}

		// Translations.
		$tr = self::core( 'translation_updates' );
		if ( 'off' === $tr ) {
			add_filter( 'auto_update_translation', '__return_false' );
			add_filter( 'async_update_translation', '__return_false' );
			foreach ( array( 'update_themes', 'update_plugins', 'update_core' ) as $t ) {
				add_filter( 'pre_transient_' . $t, array( $this, 'no_translations' ), 10, 2 );
				add_filter( 'pre_site_transient_' . $t, array( $this, 'no_translations' ), 10, 2 );
			}
		}
		if ( 'automatic' === $tr ) {
			add_filter( 'auto_update_translation', '__return_true', self::LATE );
			add_filter( 'async_update_translation', '__return_true', self::LATE );
		}
		if ( null === $tr || in_array( $tr, array( 'on', 'automatic_off' ), true ) ) {
			add_filter( 'auto_update_translation', '__return_false', self::LATE );
			add_filter( 'async_update_translation', '__return_false', self::LATE );
		}

		// "Update available" emails, per kind.
		foreach ( array( 'plugins', 'themes', 'translations' ) as $kind ) {
			if ( 'off' === self::core( 'notification_core_update_emails_' . $kind ) ) {
				add_filter( 'send_update_notification_email', array( $this, 'maybe_no_email' ), 10, 3 );
				break;
			}
		}

		$this->auto_rule( 'plugin', self::core( 'plugin_updates' ) );
		$this->auto_rule( 'theme', self::core( 'theme_updates' ) );

		// Blocked plugins/themes never show an update.
		add_filter( 'site_transient_update_plugins', array( $this, 'hide_blocked_plugins' ), self::LATE );
		add_filter( 'site_transient_update_themes', array( $this, 'hide_blocked_themes' ), self::LATE );
		add_filter( 'http_request_args', array( $this, 'strip_blocked_from_api' ), 5, 2 );

		// Emails about completed auto-updates.
		add_filter( 'automatic_updates_send_debug_email', '__return_false', self::LATE );
		add_filter( 'auto_core_update_send_email', '__return_false', self::LATE );
		add_filter( 'auto_plugin_update_send_email', '__return_false', self::LATE, 2 );
		add_filter( 'auto_theme_update_send_email', '__return_false', self::LATE, 2 );
		if ( 'off' === self::core( 'notification_core_update_emails' ) ) {
			add_filter( 'send_core_update_notification_email', '__return_false', self::LATE );
		}

		add_filter( 'plugin_auto_update_setting_html', array( $this, 'plugins_page_text' ), 11, 2 );
	}

	private function auto_rule( string $kind, ?string $mode ): void {
		$hook = 'auto_update_' . $kind;
		if ( 'automatic' === $mode ) {
			add_filter( $hook, '__return_true', self::LATE, 2 );
		} elseif ( 'individual' === $mode ) {
			add_filter( $hook, array( $this, 'plugin' === $kind ? 'auto_plugin' : 'auto_theme' ), self::LATE, 2 );
		} elseif ( 'automatic_off' === $mode ) {
			add_filter( $hook, '__return_false', self::LATE, 2 );
		} else {
			// 'on' (manual), 'off' or not set.
			add_filter( $hook, '__return_false', 1, 2 );
		}
	}

	public function maybe_auto_update(): void {
		if ( wp_doing_cron() && ! doing_action( 'wp_maybe_auto_update' ) ) {
			do_action( 'wp_maybe_auto_update' );
		}
	}

	public function allow_automatic_updater( $allowed, $context ) {
		return 'automatic_updater' === $context ? true : $allowed;
	}

	public function core_should_update() {
		$mode = self::core( 'core_updates' );
		if ( doing_filter( 'allow_dev_auto_core_updates' ) ) {
			$this->core_allowed = in_array( $mode, array( 'automatic', 'automatic_minor' ), true ) && 'on' === self::core( 'automatic_development_updates' );
		} elseif ( doing_filter( 'allow_major_auto_core_updates' ) ) {
			$this->core_allowed = 'automatic' === $mode;
		} elseif ( doing_filter( 'allow_minor_auto_core_updates' ) ) {
			$this->core_allowed = in_array( $mode, array( 'automatic', 'automatic_minor' ), true );
		}
		return $this->core_allowed;
	}

	public function core_allowed() {
		return $this->core_allowed;
	}

	private static function is_site_health_probe( $item ): bool {
		return ( isset( $item->theme ) && 'a-fake-theme' === $item->theme )
			|| ( isset( $item->plugin ) && 'a-fake-plugin/a-fake-plugin.php' === $item->plugin );
	}

	public function auto_plugin( $update, $item ) {
		return self::is_site_health_probe( $item ) || ( isset( $item->plugin ) && in_array( $item->plugin, self::options( 'plugins_automatic' ) ) ); // phpcs:ignore WordPress.PHP.StrictInArray
	}

	public function auto_theme( $update, $item ) {
		return self::is_site_health_probe( $item ) || ( isset( $item->theme ) && in_array( $item->theme, self::options( 'themes_automatic' ) ) ); // phpcs:ignore WordPress.PHP.StrictInArray
	}

	public function maybe_no_email( $send, $type ) {
		$map = array(
			'plugin'      => 'plugins',
			'theme'       => 'themes',
			'translation' => 'translations',
		);
		if ( isset( $map[ $type ] ) && 'off' === self::core( 'notification_core_update_emails_' . $map[ $type ] ) ) {
			return false;
		}
		return $send;
	}

	public function hide_blocked_plugins( $value ) {
		if ( ! isset( $value->response ) || empty( $value->response ) ) {
			return $value;
		}
		foreach ( self::options( 'plugins' ) as $plugin ) {
			unset( $value->response[ $plugin ] );
		}
		return $value;
	}

	public function hide_blocked_themes( $value ) {
		if ( ! isset( $value->response ) || empty( $value->response ) ) {
			return $value;
		}
		foreach ( self::options( 'themes' ) as $theme ) {
			unset( $value->response[ $theme ] );
		}
		return $value;
	}

	/** Don't even ask api.wordpress.org about blocked plugins/themes. */
	public function strip_blocked_from_api( $r, $url ) {
		if ( 'api.wordpress.org' !== strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			return $r;
		}
		if ( isset( $r['body']['plugins'] ) ) {
			$p = json_decode( $r['body']['plugins'], true );
			if ( ! empty( $p['active'] ) && is_array( $p['active'] ) ) {
				foreach ( self::options( 'plugins' ) as $plugin ) {
					unset( $p[ $plugin ] );
					$key = array_search( $plugin, $p['active'], true );
					if ( false !== $key ) {
						unset( $p['active'][ $key ] );
						$p['active'] = array_values( $p['active'] );
					}
				}
				$r['body']['plugins'] = wp_json_encode( $p );
			}
		}
		if ( isset( $r['body']['themes'] ) ) {
			$t = json_decode( $r['body']['themes'], true );
			foreach ( self::options( 'themes' ) as $theme ) {
				unset( $t[ $theme ] );
			}
			$r['body']['themes'] = wp_json_encode( $t );
		}
		return $r;
	}

	/* ---- blocking a whole kind ---------------------------------------- */

	private function block_core(): void {
		add_action( 'admin_init', array( $this, 'stop_core_checks' ) );
		add_filter( 'pre_transient_update_core', array( $this, 'core_checked_now' ), 10, 2 );
		add_filter( 'pre_site_transient_update_core', array( $this, 'core_checked_now' ), 10, 2 );
		foreach ( array( 'allow_minor_auto_core_updates', 'allow_major_auto_core_updates', 'allow_dev_auto_core_updates', 'auto_update_core', 'wp_auto_update_core', 'auto_core_update_send_email', 'send_core_update_notification_email', 'automatic_updates_send_debug_email' ) as $f ) {
			add_filter( $f, '__return_false' );
		}
	}

	public function stop_core_checks(): void {
		remove_action( 'wp_version_check', 'wp_version_check' );
		remove_action( 'admin_init', '_maybe_update_core' );
		wp_clear_scheduled_hook( 'wp_version_check' );
		remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_auto_update_core' );
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
		add_action( 'upgrader_process_complete', array( $this, 'forget_checks_after_translation' ), 10, 2 );
	}

	private function block_kind( string $kind ): void {
		// $kind: plugins | themes
		$this->blocked_kinds[ $kind ] = true;
		add_action( 'admin_init', array( $this, 'stop_' . $kind . '_checks' ) );
		add_filter( 'pre_transient_update_' . $kind, array( $this, $kind . '_checked_now' ), 10, 2 );
		add_filter( 'pre_site_transient_update_' . $kind, array( $this, $kind . '_checked_now' ), 10, 2 );
		add_filter( 'auto_update_' . rtrim( $kind, 's' ), '__return_false' );
	}

	/** @var array */
	private $blocked_kinds = array();

	public function stop_plugins_checks(): void {
		remove_action( 'load-plugins.php', 'wp_update_plugins' );
		remove_action( 'load-update.php', 'wp_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'wp_update_plugins', 'wp_update_plugins' );
		remove_action( 'load-update-core.php', 'wp_update_plugins' );
		wp_clear_scheduled_hook( 'wp_update_plugins' );
		add_action( 'upgrader_process_complete', array( $this, 'forget_checks_after_translation' ), 10, 2 );
	}

	public function stop_themes_checks(): void {
		remove_action( 'load-themes.php', 'wp_update_themes' );
		remove_action( 'load-update.php', 'wp_update_themes' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		remove_action( 'wp_update_themes', 'wp_update_themes' );
		remove_action( 'load-update-core.php', 'wp_update_themes' );
		wp_clear_scheduled_hook( 'wp_update_themes' );
		add_action( 'upgrader_process_complete', array( $this, 'forget_checks_after_translation' ), 10, 2 );
	}

	public function forget_checks_after_translation( $upgrader, $extra ): void {
		if ( isset( $extra['type'] ) && 'translation' === $extra['type'] ) {
			delete_site_transient( 'eum_core_checked' );
			delete_site_transient( 'eum_plugins_checked' );
			delete_site_transient( 'eum_themes_checked' );
		}
	}

	public function core_checked_now( $pre, $key ) {
		return $this->checked_now( 'core', $key );
	}

	public function plugins_checked_now( $pre, $key ) {
		return $this->checked_now( 'plugins', $key );
	}

	public function themes_checked_now( $pre, $key ) {
		return $this->checked_now( 'themes', $key );
	}

	/**
	 * A blocked kind always reports "checked just now, no updates" (kept for
	 * 6 hours), but still carries the translations WordPress found.
	 */
	private function checked_now( string $kind, string $key ) {
		$cache   = 'eum_' . $kind . '_checked';
		$checked = get_site_transient( $cache );
		if ( $checked ) {
			return $checked;
		}
		$method = $kind . '_checked_now';
		remove_filter( 'pre_transient_update_' . $kind, array( $this, $method ), 10 );
		remove_filter( 'pre_site_transient_update_' . $kind, array( $this, $method ), 10 );
		delete_site_transient( $key );
		if ( 'core' === $kind ) {
			wp_version_check();
		} elseif ( 'plugins' === $kind ) {
			wp_update_plugins();
		} else {
			wp_update_themes();
		}
		$found = get_site_transient( $key );

		$wp_version = get_bloginfo( 'version' );
		$current                  = new stdClass();
		$current->updates         = array();
		$current->version_checked = $wp_version;
		$current->last_checked    = time();
		if ( isset( $found->translations ) ) {
			$current->translations = $found->translations;
		}
		add_filter( 'pre_transient_update_' . $kind, array( $this, $method ), 10, 2 );
		add_filter( 'pre_site_transient_update_' . $kind, array( $this, $method ), 10, 2 );
		set_site_transient( $cache, $current, 6 * HOUR_IN_SECONDS );
		return $current;
	}

	public function no_translations( $pre, $key ) {
		foreach ( array( 'update_themes', 'update_plugins', 'update_core' ) as $t ) {
			remove_filter( 'pre_transient_' . $t, array( $this, 'no_translations' ), 10 );
			remove_filter( 'pre_site_transient_' . $t, array( $this, 'no_translations' ), 10 );
		}
		$value = get_site_transient( $key );
		if ( isset( $value->translations ) ) {
			$value->translations = array();
		}
		foreach ( array( 'update_themes', 'update_plugins', 'update_core' ) as $t ) {
			add_filter( 'pre_transient_' . $t, array( $this, 'no_translations' ), 10, 2 );
			add_filter( 'pre_site_transient_' . $t, array( $this, 'no_translations' ), 10, 2 );
		}
		return $value;
	}

	private function block_everything(): void {
		$this->block_core();
		$this->block_kind( 'plugins' );
		$this->block_kind( 'themes' );
		add_filter( 'automatic_updater_disabled', '__return_true', self::LATE );
		foreach ( array( 'auto_update_translation', 'async_update_translation', 'allow_minor_auto_core_updates', 'allow_major_auto_core_updates', 'allow_dev_auto_core_updates', 'auto_update_core', 'wp_auto_update_core', 'auto_core_update_send_email', 'send_core_update_notification_email', 'auto_update_plugin', 'auto_update_theme', 'automatic_updates_send_debug_email' ) as $f ) {
			add_filter( $f, '__return_false', self::LATE );
		}
	}

	/* ---- plugins page ------------------------------------------------- */

	public function plugins_page_text( $html, $plugin_file ) {
		$url  = esc_url( admin_url( 'admin.php?page=dukkan-settings&tab=updates' ) );
		$mode = self::core( 'plugin_updates' );
		$text = __( 'Managed by Dukkan.', 'dukkan-plugin' );
		if ( in_array( $mode, array( 'off', 'automatic_off' ), true ) ) {
			$text = __( 'Disabled in Dukkan.', 'dukkan-plugin' );
		} elseif ( 'on' === $mode ) {
			$text = __( 'Managed by Dukkan (manual).', 'dukkan-plugin' );
		} elseif ( 'individual' === $mode ) {
			if ( in_array( $plugin_file, self::options( 'plugins' ), true ) ) {
				$text = __( 'Updates blocked in Dukkan.', 'dukkan-plugin' );
			} elseif ( in_array( $plugin_file, self::options( 'plugins_automatic' ), true ) ) {
				$text = __( 'Auto-updates on (Dukkan).', 'dukkan-plugin' );
			} else {
				$text = __( 'Manual updates (Dukkan).', 'dukkan-plugin' );
			}
		}
		if ( ! class_exists( 'Dukkan_Updates_Admin' ) || ! Dukkan_Updates_Admin::visible() ) {
			return '<span>' . esc_html( $text ) . '</span>';
		}
		return '<a href="' . $url . '">' . esc_html( $text ) . '</a>';
	}
}

/**
 * Update log, written to the same table Easy Updates Manager used.
 */
class Dukkan_Updates_Log {

	const DB_VERSION = '1.1.6';

	/** @var self|null */
	private static $instance = null;

	/** @var array */
	private $pending = array();

	/** @var bool */
	private $auto = false;

	public static function run(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->base_prefix . 'eum_logs';
	}

	private function __construct() {
		self::ensure_table();
		register_shutdown_function( array( $this, 'flush' ) );
		add_action( 'pre_auto_update', array( $this, 'mark_auto' ) );
		add_filter( 'upgrader_package_options', array( $this, 'remember' ), 10, 1 );
		add_filter( 'upgrader_pre_download', array( $this, 'remember_core' ), 10, 3 );
		add_action( 'automatic_updates_complete', array( $this, 'log_automatic' ) );
		add_action( 'upgrader_process_complete', array( $this, 'log_manual' ), 1, 2 );
	}

	public static function ensure_table(): void {
		if ( version_compare( self::DB_VERSION, (string) get_site_option( 'mpsum_log_table_version', '0' ), '<=' ) ) {
			return;
		}
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				log_id BIGINT(20) NOT NULL AUTO_INCREMENT,
				user_id BIGINT(20) NOT NULL DEFAULT 0,
				name VARCHAR(255) NOT NULL,
				type VARCHAR(255) NOT NULL,
				version_from VARCHAR(255) NOT NULL,
				version VARCHAR(255) NOT NULL,
				action VARCHAR(255) NOT NULL,
				status VARCHAR(255) NOT NULL,
				notes TEXT NOT NULL,
				stacktrace TEXT DEFAULT NULL,
				date DATETIME NOT NULL,
				PRIMARY KEY  (log_id)
			) {$charset};"
		);
		update_site_option( 'mpsum_log_table_version', self::DB_VERSION );
	}

	public function mark_auto(): void {
		$this->auto = true;
	}

	/** Note the version an item is updating from/to before it installs. */
	public function remember( $options ) {
		$extra = $options['hook_extra'] ?? array();
		if ( isset( $extra['action'] ) && 'update' !== $extra['action'] ) {
			return $options;
		}
		if ( isset( $extra['plugin'] ) ) {
			$current = get_site_transient( 'update_plugins' );
			$file    = $extra['plugin'];
			if ( isset( $current->response[ $file ] ) ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$plugins                           = get_plugins();
				$this->pending['plugin'][ $file ] = array(
					'name'   => $plugins[ $file ]['Name'] ?? 'Unknown',
					'from'   => $plugins[ $file ]['Version'] ?? 0,
					'to'     => is_object( $current->response[ $file ] ) && isset( $current->response[ $file ]->new_version ) ? $current->response[ $file ]->new_version : 0,
					'status' => 0,
				);
			}
		} elseif ( isset( $extra['theme'] ) ) {
			$current = get_site_transient( 'update_themes' );
			$slug    = $extra['theme'];
			if ( isset( $current->response[ $slug ] ) ) {
				$theme                            = wp_get_theme( $slug );
				$this->pending['theme'][ $slug ] = array(
					'name'   => $theme->exists() ? $theme->get( 'Name' ) : 'Unknown',
					'from'   => $theme->exists() ? $theme->get( 'Version' ) : 0,
					'to'     => $current->response[ $slug ]['new_version'] ?? 0,
					'status' => 0,
				);
			}
		} elseif ( isset( $extra['language_update_type'], $extra['language_update'] ) ) {
			$this->remember_translation( $extra );
		}
		add_filter( 'upgrader_post_install', array( $this, 'set_status' ), 10, 3 );
		return $options;
	}

	private function remember_translation( array $extra ): void {
		$lu   = $extra['language_update'];
		$type = $extra['language_update_type'];
		$from = $lu->version ?? 0;
		if ( 'plugin' === $type || 'theme' === $type ) {
			$current = get_site_transient( 'theme' === $type ? 'update_themes' : 'update_plugins' );
			if ( empty( $current->translations ) ) {
				return;
			}
			foreach ( $current->translations as $t ) {
				if ( isset( $lu->slug ) && $t['slug'] !== $lu->slug ) {
					continue;
				}
				if ( 'theme' === $type ) {
					$theme = wp_get_theme( $t['slug'] );
					$name  = $theme->exists() ? $theme->get( 'Name' ) : 'Unknown';
				} else {
					if ( ! function_exists( 'get_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					$found = get_plugins( '/' . $t['slug'] );
					$found = reset( $found );
					$name  = $found['Name'] ?? 'Unknown';
				}
				$this->pending['translation'][ $t['slug'] ] = array(
					'name'   => $name . ' (' . $t['language'] . ')',
					'from'   => $from,
					'to'     => $t['version'] ?? 0,
					'status' => 0,
				);
				break;
			}
			return;
		}
		$current = get_site_transient( 'update_core' );
		if ( empty( $current->translations ) ) {
			return;
		}
		foreach ( $current->translations as $t ) {
			$this->pending['translation'][ 'wordpress_default_' . $t['language'] ] = array(
				'name'   => 'WordPress (' . $t['language'] . ')',
				'from'   => $from,
				'to'     => $t['version'] ?? 0,
				'status' => 0,
			);
		}
	}

	public function remember_core( $reply, $package, $upgrader ) {
		add_filter( 'upgrader_post_install', array( $this, 'set_status' ), 10, 3 );
		if ( ! is_a( $upgrader, 'Core_Upgrader' ) ) {
			return $reply;
		}
		$wp_version = get_bloginfo( 'version' );
		$item       = array(
			'name'   => 'WordPress',
			'from'   => $wp_version,
			'to'     => 0,
			'status' => 0,
			'notes'  => '',
		);
		add_action( '_core_updated_successfully', array( $this, 'core_success' ) );
		$current = get_site_transient( 'update_core' );
		if ( $current && ! empty( $current->updates ) ) {
			foreach ( $current->updates as $u ) {
				if ( ( $this->auto && 'autoupdate' !== $u->response ) || ( ! $this->auto && 'autoupdate' === $u->response ) || empty( $u->packages ) ) {
					continue;
				}
				foreach ( $u->packages as $url ) {
					if ( $url === $package ) {
						$item['to']    = $u->current;
						$item['name'] .= " ({$u->locale})";
						break;
					}
				}
			}
		}
		$this->pending['core']['new'] = $item;
		return $reply;
	}

	public function core_success(): void {
		if ( isset( $this->pending['core']['new'] ) ) {
			$this->pending['core']['new']['status'] = 1;
		}
	}

	public function set_status( $response, $extra, $result ) {
		$ok = $result && ! is_wp_error( $result ) ? 1 : 0;
		if ( isset( $extra['plugin'], $this->pending['plugin'][ $extra['plugin'] ] ) ) {
			$this->pending['plugin'][ $extra['plugin'] ]['status'] = $ok;
		} elseif ( isset( $extra['theme'], $this->pending['theme'][ $extra['theme'] ] ) ) {
			$this->pending['theme'][ $extra['theme'] ]['status'] = $ok;
		} elseif ( isset( $extra['language_update_type'], $extra['language_update'] ) ) {
			$key = 'core' === $extra['language_update_type'] ? 'wordpress_default_' . $extra['language_update']->language : ( $extra['language_update']->slug ?? '' );
			if ( isset( $this->pending['translation'][ $key ] ) ) {
				$this->pending['translation'][ $key ]['status'] = $ok;
			}
		}
		return $response;
	}

	public function log_manual( $upgrader, $extra ): void {
		if ( isset( $extra['action'] ) && 'update' !== $extra['action'] ) {
			return;
		}
		$notes = '';
		if ( isset( $upgrader->skin ) && is_a( $upgrader->skin, 'Automatic_Upgrader_Skin' ) ) {
			foreach ( $upgrader->skin->get_upgrade_messages() as $m ) {
				$notes .= $m . "\n\r\n\r";
			}
		}
		$type = $extra['type'] ?? '';
		if ( 'plugin' === $type || 'theme' === $type ) {
			$items = $extra[ $type . 's' ] ?? ( $extra[ $type ] ?? array() );
			foreach ( (array) $items as $key ) {
				$this->write_pending( $type, $key, $notes );
			}
		} elseif ( 'core' === $type ) {
			foreach ( array_keys( $this->pending['core'] ?? array() ) as $key ) {
				$this->write_pending( 'core', $key, $notes );
			}
		} elseif ( 'translation' === $type ) {
			foreach ( (array) ( $extra['translations'] ?? array() ) as $t ) {
				$key = 'core' === $t['type'] ? 'wordpress_default_' . $t['language'] : $t['slug'];
				$this->write_pending( 'translation', $key, $notes );
			}
		}
		$this->flush();
	}

	public function log_automatic( $results ): void {
		if ( empty( $results ) ) {
			return;
		}
		foreach ( $results as $type => $list ) {
			foreach ( (array) $list as $result ) {
				$notes = '';
				foreach ( (array) ( $result->messages ?? array() ) as $m ) {
					$notes .= $m . "\n\r\n\r";
				}
				if ( 'core' === $type ) {
					foreach ( array_keys( $this->pending['core'] ?? array() ) as $key ) {
						$this->write_pending( 'core', $key, $notes, 'automatic' );
					}
				} elseif ( 'translation' === $type ) {
					$this->write_pending( 'translation', $result->item->slug ?? '', $notes, 'automatic' );
				} elseif ( isset( $result->item->$type ) ) {
					$this->write_pending( $type, $result->item->$type, $notes, 'automatic' );
				}
			}
		}
		$this->flush();
	}

	private function write_pending( string $type, string $key, string $notes = '', string $action = '' ): void {
		if ( ! isset( $this->pending[ $type ][ $key ] ) ) {
			return;
		}
		$d = $this->pending[ $type ][ $key ];
		self::insert( $d['name'], $type, $d['from'], $d['to'], $action ? $action : ( $this->auto ? 'automatic' : 'manual' ), $d['status'], get_current_user_id(), $notes ? $notes : ( $d['notes'] ?? '' ) );
		unset( $this->pending[ $type ][ $key ] );
		if ( empty( $this->pending[ $type ] ) ) {
			unset( $this->pending[ $type ] );
		}
	}

	/** Write whatever is still pending (e.g. when a request ends mid-update). */
	public function flush(): void {
		foreach ( $this->pending as $type => $items ) {
			foreach ( array_keys( (array) $items ) as $key ) {
				$this->write_pending( $type, (string) $key );
			}
		}
		$this->pending = array();
	}

	public static function insert( $name, $type, $from, $to, $action, $status, $user_id = 0, $notes = '' ): void {
		global $wpdb;
		$notes = preg_replace( '/\?.*/', '', str_replace( '&#8230;', '', (string) $notes ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table(),
			array(
				'user_id'      => (int) $user_id,
				'name'         => (string) $name,
				'type'         => (string) $type,
				'version_from' => '' === (string) $from ? '0.00' : (string) $from,
				'version'      => (string) $to,
				'action'       => (string) $action,
				'status'       => (string) $status,
				'date'         => current_time( 'mysql' ),
				'notes'        => (string) $notes,
				'stacktrace'   => '',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/** Latest log rows. */
	public static function latest( int $limit = 100, int $offset = 0 ): array {
		global $wpdb;
		$table = self::table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT log_id, user_id, name, type, version_from, version, action, status, date FROM {$table} ORDER BY log_id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	public static function count(): int {
		global $wpdb;
		$table = self::table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return 0;
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function clear(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

/**
 * Update rules and log.
 */
class Dukkan_Updates_CLI {

	const MODES = array( 'allowed', 'auto', 'blocked' );

	/**
	 * Show whether the module runs and the main rules.
	 *
	 * @subcommand status
	 */
	public function status() {
		$why = Dukkan_Updates::idle_reason();
		WP_CLI::line( 'Running: ' . ( '' === $why ? 'yes' : 'no (' . $why . ')' ) );
		foreach ( array( 'all_updates', 'core_updates', 'plugin_updates', 'theme_updates', 'translation_updates' ) as $k ) {
			WP_CLI::line( sprintf( '%-20s %s', $k . ':', Dukkan_Updates::core( $k ) ?? '(not set)' ) );
		}
		WP_CLI::line( sprintf( '%-20s %d', 'blocked plugins:', count( Dukkan_Updates::options( 'plugins' ) ) ) );
		WP_CLI::line( sprintf( '%-20s %d', 'auto plugins:', count( Dukkan_Updates::options( 'plugins_automatic' ) ) ) );
		WP_CLI::line( sprintf( '%-20s %d', 'log entries:', Dukkan_Updates_Log::count() ) );
	}

	/**
	 * List installed plugins and their update rule.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json or csv.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$rows = array();
		foreach ( get_plugins() as $file => $p ) {
			$rows[] = array(
				'plugin'  => $file,
				'name'    => $p['Name'],
				'version' => $p['Version'],
				'rule'    => self::rule( $file ),
			);
		}
		WP_CLI\Utils\format_items( $assoc['format'], $rows, array( 'plugin', 'name', 'version', 'rule' ) );
	}

	/**
	 * Set a plugin's update rule.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : Plugin file, e.g. woocommerce/woocommerce.php.
	 *
	 * <rule>
	 * : allowed (manual updates), auto (auto-update) or blocked.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dukkan-updates set elementor/elementor.php auto
	 */
	public function set( $args ) {
		list( $file, $rule ) = $args;
		if ( ! in_array( $rule, self::MODES, true ) ) {
			WP_CLI::error( 'Rule must be allowed, auto or blocked.' );
		}
		Dukkan_Updates_Rules::set_plugin( $file, $rule );
		WP_CLI::success( "$file: $rule" );
	}

	/**
	 * Show the update log.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : How many entries.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : table, json or csv.
	 * ---
	 * default: table
	 * ---
	 */
	public function log( $args, $assoc ) {
		$rows = Dukkan_Updates_Log::latest( max( 1, (int) $assoc['limit'] ) );
		if ( ! $rows ) {
			WP_CLI::line( 'No log entries.' );
			return;
		}
		WP_CLI\Utils\format_items( $assoc['format'], $rows, array( 'date', 'name', 'type', 'version_from', 'version', 'action', 'status' ) );
	}

	private static function rule( string $file ): string {
		if ( in_array( $file, Dukkan_Updates::options( 'plugins' ), true ) ) {
			return 'blocked';
		}
		if ( in_array( $file, Dukkan_Updates::options( 'plugins_automatic' ), true ) ) {
			return 'auto';
		}
		return 'allowed';
	}
}

/**
 * Small helpers shared by the CLI and the Updates tab.
 */
class Dukkan_Updates_Rules {

	public static function set_plugin( string $file, string $rule ): void {
		self::set_item( 'plugins', $file, $rule );
	}

	public static function set_theme( string $slug, string $rule ): void {
		self::set_item( 'themes', $slug, $rule );
	}

	/** $kind: plugins | themes; $rule: allowed | auto | blocked. */
	private static function set_item( string $kind, string $item, string $rule ): void {
		$blocked = array_values( array_diff( Dukkan_Updates::options( $kind ), array( $item ) ) );
		$auto    = array_values( array_diff( Dukkan_Updates::options( $kind . '_automatic' ), array( $item ) ) );
		if ( 'blocked' === $rule ) {
			$blocked[] = $item;
		} elseif ( 'auto' === $rule ) {
			$auto[] = $item;
		}
		$o                        = Dukkan_Updates::options();
		$o[ $kind ]               = $blocked;
		$o[ $kind . '_automatic' ] = $auto;
		update_site_option( Dukkan_Updates::OPTION, $o );
	}
}
