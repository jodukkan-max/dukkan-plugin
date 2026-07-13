<?php

/**
 * GTM4WP API bridge — set the Google Tag Manager container ID from the mobile app.
 *
 * Provides one REST endpoint under dukkan-gtm/v1 that writes the GTM
 * container ID to GTM4WP's options.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.8
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */
class Dukkan_Plugin_GTM4WP_API {

	/**
	 * The ID of this plugin.
	 *
	 * @since 1.0.8
	 * @var   string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 1.0.8
	 * @var   string
	 */
	private $version;

	/**
	 * GTM4WP option key.
	 *
	 * @since 1.0.8
	 * @var   string
	 */
	const OPTION_KEY = 'gtm4wp-options';

	/**
	 * Sub-key for the GTM container ID.
	 *
	 * @since 1.0.8
	 * @var   string
	 */
	const GTM_CODE_KEY = 'gtm-code';

	/**
	 * Regex to validate GTM container IDs (e.g. GTM-ABC123).
	 *
	 * @since 1.0.8
	 * @var   string
	 */
	const GTM_ID_PATTERN = '/^GTM-[A-Z0-9]+$/';

	/**
	 * Initialize the class and register REST routes.
	 *
	 * @since 1.0.8
	 * @param string $plugin_name  The plugin identifier.
	 * @param string $version      Current plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the GTM container ID endpoint.
	 *
	 * @since 1.0.8
	 */
	public function register_routes() {
		register_rest_route( 'dukkan-gtm/v1', '/container-id', array(
			'methods'             => 'PUT',
			'callback'            => array( $this, 'update_container_id' ),
			'permission_callback' => '__return_true',
		));
	}

	/**
	 * PUT /container-id  —  Set the GTM container ID.
	 *
	 * @since  1.0.8
	 * @param  WP_REST_Request $request  Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_container_id( $request ) {
		$body = $request->get_json_params() ?: $request->get_body_params();

		if ( ! isset( $body['gtm_id'] ) ) {
			return new WP_Error( 'missing_gtm_id', 'Request body must contain "gtm_id".', array( 'status' => 400 ) );
		}

		$gtm_id = strtoupper( trim( sanitize_text_field( $body['gtm_id'] ) ) );

		if ( ! preg_match( self::GTM_ID_PATTERN, $gtm_id ) ) {
			return new WP_Error( 'invalid_gtm_id', 'gtm_id must match the format GTM-XXXXXX (e.g. GTM-ABC123).', array( 'status' => 400 ) );
		}

		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options[ self::GTM_CODE_KEY ] = $gtm_id;
		update_option( self::OPTION_KEY, $options );

		return new WP_REST_Response( array(
			'gtm_id' => $gtm_id,
		), 200 );
	}
}
