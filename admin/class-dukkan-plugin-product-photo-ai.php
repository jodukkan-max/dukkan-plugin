<?php
/**
 * "Generate with AI" product photos on the product editor — the app's
 * "Let AI do it" flow:
 *   1. DeepSeek looks at the main product image and writes the photo prompt
 *      (instruction = Firebase ai_prompts/product_photo → prompt_writer, read
 *      through the portal; the app's built-in text if that is unavailable).
 *   2. MAI gets the product image + that prompt + the chosen ratio and makes
 *      the advertising photo.
 * Both go through the same Dukkan AI gateway as the app
 * (DUKKAN_AI_GATEWAY_URL / DUKKAN_AI_GATEWAY_TOKEN in wp-config.php). Each
 * call makes one photo; the browser starts one call per photo, like the app.
 * The photo is saved to the Media Library and added to the product gallery.
 *
 * Kill switch: define( 'DUKKAN_PRODUCT_AI', false ) also turns this off.
 *
 * @since 1.0.74
 * @package Dukkan_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dukkan_Product_Photo_AI {

	const ACTION = 'dukkan_product_ai_photo';

	/** Ratios offered in the app (kProductPhotoRatios). */
	const RATIOS = array( '1:1', '3:4', '2:3' );

	/** Where the stores read the app's editable prompt. */
	const PROMPT_URL = 'https://portal.dukkan.pro/api/ai/product-photo-prompt';

	/**
	 * The app's built-in prompt_writer (product_photo_service.dart), used when
	 * the portal cannot be reached or the Firebase field is empty.
	 */
	const PROMPT_WRITER = "You are a world-class commercial product photographer and art director with 20 years of experience shooting advertising campaigns for premium brands.\n"
		. "\n"
		. "Look at the product in this photo. Work out exactly what it is, what it is used for. Then write the prompt for an image model that will turn this photo into the one advertising photograph that would sell this exact product in an online shop.\n"
		. "\n"
		. "You decide the set and scene: a real, believable place that belongs to this product's world and is specific to this product type, never a generic decorative set that could hold any product. Decide the surface it rests on, at most two props that are used together with this product, the light that really exists in that place, and the camera angle and lens.\n"
		. "\n"
		. "Write the prompt in English as plain text, under 180 words, in this order:\n"
		. "1. Start with: \"Image 1 is the product: <what it is, with its exact colours and materials>. Keep it exactly as in image 1: same shape, proportions, colours, materials, surface finish, gloss, stitching, label text, logos and every detail. Do not redraw, restyle, recolour or crop it.\"\n"
		. "2. The scene: place, surface, background (softly out of focus) and props.\n"
		. "3. The light: you choose the source, direction, softness and colour temperature, so it looks photographed there.\n"
		. "4. Camera angle, lens and framing, with the product fully visible as the clear hero.\n"
		. "5. End with: \"This is the only product in the photo. A real photograph: no text, no logos, no graphics.\"\n"
		. "\n"
		. "Rules: in the prompt, name only things that should be in the photo, never things that should not.\n"
		. "Return only the prompt text, nothing else.";

	public function __construct() {
		if ( defined( 'DUKKAN_PRODUCT_AI' ) && ! DUKKAN_PRODUCT_AI ) {
			return;
		}
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax' ) );
	}

	/** Whether the gateway constants are set on this store. */
	public static function configured() {
		return defined( 'DUKKAN_AI_GATEWAY_URL' ) && defined( 'DUKKAN_AI_GATEWAY_TOKEN' )
			&& '' !== trim( (string) DUKKAN_AI_GATEWAY_URL ) && '' !== trim( (string) DUKKAN_AI_GATEWAY_TOKEN );
	}

	/**
	 * admin-ajax: makes one photo and adds it to the Media Library.
	 */
	public function ajax() {
		check_ajax_referer( Dukkan_Product_AI_Admin::ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_products' ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you cannot use the AI features.', 'dukkan-plugin' ) ), 403 );
		}
		if ( ! self::configured() ) {
			wp_send_json_error( array( 'message' => __( 'AI photos are not set up on this store yet.', 'dukkan-plugin' ) ), 501 );
		}

		$image   = isset( $_POST['image'] ) ? absint( $_POST['image'] ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$ratio   = isset( $_POST['ratio'] ) ? sanitize_text_field( wp_unslash( $_POST['ratio'] ) ) : '3:4';
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$cats    = isset( $_POST['categories'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['categories'] ) ) : array();
		if ( ! in_array( $ratio, self::RATIOS, true ) ) {
			$ratio = '3:4';
		}
		if ( ! $image || ! wp_attachment_is_image( $image ) ) {
			wp_send_json_error( array( 'message' => __( 'Add a product image first', 'dukkan-plugin' ) ), 400 );
		}
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}

		// Small copy for DeepSeek, bigger one for MAI (the app sends <= 2 MP).
		$small = Dukkan_Product_AI_Admin::image_data( $image, 1024 );
		$big   = Dukkan_Product_AI_Admin::image_data( $image, 1536 );
		if ( is_wp_error( $small ) || is_wp_error( $big ) ) {
			$err = is_wp_error( $small ) ? $small : $big;
			wp_send_json_error( array( 'message' => $err->get_error_message() ), 400 );
		}

		$prompt = self::write_prompt( $small, $name, $cats );
		if ( is_wp_error( $prompt ) ) {
			wp_send_json_error( array( 'message' => $prompt->get_error_message() ), 502 );
		}

		$bytes = self::generate( $big, $prompt, $ratio );
		if ( is_wp_error( $bytes ) ) {
			wp_send_json_error( array( 'message' => $bytes->get_error_message() ), 502 );
		}

		$id = self::save( $bytes, $post_id, $name );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ), 500 );
		}

		// Some stores turn off the core sizes; WooCommerce's always exist.
		$thumb   = wp_get_attachment_image_src( $id, 'woocommerce_gallery_thumbnail' );
		$preview = wp_get_attachment_image_src( $id, 'woocommerce_thumbnail' );
		wp_send_json_success( array(
			'id'      => $id,
			'thumb'   => $thumb ? $thumb[0] : wp_get_attachment_url( $id ),
			'preview' => $preview ? $preview[0] : wp_get_attachment_url( $id ),
			'full'  => wp_get_attachment_url( $id ),
		) );
	}

	/** The app's editable prompt (via the portal), cached 5 minutes. */
	private static function prompt_writer() {
		$cached = get_transient( 'dukkan_ai_photo_prompt' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$text = '';
		$resp = wp_remote_get( self::PROMPT_URL, array( 'timeout' => 8 ) );
		if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
			$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
			$text = isset( $data['prompt_writer'] ) ? trim( (string) $data['prompt_writer'] ) : '';
		}
		if ( '' === $text ) {
			$text = self::PROMPT_WRITER;
		}
		set_transient( 'dukkan_ai_photo_prompt', $text, 5 * MINUTE_IN_SECONDS );
		return $text;
	}

	private static function gateway( $route, $body, $timeout ) {
		return wp_remote_post( rtrim( (string) DUKKAN_AI_GATEWAY_URL, '/' ) . '/' . $route, array(
			'timeout' => $timeout,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . trim( (string) DUKKAN_AI_GATEWAY_TOKEN ),
			),
			'body'    => wp_json_encode( $body ),
		) );
	}

	/**
	 * Step 1: DeepSeek writes the MAI prompt (same text, hints and checks as
	 * ProductPhotoService.writeMaiPrompt).
	 *
	 * @return string|WP_Error
	 */
	private static function write_prompt( $img, $name, $cats ) {
		$hints = array();
		if ( '' !== trim( $name ) ) {
			$hints[] = 'Seller\'s product name: "' . trim( $name ) . '".';
		}
		$cats = array_values( array_filter( array_map( 'trim', $cats ) ) );
		if ( $cats ) {
			$hints[] = 'Seller\'s category: ' . implode( ', ', $cats ) . '.';
		}
		$text = self::prompt_writer();
		if ( $hints ) {
			$text .= "\n" . implode( ' ', $hints ) . ' Use these hints, but trust the photo if they conflict.';
		}

		$resp = self::gateway( 'deepseek', array(
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						array( 'type' => 'text', 'text' => $text ),
						array( 'type' => 'image_url', 'image_url' => array( 'url' => 'data:' . $img['mime'] . ';base64,' . $img['base64'] ) ),
					),
				),
			),
			'temperature' => 0.4,
			'max_tokens'  => 500,
		), 60 );

		$fail = new WP_Error( 'dukkan_ai_prompt', __( 'The AI could not describe this product. Please try again.', 'dukkan-plugin' ) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return $fail;
		}
		$data   = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$prompt = isset( $data['choices'][0]['message']['content'] ) ? trim( (string) $data['choices'][0]['message']['content'] ) : '';
		$prompt = trim( preg_replace( '/^```\w*\s*|\s*```$/', '', $prompt ) );
		if ( strlen( $prompt ) > 1 && '"' === $prompt[0] && '"' === substr( $prompt, -1 ) ) {
			$prompt = trim( substr( $prompt, 1, -1 ) );
		}
		return strlen( $prompt ) < 80 ? $fail : $prompt;
	}

	/**
	 * Step 2: MAI makes the photo.
	 *
	 * @return string|WP_Error Image bytes.
	 */
	private static function generate( $img, $prompt, $ratio ) {
		$resp = self::gateway( 'mai', array(
			'prompt'       => $prompt,
			'image'        => 'data:' . $img['mime'] . ';base64,' . $img['base64'],
			'aspect_ratio' => $ratio,
		), 180 );

		$fail = new WP_Error( 'dukkan_ai_photo', __( 'Image generation failed. Please try again.', 'dukkan-plugin' ) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return $fail;
		}
		$data  = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$image = isset( $data['image'] ) ? (string) $data['image'] : '';
		if ( 0 === strpos( $image, 'data:' ) ) {
			$comma = strpos( $image, ',' );
			$bytes = false !== $comma ? base64_decode( substr( $image, $comma + 1 ) ) : ''; // phpcs:ignore
		} elseif ( 0 === strpos( $image, 'https://' ) ) {
			$dl    = wp_remote_get( $image, array( 'timeout' => 60 ) );
			$bytes = is_wp_error( $dl ) ? '' : (string) wp_remote_retrieve_body( $dl );
		} else {
			$bytes = '';
		}
		return $bytes ? $bytes : $fail;
	}

	/**
	 * Saves the photo as a JPEG in the Media Library, attached to the product.
	 *
	 * @return int|WP_Error Attachment id.
	 */
	private static function save( $bytes, $post_id, $name ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$base = sanitize_title( $name ? $name : 'product' );
		$base = ( '' !== $base ? substr( $base, 0, 60 ) : 'product' ) . '-ai-' . gmdate( 'YmdHis' ) . '-' . wp_rand( 100, 999 );

		$tmp = wp_tempnam( $base );
		file_put_contents( $tmp, $bytes ); // phpcs:ignore
		$file = $tmp;
		$ext  = 'png';
		$info = @getimagesize( $tmp ); // phpcs:ignore
		if ( $info && 'image/jpeg' === $info['mime'] ) {
			$ext = 'jpg';
		} elseif ( $info ) {
			// Web shop photo: JPEG is far smaller than the PNG MAI returns.
			$editor = wp_get_image_editor( $tmp );
			if ( ! is_wp_error( $editor ) ) {
				$editor->set_quality( 90 );
				$saved = $editor->save( $tmp . '.jpg', 'image/jpeg' );
				if ( ! is_wp_error( $saved ) && file_exists( $saved['path'] ) ) {
					wp_delete_file( $tmp );
					$file = $saved['path'];
					$ext  = 'jpg';
				}
			}
		} else {
			wp_delete_file( $tmp );
			return new WP_Error( 'dukkan_ai_photo', __( 'Image generation failed. Please try again.', 'dukkan-plugin' ) );
		}

		$title = $name ? $name : __( 'Product photo', 'dukkan-plugin' );
		$id    = media_handle_sideload(
			array( 'name' => $base . '.' . $ext, 'tmp_name' => $file ),
			$post_id,
			$title
		);
		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
		update_post_meta( $id, '_dukkan_ai_photo', 1 );
		return (int) $id;
	}
}
