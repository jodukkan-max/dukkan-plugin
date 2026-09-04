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

		add_action( 'wp_ajax_dukkan_chatbot_handoff', array( $this, 'ajax_handoff' ) );
		add_action( 'wp_ajax_nopriv_dukkan_chatbot_handoff', array( $this, 'ajax_handoff' ) );
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
				'suggestions'   => array(
					__( "What's on sale?", 'dukkan-plugin' ),
					__( 'Recommend a gift', 'dukkan-plugin' ),
					__( 'Track my order', 'dukkan-plugin' ),
				),
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
				<span class="dukkan-chatbot__launcher-icon">&#128172;</span>
			</button>

			<div class="dukkan-chatbot__panel" id="dukkan-chatbot-panel" hidden>
				<div class="dukkan-chatbot__header">
					<div class="dukkan-chatbot__header-info">
						<span class="dukkan-chatbot__avatar">&#129302;</span>
						<span class="dukkan-chatbot__name"><?php echo esc_html( $settings['bot_name'] ); ?></span>
						<span class="dukkan-chatbot__online"><?php esc_html_e( 'Online', 'dukkan-plugin' ); ?></span>
					</div>
					<button type="button" class="dukkan-chatbot__close" id="dukkan-chatbot-close" aria-label="<?php esc_attr_e( 'Close chat', 'dukkan-plugin' ); ?>">&times;</button>
				</div>

				<div class="dukkan-chatbot__messages" id="dukkan-chatbot-messages"></div>

				<div class="dukkan-chatbot__inputbar">
					<textarea id="dukkan-chatbot-input" rows="1" placeholder="<?php esc_attr_e( 'Ask about products, orders, or anything…', 'dukkan-plugin' ); ?>"></textarea>
					<button type="button" class="dukkan-chatbot__send" id="dukkan-chatbot-send" aria-label="<?php esc_attr_e( 'Send', 'dukkan-plugin' ); ?>">&#10148;</button>
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
		if ( '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'Message is empty.', 'dukkan-plugin' ) ) );
		}

		$history = isset( $_POST['history'] ) ? (array) $_POST['history'] : array();
		$history = $this->sanitize_history( $history );

		$user = $this->resolve_user();

		$result = $this->chatbot->process_message( $history, $message, $user['user_id'] );

		// Persist memory for persistent mode.
		if ( $user['user_id'] && 'persistent' === $this->chatbot->get_setting( 'memory_mode' ) ) {
			$stored = $this->append_memory( $user['key'], $message, $result['reply'] );
		}

		$this->chatbot->log_conversation( $user['user_id'], $this->chatbot->visitor_key(), $message, $result['reply'], $result['handoff'] );

		wp_send_json_success(
			array(
				'reply'    => $result['reply'],
				'products' => $result['products'],
				'handoff'  => $result['handoff'],
			)
		);
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
		set_transient( $key, $memory, 30 * DAY_IN_SECONDS );

		return $memory;
	}
}
