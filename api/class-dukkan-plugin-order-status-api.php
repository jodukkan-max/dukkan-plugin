<?php

/**
 * REST API endpoints for custom WooCommerce order statuses.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */

/**
 * Exposes CRUD endpoints for Dukkan custom order statuses.
 *
 * All routes require the `manage_options` capability.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Order_Status_API {

	/**
	 * Maximum slug length (mirrors admin class).
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const SLUG_MAX_LENGTH = 20;

	/**
	 * Option key for stored statuses.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const OPTION_KEY = 'dukkan_custom_order_statuses';

	 /**
     * Namespace for the API.
     */
    const NAMESPACE = 'wc/v3';

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
	 * Initialize the class and register REST routes.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name
	 * @param string $version
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes for order statuses.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {

		// GET    /statuses              — list all
		register_rest_route( self::NAMESPACE, '/statuses', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_statuses' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// POST   /statuses              — create
		register_rest_route( self::NAMESPACE, '/statuses', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_status' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => $this->get_create_args(),
		) );

		// GET    /statuses/{slug}       — single
		register_rest_route( self::NAMESPACE, '/statuses/(?P<slug>[a-zA-Z0-9_-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_status' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// PUT    /statuses/{slug}       — update
		register_rest_route( self::NAMESPACE, '/statuses/(?P<slug>[a-zA-Z0-9_-]+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_status' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => $this->get_update_args(),
		) );

		// DELETE /statuses/{slug}       — delete
		register_rest_route( self::NAMESPACE, '/statuses/(?P<slug>[a-zA-Z0-9_-]+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_status' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}

	// -------------------------------------------------------------------------
	// Permission
	// -------------------------------------------------------------------------

	/**
     * Permission callback — requires WooCommerce API read access.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permission( WP_REST_Request $request ) {
        if ( ! wc_rest_check_manager_permissions( 'settings', 'read' ) ) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                __( 'Sorry, you cannot view this resource.', 'your-plugin' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }

	// -------------------------------------------------------------------------
	// Endpoints
	// -------------------------------------------------------------------------

	/**
	 * GET /statuses — list all custom order statuses.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_statuses( $request ) {
		$statuses = $this->get_all_statuses();
		return rest_ensure_response( array_values( $statuses ) );
	}

	/**
	 * POST /statuses — create a new custom order status.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_status( $request ) {
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_title( $request->get_param( 'slug' ) );

		$validation = $this->validate( $name, $slug );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$statuses = $this->get_all_statuses();
		$statuses[ $slug ] = array( 'name' => $name, 'slug' => $slug );
		$this->save_statuses( $statuses );

		return rest_ensure_response( $statuses[ $slug ] );
	}

	/**
	 * GET /statuses/{slug} — get a single custom order status.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status( $request ) {
		$slug     = $request->get_param( 'slug' );
		$statuses = $this->get_all_statuses();

		if ( ! isset( $statuses[ $slug ] ) ) {
			return new WP_Error(
				'not_found',
				__( 'Order status not found.', 'dukkan-plugin' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $statuses[ $slug ] );
	}

	/**
	 * PUT /statuses/{slug} — update an existing custom order status.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_status( $request ) {
		$old_slug = $request->get_param( 'slug' );
		$name     = sanitize_text_field( $request->get_param( 'name' ) );
		$new_slug = sanitize_title( $request->get_param( 'new_slug' ) );

		$statuses = $this->get_all_statuses();
		if ( ! isset( $statuses[ $old_slug ] ) ) {
			return new WP_Error(
				'not_found',
				__( 'Order status not found.', 'dukkan-plugin' ),
				array( 'status' => 404 )
			);
		}

		$validation = $this->validate( $name, $new_slug, $old_slug );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		unset( $statuses[ $old_slug ] );
		$statuses[ $new_slug ] = array( 'name' => $name, 'slug' => $new_slug );
		$this->save_statuses( $statuses );

		return rest_ensure_response( $statuses[ $new_slug ] );
	}

	/**
	 * DELETE /statuses/{slug} — delete a custom order status.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_status( $request ) {
		$slug     = $request->get_param( 'slug' );
		$statuses = $this->get_all_statuses();

		if ( ! isset( $statuses[ $slug ] ) ) {
			return new WP_Error(
				'not_found',
				__( 'Order status not found.', 'dukkan-plugin' ),
				array( 'status' => 404 )
			);
		}

		unset( $statuses[ $slug ] );
		$this->save_statuses( $statuses );

		return rest_ensure_response( array( 'deleted' => true, 'slug' => $slug ) );
	}

	// -------------------------------------------------------------------------
	// Argument Schemas
	// -------------------------------------------------------------------------

	/**
	 * Argument definitions for the create endpoint.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private function get_create_args() {
		return array(
			'name' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Display name for the order status.', 'dukkan-plugin' ),
			),
			'slug' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'description'       => __( 'Machine-readable slug (max 20 characters).', 'dukkan-plugin' ),
			),
		);
	}

	/**
	 * Argument definitions for the update endpoint.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private function get_update_args() {
		return array(
			'name' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Updated display name.', 'dukkan-plugin' ),
			),
			'new_slug' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'description'       => __( 'Updated slug (max 20 characters).', 'dukkan-plugin' ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Data Access
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all custom order statuses.
	 *
	 * @since  1.0.0
	 * @return array<string, array{name: string, slug: string}>
	 */
	private function get_all_statuses() {
		$statuses = get_option( self::OPTION_KEY, array() );
		return is_array( $statuses ) ? $statuses : array();
	}

	/**
	 * Persist statuses array.
	 *
	 * @since 1.0.0
	 * @param array $statuses
	 */
	private function save_statuses( $statuses ) {
		update_option( self::OPTION_KEY, $statuses, 'no' );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate name and slug.
	 *
	 * @since  1.0.0
	 * @param  string $name
	 * @param  string $slug
	 * @param  string $old_slug
	 * @return true|WP_Error
	 */
	private function validate( $name, $slug, $old_slug = '' ) {
		if ( '' === $name ) {
			return new WP_Error(
				'validation_error',
				__( 'Status name is required.', 'dukkan-plugin' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $slug ) {
			return new WP_Error(
				'validation_error',
				__( 'Status slug is required.', 'dukkan-plugin' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $slug ) > self::SLUG_MAX_LENGTH ) {
			return new WP_Error(
				'validation_error',
				sprintf(
					/* translators: %d: max characters */
					__( 'Status slug must be %d characters or fewer.', 'dukkan-plugin' ),
					self::SLUG_MAX_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		$statuses = $this->get_all_statuses();
		if ( isset( $statuses[ $slug ] ) && $slug !== $old_slug ) {
			return new WP_Error(
				'validation_error',
				__( 'A status with this slug already exists.', 'dukkan-plugin' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}
}
