<?php

/**
 * The loyalty-points REST API functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.25
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */

/**
 * Loyalty Points REST API.
 *
 * Exposes loyalty settings, customer balance, ledger, and manual adjustment
 * through WooCommerce-authenticated routes under the `wc/v3` namespace,
 * mirroring the Product Add-Ons / Product Badges API conventions.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Loyalty_API {

	/**
	 * Namespace for the API.
	 */
	const NAMESPACE = 'wc/v3';

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
	 * Initialize the class and register routes.
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

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all loyalty REST routes.
	 *
	 * @since 1.0.25
	 */
	public function register_routes() {

		register_rest_route(
			self::NAMESPACE,
			'/loyalty-points/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_edit_permissions' ),
					'args'                => $this->get_settings_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/loyalty-points/balance',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_balance' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_customer_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/loyalty-points/ledger',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ledger' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_customer_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/loyalty-points/adjust',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'adjust_points' ),
					'permission_callback' => array( $this, 'check_edit_permissions' ),
					'args'                => array(
						'customer_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'description'       => __( 'Customer user ID.', 'dukkan-plugin' ),
						),
						'points' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'intval',
							'description'       => __( 'Signed points delta (positive adds, negative deducts).', 'dukkan-plugin' ),
						),
						'note' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Reason for the adjustment.', 'dukkan-plugin' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback — requires WooCommerce API read access.
	 *
	 * @since 1.0.25
	 * @param  WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_permissions( WP_REST_Request $request ) {
		if ( ! wc_rest_check_manager_permissions( 'settings', 'read' ) ) {
			return new WP_Error(
				'woocommerce_rest_cannot_view',
				__( 'Sorry, you cannot view this resource.', 'dukkan-plugin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Permission callback for mutations — requires WooCommerce edit access.
	 *
	 * @since 1.0.25
	 * @param  WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_edit_permissions( WP_REST_Request $request ) {
		if ( ! wc_rest_check_manager_permissions( 'settings', 'edit' ) ) {
			return new WP_Error(
				'woocommerce_rest_cannot_edit',
				__( 'Sorry, you cannot edit this resource.', 'dukkan-plugin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Argument schema for the settings update endpoint.
	 *
	 * @since 1.0.25
	 * @return array
	 */
	private function get_settings_args() {
		return array(
			'enabled' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( '1 to enable, 0 to disable.', 'dukkan-plugin' ),
			),
			'points_per_amount' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'Points earned per amount.', 'dukkan-plugin' ),
			),
			'amount' => array(
				'required'          => false,
				'type'              => 'number',
				'description'       => __( 'Monetary amount that earns points_per_amount.', 'dukkan-plugin' ),
			),
			'value_per_point' => array(
				'required'          => false,
				'type'              => 'number',
				'description'       => __( 'Monetary value of a single point at redemption.', 'dukkan-plugin' ),
			),
			'max_redeem_percent' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'Max % of cart subtotal that points can cover (0-100).', 'dukkan-plugin' ),
			),
			'excluded_product_ids' => array(
				'required'          => false,
				'type'              => 'array',
				'description'       => __( 'Product IDs excluded from earning.', 'dukkan-plugin' ),
			),
			'excluded_category_ids' => array(
				'required'          => false,
				'type'              => 'array',
				'description'       => __( 'Product category IDs excluded from earning.', 'dukkan-plugin' ),
			),
			'redeem_with_coupons' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( '1 to allow points + coupon stacking.', 'dukkan-plugin' ),
			),
		);
	}

	/**
	 * Shared customer resolution args for balance/ledger.
	 *
	 * @since 1.0.25
	 * @return array
	 */
	private function get_customer_args() {
		return array(
			'customer_id' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( 'Customer user ID.', 'dukkan-plugin' ),
			),
			'email' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'description'       => __( 'Customer email (used when customer_id is omitted).', 'dukkan-plugin' ),
			),
		);
	}

	/**
	 * Resolve a customer from request params.
	 *
	 * @since  1.0.25
	 * @param  WP_REST_Request $request
	 * @return WP_User|WP_Error
	 */
	private function resolve_customer( WP_REST_Request $request ) {
		$customer_id = (int) $request->get_param( 'customer_id' );
		$email       = $request->get_param( 'email' );

		$user = null;

		if ( $customer_id ) {
			$user = get_user_by( 'id', $customer_id );
		} elseif ( $email ) {
			$user = get_user_by( 'email', $email );
		}

		if ( ! $user ) {
			return new WP_Error( 'customer_not_found', __( 'Customer not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		return $user;
	}

	// -------------------------------------------------------------------------
	// Endpoints
	// -------------------------------------------------------------------------

	/**
	 * GET /loyalty-points/settings — read config.
	 *
	 * @since 1.0.25
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ) {
		return rest_ensure_response( $this->loyalty->get_settings() );
	}

	/**
	 * PUT/PATCH /loyalty-points/settings — update config (merge semantics).
	 *
	 * @since 1.0.25
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$existing = $this->loyalty->get_settings();
		$clean    = $this->sanitize_settings_input( $params );

		$merged = array_merge( $existing, $clean );

		$this->loyalty->save_settings( $merged );

		return rest_ensure_response( $merged );
	}

	/**
	 * Sanitize settings input, keeping only provided keys.
	 *
	 * @since  1.0.25
	 * @param  array $params
	 * @return array
	 */
	private function sanitize_settings_input( $params ) {
		$clean = array();

		if ( isset( $params['enabled'] ) ) {
			$clean['enabled'] = intval( $params['enabled'] ) ? 1 : 0;
		}
		if ( isset( $params['points_per_amount'] ) ) {
			$points = absint( $params['points_per_amount'] );
			$clean['points_per_amount'] = $points > 0 ? $points : 1;
		}
		if ( isset( $params['amount'] ) ) {
			$amount = (float) wc_format_decimal( sanitize_text_field( (string) $params['amount'] ) );
			$clean['amount'] = $amount > 0 ? $amount : 1;
		}
		if ( isset( $params['value_per_point'] ) ) {
			$value = (float) wc_format_decimal( sanitize_text_field( (string) $params['value_per_point'] ) );
			$clean['value_per_point'] = $value > 0 ? $value : 0.01;
		}
		if ( isset( $params['max_redeem_percent'] ) ) {
			$percent = absint( $params['max_redeem_percent'] );
			$clean['max_redeem_percent'] = min( 100, max( 0, $percent ) );
		}
		if ( isset( $params['excluded_product_ids'] ) ) {
			$clean['excluded_product_ids'] = array_values( array_filter( array_map( 'intval', (array) $params['excluded_product_ids'] ) ) );
		}
		if ( isset( $params['excluded_category_ids'] ) ) {
			$clean['excluded_category_ids'] = array_values( array_filter( array_map( 'intval', (array) $params['excluded_category_ids'] ) ) );
		}
		if ( isset( $params['redeem_with_coupons'] ) ) {
			$clean['redeem_with_coupons'] = intval( $params['redeem_with_coupons'] ) ? 1 : 0;
		}

		return $clean;
	}

	/**
	 * GET /loyalty-points/balance — read a customer's balance.
	 *
	 * @since 1.0.25
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_balance( WP_REST_Request $request ) {
		$user = $this->resolve_customer( $request );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return rest_ensure_response(
			array(
				'customer_id' => $user->ID,
				'balance'     => $this->loyalty->get_balance( $user->ID ),
			)
		);
	}

	/**
	 * GET /loyalty-points/ledger — read a customer's transaction history.
	 *
	 * @since 1.0.25
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_ledger( WP_REST_Request $request ) {
		$user = $this->resolve_customer( $request );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		global $wpdb;
		$table = $this->loyalty->ledger_table();

		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 20 ) ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_id, points, type, note, created_at FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
				$user->ID,
				$per_page
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'customer_id' => $user->ID,
				'balance'     => $this->loyalty->get_balance( $user->ID ),
				'entries'     => is_array( $rows ) ? $rows : array(),
			)
		);
	}

	/**
	 * POST /loyalty-points/adjust — manually add or deduct points.
	 *
	 * @since 1.0.25
	 * @return WP_REST_Response|WP_Error
	 */
	public function adjust_points( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$customer_id = isset( $params['customer_id'] ) ? absint( $params['customer_id'] ) : 0;
		$points      = isset( $params['points'] ) ? (int) $params['points'] : 0;
		$note        = isset( $params['note'] ) ? sanitize_text_field( wp_unslash( $params['note'] ) ) : __( 'Manual adjustment', 'dukkan-plugin' );

		$user = get_user_by( 'id', $customer_id );
		if ( ! $user ) {
			return new WP_Error( 'customer_not_found', __( 'Customer not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		if ( 0 === $points ) {
			return new WP_Error( 'invalid_points', __( 'Points must be a non-zero signed integer.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		if ( $points > 0 ) {
			$this->loyalty->add_points( $user->ID, $points, 0, 'adjust', $note );
		} else {
			$this->loyalty->deduct_points( $user->ID, abs( $points ), 0, 'adjust', $note );
		}

		return rest_ensure_response(
			array(
				'customer_id' => $user->ID,
				'balance'     => $this->loyalty->get_balance( $user->ID ),
				'adjusted'    => $points,
			)
		);
	}
}
