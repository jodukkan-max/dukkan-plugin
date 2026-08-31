<?php

/**
 * The product-badge REST API functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.24
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */

/**
 * The product-badge REST API.
 *
 * Exposes the stored product badges (option `dukkan_product_badges`) through
 * RESTful, WooCommerce-authenticated routes under the `wc/v3` namespace,
 * mirroring the Product Add-Ons API conventions.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Badge_API {

	/**
	 * Namespace for the API.
	 */
	const NAMESPACE = 'wc/v3';

	/**
	 * Option key for stored product badges.
	 *
	 * @since 1.0.24
	 * @var   string
	 */
	const OPTION_KEY = 'dukkan_product_badges';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.24
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.24
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.24
	 * @param    string $plugin_name The name of the plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'rest_api_init', array( $this, 'register_badge_routes' ) );
	}

	/**
	 * Register all REST routes for product badges.
	 */
	public function register_badge_routes() {

		// GET /product-badges — list all badges
		// POST /product-badges — create a badge
		register_rest_route( self::NAMESPACE, '/product-badges', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_badges' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_badge' ),
				'permission_callback' => array( $this, 'check_edit_permissions' ),
				'args'                => $this->get_badge_args(),
			),
		) );

		// GET / PUT / PATCH / DELETE /product-badges/{id} — single badge CRUD
		register_rest_route( self::NAMESPACE, '/product-badges/(?P<id>[a-zA-Z0-9_-]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_badge' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE, // POST, PUT, PATCH
				'callback'            => array( $this, 'update_badge' ),
				'permission_callback' => array( $this, 'check_edit_permissions' ),
				'args'                => $this->get_badge_args(),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_badge' ),
				'permission_callback' => array( $this, 'check_edit_permissions' ),
			),
		) );

		// POST /product-badges/{id}/duplicate — duplicate a badge
		register_rest_route( self::NAMESPACE, '/product-badges/(?P<id>[a-zA-Z0-9_-]+)/duplicate', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_badge' ),
				'permission_callback' => array( $this, 'check_edit_permissions' ),
			),
		) );

		// POST /product-badges/{id}/toggle — enable/disable a badge
		register_rest_route( self::NAMESPACE, '/product-badges/(?P<id>[a-zA-Z0-9_-]+)/toggle', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_badge' ),
				'permission_callback' => array( $this, 'check_edit_permissions' ),
				'args'                => array(
					'status' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( '1 to enable, 0 to disable.', 'dukkan-plugin' ),
					),
				),
			),
		) );
	}

	/**
	 * Permission callback — requires WooCommerce API read access.
	 *
	 * @param WP_REST_Request $request
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
	 * @param WP_REST_Request $request
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
	 * Argument schema shared by create/update endpoints.
	 *
	 * @return array
	 */
	private function get_badge_args() {
		return array(
			'text' => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Badge text. May contain <br> for line breaks.', 'dukkan-plugin' ),
			),
			'shape' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( '"rectangular" or "circle".', 'dukkan-plugin' ),
			),
			'background_color' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color',
				'description'       => __( 'Hex background color, e.g. #e53935.', 'dukkan-plugin' ),
			),
			'text_color' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color',
				'description'       => __( 'Hex text color, e.g. #ffffff.', 'dukkan-plugin' ),
			),
			'position' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( '"top-left", "top-right", "bottom-left" or "bottom-right".', 'dukkan-plugin' ),
			),
			'applied_to' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( '"all", "specific_products", "specific_categories" or "specific_tags".', 'dukkan-plugin' ),
			),
			'products' => array(
				'required'          => false,
				'type'              => 'array',
				'description'       => __( 'Product IDs when applied_to is "specific_products".', 'dukkan-plugin' ),
			),
			'categories' => array(
				'required'          => false,
				'type'              => 'array',
				'description'       => __( 'Product category term IDs when applied_to is "specific_categories".', 'dukkan-plugin' ),
			),
			'tags' => array(
				'required'          => false,
				'type'              => 'array',
				'description'       => __( 'Product tag term IDs when applied_to is "specific_tags".', 'dukkan-plugin' ),
			),
			'status' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'description'       => __( '1 to enable, 0 to disable.', 'dukkan-plugin' ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Data access helpers
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all stored badges.
	 *
	 * @return array
	 */
	private function get_all_badges() {
		$badges = get_option( self::OPTION_KEY, array() );
		return is_array( $badges ) ? $badges : array();
	}

	/**
	 * Persist the badges array.
	 *
	 * @param array $badges
	 */
	private function save_badges( $badges ) {
		update_option( self::OPTION_KEY, $badges, 'no' );
	}

	/**
	 * Resolve product/category/tag IDs to {id, name} objects for the response.
	 *
	 * @param array $badge
	 * @return array
	 */
	private function hydrate_badge( $badge ) {
		if ( ! empty( $badge['products'] ) && is_array( $badge['products'] ) ) {
			$products = array();
			foreach ( $badge['products'] as $product_id ) {
				if ( is_array( $product_id ) ) {
					$products[] = $product_id;
					continue;
				}
				$product = wc_get_product( (int) $product_id );
				if ( $product ) {
					$products[] = array(
						'id'   => $product->get_id(),
						'name' => $product->get_name(),
					);
				}
			}
			$badge['products'] = $products;
		}

		if ( ! empty( $badge['categories'] ) && is_array( $badge['categories'] ) ) {
			$categories = array();
			foreach ( $badge['categories'] as $term_id ) {
				if ( is_array( $term_id ) ) {
					$categories[] = $term_id;
					continue;
				}
				$term = get_term( (int) $term_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$categories[] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
					);
				}
			}
			$badge['categories'] = $categories;
		}

		if ( ! empty( $badge['tags'] ) && is_array( $badge['tags'] ) ) {
			$tags = array();
			foreach ( $badge['tags'] as $term_id ) {
				if ( is_array( $term_id ) ) {
					$tags[] = $term_id;
					continue;
				}
				$term = get_term( (int) $term_id, 'product_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$tags[] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
					);
				}
			}
			$badge['tags'] = $tags;
		}

		return $badge;
	}

	/**
	 * Sanitize badge-level input, keeping only the keys that are present.
	 *
	 * @param array $params
	 * @return array
	 */
	private function sanitize_badge_input( $params ) {
		$clean = array();

		if ( isset( $params['text'] ) ) {
			$clean['text'] = trim( wp_kses( wp_unslash( $params['text'] ), array( 'br' => array() ) ) );
		}
		if ( isset( $params['shape'] ) ) {
			$shape = sanitize_text_field( $params['shape'] );
			$clean['shape'] = in_array( $shape, array( 'rectangular', 'circle' ), true ) ? $shape : 'rectangular';
		}
		if ( isset( $params['background_color'] ) ) {
			$color = sanitize_hex_color( $params['background_color'] );
			$clean['background_color'] = $color ? $color : '#e53935';
		}
		if ( isset( $params['text_color'] ) ) {
			$color = sanitize_hex_color( $params['text_color'] );
			$clean['text_color'] = $color ? $color : '#ffffff';
		}
		if ( isset( $params['position'] ) ) {
			$position = sanitize_text_field( $params['position'] );
			$clean['position'] = in_array( $position, array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ), true ) ? $position : 'top-left';
		}
		if ( isset( $params['applied_to'] ) ) {
			$applied_to = sanitize_text_field( $params['applied_to'] );
			$clean['applied_to'] = in_array( $applied_to, array( 'all', 'specific_products', 'specific_categories', 'specific_tags' ), true ) ? $applied_to : 'all';
		}
		if ( isset( $params['products'] ) ) {
			$clean['products'] = array_values( array_filter( array_map( 'intval', (array) $params['products'] ) ) );
		}
		if ( isset( $params['categories'] ) ) {
			$clean['categories'] = array_values( array_filter( array_map( 'intval', (array) $params['categories'] ) ) );
		}
		if ( isset( $params['tags'] ) ) {
			$clean['tags'] = array_values( array_filter( array_map( 'intval', (array) $params['tags'] ) ) );
		}
		if ( isset( $params['status'] ) ) {
			$clean['status'] = intval( $params['status'] ) ? 1 : 0;
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Endpoints
	// -------------------------------------------------------------------------

	/**
	 * GET /product-badges — list all badges.
	 */
	public function get_badges( WP_REST_Request $request ) {
		$badges = $this->get_all_badges();
		$result = array();

		foreach ( $badges as $badge_id => $badge ) {
			if ( empty( $badge['id'] ) ) {
				$badge['id'] = $badge_id;
			}
			$result[] = $this->hydrate_badge( $badge );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * GET /product-badges/{id} — single badge.
	 */
	public function get_badge( WP_REST_Request $request ) {
		$badge_id = sanitize_text_field( $request['id'] );

		if ( empty( $badge_id ) ) {
			return new WP_Error( 'no_badge', __( 'Badge ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$badges = $this->get_all_badges();

		if ( ! isset( $badges[ $badge_id ] ) ) {
			return new WP_Error( 'not_found', __( 'Badge not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		$badge = $badges[ $badge_id ];
		if ( empty( $badge['id'] ) ) {
			$badge['id'] = $badge_id;
		}

		return rest_ensure_response( $this->hydrate_badge( $badge ) );
	}

	/**
	 * DELETE /product-badges/{id} — delete a badge.
	 */
	public function delete_badge( WP_REST_Request $request ) {
		$badge_id = sanitize_text_field( $request['id'] );

		if ( empty( $badge_id ) ) {
			return new WP_Error( 'no_badge', __( 'Badge ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$badges = $this->get_all_badges();

		if ( ! isset( $badges[ $badge_id ] ) ) {
			return new WP_Error( 'not_found', __( 'Badge not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		unset( $badges[ $badge_id ] );
		$this->save_badges( $badges );

		return rest_ensure_response( array(
			'deleted' => true,
			'id'      => $badge_id,
		) );
	}

	/**
	 * POST /product-badges — create a badge.
	 */
	public function create_badge( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		// Fallback to form-data / urlencoded if JSON body is empty.
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$text = isset( $params['text'] ) ? trim( wp_kses( wp_unslash( $params['text'] ), array( 'br' => array() ) ) ) : '';

		if ( '' === $text ) {
			return new WP_Error( 'missing_text', __( 'Badge text is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$badge_id = sanitize_title( $text ) . '-' . time();
		$badges   = $this->get_all_badges();

		$input = $this->sanitize_badge_input( $params );

		$new_badge = array(
			'id'               => $badge_id,
			'text'             => $text,
			'shape'            => $input['shape'] ?? 'rectangular',
			'background_color' => $input['background_color'] ?? '#e53935',
			'text_color'       => $input['text_color'] ?? '#ffffff',
			'position'         => $input['position'] ?? 'top-left',
			'applied_to'       => $input['applied_to'] ?? 'all',
			'products'         => $input['products'] ?? array(),
			'categories'       => $input['categories'] ?? array(),
			'tags'             => $input['tags'] ?? array(),
			'status'           => $input['status'] ?? 1,
		);

		$badges[ $badge_id ] = $new_badge;
		$this->save_badges( $badges );

		$response = rest_ensure_response( $this->hydrate_badge( $new_badge ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * POST /product-badges/{id}/duplicate — duplicate a badge.
	 */
	public function duplicate_badge( WP_REST_Request $request ) {
		$badge_id = sanitize_text_field( $request['id'] );

		if ( empty( $badge_id ) ) {
			return new WP_Error( 'no_badge', __( 'Badge ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$badges = $this->get_all_badges();

		if ( ! isset( $badges[ $badge_id ] ) ) {
			return new WP_Error( 'not_found', __( 'Badge not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		$badge         = $badges[ $badge_id ];
		$new_badge_id  = sanitize_title( $badge['text'] ) . '-copy-' . time();
		$badge['id']   = $new_badge_id;
		$badge['text'] = ( $badge['text'] ?? '' ) . ' (Copy)';

		$badges[ $new_badge_id ] = $badge;
		$this->save_badges( $badges );

		$response = rest_ensure_response( $this->hydrate_badge( $badge ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * PUT/PATCH /product-badges/{id} — update a badge (merge semantics).
	 */
	public function update_badge( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		// Fallback to form-data / urlencoded if JSON body is empty.
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$badge_id = sanitize_text_field( $request['id'] );

		if ( empty( $badge_id ) ) {
			return new WP_Error( 'no_badge', __( 'Badge ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$badges = $this->get_all_badges();

		if ( ! isset( $badges[ $badge_id ] ) ) {
			return new WP_Error( 'not_found', __( 'Badge not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		$existing = $badges[ $badge_id ];
		$input    = $this->sanitize_badge_input( $params );

		if ( isset( $input['text'] ) && '' === $input['text'] ) {
			return new WP_Error( 'missing_text', __( 'Badge text is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$merged = array_merge( $existing, $input );
		$merged['id'] = $badge_id;

		$badges[ $badge_id ] = $merged;
		$this->save_badges( $badges );

		return rest_ensure_response( $this->hydrate_badge( $merged ) );
	}

	/**
	 * POST /product-badges/{id}/toggle — enable/disable a badge.
	 */
	public function toggle_badge( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$badge_id = sanitize_text_field( $request['id'] );

		if ( empty( $badge_id ) ) {
			return new WP_Error( 'no_badge', __( 'Badge ID is required.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$badges = $this->get_all_badges();

		if ( ! isset( $badges[ $badge_id ] ) ) {
			return new WP_Error( 'not_found', __( 'Badge not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		$status = isset( $params['status'] ) ? intval( $params['status'] ) : 0;
		$badges[ $badge_id ]['status'] = $status ? 1 : 0;

		$this->save_badges( $badges );

		return rest_ensure_response( array(
			'id'     => $badge_id,
			'status' => $badges[ $badge_id ]['status'],
		) );
	}
}
