<?php

/**
 * The AI chatbot public-facing functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.27
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 */

/**
 * Renders the floating chatbot widget on the storefront and exposes the chat
 * AJAX + SSE streaming endpoints.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/public
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Chatbot_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	private $version;

	/**
	 * Shared chatbot engine.
	 *
	 * @since 1.0.27
	 * @var Dukkan_Plugin_Chatbot
	 */
	private $chatbot;

	/**
	 * Initialize the class and register hooks.
	 *
	 * @since 1.0.27
	 * @param string                 $plugin_name The name of this plugin.
	 * @param string                 $version     The version of this plugin.
	 * @param Dukkan_Plugin_Chatbot $chatbot     The chatbot engine.
	 */
	public function __construct( $plugin_name, $version, $chatbot ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->chatbot     = $chatbot;

		add_action( 'wp_footer', array( $this, 'render_widget' ) );

		add_action( 'wp_ajax_dukkan_chatbot_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_nopriv_dukkan_chatbot_send', array( $this, 'ajax_send' ) );

		add_action( 'wp_ajax_dukkan_chatbot_send_stream', array( $this, 'ajax_send_stream' ) );
		add_action( 'wp_ajax_nopriv_dukkan_chatbot_send_stream', array( $this, 'ajax_send_stream' ) );

		add_action( 'wp_ajax_dukkan_chatbot_handoff', array( $this, 'ajax_handoff' ) );
		add_action( 'wp_ajax_nopriv_dukkan_chatbot_handoff', array( $this, 'ajax_handoff' ) );

		add_action( 'wp_ajax_dukkan_chatbot_more_products', array( $this, 'ajax_more_products' ) );
		add_action( 'wp_ajax_nopriv_dukkan_chatbot_more_products', array( $this, 'ajax_more_products' ) );
	}

	/**
	 * Register the public styles.
	 *
	 * @since 1.0.27
	 */
	public function enqueue_styles() {
		if ( ! $this->chatbot->is_enabled() ) {
			return;
		}

		$css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/dukkan-plugin-chatbot.css' );
		wp_enqueue_style( $this->plugin_name . '-chatbot', plugin_dir_url( __FILE__ ) . 'css/dukkan-plugin-chatbot.css', array(), $css_version, 'all' );
	}

	/**
	 * Register the public scripts.
	 *
	 * @since 1.0.27
	 */
	public function enqueue_scripts() {
		if ( ! $this->chatbot->is_enabled() ) {
			return;
		}

		$js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/dukkan-plugin-chatbot.js' );
		wp_enqueue_script( $this->plugin_name . '-chatbot', plugin_dir_url( __FILE__ ) . 'js/dukkan-plugin-chatbot.js', array( 'jquery' ), $js_version, true );

		$settings = $this->chatbot->get_settings();

		$add_to_cart_url = '';
		if ( class_exists( 'WC_AJAX' ) ) {
			$add_to_cart_url = WC_AJAX::get_endpoint( 'add_to_cart' );
		}

		wp_localize_script(
			$this->plugin_name . '-chatbot',
			'dukkan_chatbot',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'dukkan_chatbot_nonce' ),
				'add_to_cart_url' => $add_to_cart_url,
				'bot_name'        => $settings['bot_name'],
				'greeting'      => $settings['greeting'],
				'accent_color'  => $settings['accent_color'],
				'position'      => $settings['position'],
				'logged_in'     => is_user_logged_in() ? 1 : 0,
				'more_label'    => __( 'View more', 'dukkan-plugin' ),
				'lang_mode'     => isset( $settings['language'] ) ? $settings['language'] : 'auto',
				'fixed_lang'    => isset( $settings['fixed_language'] ) ? $settings['fixed_language'] : 'en',
				'site_locale'   => get_locale(),
			)
		);
	}

	/**
	 * Render the widget shell in the footer.
	 *
	 * @since 1.0.27
	 */
	public function render_widget() {
		if ( ! $this->chatbot->is_enabled() ) {
			return;
		}

		$settings = $this->chatbot->get_settings();
		$position = 'bottom-left' === $settings['position'] ? 'dukkan-chatbot--left' : 'dukkan-chatbot--right';
		?>
		<div id="dukkan-chatbot" class="dukkan-chatbot <?php echo esc_attr( $position ); ?>" aria-live="polite">
			<button type="button" class="dukkan-chatbot__launcher" id="dukkan-chatbot-launcher" aria-label="<?php esc_attr_e( 'Open chat', 'dukkan-plugin' ); ?>">
				<span class="dukkan-chatbot__launcher-icon">
					<svg viewBox="0 0 24 24" width="28" height="28" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
						<path d="M4 4h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" fill="#ffffff"/>
					</svg>
				</span>
				<span class="dukkan-chatbot__launcher-label"><?php esc_html_e( 'Chat with us', 'dukkan-plugin' ); ?></span>
			</button>

			<div class="dukkan-chatbot__panel" id="dukkan-chatbot-panel" hidden>
				<div class="dukkan-chatbot__header">
					<div class="dukkan-chatbot__header-inner">
						<div class="dukkan-chatbot__avatar">
							<?php if ( ! empty( $settings['bot_avatar'] ) ) : ?>
								<img src="<?php echo esc_url( $settings['bot_avatar'] ); ?>" alt="<?php echo esc_attr( $settings['bot_name'] ); ?>">
							<?php else : ?>
								<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="<?php echo esc_attr( $settings['bot_name'] ); ?>">
									<rect width="64" height="64" fill="#eef4ff"/>
									<path d="M10 64c1-11 9-16 22-16s21 5 22 16H10z" fill="#2563eb"/>
									<path d="M28 42h8v5a4 4 0 0 1-8 0z" fill="#f3b98c"/>
									<path d="M16 33c0-11 7-17 16-17s16 6 16 17l-2 8c-3-8-9-10-14-10s-11 2-14 10l-2-8z" fill="#3f2a1c"/>
									<ellipse cx="32" cy="32" rx="11.5" ry="12.5" fill="#f8c9a0"/>
									<circle cx="28" cy="31" r="1.4" fill="#2f2219"/>
									<circle cx="36" cy="31" r="1.4" fill="#2f2219"/>
									<path d="M29 36c2 1.6 4 1.6 6 0" stroke="#cf6f57" stroke-width="1.4" stroke-linecap="round" fill="none"/>
									<circle cx="25.5" cy="35" r="1.7" fill="#f2a98f" opacity=".55"/>
									<circle cx="38.5" cy="35" r="1.7" fill="#f2a98f" opacity=".55"/>
									<ellipse cx="32" cy="21.5" rx="19" ry="5" fill="#d9a05f"/>
									<path d="M24 21c0-6.5 3.6-10 8-10s8 3.5 8 10v1.5c-2.3 1.4-5 2-8 2s-5.7-.6-8-2V21z" fill="#e6b571"/>
									<rect x="23.6" y="21" width="16.8" height="2.8" rx="1.4" fill="#2563eb"/>
								</svg>
							<?php endif; ?>
						</div>
						<div class="dukkan-chatbot__header-text">
							<span class="dukkan-chatbot__name"><?php echo esc_html( sprintf( /* translators: %s: bot name */ __( 'Chat with %s', 'dukkan-plugin' ), $settings['bot_name'] ) ); ?></span>
							<span class="dukkan-chatbot__online">
								<span class="dukkan-chatbot__online-dot" aria-hidden="true"></span>
								<?php esc_html_e( 'We are online!', 'dukkan-plugin' ); ?>
							</span>
						</div>
						<div class="dukkan-chatbot__header-actions">
							<button type="button" class="dukkan-chatbot__close" id="dukkan-chatbot-close" aria-label="<?php esc_attr_e( 'Close chat', 'dukkan-plugin' ); ?>">&times;</button>
						</div>
					</div>
					<svg class="dukkan-chatbot__header-wave" viewBox="0 0 400 40" preserveAspectRatio="none" aria-hidden="true" focusable="false">
						<path d="M0 20 C50 0 100 40 150 20 C200 0 250 40 300 20 C350 0 400 20 400 20 L400 40 L0 40 Z"/>
					</svg>
				</div>

				<div class="dukkan-chatbot__messages" id="dukkan-chatbot-messages"></div>

				<div class="dukkan-chatbot__inputbar">
					<button type="button" class="dukkan-chatbot__attach" id="dukkan-chatbot-attach" aria-label="<?php esc_attr_e( 'Attach image', 'dukkan-plugin' ); ?>" title="<?php esc_attr_e( 'Search by photo', 'dukkan-plugin' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1.5 9 3-3 2 2 3-3 4 4H5.5Z" fill="#334155"/><circle cx="9" cy="9" r="1.5" fill="#334155"/></svg>
					</button>
					<input type="file" id="dukkan-chatbot-file" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
					<textarea id="dukkan-chatbot-input" rows="1" placeholder="<?php esc_attr_e( 'Ask about products, orders, or anything…', 'dukkan-plugin' ); ?>"></textarea>
					<button type="button" class="dukkan-chatbot__mic" id="dukkan-chatbot-mic" aria-label="<?php esc_attr_e( 'Voice search', 'dukkan-plugin' ); ?>" title="<?php esc_attr_e( 'Speak your question', 'dukkan-plugin' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3Z" fill="#334155"/><path d="M19 11a1 1 0 0 1 1 1 8 8 0 0 1-7 7.94V22h3a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2h3v-2.06A8 8 0 0 1 4 12a1 1 0 0 1 2 0 6 6 0 0 0 12 0 1 1 0 0 1 1-1Z" fill="#334155"/></svg>
					</button>
					<button type="button" class="dukkan-chatbot__send" id="dukkan-chatbot-send" aria-label="<?php esc_attr_e( 'Send', 'dukkan-plugin' ); ?>">&#10148;</button>
				</div>
				<div class="dukkan-chatbot__preview" id="dukkan-chatbot-preview" hidden>
					<img id="dukkan-chatbot-preview-img" alt="">
					<button type="button" id="dukkan-chatbot-preview-remove" aria-label="<?php esc_attr_e( 'Remove image', 'dukkan-plugin' ); ?>">&times;</button>
				</div>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Verify the chat nonce.
	 *
	 * @since 1.0.27
	 */
	private function verify_nonce() {
		check_ajax_referer( 'dukkan_chatbot_nonce', 'nonce' );
	}

	/**
	 * Resolve the current user ID and persistent memory key.
	 *
	 * @since 1.0.27
	 * @return array{user_id: int, key: string}
	 */
	private function resolve_user() {
		$user_id = get_current_user_id();

		if ( $user_id && 'persistent' === $this->chatbot->get_setting( 'memory_mode' ) ) {
			$key = 'dukkan_chatbot_mem_' . $user_id;
		} else {
			$key = 'dukkan_chatbot_mem_' . $this->chatbot->visitor_key();
		}

		return array(
			'user_id' => $user_id,
			'key'     => $key,
		);
	}

	/**
	 * AJAX: handle a chat message (non-streaming).
	 *
	 * @since 1.0.27
	 */
	public function ajax_send() {
		$this->verify_nonce();

		if ( ! $this->chatbot->is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Chat is unavailable.', 'dukkan-plugin' ) ) );
		}

		if ( ! $this->chatbot->rate_limit_ok() ) {
			wp_send_json_error( array( 'message' => __( 'You are sending messages too quickly. Please wait a moment.', 'dukkan-plugin' ) ) );
		}

		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$image   = isset( $_POST['image'] ) ? sanitize_text_field( wp_unslash( $_POST['image'] ) ) : '';

		// An image search may come with an empty caption; default the text.
		if ( '' === $message && '' !== $image ) {
			$message = __( 'Find products similar to this image.', 'dukkan-plugin' );
		}

		if ( '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'Message is empty.', 'dukkan-plugin' ) ) );
		}

		$history = $this->request_history();

		$user = $this->resolve_user();

		// Persistent mode: server-side memory is the authoritative history, so
		// logged-in customers are remembered across visits and devices.
		if ( $user['user_id'] && 'persistent' === $this->chatbot->get_setting( 'memory_mode' ) ) {
			$memory = get_transient( $user['key'] );
			if ( is_array( $memory ) ) {
				$history = $memory;
			}
		}

		$result = $this->chatbot->process_message( $history, $message, $user['user_id'], $image );

		// Persist memory for persistent mode.
		if ( $user['user_id'] && 'persistent' === $this->chatbot->get_setting( 'memory_mode' ) ) {
			$stored = $this->append_memory( $user['key'], $message, $result['reply'] );
		}

		$this->chatbot->log_conversation( $user['user_id'], $this->chatbot->visitor_key(), $message, $result['reply'], $result['handoff'] );

		wp_send_json_success(
			array(
				'reply'    => $result['reply'],
				'products' => $result['products'],
				'has_more' => ! empty( $result['has_more'] ),
				'handoff'  => $result['handoff'],
			)
		);
	}

	/**
	 * AJAX: stream a chat reply as Server-Sent Events (token-by-token).
	 *
	 * Emits `data: {"t":"..."}` events for each text delta, followed by a
	 * final `data: {"d":{reply,products,has_more,handoff}}` event.
	 *
	 * @since 1.0.29
	 */
	public function ajax_send_stream() {
		$this->verify_nonce();

		if ( ! $this->chatbot->is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Chat is unavailable.', 'dukkan-plugin' ) ) );
		}

		if ( ! $this->chatbot->rate_limit_ok() ) {
			wp_send_json_error( array( 'message' => __( 'You are sending messages too quickly. Please wait a moment.', 'dukkan-plugin' ) ) );
		}

		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$image   = isset( $_POST['image'] ) ? sanitize_text_field( wp_unslash( $_POST['image'] ) ) : '';

		// An image search may come with an empty caption; default the text.
		if ( '' === $message && '' !== $image ) {
			$message = __( 'Find products similar to this image.', 'dukkan-plugin' );
		}

		if ( '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'Message is empty.', 'dukkan-plugin' ) ) );
		}

		$history = $this->request_history();

		$user = $this->resolve_user();

		// Persistent mode: server-side memory is the authoritative history.
		if ( $user['user_id'] && 'persistent' === $this->chatbot->get_setting( 'memory_mode' ) ) {
			$memory = get_transient( $user['key'] );
			if ( is_array( $memory ) ) {
				$history = $memory;
			}
		}

		// Prepare a clean streaming channel: disable compression/buffering and
		// drop any output buffers so tokens reach the browser immediately.
		@ini_set( 'zlib.output_compression', 'Off' );
		@ini_set( 'output_buffering', 'Off' );
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'X-Accel-Buffering: no' );

		$flush = function () {
			@ob_flush();
			flush();
		};

		$on_token = function ( $delta ) use ( $flush ) {
			echo 'data: ' . wp_json_encode( array( 't' => $delta ) ) . "\n\n";
			$flush();
		};

		$result = $this->chatbot->process_message_stream( $history, $message, $user['user_id'], $on_token, $image );

		// Persist memory + conversation log using the full reply.
		if ( $user['user_id'] && 'persistent' === $this->chatbot->get_setting( 'memory_mode' ) ) {
			$this->append_memory( $user['key'], $message, $result['reply'] );
		}
		$this->chatbot->log_conversation( $user['user_id'], $this->chatbot->visitor_key(), $message, $result['reply'], $result['handoff'] );

		echo 'data: ' . wp_json_encode(
			array(
				'd' => array(
					'reply'    => $result['reply'],
					'products' => $result['products'],
					'has_more' => ! empty( $result['has_more'] ),
					'handoff'  => ! empty( $result['handoff'] ),
				),
			)
		) . "\n\n";
		$flush();
		exit;
	}

	/**
	 * AJAX: handle a human-handoff request.
	 *
	 * @since 1.0.27
	 */
	public function ajax_handoff() {
		$this->verify_nonce();

		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$history = isset( $_POST['history'] ) ? (array) $_POST['history'] : array();
		$history = $this->sanitize_history( $history );

		$sent = $this->chatbot->handle_handoff( $email, $history );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Our team has been notified and will be with you shortly.', 'dukkan-plugin' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not send the request. Please contact us by email.', 'dukkan-plugin' ) ) );
		}
	}

	/**
	 * AJAX: return the deferred "View more" product cards for this visitor.
	 *
	 * @since 1.0.27
	 */
	public function ajax_more_products() {
		$this->verify_nonce();
		wp_send_json_success( array( 'products' => $this->chatbot->get_more_products() ) );
	}

	/**
	 * Read and sanitize the conversation history from the current request.
	 *
	 * The non-streaming fallback posts `history` as a jQuery-serialized nested
	 * array (`history[0][role]=...&history[0][content]=...`), while the
	 * streaming path posts it as a single JSON string. Accept both so the
	 * assistant always receives the prior turns.
	 *
	 * @since 1.0.29
	 * @return array
	 */
	private function request_history() {
		$raw = isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : array();

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return $this->sanitize_history( $raw );
	}

	/**
	 * Sanitize a client-supplied history array.
	 *
	 * @since 1.0.27
	 * @param array $history Raw history.
	 * @return array
	 */
	private function sanitize_history( $history ) {
		$clean = array();
		foreach ( $history as $turn ) {
			if ( ! is_array( $turn ) || ! isset( $turn['role'], $turn['content'] ) ) {
				continue;
			}
			$role = 'user' === $turn['role'] ? 'user' : 'assistant';
			$clean[] = array(
				'role'    => $role,
				'content' => sanitize_text_field( wp_unslash( $turn['content'] ) ),
			);
		}
		return array_slice( $clean, -10 );
	}

	/**
	 * Append to persistent memory, keeping the last N turns.
	 *
	 * @since 1.0.27
	 * @param string $key     Memory key.
	 * @param string $message User message.
	 * @param string $reply   Assistant reply.
	 * @return array
	 */
	private function append_memory( $key, $message, $reply ) {
		$memory = get_transient( $key );
		if ( ! is_array( $memory ) ) {
			$memory = array();
		}

		$memory[] = array( 'role' => 'user', 'content' => $message );
		$memory[] = array( 'role' => 'assistant', 'content' => $reply );

		$memory = array_slice( $memory, -10 );
		set_transient( $key, $memory, 30 * MINUTE_IN_SECONDS );

		return $memory;
	}
}
