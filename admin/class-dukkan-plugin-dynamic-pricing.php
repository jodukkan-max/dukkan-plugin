<?php

/**
 * Dynamic Product Pricing & Discounts management.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.1
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * Manages dynamic pricing rules — add, edit, update, delete, duplicate.
 *
 * Stores pricing rules in a WordPress option as an associative array
 * indexed by rule ID and exposes AJAX handlers for the Dukkan settings UI.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Atul Goyal <hello@wplogist.com>
 */
class Dukkan_Plugin_Dynamic_Pricing {

	/**
	 * Option key that holds all dynamic pricing rules.
	 *
	 * @since 1.0.1
	 * @var   string
	 */
	const OPTION_KEY = 'dukkan_dynamic_pricing_rules';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.1
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_filter( 'dukkan_settings_tabs', array( $this, 'add_dynamic_pricing_tab' ) );
		add_action( 'dukkan_settings_tab_content_dynamic_pricing', array( $this, 'render_tab_content' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_dukkan_dp_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_dukkan_dp_add', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_dukkan_dp_update', array( $this, 'ajax_update' ) );
		add_action( 'wp_ajax_dukkan_dp_delete', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_dukkan_dp_duplicate', array( $this, 'ajax_duplicate' ) );
		add_action( 'wp_ajax_dukkan_dp_toggle', array( $this, 'ajax_toggle' ) );
		add_action( 'wp_ajax_dukkan_dp_get', array( $this, 'ajax_get' ) );
	}

	// -------------------------------------------------------------------------
	// Tab Registration
	// -------------------------------------------------------------------------

	/**
	 * Add the Dynamic Pricing tab to Dukkan settings.
	 *
	 * @since  1.0.1
	 * @param  array $tabs Existing tabs.
	 * @return array
	 */
	public function add_dynamic_pricing_tab( $tabs ) {
		$tabs['dynamic_pricing'] = array(
			'title' => __( 'Dynamic Pricing', 'dukkan-plugin' ),
			'icon'  => 'fa-solid fa-tags',
		);
		return $tabs;
	}

	// -------------------------------------------------------------------------
	// Tab Content
	// -------------------------------------------------------------------------

	/**
	 * Render the Dynamic Pricing tab content.
	 *
	 * @since 1.0.1
	 */
	public function render_tab_content() {
		$rules = $this->get_all_rules();
		require plugin_dir_path( __FILE__ ) . 'partials/dukkan-dynamic-pricing-settings.php';
	}

	// -------------------------------------------------------------------------
	// Data Access
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all pricing rules.
	 *
	 * @since  1.0.1
	 * @return array
	 */
	public function get_all_rules() {
		$rules = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $rules ) ) {
			return array();
		}
		return $rules;
	}

	/**
	 * Persist the rules array.
	 *
	 * @since 1.0.1
	 * @param array $rules
	 */
	private function save_rules( $rules ) {
		update_option( self::OPTION_KEY, $rules, 'no' );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate pricing rule input.
	 *
	 * @since  1.0.1
	 * @param  array $data Raw POST data.
	 * @return array{valid: bool, errors: string[], sanitized: array}
	 */
	private function validate( $data ) {
		$errors = array();

		$sanitized = array(
			'name'            => sanitize_text_field( $data['name'] ?? '' ),
			'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
			'discount_type'   => sanitize_text_field( $data['discount_type'] ?? 'percentage' ),
			'discount_value'  => floatval( $data['discount_value'] ?? 0 ),
			'applies_to'      => sanitize_text_field( $data['applies_to'] ?? 'all' ),
			'categories'      => array_map( 'intval', $data['categories'] ?? array() ),
			'products'        => array_map( 'intval', $data['products'] ?? array() ),
			'min_quantity'    => intval( $data['min_quantity'] ?? 0 ),
			'min_amount'      => floatval( $data['min_amount'] ?? 0 ),
			'start_date'      => sanitize_text_field( $data['start_date'] ?? '' ),
			'end_date'        => sanitize_text_field( $data['end_date'] ?? '' ),
			'status'          => isset( $data['status'] ) ? 1 : 0,
		);

		if ( '' === $sanitized['name'] ) {
			$errors[] = __( 'Rule name is required.', 'dukkan-plugin' );
		}

		if ( $sanitized['discount_value'] <= 0 ) {
			$errors[] = __( 'Discount value must be greater than zero.', 'dukkan-plugin' );
		}

		if ( 'percentage' === $sanitized['discount_type'] && $sanitized['discount_value'] > 100 ) {
			$errors[] = __( 'Percentage discount cannot exceed 100%.', 'dukkan-plugin' );
		}

		if ( ! in_array( $sanitized['discount_type'], array( 'percentage', 'fixed', 'buy_x_get_y' ), true ) ) {
			$errors[] = __( 'Invalid discount type.', 'dukkan-plugin' );
		}

		if ( ! in_array( $sanitized['applies_to'], array( 'all', 'categories', 'products' ), true ) ) {
			$errors[] = __( 'Invalid apply-to selection.', 'dukkan-plugin' );
		}

		if ( 'categories' === $sanitized['applies_to'] && empty( $sanitized['categories'] ) ) {
			$errors[] = __( 'Please select at least one category.', 'dukkan-plugin' );
		}

		if ( 'products' === $sanitized['applies_to'] && empty( $sanitized['products'] ) ) {
			$errors[] = __( 'Please select at least one product.', 'dukkan-plugin' );
		}

		if ( ! empty( $sanitized['start_date'] ) && ! empty( $sanitized['end_date'] ) ) {
			if ( strtotime( $sanitized['end_date'] ) < strtotime( $sanitized['start_date'] ) ) {
				$errors[] = __( 'End date cannot be before start date.', 'dukkan-plugin' );
			}
		}

		return array(
			'valid'     => empty( $errors ),
			'errors'    => $errors,
			'sanitized' => $sanitized,
		);
	}

	// -------------------------------------------------------------------------
	// AJAX Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify AJAX nonce and capability.
	 *
	 * @since 1.0.1
	 */
	private function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dukkan-plugin' ) ), 403 );
		}
		check_ajax_referer( 'wpldp_nonce', 'nonce' );
	}

	/**
	 * Generate a unique rule ID.
	 *
	 * @since  1.0.1
	 * @param  string $name Rule name.
	 * @return string
	 */
	private function generate_rule_id( $name ) {
		return sanitize_title( $name ) . '-' . time();
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: list all pricing rules.
	 *
	 * @since 1.0.1
	 */
	public function ajax_list() {
		$this->verify_ajax();
		wp_send_json_success( $this->get_all_rules() );
	}

	/**
	 * AJAX: add a new pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_add() {
		$this->verify_ajax();

		$raw    = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();
		$result = $this->validate( $raw );

		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => join( ', ', $result['errors'] ) ), 400 );
		}

		$sanitized = $result['sanitized'];
		$rule_id   = $this->generate_rule_id( $sanitized['name'] );
		$sanitized['id'] = $rule_id;

		$rules          = $this->get_all_rules();
		$rules[ $rule_id ] = $sanitized;
		$this->save_rules( $rules );

		wp_send_json_success( $sanitized );
	}

	/**
	 * AJAX: update an existing pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_update() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$raw     = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		if ( ! isset( $rules[ $rule_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		$result = $this->validate( $raw );
		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => join( ', ', $result['errors'] ) ), 400 );
		}

		$sanitized           = $result['sanitized'];
		$sanitized['id']     = $rule_id;
		$rules[ $rule_id ]   = $sanitized;
		$this->save_rules( $rules );

		wp_send_json_success( $sanitized );
	}

	/**
	 * AJAX: delete a pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_delete() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		if ( ! isset( $rules[ $rule_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		unset( $rules[ $rule_id ] );
		$this->save_rules( $rules );

		wp_send_json_success( array( 'deleted' => true, 'rule_id' => $rule_id ) );
	}

	/**
	 * AJAX: duplicate a pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_duplicate() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		if ( ! isset( $rules[ $rule_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		$new_rule          = $rules[ $rule_id ];
		$new_rule['name'] .= ' (' . __( 'Copy', 'dukkan-plugin' ) . ')';
		$new_rule_id       = $this->generate_rule_id( $new_rule['name'] );
		$new_rule['id']    = $new_rule_id;

		$rules[ $new_rule_id ] = $new_rule;
		$this->save_rules( $rules );

		wp_send_json_success( $new_rule );
	}

	/**
	 * AJAX: toggle a rule's enabled/disabled status.
	 *
	 * @since 1.0.1
	 */
	public function ajax_toggle() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 0;

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		if ( ! isset( $rules[ $rule_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		$rules[ $rule_id ]['status'] = $status;
		$this->save_rules( $rules );

		wp_send_json_success( array( 'rule_id' => $rule_id, 'status' => $status ) );
	}

	/**
	 * AJAX: get a single pricing rule.
	 *
	 * @since 1.0.1
	 */
	public function ajax_get() {
		$this->verify_ajax();

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';

		if ( empty( $rule_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Rule ID is required.', 'dukkan-plugin' ) ), 400 );
		}

		$rules = $this->get_all_rules();
		if ( ! isset( $rules[ $rule_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Pricing rule not found.', 'dukkan-plugin' ) ), 404 );
		}

		// Resolve product IDs to names for display.
		if ( ! empty( $rules[ $rule_id ]['products'] ) ) {
			foreach ( $rules[ $rule_id ]['products'] as $i => $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$rules[ $rule_id ]['products'][ $i ] = array(
						'id'   => $product->get_id(),
						'name' => $product->get_name(),
					);
				} else {
					unset( $rules[ $rule_id ]['products'][ $i ] );
				}
			}
			$rules[ $rule_id ]['products'] = array_values( $rules[ $rule_id ]['products'] );
		}

		wp_send_json_success( $rules[ $rule_id ] );
	}
}
