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
		self::schedule_cron();
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

	/**
	 * Schedule the daily update-check cron on activation.
	 *
	 * Ensures the cron is set up immediately after plugin installation
	 * or reactivation, without waiting for the next admin page visit.
	 *
	 * @since 1.0.14
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'dukkan_plugin_daily_update_check' ) ) {
			wp_schedule_event(
				strtotime( 'tomorrow 01:00:00', current_time( 'timestamp' ) ),
				'daily',
				'dukkan_plugin_daily_update_check'
			);
		}
	}

}
