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
 * Powers a DeepSeek-based store assistant: semantic product search via OpenAI
 * embeddings, order/loyalty lookups and add-to-cart via function calling,
 * human handoff, and a conversation log. All API keys are held server-side.
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
	 * Default settings.
	 *
	 * @since 1.0.27
	 * @var array
	 */
	protected $defaults = array(
		'enabled'            => 0,
		'deepseek_api_key'   => '',
		'deepseek_model'     => 'deepseek-chat',
		'openai_api_key'     => '',
		'language'           => 'auto',
		'fixed_language'     => 'en',
		'tone'               => 'friendly',
		'system_prompt'      => '',
		'bot_name'           => 'Dukkan Assistant',
		'greeting'           => '',
		'accent_color'       => '#1d4f5f',
		'position'           => 'bottom-right',
		'auto_index'         => 1,
		'enable_lookup'      => 1,
		'enable_add_to_cart' => 1,
		'enable_handoff'     => 1,
		'support_email'      => '',
		'rate_limit'         => 10,
		'memory_mode'        => 'session',
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
		$log             = $this->log_table();

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

		dbDelta( $sql_products );
		dbDelta( $sql_log );
	}

	// -------------------------------------------------------------------------
	// Cron
	// -------------------------------------------------------------------------

	/**
	 * Register a two-day cron interval.
	 *
	 * @since 1.0.27
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_cron_schedule( $schedules ) {
		$schedules['dukkan_every_two_days'] = array(
			'interval' => 2 * DAY_IN_SECONDS,
			'display'  => __( 'Every two days', 'dukkan-plugin' ),
		);
		return $schedules;
	}

	/**
	 * Ensure the reindex cron event is scheduled.
	 *
	 * @since 1.0.27
	 */
	public function schedule_reindex() {
		if ( ! wp_next_scheduled( 'dukkan_chatbot_reindex' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'dukkan_every_two_days', 'dukkan_chatbot_reindex' );
		}
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
	 * Cron callback: rebuild the full product index.
	 *
	 * @since 1.0.27
	 */
	public function cron_reindex() {
		$this->ensure_tables();
		$this->build_full_index();
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

	// -------------------------------------------------------------------------
	// Embeddings (OpenAI)
	// -------------------------------------------------------------------------

	/**
	 * Embed a text into a vector via OpenAI.
	 *
	 * @since 1.0.27
	 * @param string $text Text to embed.
	 * @return array|WP_Error Vector array on success.
	 */
	public function embed_text( $text ) {
		$api_key = $this->get_setting( 'openai_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_openai_key', __( 'OpenAI API key is not configured.', 'dukkan-plugin' ) );
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/embeddings',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => 'text-embedding-3-small',
						'input' => mb_substr( $text, 0, 8000 ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['data'][0]['embedding'] ) ) {
			return new WP_Error( 'embed_failed', __( 'Embedding request failed.', 'dukkan-plugin' ) );
		}

		return $body['data'][0]['embedding'];
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

		return implode( ' | ', array_filter( array_map( 'trim', $parts ) ) );
	}

	/**
	 * Embed and store a single product.
	 *
	 * @since 1.0.27
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function index_product( $product_id ) {
		global $wpdb;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$wpdb->delete( $this->products_table(), array( 'product_id' => $product_id ), array( '%d' ) );
			return false;
		}

		$vector = $this->embed_text( $this->product_embed_text( $product ) );
		if ( is_wp_error( $vector ) ) {
			return false;
		}

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
			'embedding'         => wp_json_encode( $vector ),
			'updated_at'        => current_time( 'mysql', true ),
		);

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->products_table()} WHERE product_id = %d", $product_id ) );

		if ( $existing ) {
			$wpdb->update( $this->products_table(), $row, array( 'product_id' => $product_id ), array( '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $this->products_table(), $row, array( '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ) );
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
	 * Retrieve the current index status.
	 *
	 * @since 1.0.27
	 * @return array
	 */
	public function get_index_status() {
		global $wpdb;

		$table = $this->products_table();
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$meta = get_option( self::INDEX_META_KEY, array() );

		return array(
			'count'      => (int) $count,
			'last_build' => isset( $meta['last_build'] ) ? $meta['last_build'] : '',
		);
	}

	/**
	 * Semantic search over the product index.
	 *
	 * @since 1.0.27
	 * @param string $query User query.
	 * @param int    $top_n Number of results.
	 * @return array
	 */
	public function search_products( $query, $top_n = 6 ) {
		global $wpdb;

		$vector = $this->embed_text( $query );
		if ( is_wp_error( $vector ) ) {
			return array();
		}

		$rows = $wpdb->get_results( "SELECT product_id, sku, name, price, sale_price, stock_status, categories, short_description, embedding FROM {$this->products_table()} WHERE embedding IS NOT NULL", ARRAY_A );

		$scored = array();
		foreach ( $rows as $row ) {
			$stored = json_decode( $row['embedding'], true );
			if ( ! is_array( $stored ) ) {
				continue;
			}
			$score = $this->cosine_similarity( $vector, $stored );
			$scored[] = array(
				'product_id'        => (int) $row['product_id'],
				'sku'               => $row['sku'],
				'name'              => $row['name'],
				'price'             => $row['price'],
				'sale_price'        => $row['sale_price'],
				'stock_status'      => $row['stock_status'],
				'categories'        => $row['categories'],
				'short_description' => $row['short_description'],
				'score'             => $score,
			);
		}

		usort( $scored, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		$result = array();
		foreach ( array_slice( $scored, 0, $top_n ) as $item ) {
			if ( $item['score'] < 0.15 ) {
				continue;
			}
			unset( $item['score'] );
			$result[] = $item;
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// DeepSeek client
	// -------------------------------------------------------------------------

	/**
	 * Call the DeepSeek chat completions endpoint.
	 *
	 * @since 1.0.27
	 * @param array $messages Message array.
	 * @param array $tools    Optional tool definitions.
	 * @return array|WP_Error Assistant message array on success.
	 */
	public function call_deepseek( $messages, $tools = array() ) {
		$api_key = $this->get_setting( 'deepseek_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_deepseek_key', __( 'DeepSeek API key is not configured.', 'dukkan-plugin' ) );
		}

		$payload = array(
			'model'       => $this->get_setting( 'deepseek_model' ),
			'messages'    => $messages,
			'temperature' => 0.4,
			'stream'      => false,
		);

		if ( ! empty( $tools ) ) {
			$payload['tools'] = $tools;
		}

		$response = wp_remote_post(
			'https://api.deepseek.com/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['choices'][0]['message'] ) ) {
			return new WP_Error( 'deepseek_failed', __( 'Chat request failed.', 'dukkan-plugin' ) );
		}

		return $body['choices'][0]['message'];
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
		$tools = array();

		if ( $user_id && $this->get_setting( 'enable_lookup' ) ) {
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'lookup_orders',
					'description' => __( 'Get the customer\'s recent orders with date, status, total and items.', 'dukkan-plugin' ),
					'parameters'  => array( 'type' => 'object', 'properties' => array(), 'required' => array() ),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'lookup_points',
					'description' => __( 'Get the customer\'s loyalty points balance and its monetary value.', 'dukkan-plugin' ),
					'parameters'  => array( 'type' => 'object', 'properties' => array(), 'required' => array() ),
				),
			);
		}

		if ( $this->get_setting( 'enable_add_to_cart' ) ) {
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'add_to_cart',
					'description' => __( 'Add a product to the customer\'s shopping cart.', 'dukkan-plugin' ),
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'product_id' => array( 'type' => 'integer', 'description' => __( 'WooCommerce product ID', 'dukkan-plugin' ) ),
							'quantity'   => array( 'type' => 'integer', 'description' => __( 'Quantity (default 1)', 'dukkan-plugin' ) ),
						),
						'required'   => array( 'product_id' ),
					),
				),
			);
		}

		return $tools;
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
			case 'lookup_orders':
				return $this->tool_lookup_orders( $user_id );
			case 'lookup_points':
				return $this->tool_lookup_points( $user_id );
			case 'add_to_cart':
				$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
				$quantity   = isset( $args['quantity'] ) ? max( 1, absint( $args['quantity'] ) ) : 1;
				return $this->tool_add_to_cart( $product_id, $quantity );
			default:
				return __( 'Unknown tool.', 'dukkan-plugin' );
		}
	}

	/**
	 * Look up a customer's recent orders.
	 *
	 * @since 1.0.27
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function tool_lookup_orders( $user_id ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 5,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		if ( empty( $orders ) ) {
			return __( 'No orders found.', 'dukkan-plugin' );
		}

		$lines = array();
		foreach ( $orders as $order ) {
			$lines[] = sprintf(
				'#%1$d — %2$s — %3$s — %4$s',
				$order->get_id(),
				$order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
				wc_get_order_status_name( $order->get_status() ),
				html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) )
			);
		}

		return __( 'Recent orders:', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
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
		return __( 'Always reply in the same language the customer writes in. If the customer writes in Arabic, answer in Arabic; if English, answer in English.', 'dukkan-plugin' );
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
			$lines[] = sprintf(
				'- %1$s (ID %2$d, SKU %3$s) — %4$s — %5$s — %6$s',
				$p['name'],
				$p['product_id'],
				$p['sku'],
				html_entity_decode( wp_strip_all_tags( wc_price( $price ) ) ),
				$p['stock_status'],
				$p['categories']
			);
		}

		return __( 'Relevant products from this store (use these for recommendations and pricing; do not invent other products):', 'dukkan-plugin' ) . "\n" . implode( "\n", $lines );
	}

	/**
	 * Assemble the system prompt for a query.
	 *
	 * @since 1.0.27
	 * @param string $query User query (for retrieval).
	 * @return array{system: string, products: array}
	 */
	private function build_context( $query ) {
		$products = $this->search_products( $query, 6 );

		$store_instructions = $this->get_setting( 'system_prompt' );
		if ( '' === $store_instructions ) {
			$store_instructions = __( 'You are a helpful assistant for an online store. Help customers find products, answer questions about pricing and availability, and assist with their orders and loyalty points when available.', 'dukkan-plugin' );
		}

		$system = implode(
			"\n\n",
			array_filter(
				array(
					$store_instructions,
					$this->tone_instruction(),
					$this->language_instruction(),
					__( 'Only recommend products from the provided list. If no products match, say so honestly.', 'dukkan-plugin' ),
					$this->format_product_context( $products ),
				)
			)
		);

		return array(
			'system'   => $system,
			'products' => $products,
		);
	}

	// -------------------------------------------------------------------------
	// Message processing
	// -------------------------------------------------------------------------

	/**
	 * Process a chat turn: retrieval + DeepSeek + tools, returning the reply.
	 *
	 * @since 1.0.27
	 * @param array $history  Prior messages (array of {role, content}).
	 * @param string $message The new user message.
	 * @param int    $user_id Logged-in user ID.
	 * @return array{reply: string, products: array, handoff: bool}
	 */
	public function process_message( $history, $message, $user_id = 0 ) {
		$context = $this->build_context( $message );

		$messages   = array();
		$messages[] = array( 'role' => 'system', 'content' => $context['system'] );

		if ( is_array( $history ) ) {
			foreach ( array_slice( $history, -10 ) as $turn ) {
				$role    = isset( $turn['role'] ) && 'user' === $turn['role'] ? 'user' : 'assistant';
				$content = isset( $turn['content'] ) ? sanitize_text_field( $turn['content'] ) : '';
				if ( '' !== $content ) {
					$messages[] = array( 'role' => $role, 'content' => $content );
				}
			}
		}

		$messages[] = array( 'role' => 'user', 'content' => sanitize_text_field( $message ) );

		$tools = $this->get_tools( $user_id );

		$result = $this->call_deepseek( $messages, $tools );
		if ( is_wp_error( $result ) ) {
			return array(
				'reply'   => __( 'Sorry, I am having trouble right now. Please try again shortly.', 'dukkan-plugin' ),
				'products' => array(),
				'handoff' => false,
			);
		}

		// Function-calling loop.
		if ( ! empty( $result['tool_calls'] ) ) {
			$messages[] = $result;
			foreach ( $result['tool_calls'] as $tool_call ) {
				$name   = isset( $tool_call['function']['name'] ) ? $tool_call['function']['name'] : '';
				$args   = isset( $tool_call['function']['arguments'] ) ? json_decode( $tool_call['function']['arguments'], true ) : array();
				if ( ! is_array( $args ) ) {
					$args = array();
				}
				$tool_result = $this->execute_tool( $name, $args, $user_id );

				$messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => isset( $tool_call['id'] ) ? $tool_call['id'] : '',
					'content'      => $tool_result,
				);
			}

			$final = $this->call_deepseek( $messages, array() );
			if ( ! is_wp_error( $final ) && isset( $final['content'] ) ) {
				$result = $final;
			}
		}

		$reply = isset( $result['content'] ) ? $result['content'] : __( 'Sorry, I could not generate a response.', 'dukkan-plugin' );

		// Handoff detection: explicit ask for human.
		$handoff = false;
		if ( $this->get_setting( 'enable_handoff' ) && preg_match( '/human|agent|person|representative|support team|موظف|إنسان|شخص|الدعم/i', $message ) ) {
			$handoff = true;
		}

		return array(
			'reply'    => $reply,
			'products' => $this->products_for_widget( $context['products'] ),
			'handoff'  => $handoff,
		);
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
	 * Test DeepSeek + OpenAI connectivity.
	 *
	 * @since 1.0.27
	 * @return array{deepseek: bool|string, openai: bool|string}
	 */
	public function test_connection() {
		$result = array();

		$deepseek = $this->call_deepseek(
			array( array( 'role' => 'user', 'content' => 'Say "ok".' ) ),
			array()
		);
		$result['deepseek'] = is_wp_error( $deepseek ) ? $deepseek->get_error_message() : true;

		$embed = $this->embed_text( 'ping' );
		$result['openai'] = is_wp_error( $embed ) ? $embed->get_error_message() : true;

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
