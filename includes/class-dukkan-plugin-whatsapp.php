<?php

/**
 * WhatsApp Business (Meta Cloud API) integration for the AI chatbot.
 *
 * Reuses the existing Dukkan_Plugin_Chatbot engine as the "brain": the WhatsApp
 * webhook is just a second frontend that feeds inbound messages through
 * `process_message()` and posts the reply back via the Cloud API.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.32
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 */

/**
 * WhatsApp integration: REST webhook, Cloud API sender, phone→customer
 * resolution, and per-phone conversation memory.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_WhatsApp {

	/**
	 * REST namespace for the webhook.
	 */
	const NAMESPACE = 'dukkan-whatsapp/v1';

	/**
	 * Graph API version used for sending messages.
	 *
	 * Bump this when Meta retires the version.
	 */
	const GRAPH_VERSION = 'v21.0';

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The current version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Shared chatbot engine.
	 *
	 * @var Dukkan_Plugin_Chatbot
	 */
	private $chatbot;

	/**
	 * Defaults for the WhatsApp-specific settings (stored inside the chatbot
	 * settings option, prefixed with `whatsapp_`).
	 *
	 * @var array
	 */
	private $defaults = array(
		'whatsapp_enabled'         => 0,
		'whatsapp_phone_number_id' => '',
		'whatsapp_access_token'    => '',
		'whatsapp_verify_token'    => '',
		'whatsapp_app_secret'      => '',
		'whatsapp_session_ttl'     => 60,
		'whatsapp_handoff_number'  => '',
	);

	/**
	 * Initialize the class and register the webhook.
	 *
	 * @param string                 $plugin_name The name of this plugin.
	 * @param string                 $version     The version of this plugin.
	 * @param Dukkan_Plugin_Chatbot $chatbot     The chatbot engine.
	 */
	public function __construct( $plugin_name, $version, $chatbot ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->chatbot     = $chatbot;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_verification_raw' ), 10, 4 );
	}

	/**
	 * Register the WhatsApp webhook route.
	 *
	 * Both methods are open (permission `__return_true`) because Meta authenticates
	 * via the X-Hub-Signature-256 header and the verify token — not WP auth.
	 *
	 * @since 1.0.32
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/webhook',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_verification' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Serve the verification GET as raw plain text.
	 *
	 * Meta requires the `hub.challenge` echoed back verbatim — unquoted, un-JSON-encoded,
	 * as `text/plain`. WordPress's REST layer otherwise JSON-encodes the string into
	 * `"challenge"` (with quotes), which fails Meta's strict comparison.
	 *
	 * @since 1.0.32
	 * @param bool            $served  Whether the request is already served.
	 * @param WP_HTTP_Response $result Result to send.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  Server instance.
	 * @return bool True to short-circuit default serving for this route.
	 */
	public function serve_verification_raw( $served, $result, $request, $server ) {
		if ( 'GET' !== $request->get_method() ) {
			return $served;
		}
		if ( false === strpos( $request->get_route(), self::NAMESPACE . '/webhook' ) ) {
			return $served;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );

		if ( is_wp_error( $result ) ) {
			status_header( 403 );
			echo 'Verification failed';
			return true;
		}

		$data = $result instanceof WP_REST_Response ? $result->get_data() : $result;
		status_header( 200 );
		echo (string) $data;
		return true;
	}

	/**
	 * The public webhook URL to paste into the Meta WhatsApp configuration.
	 *
	 * @since 1.0.32
	 * @return string
	 */
	public function webhook_url() {
		return rest_url( self::NAMESPACE . '/webhook' );
	}

	/**
	 * Whether WhatsApp is enabled and fully configured.
	 *
	 * @since 1.0.32
	 * @return bool
	 */
	public function is_enabled() {
		$s = $this->get_settings();
		return ! empty( $s['whatsapp_enabled'] )
			&& ! empty( $s['whatsapp_phone_number_id'] )
			&& ! empty( $s['whatsapp_access_token'] );
	}

	/**
	 * Merge stored settings with defaults.
	 *
	 * @since 1.0.32
	 * @return array
	 */
	public function get_settings() {
		$stored = get_option( Dukkan_Plugin_Chatbot::SETTINGS_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, $this->defaults );
	}

	/**
	 * Get (or auto-generate) the webhook verify token.
	 *
	 * The user never has to invent a token: we create a strong random one and
	 * persist it, so Meta's verification handshake stays stable across saves.
	 *
	 * @since 1.0.32
	 * @return string
	 */
	public function get_verify_token() {
		$settings = $this->get_settings();
		$token    = trim( (string) $settings['whatsapp_verify_token'] );

		if ( '' === $token ) {
			$token = 'dukkan_' . wp_generate_password( 24, false, false );
			$all   = get_option( Dukkan_Plugin_Chatbot::SETTINGS_KEY, array() );
			if ( ! is_array( $all ) ) {
				$all = array();
			}
			$all['whatsapp_verify_token'] = $token;
			update_option( Dukkan_Plugin_Chatbot::SETTINGS_KEY, $all, 'no' );
		}

		return $token;
	}

	/**
	 * GET /webhook — Meta verification handshake.
	 *
	 * Meta calls this once when you subscribe the webhook, sending
	 * `hub.mode`, `hub.verify_token` and `hub.challenge`. Echo the challenge
	 * back only when the verify token matches.
	 *
	 * @since 1.0.32
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_verification( $request ) {
		$mode         = $request->get_param( 'hub.mode' );
		$verify_token = $request->get_param( 'hub.verify_token' );
		$challenge    = $request->get_param( 'hub.challenge' );

		// PHP and some REST stacks convert dots in query-string keys to
		// underscores (hub.mode → hub_mode). Accept both forms so Meta's
		// verification request always resolves.
		if ( null === $mode ) {
			$mode = $request->get_param( 'hub_mode' );
		}
		if ( null === $verify_token ) {
			$verify_token = $request->get_param( 'hub_verify_token' );
		}
		if ( null === $challenge ) {
			$challenge = $request->get_param( 'hub_challenge' );
		}

		$settings = $this->get_settings();

		if ( 'subscribe' === $mode && ! empty( $settings['whatsapp_verify_token'] ) && hash_equals( $settings['whatsapp_verify_token'], (string) $verify_token ) ) {
			return new WP_REST_Response( (string) $challenge, 200 );
		}

		return new WP_Error( 'dukkan_whatsapp_verify_failed', __( 'Verification failed.', 'dukkan-plugin' ), array( 'status' => 403 ) );
	}

	/**
	 * POST /webhook — receive inbound WhatsApp events.
	 *
	 * Verifies the X-Hub-Signature-256 header (when an app secret is set), then
	 * routes each incoming text message through the chatbot engine.
	 *
	 * @since 1.0.32
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		if ( ! $this->is_enabled() ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

		$raw_body = $this->raw_body();
		if ( ! $this->verify_signature( $raw_body ) ) {
			return new WP_Error( 'dukkan_whatsapp_bad_signature', __( 'Invalid signature.', 'dukkan-plugin' ), array( 'status' => 401 ) );
		}

		$body = json_decode( $raw_body, true );
		if ( ! is_array( $body ) || empty( $body['entry'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		foreach ( $body['entry'] as $entry ) {
			$changes = isset( $entry['changes'] ) ? $entry['changes'] : array();
			foreach ( $changes as $change ) {
				$value = isset( $change['value'] ) ? $change['value'] : array();
				$this->process_value( $value );
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Process a single `changes[].value` block.
	 *
	 * @since 1.0.32
	 * @param array $value The change value.
	 */
	private function process_value( $value ) {
		$messages = isset( $value['messages'] ) ? $value['messages'] : array();
		if ( ! is_array( $messages ) ) {
			return;
		}

		foreach ( $messages as $message ) {
			// Only text messages for Phase 1.
			if ( 'text' !== ( isset( $message['type'] ) ? $message['type'] : '' ) ) {
				continue;
			}

			$message_id = isset( $message['id'] ) ? sanitize_text_field( $message['id'] ) : '';
			$from       = isset( $message['from'] ) ? sanitize_text_field( $message['from'] ) : '';
			$text       = isset( $message['text']['body'] ) ? sanitize_text_field( $message['text']['body'] ) : '';

			if ( '' === $from || '' === $text ) {
				continue;
			}

			// Meta retries undelivered webhooks, which can produce duplicate
			// notifications. Skip a message id we have already processed so the
			// customer never receives the same reply twice.
			if ( '' !== $message_id && ! $this->claim_message_id( $message_id ) ) {
				continue;
			}

			$this->handle_inbound_text( $from, $text );
		}
	}

	/**
	 * Atomically claim a message id for deduplication.
	 *
	 * Returns true the first time a message id is seen (within the 5-minute
	 * window), false on subsequent sightings.
	 *
	 * @since 1.0.32
	 * @param string $message_id The WhatsApp message id (wamid.*).
	 * @return bool
	 */
	private function claim_message_id( $message_id ) {
		$key = 'dukkan_wa_msg_' . md5( (string) $message_id );

		if ( false !== get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Handle a single inbound text message: rate limit → resolve customer →
	 * process → send reply.
	 *
	 * @since 1.0.32
	 * @param string $from Sender phone number.
	 * @param string $text Message body.
	 */
	private function handle_inbound_text( $from, $text ) {
		if ( ! $this->rate_limit_ok( $from ) ) {
			return;
		}

		$user_id = $this->resolve_customer( $from );
		$history = $this->get_history( $from );

		$result = $this->chatbot->process_message( $history, $text, $user_id );

		$reply   = isset( $result['reply'] ) ? (string) $result['reply'] : '';
		$products = isset( $result['products'] ) ? $result['products'] : array();
		$handoff = ! empty( $result['handoff'] );

		// Append the reply + product links to the stored history.
		$history[] = array( 'role' => 'user', 'content' => $text );
		$history[] = array( 'role' => 'model', 'content' => $reply );
		$history   = array_slice( $history, -20 );
		$this->store_history( $from, $history );

		$outbound = $this->format_outbound( $reply, $products, $handoff );
		if ( '' !== $outbound ) {
			$this->send_message( $from, $outbound );
		}

		$this->chatbot->log_conversation( $user_id, 'wa_' . md5( $from ), $text, $reply, $handoff );
	}

	/**
	 * Build the outbound text: the assistant reply plus a compact product list.
	 *
	 * @since 1.0.32
	 * @param string $reply    Assistant reply text.
	 * @param array  $products Product cards.
	 * @param bool   $handoff  Whether handoff was requested.
	 * @return string
	 */
	private function format_outbound( $reply, $products, $handoff ) {
		$lines = array();

		if ( '' !== $reply ) {
			$lines[] = $reply;
		}

		foreach ( (array) $products as $p ) {
			$name  = isset( $p['name'] ) ? $p['name'] : '';
			$price = isset( $p['price'] ) ? $p['price'] : '';
			$link  = isset( $p['permalink'] ) ? $p['permalink'] : '';
			if ( '' === $name ) {
				continue;
			}
			$lines[] = '• ' . $name . ( '' !== $price ? ' — ' . $price : '' ) . ( '' !== $link ? "\n" . $link : '' );
		}

		if ( $handoff ) {
			$settings = $this->get_settings();
			$number   = trim( (string) $settings['whatsapp_handoff_number'] );
			if ( '' !== $number ) {
				$lines[] = sprintf(
					/* translators: %s: human support phone number */
					__( 'You can also reach our team directly on WhatsApp at %s.', 'dukkan-plugin' ),
					$number
				);
			}
		}

		$lines = array_filter( array_map( 'trim', $lines ) );

		return implode( "\n\n", $lines );
	}

	/**
	 * Send a text message through the Meta Cloud API.
	 *
	 * @since 1.0.32
	 * @param string $to   Recipient phone number.
	 * @param string $text Message text.
	 * @return bool True on success.
	 */
	public function send_message( $to, $text ) {
		$settings = $this->get_settings();
		$phone_id = $settings['whatsapp_phone_number_id'];
		$token    = $settings['whatsapp_access_token'];

		if ( '' === $phone_id || '' === $token ) {
			return false;
		}

		$response = wp_remote_post(
			'https://graph.facebook.com/' . self::GRAPH_VERSION . '/' . rawurlencode( $phone_id ) . '/messages',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'messaging_product' => 'whatsapp',
						'to'                => $to,
						'type'              => 'text',
						'text'              => array( 'body' => $text ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Dukkan WhatsApp send error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code && 201 !== (int) $code ) {
			$body = wp_remote_retrieve_body( $response );
			error_log( 'Dukkan WhatsApp send failed (HTTP ' . $code . '): ' . $body );
			return false;
		}

		return true;
	}

	/**
	 * Resolve a WhatsApp phone number to a WordPress user ID (via billing phone),
	 * so order and loyalty-points tools work for returning customers.
	 *
	 * @since 1.0.32
	 * @param string $phone Sender phone number.
	 * @return int User ID, or 0.
	 */
	public function resolve_customer( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( strlen( $digits ) < 9 ) {
			return 0;
		}
		$tail = substr( $digits, -9 );

		global $wpdb;

		// 1) A registered customer's billing phone.
		$uid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta}
				 WHERE meta_key = 'billing_phone'
				   AND REPLACE( REPLACE( REPLACE( meta_value, ' ', '' ), '-', '' ), '+', '' ) LIKE %s
				 LIMIT 1",
				'%' . $tail
			)
		);
		if ( $uid ) {
			return (int) $uid;
		}

		// 2) A guest order's billing phone → its customer user.
		$uid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->postmeta} pm2
				   ON pm.post_id = pm2.post_id AND pm2.meta_key = '_billing_phone'
				 WHERE pm.meta_key = '_customer_user' AND CAST( pm.meta_value AS UNSIGNED ) > 0
				   AND REPLACE( REPLACE( REPLACE( pm2.meta_value, ' ', '' ), '-', '' ), '+', '' ) LIKE %s
				 ORDER BY pm.post_id DESC
				 LIMIT 1",
				'%' . $tail
			)
		);

		return $uid ? (int) $uid : 0;
	}

	/**
	 * Retrieve the conversation history for a phone number.
	 *
	 * @since 1.0.32
	 * @param string $phone Sender phone number.
	 * @return array
	 */
	private function get_history( $phone ) {
		$history = get_transient( $this->history_key( $phone ) );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Store the conversation history for a phone number.
	 *
	 * @since 1.0.32
	 * @param string $phone   Sender phone number.
	 * @param array  $history Conversation turns.
	 */
	private function store_history( $phone, $history ) {
		$ttl = max( 5, (int) $this->get_settings()['whatsapp_session_ttl'] );
		set_transient( $this->history_key( $phone ), $history, $ttl * MINUTE_IN_SECONDS );
	}

	/**
	 * Transient key for a phone's conversation history.
	 *
	 * @since 1.0.32
	 * @param string $phone Sender phone number.
	 * @return string
	 */
	private function history_key( $phone ) {
		return 'dukkan_wa_hist_' . md5( (string) $phone );
	}

	/**
	 * Per-phone rate limit (reuses the chatbot's configured messages/min).
	 *
	 * @since 1.0.32
	 * @param string $phone Sender phone number.
	 * @return bool True if allowed.
	 */
	private function rate_limit_ok( $phone ) {
		$limit = (int) $this->chatbot->get_setting( 'rate_limit' );
		if ( $limit <= 0 ) {
			return true;
		}

		$key   = 'dukkan_wa_rl_' . md5( (string) $phone );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Verify the X-Hub-Signature-256 header against the app secret.
	 *
	 * Skipped when no app secret is configured (verification token only).
	 *
	 * @since 1.0.32
	 * @param string $raw_body Raw request body.
	 * @return bool
	 */
	private function verify_signature( $raw_body ) {
		$secret = $this->get_settings()['whatsapp_app_secret'];
		if ( '' === $secret ) {
			return true;
		}

		$header = isset( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ) : '';
		if ( '' === $header || 0 !== strpos( $header, 'sha256=' ) ) {
			return false;
		}

		$expected = substr( $header, 7 );
		$computed = hash_hmac( 'sha256', $raw_body, $secret );

		return hash_equals( $computed, $expected );
	}

	/**
	 * Read the raw request body for signature verification.
	 *
	 * @since 1.0.32
	 * @return string
	 */
	private function raw_body() {
		$body = file_get_contents( 'php://input' );
		return false === $body ? '' : $body;
	}
}
