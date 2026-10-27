<?php

/**
 * The media-upload REST API functionality of the plugin.
 *
 * Lets the mobile app upload product/variation images directly into the
 * store's WordPress media library, authenticated by the WooCommerce API key
 * (instead of routing through a third-party image host first).
 *
 * @link       https://dukkanjo.com
 * @since      1.0.41
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */
class Dukkan_Plugin_Media_API {

	/**
	 * Namespace for the API.
	 */
	const NAMESPACE = 'dukkan-media/v1';

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
		register_rest_route( self::NAMESPACE, '/upload', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'upload_media' ),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
		) );

		register_rest_route( self::NAMESPACE, '/sideload', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'sideload_media' ),
			'permission_callback' => array( $this, 'check_edit_permissions' ),
			'args'                => array(
				'url' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
				),
			),
		) );
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
	 * POST /upload — accept a multipart file and store it in the media library.
	 *
	 * Expects the file under the `file` field (standard multipart upload).
	 * Returns `{ id, source_url }` on success.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_media( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];

		// Validate it's an actual uploaded file and a supported image type.
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) ) {
			return new WP_Error( 'invalid_type', __( 'Only image files are allowed.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array( 'test_form' => false );
		$movefile  = wp_handle_upload( $file, $overrides );

		if ( $movefile && ! isset( $movefile['error'] ) ) {
			$filename = $movefile['file'];
			$filetype = wp_check_filetype( basename( $filename ), null );

			$attachment = array(
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attach_id = wp_insert_attachment( $attachment, $filename );

			if ( ! is_wp_error( $attach_id ) ) {
				$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
				wp_update_attachment_metadata( $attach_id, $attach_data );

				return rest_ensure_response( array(
					'id'         => (int) $attach_id,
					'source_url' => (string) wp_get_attachment_url( $attach_id ),
				) );
			}

			return new WP_Error( 'attach_failed', __( 'Failed to create attachment.', 'dukkan-plugin' ), array( 'status' => 500 ) );
		}

		return new WP_Error(
			'upload_failed',
			isset( $movefile['error'] ) ? $movefile['error'] : __( 'Upload failed.', 'dukkan-plugin' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * POST /sideload — download a remote image URL into the media library.
	 *
	 * Used by the website builder's "From Pinterest" flow: the app extracts the
	 * pin's direct image URL and hands it here, and WordPress downloads it into
	 * the store's own media library (so it survives on the live site).
	 *
	 * Expects JSON `{ url }`. Returns `{ id, source_url }` on success.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function sideload_media( WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );
		if ( empty( $url ) ) {
			return new WP_Error( 'no_url', __( 'No image URL provided.', 'dukkan-plugin' ), array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download with a browser-like user agent — some image CDNs (e.g. Pinimg)
		// reject requests from a blank/script UA.
		add_filter( 'http_headers_useragent', function () {
			return 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
		} );

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'image_download_failed', $tmp->get_error_message(), array( 'status' => 400 ) );
		}

		$file_array = array(
			'name'     => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ?: 'pinterest.jpg' ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $attachment_id ) && file_exists( $tmp ) ) {
			@unlink( $tmp );
		}
		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'image_sideload_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
		}

		return rest_ensure_response( array(
			'id'         => (int) $attachment_id,
			'source_url' => (string) wp_get_attachment_url( $attachment_id ),
		) );
	}
}
