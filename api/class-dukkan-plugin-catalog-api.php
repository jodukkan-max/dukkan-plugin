<?php

/**
 * The bulk catalogue-terms REST API functionality of the plugin.
 *
 * The mobile app's Add Product screen needs every product category and every
 * product tag as soon as it opens. Going through WooCommerce's own
 * `/wc/v3/products/categories` costs ~340 ms for 100 items — WooCommerce builds
 * a permalink, image, `_links` block and hydrated meta for every single term,
 * so a 119-category store takes two requests and still needs pagination.
 *
 * These endpoints return the same data in one slim call (~15 ms for 119
 * categories) and are never truncated, so the app cannot silently lose
 * categories past the first 100.
 *
 * Routes (WooCommerce manager auth, same Basic auth as the other dukkan-* APIs):
 *   GET /dukkan-catalog/v1/categories   every product_cat, flat with parent ids
 *   GET /dukkan-catalog/v1/tags         every product_tag
 *
 * @link       https://dukkanjo.com
 * @since      1.0.67
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */
class Dukkan_Plugin_Catalog_API {

	/**
	 * Namespace for the API.
	 */
	const NAMESPACE = 'dukkan-catalog/v1';

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
		register_rest_route( self::NAMESPACE, '/categories', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_categories' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( self::NAMESPACE, '/tags', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_tags' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );
	}

	/**
	 * Permission callback — store managers only.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		// WooCommerce's manager map has no 'products' key; 'settings' maps to
		// manage_woocommerce, the right bar for store managers.
		if ( ! wc_rest_check_manager_permissions( 'settings', 'read' ) ) {
			return new WP_Error(
				'dukkan_catalog_forbidden',
				__( 'Sorry, you cannot view this resource.', 'dukkan-plugin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * GET /categories — every product category, flat.
	 *
	 * Returned flat (with `parent`) rather than as a tree so the app keeps full
	 * control over ordering and nesting, and so a single response can never be
	 * split across pages.
	 *
	 * @return WP_REST_Response
	 */
	public function get_categories() {
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( array( 'status' => 'error', 'message' => $terms->get_error_message() ), 500 );
		}

		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'     => (int) $t->term_id,
				'name'   => (string) $t->name,
				'slug'   => (string) $t->slug,
				'parent' => (int) $t->parent,
				'count'  => (int) $t->count,
			);
		}

		return new WP_REST_Response( array(
			'status' => 'success',
			'total'  => count( $out ),
			'items'  => $out,
		), 200 );
	}

	/**
	 * GET /tags — every product tag, flat.
	 *
	 * @return WP_REST_Response
	 */
	public function get_tags() {
		$terms = get_terms( array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( array( 'status' => 'error', 'message' => $terms->get_error_message() ), 500 );
		}

		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'    => (int) $t->term_id,
				'name'  => (string) $t->name,
				'slug'  => (string) $t->slug,
				'count' => (int) $t->count,
			);
		}

		return new WP_REST_Response( array(
			'status' => 'success',
			'total'  => count( $out ),
			'items'  => $out,
		), 200 );
	}
}
