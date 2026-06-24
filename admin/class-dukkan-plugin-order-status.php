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
 * Manages custom WooCommerce order statuses — add, edit, update, delete.
 *
 * Stores custom statuses in a WordPress option keyed by slug and exposes
 * admin-post.php handlers for the Dukkan settings UI.
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
		add_action( 'admin_post_dukkan_add_order_status', array( $this, 'handle_add' ) );
		add_action( 'admin_post_dukkan_update_order_status', array( $this, 'handle_update' ) );
		add_action( 'admin_post_dukkan_delete_order_status', array( $this, 'handle_delete' ) );
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
		$statuses     = $this->get_all_statuses();
		$edit_slug    = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
		$edit_status  = $edit_slug && isset( $statuses[ $edit_slug ] ) ? $statuses[ $edit_slug ] : null;
		$notice       = isset( $_GET['dukkan_os_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['dukkan_os_msg'] ) ) : '';
		$notice_type  = isset( $_GET['dukkan_os_type'] ) ? sanitize_text_field( wp_unslash( $_GET['dukkan_os_type'] ) ) : 'success';

		require plugin_dir_path( __FILE__ ) . 'partials/dukkan-order-status-settings.php';
	}

	// -------------------------------------------------------------------------
	// Data Access
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all custom order statuses from the options table.
	 *
	 * @since  1.0.0
	 * @return array<string, array{name: string, slug: string}>
	 */
	public function get_all_statuses() {
		$statuses = get_option( self::OPTION_KEY, array() );
		return is_array( $statuses ) ? $statuses : array();
	}

	/**
	 * Persist the full statuses array.
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
		$statuses = $this->get_all_statuses();
		if ( ! isset( $statuses[ $slug ] ) ) {
			return false;
		}
		return $exclude !== $slug;
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
	// CRUD Handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle adding a new custom order status.
	 *
	 * @since 1.0.0
	 */
	public function handle_add() {
		$this->verify_capability_and_nonce( 'dukkan_add_order_status' );

		$name = isset( $_POST['dukkan_os_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dukkan_os_name'] ) ) : '';
		$slug = isset( $_POST['dukkan_os_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['dukkan_os_slug'] ) ) : '';

		$result = $this->validate( $name, $slug );
		if ( ! $result['valid'] ) {
			$this->redirect_with_notice( join( ', ', $result['errors'] ), 'error' );
		}

		$statuses = $this->get_all_statuses();
		$statuses[ $result['sanitized']['slug'] ] = array(
			'name' => $result['sanitized']['name'],
			'slug' => $result['sanitized']['slug'],
		);
		$this->save_statuses( $statuses );

		$this->redirect_with_notice( __( 'Order status added successfully.', 'dukkan-plugin' ), 'success' );
	}

	/**
	 * Handle updating an existing custom order status.
	 *
	 * @since 1.0.0
	 */
	public function handle_update() {
		$this->verify_capability_and_nonce( 'dukkan_update_order_status' );

		$old_slug = isset( $_POST['dukkan_os_old_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['dukkan_os_old_slug'] ) ) : '';
		$name     = isset( $_POST['dukkan_os_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dukkan_os_name'] ) ) : '';
		$slug     = isset( $_POST['dukkan_os_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['dukkan_os_slug'] ) ) : '';

		$statuses = $this->get_all_statuses();
		if ( ! isset( $statuses[ $old_slug ] ) ) {
			$this->redirect_with_notice( __( 'Order status not found.', 'dukkan-plugin' ), 'error' );
		}

		$result = $this->validate( $name, $slug, $old_slug );
		if ( ! $result['valid'] ) {
			$this->redirect_with_notice( join( ', ', $result['errors'] ), 'error' );
		}

		unset( $statuses[ $old_slug ] );
		$statuses[ $result['sanitized']['slug'] ] = array(
			'name' => $result['sanitized']['name'],
			'slug' => $result['sanitized']['slug'],
		);
		$this->save_statuses( $statuses );

		$this->redirect_with_notice( __( 'Order status updated successfully.', 'dukkan-plugin' ), 'success' );
	}

	/**
	 * Handle deleting a custom order status.
	 *
	 * @since 1.0.0
	 */
	public function handle_delete() {
		$this->verify_capability_and_nonce( 'dukkan_delete_order_status' );

		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';

		$statuses = $this->get_all_statuses();
		if ( ! isset( $statuses[ $slug ] ) ) {
			$this->redirect_with_notice( __( 'Order status not found.', 'dukkan-plugin' ), 'error' );
		}

		unset( $statuses[ $slug ] );
		$this->save_statuses( $statuses );

		$this->redirect_with_notice( __( 'Order status deleted successfully.', 'dukkan-plugin' ), 'success' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify manage_options capability and admin referer nonce.
	 *
	 * @since 1.0.0
	 * @param string $action The nonce action.
	 */
	private function verify_capability_and_nonce( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'dukkan-plugin' ) );
		}
		check_admin_referer( $action, 'dukkan_os_nonce' );
	}

	/**
	 * Redirect back to the Order Status tab with a notice.
	 *
	 * @since 1.0.0
	 * @param string $message
	 * @param string $type    success|error
	 */
	private function redirect_with_notice( $message, $type = 'success' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'dukkan-settings',
					'tab'             => 'order_status',
					'dukkan_os_msg'  => rawurlencode( $message ),
					'dukkan_os_type' => $type,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
