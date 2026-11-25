<?php
/**
 * "Generate with AI" buttons on the product editor (Products > Add New / Edit).
 *
 * Under the product name, the description and the short description. Each
 * button reads the product image (featured image, else the first gallery
 * image) and asks DeepSeek for suggestions with the same prompts the Dukkan
 * app uses; the shop manager clicks one to put it in the field. The buttons
 * stay disabled until the product has an image.
 *
 * The request runs on the server (admin-ajax) through Dukkan_Plugin_AI_API,
 * so the DeepSeek key never reaches the browser.
 *
 * Kill switch: define( 'DUKKAN_PRODUCT_AI', false ) in wp-config.php.
 *
 * @since 1.0.74
 * @package Dukkan_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dukkan_Product_AI_Admin {

	const ACTION = 'dukkan_product_ai';

	/**
	 * Same prompts and settings as the app (products_controller.dart).
	 *
	 * @return array<string,array>
	 */
	private static function fields() {
		return array(
			'title'       => array(
				'prompt'      => 'Analyze this product image and suggest 5 highly accurate, SEO-friendly product titles. Return ONLY a JSON array of strings: ["Name 1", "Name 2", ...]. No other text.',
				'temperature' => 0.4,
				'max_tokens'  => 1200,
			),
			'description' => array(
				'prompt'      => 'Analyze this product image and write 3 different, professional, and persuasive e-commerce descriptions. One should be concise, one more detailed, and one highly creative. Return ONLY a JSON array of strings: ["Desc 1", "Desc 2", "Desc 3"]. No other text.',
				'temperature' => 0.4,
				'max_tokens'  => 2500,
			),
			'short'       => array(
				'prompt'      => 'Analyze this product image and write 5 different, concise, and catchy e-commerce short descriptions. Return ONLY a JSON array of strings: ["Short desc 1", "Short desc 2", "Short desc 3", "Short desc 4", "Short desc 5"]. No other text.',
				'temperature' => 0.5,
				'max_tokens'  => 1500,
			),
		);
	}

	public function __construct() {
		if ( defined( 'DUKKAN_PRODUCT_AI' ) && ! DUKKAN_PRODUCT_AI ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax' ) );
	}

	/**
	 * Loads the buttons on the classic product editor only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type || ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$base = plugin_dir_url( __FILE__ );
		$dir  = plugin_dir_path( __FILE__ );
		$ver  = defined( 'DUKKAN_PLUGIN_VERSION' ) ? DUKKAN_PLUGIN_VERSION : '1';
		// File time in the version, so a changed file is never served from cache.
		wp_enqueue_style( 'dp-product-ai', $base . 'css/dp-product-ai.css', array(), $ver . '.' . (int) @filemtime( $dir . 'css/dp-product-ai.css' ) );
		wp_enqueue_script( 'dp-product-ai', $base . 'js/dp-product-ai.js', array( 'jquery' ), $ver . '.' . (int) @filemtime( $dir . 'js/dp-product-ai.js' ), true );
		global $post;
		wp_localize_script( 'dp-product-ai', 'dpProductAI', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'action'      => self::ACTION,
			'photoAction' => class_exists( 'Dukkan_Product_Photo_AI' ) ? Dukkan_Product_Photo_AI::ACTION : '',
			'photoReady'  => class_exists( 'Dukkan_Product_Photo_AI' ) && Dukkan_Product_Photo_AI::configured() && current_user_can( 'upload_files' ),
			'ratios'      => class_exists( 'Dukkan_Product_Photo_AI' ) ? Dukkan_Product_Photo_AI::RATIOS : array(),
			'postId'      => $post ? (int) $post->ID : 0,
			'nonce'       => wp_create_nonce( self::ACTION ),
			'i18n'    => array(
				'button'     => __( 'Generate with AI', 'dukkan-plugin' ),
				'needImage'  => __( 'Add a product image first', 'dukkan-plugin' ),
				'working'    => __( 'Reading the product image…', 'dukkan-plugin' ),
				'pick'       => __( 'Click a suggestion to use it', 'dukkan-plugin' ),
				'use'        => __( 'Use', 'dukkan-plugin' ),
				'close'      => __( 'Close', 'dukkan-plugin' ),
				'failed'     => __( 'Could not generate suggestions. Please try again.', 'dukkan-plugin' ),
				'noResults'  => __( 'No suggestions came back. Please try again.', 'dukkan-plugin' ),
				'ratio'      => __( 'Image ratio', 'dukkan-plugin' ),
				'howMany'    => __( 'Number of images', 'dukkan-plugin' ),
				'onePhoto'   => __( '1 photo', 'dukkan-plugin' ),
				'twoPhotos'  => __( '2 photos', 'dukkan-plugin' ),
				'aiDecides'  => __( 'AI picks the scene for this product.', 'dukkan-plugin' ),
				'making'     => __( 'Creating photos… this takes about 30 seconds.', 'dukkan-plugin' ),
				'added'      => __( 'Added to the product gallery. Save the product to keep them.', 'dukkan-plugin' ),
				'someFailed' => __( 'Some photos failed. Please try again.', 'dukkan-plugin' ),
				'notSetUp'   => __( 'AI photos are not set up on this store yet.', 'dukkan-plugin' ),
				'deleteImg'  => __( 'Delete image', 'dukkan-plugin' ),
				'delete'     => __( 'Delete', 'dukkan-plugin' ),
			),
		) );
	}

	/**
	 * admin-ajax handler: field + attachment id → list of suggestions.
	 */
	public function ajax() {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you cannot use the AI features.', 'dukkan-plugin' ) ), 403 );
		}

		$field  = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$image  = isset( $_POST['image'] ) ? absint( $_POST['image'] ) : 0;
		$fields = self::fields();
		if ( ! isset( $fields[ $field ] ) ) {
			wp_send_json_error( array( 'message' => 'Unknown field.' ), 400 );
		}
		if ( ! $image || ! wp_attachment_is_image( $image ) || ! current_user_can( 'read_post', $image ) ) {
			wp_send_json_error( array( 'message' => __( 'Add a product image first', 'dukkan-plugin' ) ), 400 );
		}
		if ( ! class_exists( 'Dukkan_Plugin_AI_API' ) ) {
			wp_send_json_error( array( 'message' => 'AI is not available.' ), 500 );
		}

		$data = self::image_data( $image );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ), 400 );
		}

		$cfg  = $fields[ $field ];
		$text = Dukkan_Plugin_AI_API::complete( $cfg['prompt'], $data['base64'], $data['mime'], $cfg['temperature'], $cfg['max_tokens'] );
		if ( is_wp_error( $text ) ) {
			wp_send_json_error( array( 'message' => $text->get_error_message() ), 502 );
		}

		$list = self::parse_list( $text );
		if ( empty( $list ) ) {
			wp_send_json_error( array( 'message' => __( 'No suggestions came back. Please try again.', 'dukkan-plugin' ) ), 502 );
		}
		wp_send_json_success( array( 'suggestions' => $list ) );
	}

	/**
	 * Base64 of the attachment, at most $max px (the "large" size when there
	 * is one and $max <= 1024). Reads the local file; falls back to the URL when the file was
	 * offloaded.
	 *
	 * @param int $id  Attachment id.
	 * @param int $max Longest side in px.
	 * @return array{base64:string,mime:string}|WP_Error
	 */
	public static function image_data( $id, $max = 1024 ) {
		$max  = max( 256, (int) $max );
		$path = '';
		$tmp  = '';
		$size = $max <= 1024 ? image_get_intermediate_size( $id, 'large' ) : false;
		if ( $size && ! empty( $size['path'] ) ) {
			$uploads = wp_get_upload_dir();
			$path    = trailingslashit( $uploads['basedir'] ) . $size['path'];
		}
		if ( ! $path || ! file_exists( $path ) ) {
			$path = (string) get_attached_file( $id );
		}
		if ( ! $path || ! file_exists( $path ) ) {
			// Offloaded (e.g. to R2): download the "large" copy to a temp file.
			$src = wp_get_attachment_image_src( $id, $max <= 1024 ? 'large' : 'full' );
			if ( $src && ! empty( $src[0] ) ) {
				if ( ! function_exists( 'download_url' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				$dl = download_url( $src[0], 30 );
				if ( ! is_wp_error( $dl ) ) {
					$path = $tmp = $dl;
				}
			}
		}
		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error( 'dukkan_ai_no_image', __( 'Could not read the product image.', 'dukkan-plugin' ) );
		}

		$file = $path;
		$out  = '';
		$stub = '';
		$editor = wp_get_image_editor( $path );
		if ( ! is_wp_error( $editor ) ) {
			$dims = $editor->get_size();
			if ( $dims && ( $dims['width'] > $max || $dims['height'] > $max || filesize( $path ) > 1.5 * MB_IN_BYTES ) ) {
				$editor->resize( $max, $max, false );
				$editor->set_quality( 85 );
				// Keep the format (a PNG with transparency would turn black as JPEG).
				$stub  = wp_tempnam( 'dukkan-ai' ); // Empty placeholder file; the editor may save under another extension.
				$saved = $editor->save( $stub );
				if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && file_exists( $saved['path'] ) ) {
					$file = $out = $saved['path'];
				}
			}
		}

		$bytes = (string) file_get_contents( $file ); // phpcs:ignore
		foreach ( array( $tmp, $out, $stub ) as $f ) {
			if ( $f && file_exists( $f ) ) {
				wp_delete_file( $f );
			}
		}

		if ( '' === $bytes ) {
			return new WP_Error( 'dukkan_ai_no_image', __( 'Could not read the product image.', 'dukkan-plugin' ) );
		}
		if ( strlen( $bytes ) > 4 * MB_IN_BYTES ) {
			return new WP_Error( 'dukkan_ai_image_too_large', __( 'The image is too large. Please upload a smaller photo.', 'dukkan-plugin' ) );
		}

		$mime = (string) get_post_mime_type( $id );
		$info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bytes ) : false; // phpcs:ignore
		if ( $info && ! empty( $info['mime'] ) ) {
			$mime = $info['mime'];
		}
		if ( 0 !== strpos( $mime, 'image/' ) ) {
			$mime = 'image/jpeg';
		}
		return array( 'base64' => base64_encode( $bytes ), 'mime' => $mime ); // phpcs:ignore
	}

	/**
	 * JSON array of strings out of a model reply; tolerates markdown fences
	 * and a cut-off reply (same idea as the app's parser).
	 *
	 * @param string $text Model reply.
	 * @return string[]
	 */
	private static function parse_list( $text ) {
		$text = trim( preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', trim( (string) $text ) ) );
		$from = strpos( $text, '[' );
		if ( false !== $from ) {
			$to   = strrpos( $text, ']' );
			$json = false !== $to && $to > $from ? substr( $text, $from, $to - $from + 1 ) : substr( $text, $from );
			$list = json_decode( $json, true );
			if ( ! is_array( $list ) ) {
				// Cut off mid-array: keep the complete strings.
				preg_match_all( '/"((?:[^"\\\\]|\\\\.)*)"\s*(?=,|\])/s', $json, $m );
				$list = array_map( function ( $s ) {
					$d = json_decode( '"' . $s . '"' );
					return is_string( $d ) ? $d : $s;
				}, $m[1] );
			}
		} else {
			$list = array( $text );
		}

		$out = array();
		foreach ( $list as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$out[] = trim( $item );
			}
		}
		return array_slice( $out, 0, 6 );
	}
}
