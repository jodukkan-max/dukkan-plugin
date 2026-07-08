<?php

/**
 * Slim SEO API bridge — write SEO titles and descriptions from the mobile app.
 *
 * Provides two REST endpoints under dukkan-seo/v1 that allow the app to set
 * Slim SEO meta title and meta description on any post (including products).
 *
 * @link       https://dukkanjo.com
 * @since      1.0.5
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */
class Dukkan_Plugin_Slim_SEO_API {

	/**
	 * The ID of this plugin.
	 *
	 * @since 1.0.5
	 * @var   string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 1.0.5
	 * @var   string
	 */
	private $version;

	/**
	 * Slim SEO meta key used on posts.
	 *
	 * @since 1.0.5
	 * @var   string
	 */
	const META_KEY = 'slim_seo';

	/**
	 * Initialize the class and register REST routes.
	 *
	 * @since 1.0.5
	 * @param string $plugin_name  The plugin identifier.
	 * @param string $version      Current plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the two write-only REST endpoints.
	 *
	 * @since 1.0.5
	 */
	public function register_routes() {

		register_rest_route( 'dukkan-seo/v1', '/posts/(?P<id>\d+)/title', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'update_title' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && get_post( (int) $param );
					},
				),
			),
		));

		register_rest_route( 'dukkan-seo/v1', '/posts/(?P<id>\d+)/description', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'update_description' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && get_post( (int) $param );
					},
				),
			),
		));
	}

	/**
	 * PUT /posts/{id}/title  —  Update the Slim SEO meta title.
	 *
	 * @since  1.0.5
	 * @param  WP_REST_Request $request  Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_title( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$body    = $request->get_json_params() ?: $request->get_body_params();

		if ( ! isset( $body['title'] ) ) {
			return new WP_Error( 'missing_title', 'Request body must contain "title".', array( 'status' => 400 ) );
		}

		$title = sanitize_text_field( $body['title'] );
		$this->set_meta_field( $post_id, 'title', $title );

		return new WP_REST_Response( array(
			'id'    => $post_id,
			'title' => $title,
		), 200 );
	}

	/**
	 * PUT /posts/{id}/description  —  Update the Slim SEO meta description.
	 *
	 * @since  1.0.5
	 * @param  WP_REST_Request $request  Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_description( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$body    = $request->get_json_params() ?: $request->get_body_params();

		if ( ! isset( $body['description'] ) ) {
			return new WP_Error( 'missing_description', 'Request body must contain "description".', array( 'status' => 400 ) );
		}

		$description = sanitize_textarea_field( $body['description'] );
		$this->set_meta_field( $post_id, 'description', $description );

		return new WP_REST_Response( array(
			'id'          => $post_id,
			'description' => $description,
		), 200 );
	}

	/**
	 * Read Slim SEO's existing post meta, merge one field in, write it back.
	 *
	 * Slim SEO stores all SEO data in a single serialized array under the
	 * 'slim_seo' post meta key. We read the full array, change only the
	 * targeted field, and save it back — preserving images, canonical,
	 * noindex, and any other fields untouched.
	 *
	 * @since 1.0.5
	 * @param int    $post_id  Post ID.
	 * @param string $field    Field name ('title' or 'description').
	 * @param string $value    New value for the field.
	 */
	private function set_meta_field( $post_id, $field, $value ) {
		$meta = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$meta[ $field ] = $value;
		update_post_meta( $post_id, self::META_KEY, $meta );
	}
}
