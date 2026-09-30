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
}
