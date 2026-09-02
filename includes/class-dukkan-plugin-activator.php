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
	 * Seed default custom order statuses if none exist yet.
	 *
	 * Uses add_option() so existing data is never overwritten.
	 *
	 * @since 1.0.0
	 */
	private static function seed_default_statuses() {
		$defaults = array(
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
		);

		add_option( 'dukkan_custom_order_statuses', $defaults, '', 'yes' );
	}

}
