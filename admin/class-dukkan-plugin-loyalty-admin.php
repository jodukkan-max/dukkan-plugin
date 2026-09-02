<?php

/**
 * The loyalty-points admin functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.25
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * Loyalty points admin: settings tab, settings save, balance lookup, and the
 * product/category search endpoints for the exclusion pickers.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Loyalty_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since 1.0.25
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 1.0.25
	 * @var string
	 */
	private $version;

	/**
	 * Shared loyalty engine.
	 *
	 * @since 1.0.25
	 * @var Dukkan_Plugin_Loyalty
	 */
	private $loyalty;

	/**
	 * Initialize the class and register hooks.
	 *
	 * @since 1.0.25
	 * @param string                $plugin_name The name of this plugin.
	 * @param string                $version     The version of this plugin.
	 * @param Dukkan_Plugin_Loyalty $loyalty     The loyalty engine.
	 */
	public function __construct( $plugin_name, $version, $loyalty ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->loyalty     = $loyalty;

		add_filter( 'dukkan_settings_tabs', array( $this, 'add_loyalty_settings_tab' ) );
		add_action( 'dukkan_settings_tab_content_loyalty', array( $this, 'render_tab_content' ) );

		add_action( 'admin_post_dukkan_loyalty_save_settings', array( $this, 'handle_save_settings' ) );

		add_action( 'wp_ajax_dukkan_loyalty_balance_lookup', array( $this, 'ajax_balance_lookup' ) );
		add_action( 'wp_ajax_dukkan_loyalty_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_dukkan_loyalty_search_categories', array( $this, 'ajax_search_categories' ) );
	}

	/**
	 * Register the admin styles.
	 *
	 * @since 1.0.25
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( 'toplevel_page_dukkan-settings' !== $hook_suffix ) {
			return;
		}

		$css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/dp-loyalty.css' );
		wp_enqueue_style( $this->plugin_name . '-loyalty', plugin_dir_url( __FILE__ ) . 'css/dp-loyalty.css', array(), $css_version, 'all' );
	}

	/**
	 * Register the admin scripts.
	 *
	 * @since 1.0.25
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'toplevel_page_dukkan-settings' !== $hook_suffix ) {
			return;
		}

		$js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/dp-loyalty.js' );
		wp_enqueue_script( $this->plugin_name . '-loyalty', plugin_dir_url( __FILE__ ) . 'js/dp-loyalty.js', array( 'jquery', $this->plugin_name ), $js_version, false );

		wp_localize_script(
			$this->plugin_name . '-loyalty',
			'dukkan_loyalty_admin',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wpldp_nonce' ),
			)
		);
	}

	/**
	 * Add the Loyalty Points tab to the Dukkan settings page.
	 *
	 * @since  1.0.25
	 * @param  array $tabs Existing tabs.
	 * @return array
	 */
	public function add_loyalty_settings_tab( $tabs ) {
		$tabs['loyalty'] = array(
			'title' => __( 'Loyalty Points', 'dukkan-plugin' ),
			'icon'  => 'dashicons-awards',
		);
		return $tabs;
	}

	/**
	 * Render the Loyalty Points tab content.
	 *
	 * @since 1.0.25
	 */
	public function render_tab_content() {
		$settings = $this->loyalty->get_settings();
		require plugin_dir_path( __FILE__ ) . 'partials/dukkan-loyalty-settings.php';
	}

	// -------------------------------------------------------------------------
	// Settings save
	// -------------------------------------------------------------------------

	/**
	 * Sanitize a full settings payload.
	 *
	 * @since  1.0.25
	 * @param  array $input Raw posted settings.
	 * @return array
	 */
	private function sanitize_settings( $input ) {
		$defaults = array(
			'enabled'               => 0,
			'points_per_amount'     => 1,
			'amount'                => 1,
			'value_per_point'       => 0.01,
			'max_redeem_percent'    => 100,
			'excluded_product_ids'  => array(),
			'excluded_category_ids' => array(),
			'redeem_with_coupons'   => 0,
		);

		$clean = array();

		$clean['enabled'] = empty( $input['enabled'] ) ? 0 : 1;

		$points_per_amount = isset( $input['points_per_amount'] ) ? absint( $input['points_per_amount'] ) : 1;
		$clean['points_per_amount'] = $points_per_amount > 0 ? $points_per_amount : 1;

		$amount = isset( $input['amount'] ) ? (float) wc_format_decimal( sanitize_text_field( $input['amount'] ) ) : 1;
		$clean['amount'] = $amount > 0 ? $amount : 1;

		$value_per_point = isset( $input['value_per_point'] ) ? (float) wc_format_decimal( sanitize_text_field( $input['value_per_point'] ) ) : 0.01;
		$clean['value_per_point'] = $value_per_point > 0 ? $value_per_point : 0.01;

		$max_percent = isset( $input['max_redeem_percent'] ) ? absint( $input['max_redeem_percent'] ) : 100;
		$clean['max_redeem_percent'] = min( 100, max( 0, $max_percent ) );

		$clean['excluded_product_ids']  = isset( $input['excluded_product_ids'] ) ? array_values( array_filter( array_map( 'intval', (array) $input['excluded_product_ids'] ) ) ) : array();
		$clean['excluded_category_ids'] = isset( $input['excluded_category_ids'] ) ? array_values( array_filter( array_map( 'intval', (array) $input['excluded_category_ids'] ) ) ) : array();

		$clean['redeem_with_coupons'] = empty( $input['redeem_with_coupons'] ) ? 0 : 1;

		return $clean;
	}

	/**
	 * Handle the settings form submission.
	 *
	 * @since 1.0.25
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'dukkan-plugin' ) );
		}

		check_admin_referer( 'dukkan_loyalty_settings', 'dukkan_loyalty_settings_nonce' );

		$input = isset( $_POST['dukkan_loyalty'] ) ? wp_unslash( $_POST['dukkan_loyalty'] ) : array();

		$this->loyalty->save_settings( $this->sanitize_settings( $input ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'dukkan-settings', 'tab' => 'loyalty', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Verify the AJAX nonce and capability.
	 *
	 * @since 1.0.25
	 */
	private function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dukkan-plugin' ) ), 403 );
		}
		check_ajax_referer( 'wpldp_nonce', 'nonce' );
	}

	/**
	 * AJAX: look up a customer's balance by user id or email.
	 *
	 * @since 1.0.25
	 */
	public function ajax_balance_lookup() {
		$this->verify_ajax();

		$user = null;

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
		} else {
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			if ( $email ) {
				$user = get_user_by( 'email', $email );
			}
		}

		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Customer not found.', 'dukkan-plugin' ) ), 404 );
		}

		global $wpdb;
		$table = $this->loyalty->ledger_table();

		$ledger = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_id, points, type, note, created_at FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 10",
				$user->ID
			),
			ARRAY_A
		);

		wp_send_json_success(
			array(
				'user_id'      => $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'balance'      => $this->loyalty->get_balance( $user->ID ),
				'ledger'       => is_array( $ledger ) ? $ledger : array(),
			)
		);
	}

	/**
	 * AJAX: search products for the exclusion picker.
	 *
	 * @since 1.0.25
	 */
	public function ajax_search_products() {
		$this->verify_ajax();

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$products = wc_get_products(
			array(
				'limit'  => 20,
				'status' => 'publish',
				's'      => $search,
			)
		);

		$results = array();
		foreach ( $products as $product ) {
			$results[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: search categories for the exclusion picker.
	 *
	 * @since 1.0.25
	 */
	public function ajax_search_categories() {
		$this->verify_ajax();

		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'search'     => $search,
				'number'     => 20,
			)
		);

		$results = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
				);
			}
		}

		wp_send_json_success( $results );
	}
}
