<?php

/**
 * The AI chatbot admin functionality of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.27
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 */

/**
 * AI chatbot admin: settings tab, settings save, index management, connection
 * test, and the conversation-log viewer.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/admin
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Chatbot_Admin {

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

		add_filter( 'dukkan_settings_tabs', array( $this, 'add_chatbot_settings_tab' ) );
		add_action( 'dukkan_settings_tab_content_chatbot', array( $this, 'render_tab_content' ) );

		add_action( 'admin_post_dukkan_chatbot_save_settings', array( $this, 'handle_save_settings' ) );

		add_action( 'wp_ajax_dukkan_chatbot_rebuild_index', array( $this, 'ajax_rebuild_index' ) );
		add_action( 'wp_ajax_dukkan_chatbot_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_dukkan_chatbot_logs', array( $this, 'ajax_logs' ) );
	}

	/**
	 * Register the admin styles.
	 *
	 * @since 1.0.27
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( 'toplevel_page_dukkan-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		$css_version = filemtime( plugin_dir_path( __FILE__ ) . 'css/dp-chatbot.css' );
		wp_enqueue_style( $this->plugin_name . '-chatbot', plugin_dir_url( __FILE__ ) . 'css/dp-chatbot.css', array(), $css_version, 'all' );
	}

	/**
	 * Register the admin scripts.
	 *
	 * @since 1.0.27
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'toplevel_page_dukkan-settings' !== $hook_suffix ) {
			return;
		}

		$js_version = filemtime( plugin_dir_path( __FILE__ ) . 'js/dp-chatbot.js' );
		wp_enqueue_script( $this->plugin_name . '-chatbot', plugin_dir_url( __FILE__ ) . 'js/dp-chatbot.js', array( 'jquery', 'wp-color-picker', $this->plugin_name ), $js_version, false );

		wp_localize_script(
			$this->plugin_name . '-chatbot',
			'dukkan_chatbot_admin',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wpldp_nonce' ),
			)
		);
	}

	/**
	 * Add the AI Chatbot tab to the Dukkan settings page.
	 *
	 * @since 1.0.27
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_chatbot_settings_tab( $tabs ) {
		$tabs['chatbot'] = array(
			'title' => __( 'AI Chatbot', 'dukkan-plugin' ),
			'icon'  => 'dashicons-format-chat',
		);
		return $tabs;
	}

	/**
	 * Render the AI Chatbot tab content.
	 *
	 * @since 1.0.27
	 */
	public function render_tab_content() {
		$settings = $this->chatbot->get_settings();
		$index    = $this->chatbot->get_index_status();
		require plugin_dir_path( __FILE__ ) . 'partials/dukkan-chatbot-settings.php';
	}

	// -------------------------------------------------------------------------
	// Settings save
	// -------------------------------------------------------------------------

	/**
	 * Sanitize a full settings payload.
	 *
	 * @since 1.0.27
	 * @param array $input Raw posted settings.
	 * @return array
	 */
	private function sanitize_settings( $input ) {
		$clean = array();

		$clean['enabled'] = empty( $input['enabled'] ) ? 0 : 1;

		$clean['deepseek_api_key'] = isset( $input['deepseek_api_key'] ) ? sanitize_text_field( $input['deepseek_api_key'] ) : '';
		$clean['deepseek_model']   = isset( $input['deepseek_model'] ) ? sanitize_text_field( $input['deepseek_model'] ) : 'deepseek-chat';
		$clean['openai_api_key']   = isset( $input['openai_api_key'] ) ? sanitize_text_field( $input['openai_api_key'] ) : '';

		$clean['language']       = isset( $input['language'] ) && in_array( $input['language'], array( 'auto', 'fixed', 'site' ), true ) ? $input['language'] : 'auto';
		$clean['fixed_language'] = isset( $input['fixed_language'] ) ? sanitize_text_field( $input['fixed_language'] ) : 'en';
		$clean['tone']           = isset( $input['tone'] ) && in_array( $input['tone'], array( 'official', 'friendly', 'casual', 'fun' ), true ) ? $input['tone'] : 'friendly';

		$clean['system_prompt'] = isset( $input['system_prompt'] ) ? sanitize_textarea_field( $input['system_prompt'] ) : '';
		$clean['bot_name']      = isset( $input['bot_name'] ) ? sanitize_text_field( $input['bot_name'] ) : 'Dukkan Assistant';
		$clean['greeting']      = isset( $input['greeting'] ) ? sanitize_textarea_field( $input['greeting'] ) : '';

		$clean['accent_color'] = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '#1d4f5f';
		if ( ! $clean['accent_color'] ) {
			$clean['accent_color'] = '#1d4f5f';
		}
		$clean['position'] = isset( $input['position'] ) && 'bottom-left' === $input['position'] ? 'bottom-left' : 'bottom-right';

		$clean['auto_index']         = empty( $input['auto_index'] ) ? 0 : 1;
		$clean['enable_lookup']      = empty( $input['enable_lookup'] ) ? 0 : 1;
		$clean['enable_add_to_cart'] = empty( $input['enable_add_to_cart'] ) ? 0 : 1;
		$clean['enable_handoff']     = empty( $input['enable_handoff'] ) ? 0 : 1;

		$clean['support_email'] = isset( $input['support_email'] ) ? sanitize_email( $input['support_email'] ) : '';
		$clean['rate_limit']    = isset( $input['rate_limit'] ) ? max( 0, absint( $input['rate_limit'] ) ) : 10;
		$clean['memory_mode']   = isset( $input['memory_mode'] ) && 'persistent' === $input['memory_mode'] ? 'persistent' : 'session';

		return $clean;
	}

	/**
	 * Handle the settings form submission.
	 *
	 * @since 1.0.27
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'dukkan-plugin' ) );
		}

		check_admin_referer( 'dukkan_chatbot_settings', 'dukkan_chatbot_settings_nonce' );

		$input = isset( $_POST['dukkan_chatbot'] ) ? wp_unslash( $_POST['dukkan_chatbot'] ) : array();

		$this->chatbot->save_settings( $this->sanitize_settings( $input ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'dukkan-settings', 'tab' => 'chatbot', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Verify the AJAX nonce and capability.
	 *
	 * @since 1.0.27
	 */
	private function verify_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dukkan-plugin' ) ), 403 );
		}
		check_ajax_referer( 'wpldp_nonce', 'nonce' );
	}

	/**
	 * AJAX: rebuild the product index.
	 *
	 * @since 1.0.27
	 */
	public function ajax_rebuild_index() {
		$this->verify_ajax();

		$this->chatbot->ensure_tables();
		$result = $this->chatbot->build_full_index();

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: test DeepSeek + OpenAI connectivity.
	 *
	 * @since 1.0.27
	 */
	public function ajax_test_connection() {
		$this->verify_ajax();

		wp_send_json_success( $this->chatbot->test_connection() );
	}

	/**
	 * AJAX: fetch recent conversation logs.
	 *
	 * @since 1.0.27
	 */
	public function ajax_logs() {
		$this->verify_ajax();

		wp_send_json_success( $this->chatbot->get_recent_logs( 50 ) );
	}
}
