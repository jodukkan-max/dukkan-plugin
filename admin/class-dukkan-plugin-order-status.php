<?php

/**
 * Custom WooCommerce order status management.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * Manages custom WooCommerce order statuses — add, edit, update, delete, reorder.
 *
 * Stores custom statuses in a WordPress option as an ordered array and exposes
 * AJAX handlers for the Dukkan settings UI.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Order_Status {

	/**
	 * Option key that holds all custom order statuses.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const OPTION_KEY = 'dukkan_custom_order_statuses';

	/**
	 * Maximum length allowed for a WooCommerce status slug.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const SLUG_MAX_LENGTH = 20;

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_filter( 'dukkan_settings_tabs', array( $this, 'add_order_status_tab' ) );
		add_action( 'dukkan_settings_tab_content_order_status', array( $this, 'render_tab_content' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_dukkan_os_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_dukkan_os_add', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_dukkan_os_update', array( $this, 'ajax_update' ) );
		add_action( 'wp_ajax_dukkan_os_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_dukkan_os_reorder', array( $this, 'ajax_reorder' ) );
	}

	// -------------------------------------------------------------------------
	// Tab Registration
	// -------------------------------------------------------------------------

	/**
	 * Add the Order Status tab to Dukkan settings.
	 *
	 * @since  1.0.0
	 * @param  array $tabs Existing tabs.
	 * @return array
	 */
	public function add_order_status_tab( $tabs ) {
		$tabs['order_status'] = array(
			'title' => __( 'Order Status', 'dukkan-plugin' ),
			'icon'  => 'fa-solid fa-truck-fast',
		);
		return $tabs;
	}

	// -------------------------------------------------------------------------
	// Tab Content
	// -------------------------------------------------------------------------

	/**
	 * Render the Order Status tab content.
	 *
	 * @since 1.0.0
	 */
	public function render_tab_content() {
		$statuses = $this->get_all_statuses();
		require plugin_dir_path( __FILE__ ) . 'partials/dukkan-order-status-settings.php';
	}

	// -------------------------------------------------------------------------
	// Data Access
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all custom order statuses as an ordered array.
	 *
	 * @since  1.0.0
	 * @return array<int, array{name: string, slug: string}>
	 */
	public function get_all_statuses() {
		$statuses = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $statuses ) ) {
			return array();
		}
		// Support both legacy associative and new indexed formats.
		if ( $this->is_assoc( $statuses ) ) {
			return array_values( $statuses );
		}
		return $statuses;
	}

	/**
	 * Persist the statuses array.
	 *
	 * @since 1.0.0
	 * @param array $statuses
	 */
	private function save_statuses( $statuses ) {
		update_option( self::OPTION_KEY, $statuses, 'no' );
	}

	/**
	 * Check if a slug is already taken.
	 *
	 * @since  1.0.0
	 * @param  string $slug
	 * @param  string $exclude Previous slug to ignore (for updates).
	 * @return bool
	 */
	private function slug_exists( $slug, $exclude = '' ) {
		foreach ( $this->get_all_statuses() as $status ) {
			if ( $status['slug'] === $slug && $slug !== $exclude ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect if an array is associative.
	 *
	 * @since  1.0.0
	 * @param  array $arr
	 * @return bool
	 */
	private function is_assoc( $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate order-status input.
	 *
	 * @since  1.0.0
	 * @param  string $name
	 * @param  string $slug
	 * @param  string $old_slug Previous slug (empty for new statuses).
	 * @return array{valid: bool, errors: string[], sanitized: array{name: string, slug: string}}
	 */
	private function validate( $name, $slug, $old_slug = '' ) {
		$errors = array();

		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			$errors[] = __( 'Status name is required.', 'dukkan-plugin' );
		}

		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			$errors[] = __( 'Status slug is required.', 'dukkan-plugin' );
		} elseif ( strlen( $slug ) > self::SLUG_MAX_LENGTH ) {
			$errors[] = sprintf(
				/* translators: %d: max characters allowed */
				__( 'Status slug must be %d characters or fewer.', 'dukkan-plugin' ),
				self::SLUG_MAX_LENGTH
			);
		} elseif ( $this->slug_exists( $slug, $old_slug ) ) {
			$errors[] = __( 'A status with this slug already exists.', 'dukkan-plugin' );
		}

		return array(
			'valid'     => empty( $errors ),
			'errors'    => $errors,
			'sanitized' => array(
				'name' => $name,
				'slug' => $slug,
			),
		);
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers
	// -------------------------------------------------------------------------

	/**
	 * Verify AJAX nonce and capability.
	 *
	 * @since 1.0.0
	 */
	private function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dukkan-plugin' ) ), 403 );
		}
		check_ajax_referer( 'wpldp_nonce', 'nonce' );
	}

	/**
	 * AJAX: list all statuses.
	 *
	 * @since 1.0.0
	 */
	public function ajax_list() {
		$this->verify_ajax();
		wp_send_json_success( $this->get_all_statuses() );
	}

	/**
	 * AJAX: add a new status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_add() {
		$this->verify_ajax();

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		$result = $this->validate( $name, $slug );
		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => join( ', ', $result['errors'] ) ), 400 );
		}

		$statuses   = $this->get_all_statuses();
		$statuses[] = $result['sanitized'];
		$this->save_statuses( $statuses );

		wp_send_json_success( $result['sanitized'] );
	}

	/**
	 * AJAX: update an existing status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_update() {
		$this->verify_ajax();

		$old_slug = isset( $_POST['old_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['old_slug'] ) ) : '';
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$slug     = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

		$statuses = $this->get_all_statuses();
		$found    = false;

		foreach ( $statuses as $i => $status ) {
			if ( $status['slug'] === $old_slug ) {
				$found = $i;
				break;
			}
		}

		if ( false === $found ) {
			wp_send_json_error( array( 'message' => __( 'Order status not found.', 'dukkan-plugin' ) ), 404 );
		}

		$result = $this->validate( $name, $slug, $old_slug );
		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => join( ', ', $result['errors'] ) ), 400 );
		}

		$statuses[ $found ] = $result['sanitized'];
		$this->save_statuses( $statuses );

		wp_send_json_success( $result['sanitized'] );
	}

	/**
	 * AJAX: delete a status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete() {
		$this->verify_ajax();

		$slug     = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$statuses = $this->get_all_statuses();

		foreach ( $statuses as $i => $status ) {
			if ( $status['slug'] === $slug ) {
				unset( $statuses[ $i ] );
				$this->save_statuses( array_values( $statuses ) );
				wp_send_json_success( array( 'deleted' => true, 'slug' => $slug ) );
			}
		}

		wp_send_json_error( array( 'message' => __( 'Order status not found.', 'dukkan-plugin' ) ), 404 );
	}

	/**
	 * AJAX: reorder statuses via drag-and-drop.
	 *
	 * @since 1.0.0
	 */
	public function ajax_reorder() {
		$this->verify_ajax();

		$order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array();
		if ( ! is_array( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'dukkan-plugin' ) ), 400 );
		}

		$statuses = $this->get_all_statuses();

		// Build slug → data map.
		$map = array();
		foreach ( $statuses as $status ) {
			$map[ $status['slug'] ] = $status;
		}

		// Rebuild in the new order.
		$reordered = array();
		foreach ( $order as $slug ) {
			$slug = sanitize_text_field( $slug );
			if ( isset( $map[ $slug ] ) ) {
				$reordered[] = $map[ $slug ];
			}
		}

		$this->save_statuses( $reordered );

		wp_send_json_success( array( 'reordered' => true ) );
	}
}
