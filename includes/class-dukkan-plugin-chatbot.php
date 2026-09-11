<?php

/**
 * The AI chatbot core engine of the plugin.
 *
 * @link       https://dukkanjo.com
 * @since      1.0.27
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 */

/**
 * Core AI chatbot engine.
 *
 * Powers a Google Gemini-based store assistant: chat via Gemini 2.5 Flash-Lite,
 * semantic product search via text-embedding-005 embeddings, order/loyalty
 * lookups and add-to-cart via function calling, human handoff, and a
 * conversation log. All API keys are held server-side.
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Chatbot {

	/**
	 * Option key holding the chatbot settings.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const SETTINGS_KEY = 'dukkan_chatbot_settings';

	/**
	 * Product index table name (without prefix).
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const TABLE_PRODUCTS = 'dukkan_chatbot_products';

	/**
	 * Category index table name (without prefix).
	 *
	 * @since 1.0.29
	 * @var string
	 */
	const TABLE_CATEGORIES = 'dukkan_chatbot_categories';

	/**
	 * Page index table name (without prefix).
	 *
	 * @since 1.0.30
	 * @var string
	 */
	const TABLE_PAGES = 'dukkan_chatbot_pages';

	/**
	 * Order index table name (without prefix).
	 *
	 * @since 1.0.31
	 * @var string
	 */
	const TABLE_ORDERS = 'dukkan_chatbot_orders';

	/**
	 * Conversation log table name (without prefix).
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const TABLE_LOG = 'dukkan_chatbot_log';

	/**
	 * Index meta option key (last build time + product count).
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const INDEX_META_KEY = 'dukkan_chatbot_index_meta';

	/**
	 * Category index meta option key (last build time + category count).
	 *
	 * @since 1.0.29
	 * @var string
	 */
	const CATEGORY_INDEX_META_KEY = 'dukkan_chatbot_category_index_meta';

	/**
	 * Page index meta option key (last build time + page count).
	 *
	 * @since 1.0.30
	 * @var string
	 */
	const PAGE_INDEX_META_KEY = 'dukkan_chatbot_page_index_meta';

	/**
	 * Order index meta option key (last build time + order count).
	 *
	 * @since 1.0.31
	 * @var string
	 */
	const ORDER_INDEX_META_KEY = 'dukkan_chatbot_order_index_meta';

	/**
	 * Database schema version. Bump to force a table re-check.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const DB_VERSION = '1.3.0';

	/**
	 * Option key tracking the installed DB schema version.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const DB_VERSION_KEY = 'dukkan_chatbot_db_version';

	/**
	 * Embedding strategy version. Bump when the embedding model, task type,
	 * or document text format changes so stale vectors are invalidated and
	 * re-indexed automatically.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const EMBEDDING_VERSION = '3';

	/**
	 * Option key tracking the embedding strategy version.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const EMBEDDING_VERSION_KEY = 'dukkan_chatbot_embedding_version';

	/**
	 * The store's Google AI Studio API key.
	 *
	 * Hardcoded so the assistant works out of the box. The admin setting can
	 * still override it if a different key is ever needed.
	 *
	 * @since 1.0.27
	 * @var string
	 */
	const GOOGLE_API_KEY = 'AIzaSyAAJ7uID5lBY95GeudszYBth6xRnWinRYc';

	/**
	 * The Gemma open model used for chat.
	 *
	 * Served through the same Gemini API gateway, so it uses the identical
	 * `functionCall`/`functionResponse` protocol and the same Google API key.
	 * Free-tier only on the Gemini API (no paid tier exists).
	 *
	 * @since 1.0.34
	 * @var string
	 */
	const GEMMA_MODEL = 'gemma-4-26b-a4b-it';

	/**
	 * Option key storing the cumulative usage/cost meter.
	 *
	 * @since 1.0.31
	 * @var string
	 */
	const USAGE_KEY = 'dukkan_chatbot_usage';

	/**
	 * USD price per 1M input tokens for Gemma 4 26B A4B (free tier on the
	 * Gemini API, so the meter reports $0).
	 *
	 * @since 1.0.34
	 * @var float
	 */
	const PRICE_GEMMA_INPUT = 0.0;

	/**
	 * USD price per 1M output tokens for Gemma 4 26B A4B (free tier).
	 *
	 * @since 1.0.34
	 * @var float
	 */
	const PRICE_GEMMA_OUTPUT = 0.0;

	/**
	 * USD price per 1M input tokens for gemini-embedding-001.
	 *
	 * @since 1.0.31
	 * @var float
	 */
	const PRICE_EMBED_INPUT = 0.15;

	/**
	 * Default settings.
	 *
	 * @since 1.0.27
	 * @var array
	 */
	protected $defaults = array(
		'enabled'            => 0,
		'google_api_key'     => '',
		'language'           => 'auto',
		'fixed_language'     => 'en',
		'tone'               => 'friendly',
		'system_prompt'      => '',
		'bot_name'           => 'Jessica Smith',
		'bot_avatar'         => '',
		'greeting'           => '',
		'accent_color'       => '#1d4f5f',
		'position'           => 'bottom-right',
		'auto_index'         => 1,
		'enable_lookup'      => 1,
		'enable_handoff'     => 1,
		'support_email'      => '',
		'rate_limit'         => 10,
		'memory_mode'        => 'session',
		// WhatsApp Business (Meta Cloud API).
		'whatsapp_enabled'         => 0,
		'whatsapp_phone_number_id' => '',
		'whatsapp_access_token'    => '',
		'whatsapp_verify_token'    => '',
		'whatsapp_app_secret'      => '',
		'whatsapp_session_ttl'     => 60,
		'whatsapp_handoff_number'  => '',
	);

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
	 * Products returned by the last search_products tool call, used to render
	 * product cards in the widget.
	 *
	 * @since 1.0.27
	 * @var array
	 */
	private $last_products = array();

	/**
	 * Initialize the class and register cron hooks.
	 *
	 * @since 1.0.27
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( 'dukkan_chatbot_reindex', array( $this, 'cron_reindex' ) );
		add_action( 'save_post_product', array( $this, 'on_product_saved' ), 10, 2 );
		add_action( 'save_post_page', array( $this, 'on_page_saved' ), 10, 2 );

		// Force IPv4 for Gemini calls: Google's geolocation DB frequently
		// misclassifies IPv6 ranges and returns "User location is not
		// supported", even from supported regions.
		add_action( 'http_api_curl', array( $this, 'force_ipv4_for_google' ), 10, 3 );

		// Ensure tables exist even when the plugin is uploaded manually
		// (which skips the activation hook).
		add_action( 'init', array( $this, 'maybe_ensure_tables' ) );
	}

	/**
	 * Force the cURL transport to resolve and connect over IPv4 for requests
	 * to Google's generative language endpoints.
	 *
	 * Google has been observed rejecting requests made over IPv6 with a
	 * "User location is not supported" error even when the server is in a
	 * supported region, due to inaccurate IPv6 geolocation data.
	 *
	 * @since 1.0.27
	 * @param resource $handle      The cURL handle.
	 * @param array    $parsed_args Request arguments.
	 * @param string   $url         Request URL.
	 */
	public function force_ipv4_for_google( $handle, $parsed_args, $url ) {
		if ( false !== strpos( (string) $url, 'generativelanguage.googleapis.com' ) ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	/**
	 * Retrieve chatbot settings merged with defaults.
	 *
	 * @since 1.0.27
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::SETTINGS_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, $this->defaults );
	}

	/**
	 * Retrieve a single setting value.
	 *
	 * @since 1.0.27
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get_setting( $key ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Resolve the Google API key.
	 *
	 * Always falls back to the hardcoded store key so the assistant works even
	 * if the saved setting is empty.
	 *
	 * @since 1.0.27
	 * @return string
	 */
	public function get_google_api_key() {
		$key = $this->get_setting( 'google_api_key' );
		if ( empty( $key ) ) {
			$key = self::GOOGLE_API_KEY;
		}
		return $key;
	}

	/**
	 * Resolve the active chat model ID based on the admin selection.
	 *
	 * @since 1.0.34
	 * @return string
	 */
	public function model_name() {
		return self::GEMMA_MODEL;
	}

	/**
	 * Build the model-appropriate thinking configuration.
	 *
	 * Gemma 4 does not accept any `thinkingConfig` (it rejects both
	 * `thinkingLevel` and `thinkingBudget` with HTTP 400) — it thinks by
	 * default and emits `thought: true` parts, so we return an empty config
	 * and filter those thought parts separately.
	 *
	 * @since 1.0.34
	 * @param int $budget Unused (kept for call-site compatibility).
	 * @return array
	 */
	private function thinking_config( $budget ) {
		return array();
	}

	/**
	 * Build the model-appropriate sampling configuration.
	 *
	 * Gemma 4's model card recommends `temperature=1.0`, `top_p=0.95`,
	 * `top_k=64` across all use cases — a low temperature makes open models
	 * repetitive and stiff, and hurts natural function calling.
	 *
	 * @since 1.0.34
	 * @return array
	 */
	private function sampling_config() {
		return array(
			'temperature' => 1.0,
			'topP'        => 0.95,
			'topK'        => 64,
		);
	}

	/**
	 * USD price per 1M input tokens for the active chat model.
	 *
	 * @since 1.0.34
	 * @return float
	 */
	private function chat_input_price() {
		return self::PRICE_GEMMA_INPUT;
	}

	/**
	 * USD price per 1M output tokens for the active chat model.
	 *
	 * @since 1.0.34
	 * @return float
	 */
	private function chat_output_price() {
		return self::PRICE_GEMMA_OUTPUT;
	}

	/**
	 * Persist settings.
	 *
	 * @since 1.0.27
	 * @param array $settings
	 */
	public function save_settings( $settings ) {
		update_option( self::SETTINGS_KEY, $settings, 'no' );
	}

	/**
	 * Whether the chatbot is enabled.
	 *
	 * @since 1.0.27
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->get_setting( 'enabled' ) );
	}

	// -------------------------------------------------------------------------
	// Usage & cost meter
	// -------------------------------------------------------------------------

	/**
	 * Retrieve the cumulative usage meter.
	 *
	 * @since 1.0.31
	 * @return array
	 */
	public function get_usage() {
		$usage = get_option( self::USAGE_KEY, array() );
		if ( ! is_array( $usage ) ) {
			$usage = array();
		}

		return wp_parse_args(
			$usage,
			array(
				'chat_input'   => 0,
				'chat_output'  => 0,
				'embed_tokens' => 0,
				'chat_calls'   => 0,
				'embed_calls'  => 0,
				'updated_at'   => '',
			)
		);
	}

	/**
	 * Record chat tokens (input + output/thinking) against the meter.
	 *
	 * @since 1.0.31
	 * @param int $input_tokens  Prompt token count.
	 * @param int $output_tokens Candidates + thinking token count.
	 */
	private function record_chat_usage( $input_tokens, $output_tokens ) {
		$usage                 = $this->get_usage();
		$usage['chat_input']  += (int) $input_tokens;
		$usage['chat_output'] += (int) $output_tokens;
		$usage['chat_calls']  += 1;
		$usage['updated_at']   = current_time( 'mysql', true );
		update_option( self::USAGE_KEY, $usage, 'no' );
	}

	/**
	 * Record embedding tokens against the meter.
	 *
	 * @since 1.0.31
	 * @param int $tokens Estimated token count.
	 * @param int $calls  Number of embedding calls (1 = one batch).
	 */
	private function record_embed_usage( $tokens, $calls = 1 ) {
		$usage                  = $this->get_usage();
		$usage['embed_tokens'] += (int) $tokens;
		$usage['embed_calls']  += (int) $calls;
		$usage['updated_at']    = current_time( 'mysql', true );
		update_option( self::USAGE_KEY, $usage, 'no' );
	}

	/**
	 * Reset the usage meter to zero.
	 *
	 * @since 1.0.31
	 */
	public function reset_usage() {
		delete_option( self::USAGE_KEY );
	}

	/**
	 * Estimate the number of tokens in a text (~4 characters per token).
	 *
	 * Used only for the embedding meter, since the embedding API does not
	 * return usage metadata.
	 *
	 * @since 1.0.31
	 * @param string $text Text to estimate.
	 * @return int
	 */
	private function estimate_tokens( $text ) {
		$length = mb_strlen( (string) $text, 'UTF-8' );
		return max( 1, (int) ceil( $length / 4 ) );
	}

	/**
	 * Estimated cumulative cost in USD from the meter.
	 *
	 * @since 1.0.31
	 * @return float
	 */
	public function get_estimated_cost() {
		$usage = $this->get_usage();

		$cost  = ( (float) $usage['chat_input'] / 1000000 ) * $this->chat_input_price();
		$cost += ( (float) $usage['chat_output'] / 1000000 ) * $this->chat_output_price();
		$cost += ( (float) $usage['embed_tokens'] / 1000000 ) * self::PRICE_EMBED_INPUT;

		return $cost;
	}

	// -------------------------------------------------------------------------
	// Tables
	// -------------------------------------------------------------------------

	/**
	 * Full product-index table name.
	 *
	 * @since 1.0.27
	 * @return string
	 */
	public function products_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_PRODUCTS;
	}

	/**
	 * Full category-index table name.
	 *
	 * @since 1.0.29
	 * @return string
	 */
	public function categories_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_CATEGORIES;
	}

	/**
	 * Full page-index table name.
	 *
	 * @since 1.0.30
	 * @return string
	 */
	public function pages_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_PAGES;
	}

	/**
	 * Full order-index table name.
	 *
	 * @since 1.0.31
	 * @return string
	 */
	public function orders_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_ORDERS;
	}

	/**
	 * Full conversation-log table name.
	 *
	 * @since 1.0.27
	 * @return string
	 */
	public function log_table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_LOG;
	}

	/**
	 * Create (or update) the plugin's custom tables.
	 *
	 * @since 1.0.27
	 */
	public function ensure_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$products        = $this->products_table();
		$categories      = $this->categories_table();
		$pages           = $this->pages_table();
		$orders          = $this->orders_table();
		$log             = $this->log_table();

		$sql_orders = "CREATE TABLE {$orders} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL DEFAULT 0,
			status varchar(40) NULL,
			date_created varchar(40) NULL,
			total varchar(40) NULL,
			summary text NULL,
			embedding longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		$sql_pages = "CREATE TABLE {$pages} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			page_id bigint(20) NOT NULL,
			title text NULL,
			content text NULL,
			permalink text NULL,
			embedding longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY page_id (page_id)
		) {$charset_collate};";

		$sql_categories = "CREATE TABLE {$categories} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			term_id bigint(20) NOT NULL,
			name text NULL,
			description text NULL,
			count bigint(20) NULL,
			embedding longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY term_id (term_id)
		) {$charset_collate};";

		$sql_products = "CREATE TABLE {$products} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			product_id bigint(20) NOT NULL,
			sku varchar(100) NULL,
			name text NULL,
			price decimal(19,4) NULL,
			sale_price decimal(19,4) NULL,
			stock_status varchar(20) NULL,
			categories text NULL,
			short_description text NULL,
			attributes text NULL,
			embedding longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_id (product_id)
		) {$charset_collate};";

		$sql_log = "CREATE TABLE {$log} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL DEFAULT 0,
			visitor_key varchar(64) NULL,
			message text NULL,
			reply text NULL,
			handoff tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_categories );
		dbDelta( $sql_products );
		dbDelta( $sql_pages );
		dbDelta( $sql_orders );
		dbDelta( $sql_log );
	}

	/**
	 * Create tables lazily if the DB schema version is out of date.
	 *
	 * Runs on `init` so the tables exist even when the plugin was uploaded
	 * manually (which skips the activation hook).
	 *
	 * @since 1.0.27
	 */
	public function maybe_ensure_tables() {
		if ( get_option( self::DB_VERSION_KEY ) !== self::DB_VERSION ) {
			$this->ensure_tables();
			update_option( self::DB_VERSION_KEY, self::DB_VERSION, 'no' );
		}

		// Invalidate stale embeddings when the embedding strategy changes
		// (e.g. switching to RETRIEVAL_DOCUMENT task type). The next cron
		// run or a manual "Rebuild index now" re-embeds everything correctly.
		if ( get_option( self::EMBEDDING_VERSION_KEY ) !== self::EMBEDDING_VERSION ) {
			$this->invalidate_embeddings();
			update_option( self::EMBEDDING_VERSION_KEY, self::EMBEDDING_VERSION, 'no' );
		}
	}

	/**
	 * Clear all stored product embeddings so they are rebuilt with the
	 * current strategy.
	 *
	 * @since 1.0.27
	 */
	public function invalidate_embeddings() {
		global $wpdb;
		$table = $this->products_table();
		$wpdb->query( "UPDATE {$table} SET embedding = NULL" ); // phpcs:ignore
	}

	// -------------------------------------------------------------------------
	// Cron
	// -------------------------------------------------------------------------

	/**
	 * Register a daily cron interval for the reindex.
	 *
	 * @since 1.0.27
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedule( $schedules ) {
		$schedules['dukkan_daily'] = array(
			'interval' => DAY_IN_SECONDS,
			'display'  => __( 'Once Daily', 'dukkan-plugin' ),
		);
		return $schedules;
	}

	/**
	 * Ensure the reindex cron event is scheduled daily at 4am (site time).
	 *
	 * Handles migration from the old every-two-days schedule.
	 *
	 * @since 1.0.27
	 */
	public function schedule_reindex() {
		if ( get_option( 'dukkan_chatbot_cron_v2' ) !== 'daily' ) {
			// Migrate from the old every-two-days schedule.
			$this->clear_schedule();
			$this->schedule_daily_reindex();
			update_option( 'dukkan_chatbot_cron_v2', 'daily', 'no' );
			return;
		}

		if ( ! wp_next_scheduled( 'dukkan_chatbot_reindex' ) ) {
			$this->schedule_daily_reindex();
		}
	}

	/**
	 * Schedule the daily reindex at 4am in the site's timezone.
	 *
	 * @since 1.0.27
	 */
	private function schedule_daily_reindex() {
		$tz   = wp_timezone();
		$next = new DateTimeImmutable( 'tomorrow 04:00:00', $tz );
		wp_schedule_event( $next->getTimestamp(), 'dukkan_daily', 'dukkan_chatbot_reindex' );
	}

	/**
	 * Clear the reindex cron event.
	 *
	 * @since 1.0.27
	 */
	public function clear_schedule() {
		wp_clear_scheduled_hook( 'dukkan_chatbot_reindex' );
	}

	/**
	 * Cron callback: rebuild the full product + category indexes.
	 *
	 * @since 1.0.27
	 */
	public function cron_reindex() {
		$this->ensure_tables();

		// Rebuild products in full (loop batches until the queue drains).
		$guard = 0;
		do {
			$result = $this->build_full_index_batch( 100 );
			$guard++;
		} while ( empty( $result['done'] ) && $guard < 100 );

		// Rebuild categories in full (loop batches until the queue drains).
		$guard = 0;
		do {
			$result = $this->build_category_index_batch( 100 );
			$guard++;
		} while ( empty( $result['done'] ) && $guard < 100 );

		// Rebuild pages in full (loop batches until the queue drains).
		$guard = 0;
		do {
			$result = $this->build_page_index_batch( 100 );
			$guard++;
		} while ( empty( $result['done'] ) && $guard < 100 );

		// Rebuild orders in full (loop batches until the queue drains).
		$guard = 0;
		do {
			$result = $this->build_order_index_batch( 100 );
			$guard++;
		} while ( empty( $result['done'] ) && $guard < 100 );
	}

	/**
	 * Re-embed a product when it is saved (live freshness).
	 *
	 * @since 1.0.27
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_product_saved( $post_id, $post ) {
		if ( ! $this->is_enabled() || empty( $this->get_setting( 'auto_index' ) ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		// Debounce: avoid embedding twice within the same request.
		$key = 'dukkan_chatbot_indexed_' . $post_id;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, MINUTE_IN_SECONDS );

		$this->ensure_tables();
		$this->index_product( $post_id );
	}

	/**
	 * Re-embed a page when it is saved (live freshness).
	 *
	 * @since 1.0.30
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_page_saved( $post_id, $post ) {
		if ( ! $this->is_enabled() || empty( $this->get_setting( 'auto_index' ) ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$key = 'dukkan_chatbot_indexed_page_' . $post_id;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, MINUTE_IN_SECONDS );

		$this->ensure_tables();
		$this->index_page( $post_id );
	}

	// -------------------------------------------------------------------------
	// Embeddings (Google)
	// -------------------------------------------------------------------------

	/**
	 * Embed a text into a vector via Google's Gemini embeddings API.
	 *
	 * @since 1.0.27
	 * @param string $text      Text to embed.
	 * @param string $task_type Retrieval task type. Use RETRIEVAL_QUERY for
	 *                          user queries and RETRIEVAL_DOCUMENT for indexed
	 *                          product documents. Gemini's default
	 *                          (unspecified) behaves like RETRIEVAL_QUERY, so
	 *                          product embeddings MUST use RETRIEVAL_DOCUMENT
	 *                          or retrieval precision suffers.
	 * @return array|WP_Error Vector array on success.
	 */
	public function embed_text( $text, $task_type = 'RETRIEVAL_QUERY' ) {
		$api_key = $this->get_google_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_google_key', __( 'Google API key is not configured.', 'dukkan-plugin' ) );
		}

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent',
			array(
				'timeout' => 15,
				'headers' => array(
					'x-goog-api-key' => $api_key,
					'Content-Type'   => 'application/json',
				),
				'body'    => $this->json_encode(
					array(
						'content'  => array(
							'parts' => array(
								array( 'text' => mb_substr( $text, 0, 4000 ) ),
							),
						),
						'taskType' => $task_type,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Dukkan chatbot Gemini embed transport error: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['embedding']['values'] ) ) {
			$message = __( 'Embedding request failed.', 'dukkan-plugin' );
			if ( ! empty( $body['error']['message'] ) ) {
				$message .= ' ' . $body['error']['message'];
			} else {
				$message .= ' HTTP ' . $code;
			}
			error_log( 'Dukkan chatbot Gemini embed error: ' . $message );
			return new WP_Error( 'embed_failed', $message );
		}

		$this->record_embed_usage( $this->estimate_tokens( $text ), 1 );

		return $body['embedding']['values'];
	}

	/**
	 * Embed multiple texts in one API call via Gemini's batch endpoint.
	 *
	 * Indexing used to make one HTTP request per product, which made a full
	 * rebuild very slow. Google's `batchEmbedContents` accepts up to 100
	 * requests in a single call, so this collapses N round-trips into N/100.
	 *
	 * @since 1.0.29
	 * @param string[] $texts     Texts to embed.
	 * @param string   $task_type Embedding task type (RETRIEVAL_DOCUMENT/QUERY).
	 * @return array|WP_Error Array of vectors aligned with $texts, or WP_Error.
	 */
	public function embed_texts_batch( $texts, $task_type = 'RETRIEVAL_DOCUMENT' ) {
		$api_key = $this->get_google_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_google_key', __( 'Google API key is not configured.', 'dukkan-plugin' ) );
		}

		$texts = array_values( array_filter( array_map( 'strval', (array) $texts ) ) );
		if ( empty( $texts ) ) {
			return array();
		}

		$requests = array();
		foreach ( $texts as $text ) {
			$requests[] = array(
				'model'    => 'models/gemini-embedding-001',
				'content'  => array(
					'parts' => array( array( 'text' => mb_substr( $text, 0, 4000 ) ) ),
				),
				'taskType' => $task_type,
			);
		}

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents',
			array(
				'timeout' => 60,
				'headers' => array(
					'x-goog-api-key' => $api_key,
					'Content-Type'   => 'application/json',
				),
				'body'    => $this->json_encode( array( 'requests' => $requests ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Dukkan chatbot Gemini batch embed transport error: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! isset( $body['embeddings'] ) || ! is_array( $body['embeddings'] ) ) {
			$message = __( 'Embedding request failed.', 'dukkan-plugin' );
			if ( ! empty( $body['error']['message'] ) ) {
				$message .= ' ' . $body['error']['message'];
			} else {
				$message .= ' HTTP ' . $code;
			}
			error_log( 'Dukkan chatbot Gemini batch embed error: ' . $message );
			return new WP_Error( 'embed_failed', $message );
		}

		$vectors = array();
		foreach ( $body['embeddings'] as $embedding ) {
			if ( ! empty( $embedding['values'] ) && is_array( $embedding['values'] ) ) {
				$vectors[] = $embedding['values'];
			} else {
				$vectors[] = null;
			}
		}

		$total_tokens = array_sum( array_map( array( $this, 'estimate_tokens' ), $texts ) );
		$this->record_embed_usage( $total_tokens, 1 );

		return $vectors;
	}

	/**
	 * Compute cosine similarity between two equal-length vectors.
	 *
	 * @since 1.0.27
	 * @param array $a Vector A.
	 * @param array $b Vector B.
	 * @return float
	 */
	private function cosine_similarity( $a, $b ) {
		$n = count( $a );
		if ( 0 === $n || count( $b ) !== $n ) {
			return 0.0;
		}

		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;

		for ( $i = 0; $i < $n; $i++ ) {
			$dot += $a[ $i ] * $b[ $i ];
			$na  += $a[ $i ] * $a[ $i ];
			$nb  += $b[ $i ] * $b[ $i ];
		}

		if ( 0.0 === $na || 0.0 === $nb ) {
			return 0.0;
		}

		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}

	// -------------------------------------------------------------------------
	// Product index
	// -------------------------------------------------------------------------

	/**
	 * Build the embedding text for a product.
	 *
	 * @since 1.0.27
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function product_embed_text( $product ) {
		$cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		if ( is_wp_error( $cats ) ) {
			$cats = array();
		}

		$parts = array(
			$product->get_name(),
			$product->get_sku(),
			$product->get_short_description(),
		);

		if ( ! empty( $cats ) ) {
			$parts[] = implode( ', ', $cats );
		}

		$attributes = $this->product_attributes_text( $product );
		if ( '' !== $attributes ) {
			$parts[] = $attributes;
		}

		return implode( ' | ', array_filter( array_map( 'trim', $parts ) ) );
	}

	/**
	 * Extract a product's visible attributes and their values as a compact
	 * string, e.g. "Color: Red, Blue | Size: S, M, L".
	 *
	 * @since 1.0.29
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function product_attributes_text( $product ) {
		if ( ! method_exists( $product, 'get_attributes' ) ) {
			return '';
		}

		$attributes = $product->get_attributes();
		if ( empty( $attributes ) ) {
			return '';
		}

		$parts = array();
		foreach ( $attributes as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute ) {
				continue;
			}
			if ( ! $attribute->get_visible() ) {
				continue;
			}

			$name = wc_attribute_label( $attribute->get_name() );

			if ( $attribute->is_taxonomy() ) {
				$terms = $attribute->get_terms();
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				$values = wp_list_pluck( $terms, 'name' );
			} else {
				$values = $attribute->get_options();
			}

			$values = array_filter( array_map( 'trim', (array) $values ) );
			if ( ! empty( $values ) ) {
				$parts[] = $name . ': ' . implode( ', ', $values );
			}
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Embed and store a single product.
	 *
	 * @since 1.0.27
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function index_product( $product_id, $vector = null ) {
		global $wpdb;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$wpdb->delete( $this->products_table(), array( 'product_id' => $product_id ), array( '%d' ) );
			return false;
		}

		if ( null === $vector ) {
			$vector = $this->embed_text( $this->product_embed_text( $product ), 'RETRIEVAL_DOCUMENT' );
		}
		$embedding_json = ( is_wp_error( $vector ) || null === $vector ) ? null : wp_json_encode( $vector );

		$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		if ( is_wp_error( $cats ) ) {
			$cats = array();
		}

		$row = array(
			'product_id'        => $product_id,
			'sku'               => $product->get_sku(),
			'name'              => $product->get_name(),
			'price'             => (float) $product->get_price(),
			'sale_price'        => (float) $product->get_sale_price(),
			'stock_status'      => $product->get_stock_status(),
			'categories'        => implode( ', ', $cats ),
			'short_description' => $product->get_short_description(),
			'attributes'        => $this->product_attributes_text( $product ),
			'embedding'         => $embedding_json,
			'updated_at'        => current_time( 'mysql', true ),
		);

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->products_table()} WHERE product_id = %d", $product_id ) );

		if ( $existing ) {
			$wpdb->update( $this->products_table(), $row, array( 'product_id' => $product_id ), array( '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $this->products_table(), $row, array( '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		}

		return true;
	}

	/**
	 * Rebuild the full product index.
	 *
	 * @since 1.0.27
	 * @param int $limit Max products to index in one pass (0 = all).
	 * @return array Counts.
	 */
	public function build_full_index( $limit = 0 ) {
		$product_ids = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => $limit > 0 ? $limit : -1,
				'return' => 'ids',
			)
		);

		$indexed = 0;
		$failed  = 0;

		foreach ( $product_ids as $product_id ) {
			if ( $this->index_product( $product_id ) ) {
				$indexed++;
			} else {
				$failed++;
			}
		}

		update_option(
			self::INDEX_META_KEY,
			array(
				'last_build' => current_time( 'mysql', true ),
				'count'      => $indexed,
			),
			'no'
		);

		return array(
			'indexed' => $indexed,
			'failed'  => $failed,
		);
	}

	/**
	 * Build one batch of the product index, for a progressive AJAX rebuild.
	 *
	 * The full rebuild is too slow to run in a single request (one embedding
	 * API call per product), so it is split into small chunks. The pending
	 * product IDs are stashed in a transient and consumed a chunk at a time;
	 * when the queue empties the build is finished and the index meta is set.
	 *
	 * @since 1.0.29
	 * @param int $batch_size Products per request.
	 * @return array{total:int, indexed:int, failed:int, done:bool}
	 */
	public function build_full_index_batch( $batch_size = 50 ) {
		// The batch endpoint accepts up to 100 texts per call, so a larger
		// chunk is now cheap. Cap at 100 to stay within the API limit.
		$batch_size = max( 1, min( 100, (int) $batch_size ) );

		$queue = get_transient( 'dukkan_chatbot_index_queue' );
		if ( ! is_array( $queue ) ) {
			// Start a fresh queue: all published product IDs.
			$product_ids = wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => -1,
					'return' => 'ids',
				)
			);
			$queue       = array_values( (array) $product_ids );
			update_option(
				'dukkan_chatbot_index_progress',
				array(
					'total'  => count( $queue ),
					'done'   => 0,
					'failed' => 0,
				),
				'no'
			);
		}

		$progress = get_option( 'dukkan_chatbot_index_progress', array() );
		$total    = isset( $progress['total'] ) ? (int) $progress['total'] : count( $queue );
		$done     = isset( $progress['done'] ) ? (int) $progress['done'] : 0;
		$failed   = isset( $progress['failed'] ) ? (int) $progress['failed'] : 0;

		$chunk = array_splice( $queue, 0, $batch_size );

		// Gather the embed text for every product in the chunk, then embed the
		// whole chunk in a single batched API call (fast) instead of one call
		// per product (slow).
		$texts    = array();
		$products = array();
		foreach ( $chunk as $product_id ) {
			$product = wc_get_product( (int) $product_id );
			if ( ! $product ) {
				$this->index_product( (int) $product_id, null );
				continue;
			}
			$texts[]    = $this->product_embed_text( $product );
			$products[] = $product;
		}

		$vectors = $this->embed_texts_batch( $texts, 'RETRIEVAL_DOCUMENT' );

		foreach ( $products as $i => $product ) {
			$vector = is_wp_error( $vectors ) ? null : ( isset( $vectors[ $i ] ) ? $vectors[ $i ] : null );
			if ( $this->index_product( $product->get_id(), $vector ) ) {
				$done++;
			} else {
				$failed++;
			}
		}

		if ( empty( $queue ) ) {
			// Finished the queue.
			delete_transient( 'dukkan_chatbot_index_queue' );
			delete_option( 'dukkan_chatbot_index_progress' );
			update_option(
				self::INDEX_META_KEY,
				array(
					'last_build' => current_time( 'mysql', true ),
					'count'      => $done,
				),
				'no'
			);

			return array(
				'total'   => $total,
				'indexed' => $done,
				'failed'  => $failed,
				'done'    => true,
			);
		}

		set_transient( 'dukkan_chatbot_index_queue', $queue, 30 * MINUTE_IN_SECONDS );
		update_option(
			'dukkan_chatbot_index_progress',
			array(
				'total'  => $total,
				'done'   => $done,
				'failed' => $failed,
			),
			'no'
		);

		return array(
			'total'   => $total,
			'indexed' => $done,
			'failed'  => $failed,
			'done'    => false,
		);
	}

	/**
	 * Retrieve the current index status.
	 *
	 * @since 1.0.27
	 * @return array
	 */
	public function get_index_status() {
		global $wpdb;

		$this->maybe_ensure_tables();

		$table = $this->products_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$meta = get_option( self::INDEX_META_KEY, array() );

		return array(
			'count'      => (int) $count,
			'last_build' => isset( $meta['last_build'] ) ? $meta['last_build'] : '',
		);
	}

	/**
	 * Build the embedding text for a product category.
	 *
	 * @since 1.0.29
	 * @param WP_Term $term Category term.
	 * @return string
	 */
	private function category_embed_text( $term ) {
		$parts = array( $term->name );

		if ( ! empty( $term->description ) ) {
			$parts[] = $term->description;
		}

		// Include the parent chain so "Shirts" also matches "Men".
		$ancestors = get_ancestors( $term->term_id, 'product_cat' );
		if ( ! empty( $ancestors ) ) {
			$names = array();
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'product_cat' );
				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$names[] = $ancestor->name;
				}
			}
			if ( ! empty( $names ) ) {
				$parts[] = implode( ', ', $names );
			}
		}

		return implode( ' | ', array_filter( array_map( 'trim', $parts ) ) );
	}

	/**
	 * Embed and store a single product category.
	 *
	 * @since 1.0.29
	 * @param int $term_id Category term ID.
	 * @return bool
	 */
	public function index_category( $term_id, $vector = null ) {
		global $wpdb;

		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			$wpdb->delete( $this->categories_table(), array( 'term_id' => $term_id ), array( '%d' ) );
			return false;
		}

		if ( null === $vector ) {
			$vector = $this->embed_text( $this->category_embed_text( $term ), 'RETRIEVAL_DOCUMENT' );
		}
		$embedding_json = ( is_wp_error( $vector ) || null === $vector ) ? null : wp_json_encode( $vector );

		$row = array(
			'term_id'     => (int) $term_id,
			'name'        => $term->name,
			'description' => $term->description,
			'count'       => (int) $term->count,
			'embedding'   => $embedding_json,
			'updated_at'  => current_time( 'mysql', true ),
		);

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->categories_table()} WHERE term_id = %d", $term_id ) );

		if ( $existing ) {
			$wpdb->update( $this->categories_table(), $row, array( 'term_id' => $term_id ), array( '%d', '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $this->categories_table(), $row, array( '%d', '%s', '%s', '%d', '%s', '%s' ) );
		}

		return true;
	}

	/**
	 * Build one batch of the category index, for a progressive AJAX rebuild.
	 *
	 * @since 1.0.29
	 * @param int $batch_size Categories per request.
	 * @return array{total:int, indexed:int, failed:int, done:bool}
	 */
	public function build_category_index_batch( $batch_size = 50 ) {
		$batch_size = max( 1, min( 100, (int) $batch_size ) );

		$queue = get_transient( 'dukkan_chatbot_category_queue' );
		if ( ! is_array( $queue ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => 0,
				)
			);
			$queue = is_wp_error( $terms ) ? array() : array_values( array_map( 'intval', $terms ) );
			update_option(
				'dukkan_chatbot_category_progress',
				array(
					'total'  => count( $queue ),
					'done'   => 0,
					'failed' => 0,
				),
				'no'
			);
		}

		$progress = get_option( 'dukkan_chatbot_category_progress', array() );
		$total    = isset( $progress['total'] ) ? (int) $progress['total'] : count( $queue );
		$done     = isset( $progress['done'] ) ? (int) $progress['done'] : 0;
		$failed   = isset( $progress['failed'] ) ? (int) $progress['failed'] : 0;

		$chunk = array_splice( $queue, 0, $batch_size );

		// Embed the whole chunk of categories in a single batched API call.
		$texts = array();
		$terms = array();
		foreach ( $chunk as $term_id ) {
			$term = get_term( (int) $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				$this->index_category( (int) $term_id, null );
				continue;
			}
			$texts[] = $this->category_embed_text( $term );
			$terms[] = $term;
		}

		$vectors = $this->embed_texts_batch( $texts, 'RETRIEVAL_DOCUMENT' );

		foreach ( $terms as $i => $term ) {
			$vector = is_wp_error( $vectors ) ? null : ( isset( $vectors[ $i ] ) ? $vectors[ $i ] : null );
			if ( $this->index_category( $term->term_id, $vector ) ) {
				$done++;
			} else {
				$failed++;
			}
		}

		if ( empty( $queue ) ) {
			delete_transient( 'dukkan_chatbot_category_queue' );
			delete_option( 'dukkan_chatbot_category_progress' );
			update_option(
				self::CATEGORY_INDEX_META_KEY,
				array(
					'last_build' => current_time( 'mysql', true ),
					'count'      => $done,
				),
				'no'
			);

			return array(
				'total'   => $total,
				'indexed' => $done,
				'failed'  => $failed,
				'done'    => true,
			);
		}

		set_transient( 'dukkan_chatbot_category_queue', $queue, 30 * MINUTE_IN_SECONDS );
		update_option(
			'dukkan_chatbot_category_progress',
			array(
				'total'  => $total,
				'done'   => $done,
				'failed' => $failed,
			),
			'no'
		);

		return array(
			'total'   => $total,
			'indexed' => $done,
			'failed'  => $failed,
			'done'    => false,
		);
	}

	/**
	 * Retrieve the current category index status.
	 *
	 * @since 1.0.29
	 * @return array
	 */
	public function get_category_index_status() {
		global $wpdb;

		$this->maybe_ensure_tables();

		$table = $this->categories_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$meta = get_option( self::CATEGORY_INDEX_META_KEY, array() );

		return array(
			'count'      => (int) $count,
			'last_build' => isset( $meta['last_build'] ) ? $meta['last_build'] : '',
		);
	}

	// -------------------------------------------------------------------------
	// Page index
	// -------------------------------------------------------------------------

	/**
	 * Build the embedding text for a WordPress page.
	 *
	 * @since 1.0.30
	 * @param WP_Post $post Page post object.
	 * @return string
	 */
	private function page_embed_text( $post ) {
		$content = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		return trim( (string) $post->post_title . ' | ' . $content );
	}

	/**
	 * Embed and store a single page (or delete it if no longer published).
	 *
	 * @since 1.0.30
	 * @param int   $page_id Page ID.
	 * @param array $vector  Optional precomputed embedding vector.
	 * @return bool
	 */
	public function index_page( $page_id, $vector = null ) {
		global $wpdb;

		$post = get_post( $page_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			$wpdb->delete( $this->pages_table(), array( 'page_id' => $page_id ), array( '%d' ) );
			return false;
		}

		if ( null === $vector ) {
			$vector = $this->embed_text( $this->page_embed_text( $post ), 'RETRIEVAL_DOCUMENT' );
		}
		$embedding_json = ( is_wp_error( $vector ) || null === $vector ) ? null : wp_json_encode( $vector );

		$row = array(
			'page_id'    => (int) $page_id,
			'title'      => $post->post_title,
			'content'    => wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ),
			'permalink'  => get_permalink( $page_id ),
			'embedding'  => $embedding_json,
			'updated_at' => current_time( 'mysql', true ),
		);

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->pages_table()} WHERE page_id = %d", $page_id ) );

		if ( $existing ) {
			$wpdb->update( $this->pages_table(), $row, array( 'page_id' => $page_id ), array( '%d', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $this->pages_table(), $row, array( '%d', '%s', '%s', '%s', '%s', '%s' ) );
		}

		return true;
	}

	/**
	 * Build one batch of the page index, for a progressive AJAX rebuild.
	 *
	 * @since 1.0.30
	 * @param int $batch_size Pages per request.
	 * @return array{total:int, indexed:int, failed:int, done:bool}
	 */
	public function build_page_index_batch( $batch_size = 50 ) {
		$batch_size = max( 1, min( 100, (int) $batch_size ) );

		$queue = get_transient( 'dukkan_chatbot_page_queue' );
		if ( ! is_array( $queue ) ) {
			$page_ids = get_posts(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			);
			$queue    = array_values( array_map( 'intval', (array) $page_ids ) );
			update_option(
				'dukkan_chatbot_page_progress',
				array(
					'total'  => count( $queue ),
					'done'   => 0,
					'failed' => 0,
				),
				'no'
			);
		}

		$progress = get_option( 'dukkan_chatbot_page_progress', array() );
		$total    = isset( $progress['total'] ) ? (int) $progress['total'] : count( $queue );
		$done     = isset( $progress['done'] ) ? (int) $progress['done'] : 0;
		$failed   = isset( $progress['failed'] ) ? (int) $progress['failed'] : 0;

		$chunk = array_splice( $queue, 0, $batch_size );

		$texts = array();
		$posts = array();
		foreach ( $chunk as $page_id ) {
			$post = get_post( (int) $page_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				$this->index_page( (int) $page_id, null );
				continue;
			}
			$texts[] = $this->page_embed_text( $post );
			$posts[] = $post;
		}

		$vectors = $this->embed_texts_batch( $texts, 'RETRIEVAL_DOCUMENT' );

		foreach ( $posts as $i => $post ) {
			$vector = is_wp_error( $vectors ) ? null : ( isset( $vectors[ $i ] ) ? $vectors[ $i ] : null );
			if ( $this->index_page( $post->ID, $vector ) ) {
				$done++;
			} else {
				$failed++;
			}
		}

		if ( empty( $queue ) ) {
			delete_transient( 'dukkan_chatbot_page_queue' );
			delete_option( 'dukkan_chatbot_page_progress' );
			update_option(
				self::PAGE_INDEX_META_KEY,
				array(
					'last_build' => current_time( 'mysql', true ),
					'count'      => $done,
				),
				'no'
			);

			return array(
				'total'   => $total,
				'indexed' => $done,
				'failed'  => $failed,
				'done'    => true,
			);
		}

		set_transient( 'dukkan_chatbot_page_queue', $queue, 30 * MINUTE_IN_SECONDS );
		update_option(
			'dukkan_chatbot_page_progress',
			array(
				'total'  => $total,
				'done'   => $done,
				'failed' => $failed,
			),
			'no'
		);

		return array(
			'total'   => $total,
			'indexed' => $done,
			'failed'  => $failed,
			'done'    => false,
		);
	}

	/**
	 * Retrieve the current page index status.
	 *
	 * @since 1.0.30
	 * @return array
	 */
	public function get_page_index_status() {
		global $wpdb;

		$this->maybe_ensure_tables();

		$table = $this->pages_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$meta = get_option( self::PAGE_INDEX_META_KEY, array() );

		return array(
			'count'      => (int) $count,
			'last_build' => isset( $meta['last_build'] ) ? $meta['last_build'] : '',
		);
	}

	/**
	 * Semantic search over the page index.
	 *
	 * @since 1.0.30
	 * @param string $query User query.
	 * @param int    $top_n Number of results.
	 * @return array
	 */
	public function search_pages( $query, $top_n = 3 ) {
		global $wpdb;

		$vector = $this->embed_text( $query );
		if ( is_wp_error( $vector ) ) {
			return $this->keyword_search_pages( $query, $top_n );
		}

		$rows = $wpdb->get_results( "SELECT page_id, title, content, permalink, embedding FROM {$this->pages_table()} WHERE embedding IS NOT NULL", ARRAY_A );

		$scored = array();
		foreach ( $rows as $row ) {
			$stored = json_decode( $row['embedding'], true );
			if ( ! is_array( $stored ) ) {
				continue;
			}
			$score    = $this->cosine_similarity( $vector, $stored );
			$scored[] = array(
				'page_id'   => (int) $row['page_id'],
				'title'     => $row['title'],
				'content'   => $row['content'],
				'permalink' => $row['permalink'],
				'score'     => $score,
			);
		}

		usort(
			$scored,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		$result = array();
		$max    = ! empty( $scored[0]['score'] ) ? (float) $scored[0]['score'] : 0.0;
		foreach ( $scored as $item ) {
			if ( count( $result ) >= $top_n ) {
				break;
			}
			if ( $item['score'] < 0.30 ) {
				continue;
			}
			if ( $max > 0 && $item['score'] < $max * 0.5 ) {
				continue;
			}
			unset( $item['score'] );
			$result[] = $item;
		}

		if ( empty( $result ) ) {
			return $this->keyword_search_pages( $query, $top_n );
		}

		return $result;
	}

	/**
	 * Keyword-based page search (used when embeddings are unavailable).
	 *
	 * @since 1.0.30
	 * @param string $query User query.
	 * @param int    $top_n Number of results.
	 * @return array
	 */
	private function keyword_search_pages( $query, $top_n = 3 ) {
		global $wpdb;

		$terms = preg_split( '/\s+/', trim( (string) $query ) );
		$terms = array_filter( array_map( 'trim', $terms ) );
		if ( empty( $terms ) ) {
			return array();
		}

		$clauses = array();
		$params  = array();
		foreach ( $terms as $term ) {
			$like      = '%' . $wpdb->esc_like( $term ) . '%';
			$clauses[] = '(title LIKE %s OR content LIKE %s)';
			array_push( $params, $like, $like );
		}

		$params[] = $top_n;

		$table = $this->pages_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_id, title, content, permalink FROM {$table} WHERE " . implode( ' OR ', $clauses ) . ' LIMIT %d',
				$params
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $rows as $row ) {
			$result[] = array(
				'page_id'   => (int) $row['page_id'],
				'title'     => $row['title'],
				'content'   => $row['content'],
				'permalink' => $row['permalink'],
			);
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Order index
	// -------------------------------------------------------------------------

	/**
	 * Build the embedding text for a single order.
	 *
	 * Includes the order number, status, total and full item list so a query
	 * like "where is my mascara order?" or "order 1234" matches semantically.
	 *
	 * @since 1.0.31
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	private function order_embed_text( $order ) {
		$item_names = array();
		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item->get_name();
		}

		$date = $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '';

		return sprintf(
			'Order #%1$d %2$s %3$s %4$s %5$s',
			$order->get_id(),
			$date,
			wc_get_order_status_name( $order->get_status() ),
			html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ),
			$item_names ? 'items: ' . implode( ', ', $item_names ) : ''
		);
	}

	/**
	 * Embed and store a single order (or delete it if it no longer exists).
	 *
	 * @since 1.0.31
	 * @param int   $order_id Order ID.
	 * @param array $vector   Optional precomputed embedding vector.
	 * @return bool
	 */
	public function index_order( $order_id, $vector = null ) {
		global $wpdb;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$wpdb->delete( $this->orders_table(), array( 'order_id' => $order_id ), array( '%d' ) );
			return false;
		}

		$item_names = array();
		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item->get_name();
		}

		$summary = sprintf(
			'#%1$d — %2$s — %3$s — %4$s%5$s',
			$order->get_id(),
			$order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
			wc_get_order_status_name( $order->get_status() ),
			html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ),
			$item_names ? ' (' . implode( ', ', array_slice( $item_names, 0, 10 ) ) . ')' : ''
		);

		if ( null === $vector ) {
			$vector = $this->embed_text( $this->order_embed_text( $order ), 'RETRIEVAL_DOCUMENT' );
		}
		$embedding_json = ( is_wp_error( $vector ) || null === $vector ) ? null : wp_json_encode( $vector );

		$row = array(
			'order_id'     => (int) $order_id,
			'user_id'      => (int) $order->get_customer_id(),
			'status'       => wc_get_order_status_name( $order->get_status() ),
			'date_created' => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
			'total'        => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ),
			'summary'      => $summary,
			'embedding'    => $embedding_json,
			'updated_at'   => current_time( 'mysql', true ),
		);

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->orders_table()} WHERE order_id = %d", $order_id ) );

		if ( $existing ) {
			$wpdb->update( $this->orders_table(), $row, array( 'order_id' => $order_id ), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $this->orders_table(), $row, array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		}

		return true;
	}

	/**
	 * Build one batch of the order index, for a progressive AJAX rebuild.
	 *
	 * @since 1.0.31
	 * @param int $batch_size Orders per request.
	 * @return array{total:int, indexed:int, failed:int, done:bool}
	 */
	public function build_order_index_batch( $batch_size = 50 ) {
		$batch_size = max( 1, min( 100, (int) $batch_size ) );

		$queue = get_transient( 'dukkan_chatbot_order_queue' );
		if ( ! is_array( $queue ) ) {
			// All real statuses except checkout drafts (abandoned carts).
			$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) );
			$order_ids = wc_get_orders(
				array(
					'limit'    => -1,
					'return'   => 'ids',
					'orderby'  => 'date',
					'order'    => 'DESC',
					'status'   => array_values( $statuses ),
				)
			);
			$queue     = array_values( array_map( 'intval', (array) $order_ids ) );
			update_option(
				'dukkan_chatbot_order_progress',
				array(
					'total'  => count( $queue ),
					'done'   => 0,
					'failed' => 0,
				),
				'no'
			);
		}

		$progress = get_option( 'dukkan_chatbot_order_progress', array() );
		$total    = isset( $progress['total'] ) ? (int) $progress['total'] : count( $queue );
		$done     = isset( $progress['done'] ) ? (int) $progress['done'] : 0;
		$failed   = isset( $progress['failed'] ) ? (int) $progress['failed'] : 0;

		$chunk = array_splice( $queue, 0, $batch_size );

		$texts  = array();
		$orders = array();
		foreach ( $chunk as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( ! $order ) {
				$this->index_order( (int) $order_id, null );
				continue;
			}
			$texts[]  = $this->order_embed_text( $order );
			$orders[] = $order;
		}

		$vectors = $this->embed_texts_batch( $texts, 'RETRIEVAL_DOCUMENT' );

		foreach ( $orders as $i => $order ) {
			$vector = is_wp_error( $vectors ) ? null : ( isset( $vectors[ $i ] ) ? $vectors[ $i ] : null );
			if ( $this->index_order( $order->get_id(), $vector ) ) {
				$done++;
			} else {
				$failed++;
			}
		}

		if ( empty( $queue ) ) {
			delete_transient( 'dukkan_chatbot_order_queue' );
			delete_option( 'dukkan_chatbot_order_progress' );
			update_option(
				self::ORDER_INDEX_META_KEY,
				array(
					'last_build' => current_time( 'mysql', true ),
					'count'      => $done,
				),
				'no'
			);

			return array(
				'total'   => $total,
				'indexed' => $done,
				'failed'  => $failed,
				'done'    => true,
			);
		}

		set_transient( 'dukkan_chatbot_order_queue', $queue, 30 * MINUTE_IN_SECONDS );
		update_option(
			'dukkan_chatbot_order_progress',
			array(
				'total'  => $total,
				'done'   => $done,
				'failed' => $failed,
			),
			'no'
		);

		return array(
			'total'   => $total,
			'indexed' => $done,
			'failed'  => $failed,
			'done'    => false,
		);
	}

	/**
	 * Retrieve the current order index status.
	 *
	 * @since 1.0.31
	 * @return array
	 */
	public function get_order_index_status() {
		global $wpdb;

		$this->maybe_ensure_tables();

		$table = $this->orders_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$meta = get_option( self::ORDER_INDEX_META_KEY, array() );

		return array(
			'count'      => (int) $count,
			'last_build' => isset( $meta['last_build'] ) ? $meta['last_build'] : '',
		);
	}

	/**
	 * Retrieve a customer's orders from the index, optionally ranked by a query.
	 *
	 * Falls back to a live WooCommerce lookup when the index is empty so the
	 * assistant never goes silent before the first rebuild.
	 *
	 * @since 1.0.31
	 * @param int    $user_id Customer user ID.
	 * @param string $query   Optional search query (order number, item, etc.).
	 * @param int    $top_n   Number of results.
	 * @return array List of order summary strings.
	 */
	public function search_orders( $user_id, $query = '', $top_n = 5 ) {
		global $wpdb;

		$table = $this->orders_table();
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );

		// Empty index: fall back to a live lookup so the reply still works.
		if ( 0 === $count ) {
			return $this->lookup_orders_live( $user_id, $query, $top_n );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT order_id, summary, embedding FROM {$table} WHERE user_id = %d ORDER BY order_id DESC LIMIT %d", $user_id, 100 ),
			ARRAY_A
		);

		// No query: return the most recent orders in plain order.
		if ( '' === $query ) {
			return array_map(
				function ( $row ) {
					return $row['summary'];
				},
				array_slice( $rows, 0, $top_n )
			);
		}

		// Semantic ranking against the query.
		$translated = $this->translate_query_to_english( $query );
		$qvec       = $this->embed_text( $translated, 'RETRIEVAL_QUERY' );

		if ( ! is_wp_error( $qvec ) ) {
			$scored = array();
			foreach ( $rows as $row ) {
				$stored = json_decode( $row['embedding'], true );
				if ( ! is_array( $stored ) ) {
					continue;
				}
				$scored[] = array(
					'summary' => $row['summary'],
					'score'   => $this->cosine_similarity( $qvec, $stored ),
				);
			}
			usort(
				$scored,
				function ( $a, $b ) {
					return $b['score'] <=> $a['score'];
				}
			);
			$top = array_map(
				function ( $item ) {
					return $item['summary'];
				},
				array_slice( $scored, 0, $top_n )
			);

			if ( ! empty( $top ) ) {
				return $top;
			}
		}

		// Embeddings unavailable: keyword fallback over the stored summaries.
		$terms = array_filter( array_map( 'trim', preg_split( '/\s+/', trim( (string) $query ) ) ) );
		if ( ! empty( $terms ) ) {
			$matched = array();
			foreach ( $rows as $row ) {
				foreach ( $terms as $term ) {
					if ( false !== stripos( (string) $row['summary'], $term ) ) {
						$matched[] = $row['summary'];
						break;
					}
				}
			}
			if ( ! empty( $matched ) ) {
				return array_slice( $matched, 0, $top_n );
			}
		}

		return array_map(
			function ( $row ) {
				return $row['summary'];
			},
			array_slice( $rows, 0, $top_n )
		);
	}

	/**
	 * Live WooCommerce order lookup (used before the order index is built).
	 *
	 * @since 1.0.31
	 * @param int    $user_id Customer user ID.
	 * @param string $query   Optional search query.
	 * @param int    $top_n   Number of results.
	 * @return array List of order summary strings.
	 */
	private function lookup_orders_live( $user_id, $query = '', $top_n = 5 ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => max( 5, (int) $top_n ),
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$lines = array();
		foreach ( $orders as $order ) {
			$item_names = array();
			foreach ( $order->get_items() as $item ) {
				$item_names[] = $item->get_name();
			}
			$lines[] = sprintf(
				'#%1$d — %2$s — %3$s — %4$s%5$s',
				$order->get_id(),
				$order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
				wc_get_order_status_name( $order->get_status() ),
				html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ),
				$item_names ? ' (' . implode( ', ', array_slice( $item_names, 0, 5 ) ) . ')' : ''
			);
		}

		return array_slice( $lines, 0, $top_n );
	}

	/**
	 * Semantic search over the category index.
	 *
	 * @since 1.0.29
	 * @param string $query User query.
	 * @param int    $top_n Number of results.
	 * @return array
	 */
	public function search_categories( $query, $top_n = 6 ) {
		global $wpdb;

		$vector = $this->embed_text( $query );
		if ( is_wp_error( $vector ) ) {
			return $this->keyword_search_categories( $query, $top_n );
		}

		$rows = $wpdb->get_results( "SELECT term_id, name, description, count, embedding FROM {$this->categories_table()} WHERE embedding IS NOT NULL", ARRAY_A );

		$scored = array();
		foreach ( $rows as $row ) {
			$stored = json_decode( $row['embedding'], true );
			if ( ! is_array( $stored ) ) {
				continue;
			}
			$score = $this->cosine_similarity( $vector, $stored );
			$scored[] = array(
				'term_id'     => (int) $row['term_id'],
				'name'        => $row['name'],
				'description' => $row['description'],
				'count'       => (int) $row['count'],
				'score'       => $score,
			);
		}

		usort( $scored, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		$result = array();
		$max    = ! empty( $scored[0]['score'] ) ? (float) $scored[0]['score'] : 0.0;
		foreach ( $scored as $item ) {
			if ( count( $result ) >= $top_n ) {
				break;
			}
			if ( $item['score'] < 0.35 ) {
				continue;
			}
			if ( $max > 0 && $item['score'] < $max * 0.55 ) {
				continue;
			}
			unset( $item['score'] );
			$result[] = $item;
		}

		if ( empty( $result ) ) {
			return $this->keyword_search_categories( $query, $top_n );
		}

		return $result;
	}

	/**
	 * Keyword-based category search (fallback when embeddings are unavailable).
	 *
	 * @since 1.0.29
	 * @param string $query User query.
	 * @param int    $top_n Number of results.
	 * @return array
	 */
	private function keyword_search_categories( $query, $top_n = 6 ) {
		global $wpdb;

		$terms = array_filter( array_map( 'trim', preg_split( '/\s+/', trim( (string) $query ) ) ) );
		if ( empty( $terms ) ) {
			return array();
		}

		$clauses = array();
		$params  = array();
		foreach ( $terms as $term ) {
			$like      = '%' . $wpdb->esc_like( $term ) . '%';
			$clauses[] = '(name LIKE %s OR description LIKE %s)';
			array_push( $params, $like, $like );
		}
		$params[] = $top_n;

		$table = $this->categories_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id, name, description, count FROM {$table} WHERE " . implode( ' OR ', $clauses ) . ' LIMIT %d',
				$params
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $rows as $row ) {
			$result[] = array(
				'term_id'     => (int) $row['term_id'],
				'name'        => $row['name'],
				'description' => $row['description'],
				'count'       => (int) $row['count'],
			);
		}

		return $result;
	}

	/**
	 * Semantic search over the product index.
	 *
	 * @since 1.0.27
	 * @param string $query User query.
	 * @param int    $top_n Number of results.
	 * @return array
	 */
	public function search_products( $query, $top_n = 6, $filters = array() ) {
		global $wpdb;

		$vector = $this->embed_text( $query );

		// No Google key configured (or embedding failed): fall back to keyword search.
		if ( is_wp_error( $vector ) ) {
			return $this->keyword_search( $query, $top_n, $filters );
		}

		$rows = $wpdb->get_results( "SELECT product_id, sku, name, price, sale_price, stock_status, categories, short_description, attributes, embedding FROM {$this->products_table()} WHERE embedding IS NOT NULL", ARRAY_A );

		// Pre-compute query terms for the hybrid keyword boost.
		$terms = preg_split( '/\s+/', strtolower( trim( (string) $query ) ) );
		$terms = array_values( array_filter( array_map( 'trim', $terms ), function ( $t ) {
			return strlen( $t ) > 1;
		} ) );

		$scored = array();
		foreach ( $rows as $row ) {
			// Structured filters (price / stock / category / attribute) are
			// applied first so unrelated rows never enter the ranking.
			if ( ! $this->product_matches_filters( $row, $filters ) ) {
				continue;
			}

			$stored = json_decode( $row['embedding'], true );
			if ( ! is_array( $stored ) ) {
				continue;
			}
			$score = $this->cosine_similarity( $vector, $stored );

			// Hybrid boost: an exact lexical match (name/SKU/category/attribute)
			// is a strong signal, so nudge its rank above mere semantic neighbours.
			$score += $this->keyword_boost( $row, $terms );

			$scored[] = array(
				'product_id'        => (int) $row['product_id'],
				'sku'               => $row['sku'],
				'name'              => $row['name'],
				'price'             => $row['price'],
				'sale_price'        => $row['sale_price'],
				'stock_status'      => $row['stock_status'],
				'categories'        => $row['categories'],
				'short_description' => $row['short_description'],
				'attributes'        => $row['attributes'],
				'score'             => $score,
			);
		}

		usort( $scored, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		// Filter to relevant results only. Two gates:
		//   1. An absolute floor — scores below this are noise (same broad
		//      category but not actually matching the query).
		//   2. A relative floor — a result far below the best match is almost
		//      always unrelated, so drop it even if it clears the absolute floor.
		// We deliberately return FEWER results rather than pad up to $top_n:
		// showing a couple of strong matches beats listing unrelated products.
		$result = array();
		$max    = ! empty( $scored[0]['score'] ) ? (float) $scored[0]['score'] : 0.0;
		foreach ( $scored as $item ) {
			if ( count( $result ) >= $top_n ) {
				break;
			}
			if ( $item['score'] < 0.42 ) {
				continue;
			}
			if ( $max > 0 && $item['score'] < $max * 0.68 ) {
				continue;
			}
			unset( $item['score'] );
			$result[] = $item;
		}

		// No semantically strong matches: fall back to keyword search.
		if ( empty( $result ) ) {
			return $this->keyword_search( $query, $top_n, $filters );
		}

		return $result;
	}

	/**
	 * Hybrid lexical boost for a product row against query terms.
	 *
	 * Exact keyword matches (in name, SKU, category or attribute values) raise
	 * the semantic score so brand names, SKUs and transliterations rank first.
	 *
	 * @since 1.0.34
	 * @param array $row   Product row.
	 * @param array $terms Normalized query terms.
	 * @return float Boost amount (0.0 - 0.30).
	 */
	private function keyword_boost( $row, $terms ) {
		if ( empty( $terms ) ) {
			return 0.0;
		}

		$haystack = strtolower( implode( ' ', array(
			(string) $row['name'],
			(string) $row['sku'],
			(string) $row['categories'],
			(string) $row['attributes'],
		) ) );

		$boost  = 0.0;
		$matched = 0;
		foreach ( $terms as $term ) {
			if ( false !== strpos( $haystack, $term ) ) {
				$matched++;
			}
		}

		if ( $matched > 0 ) {
			$boost = min( 0.30, 0.10 * $matched );
		}

		return $boost;
	}

	/**
	 * Whether a product row passes the structured filters.
	 *
	 * @since 1.0.34
	 * @param array $row     Product row.
	 * @param array $filters Filters: price_min, price_max, in_stock, category, attribute.
	 * @return bool
	 */
	private function product_matches_filters( $row, $filters ) {
		if ( ! is_array( $filters ) || empty( $filters ) ) {
			return true;
		}

		// Effective price: sale price when on sale, otherwise regular price.
		$sale  = isset( $row['sale_price'] ) ? (float) $row['sale_price'] : 0.0;
		$price = isset( $row['price'] ) ? (float) $row['price'] : 0.0;
		$effective = ( $sale > 0 && $sale < $price ) ? $sale : $price;

		if ( isset( $filters['price_min'] ) && '' !== $filters['price_min'] && $effective < (float) $filters['price_min'] ) {
			return false;
		}
		if ( isset( $filters['price_max'] ) && '' !== $filters['price_max'] && $effective > (float) $filters['price_max'] ) {
			return false;
		}

		if ( ! empty( $filters['in_stock'] ) && 'instock' !== ( isset( $row['stock_status'] ) ? $row['stock_status'] : '' ) ) {
			return false;
		}

		if ( ! empty( $filters['category'] ) && false === stripos( (string) ( isset( $row['categories'] ) ? $row['categories'] : '' ), (string) $filters['category'] ) ) {
			return false;
		}

		if ( ! empty( $filters['attribute'] ) && false === stripos( (string) ( isset( $row['attributes'] ) ? $row['attributes'] : '' ), (string) $filters['attribute'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Keyword-based product search (used when embeddings are unavailable).
	 *
	 * Matches the query terms against name, SKU, short description and
	 * categories using a LIKE query. Requires no external API key.
	 *
	 * @since 1.0.27
	 * @param string $query User query.
	 * @param int    $top_n Number of results.
	 * @param array  $filters Structured filters (price/stock/category/attribute).
	 * @return array
	 */
	private function keyword_search( $query, $top_n = 6, $filters = array() ) {
		global $wpdb;

		$terms = preg_split( '/\s+/', trim( (string) $query ) );
		$terms = array_filter( array_map( 'trim', $terms ) );
		if ( empty( $terms ) ) {
			return array();
		}

		$clauses = array();
		$params  = array();
		foreach ( $terms as $term ) {
			$like      = '%' . $wpdb->esc_like( $term ) . '%';
			$clauses[] = '(name LIKE %s OR sku LIKE %s OR short_description LIKE %s OR categories LIKE %s OR attributes LIKE %s)';
			array_push( $params, $like, $like, $like, $like, $like );
		}

		$params[] = $top_n * 5;

		$table = $this->products_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, sku, name, price, sale_price, stock_status, categories, short_description, attributes
				 FROM {$table}
				 WHERE " . implode( ' OR ', $clauses ) . "
				 LIMIT %d",
				$params
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $rows as $row ) {
			if ( ! $this->product_matches_filters( $row, $filters ) ) {
				continue;
			}
			$result[] = array(
				'product_id'        => (int) $row['product_id'],
				'sku'               => $row['sku'],
				'name'              => $row['name'],
				'price'             => $row['price'],
				'sale_price'        => $row['sale_price'],
				'stock_status'      => $row['stock_status'],
				'categories'        => $row['categories'],
				'short_description' => $row['short_description'],
				'attributes'        => $row['attributes'],
			);
			if ( count( $result ) >= $top_n ) {
				break;
			}
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Gemini client
	// -------------------------------------------------------------------------

	/**
	 * Call the Gemini generateContent endpoint.
	 *
	 * @since 1.0.27
	 * @param array  $contents Conversation contents (role + parts).
	 * @param string $system   System instruction text.
	 * @param array  $tools    Optional tool declarations.
	 * @return array|WP_Error Candidate content array on success.
	 */
	public function call_gemini( $contents, $system = '', $tools = array(), $thinking_budget = 0 ) {
		$api_key = $this->get_google_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_google_key', __( 'Google API key is not configured.', 'dukkan-plugin' ) );
		}

		$payload = array(
			'contents'         => $contents,
			'generationConfig' => array_merge(
				$this->sampling_config(),
				// Gemini takes an integer `thinkingBudget`; Gemma takes no
				// thinking config (it thinks by default). Zero disables thinking.
				$this->thinking_config( $thinking_budget )
			),
		);

		if ( '' !== $system ) {
			$payload['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system ) ),
			);
		}

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $tools;
		}

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model_name() . ':generateContent',
			array(
				'timeout' => 60,
				'headers' => array(
					'x-goog-api-key' => $api_key,
					'Content-Type'   => 'application/json',
				),
				'body'    => $this->json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Dukkan chatbot Gemini transport error: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['candidates'][0]['content'] ) ) {
			$message = __( 'Chat request failed.', 'dukkan-plugin' );
			if ( ! empty( $body['error']['message'] ) ) {
				$message .= ' ' . $body['error']['message'];
			} else {
				$message .= ' HTTP ' . $code;
			}
			error_log( 'Dukkan chatbot Gemini error: ' . $message );
			return new WP_Error( 'gemini_failed', $message );
		}

		// Record usage against the cost meter (thinking tokens are billed as
		// output, so they are summed with candidates tokens).
		if ( ! empty( $body['usageMetadata'] ) ) {
			$input  = isset( $body['usageMetadata']['promptTokenCount'] ) ? (int) $body['usageMetadata']['promptTokenCount'] : 0;
			$output = isset( $body['usageMetadata']['candidatesTokenCount'] ) ? (int) $body['usageMetadata']['candidatesTokenCount'] : 0;
			$output += isset( $body['usageMetadata']['thoughtsTokenCount'] ) ? (int) $body['usageMetadata']['thoughtsTokenCount'] : 0;
			$this->record_chat_usage( $input, $output );
		}

		$content = $body['candidates'][0]['content'];
		if ( isset( $content['parts'] ) ) {
			$content['parts'] = $this->normalize_parts( $content['parts'] );
		}

		return $content;
	}

	/**
	 * Normalise Gemini response parts so they survive a PHP json_encode
	 * round-trip. `json_decode(..., true)` turns an empty JSON object (`{}`)
	 * into an empty PHP array, which then re-encodes as `[]`. Gemini rejects a
	 * `functionCall.args` that is an array with HTTP 400 ("Proto field is not
	 * repeating, cannot start list"), which broke every no-argument tool
	 * (list_categories, get_shipping, lookup_points). Convert empty `args`
	 * back to an empty object.
	 *
	 * @since 1.0.30
	 * @param array $parts Gemini content parts.
	 * @return array
	 */
	private function normalize_parts( $parts ) {
		if ( ! is_array( $parts ) ) {
			return $parts;
		}
		foreach ( $parts as &$part ) {
			if ( isset( $part['functionCall']['args'] ) && is_array( $part['functionCall']['args'] ) && empty( $part['functionCall']['args'] ) ) {
				$part['functionCall']['args'] = new stdClass();
			}
		}
		unset( $part );
		return $parts;
	}

	/**
	 * Stream a Gemini response token-by-token using raw cURL.
	 *
	 * wp_remote_post buffers the entire response body, so true SSE streaming
	 * requires cURL with a write callback. Each `data:` event is a
	 * GenerateContentResponse chunk whose text is a *delta* (not cumulative).
	 *
	 * @since 1.0.29
	 * @param array    $contents Conversation contents (role + parts).
	 * @param string   $system   System instruction text.
	 * @param array    $tools    Optional tool declarations.
	 * @param callable $on_token Optional callback invoked with each text delta.
	 * @return array|WP_Error Array with `text` and `calls` keys on success.
	 */
	private function stream_generate( $contents, $system = '', $tools = array(), $on_token = null, $thinking_budget = 0 ) {
		$api_key = $this->get_google_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_google_key', __( 'Google API key is not configured.', 'dukkan-plugin' ) );
		}

		if ( ! function_exists( 'curl_init' ) ) {
			return new WP_Error( 'no_curl', __( 'cURL is not available on this server.', 'dukkan-plugin' ) );
		}

		$payload = array(
			'contents'         => $contents,
			'generationConfig' => array_merge(
				$this->sampling_config(),
				$this->thinking_config( $thinking_budget )
			),
		);

		if ( '' !== $system ) {
			$payload['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system ) ),
			);
		}

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $tools;
		}

		$text       = '';
		$calls      = array();
		$all_parts  = array();
		$buffer     = '';
		$usage_meta = array();

		$consume = function ( $raw ) use ( &$text, &$calls, &$all_parts, &$usage_meta, $on_token ) {
			foreach ( explode( "\n", $raw ) as $line ) {
				if ( 0 !== strpos( $line, 'data: ' ) ) {
					continue;
				}
				$chunk = json_decode( trim( substr( $line, 6 ) ), true );
				if ( ! is_array( $chunk ) || empty( $chunk['candidates'] ) ) {
					continue;
				}
				// Usage metadata arrives on the final chunk; remember it for the
				// cost meter after the stream finishes.
				if ( ! empty( $chunk['usageMetadata'] ) ) {
					$usage_meta = $chunk['usageMetadata'];
				}
				foreach ( $chunk['candidates'] as $candidate ) {
					$parts = isset( $candidate['content']['parts'] ) ? $candidate['content']['parts'] : array();
					foreach ( $parts as $part ) {
						// Keep every part verbatim so the model's turn can be
						// reconstructed faithfully on the tool round-trip. In
						// "thinking" mode Gemini attaches the `thoughtSignature`
						// to the *text* part (not the functionCall), so we must
						// retain the full part sequence — announcement text +
						// signature + functionCall — or the follow-up call after
						// a tool result can fail and return an empty reply.
						$all_parts[] = $part;
						if ( isset( $part['text'] ) ) {
							// Skip `thought: true` parts (Gemma chain-of-thought)
							// so reasoning never leaks into the streamed reply.
							if ( empty( $part['thought'] ) ) {
								$delta = (string) $part['text'];
								$text .= $delta;
								if ( is_callable( $on_token ) ) {
									call_user_func( $on_token, $delta );
								}
							}
						} elseif ( isset( $part['functionCall'] ) ) {
							$calls[] = $part;
						}
					}
				}
			}
		};

		$ch = curl_init();
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model_name() . ':streamGenerateContent?alt=sse',
				CURLOPT_POST           => true,
				CURLOPT_HTTPHEADER     => array(
					'x-goog-api-key: ' . $api_key,
					'Content-Type: application/json',
					'Accept: text/event-stream',
				),
				CURLOPT_POSTFIELDS     => $this->json_encode( $payload ),
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
				CURLOPT_WRITEFUNCTION  => function ( $ch, $data ) use ( &$buffer, $consume ) {
					// Normalise CRLF -> LF so event boundaries are consistent.
					$buffer .= str_replace( "\r\n", "\n", $data );
					// Split on SSE event boundaries (a blank line terminates an event).
					while ( false !== ( $pos = strpos( $buffer, "\n\n" ) ) ) {
						$event   = substr( $buffer, 0, $pos );
						$buffer  = substr( $buffer, $pos + 2 );
						$consume( $event );
					}
					return strlen( $data );
				},
			)
		);

		$ok   = curl_exec( $ch );
		$err  = curl_error( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );

		// Drain any trailing event without a final blank line.
		if ( '' !== trim( $buffer ) ) {
			$consume( $buffer );
		}

		if ( false === $ok || 200 !== $code ) {
			$message = __( 'Chat request failed.', 'dukkan-plugin' );
			$message .= '' !== $err ? ' ' . $err : ' HTTP ' . $code;
			error_log( 'Dukkan chatbot Gemini stream error: ' . $message );
			return new WP_Error( 'gemini_failed', $message );
		}

		// Record usage against the cost meter (thinking tokens are billed as
		// output, so they are summed with candidates tokens).
		if ( ! empty( $usage_meta ) ) {
			$input  = isset( $usage_meta['promptTokenCount'] ) ? (int) $usage_meta['promptTokenCount'] : 0;
			$output = isset( $usage_meta['candidatesTokenCount'] ) ? (int) $usage_meta['candidatesTokenCount'] : 0;
			$output += isset( $usage_meta['thoughtsTokenCount'] ) ? (int) $usage_meta['thoughtsTokenCount'] : 0;
			$this->record_chat_usage( $input, $output );
		}

		return array(
			'text'  => $text,
			'calls' => $calls,
			'parts' => $this->normalize_parts( $all_parts ),
		);
	}

	/**
	 * JSON-encode a payload with a fallback for invalid UTF-8 byte sequences.
	 *
	 * WooCommerce product names/descriptions can occasionally contain invalid
	 * UTF-8, which makes json_encode() return false and silently break the
	 * request body. This guarantees a valid JSON string is always produced.
	 *
	 * @since 1.0.27
	 * @param mixed $data Data to encode.
	 * @return string
	 */
	private function json_encode( $data ) {
		$json = wp_json_encode( $data );
		if ( false !== $json ) {
			return $json;
		}
		$json = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		return false === $json ? '{}' : $json;
	}

	/**
	 * Concatenate all non-thinking text parts of a Gemini/Gemma content object.
	 *
	 * Gemma 4 emits `thought: true` parts (its reasoning) ahead of the real
	 * answer, so those are skipped to avoid leaking chain-of-thought into the
	 * reply shown to the customer.
	 *
	 * @since 1.0.27
	 * @param array $content Gemini content (role + parts).
	 * @return string
	 */
	private function gemini_extract_text( $content ) {
		$text = '';
		foreach ( (array) ( isset( $content['parts'] ) ? $content['parts'] : array() ) as $part ) {
			if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
				$text .= $part['text'];
			}
		}
		return $text;
	}

	/**
	 * Extract pending function calls from a Gemini content object.
	 *
	 * @since 1.0.27
	 * @param array $content Gemini content (role + parts).
	 * @return array
	 */
	private function gemini_extract_calls( $content ) {
		$calls = array();
		foreach ( (array) ( isset( $content['parts'] ) ? $content['parts'] : array() ) as $part ) {
			if ( isset( $part['functionCall'] ) ) {
				$calls[] = $part['functionCall'];
			}
		}
		return $calls;
	}

	// -------------------------------------------------------------------------
	// Tool definitions + executors
	// -------------------------------------------------------------------------

	/**
	 * Build the available tool definitions.
	 *
	 * @since 1.0.27
	 * @param int $user_id Logged-in user ID (0 for guests).
	 * @return array
	 */
	private function get_tools( $user_id ) {
		$declarations = array();

		// Product search is the core capability and is always available.
		$declarations[] = array(
			'name'        => 'search_products',
			'description' => __( 'Search the store catalog for products matching a query, optionally filtered by price, stock, category or an attribute value. Use whenever the customer asks about products, prices, or availability — apply filters whenever the customer specifies a constraint such as "under $50", "in stock", "size M", "red" or a category.', 'dukkan-plugin' ),
			'parameters'  => array(
				'type'       => 'OBJECT',
				'properties' => array(
					'query'      => array( 'type' => 'STRING', 'description' => __( 'The search query, e.g. "red cotton shirt" or "running shoes".', 'dukkan-plugin' ) ),
					'price_min'  => array( 'type' => 'NUMBER', 'description' => __( 'Optional minimum price.', 'dukkan-plugin' ) ),
					'price_max'  => array( 'type' => 'NUMBER', 'description' => __( 'Optional maximum price.', 'dukkan-plugin' ) ),
					'in_stock'   => array( 'type' => 'BOOLEAN', 'description' => __( 'Set true to show only in-stock products.', 'dukkan-plugin' ) ),
					'category'   => array( 'type' => 'STRING', 'description' => __( 'Optional category name to restrict results to.', 'dukkan-plugin' ) ),
					'attribute'  => array( 'type' => 'STRING', 'description' => __( 'Optional attribute value to match, e.g. "Red", "M", "Cotton".', 'dukkan-plugin' ) ),
				),
				'required'   => array( 'query' ),
			),
		);

		// Single-product details are always available.
		$declarations[] = array(
			'name'        => 'get_product_details',
			'description' => __( 'Get full details for one product: price, stock, SKU, categories, available attributes/variants, description and link. Use when the customer asks a specific question about a single product, e.g. "does it come in size M?" or "what colors are available?".', 'dukkan-plugin' ),
			'parameters'  => array(
				'type'       => 'OBJECT',
				'properties' => array(
					'product_id' => array( 'type' => 'INTEGER', 'description' => __( 'WooCommerce product ID', 'dukkan-plugin' ) ),
				),
				'required'   => array( 'product_id' ),
			),
		);

		// Recommendations are always available.
		$declarations[] = array(
			'name'        => 'recommend_products',
			'description' => __( 'Recommend products similar to a given product. Use when the customer asks for alternatives, "similar items", "what goes with this" or "what else do you have like this".', 'dukkan-plugin' ),
			'parameters'  => array(
				'type'       => 'OBJECT',
				'properties' => array(
					'product_id' => array( 'type' => 'INTEGER', 'description' => __( 'WooCommerce product ID to base recommendations on', 'dukkan-plugin' ) ),
				),
				'required'   => array( 'product_id' ),
			),
		);

		// Cart view is always available (add-to-cart is gated separately).
		$declarations[] = array(
			'name'        => 'get_cart',
			'description' => __( 'Read the customer\'s current cart contents, quantities and subtotal. Use when the customer asks what is in their cart, or before adding/removing items.', 'dukkan-plugin' ),
			'parameters'  => array( 'type' => 'OBJECT', 'properties' => new stdClass(), 'required' => array() ),
		);

		// Shipping information is always available.
		$declarations[] = array(
			'name'        => 'get_shipping',
			'description' => __( 'Get the store\'s shipping methods, costs, and free-shipping threshold. Use when the customer asks about delivery, shipping cost, or delivery time.', 'dukkan-plugin' ),
			'parameters'  => array( 'type' => 'OBJECT', 'properties' => new stdClass(), 'required' => array() ),
		);

		// Category browsing is always available.
		$declarations[] = array(
			'name'        => 'list_categories',
			'description' => __( 'List the store\'s product categories with product counts. Use when the customer asks what categories or sections are available.', 'dukkan-plugin' ),
			'parameters'  => array( 'type' => 'OBJECT', 'properties' => new stdClass(), 'required' => array() ),
		);

		// Semantic category search is always available.
		$declarations[] = array(
			'name'        => 'search_categories',
			'description' => __( 'Semantically search product categories by meaning. Use when the customer asks about a specific kind of category, e.g. "do you have anything for skincare?" or "where are the shoes?".', 'dukkan-plugin' ),
			'parameters'  => array(
				'type'       => 'OBJECT',
				'properties' => array(
					'query' => array( 'type' => 'STRING', 'description' => __( 'The category search query, e.g. "skincare" or "shoes".', 'dukkan-plugin' ) ),
				),
				'required'   => array( 'query' ),
			),
		);

		// Store pages (About, policies, FAQ, shipping info) are always available.
		$declarations[] = array(
			'name'        => 'search_pages',
			'description' => __( 'Search the store\'s informational pages (about, shipping/return policy, FAQ, contact, etc.) by meaning. Use when the customer asks about store policies, delivery terms, returns, or general store information.', 'dukkan-plugin' ),
			'parameters'  => array(
				'type'       => 'OBJECT',
				'properties' => array(
					'query' => array( 'type' => 'STRING', 'description' => __( 'The page search query, e.g. "return policy" or "shipping time".', 'dukkan-plugin' ) ),
				),
				'required'   => array( 'query' ),
			),
		);

		if ( $user_id && $this->get_setting( 'enable_lookup' ) ) {
			$declarations[] = array(
				'name'        => 'lookup_orders',
				'description' => __( 'Get the customer\'s recent orders with date, status, total and items. Optionally pass a query to find a specific order (e.g. "the one with the mascara").', 'dukkan-plugin' ),
				'parameters'  => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'query' => array( 'type' => 'STRING', 'description' => __( 'Optional: what the customer is looking for, e.g. "mascara".', 'dukkan-plugin' ) ),
					),
					'required'   => array(),
				),
			);
			$declarations[] = array(
				'name'        => 'lookup_points',
				'description' => __( 'Get the customer\'s loyalty points balance and its monetary value.', 'dukkan-plugin' ),
				'parameters'  => array( 'type' => 'OBJECT', 'properties' => new stdClass(), 'required' => array() ),
			);
		}

		if ( $this->get_setting( 'enable_add_to_cart' ) ) {
			$declarations[] = array(
				'name'        => 'add_to_cart',
				'description' => __( 'Add a product to the customer\'s shopping cart.', 'dukkan-plugin' ),
				'parameters'  => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'product_id' => array( 'type' => 'INTEGER', 'description' => __( 'WooCommerce product ID', 'dukkan-plugin' ) ),
						'quantity'   => array( 'type' => 'INTEGER', 'description' => __( 'Quantity (default 1)', 'dukkan-plugin' ) ),
					),
					'required'   => array( 'product_id' ),
				),
			);
		}

		// Coupons are always available.
		$declarations[] = array(
			'name'        => 'list_coupons',
			'description' => __( 'List the store\'s currently active coupons/discount codes with their discount. Use when the customer asks about discounts, promo codes, offers, deals, or "do you have any coupons?".', 'dukkan-plugin' ),
			'parameters'  => array( 'type' => 'OBJECT', 'properties' => new stdClass(), 'required' => array() ),
		);
		$declarations[] = array(
			'name'        => 'apply_coupon',
			'description' => __( 'Apply a coupon code to the customer\'s shopping cart. Use when the customer gives a coupon code or asks you to apply a discount.', 'dukkan-plugin' ),
			'parameters'  => array(
				'type'       => 'OBJECT',
				'properties' => array(
					'code' => array( 'type' => 'STRING', 'description' => __( 'The coupon code to apply, e.g. "WELCOME10".', 'dukkan-plugin' ) ),
				),
				'required'   => array( 'code' ),
			),
		);

		if ( empty( $declarations ) ) {
			return array();
		}

		return array(
			array(
				'functionDeclarations' => $declarations,
			),
		);
	}

	/**
	 * Execute a single tool call and return a result string.
	 *
	 * @since 1.0.27
	 * @param string $name   Tool name.
	 * @param array  $args   Tool arguments.
	 * @param int    $user_id Logged-in user ID.
	 * @return string
	 */
	private function execute_tool( $name, $args, $user_id ) {
		switch ( $name ) {
			case 'search_products':
				$query   = isset( $args['query'] ) ? sanitize_text_field( (string) $args['query'] ) : '';
				$filters = array(
					'price_min' => isset( $args['price_min'] ) ? (float) $args['price_min'] : null,
					'price_max' => isset( $args['price_max'] ) ? (float) $args['price_max'] : null,
					'in_stock'  => ! empty( $args['in_stock'] ) ? 1 : 0,
					'category'  => isset( $args['category'] ) ? sanitize_text_field( (string) $args['category'] ) : '',
					'attribute' => isset( $args['attribute'] ) ? sanitize_text_field( (string) $args['attribute'] ) : '',
				);
				return $this->tool_search_products( $query, $filters );
			case 'get_product_details':
				$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
				return $this->tool_get_product_details( $product_id );
			case 'recommend_products':
				$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
				return $this->tool_recommend_products( $product_id );
			case 'get_cart':
				return $this->tool_get_cart();
			case 'get_shipping':
				return $this->tool_get_shipping();
			case 'list_categories':
				return $this->tool_list_categories();
			case 'search_categories':
				$query = isset( $args['query'] ) ? sanitize_text_field( (string) $args['query'] ) : '';
				return $this->tool_search_categories( $query );
			case 'search_pages':
				$query = isset( $args['query'] ) ? sanitize_text_field( (string) $args['query'] ) : '';
				return $this->tool_search_pages( $query );
			case 'lookup_orders':
				$query = isset( $args['query'] ) ? sanitize_text_field( (string) $args['query'] ) : '';
				return $this->tool_lookup_orders( $user_id, $query );
			case 'lookup_points':
				return $this->tool_lookup_points( $user_id );
			case 'add_to_cart':
				$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
				$quantity   = isset( $args['quantity'] ) ? max( 1, absint( $args['quantity'] ) ) : 1;
				return $this->tool_add_to_cart( $product_id, $quantity );
			case 'list_coupons':
				return $this->tool_list_coupons();
			case 'apply_coupon':
				$code = isset( $args['code'] ) ? sanitize_text_field( (string) $args['code'] ) : '';
				return $this->tool_apply_coupon( $code );
			default:
				return __( 'Unknown tool.', 'dukkan-plugin' );
		}
	}

	/**
	 * Search the catalog and remember the results for widget rendering.
	 *
	 * @since 1.0.27
	 * @param string $query Search query.
	 * @param array  $filters Structured filters (price_min, price_max, in_stock, category, attribute).
	 * @return string
	 */
	private function tool_search_products( $query, $filters = array() ) {
		if ( '' === $query ) {
			return __( 'No query provided.', 'dukkan-plugin' );
		}

		// Translate non-English queries to the catalog language (English)
		// before searching, so an Arabic query like "فاونديشن" matches the
		// English product "Foundation".
		$query = $this->translate_query_to_english( $query );

		// Normalize filters: keep only recognised, non-empty keys.
		$clean = array();
		foreach ( array( 'price_min', 'price_max', 'in_stock', 'category', 'attribute' ) as $key ) {
			if ( isset( $filters[ $key ] ) && '' !== $filters[ $key ] && null !== $filters[ $key ] ) {
				$clean[ $key ] = $filters[ $key ];
			}
		}

		$products = $this->search_products( $query, 6, $clean );
		$this->last_products = $products;

		return $this->format_product_context( $products );
	}

	/**
	 * Translate a search query to English when it contains non-Latin script.
	 *
	 * The catalog is typically indexed in English, so Arabic (or other
	 * non-Latin) queries would otherwise never match. Uses a lightweight
	 * Gemini call to translate, then falls back to the original query if
	 * translation fails.
	 *
	 * @since 1.0.27
	 * @param string $query Search query.
	 * @return string
	 */
	private function translate_query_to_english( $query ) {
		// Quick bail: already Latin script (English or transliterated).
		if ( ! preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $query ) ) {
			return $query;
		}

		$result = $this->call_gemini(
			array(
				array(
					'role'  => 'user',
					'parts' => array(
						array(
							'text' => "Translate this product search query into English. Keep product and category names natural and concise. Reply with only the English translation, no explanation.\n\nQuery: " . $query,
						),
					),
				),
			),
			'',
			array()
		);

		if ( is_wp_error( $result ) ) {
			return $query;
		}

		$translated = trim( $this->gemini_extract_text( $result ) );
		if ( '' === $translated ) {
			return $query;
		}

		// Guard against the model echoing the prompt instead of translating.
		if ( preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $translated ) ) {
			return $query;
		}

		return $translated;
	}

	/**
	 * Look up a customer's recent orders.
	 *
	 * @since 1.0.27
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function tool_lookup_orders( $user_id, $query = '' ) {
		$lines = $this->search_orders( $user_id, $query, 5 );

		if ( empty( $lines ) ) {
			return __( 'No orders found.', 'dukkan-plugin' );
		}

		return __( 'Orders:', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	/**
	 * Look up a customer's loyalty points balance.
	 *
	 * @since 1.0.27
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function tool_lookup_points( $user_id ) {
		$balance = (int) get_user_meta( $user_id, '_dukkan_loyalty_points', true );

		$value = 0.0;
		$loyalty_settings = get_option( 'dukkan_loyalty_settings', array() );
		$value_per_point  = isset( $loyalty_settings['value_per_point'] ) ? (float) $loyalty_settings['value_per_point'] : 0.01;
		if ( $value_per_point > 0 ) {
			$value = round( $balance * $value_per_point, wc_get_price_decimals() );
		}

		return sprintf(
			__( 'Balance: %1$d points (worth %2$s).', 'dukkan-plugin' ),
			$balance,
			html_entity_decode( wp_strip_all_tags( wc_price( $value ) ) )
		);
	}

	/**
	 * Add a product to the cart.
	 *
	 * @since 1.0.27
	 * @param int $product_id Product ID.
	 * @param int $quantity   Quantity.
	 * @return string
	 */
	private function tool_add_to_cart( $product_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return __( 'This product is not available to add to cart.', 'dukkan-plugin' );
		}

		$added = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( $added ) {
			return sprintf( __( 'Added %1$d x %2$s to the cart.', 'dukkan-plugin' ), $quantity, $product->get_name() );
		}

		return __( 'Could not add the product to the cart.', 'dukkan-plugin' );
	}

	/**
	 * Fetch full details for a single product.
	 *
	 * Lets the assistant answer specific questions about one product —
	 * attributes/variants, stock, price, description and link — that the
	 * generic search context does not carry in enough depth.
	 *
	 * @since 1.0.34
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function tool_get_product_details( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return __( 'Product not found.', 'dukkan-plugin' );
		}

		// Surface a card for this product too.
		$this->last_products = array(
			array(
				'product_id'        => $product_id,
				'sku'               => $product->get_sku(),
				'name'              => $product->get_name(),
				'price'             => (float) $product->get_price(),
				'sale_price'        => (float) $product->get_sale_price(),
				'stock_status'      => $product->get_stock_status(),
				'categories'        => '',
				'short_description' => $product->get_short_description(),
				'attributes'        => $this->product_attributes_text( $product ),
			)
		);

		$price = $product->get_sale_price() ? $product->get_sale_price() : $product->get_price();
		$lines = array(
			sprintf( __( 'Name: %s', 'dukkan-plugin' ), $product->get_name() ),
			sprintf( __( 'Price: %s', 'dukkan-plugin' ), html_entity_decode( wp_strip_all_tags( wc_price( $price ) ) ) ),
			sprintf( __( 'Stock: %s', 'dukkan-plugin' ), $product->get_stock_status() ),
		);

		if ( $product->get_sku() ) {
			$lines[] = sprintf( __( 'SKU: %s', 'dukkan-plugin' ), $product->get_sku() );
		}

		$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$lines[] = sprintf( __( 'Categories: %s', 'dukkan-plugin' ), implode( ', ', $cats ) );
		}

		$attrs = $this->product_attributes_text( $product );
		if ( '' !== $attrs ) {
			$lines[] = sprintf( __( 'Attributes: %s', 'dukkan-plugin' ), $attrs );
		}

		$desc = $product->get_short_description();
		if ( '' !== $desc ) {
			$lines[] = sprintf( __( 'Description: %s', 'dukkan-plugin' ), wp_strip_all_tags( $desc ) );
		}

		$lines[] = sprintf( __( 'Link: %s', 'dukkan-plugin' ), get_permalink( $product_id ) );

		return implode( "\n", $lines );
	}

	/**
	 * Read the customer's current cart contents.
	 *
	 * @since 1.0.34
	 * @return string
	 */
	private function tool_get_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return __( 'The cart is not available right now.', 'dukkan-plugin' );
		}

		$cart = WC()->cart;
		if ( $cart->is_empty() ) {
			return __( 'The cart is currently empty.', 'dukkan-plugin' );
		}

		$lines = array();
		foreach ( $cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data'] : null;
			if ( ! $product ) {
				continue;
			}
			$name     = method_exists( $product, 'get_name' ) ? $product->get_name() : __( 'Product', 'dukkan-plugin' );
			$qty      = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			$line_tot = isset( $item['line_total'] ) ? (float) $item['line_total'] : 0.0;
			$lines[]  = sprintf( '%1$d x %2$s — %3$s', $qty, $name, html_entity_decode( wp_strip_all_tags( wc_price( $line_tot ) ) ) );
		}

		$summary = sprintf(
			__( 'Cart has %1$d item(s), subtotal %2$s.', 'dukkan-plugin' ),
			$cart->get_cart_contents_count(),
			html_entity_decode( wp_strip_all_tags( $cart->get_cart_subtotal() ) )
		);

		return $summary . "\n" . implode( "\n", $lines );
	}

	/**
	 * List the store's currently active coupons/discount codes.
	 *
	 * @since 1.0.34
	 * @return string
	 */
	private function tool_list_coupons() {
		if ( ! function_exists( 'WC' ) ) {
			return __( 'Coupons are not available right now.', 'dukkan-plugin' );
		}

		$posts = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$lines = array();
		foreach ( $posts as $post ) {
			if ( ! class_exists( 'WC_Coupon' ) ) {
				continue;
			}
			$coupon = new WC_Coupon( $post->ID );
			if ( ! $coupon->get_id() ) {
				continue;
			}

			// Skip coupons that are currently invalid (expired, usage limit
			// reached, or not yet active).
			if ( ! $coupon->is_valid() ) {
				continue;
			}

			$code  = $coupon->get_code();
			$type  = $coupon->get_discount_type();
			$value = $coupon->get_amount();

			if ( 'percent' === $type ) {
				$desc = sprintf( __( '%1$s%% off', 'dukkan-plugin' ), (float) $value );
			} elseif ( 'fixed_cart' === $type ) {
				$desc = sprintf( __( '%1$s off your cart', 'dukkan-plugin' ), html_entity_decode( wp_strip_all_tags( wc_price( $value ) ) ) );
			} elseif ( 'fixed_product' === $type ) {
				$desc = sprintf( __( '%1$s off per item', 'dukkan-plugin' ), html_entity_decode( wp_strip_all_tags( wc_price( $value ) ) ) );
			} elseif ( 'free_shipping' === $type ) {
				$desc = __( 'Free shipping', 'dukkan-plugin' );
			} else {
				$desc = $type;
			}

			$lines[] = sprintf( '%1$s — %2$s', $code, $desc );
		}

		if ( empty( $lines ) ) {
			return __( 'There are no active coupons right now.', 'dukkan-plugin' );
		}

		return __( 'Active coupons (mention the code to the customer):', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	/**
	 * Apply a coupon code to the customer's cart.
	 *
	 * @since 1.0.34
	 * @param string $code Coupon code.
	 * @return string
	 */
	private function tool_apply_coupon( $code ) {
		if ( '' === $code ) {
			return __( 'No coupon code provided.', 'dukkan-plugin' );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return __( 'The cart is not available right now.', 'dukkan-plugin' );
		}

		$applied = WC()->cart->apply_coupon( $code );
		if ( is_wp_error( $applied ) ) {
			return sprintf( __( 'Could not apply that coupon: %s', 'dukkan-plugin' ), $applied->get_error_message() );
		}

		return sprintf( __( 'Coupon %s applied to the cart.', 'dukkan-plugin' ), $code );
	}

	/**
	 * Recommend similar products to a given product.
	 *
	 * Uses embedding nearest-neighbours (falling back to shared categories) so
	 * the assistant can suggest complements and alternatives.
	 *
	 * @since 1.0.34
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function tool_recommend_products( $product_id ) {
		global $wpdb;

		$base = $wpdb->get_row( $wpdb->prepare( "SELECT product_id, embedding, categories FROM {$this->products_table()} WHERE product_id = %d", (int) $product_id ), ARRAY_A );

		$rows = $wpdb->get_results( "SELECT product_id, sku, name, price, sale_price, stock_status, categories, short_description, attributes, embedding FROM {$this->products_table()} WHERE product_id != " . (int) $product_id . " AND embedding IS NOT NULL", ARRAY_A );

		$similar = array();

		$base_vec = ( $base && isset( $base['embedding'] ) ) ? json_decode( $base['embedding'], true ) : null;

		if ( is_array( $base_vec ) && ! empty( $rows ) ) {
			// Semantic nearest-neighbours.
			$scored = array();
			foreach ( $rows as $row ) {
				$stored = json_decode( $row['embedding'], true );
				if ( ! is_array( $stored ) ) {
					continue;
				}
				$scored[] = array(
					'row'   => $row,
					'score' => $this->cosine_similarity( $base_vec, $stored ),
				);
			}
			usort( $scored, function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			} );
			foreach ( array_slice( $scored, 0, 4 ) as $entry ) {
				$similar[] = $entry['row'];
			}
		} elseif ( $base && ! empty( $base['categories'] ) && empty( $similar ) ) {
			// Fallback: shared categories.
			$cats = array_filter( array_map( 'trim', explode( ',', (string) $base['categories'] ) ) );
			foreach ( $rows as $row ) {
				foreach ( $cats as $cat ) {
					if ( '' !== $cat && false !== stripos( (string) $row['categories'], $cat ) ) {
						$similar[] = $row;
						break;
					}
				}
				if ( count( $similar ) >= 4 ) {
					break;
				}
			}
		}

		if ( empty( $similar ) ) {
			return __( 'No similar products found.', 'dukkan-plugin' );
		}

		$this->last_products = array_map( function ( $row ) {
			return array(
				'product_id'        => (int) $row['product_id'],
				'sku'               => $row['sku'],
				'name'              => $row['name'],
				'price'             => $row['price'],
				'sale_price'        => $row['sale_price'],
				'stock_status'      => $row['stock_status'],
				'categories'        => $row['categories'],
				'short_description' => $row['short_description'],
				'attributes'        => $row['attributes'],
			);
		}, $similar );

		return $this->format_product_context( $this->last_products );
	}

	/**
	 * Gather the store's shipping methods and costs for the assistant.
	 *
	 * Reads enabled shipping zones/methods and formats their titles and flat
	 * costs, plus a cart-aware "free shipping" hint so the answer is
	 * personalised to what the customer currently has in their cart.
	 *
	 * @since 1.0.29
	 * @return string
	 */
	private function tool_get_shipping() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return __( 'Shipping information is not available right now.', 'dukkan-plugin' );
		}

		$lines       = array();
		$free_min    = 0.0;
		$found_any   = false;

		// All configured zones (locations not covered fall under zone 0).
		$zones = array();
		foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
			$zone_id = isset( $zone_data['zone_id'] ) ? $zone_data['zone_id'] : ( isset( $zone_data['id'] ) ? $zone_data['id'] : 0 );
			$zones[] = new WC_Shipping_Zone( $zone_id );
		}
		$zones[] = new WC_Shipping_Zone( 0 ); // "Locations not covered by your other zones".

		foreach ( $zones as $zone ) {
			if ( ! $zone instanceof WC_Shipping_Zone ) {
				continue;
			}
			$zone_name = $zone->get_zone_name();
			$methods   = $zone->get_shipping_methods( true ); // enabled only.

			foreach ( $methods as $method ) {
				if ( ! is_object( $method ) || ! method_exists( $method, 'is_enabled' ) ) {
					continue;
				}
				$found_any = true;

				$title = method_exists( $method, 'get_title' ) ? $method->get_title() : ( isset( $method->method_title ) ? $method->method_title : $method->id );
				$cost  = '';

				switch ( $method->id ) {
					case 'flat_rate':
						$cost = $method->get_option( 'cost', '0' );
						break;
					case 'free_shipping':
						$min = (float) $method->get_option( 'min_amount', '0' );
						if ( $min > 0 ) {
							$cost     = sprintf( __( 'Free over %s', 'dukkan-plugin' ), html_entity_decode( wp_strip_all_tags( wc_price( $min ) ) ) );
							$free_min = ( $free_min > 0 ) ? min( $free_min, $min ) : $min;
						} else {
							$cost = __( 'Free', 'dukkan-plugin' );
						}
						break;
					case 'local_pickup':
						$cost = __( 'Pickup', 'dukkan-plugin' );
						break;
					default:
						$cost = $method->get_option( 'cost', '' );
						break;
				}

				$lines[] = sprintf( '%1$s — %2$s%3$s', $title, $zone_name, ( '' !== $cost ? ' — ' . $cost : '' ) );
			}
		}

		if ( ! $found_any ) {
			return __( 'No shipping methods are configured yet.', 'dukkan-plugin' );
		}

		// Cart-aware free-shipping hint.
		if ( $free_min > 0 && null !== WC()->cart && method_exists( WC()->cart, 'get_subtotal' ) ) {
			$subtotal  = (float) WC()->cart->get_subtotal();
			$remaining = $free_min - $subtotal;
			if ( $remaining > 0 ) {
				$lines[] = sprintf(
					__( 'Current cart subtotal is %1$s — add %2$s more to unlock free shipping.', 'dukkan-plugin' ),
					html_entity_decode( wp_strip_all_tags( wc_price( $subtotal ) ) ),
					html_entity_decode( wp_strip_all_tags( wc_price( $remaining ) ) )
				);
			} else {
				$lines[] = __( 'The current cart qualifies for free shipping.', 'dukkan-plugin' );
			}
		}

		return __( 'Shipping methods (zone — cost):', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	/**
	 * List the store's product categories with product counts.
	 *
	 * @since 1.0.29
	 * @return string
	 */
	private function tool_list_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 30,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return __( 'No product categories found.', 'dukkan-plugin' );
		}

		$lines = array();
		foreach ( $terms as $term ) {
			$lines[] = sprintf( '%1$s (%2$d)', $term->name, (int) $term->count );

			// Include one level of child categories for a richer answer.
			$children = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'parent'     => $term->term_id,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 10,
				)
			);
			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				foreach ( $children as $child ) {
					$lines[] = sprintf( '  - %1$s (%2$d)', $child->name, (int) $child->count );
				}
			}
		}

		return __( 'Product categories:', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	/**
	 * Semantically search product categories for the assistant.
	 *
	 * @since 1.0.29
	 * @param string $query Search query.
	 * @return string
	 */
	private function tool_search_categories( $query ) {
		if ( '' === $query ) {
			return __( 'No query provided.', 'dukkan-plugin' );
		}

		$query = $this->translate_query_to_english( $query );
		$terms = $this->search_categories( $query, 6 );

		if ( empty( $terms ) ) {
			return __( '(No matching categories found.)', 'dukkan-plugin' );
		}

		$lines = array();
		foreach ( $terms as $term ) {
			$lines[] = sprintf( '- %1$s (%2$d products)', $term['name'], (int) $term['count'] );
		}

		return __( 'Matching categories:', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	/**
	 * Semantically search store pages (About, policies, FAQ, etc.) for the
	 * assistant, returning a title, excerpt and link for each match.
	 *
	 * @since 1.0.30
	 * @param string $query Search query.
	 * @return string
	 */
	private function tool_search_pages( $query ) {
		if ( '' === $query ) {
			return __( 'No query provided.', 'dukkan-plugin' );
		}

		$results = $this->search_pages( $query, 3 );
		if ( empty( $results ) ) {
			return __( '(No matching store pages found.)', 'dukkan-plugin' );
		}

		$lines = array();
		foreach ( $results as $p ) {
			$excerpt = wp_trim_words( (string) $p['content'], 45, '…' );
			$lines[] = sprintf( '- %1$s — %2$s — %3$s', $p['title'], $excerpt, $p['permalink'] );
		}

		return __( 'Relevant store pages (answer from this content and share the link):', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	// -------------------------------------------------------------------------
	// Prompt assembly
	// -------------------------------------------------------------------------

	/**
	 * Build the language instruction.
	 *
	 * @since 1.0.27
	 * @return string
	 */
	private function language_instruction() {
		$mode = $this->get_setting( 'language' );

		if ( 'fixed' === $mode ) {
			$lang = $this->get_setting( 'fixed_language' );
			return sprintf( __( 'Always reply in language code "%1$s".', 'dukkan-plugin' ), $lang );
		}

		if ( 'site' === $mode ) {
			$locale = get_locale();
			return sprintf( __( 'Reply in the site language (locale "%1$s").', 'dukkan-plugin' ), $locale );
		}

		// Auto.
		return __( 'Always reply in the exact same language the customer writes in. If the customer writes in Arabic, answer entirely in Arabic; if the customer writes in English, answer entirely in English. Never switch languages and never mix languages in a single reply.', 'dukkan-plugin' );
	}

	/**
	 * Whether the assistant should speak Arabic for this message.
	 *
	 * Centralizes the language decision so the Arabic-dialect instruction is
	 * only injected when the reply is actually going to be Arabic (previously it
	 * was always present, which biased the model toward Arabic even for English
	 * customers).
	 *
	 * @since 1.0.33
	 * @param string $message The customer message (used in 'auto' mode).
	 * @return bool
	 */
	private function should_speak_arabic( $message ) {
		$mode = $this->get_setting( 'language' );

		if ( 'fixed' === $mode ) {
			$lang = strtolower( trim( (string) $this->get_setting( 'fixed_language' ) ) );
			return 'ar' === $lang || 0 === strpos( $lang, 'ar' );
		}

		if ( 'site' === $mode ) {
			return 0 === strpos( strtolower( get_locale() ), 'ar' );
		}

		// Auto — match the customer.
		return $this->is_arabic_text( (string) $message );
	}

	/**
	 * Build the tone instruction.
	 *
	 * @since 1.0.27
	 * @return string
	 */
	private function tone_instruction() {
		switch ( $this->get_setting( 'tone' ) ) {
			case 'official':
				return __( 'Speak formally and professionally. Use full sentences, no slang or emojis.', 'dukkan-plugin' );
			case 'casual':
				return __( 'Speak in a relaxed, conversational way, like a helpful shop assistant.', 'dukkan-plugin' );
			case 'fun':
				return __( 'Be upbeat and playful. Use light humor and emojis where appropriate.', 'dukkan-plugin' );
			case 'friendly':
			default:
				return __( 'Be friendly and helpful. Keep answers clear and concise.', 'dukkan-plugin' );
		}
	}

	/**
	 * Build the Jordanian Arabic dialect instruction.
	 *
	 * @since 1.0.29
	 * @return string
	 */
	private function arabic_dialect_instruction() {
		return __( 'When replying in Arabic, speak natural Jordanian (Amman) dialect, exactly like a close, helpful, fun Jordanian friend. Feel free to use elegant, common Jordanian expressions, friendly fillers, and light humor naturally on your own, wherever they fit — do not limit yourself to a fixed list. Style guide: use "بدي" instead of "أريد"; use "كمان شوي" for time; greet with "شو أخبارك" or "كيف حالك"; use "مش" for negation (e.g. "مش عارف", "مش مشكلة"). Keep sentences short, relaxed, and conversational. Greet warmly only on the first message of the conversation — do not repeat a greeting on follow-up replies. Address the customer in a gender-neutral way: never use feminine forms like "تعرفي", "جاهزة", "لقيتلك", "بدك" or assume the customer\'s gender unless they clearly state it.', 'dukkan-plugin' );
	}

	/**
	 * Whether the given text contains Arabic script.
	 *
	 * @since 1.0.29
	 * @param string $text Text to test.
	 * @return bool
	 */
	private function is_arabic_text( $text ) {
		return (bool) preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', (string) $text );
	}

	/**
	 * Resolve the thinking budget for a customer message.
	 *
	 * Arabic queries get extra reasoning time (Gemini "thinking") so the
	 * assistant reasons harder before searching — this fixes cases where an
	 * Arabic product request was answered with "not found" because the model
	 * jumped to a conclusion instead of working out the right search terms.
	 *
	 * @since 1.0.29
	 * @param string $message Customer message.
	 * @return int Thinking budget in tokens (0 = no thinking).
	 */
	private function thinking_budget_for( $message ) {
		$arabic = $this->is_arabic_text( $message );

		// Complex requests — comparisons, filters, preferences, multi-part
		// questions — get extra reasoning so the model resolves constraints
		// instead of jumping to a guess. Detected across English and Arabic.
		$complex = (bool) preg_match(
			'/compare|vs\.?|versus|cheaper|cheapest|under|over|between|budget|within|which one|better|best|difference|recommend|suggest|similar|both|or does|should i|ما الفرق|الفرق|أيهما|الأفضل|الأرخص|أرخص|أقل من|أكثر من|بين|ضمن|ميزانية|انصحني|اقترح|مشابه|تشبه/i',
			(string) $message
		);

		if ( $arabic ) {
			return $complex ? 3072 : 2048;
		}

		return $complex ? 1536 : 768;
	}

	/**
	 * Format retrieved products into a compact context block.
	 *
	 * @since 1.0.27
	 * @param array $products Retrieved products.
	 * @return string
	 */
	private function format_product_context( $products ) {
		if ( empty( $products ) ) {
			return __( '(No matching products found in the catalog.)', 'dukkan-plugin' );
		}

		$lines = array();
		foreach ( $products as $p ) {
			$price = $p['sale_price'] && $p['sale_price'] < $p['price'] ? $p['sale_price'] : $p['price'];
			$attrs = isset( $p['attributes'] ) && '' !== trim( (string) $p['attributes'] ) ? ' | ' . $p['attributes'] : '';
			$lines[] = sprintf(
				'- %1$s (ID %2$d, SKU %3$s) — %4$s — %5$s — %6$s%7$s',
				$p['name'],
				$p['product_id'],
				$p['sku'],
				html_entity_decode( wp_strip_all_tags( wc_price( $price ) ) ),
				$p['stock_status'],
				$p['categories'],
				$attrs
			);
		}

		return __( 'Relevant products from this store (use these for recommendations and pricing; do not invent other products):', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	/**
	 * Assemble the system prompt.
	 *
	 * No retrieval happens here — product search is a model-controlled tool,
	 * so the assistant only receives products it explicitly searched for.
	 *
	 * @since 1.0.27
	 * @return string
	 */
	private function build_system_prompt( $message = '' ) {
		$store_instructions = $this->get_setting( 'system_prompt' );
		if ( '' === $store_instructions ) {
			$store_instructions = __( 'You are a helpful shopping assistant for an online store. Help customers discover products, compare options, and answer questions about pricing and availability. When the customer asks about orders or loyalty points and those tools are available, use them.', 'dukkan-plugin' );
		}

		// Only include the Arabic dialect guide when the reply should be Arabic,
		// so English customers are never pulled toward Arabic.
		$dialect_instruction = $this->should_speak_arabic( $message ) ? $this->arabic_dialect_instruction() : '';

		return implode(
			"\n\n",
			array_filter(
				array(
					$store_instructions,
					$this->tone_instruction(),
					$this->language_instruction(),
					$dialect_instruction,
					__( 'Call the appropriate tool in the same turn, before replying. Never greet, thank, or say you are "searching", "checking", or "looking" first — run the tool immediately and only speak once you have its result. If a customer request needs a tool (products, categories, shipping, orders, points, store pages), always execute that tool now rather than promising to look later.', 'dukkan-plugin' ),
					__( 'Always use the search_products tool whenever the customer asks about products, prices, or availability. Recommend only products returned by that tool — never invent products, prices, or availability.', 'dukkan-plugin' ),
					__( 'If the customer uploads a photo, describe what the item is and immediately use search_products with a short query describing it (e.g. its type, color, style) so you can find it or the closest similar products in the store.', 'dukkan-plugin' ),
					__( 'Use the search_products tool\'s filters to honour the customer\'s constraints instead of approximating them: pass price_min/price_max for "under/over/between", in_stock=true for "in stock", category for "in the X section", and attribute for a color/size/material ("size M", "red"). If a filter yields no results, say so honestly rather than listing unfiltered products.', 'dukkan-plugin' ),
					__( 'Use get_product_details when the customer asks a specific question about a single product (variants, sizes, colors, stock, exact price) instead of re-searching the whole catalog.', 'dukkan-plugin' ),
					__( 'Use recommend_products when the customer asks for alternatives or "similar items". Use get_cart to read the current cart before adding items or when the customer asks what is in their cart.', 'dukkan-plugin' ),
					__( 'Keep the conversation context. When the customer sends a short follow-up or correction that depends on their previous message (for example "no, from Maybelline", "just the blue one", "cheaper"), carry forward the earlier request — the product type, filters, and question — and apply only the new change. If they first asked for mascara and then say "from Maybelline", search for Maybelline mascara, not every Maybelline product.', 'dukkan-plugin' ),
					__( 'Use the get_shipping tool for any shipping or delivery question. Use the list_categories tool when the customer asks what categories or sections the store offers, and the search_categories tool when they ask about a specific kind of category.', 'dukkan-plugin' ),
					__( 'Use the search_pages tool when the customer asks about store policies, returns, shipping terms, contact details, or any general store information. Answer from the returned page content and share the page link.', 'dukkan-plugin' ),
					__( 'Ground every factual answer with a citation. When the information comes from a store page, include a markdown link to that page inline, e.g. "as explained in our [returns policy](https://…)". Never state a policy, price, shipping term, or deadline as fact unless a tool returned it — if you do not have the data, say you are not sure instead of guessing.', 'dukkan-plugin' ),
					__( 'Use the list_coupons tool when the customer asks about discounts, promo codes, offers, deals or coupons, and tell them the code. Use the apply_coupon tool when the customer provides a code or asks you to apply a discount; report whether it succeeded.', 'dukkan-plugin' ),
					__( 'Products include attributes (e.g. color, size, material) and their values. Reason about these when the customer asks for a specific variant such as "blue in size M", and confirm availability from the returned attribute values.', 'dukkan-plugin' ),
					__( 'Product cards are shown to the customer automatically, so never list products yourself. Never print a bullet-point list of products, prices, SKUs, or descriptions in your text reply — mention at most 1 or 2 product names inline as part of a short summary. Keep every reply short (1-3 sentences). End with at most one short question or offer to help, never two.', 'dukkan-plugin' ),
				)
			)
		);
	}

	// -------------------------------------------------------------------------
	// Message processing
	// -------------------------------------------------------------------------

	/**
	 * Process a chat turn: retrieval + Gemini + tools, returning the reply.
	 *
	 * @since 1.0.27
	 * @param array  $history Prior messages (array of {role, content}).
	 * @param string $message The new user message.
	 * @param int    $user_id Logged-in user ID.
	 * @param string $image   Optional image data URL (image search).
	 * @return array{reply: string, products: array, handoff: bool}
	 */
	public function process_message( $history, $message, $user_id = 0, $image = '' ) {
		$system = $this->build_system_prompt( $message );
		$this->last_products = array();

		$contents = $this->build_contents( $history, $message, $image );
		$tools    = $this->get_tools( $user_id );
		$thinking = $this->thinking_budget_for( $message );

		$result = $this->call_gemini( $contents, $system, $tools, $thinking );
		if ( is_wp_error( $result ) ) {
			error_log( 'Dukkan chatbot process error: ' . $result->get_error_message() );
			$reply = __( 'Sorry, I am having trouble right now. Please try again shortly.', 'dukkan-plugin' );
			// Surface the real cause to admins only, for fast diagnosis.
			if ( current_user_can( 'manage_options' ) ) {
				$reply .= ' [' . $result->get_error_message() . ']';
			}
			return array(
				'reply'    => $reply,
				'products' => array(),
				'has_more' => false,
				'handoff'  => false,
			);
		}

		// Function-calling loop: let the model chain multiple tool calls
		// (e.g. search_products → filter → recommend → compare → add_to_cart)
		// with a safety guard against runaway loops.
		$guard = 0;
		$calls = $this->gemini_extract_calls( $result );
		while ( ! empty( $calls ) && $guard < 6 ) {
			$guard++;
			$contents[] = $result;

			$responses = array();
			foreach ( $calls as $call ) {
				$name        = isset( $call['name'] ) ? $call['name'] : '';
				$args        = isset( $call['args'] ) && is_array( $call['args'] ) ? $call['args'] : array();
				$tool_result = $this->execute_tool( $name, $args, $user_id );

				$responses[] = array(
					'functionResponse' => array(
						'name'     => $name,
						'response' => array( 'result' => $tool_result ),
					),
				);
			}

			$contents[] = array(
				'role'  => 'user',
				'parts' => $responses,
			);

			$result = $this->call_gemini( $contents, $system, array(), $thinking );
			if ( is_wp_error( $result ) ) {
				break;
			}
			$calls = $this->gemini_extract_calls( $result );
		}

		$reply = is_wp_error( $result ) ? '' : $this->gemini_extract_text( $result );
		if ( '' === $reply ) {
			$reply = __( 'Sorry, I could not generate a response.', 'dukkan-plugin' );
		}

		return $this->finalize_reply( $reply, $message, $history );
	}

	/**
	 * Stream a chat turn token-by-token to a callback.
	 *
	 * Same behaviour as process_message(), but the assistant's final text is
	 * delivered incrementally through $on_token so the UI can render a live
	 * "typing" effect.
	 *
	 * @since 1.0.29
	 * @param array         $history  Prior messages (array of {role, content}).
	 * @param string        $message  The new user message.
	 * @param int           $user_id  Logged-in user ID.
	 * @param callable|null $on_token Callback invoked with each text delta.
	 * @param string        $image    Optional image data URL (image search).
	 * @return array{reply: string, products: array, has_more: bool, handoff: bool}
	 */
	public function process_message_stream( $history, $message, $user_id = 0, $on_token = null, $image = '' ) {
		$system = $this->build_system_prompt( $message );
		$this->last_products = array();

		$contents = $this->build_contents( $history, $message, $image );
		$tools    = $this->get_tools( $user_id );
		$thinking = $this->thinking_budget_for( $message );

		// First call runs WITHOUT streaming: in "thinking" mode the model can
		// emit an "announcement" text ("I'm searching…") before its tool call.
		// We buffer that so it is never shown to the customer; only the final
		// answer (after the tools resolve) is streamed.
		$result = $this->stream_generate( $contents, $system, $tools, null, $thinking );
		if ( is_wp_error( $result ) ) {
			error_log( 'Dukkan chatbot stream error: ' . $result->get_error_message() );
			$reply = __( 'Sorry, I am having trouble right now. Please try again shortly.', 'dukkan-plugin' );
			if ( current_user_can( 'manage_options' ) ) {
				$reply .= ' [' . $result->get_error_message() . ']';
			}
			if ( is_callable( $on_token ) ) {
				call_user_func( $on_token, $reply );
			}
			return array(
				'reply'    => $reply,
				'products' => array(),
				'has_more' => false,
				'handoff'  => false,
			);
		}

		$text       = isset( $result['text'] ) ? (string) $result['text'] : '';
		$calls      = isset( $result['calls'] ) ? $result['calls'] : array();
		$model_turn = isset( $result['parts'] ) ? $result['parts'] : array();

		// Tool loop, streaming the final answer from the last call.
		$guard      = 0;
		$loop_error = '';
		while ( ! empty( $calls ) && $guard < 6 ) {
			$guard++;

			// Reconstruct the model's turn verbatim from the parts Gemini
			// returned. In "thinking" mode the `thoughtSignature` lives on the
			// text part (not the functionCall), so we must echo the full part
			// sequence back — text + signature + functionCall — or the follow-up
			// call after a tool result can fail and return an empty reply.
			$contents[] = array( 'role' => 'model', 'parts' => $model_turn );

			$responses = array();
			foreach ( $calls as $call ) {
				$fc          = isset( $call['functionCall'] ) ? $call['functionCall'] : $call;
				$name        = isset( $fc['name'] ) ? $fc['name'] : '';
				$args        = isset( $fc['args'] ) && is_array( $fc['args'] ) ? $fc['args'] : array();
				$tool_result = $this->execute_tool( $name, $args, $user_id );

				$responses[] = array(
					'functionResponse' => array(
						'name'     => $name,
						'response' => array( 'result' => $tool_result ),
					),
				);
			}
			$contents[] = array( 'role' => 'user', 'parts' => $responses );

			$result = $this->stream_generate( $contents, $system, array(), $on_token, $thinking );
			if ( is_wp_error( $result ) ) {
				error_log( 'Dukkan chatbot stream tool-loop error: ' . $result->get_error_message() );
				$loop_error = $result->get_error_message();
				$text       = '';
				break;
			}
			$text       = isset( $result['text'] ) ? (string) $result['text'] : '';
			$calls      = isset( $result['calls'] ) ? $result['calls'] : array();
			$model_turn = isset( $result['parts'] ) ? $result['parts'] : array();
		}

		// If the model answered directly (no tool call), stream the buffered
		// text now — it was withheld above so "announcement" text never leaks.
		if ( 0 === $guard && '' !== $text && is_callable( $on_token ) ) {
			call_user_func( $on_token, $text );
		}

		if ( '' === $text ) {
			$text = __( 'Sorry, I could not generate a response.', 'dukkan-plugin' );
			// Surface the real cause to admins only, for fast diagnosis.
			if ( current_user_can( 'manage_options' ) && '' !== $loop_error ) {
				$text .= ' [' . $loop_error . ']';
			}
			if ( is_callable( $on_token ) ) {
				call_user_func( $on_token, $text );
			}
		}

		return $this->finalize_reply( $text, $message, $history );
	}

	/**
	 * Build the deduplicated Gemini `contents` array from history + message.
	 *
	 * The client pre-pushes the current message, so we drop the duplicate and
	 * merge consecutive same-role turns to keep strict user/model alternation.
	 *
	 * @since 1.0.29
	 * @param array  $history Prior messages.
	 * @param string $message New user message.
	 * @param string $image   Optional image data URL to attach to the turn.
	 * @return array
	 */
	private function build_contents( $history, $message, $image = '' ) {
		$contents = array();
		$window   = 16;

		if ( is_array( $history ) && count( $history ) > $window ) {
			// Rolling summary: keep the last $window turns verbatim, and condense
			// everything older into a compact "earlier" note so long conversations
			// don't lose their context.
			$older   = array_slice( $history, 0, count( $history ) - $window );
			$summary = $this->summarize_older_turns( $older );
			if ( '' !== $summary ) {
				$this->append_turn( $contents, 'user', $summary );
			}
		}

		$recent = array_slice( (array) $history, -$window );
		foreach ( $recent as $turn ) {
			$role    = isset( $turn['role'] ) && 'user' === $turn['role'] ? 'user' : 'model';
			$content = isset( $turn['content'] ) ? sanitize_text_field( $turn['content'] ) : '';
			if ( '' !== $content ) {
				$this->append_turn( $contents, $role, $content );
			}
		}

		// Ensure the current message is present exactly once, at the end.
		$current = sanitize_text_field( $message );
		$last    = end( $contents );
		$already = false;
		if ( $last && 'user' === $last['role'] ) {
			foreach ( $last['parts'] as $part ) {
				if ( isset( $part['text'] ) && $part['text'] === $current ) {
					$already = true;
					break;
				}
			}
		}
		if ( ! $already ) {
			$this->append_turn( $contents, 'user', $current );
		}

		// Attach an uploaded image to the current user turn (image search).
		$inline = $this->image_inline_part( $image );
		if ( null !== $inline && ! empty( $contents ) ) {
			$last_index = count( $contents ) - 1;
			$contents[ $last_index ]['parts'][] = $inline;
		}

		return $contents;
	}

	/**
	 * Convert a `data:` image URL into a Gemini inline-data part.
	 *
	 * @since 1.0.34
	 * @param string $data_url Data URL, e.g. "data:image/jpeg;base64,....".
	 * @return array|null Inline part array, or null if invalid.
	 */
	private function image_inline_part( $data_url ) {
		$data_url = trim( (string) $data_url );
		if ( '' === $data_url || 0 !== strpos( $data_url, 'data:image/' ) ) {
			return null;
		}

		// Parse "data:<mime>;base64,<data>".
		if ( ! preg_match( '/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $data_url, $m ) ) {
			return null;
		}

		$mime = $m[1];
		$data = $m[2];

		// Only allow image MIME types; cap size at ~3.5MB of raw bytes.
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			return null;
		}
		$decoded = base64_decode( $data, true );
		if ( false === $decoded || strlen( $decoded ) > 3500000 ) {
			return null;
		}

		return array(
			'inlineData' => array(
				'mimeType' => $mime,
				'data'     => $data,
			),
		);
	}

	/**
	 * Condense older conversation turns into a compact context note.
	 *
	 * Deterministic (no extra AI call): joins the customer's earlier messages
	 * into a single "Earlier in the conversation…" line, capped for length, so
	 * the model retains intent without exceeding the prompt budget.
	 *
	 * @since 1.0.34
	 * @param array $turns Older turns.
	 * @return string
	 */
	private function summarize_older_turns( $turns ) {
		$user_msgs = array();
		foreach ( $turns as $turn ) {
			if ( isset( $turn['role'] ) && 'user' === $turn['role'] && isset( $turn['content'] ) && '' !== trim( (string) $turn['content'] ) ) {
				$user_msgs[] = sanitize_text_field( $turn['content'] );
			}
		}

		if ( empty( $user_msgs ) ) {
			return '';
		}

		$user_msgs = array_slice( $user_msgs, -6 );
		$text      = implode( ' | ', $user_msgs );

		if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > 600 ) {
			$text = mb_substr( $text, 0, 600 ) . '…';
		} elseif ( strlen( $text ) > 600 ) {
			$text = substr( $text, 0, 600 ) . '…';
		}

		return __( 'Earlier in the conversation the customer said:', 'dukkan-plugin' ) . ' ' . $text;
	}

	/**
	 * Assemble the final reply payload (handoff + product cards + "view more").
	 *
	 * @since 1.0.29
	 * @param string $reply   Assistant reply text.
	 * @param string $message The triggering user message.
	 * @param array  $history Prior conversation turns (for frustration detection).
	 * @return array
	 */
	private function finalize_reply( $reply, $message, $history = array() ) {
		$handoff = false;
		if ( $this->get_setting( 'enable_handoff' ) ) {
			if ( preg_match( '/human|agent|person|representative|support team|موظف|إنسان|شخص|الدعم/i', $message ) ) {
				$handoff = true;
			} elseif ( $this->customer_is_frustrated( $history ) ) {
				$handoff = true;
			}
		}

		// Show only the first 3 product cards; stash the rest so a "View more"
		// click can fetch them without re-searching.
		$widget  = $this->products_for_widget( $this->last_products );
		$initial = array_slice( $widget, 0, 3 );
		$more    = array_slice( $widget, 3 );
		if ( ! empty( $more ) ) {
			set_transient( 'dukkan_chatbot_more_' . $this->visitor_key(), $more, 10 * MINUTE_IN_SECONDS );
		}

		return array(
			'reply'    => $reply,
			'products' => $initial,
			'has_more' => ! empty( $more ),
			'handoff'  => $handoff,
		);
	}

	/**
	 * Detect customer frustration from the conversation, for smarter handoff.
	 *
	 * Escalates when the customer has repeated "no"/"wrong"/"not what I asked"
	 * several times, or uses strong negative/angry language — a more reliable
	 * signal than the single "human/agent" keyword.
	 *
	 * @since 1.0.34
	 * @param array $history Prior conversation turns.
	 * @return bool
	 */
	private function customer_is_frustrated( $history ) {
		if ( ! is_array( $history ) ) {
			return false;
		}

		$angry   = 0;
		$negated = 0;
		$recent  = array_slice( $history, -8 );

		foreach ( $recent as $turn ) {
			if ( ! isset( $turn['role'] ) || 'user' !== $turn['role'] || ! isset( $turn['content'] ) ) {
				continue;
			}
			$text = (string) $turn['content'];

			if ( preg_match( '/stupid|useless|terrible|worst|bad|waste|useless|angry|frustrat|مستحيل|سيء|سيئة|غبي|غبية|ما في فايدة|بدون فايدة|زفت|خرا|تافه|تافهة/i', $text ) ) {
				$angry++;
			}
			if ( preg_match( '/\b(no|nope|wrong|not|still|again|incorrect|لا|غلط|خطأ|مش هيك|مو هيك|لسا|بعدني|مش صح|مو صح)\b/i', $text ) ) {
				$negated++;
			}
		}

		return $angry >= 2 || $negated >= 3;
	}

	/**
	 * Retrieve (and consume) the deferred product cards for this visitor.
	 *
	 * @since 1.0.27
	 * @return array
	 */
	public function get_more_products() {
		$key  = 'dukkan_chatbot_more_' . $this->visitor_key();
		$more = get_transient( $key );
		if ( ! is_array( $more ) ) {
			return array();
		}
		delete_transient( $key );
		return $more;
	}

	/**
	 * Append a turn to Gemini contents, merging consecutive same-role turns
	 * into a single message so the conversation strictly alternates
	 * user/model (which Gemini requires).
	 *
	 * @since 1.0.27
	 * @param array  $contents Contents array (by reference).
	 * @param string $role     Role ('user' or 'model').
	 * @param string $text     Message text.
	 */
	private function append_turn( &$contents, $role, $text ) {
		$n = count( $contents );
		if ( $n > 0 && isset( $contents[ $n - 1 ]['role'] ) && $contents[ $n - 1 ]['role'] === $role ) {
			$contents[ $n - 1 ]['parts'][] = array( 'text' => $text );
		} else {
			$contents[] = array(
				'role'  => $role,
				'parts' => array( array( 'text' => $text ) ),
			);
		}
	}

	/**
	 * Map retrieved products to the widget card shape.
	 *
	 * @since 1.0.27
	 * @param array $products Retrieved products.
	 * @return array
	 */
	private function products_for_widget( $products ) {
		$out = array();
		foreach ( $products as $p ) {
			$product = wc_get_product( $p['product_id'] );
			if ( ! $product ) {
				continue;
			}
			$out[] = array(
				'id'         => $product->get_id(),
				'name'       => $product->get_name(),
				'price'      => wp_strip_all_tags( wc_price( $product->get_price() ) ),
				'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'permalink'  => get_permalink( $product->get_id() ),
				'purchasable'=> $product->is_purchasable() && $product->is_in_stock(),
			);
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// Handoff + logging
	// -------------------------------------------------------------------------

	/**
	 * Email the support address with a handoff transcript.
	 *
	 * @since 1.0.27
	 * @param string $customer_email Customer email (if provided).
	 * @param array  $history        Conversation history.
	 * @return bool
	 */
	public function handle_handoff( $customer_email, $history ) {
		$to = $this->get_setting( 'support_email' );
		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$lines = array();
		if ( is_array( $history ) ) {
			foreach ( $history as $turn ) {
				$role    = isset( $turn['role'] ) && 'user' === $turn['role'] ? __( 'Customer', 'dukkan-plugin' ) : __( 'Assistant', 'dukkan-plugin' );
				$content = isset( $turn['content'] ) ? $turn['content'] : '';
				$lines[] = $role . ': ' . $content;
			}
		}

		$body  = __( 'A customer requested human assistance.', 'dukkan-plugin' ) . "\n\n";
		if ( $customer_email ) {
			$body .= __( 'Customer email:', 'dukkan-plugin' ) . ' ' . $customer_email . "\n\n";
		}
		$body .= __( 'Transcript:', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );

		return wp_mail( $to, __( '[Dukkan] Chatbot handoff request', 'dukkan-plugin' ), $body );
	}

	/**
	 * Write an exchange to the conversation log.
	 *
	 * @since 1.0.27
	 * @param int    $user_id     User ID (0 for guest).
	 * @param string $visitor_key Anonymous visitor key.
	 * @param string $message     User message.
	 * @param string $reply       Assistant reply.
	 * @param bool   $handoff     Whether a handoff was triggered.
	 */
	public function log_conversation( $user_id, $visitor_key, $message, $reply, $handoff = false ) {
		global $wpdb;
		$this->ensure_tables();

		$wpdb->insert(
			$this->log_table(),
			array(
				'user_id'     => (int) $user_id,
				'visitor_key' => sanitize_text_field( $visitor_key ),
				'message'     => sanitize_text_field( $message ),
				'reply'       => sanitize_textarea_field( $reply ),
				'handoff'     => $handoff ? 1 : 0,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Fetch recent conversation log entries.
	 *
	 * @since 1.0.27
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public function get_recent_logs( $limit = 50 ) {
		global $wpdb;
		$this->ensure_tables();

		$table = $this->log_table();

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT id, user_id, visitor_key, message, reply, handoff, created_at FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
	}

	/**
	 * Test Gemini chat + embeddings connectivity.
	 *
	 * @since 1.0.27
	 * @return array{chat: bool|string, embed: bool|string}
	 */
	public function test_connection() {
		$result = array();

		$chat = $this->call_gemini(
			array( array( 'role' => 'user', 'parts' => array( array( 'text' => 'Say "ok".' ) ) ) ),
			'',
			array()
		);
		$result['chat'] = is_wp_error( $chat ) ? $chat->get_error_message() : true;

		$embed = $this->embed_text( 'ping' );
		$result['embed'] = is_wp_error( $embed ) ? $embed->get_error_message() : true;

		return $result;
	}

	/**
	 * Compute a stable anonymous visitor key from the request.
	 *
	 * @since 1.0.27
	 * @return string
	 */
	public function visitor_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return substr( md5( $ip ), 0, 32 );
	}

	/**
	 * Check and increment the per-IP rate limit.
	 *
	 * @since 1.0.27
	 * @return bool True if allowed.
	 */
	public function rate_limit_ok() {
		$limit = (int) $this->get_setting( 'rate_limit' );
		if ( $limit <= 0 ) {
			return true;
		}

		$key   = 'dukkan_chatbot_rl_' . $this->visitor_key();
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
