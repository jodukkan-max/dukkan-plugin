<?php

/**
 * The AI text REST API functionality of the plugin.
 *
 * Proxies the mobile app's AI text requests (product titles, short and long
 * descriptions) to DeepSeek so that the API key never ships inside the app.
 * Anyone can unzip an APK and read bundled secrets, so keys must stay on the
 * server.
 *
 * The key is read from, in order:
 *   1. the DUKKAN_DEEPSEEK_API_KEY constant (define it in wp-config.php)
 *   2. the dukkan_deepseek_api_key option
 *
 * @link       https://dukkanjo.com
 * @since      1.0.65
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/api
 */
class Dukkan_Plugin_AI_API {

	/**
	 * Namespace for the API.
	 */
	const NAMESPACE = 'dukkan-ai/v1';

	/**
	 * DeepSeek chat-completions endpoint.
	 */
	const ENDPOINT = 'https://api.deepseek.com/chat/completions';

	/**
	 * Model used for every text suggestion.
	 */
	const MODEL = 'deepseek-flash';

	/**
	 * Hard ceiling for an incoming base64 image payload (~6 MB of base64).
	 */
	const MAX_IMAGE_BYTES = 6291456;

	/**
	 * How many requests one user may make per hour.
	 */
	const RATE_LIMIT = 60;

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
		// POST /suggest — generate text suggestions from a product image.
		register_rest_route( self::NAMESPACE, '/suggest', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_suggestions' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'prompt'      => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				),
				'image'       => array(
					'required' => true,
					'type'     => 'string',
				),
				'temperature' => array(
					'required' => false,
					'type'     => 'number',
					'default'  => 0.4,
				),
				'max_tokens'  => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 2048,
				),
			),
		) );

		// GET /status — lets the app show a clear message when the key is missing.
		register_rest_route( self::NAMESPACE, '/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_status' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );
	}

	/**
	 * Permission callback — store managers only (WooCommerce credentials).
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		// WooCommerce's manager map has no 'products' key; 'settings' maps to
		// manage_woocommerce, which is the right bar for store managers.
		if ( ! wc_rest_check_manager_permissions( 'settings', 'edit' ) ) {
			return new WP_Error(
				'dukkan_ai_forbidden',
				__( 'Sorry, you cannot use the AI features.', 'dukkan-plugin' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Resolves the DeepSeek API key from the constant or the option.
	 *
	 * @return string Empty string when unconfigured.
	 */
	private function api_key() {
		if ( defined( 'DUKKAN_DEEPSEEK_API_KEY' ) ) {
			return trim( (string) DUKKAN_DEEPSEEK_API_KEY );
		}
		return trim( (string) get_option( 'dukkan_deepseek_api_key', '' ) );
	}

	/**
	 * GET /status — reports whether the proxy is configured.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status() {
		return rest_ensure_response( array(
			'configured' => '' !== $this->api_key(),
			'model'      => self::MODEL,
		) );
	}

	/**
	 * POST /suggest — forwards a prompt + image to DeepSeek and returns the text.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_suggestions( WP_REST_Request $request ) {
		$api_key = $this->api_key();
		if ( '' === $api_key ) {
			return new WP_Error(
				'dukkan_ai_not_configured',
				__( 'AI is not configured on this store. Add DUKKAN_DEEPSEEK_API_KEY to wp-config.php.', 'dukkan-plugin' ),
				array( 'status' => 501 )
			);
		}

		$limited = $this->rate_limit_exceeded();
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$image = (string) $request['image'];
		if ( strlen( $image ) > self::MAX_IMAGE_BYTES ) {
			return new WP_Error(
				'dukkan_ai_image_too_large',
				__( 'The image is too large. Please upload a smaller photo.', 'dukkan-plugin' ),
				array( 'status' => 413 )
			);
		}

		// Accept either bare base64 or a full data URI.
		$image = preg_replace( '#^data:image/[a-z0-9.+-]+;base64,#i', '', $image );
		if ( ! preg_match( '#^[A-Za-z0-9+/=\r\n]+$#', $image ) ) {
			return new WP_Error(
				'dukkan_ai_bad_image',
				__( 'The image payload is not valid base64.', 'dukkan-plugin' ),
				array( 'status' => 400 )
			);
		}

		$max_tokens  = (int) $request['max_tokens'];
		$max_tokens  = max( 256, min( 8000, $max_tokens ) );
		$temperature = (float) $request['temperature'];
		$temperature = max( 0.0, min( 1.5, $temperature ) );

		$body = array(
			'model'       => self::MODEL,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => (string) $request['prompt'],
						),
						array(
							'type'      => 'image_url',
							'image_url' => array( 'url' => 'data:image/jpeg;base64,' . $image ),
						),
					),
				),
			),
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
			// Thinking mode is ON by default and spends 800-1200 output tokens
			// on reasoning_content before writing the answer — measured at 80-87%
			// of all output on this task. Turning it off cuts cost ~4.6x and
			// latency ~3x, and it also makes `temperature` take effect (thinking
			// mode silently ignores it).
			'thinking'    => array( 'type' => 'disabled' ),
		);

		$response = wp_remote_post( self::ENDPOINT, array(
			'timeout' => 90,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'dukkan_ai_unreachable',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			// Surface quota/auth problems distinctly so the app can explain them.
			$error_code = ( 401 === $code || 403 === $code ) ? 'dukkan_ai_unauthorized' : 'dukkan_ai_upstream_error';
			return new WP_Error(
				$error_code,
				sprintf(
					/* translators: %d: HTTP status code from DeepSeek. */
					__( 'The AI service returned HTTP %d.', 'dukkan-plugin' ),
					$code
				),
				array(
					'status' => 502,
					'upstream_status' => $code,
				)
			);
		}

		$decoded = json_decode( $raw, true );
		$text    = isset( $decoded['choices'][0]['message']['content'] )
			? (string) $decoded['choices'][0]['message']['content']
			: '';

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'dukkan_ai_empty',
				__( 'The AI returned an empty response. Try again.', 'dukkan-plugin' ),
				array( 'status' => 502 )
			);
		}

		return rest_ensure_response( array( 'text' => $text ) );
	}

	/**
	 * Per-user hourly rate limit, so a leaked credential cannot burn the quota.
	 *
	 * @return true|WP_Error
	 */
	private function rate_limit_exceeded() {
		$user_id = get_current_user_id();
		$key     = 'dukkan_ai_rl_' . ( $user_id ? 'u' . $user_id : 'ip' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) );

		$hits = (int) get_transient( $key );
		if ( $hits >= self::RATE_LIMIT ) {
			return new WP_Error(
				'dukkan_ai_rate_limited',
				__( 'Too many AI requests. Please wait a few minutes and try again.', 'dukkan-plugin' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		return true;
	}
}
