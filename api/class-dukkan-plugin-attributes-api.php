<?php

/**
 * The Rey attribute swatch REST API functionality of the plugin.
 *
 * Exposes the Rey theme's variation-swatch settings (attribute "type" and the
 * per-term swatch meta — color / image) through WooCommerce-authenticated
 * routes, so the mobile app can configure them without the WP admin.
 *
 * Rey swatch types (stored in `wp_woocommerce_attribute_taxonomies.attribute_type`):
 *   - rey_color        (Color)
 *   - rey_image        (Image)
 *   - rey_button       (Buttons - inline)
 *   - rey_large_button (Button - Large)
 *   - rey_radio        (Radio)
 * plus WooCommerce core: select, text.
 *
 * Per-term swatch meta (stored in `wp_termmeta`, read by Rey via ACF get_field):
 *   - rey_attribute_color           (hex string, e.g. #dfc5a0)
 *   - rey_attribute_color_secondary (hex string)
 *   - rey_attribute_image           (attachment ID)
 *
 * @link       https://dukkanjo.com
 * @since      1.0.39
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */
class Dukkan_Plugin_Attributes_API {

	/**
	 * Namespace for the API.
	 */
	const NAMESPACE = 'dukkan-attributes/v1';

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 */
	public function register_routes() {
		// GET /types — list available attribute types (select + Rey swatches).
		register_rest_route( self::NAMESPACE, '/types', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_types' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// PUT /attributes/{id}/type — set the attribute type.
		register_rest_route( self::NAMESPACE, '/attributes/(?P<id>\d+)/type', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_attribute_type' ),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
			'args'                => array(
				'type' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		// GET /terms/{id}/swatch — read a term's swatch settings.
		register_rest_route( self::NAMESPACE, '/terms/(?P<id>\d+)/swatch', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_term_swatch' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_term_swatch' ),
				'permission_callback' => array( $this, 'check_edit_permissions' ),
				'args'                => array(
					'color'            => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_hex_color',
					),
					'color_secondary'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_hex_color',
					),
					'image_url'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
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
	 * GET /types — return available attribute types (slug => label).
	 *
	 * Uses WooCommerce's `wc_get_attribute_types()`, which includes the core
	 * `select`/`text` types plus any theme-registered swatch types (Rey injects
	 * them via the `product_attributes_type_selector` filter).
	 *
	 * @return WP_REST_Response
	 */
	public function get_types() {
		$types = array();
		if ( function_exists( 'wc_get_attribute_types' ) ) {
			foreach ( wc_get_attribute_types() as $slug => $label ) {
				$types[] = array(
					'slug'  => (string) $slug,
					'label' => (string) $label,
				);
			}
		}
		return rest_ensure_response( $types );
	}

	/**
	 * PUT /attributes/{id}/type — update an attribute's type.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_attribute_type( WP_REST_Request $request ) {
		$attribute_id = (int) $request['id'];
		$type         = (string) $request['type'];

		if ( ! function_exists( 'wc_get_attribute_types' ) ) {
			return new WP_Error( 'woocommerce_missing', __( 'WooCommerce is not active.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$valid_types = array_keys( wc_get_attribute_types() );
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new WP_Error(
				'invalid_type',
				sprintf( __( 'Invalid attribute type. Allowed: %s.', 'dukkan-plugin' ), implode( ', ', $valid_types ) ),
				array( 'status' => 400 )
			);
		}

		// Verify the attribute exists.
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
			$attribute_id
		) );

		if ( ! $exists ) {
			return new WP_Error( 'attribute_not_found', __( 'Attribute not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		$result = wc_update_attribute( $attribute_id, array( 'type' => $type ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'id'   => $attribute_id,
			'type' => $type,
		) );
	}

	/**
	 * GET /terms/{id}/swatch — read a term's swatch settings.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_term_swatch( WP_REST_Request $request ) {
		$term_id = (int) $request['id'];

		if ( ! term_exists( $term_id ) ) {
			return new WP_Error( 'term_not_found', __( 'Term not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		$image_id = (int) get_term_meta( $term_id, 'rey_attribute_image', true );

		return rest_ensure_response( array(
			'term_id'         => $term_id,
			'color'           => (string) get_term_meta( $term_id, 'rey_attribute_color', true ),
			'color_secondary' => (string) get_term_meta( $term_id, 'rey_attribute_color_secondary', true ),
			'image_id'        => $image_id,
			'image_url'       => $image_id ? (string) wp_get_attachment_url( $image_id ) : '',
		) );
	}

	/**
	 * PUT /terms/{id}/swatch — update a term's swatch settings.
	 *
	 * Accepts a JSON body with any combination of:
	 *   - color:           hex color (rey_attribute_color)
	 *   - color_secondary: hex color (rey_attribute_color_secondary)
	 *   - image_url:       sideloads the URL into the media library and stores
	 *                      the resulting attachment ID as rey_attribute_image.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_term_swatch( WP_REST_Request $request ) {
		$term_id = (int) $request['id'];

		if ( ! term_exists( $term_id ) ) {
			return new WP_Error( 'term_not_found', __( 'Term not found.', 'dukkan-plugin' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid or missing JSON body.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$response = array( 'term_id' => $term_id );

		// Color (primary).
		if ( array_key_exists( 'color', $params ) ) {
			$color = sanitize_hex_color( (string) $params['color'] );
			if ( $color ) {
				update_term_meta( $term_id, 'rey_attribute_color', $color );
				$response['color'] = $color;
			} else {
				delete_term_meta( $term_id, 'rey_attribute_color' );
				$response['color'] = '';
			}
		}

		// Color (secondary).
		if ( array_key_exists( 'color_secondary', $params ) ) {
			$secondary = sanitize_hex_color( (string) $params['color_secondary'] );
			if ( $secondary ) {
				update_term_meta( $term_id, 'rey_attribute_color_secondary', $secondary );
				$response['color_secondary'] = $secondary;
			} else {
				delete_term_meta( $term_id, 'rey_attribute_color_secondary' );
				$response['color_secondary'] = '';
			}
		}

		// Image — sideload from URL into the media library.
		if ( array_key_exists( 'image_url', $params ) ) {
			$image_url = esc_url_raw( (string) $params['image_url'] );
			if ( $image_url ) {
				$attachment_id = $this->sideload_image( $image_url );
				if ( is_wp_error( $attachment_id ) ) {
					return $attachment_id;
				}
				update_term_meta( $term_id, 'rey_attribute_image', $attachment_id );
				$response['image_id']  = $attachment_id;
				$response['image_url'] = (string) wp_get_attachment_url( $attachment_id );
			} else {
				delete_term_meta( $term_id, 'rey_attribute_image' );
				$response['image_id']  = 0;
				$response['image_url'] = '';
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Sideload a remote image URL into the WordPress media library and return
	 * the attachment ID.
	 *
	 * @param string $image_url Remote image URL.
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	private function sideload_image( $image_url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'image_download_failed', $tmp->get_error_message(), array( 'status' => 400 ) );
		}

		$file_array = array(
			'name'     => basename( parse_url( $image_url, PHP_URL_PATH ) ?: 'swatch.jpg' ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );

		// media_handle_sideload moves the file; clean up if it didn't.
		if ( is_wp_error( $attachment_id ) && file_exists( $tmp ) ) {
			@unlink( $tmp );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'image_sideload_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
		}

		return (int) $attachment_id;
	}
}
