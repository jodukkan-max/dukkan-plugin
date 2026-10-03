<?php

/**
 * Fired during plugin activation
 *
 * @link       https://dukkanjo.com
 * @since      1.0.0
 *
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 */

/**
 * Fired during plugin activation.
 *
 * Seeds default custom order statuses into the options table when
 * the option does not already exist.
 *
 * @since      1.0.0
 * @package    Dukkan_Plugin
 * @subpackage Dukkan_Plugin/includes
 * @author     Dukkan Ecommerce LLC
 */
class Dukkan_Plugin_Activator {

	/**
	 * Run activation routines.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::seed_default_statuses();
		self::seed_loyalty_settings();
		self::create_loyalty_ledger_table();
		self::seed_chatbot_settings();
		self::create_chatbot_tables();
	}

	/**
	 * Seed default chatbot settings if none exist yet.
	 *
	 * @since 1.0.27
	 */
	private static function seed_chatbot_settings() {
		$defaults = array(
			'enabled'            => 0,
			'google_api_key'     => '',
			'gateway_url'        => '',
			'gateway_token'      => '',
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
			'enable_handoff'     => 1,
			'support_email'      => '',
			'rate_limit'         => 10,
			'memory_mode'        => 'session',
		);

		add_option( 'dukkan_chatbot_settings', $defaults, '', 'no' );
	}

	/**
	 * Create the chatbot product-index and conversation-log tables.
	 *
	 * @since 1.0.27
	 */
	private static function create_chatbot_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$products        = $wpdb->prefix . 'dukkan_chatbot_products';
		$categories      = $wpdb->prefix . 'dukkan_chatbot_categories';
		$pages           = $wpdb->prefix . 'dukkan_chatbot_pages';
		$orders          = $wpdb->prefix . 'dukkan_chatbot_orders';
		$log             = $wpdb->prefix . 'dukkan_chatbot_log';

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
	 * Seed default loyalty-points settings if none exist yet.
	 *
	 * Uses add_option() so existing data is never overwritten.
	 *
	 * @since 1.0.25
	 */
	private static function seed_loyalty_settings() {
		$defaults = array(
			'enabled'               => 0,
			'points_per_amount'     => 1,
			'amount'                => 1,
			'value_per_point'       => 0.01,
			'max_redeem_percent'    => 100,
			'excluded_product_ids'  => array(),
			'excluded_category_ids' => array(),
			'redeem_with_coupons'   => 1,
		);

		add_option( 'dukkan_loyalty_settings', $defaults, '', 'no' );
	}

	/**
	 * Create the loyalty points ledger table.
	 *
	 * @since 1.0.25
	 */
	private static function create_loyalty_ledger_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'dukkan_loyalty_ledger';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			order_id bigint(20) NOT NULL DEFAULT 0,
			points int(11) NOT NULL,
			type varchar(20) NOT NULL,
			note text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * The full set of default, LogesTechs-aligned order statuses.
	 *
	 * Single source of truth used by both fresh activation seeding and the
	 * idempotent `maybe_migrate_statuses()` migration.
	 *
	 * @since 1.0.46
	 * @return array<int, array{name: string, slug: string}>
	 */
	public static function get_default_statuses() {
		return array(
			array(
				'name' => __( 'Ready For Delivery', 'dukkan-plugin' ),
				'slug' => 'ready-delivery',
			),
			array(
				'name' => __( 'Out For Delivery', 'dukkan-plugin' ),
				'slug' => 'out-for-delivery',
			),
			array(
				'name' => __( 'With Carrier', 'dukkan-plugin' ),
				'slug' => 'with-carrier',
			),
			array(
				'name' => __( 'At Sorting Center', 'dukkan-plugin' ),
				'slug' => 'at-sorting-center',
			),
			array(
				'name' => __( 'Partially Delivered', 'dukkan-plugin' ),
				'slug' => 'partially-delivered',
			),
			array(
				'name' => __( 'Delivered To Sender', 'dukkan-plugin' ),
				'slug' => 'delivered-to-sender',
			),
		);
	}

	/**
	 * Seed default custom order statuses if none exist yet.
	 *
	 * Uses add_option() so existing data is never overwritten.
	 *
	 * @since 1.0.0
	 */
	private static function seed_default_statuses() {
		add_option( 'dukkan_custom_order_statuses', self::get_default_statuses(), '', 'yes' );
	}

	/**
	 * Idempotent migration: append any missing default statuses (matched by
	 * slug) to the existing `dukkan_custom_order_statuses` option. Never
	 * removes or renames user-created statuses. Safe to run on every load.
	 *
	 * @since 1.0.46
	 */
	public static function maybe_migrate_statuses() {
		$existing = get_option( 'dukkan_custom_order_statuses', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$existing_slugs = array();
		foreach ( $existing as $status ) {
			if ( is_array( $status ) && isset( $status['slug'] ) ) {
				$existing_slugs[ sanitize_title( $status['slug'] ) ] = true;
			}
		}

		$changed = false;
		foreach ( self::get_default_statuses() as $default ) {
			$slug = sanitize_title( $default['slug'] );
			if ( ! isset( $existing_slugs[ $slug ] ) ) {
				$existing[] = array(
					'name' => $default['name'],
					'slug' => $slug,
				);
				$existing_slugs[ $slug ] = true;
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( 'dukkan_custom_order_statuses', $existing, 'no' );
		}
	}

}
